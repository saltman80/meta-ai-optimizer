public static function init() {
            add_action( 'wp_ajax_maio_add_missing_ai_file', array( __CLASS__, 'handle_ajax' ) );
        }

        public static function handle_ajax() {
            check_ajax_referer( 'maio_nonce', 'nonce' );

            $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
            if ( ! $post_id ) {
                wp_send_json_error( array(
                    'message' => __( 'Invalid post ID.', 'meta-ai-optimizer' ),
                ) );
            }

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                wp_send_json_error( array(
                    'message' => __( 'You are not allowed to edit this post.', 'meta-ai-optimizer' ),
                ) );
            }

            $post = get_post( $post_id );
            if ( ! $post || 'trash' === $post->post_status ) {
                wp_send_json_error( array(
                    'message' => __( 'Post not found or invalid status.', 'meta-ai-optimizer' ),
                ) );
            }

            if ( self::add_missing_ai_file( $post_id ) ) {
                wp_send_json_success();
            } else {
                wp_send_json_error( array(
                    'message' => __( 'Failed to create AI data file.', 'meta-ai-optimizer' ),
                ) );
            }
        }

        public static function add_missing_ai_file( $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post || 'trash' === $post->post_status ) {
                return false;
            }

            $upload = wp_upload_dir();
            if ( isset( $upload['error'] ) && $upload['error'] ) {
                return false;
            }

            $dir = trailingslashit( $upload['basedir'] ) . 'meta-ai-optimizer';
            if ( ! file_exists( $dir ) ) {
                if ( ! wp_mkdir_p( $dir ) ) {
                    return false;
                }
            }

            // Prevent direct access
            $htaccess = $dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                $rules = "Order allow,deny\nDeny from all";
                @file_put_contents( $htaccess, $rules, LOCK_EX );
            }

            $file = $dir . '/post_' . $post_id . '.json';
            if ( ! file_exists( $file ) ) {
                $data = array(
                    'ai_title'       => '',
                    'ai_description' => '',
                    'generated_at'   => current_time( 'mysql' ),
                );
                $json = wp_json_encode( $data, JSON_PRETTY_PRINT );
                if ( false === @file_put_contents( $file, $json, LOCK_EX ) ) {
                    return false;
                }
            }

            return true;
        }
    }

    Maio_Add_Missing_Ai_File::init();
}