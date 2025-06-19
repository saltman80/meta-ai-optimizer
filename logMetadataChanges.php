<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Logger {
    public static function logChange( $post_id, $old, $new ) {
        global $wpdb;

        // Validate and sanitize post ID.
        $post_id = absint( $post_id );
        if ( ! $post_id ) {
            return false;
        }

        $table_name = $wpdb->prefix . 'mao_logs';
        $timestamp  = current_time( 'mysql', 1 );

        // Prepare old value.
        if ( is_scalar( $old ) ) {
            $old_value = sanitize_text_field( (string) $old );
        } else {
            $encoded_old = wp_json_encode( $old );
            if ( false === $encoded_old ) {
                // Fallback to a sanitized print_r if encoding fails.
                $old_value = sanitize_textarea_field( print_r( $old, true ) );
            } else {
                $old_value = $encoded_old;
            }
        }

        // Prepare new value.
        if ( is_scalar( $new ) ) {
            $new_value = sanitize_text_field( (string) $new );
        } else {
            $encoded_new = wp_json_encode( $new );
            if ( false === $encoded_new ) {
                // Fallback to a sanitized print_r if encoding fails.
                $new_value = sanitize_textarea_field( print_r( $new, true ) );
            } else {
                $new_value = $encoded_new;
            }
        }

        // Enforce maximum length to fit within TEXT column limits (max 65535 bytes).
        $max_length = 65535;
        if ( strlen( $old_value ) > $max_length ) {
            $old_value = mb_substr( $old_value, 0, $max_length );
        }
        if ( strlen( $new_value ) > $max_length ) {
            $new_value = mb_substr( $new_value, 0, $max_length );
        }

        $data = array(
            'postId'    => $post_id,
            'old'       => $old_value,
            'new'       => $new_value,
            'timestamp' => $timestamp,
        );

        $format = array( '%d', '%s', '%s', '%s' );

        $inserted = $wpdb->insert( $table_name, $data, $format );

        return (bool) $inserted;
    }

    public static function log( $post_id, $old, $new ) {
        return self::logChange( $post_id, $old, $new );
    }
}