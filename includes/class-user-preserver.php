<?php
/**
 * User Preserver Class
 * 
 * Handles the critical task of preserving the current admin user during import
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_User_Preserver {
    
    private $preserved_user_data = array();
    private $preserved_user_id;
    private $session_token;
    private $auth_cookie;
    
    /**
     * Preserve current admin user before import
     */
    public function preserve_current_admin() {
        $current_user = wp_get_current_user();
        
        if (!$current_user->exists() || !current_user_can('manage_options')) {
            throw new Exception(__('No admin user logged in', 'reign-demo-install'));
        }
        
        $this->preserved_user_id = $current_user->ID;
        
        // Store comprehensive user data
        $this->preserved_user_data = array(
            'ID' => $current_user->ID,
            'user_login' => $current_user->user_login,
            'user_email' => $current_user->user_email,
            'user_pass' => $current_user->user_pass, // Already hashed
            'user_nicename' => $current_user->user_nicename,
            'user_registered' => $current_user->user_registered,
            'user_activation_key' => $current_user->user_activation_key,
            'user_status' => $current_user->user_status,
            'display_name' => $current_user->display_name,
            'first_name' => $current_user->first_name,
            'last_name' => $current_user->last_name,
            'description' => $current_user->description,
            'rich_editing' => $current_user->rich_editing,
            'syntax_highlighting' => $current_user->syntax_highlighting,
            'comment_shortcuts' => $current_user->comment_shortcuts,
            'admin_color' => $current_user->admin_color,
            'use_ssl' => $current_user->use_ssl,
            'show_admin_bar_front' => $current_user->show_admin_bar_front,
            'locale' => $current_user->locale,
            'roles' => $current_user->roles,
            'allcaps' => $current_user->allcaps,
            'caps' => $current_user->caps,
            'cap_key' => $current_user->cap_key,
            'filter' => $current_user->filter
        );
        
        // Store all user meta
        $this->preserved_user_data['meta'] = get_user_meta($current_user->ID);
        
        // Store session information
        $this->preserve_session_data();
        
        // Save to database
        update_option('reign_demo_preserved_admin', $this->preserved_user_data, false);
        update_option('reign_demo_preserved_session', array(
            'session_token' => $this->session_token,
            'auth_cookie' => $this->auth_cookie,
            'logged_in_cookie' => $_COOKIE[LOGGED_IN_COOKIE] ?? '',
            'time' => time()
        ), false);
        
        // Mark user as protected
        update_user_meta($current_user->ID, '_reign_demo_protected_admin', true);
        update_user_meta($current_user->ID, '_reign_demo_import_time', time());
        
        // Don't set transients - they can cause session issues
        // Following Wbcom's simpler approach
        
        return true;
    }
    
    /**
     * Preserve session data
     */
    private function preserve_session_data() {
        // Get current session token
        $session = WP_Session_Tokens::get_instance($this->preserved_user_id);
        $token = wp_get_session_token();
        
        if ($token) {
            $this->session_token = $token;
            $session_data = $session->get($token);
            
            // Extend session expiration
            $session->update($token, array_merge($session_data, array(
                'expiration' => time() + (2 * HOUR_IN_SECONDS)
            )));
        }
        
        // Store auth cookie
        if (isset($_COOKIE[AUTH_COOKIE])) {
            $this->auth_cookie = $_COOKIE[AUTH_COOKIE];
        }
    }
    
    /**
     * Protect user during import
     */
    public function protect_user_during_import() {
        // Prevent any modifications to protected user
        add_filter('user_row_actions', array($this, 'remove_user_actions'), 999, 2);
        add_filter('bulk_actions-users', array($this, 'remove_bulk_actions'), 999);
        add_action('delete_user', array($this, 'prevent_user_deletion'), 1);
        add_action('remove_user_from_blog', array($this, 'prevent_user_removal'), 1);
        add_action('profile_update', array($this, 'prevent_user_update'), 1);
        add_filter('pre_user_login', array($this, 'protect_username'), 999);
        add_filter('pre_user_email', array($this, 'protect_email'), 999);
    }
    
    /**
     * Remove user actions for protected user
     */
    public function remove_user_actions($actions, $user) {
        if ($user->ID === $this->preserved_user_id) {
            return array();
        }
        return $actions;
    }
    
    /**
     * Remove bulk actions during import
     */
    public function remove_bulk_actions($actions) {
        // Don't need to check transients
        // Just check if user is protected
        $protected = get_user_meta(get_current_user_id(), '_reign_demo_protected_admin', true);
        if ($protected) {
            unset($actions['delete']);
            unset($actions['remove']);
        }
        return $actions;
    }
    
    /**
     * Prevent user deletion
     */
    public function prevent_user_deletion($user_id) {
        if ($user_id === $this->preserved_user_id) {
            wp_die(__('Cannot delete protected admin user during import.', 'reign-demo-install'));
        }
    }
    
    /**
     * Prevent user removal
     */
    public function prevent_user_removal($user_id) {
        if ($user_id === $this->preserved_user_id) {
            wp_die(__('Cannot remove protected admin user during import.', 'reign-demo-install'));
        }
    }
    
    /**
     * Prevent user update
     */
    public function prevent_user_update($user_id) {
        if ($user_id === $this->preserved_user_id) {
            $protected = get_user_meta($user_id, '_reign_demo_protected_admin', true);
            if ($protected) {
                wp_die(__('Cannot modify protected admin user during import.', 'reign-demo-install'));
            }
        }
    }
    
    /**
     * Protect username
     */
    public function protect_username($username) {
        $preserved = get_option('reign_demo_preserved_admin');
        if ($preserved && $username === $preserved['user_login']) {
            return $username . '_temp_' . uniqid();
        }
        return $username;
    }
    
    /**
     * Protect email
     */
    public function protect_email($email) {
        $preserved = get_option('reign_demo_preserved_admin');
        if ($preserved && $email === $preserved['user_email']) {
            return 'temp_' . uniqid() . '@example.com';
        }
        return $email;
    }
    
    /**
     * Maintain session during import
     */
    public function maintain_session() {
        $preserved = get_option('reign_demo_preserved_admin');
        if (!$preserved || !isset($preserved['ID'])) {
            return false;
        }
        
        // Set current user without manipulating cookies
        wp_set_current_user($preserved['ID']);
        
        // Restore capabilities if needed
        $user = get_user_by('ID', $preserved['ID']);
        if ($user && !in_array('administrator', $user->roles)) {
            $user->set_role('administrator');
        }
        
        return true;
    }
    
    /**
     * Restore admin after import
     */
    public function restore_admin_after_import() {
        $preserved = get_option('reign_demo_preserved_admin');
        if (!$preserved || !isset($preserved['ID'])) {
            return false;
        }
        
        // Get or create user
        $user = get_user_by('ID', $preserved['ID']);
        
        if (!$user) {
            // Recreate user if it was somehow deleted
            $user_id = wp_insert_user(array(
                'user_login' => $preserved['user_login'],
                'user_email' => $preserved['user_email'],
                'user_pass' => $preserved['user_pass'],
                'user_nicename' => $preserved['user_nicename'],
                'display_name' => $preserved['display_name'],
                'user_registered' => $preserved['user_registered'],
                'role' => 'administrator'
            ));
            
            if (is_wp_error($user_id)) {
                return false;
            }
            
            $user = get_user_by('ID', $user_id);
        }
        
        // Ensure administrator role
        $user->set_role('administrator');
        
        // Restore all capabilities
        if (isset($preserved['allcaps'])) {
            foreach ($preserved['allcaps'] as $cap => $grant) {
                if ($grant) {
                    $user->add_cap($cap);
                }
            }
        }
        
        // Restore user meta
        if (isset($preserved['meta'])) {
            foreach ($preserved['meta'] as $meta_key => $meta_value) {
                // Skip BuddyPress deprecated meta keys that might cause notices
                if (in_array($meta_key, array('last_activity'))) {
                    continue;
                }
                update_user_meta($user->ID, $meta_key, maybe_unserialize($meta_value[0]));
            }
        }
        
        // Clear protection flags
        delete_user_meta($user->ID, '_reign_demo_protected_admin');
        delete_user_meta($user->ID, '_reign_demo_import_time');
        
        // Clear preserved data
        delete_option('reign_demo_preserved_admin');
        delete_option('reign_demo_preserved_session');
        delete_option('reign_demo_import_lock');
        
        // Remove protection meta
        delete_user_meta($user->ID, '_reign_demo_protected_admin');
        delete_user_meta($user->ID, '_reign_demo_import_time');
        
        // Don't manipulate cookies - causes logout issues
        
        return true;
    }
    
    /**
     * Check if user is preserved
     */
    public function is_user_preserved($user_id) {
        $preserved = get_option('reign_demo_preserved_admin');
        return ($preserved && isset($preserved['ID']) && $preserved['ID'] == $user_id);
    }
    
    /**
     * Get preserved user data
     */
    public function get_preserved_user_data() {
        return $this->preserved_user_data;
    }
}