<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin_file = dirname( dirname( __FILE__ ) ) . '/pluginmain.php';

if ( ! defined( 'META_AI_OPTIMIZER_PLUGIN_FILE' ) ) {
    define( 'META_AI_OPTIMIZER_PLUGIN_FILE', $plugin_file );
}
if ( ! defined( 'META_AI_OPTIMIZER_PLUGIN_DIR' ) ) {
    define( 'META_AI_OPTIMIZER_PLUGIN_DIR', plugin_dir_path( META_AI_OPTIMIZER_PLUGIN_FILE ) );
}
if ( ! defined( 'META_AI_OPTIMIZER_PLUGIN_URL' ) ) {
    define( 'META_AI_OPTIMIZER_PLUGIN_URL', plugin_dir_url( META_AI_OPTIMIZER_PLUGIN_FILE ) );
}
if ( ! defined( 'META_AI_OPTIMIZER_PLUGIN_BASENAME' ) ) {
    define( 'META_AI_OPTIMIZER_PLUGIN_BASENAME', plugin_basename( META_AI_OPTIMIZER_PLUGIN_FILE ) );
}

// Nonce constants for CSRF protection
if ( ! defined( 'META_AI_OPTIMIZER_NONCE_ACTION' ) ) {
    define( 'META_AI_OPTIMIZER_NONCE_ACTION', 'meta_ai_optimizer_action' );
}
if ( ! defined( 'META_AI_OPTIMIZER_NONCE_NAME' ) ) {
    define( 'META_AI_OPTIMIZER_NONCE_NAME', 'meta_ai_optimizer_nonce' );
}

// Autoload dependencies
if ( file_exists( META_AI_OPTIMIZER_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once META_AI_OPTIMIZER_PLUGIN_DIR . 'vendor/autoload.php';
}

// Activation & Deactivation Hooks
register_activation_hook( META_AI_OPTIMIZER_PLUGIN_FILE, [ 'MetaAiOptimizer\\Plugin', 'activate' ] );
register_deactivation_hook( META_AI_OPTIMIZER_PLUGIN_FILE, [ 'MetaAiOptimizer\\Plugin', 'deactivate' ] );

// Core Hooks
add_action( 'init', [ 'MetaAiOptimizer\\Assets', 'enqueueAssets' ] );
add_action( 'admin_menu', [ 'MetaAiOptimizer\\Admin\\Menu', 'registerAdminMenu' ] );

// AJAX Handlers - Admin only, prefixed to avoid collisions
$ajax_handlers = [
    'scan_posts'             => 'scanPosts',
    'get_suggestions'        => 'getSuggestions',
    'apply_suggestion'       => 'applySuggestion',
    'bulk_apply_suggestions' => 'bulkApplySuggestions',
    'save_settings'          => 'saveSettings',
];

foreach ( $ajax_handlers as $action => $method ) {
    $prefixed_action = 'meta_ai_optimizer_' . $action;
    add_action( "wp_ajax_{$prefixed_action}", [ 'MetaAiOptimizer\\Ajax\\Handler', $method ] );
}

// Localize script with AJAX URL and nonce
add_action( 'admin_enqueue_scripts', function() {
    wp_localize_script(
        'meta-ai-optimizer-admin',
        'MetaAiOptimizerData',
        [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonceName'  => META_AI_OPTIMIZER_NONCE_NAME,
            'nonceValue' => wp_create_nonce( META_AI_OPTIMIZER_NONCE_ACTION ),
            'actions'    => array_keys( (array) apply_filters( 'meta_ai_optimizer_ajax_actions', [] ) ),
        ]
    );
} );