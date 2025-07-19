<?php
/**
 * Settings Importer Class
 * 
 * Handles importing WordPress and plugin settings
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Settings_Importer {
    
    private $settings_data = array();
    private $preserved_settings = array();
    private $excluded_options = array(
        // Core WordPress options to preserve
        'siteurl',
        'home',
        'blogname',
        'blogdescription',
        'admin_email',
        'users_can_register',
        'default_role',
        'timezone_string',
        'date_format',
        'time_format',
        'start_of_week',
        
        // Security related
        'auth_key',
        'secure_auth_key',
        'logged_in_key',
        'nonce_key',
        'auth_salt',
        'secure_auth_salt',
        'logged_in_salt',
        'nonce_salt',
        
        // Database related
        'db_version',
        'initial_db_version',
        
        // User related
        'user_roles',
        
        // Plugin activation
        'active_plugins',
        'recently_activated',
        
        // Transients and cache
        '_transient_',
        '_site_transient_',
        
        // Reign Demo Install specific
        'reign_demo_preserved_admin',
        'reign_demo_preserved_session',
        'reign_demo_import_lock',
        'reign_demo_plugin_licenses'
    );
    
    /**
     * Import settings
     */
    public function import_settings($settings_file, $options = array()) {
        if (!file_exists($settings_file)) {
            return new WP_Error('file_not_found', __('Settings file not found', 'reign-demo-install'));
        }
        
        // Load settings data
        $content = file_get_contents($settings_file);
        $this->settings_data = json_decode($content, true);
        
        if (!$this->settings_data) {
            return new WP_Error('invalid_json', __('Invalid settings file', 'reign-demo-install'));
        }
        
        // Set default options
        $defaults = array(
            'import_options' => true,
            'import_theme_mods' => true,
            'import_widgets' => true,
            'import_menus' => true,
            'import_plugins_settings' => true,
            'preserve_current' => true
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Preserve current settings if needed
        if ($options['preserve_current']) {
            $this->preserve_current_settings();
        }
        
        $results = array();
        
        // Import WordPress options
        if ($options['import_options'] && isset($this->settings_data['options'])) {
            $results['options'] = $this->import_options();
        }
        
        // Import theme mods
        if ($options['import_theme_mods'] && isset($this->settings_data['theme_mods'])) {
            $results['theme_mods'] = $this->import_theme_mods();
        }
        
        // Import plugin settings
        if ($options['import_plugins_settings'] && isset($this->settings_data['plugins'])) {
            $results['plugins'] = $this->import_plugin_settings();
        }
        
        // Import custom tables
        if (isset($this->settings_data['custom_tables'])) {
            $results['custom_tables'] = $this->import_custom_tables();
        }
        
        // Run post-import actions
        $this->post_import_actions();
        
        return $results;
    }
    
    /**
     * Preserve current settings
     */
    private function preserve_current_settings() {
        // Preserve site info
        $this->preserved_settings['site_info'] = array(
            'blogname' => get_option('blogname'),
            'blogdescription' => get_option('blogdescription'),
            'admin_email' => get_option('admin_email'),
            'users_can_register' => get_option('users_can_register'),
            'default_role' => get_option('default_role')
        );
        
        // Preserve timezone settings
        $this->preserved_settings['timezone'] = array(
            'timezone_string' => get_option('timezone_string'),
            'gmt_offset' => get_option('gmt_offset')
        );
        
        // Preserve active plugins
        $this->preserved_settings['active_plugins'] = get_option('active_plugins');
        
        // Store preserved settings
        update_option('reign_demo_preserved_settings', $this->preserved_settings, false);
    }
    
    /**
     * Import WordPress options
     */
    private function import_options() {
        $imported = 0;
        $skipped = 0;
        
        foreach ($this->settings_data['options'] as $option_name => $option_value) {
            // Skip excluded options
            if ($this->should_skip_option($option_name)) {
                $skipped++;
                continue;
            }
            
            // Process option value
            $processed_value = $this->process_option_value($option_value, $option_name);
            
            // Update option
            update_option($option_name, $processed_value);
            $imported++;
        }
        
        return array(
            'imported' => $imported,
            'skipped' => $skipped
        );
    }
    
    /**
     * Check if option should be skipped
     */
    private function should_skip_option($option_name) {
        // Check exact matches
        if (in_array($option_name, $this->excluded_options)) {
            return true;
        }
        
        // Check patterns
        foreach ($this->excluded_options as $pattern) {
            if (strpos($option_name, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Process option value
     */
    private function process_option_value($value, $option_name) {
        // Unserialize if needed
        if (is_string($value) && is_serialized($value)) {
            $value = unserialize($value);
        }
        
        // Process URLs
        $value = $this->update_urls_in_value($value);
        
        // Process specific options
        switch ($option_name) {
            case 'page_on_front':
            case 'page_for_posts':
                // Map to new post IDs
                $post_mapping = get_option('reign_demo_post_mapping', array());
                if (isset($post_mapping[$value])) {
                    $value = $post_mapping[$value];
                }
                break;
                
            case 'sidebars_widgets':
                // Already handled in content importer
                return get_option('sidebars_widgets');
                
            case 'nav_menu_locations':
                // Map menu locations
                $value = $this->map_menu_locations($value);
                break;
        }
        
        return $value;
    }
    
    /**
     * Update URLs in value
     */
    private function update_urls_in_value($value) {
        $old_url = isset($this->settings_data['site_url']) ? $this->settings_data['site_url'] : '';
        $new_url = get_site_url();
        
        if (!$old_url || $old_url === $new_url) {
            return $value;
        }
        
        // Handle strings
        if (is_string($value)) {
            return str_replace($old_url, $new_url, $value);
        }
        
        // Handle arrays recursively
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->update_urls_in_value($val);
            }
        }
        
        // Handle objects
        if (is_object($value)) {
            $vars = get_object_vars($value);
            foreach ($vars as $key => $val) {
                $value->$key = $this->update_urls_in_value($val);
            }
        }
        
        return $value;
    }
    
    /**
     * Map menu locations
     */
    private function map_menu_locations($locations) {
        $menu_mapping = get_option('reign_demo_menu_mapping', array());
        $mapped_locations = array();
        
        foreach ($locations as $location => $menu_id) {
            if (isset($menu_mapping[$menu_id])) {
                $mapped_locations[$location] = $menu_mapping[$menu_id];
            } else {
                $mapped_locations[$location] = $menu_id;
            }
        }
        
        return $mapped_locations;
    }
    
    /**
     * Import theme mods
     */
    private function import_theme_mods() {
        $theme_slug = get_option('stylesheet');
        $imported = 0;
        
        // Check if we have mods for current theme
        if (isset($this->settings_data['theme_mods'][$theme_slug])) {
            $mods = $this->settings_data['theme_mods'][$theme_slug];
            
            // Process each mod
            foreach ($mods as $mod_name => $mod_value) {
                // Skip certain mods
                if (in_array($mod_name, array('nav_menu_locations', 'custom_css_post_id'))) {
                    continue;
                }
                
                // Process value
                $processed_value = $this->process_theme_mod_value($mod_value, $mod_name);
                
                // Set theme mod
                set_theme_mod($mod_name, $processed_value);
                $imported++;
            }
            
            // Handle custom CSS separately
            if (isset($mods['custom_css']) && !empty($mods['custom_css'])) {
                wp_update_custom_css_post($mods['custom_css']);
            }
        }
        
        return array(
            'imported' => $imported,
            'theme' => $theme_slug
        );
    }
    
    /**
     * Process theme mod value
     */
    private function process_theme_mod_value($value, $mod_name) {
        // Update URLs
        $value = $this->update_urls_in_value($value);
        
        // Handle media IDs
        if (strpos($mod_name, '_image') !== false || strpos($mod_name, '_logo') !== false) {
            $post_mapping = get_option('reign_demo_post_mapping', array());
            if (is_numeric($value) && isset($post_mapping[$value])) {
                $value = $post_mapping[$value];
            }
        }
        
        return $value;
    }
    
    /**
     * Import plugin settings
     */
    private function import_plugin_settings() {
        $results = array();
        
        foreach ($this->settings_data['plugins'] as $plugin_slug => $plugin_settings) {
            $imported = 0;
            $method = 'import_' . str_replace('-', '_', $plugin_slug) . '_settings';
            
            // Check for specific import method
            if (method_exists($this, $method)) {
                $result = $this->$method($plugin_settings);
                $results[$plugin_slug] = $result;
            } else {
                // Generic import
                foreach ($plugin_settings as $option_name => $option_value) {
                    $processed_value = $this->process_option_value($option_value, $option_name);
                    update_option($option_name, $processed_value);
                    $imported++;
                }
                
                $results[$plugin_slug] = array('imported' => $imported);
            }
        }
        
        return $results;
    }
    
    /**
     * Import BuddyPress settings
     */
    private function import_buddypress_settings($settings) {
        $imported = 0;
        
        // Import BP options
        foreach ($settings['options'] as $option_name => $option_value) {
            update_option($option_name, $option_value);
            $imported++;
        }
        
        // Import BP pages
        if (isset($settings['pages'])) {
            $post_mapping = get_option('reign_demo_post_mapping', array());
            $bp_pages = array();
            
            foreach ($settings['pages'] as $component => $page_id) {
                if (isset($post_mapping[$page_id])) {
                    $bp_pages[$component] = $post_mapping[$page_id];
                }
            }
            
            bp_core_update_directory_page_ids($bp_pages);
        }
        
        // Import xProfile fields
        if (isset($settings['xprofile_fields']) && function_exists('xprofile_insert_field_group')) {
            $this->import_xprofile_fields($settings['xprofile_fields']);
        }
        
        // Import activity types
        if (isset($settings['activity_types'])) {
            update_option('bp-active-components', $settings['activity_types']);
        }
        
        return array('imported' => $imported);
    }
    
    /**
     * Import xProfile fields
     */
    private function import_xprofile_fields($fields_data) {
        global $wpdb;
        $bp = buddypress();
        
        foreach ($fields_data as $group_data) {
            // Create field group
            $group_id = xprofile_insert_field_group(array(
                'name' => $group_data['name'],
                'description' => $group_data['description'] ?? '',
                'can_delete' => $group_data['can_delete'] ?? 1
            ));
            
            if ($group_id && isset($group_data['fields'])) {
                foreach ($group_data['fields'] as $field_data) {
                    // Create field
                    $field_id = xprofile_insert_field(array(
                        'field_group_id' => $group_id,
                        'name' => $field_data['name'],
                        'description' => $field_data['description'] ?? '',
                        'type' => $field_data['type'],
                        'order_by' => $field_data['order_by'] ?? '',
                        'is_required' => $field_data['is_required'] ?? 0,
                        'can_delete' => $field_data['can_delete'] ?? 1
                    ));
                    
                    // Add field options
                    if ($field_id && isset($field_data['options'])) {
                        foreach ($field_data['options'] as $option) {
                            xprofile_insert_field(array(
                                'field_group_id' => $group_id,
                                'parent_id' => $field_id,
                                'type' => 'option',
                                'name' => $option['name'],
                                'is_default_option' => $option['is_default'] ?? 0,
                                'option_order' => $option['order'] ?? 0
                            ));
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Import WooCommerce settings
     */
    private function import_woocommerce_settings($settings) {
        $imported = 0;
        
        // Import WC options
        foreach ($settings['options'] as $option_name => $option_value) {
            // Handle special WC pages
            if (strpos($option_name, 'woocommerce_') === 0 && strpos($option_name, '_page_id') !== false) {
                $post_mapping = get_option('reign_demo_post_mapping', array());
                if (isset($post_mapping[$option_value])) {
                    $option_value = $post_mapping[$option_value];
                }
            }
            
            update_option($option_name, $option_value);
            $imported++;
        }
        
        // Import payment gateways
        if (isset($settings['payment_gateways'])) {
            update_option('woocommerce_gateway_order', $settings['payment_gateways']);
        }
        
        // Import shipping zones
        if (isset($settings['shipping_zones'])) {
            $this->import_shipping_zones($settings['shipping_zones']);
        }
        
        // Import tax rates
        if (isset($settings['tax_rates'])) {
            $this->import_tax_rates($settings['tax_rates']);
        }
        
        return array('imported' => $imported);
    }
    
    /**
     * Import shipping zones
     */
    private function import_shipping_zones($zones_data) {
        if (!class_exists('WC_Shipping_Zone')) {
            return;
        }
        
        foreach ($zones_data as $zone_data) {
            $zone = new WC_Shipping_Zone();
            $zone->set_zone_name($zone_data['name']);
            $zone->set_zone_order($zone_data['order']);
            
            // Set locations
            if (isset($zone_data['locations'])) {
                $zone->set_zone_locations($zone_data['locations']);
            }
            
            $zone->save();
            
            // Add shipping methods
            if (isset($zone_data['methods'])) {
                foreach ($zone_data['methods'] as $method_data) {
                    $instance_id = $zone->add_shipping_method($method_data['id']);
                    
                    // Update method settings
                    if ($instance_id && isset($method_data['settings'])) {
                        $method = WC_Shipping_Zones::get_shipping_method($instance_id);
                        if ($method) {
                            $method->init_settings();
                            foreach ($method_data['settings'] as $key => $value) {
                                $method->settings[$key] = $value;
                            }
                            update_option($method->get_option_key(), $method->settings);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Import tax rates
     */
    private function import_tax_rates($tax_rates) {
        global $wpdb;
        
        foreach ($tax_rates as $tax_rate) {
            $wpdb->insert(
                $wpdb->prefix . 'woocommerce_tax_rates',
                array(
                    'tax_rate_country' => $tax_rate['country'],
                    'tax_rate_state' => $tax_rate['state'],
                    'tax_rate' => $tax_rate['rate'],
                    'tax_rate_name' => $tax_rate['name'],
                    'tax_rate_priority' => $tax_rate['priority'],
                    'tax_rate_compound' => $tax_rate['compound'],
                    'tax_rate_shipping' => $tax_rate['shipping'],
                    'tax_rate_order' => $tax_rate['order'],
                    'tax_rate_class' => $tax_rate['class']
                )
            );
        }
    }
    
    /**
     * Import custom tables
     */
    private function import_custom_tables() {
        global $wpdb;
        $imported_tables = 0;
        
        foreach ($this->settings_data['custom_tables'] as $table_name => $table_data) {
            // Skip if table doesn't exist
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table_name}'") !== $wpdb->prefix . $table_name) {
                continue;
            }
            
            // Clear existing data if requested
            if (isset($table_data['clear_existing']) && $table_data['clear_existing']) {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$table_name}");
            }
            
            // Import rows
            if (isset($table_data['rows'])) {
                foreach ($table_data['rows'] as $row) {
                    $wpdb->insert($wpdb->prefix . $table_name, $row);
                }
            }
            
            $imported_tables++;
        }
        
        return array('imported_tables' => $imported_tables);
    }
    
    /**
     * Post import actions
     */
    private function post_import_actions() {
        // Clear caches
        wp_cache_flush();
        
        // Update rewrite rules
        flush_rewrite_rules();
        
        // Clear transients
        $this->clear_transients();
        
        // Run plugin-specific actions
        do_action('reign_demo_settings_imported', $this->settings_data);
        
        // Restore preserved settings if needed
        $this->restore_preserved_settings();
    }
    
    /**
     * Clear transients
     */
    private function clear_transients() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
    }
    
    /**
     * Restore preserved settings
     */
    private function restore_preserved_settings() {
        $preserved = get_option('reign_demo_preserved_settings');
        
        if (!$preserved) {
            return;
        }
        
        // Restore site info if user chose to
        if (get_option('reign_demo_preserve_site_info', false)) {
            foreach ($preserved['site_info'] as $option => $value) {
                update_option($option, $value);
            }
        }
        
        // Always restore timezone
        if (isset($preserved['timezone'])) {
            update_option('timezone_string', $preserved['timezone']['timezone_string']);
            update_option('gmt_offset', $preserved['timezone']['gmt_offset']);
        }
    }
}