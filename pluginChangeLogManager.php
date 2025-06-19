<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PluginChangeLogManager {
    const OPTION_VERSION    = 'meta_ai_optimizer_version';
    const USER_META_VERSION = 'meta_ai_optimizer_changelog_version';
    const AJAX_ACTION       = 'meta_ai_optimizer_dismiss_changelog';
    const NONCE_ACTION      = 'meta_ai_optimizer_dismiss_changelog';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'check_plugin_version' ) );
        add_action( 'admin_notices', array( __CLASS__, 'maybe_display_changelog' ) );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_dismiss_changelog' ) );
    }

    public static function check_plugin_version() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! function_exists( 'get_file_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = plugin_dir_path( __FILE__ ) . 'pluginmain.php';
        $data        = get_file_data( $plugin_file, array( 'Version' => 'Version' ) );
        $current     = isset( $data['Version'] ) ? $data['Version'] : '';
        $stored      = get_option( self::OPTION_VERSION, '' );

        if ( $stored !== $current ) {
            update_option( self::OPTION_VERSION, $current );
        }
    }

    public static function maybe_display_changelog() {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! function_exists( 'get_file_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Get current plugin version
        $plugin_file = plugin_dir_path( __FILE__ ) . 'pluginmain.php';
        $data        = get_file_data( $plugin_file, array( 'Version' => 'Version' ) );
        $current     = isset( $data['Version'] ) ? $data['Version'] : '';

        // Check if this user has already seen this version
        $user_id      = get_current_user_id();
        $user_version = get_user_meta( $user_id, self::USER_META_VERSION, true );
        if ( $user_version === $current ) {
            return;
        }

        // Load changelog content
        $changelog_file = plugin_dir_path( __FILE__ ) . 'CHANGELOG.md';
        $content        = file_exists( $changelog_file ) ? file_get_contents( $changelog_file ) : '';

        if ( $content ) {
            $nonce = wp_create_nonce( self::NONCE_ACTION );
            echo '<div class="notice notice-info is-dismissible meta-ai-optimizer-changelog">';
            echo '<h2>' . esc_html__( 'Meta AI Optimizer Changelog', 'meta-ai-optimizer' ) . '</h2>';
            echo '<pre style="white-space: pre-wrap; background:#f5f5f5; padding:8px; border-radius:4px;">' . esc_html( $content ) . '</pre>';
            echo '</div>';
            echo '<script type="text/javascript">
                (function($){
                    $(document).on("click", ".meta-ai-optimizer-changelog .notice-dismiss", function(){
                        $.post(ajaxurl, {
                            action: "' . esc_js( self::AJAX_ACTION ) . '",
                            _ajax_nonce: "' . esc_js( $nonce ) . '"
                        });
                    });
                })(jQuery);
            </script>';
        }
    }

    public static function ajax_dismiss_changelog() {
        check_ajax_referer( self::NONCE_ACTION );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error();
        }

        if ( ! function_exists( 'get_file_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = plugin_dir_path( __FILE__ ) . 'pluginmain.php';
        $data        = get_file_data( $plugin_file, array( 'Version' => 'Version' ) );
        $current     = isset( $data['Version'] ) ? $data['Version'] : '';

        $user_id = get_current_user_id();
        update_user_meta( $user_id, self::USER_META_VERSION, $current );

        wp_send_json_success();
    }
}

PluginChangeLogManager::init();