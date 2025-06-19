<?php
if ( ! defined( 'MAIO_PLUGIN_URL' ) ) {
    define( 'MAIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'MAIO_VERSION' ) ) {
    define( 'MAIO_VERSION', '1.0.0' );
}

function maio_activate() {
    if ( get_option( 'maio_api_key' ) === false ) {
        add_option( 'maio_api_key', '' );
    }
    if ( get_option( 'maio_api_endpoint' ) === false ) {
        add_option( 'maio_api_endpoint', 'https://openrouter.ai/api/v1/chat/completions' );
    }
}

add_action( 'admin_menu', 'maio_add_admin_menu' );
function maio_add_admin_menu() {
    add_menu_page(
        __( 'Meta AI Optimizer', 'meta-ai-optimizer' ),
        __( 'Meta AI Optimizer', 'meta-ai-optimizer' ),
        'manage_options',
        MAIO_SETTINGS_PAGE,
        'maio_settings_page_html',
        'dashicons-admin-generic',
        81
    );
}

add_action( 'admin_init', 'maio_settings_init' );
function maio_settings_init() {
    register_setting( 'maio_settings_group', 'maio_api_key' );
    register_setting( 'maio_settings_group', 'maio_api_endpoint' );

    add_settings_section(
        'maio_settings_section',
        __( 'OpenAI / OpenRouter Settings', 'meta-ai-optimizer' ),
        'maio_settings_section_cb',
        MAIO_SETTINGS_PAGE
    );

    add_settings_field(
        'maio_api_key_field',
        __( 'API Key', 'meta-ai-optimizer' ),
        'maio_api_key_field_cb',
        MAIO_SETTINGS_PAGE,
        'maio_settings_section'
    );

    add_settings_field(
        'maio_api_endpoint_field',
        __( 'API Endpoint', 'meta-ai-optimizer' ),
        'maio_api_endpoint_field_cb',
        MAIO_SETTINGS_PAGE,
        'maio_settings_section'
    );
}

function maio_settings_section_cb() {
    echo '<p>' . esc_html__( 'Enter your API credentials for OpenRouter or OpenAI.', 'meta-ai-optimizer' ) . '</p>';
}

function maio_api_key_field_cb() {
    $key = esc_attr( get_option( 'maio_api_key' ) );
    printf( '<input type="text" name="maio_api_key" value="%s" class="regular-text" />', $key );
}

function maio_api_endpoint_field_cb() {
    $endpoint = esc_attr( get_option( 'maio_api_endpoint' ) );
    printf( '<input type="text" name="maio_api_endpoint" value="%s" class="regular-text" />', $endpoint );
}

function maio_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'maio_settings_group' );
            do_settings_sections( MAIO_SETTINGS_PAGE );
            submit_button( __( 'Save Settings', 'meta-ai-optimizer' ) );
            ?>
        </form>
    </div>
    <?php
}

add_action( 'admin_enqueue_scripts', 'maio_enqueue_admin_assets' );
function maio_enqueue_admin_assets( $hook ) {
    if ( $hook === 'toplevel_page_' . MAIO_SETTINGS_PAGE || in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        wp_enqueue_style( 'maio-admin-css', MAIO_PLUGIN_URL . 'assets/css/admin.css', [], MAIO_VERSION );
        wp_enqueue_script( 'maio-admin-js', MAIO_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], MAIO_VERSION, true );
        wp_localize_script( 'maio-admin-js', 'maioData', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'maio_nonce' ),
            'apiEndpoint'  => esc_url_raw( get_option( 'maio_api_endpoint' ) ),
            'apiKey'       => esc_attr( get_option( 'maio_api_key' ) ),
        ] );
    }
}

add_action( 'wp_ajax_maio_generate_meta', 'maio_generate_meta_callback' );
function maio_generate_meta_callback() {
    check_ajax_referer( 'maio_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( __( 'Permission denied', 'meta-ai-optimizer' ), 403 );
    }
    $post_id = intval( $_POST['post_id'] );
    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( __( 'Post not found', 'meta-ai-optimizer' ), 404 );
    }
    $content = $post->post_content;
    $prompt = "Generate an SEO-optimized title and meta description for the following content:\n\n" . wp_strip_all_tags( $content );
    $response = maio_call_api( $prompt );
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message(), 500 );
    }
    wp_send_json_success( $response );
}

add_action( 'wp_ajax_maio_apply_meta', 'maio_apply_meta_callback' );
function maio_apply_meta_callback() {
    check_ajax_referer( 'maio_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( __( 'Permission denied', 'meta-ai-optimizer' ), 403 );
    }
    $post_id = intval( $_POST['post_id'] );
    $title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
    $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ) );
    $updated = wp_update_post( [
        'ID'         => $post_id,
        'post_title' => $title,
        'post_excerpt' => $description,
    ], true );
    if ( is_wp_error( $updated ) ) {
        wp_send_json_error( $updated->get_error_message(), 500 );
    }
    wp_send_json_success();
}

add_action( 'wp_ajax_maio_bulk_apply_meta', 'maio_bulk_apply_meta_callback' );
function maio_bulk_apply_meta_callback() {
    check_ajax_referer( 'maio_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( __( 'Permission denied', 'meta-ai-optimizer' ), 403 );
    }
    $items = isset( $_POST['items'] ) ? $_POST['items'] : [];
    foreach ( $items as $item ) {
        $post_id     = intval( $item['post_id'] );
        $title       = sanitize_text_field( wp_unslash( $item['title'] ) );
        $description = sanitize_textarea_field( wp_unslash( $item['description'] ) );
        wp_update_post( [
            'ID'           => $post_id,
            'post_title'   => $title,
            'post_excerpt' => $description,
        ] );
    }
    wp_send_json_success();
}

function maio_call_api( $prompt ) {
    $endpoint = get_option( 'maio_api_endpoint' );
    $api_key  = get_option( 'maio_api_key' );
    $body = [
        'model'     => 'gpt-3.5-turbo',
        'messages'  => [
            [ 'role' => 'system', 'content' => 'You are an SEO assistant.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ],
        'temperature' => 0.7,
    ];
    $headers = [
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
    ];
    $response = wp_remote_post( $endpoint, [
        'headers' => $headers,
        'body'    => wp_json_encode( $body ),
        'timeout' => 60,
    ] );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    if ( $code !== 200 ) {
        return new WP_Error( 'api_error', __( 'API error: ', 'meta-ai-optimizer' ) . $body );
    }
    $data = json_decode( $body, true );
    if ( isset( $data['choices'][0]['message']['content'] ) ) {
        $content = trim( $data['choices'][0]['message']['content'] );
        $lines = preg_split( '/\r\n|\r|\n/', $content );
        $result = [ 'title' => '', 'description' => '' ];
        foreach ( $lines as $line ) {
            if ( stripos( $line, 'title:' ) === 0 ) {
                $result['title'] = trim( substr( $line, 6 ) );
            } elseif ( stripos( $line, 'description:' ) === 0 ) {
                $result['description'] = trim( substr( $line, 12 ) );
            }
        }
        return $result;
    }
    return new WP_Error( 'invalid_response', __( 'Invalid API response', 'meta-ai-optimizer' ) );
}