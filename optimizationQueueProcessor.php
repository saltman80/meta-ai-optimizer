<?php

namespace MetaAiOptimizer;

use Logger;

class optimizationQueueProcessor
{
    const QUEUE_OPTION = 'mao_optimization_queue';
    const META_DESCRIPTION_KEY = 'mao_meta_description';

    public function __construct() {
        if ( false === get_option( self::QUEUE_OPTION ) ) {
            add_option( self::QUEUE_OPTION, array(), '', 'no' );
        }
    }

    public function enqueue( $postIds ) {
        if ( ! is_array( $postIds ) ) {
            $postIds = array( $postIds );
        }
        $postIds = array_map( 'absint', $postIds );
        $postIds = array_filter( $postIds );
        if ( empty( $postIds ) ) {
            return false;
        }
        $queue = $this->getQueue();
        $added = false;
        foreach ( $postIds as $id ) {
            if ( ! in_array( $id, $queue, true ) ) {
                $queue[] = $id;
                $added = true;
            }
        }
        if ( $added ) {
            update_option( self::QUEUE_OPTION, $queue );
            Logger::log( 'Enqueued posts: ' . implode( ',', $postIds ) );
        }
        return $added;
    }

    public function processNext() {
        $id = $this->getNextId();
        if ( ! $id ) {
            return null;
        }
        $post = get_post( $id );
        if ( ! $post ) {
            Logger::log( "Post not found: {$id}" );
            return null;
        }
        try {
            $suggestion = AIHelper::generate( array(
                'title'   => $post->post_title,
                'content' => $post->post_content,
                'type'    => $post->post_type,
            ) );
        } catch ( Exception $e ) {
            Logger::log( "AI generation failed for post {$id}: " . $e->getMessage() );
            $this->enqueue( $id );
            return null;
        }
        return array(
            'id'         => $id,
            'suggestion' => $suggestion,
        );
    }

    public function apply( $id, $suggestion ) {
        $id = absint( $id );
        if ( ! $id || ! is_array( $suggestion ) ) {
            return false;
        }
        $updated = false;
        $update_args = array( 'ID' => $id );
        if ( isset( $suggestion['title'] ) ) {
            $update_args['post_title'] = sanitize_text_field( $suggestion['title'] );
            $updated = true;
        }
        if ( $updated ) {
            wp_update_post( $update_args );
        }
        if ( isset( $suggestion['meta_description'] ) ) {
            $desc = sanitize_textarea_field( $suggestion['meta_description'] );
            update_post_meta( $id, self::META_DESCRIPTION_KEY, $desc );
            $updated = true;
        }
        if ( $updated ) {
            Logger::logChange( $id, $suggestion );
        }
        return $updated;
    }

    protected function getQueue() {
        $queue = get_option( self::QUEUE_OPTION, array() );
        if ( ! is_array( $queue ) ) {
            $queue = array();
        }
        return array_map( 'absint', $queue );
    }

    protected function setQueue( $queue ) {
        $queue = array_map( 'absint', (array) $queue );
        update_option( self::QUEUE_OPTION, $queue );
    }

    protected function getNextId() {
        if ( ! $this->acquireLock() ) {
            return false;
        }
        try {
            $queue = $this->getQueue();
            if ( empty( $queue ) ) {
                return false;
            }
            $next = array_shift( $queue );
            $this->setQueue( $queue );
        } finally {
            $this->releaseLock();
        }
        return isset( $next ) ? $next : false;
    }

    protected function acquireLock() {
        $lock_key = self::QUEUE_OPTION . '_lock';
        return (bool) wp_cache_add( $lock_key, 1, '', 30 );
    }

    protected function releaseLock() {
        $lock_key = self::QUEUE_OPTION . '_lock';
        wp_cache_delete( $lock_key, '' );
    }
}