<?php
function mao_render_api_key_model_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'meta-ai-optimizer' ) );
    }

    // Define allowed AI models.
    $available_models = array(
        'gpt-3.5-turbo' => esc_html__( 'GPT-3.5 Turbo', 'meta-ai-optimizer' ),
        'gpt-4'         => esc_html__( 'GPT-4', 'meta-ai-optimizer' ),
        // Add additional supported models here.
    );

    if ( isset( $_POST['mao_settings_submit'] ) ) {
        check_admin_referer( 'mao_api_model_settings' );

        // Preserve API key characters, strip tags.
        $api_key = sanitize_textarea_field( wp_unslash( $_POST['mao_api_key'] ?? '' ) );

        // Sanitize and validate model selection.
        $model = sanitize_text_field( wp_unslash( $_POST['mao_model'] ?? '' ) );
        if ( ! array_key_exists( $model, $available_models ) ) {
            $model = get_option( 'meta_ai_optimizer_model', '' );
        }

        // Store options without autoload to limit exposure.
        update_option( 'meta_ai_optimizer_api_key', $api_key, false );
        update_option( 'meta_ai_optimizer_model',   $model,   false );

        add_settings_error(
            'mao_settings_messages',
            'mao_settings_saved',
            esc_html__( 'Settings saved.', 'meta-ai-optimizer' ),
            'updated'
        );
    }

    settings_errors( 'mao_settings_messages' );

    $api_key = get_option( 'meta_ai_optimizer_api_key', '' );
    $model   = get_option( 'meta_ai_optimizer_model', '' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Meta AI Optimizer Settings', 'meta-ai-optimizer' ); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'mao_api_model_settings' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="mao_api_key"><?php esc_html_e( 'API Key', 'meta-ai-optimizer' ); ?></label>
                    </th>
                    <td>
                        <textarea
                            name="mao_api_key"
                            id="mao_api_key"
                            class="large-text code"
                            rows="2"
                            autocomplete="off"
                        ><?php echo esc_html( $api_key ); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mao_model"><?php esc_html_e( 'AI Model', 'meta-ai-optimizer' ); ?></label>
                    </th>
                    <td>
                        <select name="mao_model" id="mao_model">
                            <?php foreach ( $available_models as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $model, $key ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button( esc_html__( 'Save Changes', 'meta-ai-optimizer' ), 'primary', 'mao_settings_submit' ); ?>
        </form>
    </div>
    <?php
}
?>