<?php
/**
 * Plugin Installer Class
 * 
 * Handles installing and activating required plugins for demos
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Plugin_Installer {
    
    private $tgmpa_url = 'http://tgmpluginactivation.com/download/';
    private $wp_repo_url = 'https://downloads.wordpress.org/plugin/';
    private $errors = array();
    
    /**
     * Install plugins from manifest
     */
    public function install_from_manifest($plugins_manifest) {
        if (empty($plugins_manifest) || !is_array($plugins_manifest)) {
            return false;
        }
        
        $results = array(
            'installed' => array(),
            'activated' => array(),
            'failed' => array(),
            'skipped' => array()
        );
        
        foreach ($plugins_manifest as $plugin_data) {
            $result = $this->process_plugin($plugin_data);
            
            if ($result['status'] === 'installed') {
                $results['installed'][] = $plugin_data['slug'];
            } elseif ($result['status'] === 'activated') {
                $results['activated'][] = $plugin_data['slug'];
            } elseif ($result['status'] === 'failed') {
                $results['failed'][] = $plugin_data['slug'];
                $this->errors[] = $result['error'];
            } elseif ($result['status'] === 'skipped') {
                $results['skipped'][] = $plugin_data['slug'];
            }
        }
        
        return $results;
    }
    
    /**
     * Process individual plugin
     */
    private function process_plugin($plugin_data) {
        // Validate plugin data
        if (!isset($plugin_data['slug']) || !isset($plugin_data['name'])) {
            return array('status' => 'failed', 'error' => 'Invalid plugin data');
        }
        
        $slug = $plugin_data['slug'];
        $name = $plugin_data['name'];
        $required = isset($plugin_data['required']) ? $plugin_data['required'] : false;
        $version = isset($plugin_data['version']) ? $plugin_data['version'] : '';
        
        // Check if plugin is already installed
        if ($this->is_plugin_installed($slug)) {
            // Check if activation is needed
            if (!$this->is_plugin_active($slug)) {
                $activated = $this->activate_plugin($slug);
                if ($activated) {
                    return array('status' => 'activated');
                } else {
                    return array('status' => 'failed', 'error' => 'Failed to activate ' . $name);
                }
            }
            return array('status' => 'skipped', 'reason' => 'already_active');
        }
        
        // Handle premium plugins
        if (isset($plugin_data['source']) && $plugin_data['source'] === 'premium') {
            return $this->handle_premium_plugin($plugin_data);
        }
        
        // Install the plugin
        $installed = $this->install_plugin($plugin_data);
        
        if ($installed) {
            // Activate after installation
            $activated = $this->activate_plugin($slug);
            if ($activated) {
                return array('status' => 'installed');
            } else {
                return array('status' => 'failed', 'error' => 'Installed but failed to activate ' . $name);
            }
        }
        
        return array('status' => 'failed', 'error' => 'Failed to install ' . $name);
    }
    
    /**
     * Install plugin
     */
    private function install_plugin($plugin_data) {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        
        $slug = $plugin_data['slug'];
        
        // Determine download URL
        $download_url = $this->get_download_url($plugin_data);
        
        if (!$download_url) {
            return false;
        }
        
        // Use WP_Upgrader to install plugin
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        
        $result = $upgrader->install($download_url);
        
        if (is_wp_error($result)) {
            $this->errors[] = $result->get_error_message();
            return false;
        }
        
        return $result;
    }
    
    /**
     * Get download URL for plugin
     */
    private function get_download_url($plugin_data) {
        $slug = $plugin_data['slug'];
        
        // Check if custom download URL is provided
        if (isset($plugin_data['download_url']) && !empty($plugin_data['download_url'])) {
            return $plugin_data['download_url'];
        }
        
        // Check if it's a WordPress.org plugin
        if (!isset($plugin_data['source']) || $plugin_data['source'] === 'wordpress') {
            $api = plugins_api('plugin_information', array(
                'slug' => $slug,
                'fields' => array('download_link' => true)
            ));
            
            if (!is_wp_error($api) && isset($api->download_link)) {
                return $api->download_link;
            }
        }
        
        // Check if it's bundled with the demo
        if (isset($plugin_data['source']) && $plugin_data['source'] === 'bundled') {
            return REIGN_DEMO_HUB_URL . 'plugins/' . $slug . '.zip';
        }
        
        return false;
    }
    
    /**
     * Handle premium plugin
     */
    private function handle_premium_plugin($plugin_data) {
        $slug = $plugin_data['slug'];
        $name = $plugin_data['name'];
        
        // Check if license key is provided
        $license_key = $this->get_plugin_license($slug);
        
        if (!$license_key) {
            return array(
                'status' => 'failed',
                'error' => sprintf(
                    __('%s requires a license key. Please enter your license key in the plugin settings.', 'reign-demo-install'),
                    $name
                ),
                'needs_license' => true
            );
        }
        
        // Attempt to download with license
        $download_url = $this->get_premium_download_url($plugin_data, $license_key);
        
        if ($download_url) {
            $plugin_data['download_url'] = $download_url;
            return $this->process_plugin($plugin_data);
        }
        
        return array(
            'status' => 'failed',
            'error' => sprintf(__('Failed to download %s. Please check your license key.', 'reign-demo-install'), $name)
        );
    }
    
    /**
     * Get premium plugin download URL
     */
    private function get_premium_download_url($plugin_data, $license_key) {
        // This would be customized based on each premium plugin's API
        // Example implementation for common premium plugins
        
        $slug = $plugin_data['slug'];
        
        switch ($slug) {
            case 'buddyboss-platform':
            case 'buddyboss-platform-pro':
                return $this->get_buddyboss_download_url($license_key);
                
            case 'learndash':
                return $this->get_learndash_download_url($license_key);
                
            case 'elementor-pro':
                return $this->get_elementor_pro_download_url($license_key);
                
            default:
                // Generic premium plugin handler
                if (isset($plugin_data['license_api'])) {
                    return $this->get_generic_premium_url($plugin_data['license_api'], $license_key);
                }
        }
        
        return false;
    }
    
    /**
     * Activate plugin
     */
    private function activate_plugin($slug) {
        $plugin_file = $this->get_plugin_file($slug);
        
        if (!$plugin_file) {
            return false;
        }
        
        $result = activate_plugin($plugin_file);
        
        return !is_wp_error($result);
    }
    
    /**
     * Check if plugin is installed
     */
    private function is_plugin_installed($slug) {
        $plugin_file = $this->get_plugin_file($slug);
        return !empty($plugin_file);
    }
    
    /**
     * Check if plugin is active
     */
    private function is_plugin_active($slug) {
        $plugin_file = $this->get_plugin_file($slug);
        
        if (!$plugin_file) {
            return false;
        }
        
        return is_plugin_active($plugin_file);
    }
    
    /**
     * Get plugin file from slug
     */
    private function get_plugin_file($slug) {
        $plugins = get_plugins();
        
        foreach ($plugins as $plugin_file => $plugin_data) {
            // Check if this is the plugin we're looking for
            if (strpos($plugin_file, $slug . '/') === 0 || 
                strpos($plugin_file, $slug . '.php') !== false) {
                return $plugin_file;
            }
        }
        
        return false;
    }
    
    /**
     * Get plugin license
     */
    private function get_plugin_license($slug) {
        $licenses = get_option('reign_demo_plugin_licenses', array());
        return isset($licenses[$slug]) ? $licenses[$slug] : false;
    }
    
    /**
     * BuddyBoss download URL handler
     */
    private function get_buddyboss_download_url($license_key) {
        // BuddyBoss API implementation
        $api_url = 'https://www.buddyboss.com/api/v1/download/';
        
        $response = wp_remote_get($api_url, array(
            'body' => array(
                'license_key' => $license_key,
                'product_id' => 'buddyboss-platform'
            )
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['download_url'])) {
                return $body['download_url'];
            }
        }
        
        return false;
    }
    
    /**
     * LearnDash download URL handler
     */
    private function get_learndash_download_url($license_key) {
        // LearnDash API implementation
        $api_url = 'https://www.learndash.com/api/download/';
        
        $response = wp_remote_post($api_url, array(
            'body' => array(
                'license' => $license_key,
                'item_name' => 'LearnDash LMS'
            )
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['download_link'])) {
                return $body['download_link'];
            }
        }
        
        return false;
    }
    
    /**
     * Elementor Pro download URL handler
     */
    private function get_elementor_pro_download_url($license_key) {
        // Elementor API implementation
        $api_url = 'https://my.elementor.com/api/v1/licenses/download/';
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $license_key
            )
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['download_url'])) {
                return $body['download_url'];
            }
        }
        
        return false;
    }
    
    /**
     * Generic premium plugin URL handler
     */
    private function get_generic_premium_url($api_url, $license_key) {
        $response = wp_remote_post($api_url, array(
            'body' => array(
                'license_key' => $license_key,
                'action' => 'get_download_url'
            )
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['download_url'])) {
                return $body['download_url'];
            }
        }
        
        return false;
    }
    
    /**
     * Get installation errors
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Clear errors
     */
    public function clear_errors() {
        $this->errors = array();
    }
    
    /**
     * Batch check plugin status
     */
    public function check_plugins_status($plugin_slugs) {
        $status = array();
        
        foreach ($plugin_slugs as $slug) {
            $status[$slug] = array(
                'installed' => $this->is_plugin_installed($slug),
                'active' => $this->is_plugin_active($slug),
                'version' => $this->get_plugin_version($slug)
            );
        }
        
        return $status;
    }
    
    /**
     * Get plugin version
     */
    private function get_plugin_version($slug) {
        $plugin_file = $this->get_plugin_file($slug);
        
        if (!$plugin_file) {
            return false;
        }
        
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
        
        return isset($plugin_data['Version']) ? $plugin_data['Version'] : false;
    }
}