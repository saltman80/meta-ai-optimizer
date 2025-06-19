<?php
/**
 * assignCustomCapabilities.php
 */

class AssignCustomCapabilities {
    /**
     * Roles to assign custom capabilities to.
     *
     * @var array
     */
    protected static $roles = array(
        'administrator',
        'editor',
    );

    /**
     * Custom capabilities to assign/remove.
     *
     * @var array
     */
    protected static $capabilities = array(
        'mao_scan_content',
        'mao_apply_suggestion',
        'mao_manage_settings',
        'mao_view_dashboard',
    );

    /**
     * Activation handler: assign capabilities to roles, handling multisite.
     */
    public static function activate() {
        if ( ! is_array( self::$roles ) || ! is_array( self::$capabilities ) ) {
            error_log( 'AssignCustomCapabilities activation error: roles or capabilities not defined as arrays.' );
            return;
        }

        if ( is_multisite() ) {
            // Assign capabilities on each site.
            $sites = function_exists( 'get_sites' ) ? get_sites( array( 'fields' => 'ids' ) ) : array();
            foreach ( $sites as $blog_id ) {
                switch_to_blog( $blog_id );
                self::add_caps_to_roles();
                restore_current_blog();
            }
        } else {
            self::add_caps_to_roles();
        }
    }

    /**
     * Deactivation handler: remove capabilities from roles, handling multisite.
     */
    public static function deactivate() {
        if ( ! is_array( self::$roles ) || ! is_array( self::$capabilities ) ) {
            error_log( 'AssignCustomCapabilities deactivation error: roles or capabilities not defined as arrays.' );
            return;
        }

        if ( is_multisite() ) {
            // Remove capabilities on each site.
            $sites = function_exists( 'get_sites' ) ? get_sites( array( 'fields' => 'ids' ) ) : array();
            foreach ( $sites as $blog_id ) {
                switch_to_blog( $blog_id );
                self::remove_caps_from_roles();
                restore_current_blog();
            }
        } else {
            self::remove_caps_from_roles();
        }
    }

    /**
     * Loop through roles and add each capability.
     */
    protected static function add_caps_to_roles() {
        foreach ( self::$roles as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                error_log( "AssignCustomCapabilities: Role '{$role_name}' not found during activation." );
                continue;
            }
            foreach ( self::$capabilities as $cap ) {
                $role->add_cap( $cap );
            }
        }
    }

    /**
     * Loop through roles and remove each capability.
     */
    protected static function remove_caps_from_roles() {
        foreach ( self::$roles as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                error_log( "AssignCustomCapabilities: Role '{$role_name}' not found during deactivation." );
                continue;
            }
            foreach ( self::$capabilities as $cap ) {
                $role->remove_cap( $cap );
            }
        }
    }
}

// Register hooks in the main plugin file context.
if ( defined( 'META_AI_OPTIMIZER_PLUGIN_FILE' ) ) {
    register_activation_hook( META_AI_OPTIMIZER_PLUGIN_FILE, array( 'AssignCustomCapabilities', 'activate' ) );
    register_deactivation_hook( META_AI_OPTIMIZER_PLUGIN_FILE, array( 'AssignCustomCapabilities', 'deactivate' ) );
}
?>