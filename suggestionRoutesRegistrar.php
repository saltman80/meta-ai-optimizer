<?php

if ( ! class_exists( 'SuggestionController' ) ) {
    class SuggestionController {
        public static function get_suggestions( $post_id ) {
            return [];
        }
        public static function generate_suggestions( $post_id, $options ) {
            return [];
        }
        public static function apply_suggestion( $post_id, $type, $content ) {
            return true;
        }
        public static function apply_bulk_suggestions( $items ) {
            return [];
        }
    }
}

class SuggestionRoutesRegistrar {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        $namespace = 'meta-ai-optimizer/v1';

        register_rest_route( $namespace, '/suggestions/(?P<post_id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_suggestions' ],
                'permission_callback' => [ __CLASS__, 'permission_check' ],
                'args'                => [
                    'post_id' => [
                        'required'          => true,
                        'validate_callback' => [ __CLASS__, 'validate_positive_int' ],
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'generate_suggestions' ],
                'permission_callback' => [ __CLASS__, 'permission_check' ],
                'args'                => [
                    'post_id' => [
                        'required'          => true,
                        'validate_callback' => [ __CLASS__, 'validate_positive_int' ],
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ] );

        register_rest_route( $namespace, '/suggestions/(?P<post_id>\d+)/apply', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'apply_suggestion' ],
            'permission_callback' => [ __CLASS__, 'permission_check' ],
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'validate_callback' => [ __CLASS__, 'validate_positive_int' ],
                    'sanitize_callback' => 'absint',
                ],
                'type'    => [
                    'required'          => true,
                    'validate_callback' => [ __CLASS__, 'validate_type' ],
                    'sanitize_callback' => [ __CLASS__, 'sanitize_type' ],
                ],
                'content' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        register_rest_route( $namespace, '/suggestions/bulk-apply', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'apply_bulk_suggestions' ],
            'permission_callback' => [ __CLASS__, 'bulk_permission_check' ],
            'args'                => [
                'items' => [
                    'required'          => true,
                    'validate_callback' => [ __CLASS__, 'validate_items_array' ],
                    'sanitize_callback' => [ __CLASS__, 'sanitize_items_array' ],
                ],
            ],
        ] );
    }

    public static function validate_positive_int( $param ) {
        return ( is_int( $param ) || ctype_digit( strval( $param ) ) ) && (int) $param > 0;
    }

    public static function validate_type( $param ) {
        return in_array( $param, [ 'title', 'meta_description' ], true );
    }

    public static function sanitize_type( $param ) {
        return self::validate_type( $param ) ? $param : '';
    }

    public static function validate_items_array( $param ) {
        if ( ! is_array( $param ) ) {
            return false;
        }
        foreach ( $param as $item ) {
            if (
                empty( $item['post_id'] ) ||
                ! ( is_int( $item['post_id'] ) || ctype_digit( strval( $item['post_id'] ) ) ) ||
                (int) $item['post_id'] <= 0 ||
                empty( $item['type'] ) ||
                ! in_array( $item['type'], [ 'title', 'meta_description' ], true ) ||
                ! isset( $item['content'] )
            ) {
                return false;
            }
        }
        return true;
    }

    public static function sanitize_items_array( $param ) {
        $sanitized = [];
        foreach ( $param as $item ) {
            $post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : 0;
            $type    = isset( $item['type'] ) ? self::sanitize_type( $item['type'] ) : '';
            $content = isset( $item['content'] ) ? sanitize_text_field( $item['content'] ) : '';
            $sanitized[] = [
                'post_id' => $post_id,
                'type'    => $type,
                'content' => $content,
            ];
        }
        return $sanitized;
    }

    public static function permission_check( \WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            return new \WP_Error( 'rest_post_invalid_id', 'Invalid post ID.', [ 'status' => 404 ] );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new \WP_Error( 'rest_forbidden', 'You do not have permission to edit this post.', [ 'status' => 403 ] );
        }
        return true;
    }

    public static function bulk_permission_check( \WP_REST_Request $request ) {
        $items = $request->get_param( 'items' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'rest_forbidden', 'You do not have permission to perform bulk apply.', [ 'status' => 403 ] );
        }
        foreach ( $items as $item ) {
            $post_id = (int) $item['post_id'];
            if ( ! $post_id || ! get_post( $post_id ) ) {
                return new \WP_Error( 'rest_post_invalid_id', "Invalid post ID: {$post_id}.", [ 'status' => 404 ] );
            }
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return new \WP_Error( 'rest_forbidden', "You do not have permission to edit post {$post_id}.", [ 'status' => 403 ] );
            }
        }
        return true;
    }

    public static function get_suggestions( \WP_REST_Request $request ) {
        $post_id     = (int) $request->get_param( 'post_id' );
        $suggestions = SuggestionController::get_suggestions( $post_id );
        if ( is_wp_error( $suggestions ) ) {
            return $suggestions;
        }
        return rest_ensure_response( $suggestions );
    }

    public static function generate_suggestions( \WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $body    = $request->get_json_params();
        $options = [
            'generate_title'            => ! empty( $body['generate_title'] ),
            'generate_meta_description' => ! empty( $body['generate_meta_description'] ),
        ];
        $result = SuggestionController::generate_suggestions( $post_id, $options );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( $result );
    }

    public static function apply_suggestion( \WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        $type    = $request->get_param( 'type' );
        $content = $request->get_param( 'content' );
        $result  = SuggestionController::apply_suggestion( $post_id, $type, $content );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( [ 'success' => true ] );
    }

    public static function apply_bulk_suggestions( \WP_REST_Request $request ) {
        $items   = $request->get_param( 'items' );
        $results = SuggestionController::apply_bulk_suggestions( $items );
        if ( is_wp_error( $results ) ) {
            return $results;
        }
        return rest_ensure_response( $results );
    }
}

SuggestionRoutesRegistrar::init();