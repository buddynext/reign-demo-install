<?php
/**
 * Component Enabler Class
 * 
 * Ensures all BuddyBoss/BuddyPress components are enabled before import
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Component_Enabler {
    
    /**
     * Enable all BuddyBoss/BuddyPress components
     * 
     * @return array Results of component activation
     */
    public function enable_all_components() {
        $results = array(
            'enabled' => array(),
            'errors' => array(),
            'platform' => $this->detect_platform()
        );
        
        // Detect which platform is active
        if ($results['platform'] === 'buddyboss') {
            $results = $this->enable_buddyboss_components($results);
        } elseif ($results['platform'] === 'buddypress') {
            $results = $this->enable_buddypress_components($results);
        } else {
            $results['errors'][] = 'Neither BuddyBoss Platform nor BuddyPress is active';
        }
        
        return $results;
    }
    
    /**
     * Detect which platform is active
     * 
     * @return string 'buddyboss', 'buddypress', or 'none'
     */
    private function detect_platform() {
        if (class_exists('BuddyBoss_Platform') || defined('BP_PLATFORM_VERSION')) {
            return 'buddyboss';
        } elseif (class_exists('BuddyPress') || function_exists('bp_is_active')) {
            return 'buddypress';
        }
        return 'none';
    }
    
    /**
     * Enable all BuddyBoss components
     * 
     * @param array $results Current results array
     * @return array Updated results
     */
    private function enable_buddyboss_components($results) {
        // BuddyBoss components
        $components = array(
            'activity' => 'Activity Streams',
            'groups' => 'User Groups',
            'friends' => 'Friend Connections',
            'messages' => 'Private Messages',
            'notifications' => 'Notifications',
            'xprofile' => 'Extended Profiles',
            'settings' => 'Account Settings',
            'members' => 'Members Directory',
            'forums' => 'Forums',
            'media' => 'Media',
            'document' => 'Documents',
            'video' => 'Videos',
            'invites' => 'Email Invites',
            'moderation' => 'Moderation',
            'search' => 'Network Search'
        );
        
        // Get current active components
        $active_components = bp_get_option('bp-active-components', array());
        
        // Enable each component
        foreach ($components as $component => $name) {
            if (!isset($active_components[$component])) {
                $active_components[$component] = 1;
                $results['enabled'][] = $name;
            }
        }
        
        // Update the active components
        bp_update_option('bp-active-components', $active_components);
        
        // Additional BuddyBoss specific settings
        $this->configure_buddyboss_settings();
        
        // Ensure database tables are created
        $this->ensure_buddyboss_tables();
        
        return $results;
    }
    
    /**
     * Enable all BuddyPress components
     * 
     * @param array $results Current results array
     * @return array Updated results
     */
    private function enable_buddypress_components($results) {
        // BuddyPress core components
        $components = array(
            'activity' => 'Activity Streams',
            'groups' => 'User Groups',
            'friends' => 'Friend Connections',
            'messages' => 'Private Messages',
            'notifications' => 'Notifications',
            'xprofile' => 'Extended Profiles',
            'settings' => 'Account Settings',
            'members' => 'Members Directory',
            'blogs' => 'Site Tracking' // Multisite only
        );
        
        // Get current active components
        $active_components = bp_get_option('bp-active-components', array());
        
        // Enable each component
        foreach ($components as $component => $name) {
            // Skip blogs component if not multisite
            if ($component === 'blogs' && !is_multisite()) {
                continue;
            }
            
            if (!isset($active_components[$component])) {
                $active_components[$component] = 1;
                $results['enabled'][] = $name;
            }
        }
        
        // Update the active components
        bp_update_option('bp-active-components', $active_components);
        
        // Ensure database tables are created
        $this->ensure_buddypress_tables();
        
        return $results;
    }
    
    /**
     * Configure BuddyBoss specific settings
     */
    private function configure_buddyboss_settings() {
        // Enable profile types
        bp_update_option('bp-member-type-enable-disable', 1);
        
        // Enable group types
        bp_update_option('bp-group-type-enable-disable', 1);
        
        // Enable activity follow
        bp_update_option('bp-enable-activity-follow', 1);
        
        // Enable media in messages
        bp_update_option('bp_media_messages_media', 1);
        
        // Enable document in messages
        bp_update_option('bp_media_messages_document', 1);
        
        // Enable emoji support
        bp_update_option('bp_media_messages_emoji', 1);
        
        // Enable activity edit
        bp_update_option('bp-enable-activity-edit', 1);
        
        // Enable profile completion widget
        bp_update_option('bp_profile_completion_enable', 1);
        
        // Set default member type (if exists)
        bp_update_option('bp-default-member-type', 0);
        
        // Enable group hierarchies
        bp_update_option('bp_enable_group_hierarchies', 1);
        
        // Enable group auto join
        bp_update_option('bp-enable-group-auto-join', 1);
    }
    
    /**
     * Ensure BuddyBoss database tables are created
     */
    private function ensure_buddyboss_tables() {
        global $wpdb;
        
        // Check if we need to run the installer
        if (function_exists('bp_core_install')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Run core install
            bp_core_install();
            
            // Install components
            if (function_exists('bp_core_install_activity_streams')) {
                bp_core_install_activity_streams();
            }
            
            if (function_exists('bp_core_install_groups')) {
                bp_core_install_groups();
            }
            
            if (function_exists('bp_core_install_friends')) {
                bp_core_install_friends();
            }
            
            if (function_exists('bp_core_install_private_messaging')) {
                bp_core_install_private_messaging();
            }
            
            if (function_exists('bp_core_install_notifications')) {
                bp_core_install_notifications();
            }
            
            if (function_exists('bp_core_install_extended_profiles')) {
                bp_core_install_extended_profiles();
            }
            
            // BuddyBoss specific
            if (function_exists('bp_core_install_media')) {
                bp_core_install_media();
            }
            
            if (function_exists('bp_core_install_document')) {
                bp_core_install_document();
            }
            
            if (function_exists('bp_core_install_moderation')) {
                bp_core_install_moderation();
            }
            
            if (function_exists('bp_core_install_invitations')) {
                bp_core_install_invitations();
            }
        }
        
        // Additional table check
        $this->verify_tables_exist();
    }
    
    /**
     * Ensure BuddyPress database tables are created
     */
    private function ensure_buddypress_tables() {
        global $wpdb;
        
        // Check if we need to run the installer
        if (function_exists('bp_core_install')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Run the installer
            bp_core_install();
            
            // Install component tables
            if (function_exists('bp_core_install_activity_streams')) {
                bp_core_install_activity_streams();
            }
            
            if (function_exists('bp_core_install_groups')) {
                bp_core_install_groups();
            }
            
            if (function_exists('bp_core_install_friends')) {
                bp_core_install_friends();
            }
            
            if (function_exists('bp_core_install_private_messaging')) {
                bp_core_install_private_messaging();
            }
            
            if (function_exists('bp_core_install_notifications')) {
                bp_core_install_notifications();
            }
            
            if (function_exists('bp_core_install_extended_profiles')) {
                bp_core_install_extended_profiles();
            }
            
            if (is_multisite() && function_exists('bp_core_install_blog_tracking')) {
                bp_core_install_blog_tracking();
            }
        }
        
        // Additional table check
        $this->verify_tables_exist();
    }
    
    /**
     * Verify that expected tables exist
     */
    private function verify_tables_exist() {
        global $wpdb;
        
        $expected_tables = array(
            'bp_activity',
            'bp_activity_meta',
            'bp_groups',
            'bp_groups_members',
            'bp_groups_groupmeta',
            'bp_friends',
            'bp_messages_messages',
            'bp_messages_recipients',
            'bp_messages_meta',
            'bp_notifications',
            'bp_notifications_meta',
            'bp_xprofile_data',
            'bp_xprofile_fields',
            'bp_xprofile_groups',
            'bp_xprofile_meta'
        );
        
        // BuddyBoss specific tables
        if ($this->detect_platform() === 'buddyboss') {
            $additional_tables = array(
                'bp_invitations',
                'bb_media',
                'bb_media_albums',
                'bb_document',
                'bb_document_folder',
                'bb_moderation',
                'bb_moderation_meta',
                'bb_media_privacy',
                'bb_document_privacy'
            );
            $expected_tables = array_merge($expected_tables, $additional_tables);
        }
        
        foreach ($expected_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log("Reign Demo Install: Missing table $table_name");
            }
        }
    }
    
    /**
     * Get component status report
     * 
     * @return array Component status
     */
    public function get_component_status() {
        $status = array(
            'platform' => $this->detect_platform(),
            'components' => array(),
            'tables' => array()
        );
        
        if ($status['platform'] !== 'none') {
            // Get active components
            $active_components = bp_get_option('bp-active-components', array());
            $status['components'] = array_keys($active_components);
            
            // Check tables
            global $wpdb;
            $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}bp_%'");
            $bb_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}bb_%'");
            $status['tables'] = array_merge($tables, $bb_tables);
        }
        
        return $status;
    }
}