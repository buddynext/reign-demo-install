<?php
/**
 * Session Keeper Class
 * 
 * Maintains user session during long-running import processes
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Session_Keeper {
    
    /**
     * Initialize session keeper
     */
    public static function init() {
        // Hook early to preserve session
        add_action('init', array(__CLASS__, 'preserve_session'), 1);
        
        // Prevent auth cookie expiration during AJAX
        add_filter('auth_cookie_expiration', array(__CLASS__, 'extend_cookie_expiration'), 10, 3);
        
        // Prevent nonce expiration during import
        add_filter('nonce_life', array(__CLASS__, 'extend_nonce_life'));
        
        // Maintain user capabilities during import
        add_filter('user_has_cap', array(__CLASS__, 'maintain_capabilities'), 10, 4);
    }
    
    /**
     * Check if this is a demo import AJAX request
     */
    private static function is_demo_ajax() {
        if (!wp_doing_ajax()) {
            return false;
        }
        
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        $demo_actions = array(
            'reign_demo_preserve_user',
            'reign_demo_import_step',
            'reign_demo_check_session',
            'reign_demo_restore_session',
            'reign_demo_get_demo_list',
            'reign_demo_check_plugins',
            'reign_demo_install_plugins'
        );
        
        return in_array($action, $demo_actions);
    }
    
    /**
     * Preserve user session
     */
    public static function preserve_session() {
        if (!self::is_demo_ajax()) {
            return;
        }
        
        // Get current user ID from cookie if not set
        $user_id = get_current_user_id();
        
        if ($user_id === 0) {
            // Try to determine user from auth cookie
            $cookie_elements = wp_parse_auth_cookie('', 'logged_in');
            if ($cookie_elements && !empty($cookie_elements['username'])) {
                $user = get_user_by('login', $cookie_elements['username']);
                if ($user) {
                    wp_set_current_user($user->ID);
                    $user_id = $user->ID;
                }
            }
        }
        
        // Store in global for later use
        if ($user_id > 0) {
            $GLOBALS['reign_demo_current_user'] = $user_id;
        }
    }
    
    /**
     * Extend cookie expiration during import
     */
    public static function extend_cookie_expiration($expiration, $user_id, $remember) {
        if (self::is_demo_ajax()) {
            // Extend to 1 day during import
            return DAY_IN_SECONDS;
        }
        return $expiration;
    }
    
    /**
     * Extend nonce life during import
     */
    public static function extend_nonce_life($life) {
        if (self::is_demo_ajax()) {
            // Extend to 1 day during import
            return DAY_IN_SECONDS;
        }
        return $life;
    }
    
    /**
     * Maintain user capabilities during import
     */
    public static function maintain_capabilities($allcaps, $caps, $args, $user) {
        if (!self::is_demo_ajax()) {
            return $allcaps;
        }
        
        // During import, ensure admin users keep their capabilities
        if ($user->ID > 0) {
            // Check if user was an admin
            $stored_admin_id = get_option('reign_demo_current_admin_id');
            if ($user->ID == $stored_admin_id) {
                // Grant all admin capabilities
                $admin_caps = get_role('administrator')->capabilities;
                $allcaps = array_merge($allcaps, $admin_caps);
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Verify session is valid
     */
    public static function verify_session() {
        $user_id = get_current_user_id();
        
        // Check global backup
        if ($user_id === 0 && !empty($GLOBALS['reign_demo_current_user'])) {
            wp_set_current_user($GLOBALS['reign_demo_current_user']);
            $user_id = $GLOBALS['reign_demo_current_user'];
        }
        
        // Check stored admin ID
        if ($user_id === 0) {
            $stored_admin_id = get_option('reign_demo_current_admin_id');
            if ($stored_admin_id) {
                wp_set_current_user($stored_admin_id);
                $user_id = $stored_admin_id;
            }
        }
        
        return $user_id > 0;
    }
}

// Initialize session keeper
Reign_Demo_Session_Keeper::init();