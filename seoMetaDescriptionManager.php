<?php

if ( ! class_exists( 'SeoMetaDescriptionManager' ) ) {
    class SeoMetaDescriptionManager {
        /**
         * Meta key for storing the description.
         *
         * @var string
         */
        protected $meta_key = 'maio_meta_description';

        /**
         * Option name where plugin settings are stored.
         *
         * @var string
         */
        protected $option_name = 'maio_settings';

        /**
         * Constructor: hook into WordPress.
         */
        public function __construct() {
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
            add_action( 'save_post', array( $this, 'save_meta_description' ), 10, 2 );
            add_action( 'wp_ajax_maio_generate_meta_description', array( $this, 'ajax_generate_meta_description' ) );
        }

        /**
         * Enqueue JS and CSS on post edit screens.
         *
         * @param string $hook Current admin page.
         */
        public function enqueue_scripts( $hook ) {
            if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
                return;
            }
            global $post;
            $post_id = isset( $post->ID ) ? $post->ID : 0;

            wp_enqueue_script(
                'seo-meta-manager-script',
                plugin_dir_url( __FILE__ ) . 'js/seo-meta-manager.js',
                array( 'jquery' ),
                '1.0.0',
                true
            );
            wp_localize_script(
                'seo-meta-manager-script',
                'SeoMetaManager',
                array(
                    'ajax_url'   => admin_url( 'admin-ajax.php' ),
                    'nonce'      => wp_create_nonce( 'maio_generate_meta_description' ),
                    'post_id'    => $post_id,
                    'generating' => __( 'Generating...', 'meta-ai-optimizer' ),
                )
            );
            wp_enqueue_style(
                'seo-meta-manager-style',
                plugin_dir_url( __FILE__ ) . 'css/seo-meta-manager.css',
                array(),
                '1.0.0'
            );
        }

        /**
         * Register the meta box.
         */
        public function add_meta_box() {
            $screens = array( 'post', 'page' );
            foreach ( $screens as $screen ) {
                add_meta_box(
                    'maio_meta_description',
                    __( 'AI Meta Description', 'meta-ai-optimizer' ),
                    array( $this, 'render_meta_box' ),
                    $screen,
                    'normal',
                    'default'
                );
            }
        }

        /**
         * Render the meta box HTML.
         *
         * @param WP_Post $post The post object.
         */
        public function render_meta_box( $post ) {
            wp_nonce_field( 'save_meta_description', 'maio_meta_description_nonce' );
            $value = get_post_meta( $post->ID, $this->meta_key, true );
            echo '<div id="maio_meta_description_container" data-post-id="' . esc_attr( $post->ID ) . '">';
            echo '<textarea style="width:100%" id="maio_meta_description" name="maio_meta_description" rows="3">' . esc_textarea( $value ) . '</textarea>';
            echo '<p><button type="button" class="button" id="maio_generate_meta_description">' . esc_html__( 'Generate with AI', 'meta-ai-optimizer' ) . '</button></p>';
            echo '<div id="maio_meta_description_suggestions" style="margin-top:10px;"></div>';
            echo '</div>';
        }

        /**
         * Save the meta description when the post is saved.
         *
         * @param int     $post_id The post ID.
         * @param WP_Post $post    The post object.
         */
        public function save_meta_description( $post_id, $post ) {
            if ( ! isset( $_POST['maio_meta_description_nonce'] ) ) {
                return;
            }
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maio_meta_description_nonce'] ) ), 'save_meta_description' ) ) {
                return;
            }
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }
            if ( wp_is_post_revision( $post_id ) ) {
                return;
            }
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }
            if ( isset( $_POST['maio_meta_description'] ) ) {
                $meta = sanitize_text_field( wp_unslash( $_POST['maio_meta_description'] ) );
                update_post_meta( $post_id, $this->meta_key, $meta );
            }
        }

        /**
         * Handle AJAX request to generate meta description suggestions.
         */
        public function ajax_generate_meta_description() {
            check_ajax_referer( 'maio_generate_meta_description', 'nonce' );
            if ( ! current_user_can( 'edit_posts' ) ) {
                wp_send_json_error( __( 'Unauthorized', 'meta-ai-optimizer' ), 403 );
            }
            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
            if ( ! $post_id ) {
                wp_send_json_error( __( 'Invalid post ID', 'meta-ai-optimizer' ), 400 );
            }
            $post = get_post( $post_id );
            if ( ! $post ) {
                wp_send_json_error( __( 'Post not found', 'meta-ai-optimizer' ), 404 );
            }
            $content = $post->post_content;
            $suggestions = $this->generate_with_ai( $content );
            if ( is_wp_error( $suggestions ) ) {
                wp_send_json_error( $suggestions->get_error_message(), 500 );
            }
            wp_send_json_success( $suggestions );
        }

        /**
         * Call the AI API to generate meta descriptions.
         *
         * @param string $content Post content.
         * @return array|WP_Error Array of suggestions or WP_Error.
         */
        private function generate_with_ai( $content ) {
            $opts   = get_option( $this->option_name, array() );
            $api_key = isset( $opts['api_key'] ) ? trim( $opts['api_key'] ) : '';
            $model   = isset( $opts['model'] ) ? $opts['model'] : 'gpt-4';

            if ( empty( $api_key ) ) {
                return new WP_Error( 'no_api_key', __( 'API key not set', 'meta-ai-optimizer' ) );
            }

            $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
            $body = array(
                'model'       => $model,
                'messages'    => array(
                    array( 'role' => 'system', 'content' => 'You are an expert SEO assistant. Generate 3 concise and engaging meta description suggestions.' ),
                    array( 'role' => 'user',   'content' => wp_strip_all_tags( $content ) ),
                ),
                'max_tokens'  => 60,
                'temperature' => 0.7,
            );
            $args = array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 30,
            );
            $response = wp_remote_post( $endpoint, $args );
            if ( is_wp_error( $response ) ) {
                return $response;
            }
            $code = wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( $code !== 200 || empty( $data['choices'] ) ) {
                $message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'AI generation failed', 'meta-ai-optimizer' );
                return new WP_Error( 'api_error', $message );
            }
            $suggestions = array();
            foreach ( $data['choices'] as $choice ) {
                $text = trim( $choice['message']['content'] );
                $lines = preg_split( '/\r\n|\n/', $text );
                foreach ( $lines as $line ) {
                    $line = trim( preg_replace( '/^[\-\d\.\)\s]+/', '', $line ) );
                    if ( $line ) {
                        $suggestions[] = $line;
                    }
                }
            }
            return array_slice( array_unique( $suggestions ), 0, 3 );
        }
    }

    new SeoMetaDescriptionManager();
}