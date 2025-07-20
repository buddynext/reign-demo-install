<?php
/**
 * Session Manager Class
 * 
 * Manages user sessions during import to prevent logout
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Session_Manager {
    
    private $session_key = 'reign_demo_import_session';
    private $heartbeat_interval = 30; // seconds
    
    /**
     * Initialize session protection
     */
    public function init_session_protection() {
        // Create session lock
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }
        
        // Get session token safely
        $session_token = '';
        if (function_exists('wp_get_session_token')) {
            try {
                $session_token = wp_get_session_token();
            } catch (Exception $e) {
                // Continue without token
            }
        }
        
        $session_data = array(
            'user_id' => $user_id,
            'session_token' => $session_token,
            'start_time' => time(),
            'last_heartbeat' => time(),
            'import_active' => true
        );
        
        update_option($this->session_key, $session_data, false);
        
        // Don't try to extend cookies - this can cause issues
        // Just rely on the session keeper filters
        
        return true;
    }
    
    /**
     * Extend session cookie expiration
     */
    private function extend_session_cookie() {
        // Don't manipulate cookies - this causes logout issues
        // Following Wbcom's approach of not touching auth cookies
        return true;
    }
    
    /**
     * Refresh user session (called periodically)
     */
    public function refresh_user_session() {
        $session_data = get_option($this->session_key);
        
        if (!$session_data || !$session_data['import_active']) {
            return false;
        }
        
        // Update heartbeat
        $session_data['last_heartbeat'] = time();
        update_option($this->session_key, $session_data, false);
        
        // Check if session is still valid
        if (!is_user_logged_in() || get_current_user_id() != $session_data['user_id']) {
            // Attempt to restore session
            return $this->restore_session($session_data);
        }
        
        // Extend cookie if needed
        $time_elapsed = time() - $session_data['start_time'];
        if ($time_elapsed > 1800) { // 30 minutes
            $this->extend_session_cookie();
        }
        
        return true;
    }
    
    /**
     * Restore lost session
     */
    public function restore_session($session_data = null) {
        if (!$session_data) {
            $session_data = get_option($this->session_key);
        }
        
        if (!$session_data || !isset($session_data['user_id'])) {
            return false;
        }
        
        // Get preserved user data
        $preserved_user = get_option('reign_demo_preserved_admin');
        if (!$preserved_user) {
            return false;
        }
        
        // Don't manipulate auth cookies - causes logout issues
        // Just set current user without touching cookies
        wp_set_current_user($session_data['user_id']);
        
        // Verify authentication
        if (get_current_user_id() == $session_data['user_id']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check session status
     */
    public function check_session_status() {
        $session_data = get_option($this->session_key);
        
        if (!$session_data) {
            return array('status' => 'no_session');
        }
        
        $current_user_id = get_current_user_id();
        $time_since_heartbeat = time() - $session_data['last_heartbeat'];
        
        // Check various conditions
        if (!is_user_logged_in()) {
            return array('status' => 'logged_out', 'can_restore' => true);
        }
        
        if ($current_user_id != $session_data['user_id']) {
            return array('status' => 'wrong_user', 'can_restore' => true);
        }
        
        if ($time_since_heartbeat > 120) { // 2 minutes
            return array('status' => 'stale', 'can_restore' => true);
        }
        
        if (!$session_data['import_active']) {
            return array('status' => 'import_complete');
        }
        
        return array('status' => 'active', 'user_id' => $current_user_id);
    }
    
    /**
     * Prevent logout URL during import
     */
    public function prevent_logout_url($logout_url) {
        $session_data = get_option($this->session_key);
        
        if ($session_data && $session_data['import_active']) {
            return '#reign-demo-import-active';
        }
        
        return $logout_url;
    }
    
    /**
     * Prevent logout action during import
     */
    public function prevent_logout_action() {
        $session_data = get_option($this->session_key);
        
        if ($session_data && $session_data['import_active']) {
            wp_die(__('Cannot logout during demo import. Please wait for the import to complete.', 'reign-demo-install'));
        }
    }
    
    /**
     * Modify login URL during import
     */
    public function modify_login_url($login_url) {
        $session_data = get_option($this->session_key);
        
        if ($session_data && $session_data['import_active']) {
            // Add parameter to indicate import is active
            $login_url = add_query_arg('reign_import_active', '1', $login_url);
        }
        
        return $login_url;
    }
    
    /**
     * End session protection
     */
    public function end_session_protection() {
        $session_data = get_option($this->session_key);
        
        if ($session_data) {
            $session_data['import_active'] = false;
            $session_data['end_time'] = time();
            update_option($this->session_key, $session_data, false);
        }
        
        // Remove hooks
        remove_filter('logout_url', array($this, 'prevent_logout_url'), 999);
        remove_action('wp_logout', array($this, 'prevent_logout_action'), 1);
        remove_filter('login_url', array($this, 'modify_login_url'), 999);
        
        return true;
    }
    
    /**
     * Clean up session data
     */
    public function cleanup() {
        delete_option($this->session_key);
        delete_transient('reign_demo_import_active_' . get_current_user_id());
    }
}