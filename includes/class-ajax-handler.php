<?php
/**
 * AJAX Handler Class
 * 
 * Handles all AJAX requests for the demo installer
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Ajax_Handler {
    
    /**
     * Check requirements via AJAX
     */
    public function check_requirements() {
        // Verify nonce
        if (!check_ajax_referer('reign_demo_install_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'reign-demo-install')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'reign-demo-install')));
        }
        
        $checker = new Reign_Demo_Requirements_Checker();
        $results = $checker->check_all_requirements();
        
        wp_send_json_success($results);
    }
    
    /**
     * Preserve user via AJAX
     */
    public function preserve_user() {
        // Verify nonce
        if (!check_ajax_referer('reign_demo_install_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'reign-demo-install')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'reign-demo-install')));
        }
        
        $preserver = new Reign_Demo_User_Preserver();
        
        try {
            $result = $preserver->preserve_current_admin();
            
            if ($result) {
                // Initialize session protection
                $session_manager = new Reign_Demo_Session_Manager();
                $session_manager->init_session_protection();
                
                wp_send_json_success(array(
                    'message' => __('User preserved successfully', 'reign-demo-install'),
                    'user_data' => array(
                        'id' => get_current_user_id(),
                        'login' => wp_get_current_user()->user_login,
                        'email' => wp_get_current_user()->user_email
                    )
                ));
            } else {
                wp_send_json_error(array('message' => __('Failed to preserve user', 'reign-demo-install')));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Process import step
     */
    public function process_import_step() {
        // Verify nonce
        if (!check_ajax_referer('reign_demo_install_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'reign-demo-install')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'reign-demo-install')));
        }
        
        // Get parameters
        $demo_id = isset($_POST['demo_id']) ? sanitize_text_field($_POST['demo_id']) : '';
        $step = isset($_POST['step']) ? sanitize_text_field($_POST['step']) : '';
        $options = isset($_POST['options']) ? $_POST['options'] : array();
        
        if (empty($demo_id) || empty($step)) {
            wp_send_json_error(array('message' => __('Missing required parameters', 'reign-demo-install')));
        }
        
        // Set longer execution time
        @set_time_limit(300);
        
        // Process based on step
        switch ($step) {
            case 'backup':
                $this->process_backup_step($options);
                break;
                
            case 'download':
                $this->process_download_step($demo_id);
                break;
                
            case 'plugins':
                $this->process_plugins_step($demo_id);
                break;
                
            case 'content':
                $this->process_content_step($demo_id, $options);
                break;
                
            case 'files':
                $this->process_files_step($demo_id);
                break;
                
            case 'settings':
                $this->process_settings_step($demo_id);
                break;
                
            case 'cleanup':
                $this->process_cleanup_step($demo_id);
                break;
                
            default:
                wp_send_json_error(array('message' => __('Invalid import step', 'reign-demo-install')));
        }
    }
    
    /**
     * Process backup step
     */
    private function process_backup_step($options) {
        $rollback_manager = new Reign_Demo_Rollback_Manager();
        
        $backup_options = array(
            'backup_database' => isset($options['backup_database']) ? (bool)$options['backup_database'] : true,
            'backup_files' => isset($options['backup_files']) ? (bool)$options['backup_files'] : false,
            'backup_settings' => true,
            'backup_users' => true
        );
        
        $result = $rollback_manager->create_backup($backup_options);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Backup created successfully', 'reign-demo-install'),
            'backup_id' => $result['backup_id'],
            'next_step' => 'download'
        ));
    }
    
    /**
     * Process download step
     */
    private function process_download_step($demo_id) {
        $demo_browser = new Reign_Demo_Browser();
        $demo = $demo_browser->get_demo_by_id($demo_id);
        
        if (!$demo) {
            wp_send_json_error(array('message' => __('Demo not found', 'reign-demo-install')));
        }
        
        // Check if already downloaded
        if ($demo_browser->is_demo_downloaded($demo_id)) {
            wp_send_json_success(array(
                'message' => __('Demo already downloaded', 'reign-demo-install'),
                'next_step' => 'plugins'
            ));
        }
        
        // Create temp directory
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $demo_dir = $temp_dir . $demo_id . '/';
        wp_mkdir_p($demo_dir);
        
        // Download manifest
        $manifest_url = $demo['manifest_url'];
        $manifest_file = $demo_dir . 'manifest.json';
        
        $download_result = $this->download_file($manifest_url, $manifest_file);
        
        if (is_wp_error($download_result)) {
            wp_send_json_error(array(
                'message' => sprintf(__('Failed to download manifest: %s', 'reign-demo-install'), $download_result->get_error_message())
            ));
        }
        
        // Download content package
        if (!empty($demo['package_url'])) {
            $package_file = $demo_dir . 'content-package.zip';
            $download_result = $this->download_file($demo['package_url'], $package_file);
            
            if (is_wp_error($download_result)) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Failed to download package: %s', 'reign-demo-install'), $download_result->get_error_message())
                ));
            }
            
            // Extract package
            $extract_dir = $demo_dir . 'current-import/';
            $unzip_result = unzip_file($package_file, $extract_dir);
            
            if (is_wp_error($unzip_result)) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Failed to extract package: %s', 'reign-demo-install'), $unzip_result->get_error_message())
                ));
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Demo downloaded successfully', 'reign-demo-install'),
            'next_step' => 'plugins'
        ));
    }
    
    /**
     * Process plugins step
     */
    private function process_plugins_step($demo_id) {
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $manifest_file = $temp_dir . $demo_id . '/manifest.json';
        
        if (!file_exists($manifest_file)) {
            wp_send_json_error(array('message' => __('Manifest file not found', 'reign-demo-install')));
        }
        
        $manifest = json_decode(file_get_contents($manifest_file), true);
        
        if (!isset($manifest['required_plugins'])) {
            wp_send_json_success(array(
                'message' => __('No plugins to install', 'reign-demo-install'),
                'next_step' => 'content'
            ));
        }
        
        $plugin_installer = new Reign_Demo_Plugin_Installer();
        $results = $plugin_installer->install_from_manifest($manifest['required_plugins']);
        
        $message_parts = array();
        if (!empty($results['installed'])) {
            $message_parts[] = sprintf(__('Installed: %d', 'reign-demo-install'), count($results['installed']));
        }
        if (!empty($results['activated'])) {
            $message_parts[] = sprintf(__('Activated: %d', 'reign-demo-install'), count($results['activated']));
        }
        if (!empty($results['failed'])) {
            $message_parts[] = sprintf(__('Failed: %d', 'reign-demo-install'), count($results['failed']));
        }
        
        wp_send_json_success(array(
            'message' => implode(', ', $message_parts),
            'results' => $results,
            'next_step' => 'content'
        ));
    }
    
    /**
     * Process content step
     */
    private function process_content_step($demo_id, $options) {
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $content_file = $temp_dir . $demo_id . '/current-import/content.json';
        
        if (!file_exists($content_file)) {
            wp_send_json_error(array('message' => __('Content file not found', 'reign-demo-install')));
        }
        
        $content_importer = new Reign_Demo_Content_Importer();
        $import_options = array(
            'import_users' => isset($options['import_users']) ? (bool)$options['import_users'] : true,
            'import_media' => isset($options['import_media']) ? (bool)$options['import_media'] : true,
            'clean_existing' => isset($options['clean_existing']) ? (bool)$options['clean_existing'] : false
        );
        
        $results = $content_importer->import_content($content_file, $import_options);
        
        if (is_wp_error($results)) {
            wp_send_json_error(array(
                'message' => $results->get_error_message()
            ));
        }
        
        // Store mappings for later use
        update_option('reign_demo_user_mapping', $content_importer->user_mapping);
        update_option('reign_demo_post_mapping', $content_importer->post_mapping);
        update_option('reign_demo_term_mapping', $content_importer->term_mapping);
        update_option('reign_demo_menu_mapping', $content_importer->menu_mapping);
        
        wp_send_json_success(array(
            'message' => __('Content imported successfully', 'reign-demo-install'),
            'results' => $results,
            'next_step' => 'files'
        ));
    }
    
    /**
     * Process files step
     */
    private function process_files_step($demo_id) {
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $files_manifest = $temp_dir . $demo_id . '/current-import/files-manifest.json';
        $files_package = $temp_dir . $demo_id . '/current-import/files-package.zip';
        
        // Check if files need to be imported
        if (!file_exists($files_manifest) && !file_exists($files_package)) {
            wp_send_json_success(array(
                'message' => __('No files to import', 'reign-demo-install'),
                'next_step' => 'settings'
            ));
        }
        
        $file_importer = new Reign_Demo_File_Importer();
        $results = $file_importer->import_files($files_package, $files_manifest);
        
        if (is_wp_error($results)) {
            wp_send_json_error(array(
                'message' => $results->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Files imported successfully', 'reign-demo-install'),
            'results' => $results,
            'next_step' => 'settings'
        ));
    }
    
    /**
     * Process settings step
     */
    private function process_settings_step($demo_id) {
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $settings_file = $temp_dir . $demo_id . '/current-import/settings.json';
        
        if (!file_exists($settings_file)) {
            wp_send_json_success(array(
                'message' => __('No settings to import', 'reign-demo-install'),
                'next_step' => 'cleanup'
            ));
        }
        
        $settings_importer = new Reign_Demo_Settings_Importer();
        $results = $settings_importer->import_settings($settings_file);
        
        if (is_wp_error($results)) {
            wp_send_json_error(array(
                'message' => $results->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Settings imported successfully', 'reign-demo-install'),
            'results' => $results,
            'next_step' => 'cleanup'
        ));
    }
    
    /**
     * Process cleanup step
     */
    private function process_cleanup_step($demo_id) {
        // Restore preserved admin
        $user_preserver = new Reign_Demo_User_Preserver();
        $user_preserver->restore_admin_after_import();
        
        // End session protection
        $session_manager = new Reign_Demo_Session_Manager();
        $session_manager->end_session_protection();
        
        // Clean up temporary files
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $demo_dir = $temp_dir . $demo_id . '/';
        
        if (is_dir($demo_dir)) {
            $this->delete_directory($demo_dir);
        }
        
        // Clear import mappings
        delete_option('reign_demo_user_mapping');
        delete_option('reign_demo_post_mapping');
        delete_option('reign_demo_term_mapping');
        delete_option('reign_demo_menu_mapping');
        
        // Clear caches
        wp_cache_flush();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        wp_send_json_success(array(
            'message' => __('Import completed successfully!', 'reign-demo-install'),
            'redirect_url' => admin_url()
        ));
    }
    
    /**
     * Check session
     */
    public function check_session() {
        // Verify nonce
        if (!check_ajax_referer('reign_demo_install_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'reign-demo-install')));
        }
        
        $session_manager = new Reign_Demo_Session_Manager();
        $status = $session_manager->check_session_status();
        
        wp_send_json_success($status);
    }
    
    /**
     * Restore session
     */
    public function restore_session() {
        // Verify nonce
        if (!check_ajax_referer('reign_demo_install_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'reign-demo-install')));
        }
        
        $session_manager = new Reign_Demo_Session_Manager();
        $restored = $session_manager->restore_session();
        
        if ($restored) {
            wp_send_json_success(array(
                'message' => __('Session restored', 'reign-demo-install'),
                'user_id' => get_current_user_id()
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to restore session', 'reign-demo-install')));
        }
    }
    
    /**
     * Get demo list
     */
    public function get_demo_list() {
        // Verify nonce
        if (!check_ajax_referer('reign_demo_install_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'reign-demo-install')));
        }
        
        $demo_browser = new Reign_Demo_Browser();
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'all';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (!empty($search)) {
            $demos = $demo_browser->search_demos($search);
        } elseif ($category !== 'all') {
            $demos = $demo_browser->get_demos_by_category($category);
        } else {
            $demos = $demo_browser->get_available_demos();
        }
        
        wp_send_json_success(array(
            'demos' => $demos,
            'categories' => $demo_browser->get_demo_categories()
        ));
    }
    
    /**
     * Download demo
     */
    public function download_demo() {
        // Verify nonce
        if (!check_ajax_referer('reign_demo_install_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'reign-demo-install')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'reign-demo-install')));
        }
        
        $demo_id = isset($_POST['demo_id']) ? sanitize_text_field($_POST['demo_id']) : '';
        
        if (empty($demo_id)) {
            wp_send_json_error(array('message' => __('Demo ID required', 'reign-demo-install')));
        }
        
        // Process download
        $this->process_download_step($demo_id);
    }
    
    /**
     * Download file
     */
    private function download_file($url, $destination) {
        $response = wp_remote_get($url, array(
            'timeout' => 300,
            'stream' => true,
            'filename' => $destination
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return new WP_Error('download_failed', sprintf(__('HTTP Error: %d', 'reign-demo-install'), $response_code));
        }
        
        return true;
    }
    
    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
}