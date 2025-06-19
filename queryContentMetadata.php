const DEFAULT_POST_TYPES      = array( 'post', 'page' );
    const DEFAULT_POST_STATUS     = 'publish';
    const DEFAULT_POSTS_PER_PAGE  = 100;
    const MAX_POSTS_PER_PAGE      = 500;
    const ALLOWED_QUERY_ARGS      = array( 'post_type', 'post_status', 'posts_per_page', 'orderby', 'order' );
    const ALLOWED_POST_STATUSES   = array( 'publish', 'pending', 'draft', 'private', 'inherit', 'future', 'any' );
    const ALLOWED_ORDERBYS        = array( 'date', 'title', 'name', 'ID', 'author', 'rand', 'menu_order' );
    const ALLOWED_ORDER           = array( 'ASC', 'DESC' );

    /**
     * Scan posts and retrieve metadata.
     *
     * @param array $args {
     *     Optional. Array of arguments to customize the query.
     *
     *     @type array|string $post_type       Post type or array of post types. Default ['post','page'].
     *     @type string       $post_status     Post status. Default 'publish'.
     *     @type int          $posts_per_page  Number of posts per batch/page. Default 100.
     *     @type string       $orderby         Field to order by. Default 'date'.
     *     @type string       $order           Order direction. 'ASC' or 'DESC'. Default 'DESC'.
     *     @type array        $meta_keys       List of meta keys to retrieve. Default empty (all).
     * }
     * @return array List of posts with ID, title, and metadata.
     */
    public function scan( $args = array() ) {
        if ( ! is_array( $args ) ) {
            return array();
        }

        // Extract and sanitize meta_keys separately.
        $meta_keys = array();
        if ( isset( $args['meta_keys'] ) ) {
            if ( is_array( $args['meta_keys'] ) ) {
                $meta_keys = array_filter( array_map( 'sanitize_key', $args['meta_keys'] ) );
            }
            unset( $args['meta_keys'] );
        }

        // Build query args with whitelist and defaults.
        $query_args = array();

        // Post type.
        if ( isset( $args['post_type'] ) ) {
            $pt = $args['post_type'];
            if ( is_array( $pt ) ) {
                $query_args['post_type'] = array_map( 'sanitize_key', $pt );
            } else {
                $query_args['post_type'] = sanitize_key( $pt );
            }
        } else {
            $query_args['post_type'] = self::DEFAULT_POST_TYPES;
        }

        // Post status.
        if ( isset( $args['post_status'] ) && in_array( $args['post_status'], self::ALLOWED_POST_STATUSES, true ) ) {
            $query_args['post_status'] = $args['post_status'];
        } else {
            $query_args['post_status'] = self::DEFAULT_POST_STATUS;
        }

        // Posts per page.
        if ( isset( $args['posts_per_page'] ) ) {
            $ppp = absint( $args['posts_per_page'] );
            if ( $ppp < 1 ) {
                $ppp = self::DEFAULT_POSTS_PER_PAGE;
            } elseif ( $ppp > self::MAX_POSTS_PER_PAGE ) {
                $ppp = self::MAX_POSTS_PER_PAGE;
            }
            $query_args['posts_per_page'] = $ppp;
        } else {
            $query_args['posts_per_page'] = self::DEFAULT_POSTS_PER_PAGE;
        }

        // Order by.
        if ( isset( $args['orderby'] ) && in_array( $args['orderby'], self::ALLOWED_ORDERBYS, true ) ) {
            $query_args['orderby'] = $args['orderby'];
        }

        // Order.
        if ( isset( $args['order'] ) ) {
            $ord = strtoupper( $args['order'] );
            if ( in_array( $ord, self::ALLOWED_ORDER, true ) ) {
                $query_args['order'] = $ord;
            }
        }

        $results = array();
        $paged   = 1;

        do {
            $query_args['paged'] = $paged;
            $query               = new WP_Query( $query_args );

            if ( ! $query->have_posts() ) {
                break;
            }

            foreach ( $query->posts as $post ) {
                $post_id = $post->ID;
                $meta    = array();

                if ( empty( $meta_keys ) ) {
                    // Fetch all meta.
                    $meta_raw = get_post_meta( $post_id );
                } else {
                    // Fetch specified keys only.
                    $meta_raw = array();
                    foreach ( $meta_keys as $key ) {
                        $values = get_post_meta( $post_id, $key, false );
                        if ( ! empty( $values ) ) {
                            $meta_raw[ $key ] = $values;
                        }
                    }
                }

                foreach ( $meta_raw as $key => $values ) {
                    if ( count( $values ) === 1 ) {
                        $meta[ $key ] = maybe_unserialize( $values[0] );
                    } else {
                        $meta[ $key ] = array_map( 'maybe_unserialize', $values );
                    }
                }

                $results[] = array(
                    'ID'         => $post_id,
                    'post_title' => get_the_title( $post_id ),
                    'meta'       => $meta,
                );
            }

            $max_pages = $query->max_num_pages;
            wp_reset_postdata();
            $paged++;
        } while ( $paged <= $max_pages );

        return $results;
    }
}