<?php
/**
 * Demo Browser Class
 * 
 * Handles fetching and displaying available demos
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Browser {
    
    private $demos_cache_key = 'reign_demo_list';
    private $cache_duration = 3600; // 1 hour
    
    /**
     * Get available demos
     */
    public function get_available_demos() {
        // Check cache first
        $cached_demos = get_transient($this->demos_cache_key);
        if ($cached_demos !== false) {
            return $cached_demos;
        }
        
        // Fetch from master registry
        $demos = $this->fetch_demos_from_registry();
        
        if ($demos) {
            // Cache the results
            set_transient($this->demos_cache_key, $demos, $this->cache_duration);
        }
        
        return $demos;
    }
    
    /**
     * Fetch demos from master registry
     */
    private function fetch_demos_from_registry() {
        $registry_url = REIGN_DEMO_HUB_URL . 'master-registry.json';
        
        $response = wp_remote_get($registry_url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'Reign Demo Install/' . REIGN_DEMO_INSTALL_VERSION
            )
        ));
        
        if (is_wp_error($response)) {
            return $this->get_fallback_demos();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['demos'])) {
            return $this->get_fallback_demos();
        }
        
        // Process and enhance demo data
        return $this->process_demo_data($data['demos']);
    }
    
    /**
     * Process demo data
     */
    private function process_demo_data($demos) {
        $processed = array();
        
        foreach ($demos as $demo) {
            // Ensure all required fields exist
            $demo_data = wp_parse_args($demo, array(
                'id' => '',
                'name' => '',
                'slug' => '',
                'description' => '',
                'category' => 'general',
                'preview_url' => '',
                'thumbnail' => '',
                'manifest_url' => '',
                'package_url' => '',
                'plugins_manifest_url' => '',
                'files_manifest_url' => '',
                'required_plugins' => array(),
                'tags' => array(),
                'version' => '1.0.0'
            ));
            
            // Generate URLs if not provided
            if (empty($demo_data['manifest_url']) && !empty($demo_data['slug'])) {
                $demo_base_url = REIGN_DEMO_HUB_URL . $demo_data['slug'] . '/';
                $demo_data['manifest_url'] = $demo_base_url . 'manifest.json';
                $demo_data['package_url'] = $demo_base_url . 'content-package.zip';
                $demo_data['plugins_manifest_url'] = $demo_base_url . 'plugins-manifest.json';
                $demo_data['files_manifest_url'] = $demo_base_url . 'files-manifest.json';
            }
            
            // Set thumbnail
            if (empty($demo_data['thumbnail']) && !empty($demo_data['slug'])) {
                $demo_data['thumbnail'] = REIGN_DEMO_HUB_URL . 'previews/' . $demo_data['slug'] . '.jpg';
            }
            
            $processed[] = $demo_data;
        }
        
        return $processed;
    }
    
    /**
     * Get fallback demos if registry is unavailable
     */
    private function get_fallback_demos() {
        // Hardcoded fallback demos
        return array(
            array(
                'id' => 'reign-community',
                'name' => 'Community Hub',
                'slug' => 'reign-community',
                'description' => 'A vibrant community platform with BuddyPress integration',
                'category' => 'community',
                'preview_url' => 'https://demos.wbcomdesigns.com/reign-community/',
                'thumbnail' => REIGN_DEMO_HUB_URL . 'previews/reign-community.jpg',
                'manifest_url' => REIGN_DEMO_HUB_URL . 'reign-community/manifest.json',
                'package_url' => REIGN_DEMO_HUB_URL . 'reign-community/content-package.zip',
                'required_plugins' => array('buddypress', 'bbpress'),
                'tags' => array('social', 'forum', 'community')
            ),
            array(
                'id' => 'reign-business',
                'name' => 'Business Pro',
                'slug' => 'reign-business',
                'description' => 'Professional business website with WooCommerce',
                'category' => 'business',
                'preview_url' => 'https://demos.wbcomdesigns.com/reign-business/',
                'thumbnail' => REIGN_DEMO_HUB_URL . 'previews/reign-business.jpg',
                'manifest_url' => REIGN_DEMO_HUB_URL . 'reign-business/manifest.json',
                'package_url' => REIGN_DEMO_HUB_URL . 'reign-business/content-package.zip',
                'required_plugins' => array('woocommerce', 'elementor'),
                'tags' => array('business', 'corporate', 'ecommerce')
            ),
            array(
                'id' => 'reign-education',
                'name' => 'Education Platform',
                'slug' => 'reign-education',
                'description' => 'Complete LMS solution with LearnDash',
                'category' => 'education',
                'preview_url' => 'https://demos.wbcomdesigns.com/reign-education/',
                'thumbnail' => REIGN_DEMO_HUB_URL . 'previews/reign-education.jpg',
                'manifest_url' => REIGN_DEMO_HUB_URL . 'reign-education/manifest.json',
                'package_url' => REIGN_DEMO_HUB_URL . 'reign-education/content-package.zip',
                'required_plugins' => array('learndash', 'buddypress'),
                'tags' => array('education', 'lms', 'courses')
            )
        );
    }
    
    /**
     * Get demo by ID
     */
    public function get_demo_by_id($demo_id) {
        $demos = $this->get_available_demos();
        
        foreach ($demos as $demo) {
            if ($demo['id'] === $demo_id) {
                return $demo;
            }
        }
        
        return null;
    }
    
    /**
     * Get demos by category
     */
    public function get_demos_by_category($category) {
        $demos = $this->get_available_demos();
        $filtered = array();
        
        foreach ($demos as $demo) {
            if ($demo['category'] === $category) {
                $filtered[] = $demo;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get demo categories
     */
    public function get_demo_categories() {
        return array(
            'all' => __('All Demos', 'reign-demo-install'),
            'community' => __('Community', 'reign-demo-install'),
            'business' => __('Business', 'reign-demo-install'),
            'education' => __('Education', 'reign-demo-install'),
            'ecommerce' => __('E-Commerce', 'reign-demo-install'),
            'marketplace' => __('Marketplace', 'reign-demo-install'),
            'directory' => __('Directory', 'reign-demo-install'),
            'job-board' => __('Job Board', 'reign-demo-install'),
            'dating' => __('Dating', 'reign-demo-install'),
            'nonprofit' => __('Non-Profit', 'reign-demo-install'),
            'portfolio' => __('Portfolio', 'reign-demo-install')
        );
    }
    
    /**
     * Search demos
     */
    public function search_demos($keyword) {
        $demos = $this->get_available_demos();
        $results = array();
        $keyword = strtolower($keyword);
        
        foreach ($demos as $demo) {
            // Search in name, description, category, and tags
            $searchable = strtolower($demo['name'] . ' ' . $demo['description'] . ' ' . $demo['category']);
            
            if (!empty($demo['tags'])) {
                $searchable .= ' ' . implode(' ', $demo['tags']);
            }
            
            if (strpos($searchable, $keyword) !== false) {
                $results[] = $demo;
            }
        }
        
        return $results;
    }
    
    /**
     * Clear demos cache
     */
    public function clear_cache() {
        delete_transient($this->demos_cache_key);
    }
    
    /**
     * Check if demo is downloaded
     */
    public function is_demo_downloaded($demo_id) {
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $demo_dir = $temp_dir . $demo_id . '/';
        
        return (is_dir($demo_dir) && file_exists($demo_dir . 'manifest.json'));
    }
    
    /**
     * Get download progress
     */
    public function get_download_progress($demo_id) {
        return get_transient('reign_demo_download_progress_' . $demo_id);
    }
}