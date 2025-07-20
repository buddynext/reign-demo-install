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

class Reign_Demo_Install_Ajax_Handler {
    
    /**
     * Check requirements via AJAX
     */
    public function check_requirements() {
        // Verify nonce for security
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
        // Start output buffering to catch any PHP warnings
        ob_start();
        
        try {
            // Log for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Preserve User - Starting');
                error_log('Preserve User - User ID: ' . get_current_user_id());
            }
            
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                ob_end_clean();
                wp_send_json_error(array('message' => __('Insufficient permissions', 'reign-demo-install')));
                return;
            }
            
            // Simple approach - just verify we have a logged in admin user
            $current_user = wp_get_current_user();
            
            if ($current_user->ID > 0 && current_user_can('manage_options')) {
                // Store current user info for reference during import
                update_option('reign_demo_current_admin_id', $current_user->ID);
                update_option('reign_demo_current_admin_login', $current_user->user_login);
                
                // Simply store current user ID - don't manipulate auth cookies
                // This follows the Wbcom plugin approach which works reliably
                
                ob_end_clean();
                
                wp_send_json_success(array(
                    'message' => __('Admin user preserved', 'reign-demo-install'),
                    'user_data' => array(
                        'id' => $current_user->ID,
                        'login' => $current_user->user_login,
                        'email' => $current_user->user_email
                    )
                ));
            } else {
                ob_end_clean();
                wp_send_json_error(array('message' => __('Please log in as administrator to continue', 'reign-demo-install')));
            }
        } catch (Exception $e) {
            ob_end_clean();
            error_log('Preserve user exception: ' . $e->getMessage());
            wp_send_json_error(array('message' => __('Error preserving user session', 'reign-demo-install')));
        }
    }
    
    /**
     * Process import step
     */
    public function process_import_step() {
        // Increase time limit for import operations
        @set_time_limit(600);
        @ini_set('max_execution_time', 600);
        @ini_set('memory_limit', '512M');
        
        // TEMPORARY: More lenient security check
        // Still verify user can manage options for security
        
        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Process Import Step - Nonce: ' . (isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : 'none'));
            error_log('Process Import Step - Demo: ' . (isset($_POST['demo_id']) ? $_POST['demo_id'] : 'none'));
            error_log('Process Import Step - Step: ' . (isset($_POST['step']) ? $_POST['step'] : 'none'));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'reign-demo-install')));
        }
        
        // Simple session check - no manipulation
        $current_user_id = get_current_user_id();
        if ($current_user_id <= 0) {
            wp_send_json_error(array('message' => __('Session expired. Please log in again.', 'reign-demo-install')));
            return;
        }
        
        // Rate limiting check
        if (!$this->check_rate_limit('import_step', 10, 60)) { // 10 requests per minute
            wp_send_json_error(array('message' => __('Too many requests. Please wait before trying again.', 'reign-demo-install')));
        }
        
        // Get and sanitize parameters
        $demo_id = isset($_POST['demo_id']) ? sanitize_text_field($_POST['demo_id']) : '';
        $step = isset($_POST['step']) ? sanitize_text_field($_POST['step']) : '';
        $options = isset($_POST['options']) ? $this->sanitize_options($_POST['options']) : array();
        
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
        // Skip backup if not requested
        if (!isset($options['backup_database']) || !$options['backup_database']) {
            wp_send_json_success(array(
                'message' => __('Skipping backup as requested', 'reign-demo-install'),
                'next_step' => 'download'
            ));
            return;
        }
        
        // Set longer execution time for backup
        @set_time_limit(600); // 10 minutes
        @ini_set('memory_limit', '512M');
        
        // Check if only essential tables should be backed up
        $backup_essential_only = isset($options['backup_essential_only']) ? (bool)$options['backup_essential_only'] : false;
        if ($backup_essential_only) {
            update_option('reign_demo_backup_all_tables', false);
        } else {
            update_option('reign_demo_backup_all_tables', true);
        }
        
        try {
            $rollback_manager = new Reign_Demo_Rollback_Manager();
            
            $backup_options = array(
                'backup_database' => true,
                'backup_files' => false,    // Not needed - same site
                'backup_settings' => false,  // Already in database
                'backup_users' => false      // Already in database
            );
            
            $result = $rollback_manager->create_backup($backup_options);
        } catch (Exception $e) {
            // If backup fails, allow import to continue with warning
            wp_send_json_success(array(
                'message' => __('Backup failed, but continuing with import. Error: ', 'reign-demo-install') . $e->getMessage(),
                'warning' => true,
                'next_step' => 'download'
            ));
            return;
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Database backup created successfully', 'reign-demo-install'),
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
        
        // Create temp directory
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $demo_dir = $temp_dir . $demo_id . '/';
        
        // Always clean up existing demo folder to ensure fresh download
        if (is_dir($demo_dir)) {
            $this->recursive_rmdir($demo_dir);
        }
        
        wp_mkdir_p($demo_dir);
        
        // Download all 4 required files
        $files_to_download = array(
            'manifest.json' => $demo['manifest_url'],
            'plugins-manifest.json' => $demo['plugins_manifest_url'],
            'files-manifest.json' => $demo['files_manifest_url'],
            'content-package.zip' => $demo['package_url']
        );
        
        foreach ($files_to_download as $filename => $url) {
            if (empty($url)) {
                continue; // Skip if URL not provided
            }
            
            $local_file = $demo_dir . $filename;
            $download_result = $this->download_file($url, $local_file);
            
            if (is_wp_error($download_result)) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Failed to download %s: %s', 'reign-demo-install'), $filename, $download_result->get_error_message())
                ));
            }
        }
        
        // Extract content package
        $package_file = $demo_dir . 'content-package.zip';
        if (file_exists($package_file)) {
            $extract_dir = $demo_dir . 'extracted/';
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
        $plugins_manifest_file = $temp_dir . $demo_id . '/plugins-manifest.json';
        
        // Check if plugins manifest exists
        if (!file_exists($plugins_manifest_file)) {
            // No plugins manifest, skip to content
            wp_send_json_success(array(
                'message' => __('No plugins manifest found, skipping plugin installation', 'reign-demo-install'),
                'next_step' => 'content'
            ));
        }
        
        $plugins_manifest = json_decode(file_get_contents($plugins_manifest_file), true);
        
        if (!$plugins_manifest || !isset($plugins_manifest['plugins'])) {
            wp_send_json_success(array(
                'message' => __('No plugins to install', 'reign-demo-install'),
                'next_step' => 'content'
            ));
        }
        
        // Combine required and optional plugins
        $all_plugins = array();
        if (isset($plugins_manifest['plugins']['required']) && is_array($plugins_manifest['plugins']['required'])) {
            $all_plugins = array_merge($all_plugins, $plugins_manifest['plugins']['required']);
        }
        if (isset($plugins_manifest['plugins']['optional']) && is_array($plugins_manifest['plugins']['optional'])) {
            $all_plugins = array_merge($all_plugins, $plugins_manifest['plugins']['optional']);
        }
        
        // If plugins is a direct array (old format)
        if (isset($plugins_manifest['plugins']) && is_array($plugins_manifest['plugins']) && isset($plugins_manifest['plugins'][0])) {
            $all_plugins = $plugins_manifest['plugins'];
        }
        
        if (empty($all_plugins)) {
            wp_send_json_success(array(
                'message' => __('No plugins to install', 'reign-demo-install'),
                'next_step' => 'content'
            ));
        }
        
        $plugin_installer = new Reign_Demo_Plugin_Installer();
        $results = $plugin_installer->install_from_manifest($all_plugins);
        
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
        // No auth manipulation needed - following Wbcom's simpler approach
        
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $demo_dir = $temp_dir . $demo_id . '/extracted/';
        $content_file = $demo_dir . 'content.json';
        $export_info_file = $demo_dir . 'export-info.json';
        $database_dir = $demo_dir . 'database/';
        
        // Check which type of export we have
        if (file_exists($export_info_file) && file_exists($database_dir)) {
            // SQL-based export
            $export_info = json_decode(file_get_contents($export_info_file), true);
            
            if (isset($export_info['export_type']) && $export_info['export_type'] === 'sql') {
                // Use SQL importer
                $results = $this->import_sql_content($demo_id, $options);
                
                if (is_wp_error($results)) {
                    wp_send_json_error(array(
                        'message' => $results->get_error_message()
                    ));
                }
                
                wp_send_json_success(array(
                    'message' => __('Database content imported successfully', 'reign-demo-install'),
                    'results' => $results,
                    'next_step' => 'files'
                ));
                return;
            }
        }
        
        // JSON-based export (original logic)
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
     * Import SQL-based content
     */
    private function import_sql_content($demo_id, $options) {
        global $wpdb;
        
        // Increase time limit for SQL import
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        
        // Check for database files in extracted directory
        $database_dir = $temp_dir . $demo_id . '/extracted/database/';
        
        if (!is_dir($database_dir)) {
            return new WP_Error('no_database_dir', __('Database directory not found', 'reign-demo-install'));
        }
        
        $import_order_file = $database_dir . 'import-order.json';
        
        // Store the source prefix for later use
        $source_prefix = null;
        
        // Store current logged-in user ID to preserve their session
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        $current_user_login = $current_user->user_login;
        
        // We'll need to update their capabilities after import if prefix changes
        $preserve_user_caps = false;
        if ($current_user_id > 0) {
            $preserve_user_caps = true;
        }
        
        if (!file_exists($import_order_file)) {
            // If no import order, try to import all SQL files
            $sql_files = glob($database_dir . '*.sql');
            $gz_files = glob($database_dir . '*.sql.gz');
            $all_files = array_merge($sql_files, $gz_files);
        } else {
            // Use import order if available
            $import_order = json_decode(file_get_contents($import_order_file), true);
            $all_files = array();
            
            if (is_array($import_order)) {
                foreach ($import_order as $table_name) {
                    // Try both .sql and .sql.gz extensions
                    $sql_file = $database_dir . $table_name . '.sql';
                    $gz_file = $database_dir . $table_name . '.sql.gz';
                    
                    if (file_exists($gz_file)) {
                        $all_files[] = $gz_file;
                    } elseif (file_exists($sql_file)) {
                        $all_files[] = $sql_file;
                    }
                }
            }
        }
        
        if (empty($all_files)) {
            return new WP_Error('no_sql_files', __('No SQL files found for import', 'reign-demo-install'));
        }
        
        $results = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        // Disable foreign key checks
        $wpdb->query('SET foreign_key_checks = 0');
        
        // Define critical tables that need special handling
        $critical_tables = array(
            $wpdb->options,
            $wpdb->users,
            $wpdb->usermeta
        );
        
        // Store critical options to preserve
        $preserve_options = array(
            'siteurl',
            'home', 
            'blogname',
            'blogdescription',
            'users_can_register',
            'admin_email',
            'default_role',
            'active_plugins',
            'template',
            'stylesheet',
            'current_theme',
            'rewrite_rules',
            'permalink_structure',
            // Session and auth related
            '_site_transient_timeout_theme_roots',
            '_site_transient_theme_roots',
            'auth_key',
            'auth_salt',
            'logged_in_key',
            'logged_in_salt',
            'nonce_key',
            'nonce_salt',
            // Preserve our import flags
            'reign_demo_current_admin_id',
            'reign_demo_current_admin_login'
        );
        
        $preserved_options = array();
        foreach ($preserve_options as $option_name) {
            $preserved_options[$option_name] = get_option($option_name);
        }
        
        foreach ($all_files as $file) {
            $table_name = $this->get_table_name_from_file($file);
            
            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            )) > 0;
            
            if (!$table_exists) {
                // Skip tables that don't exist
                $results['skipped']++;
                continue;
            }
            
            // Handle tables differently based on type
            if (in_array($table_name, $critical_tables)) {
                if ($table_name === $wpdb->options) {
                    // For options table, delete non-critical options only
                    $wpdb->query("DELETE FROM `{$table_name}` WHERE option_name NOT IN ('" . implode("','", $preserve_options) . "')");
                } elseif ($table_name === $wpdb->users || $table_name === $wpdb->usermeta) {
                    // For user tables, we'll handle them specially after import
                    // Skip truncating, we'll merge the data
                }
            } else {
                // For all other tables, truncate before import
                $wpdb->query("TRUNCATE TABLE `{$table_name}`");
            }
            
            // Import the SQL file
            $import_result = $this->import_sql_file($file, $table_name);
            
            if (is_wp_error($import_result)) {
                $results['errors'][] = $import_result->get_error_message();
            } else {
                $results['imported']++;
                
                // Capture the source prefix from the first successful import
                if ($source_prefix === null && isset($import_result['source_prefix'])) {
                    $source_prefix = $import_result['source_prefix'];
                }
            }
        }
        
        // Re-enable foreign key checks
        $wpdb->query('SET foreign_key_checks = 1');
        
        // Restore preserved options
        foreach ($preserved_options as $option_name => $option_value) {
            if ($option_value !== false) {
                update_option($option_name, $option_value);
            }
        }
        
        // Fix current user's capabilities if table prefix changed
        if ($preserve_user_caps && $current_user_id > 0) {
            // Ensure the current user maintains administrator role
            $user = new WP_User($current_user_id);
            
            // If prefix changed, fix capability keys in usermeta
            if ($source_prefix && $source_prefix !== $wpdb->prefix) {
                // Update capability-related user meta keys
                $cap_key = $wpdb->prefix . 'capabilities';
                $level_key = $wpdb->prefix . 'user_level';
                
                // Get existing capabilities
                $existing_caps = get_user_meta($current_user_id, $source_prefix . 'capabilities', true);
                if (!empty($existing_caps)) {
                    // Delete old capability meta
                    delete_user_meta($current_user_id, $source_prefix . 'capabilities');
                    delete_user_meta($current_user_id, $source_prefix . 'user_level');
                    
                    // Add with new prefix
                    update_user_meta($current_user_id, $cap_key, $existing_caps);
                    update_user_meta($current_user_id, $level_key, 10);
                }
            }
            
            // Ensure administrator role
            if (!$user->has_cap('administrator')) {
                $user->set_role('administrator');
            }
            
            // Clear user cache to ensure capabilities are reloaded
            clean_user_cache($current_user_id);
            wp_cache_delete($current_user_id, 'user_meta');
            
            // Re-initialize current user to maintain session
            wp_set_current_user($current_user_id);
        }
        
        // Fix prefix-dependent data if prefix was changed
        if ($source_prefix && $source_prefix !== $wpdb->prefix) {
            $this->fix_prefix_dependent_data($source_prefix, $wpdb->prefix);
        }
        
        if (!empty($results['errors'])) {
            return new WP_Error('import_errors', implode(', ', $results['errors']));
        }
        
        return $results;
    }
    
    /**
     * Import a single SQL file
     */
    private function import_sql_file($file_path, $table_name) {
        global $wpdb;
        
        // Check if file is gzipped
        if (substr($file_path, -3) === '.gz') {
            $sql_content = gzdecode(file_get_contents($file_path));
        } else {
            $sql_content = file_get_contents($file_path);
        }
        
        if (empty($sql_content)) {
            return new WP_Error('empty_file', sprintf(__('SQL file %s is empty', 'reign-demo-install'), basename($file_path)));
        }
        
        // Detect the source table prefix from the SQL content
        $source_prefix = $this->detect_table_prefix($sql_content, $table_name);
        
        // Replace table prefix if needed
        if ($source_prefix && $source_prefix !== $wpdb->prefix) {
            // Log prefix conversion for debugging
            // Converting table prefix for compatibility
            $sql_content = $this->replace_table_prefix($sql_content, $source_prefix, $wpdb->prefix);
        }
        
        // Get current user info for special handling
        $current_user_id = get_current_user_id();
        $current_user_login = '';
        if ($current_user_id > 0) {
            $current_user = wp_get_current_user();
            $current_user_login = $current_user->user_login;
        }
        
        // Check if this is a critical table that needs special handling
        $is_user_table = ($table_name === $wpdb->users || $table_name === $wpdb->usermeta);
        $is_options_table = ($table_name === $wpdb->options);
        
        // Split SQL content by statements
        $statements = $this->split_sql_statements($sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }
            
            // For user tables, ALWAYS skip current user's data
            if ($is_user_table && stripos($statement, 'INSERT INTO') !== false && $current_user_id > 0) {
                // For users table, check if this would affect current user
                if ($table_name === $wpdb->users) {
                    // Check if INSERT contains current user's ID
                    if (preg_match('/VALUES\s*\(\s*(\d+)/i', $statement, $matches)) {
                        $importing_user_id = intval($matches[1]);
                        if ($importing_user_id === $current_user_id) {
                            continue; // Skip this INSERT entirely
                        }
                    }
                }
                
                // For usermeta table, check if this is for current user
                if ($table_name === $wpdb->usermeta) {
                    // Check if INSERT contains current user's ID in user_id field
                    if (preg_match('/VALUES\s*\([^,]+,\s*(\d+)/i', $statement, $matches)) {
                        $importing_user_id = intval($matches[1]);
                        if ($importing_user_id === $current_user_id) {
                            continue; // Skip this INSERT entirely
                        }
                    }
                }
                
                // Use INSERT IGNORE to avoid duplicate key errors for other users
                $statement = preg_replace('/INSERT\s+INTO/i', 'INSERT IGNORE INTO', $statement);
            }
            
            // For options table, skip auth-related options entirely
            if ($is_options_table && stripos($statement, 'INSERT INTO') !== false) {
                // Check if this is an auth-related option we should skip
                $skip_options = array('auth_key', 'auth_salt', 'logged_in_key', 'logged_in_salt', 'nonce_key', 'nonce_salt');
                $should_skip = false;
                
                foreach ($skip_options as $skip_option) {
                    if (strpos($statement, "'" . $skip_option . "'") !== false) {
                        $should_skip = true;
                        break;
                    }
                }
                
                if ($should_skip) {
                    continue; // Skip auth options entirely
                }
                
                // For other options, use REPLACE
                $statement = preg_replace('/INSERT\s+INTO/i', 'REPLACE INTO', $statement);
            }
            
            // Execute the statement
            $result = $wpdb->query($statement);
            
            if ($result === false) {
                // For user tables, ignore duplicate key errors
                if ($is_user_table && strpos($wpdb->last_error, 'Duplicate entry') !== false) {
                    continue;
                }
                return new WP_Error('sql_error', sprintf(__('SQL error in %s: %s', 'reign-demo-install'), basename($file_path), $wpdb->last_error));
            }
        }
        
        return array(
            'success' => true,
            'source_prefix' => $source_prefix
        );
    }
    
    /**
     * Split SQL content into individual statements
     */
    private function split_sql_statements($sql) {
        // Simple SQL statement splitter
        // This is a basic implementation and may need refinement for complex SQL
        $statements = array();
        $current = '';
        $in_string = false;
        $string_char = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            $next_char = isset($sql[$i + 1]) ? $sql[$i + 1] : '';
            
            // Handle string literals
            if (!$in_string && ($char === '"' || $char === "'")) {
                $in_string = true;
                $string_char = $char;
            } elseif ($in_string && $char === $string_char && $sql[$i - 1] !== '\\') {
                $in_string = false;
            }
            
            $current .= $char;
            
            // Statement delimiter
            if (!$in_string && $char === ';') {
                $statements[] = trim($current);
                $current = '';
            }
        }
        
        // Add any remaining SQL
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }
        
        return $statements;
    }
    
    /**
     * Get table name from SQL file name
     */
    private function get_table_name_from_file($file_path) {
        $filename = basename($file_path);
        // Remove .sql or .sql.gz extension
        $table_name = preg_replace('/\.sql(\.gz)?$/', '', $filename);
        return $table_name;
    }
    
    /**
     * Process files step
     */
    private function process_files_step($demo_id) {
        // Disable all error reporting to prevent any output
        $original_error_reporting = error_reporting(0);
        ini_set('display_errors', 0);
        
        // Start output buffering to catch any warnings or notices
        ob_start();
        
        try {
            // Quick test to ensure we can send JSON
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            
            // Simple session check
            if (get_current_user_id() <= 0) {
                throw new Exception('Session expired during import');
            }
            
            $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
            $demo_dir = $temp_dir . $demo_id . '/';
            
            // Check for different file locations
            $files_manifest = $demo_dir . 'files-manifest.json';
            $uploads_dir = $demo_dir . 'extracted/uploads/';
            
            // If we have an uploads directory, that's what we need to import
            if (is_dir($uploads_dir)) {
                // Load file importer if not already loaded
                if (!class_exists('Reign_Demo_File_Importer')) {
                    $importer_path = plugin_dir_path(dirname(__FILE__)) . 'includes/class-file-importer.php';
                    require_once $importer_path;
                }
                
                $file_importer = new Reign_Demo_File_Importer();
                // Pass the uploads directory directly
                $results = $file_importer->import_files(null, $files_manifest, $demo_id);
                
                ob_end_clean();
                error_reporting($original_error_reporting);
                
                if (is_wp_error($results)) {
                    wp_send_json_error(array(
                        'message' => $results->get_error_message()
                    ));
                    return;
                }
                
                wp_send_json_success(array(
                    'message' => __('Files imported successfully', 'reign-demo-install'),
                    'results' => $results,
                    'next_step' => 'settings'
                ));
                return;
            }
            
            // No files to import - this is OK
            ob_end_clean();
            error_reporting($original_error_reporting);
            
            wp_send_json_success(array(
                'message' => __('No files to import', 'reign-demo-install'),
                'next_step' => 'settings',
                'results' => array(
                    'imported' => 0,
                    'failed' => 0,
                    'skipped' => 0
                )
            ));
            
        } catch (Exception $e) {
            // Exception occurred during file import
            
            ob_end_clean();
            error_reporting($original_error_reporting);
            
            // Even on exception, try to continue
            wp_send_json_success(array(
                'message' => __('Files step completed with errors', 'reign-demo-install'),
                'next_step' => 'settings',
                'results' => array(
                    'imported' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                    'error' => $e->getMessage()
                )
            ));
        }
    }
    
    /**
     * Process settings step
     */
    private function process_settings_step($demo_id) {
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $settings_file = $temp_dir . $demo_id . '/extracted/settings.json';
        
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
        // Capture any output to prevent JSON errors
        ob_start();
        
        try {
            // Simple session check
            $current_user_id = get_current_user_id();
            if ($current_user_id <= 0) {
                ob_end_clean();
                wp_send_json_error(array('message' => __('Session expired. Please log in again.', 'reign-demo-install')));
                return;
            }
            
            // DO NOT restore or modify user - they should stay logged in as-is
            // Just ensure their capabilities are correct if prefix changed
            if ($current_user_id > 0) {
                $user = new WP_User($current_user_id);
                if (!$user->has_cap('administrator')) {
                    $user->set_role('administrator');
                }
            }
            
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
            
            // Flush rewrite rules - suppress any output
            @flush_rewrite_rules();
            
            // Clear any output that might have been generated
            ob_end_clean();
            
            // No cleanup needed with simpler approach
            
            wp_send_json_success(array(
                'message' => __('Import completed successfully!', 'reign-demo-install'),
                'redirect_url' => admin_url()
            ));
            
        } catch (Exception $e) {
            ob_end_clean();
            wp_send_json_error(array(
                'message' => __('Cleanup error: ', 'reign-demo-install') . $e->getMessage()
            ));
        }
    }
    
    /**
     * Check session
     */
    public function check_session() {
        // Verify nonce for security
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
        // Verify nonce for security
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
        // Verify nonce for security
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'reign_demo_install_nonce')) {
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
        // Verify nonce for security
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
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), array('http', 'https'))) {
            return new WP_Error('invalid_url', __('Invalid URL provided', 'reign-demo-install'));
        }
        
        // Only allow downloads from trusted domains
        $allowed_domains = apply_filters('reign_demo_allowed_domains', array(
            'installer.wbcomdesigns.com',
            'wbcomdesigns.com',
            'demos.wbcomdesigns.com'
        ));
        
        $domain = parse_url($url, PHP_URL_HOST);
        if (!in_array($domain, $allowed_domains)) {
            return new WP_Error('untrusted_domain', __('Downloads only allowed from trusted domains', 'reign-demo-install'));
        }
        
        $response = wp_remote_get($url, array(
            'timeout' => 300,
            'stream' => true,
            'filename' => $destination,
            'sslverify' => true, // Enforce SSL certificate validation
            'redirection' => 5,
            'user-agent' => 'Reign Demo Install/' . REIGN_DEMO_INSTALL_VERSION
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
    
    /**
     * Sanitize import options array
     * 
     * @param array $options Raw options from POST
     * @return array Sanitized options
     */
    private function sanitize_options($options) {
        if (!is_array($options)) {
            return array();
        }
        
        $sanitized = array();
        $allowed_keys = array(
            'import_content',
            'import_media', 
            'import_users',
            'import_settings',
            'clean_install',
            'backup_before_import',
            'backup_essential_only'
        );
        
        foreach ($allowed_keys as $key) {
            if (isset($options[$key])) {
                $sanitized[$key] = filter_var($options[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Check rate limiting for AJAX requests
     * 
     * @param string $action The action identifier
     * @param int $max_requests Maximum number of requests
     * @param int $window_seconds Time window in seconds
     * @return bool True if within limits, false if exceeded
     */
    private function check_rate_limit($action, $max_requests = 5, $window_seconds = 60) {
        $user_id = get_current_user_id();
        $transient_key = 'reign_demo_rate_' . $action . '_' . $user_id;
        
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            // First request
            set_transient($transient_key, 1, $window_seconds);
            return true;
        }
        
        if ($attempts >= $max_requests) {
            // Rate limit exceeded
            return false;
        }
        
        // Increment counter
        set_transient($transient_key, $attempts + 1, $window_seconds);
        return true;
    }
    
    /**
     * Check plugin requirements for a demo
     */
    public function check_plugins() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'reign-demo-install')));
        }
        
        $demo_id = isset($_POST['demo_id']) ? sanitize_text_field($_POST['demo_id']) : '';
        
        if (empty($demo_id)) {
            wp_send_json_error(array('message' => __('Demo ID required', 'reign-demo-install')));
        }
        
        // Get demo info
        $demo_browser = new Reign_Demo_Browser();
        $demo = $demo_browser->get_demo_by_id($demo_id);
        
        if (!$demo) {
            wp_send_json_error(array('message' => __('Demo not found', 'reign-demo-install')));
        }
        
        // Check if plugins manifest URL exists
        if (empty($demo['plugins_manifest_url'])) {
            // No plugins required
            wp_send_json_success(array('plugins' => array()));
        }
        
        // Check if already downloaded locally
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $local_plugins_manifest = $temp_dir . $demo_id . '/plugins-manifest.json';
        
        if (file_exists($local_plugins_manifest)) {
            // Use local file
            $manifest = json_decode(file_get_contents($local_plugins_manifest), true);
        } else {
            // Download plugins manifest
            $response = wp_remote_get($demo['plugins_manifest_url'], array(
                'timeout' => 30,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => __('Failed to fetch plugin requirements', 'reign-demo-install')));
            }
            
            $manifest = json_decode(wp_remote_retrieve_body($response), true);
        }
        
        if (!$manifest || !isset($manifest['plugins'])) {
            wp_send_json_success(array('plugins' => array()));
        }
        
        // Combine required and optional plugins
        $all_plugins = array();
        if (isset($manifest['plugins']['required']) && is_array($manifest['plugins']['required'])) {
            $all_plugins = array_merge($all_plugins, $manifest['plugins']['required']);
        }
        if (isset($manifest['plugins']['optional']) && is_array($manifest['plugins']['optional'])) {
            $all_plugins = array_merge($all_plugins, $manifest['plugins']['optional']);
        }
        
        // If plugins is a direct array (old format)
        if (isset($manifest['plugins']) && is_array($manifest['plugins']) && isset($manifest['plugins'][0])) {
            $all_plugins = $manifest['plugins'];
        }
        
        // Check status of each plugin
        $plugin_installer = new Reign_Demo_Plugin_Installer();
        $plugin_status = array();
        
        foreach ($all_plugins as $plugin) {
            if (!isset($plugin['slug']) || !isset($plugin['name'])) {
                continue; // Skip invalid entries
            }
            
            $slug = $plugin['slug'];
            $status = $plugin_installer->check_plugins_status(array($slug));
            
            $plugin_status[$slug] = array(
                'name' => $plugin['name'],
                'slug' => $slug,
                'required' => isset($plugin['required']) ? $plugin['required'] : false,
                'version' => isset($plugin['version']) ? $plugin['version'] : '',
                'installed' => $status[$slug]['installed'],
                'active' => $status[$slug]['active'],
                'current_version' => $status[$slug]['version'],
                'source' => isset($plugin['source']) ? $plugin['source'] : 'wordpress'
            );
        }
        
        wp_send_json_success(array(
            'plugins' => $plugin_status
        ));
    }
    
    /**
     * Install missing plugins for a demo
     */
    public function install_plugins() {
        // Check user capabilities
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(array('message' => __('Insufficient permissions to install plugins', 'reign-demo-install')));
        }
        
        $demo_id = isset($_POST['demo_id']) ? sanitize_text_field($_POST['demo_id']) : '';
        
        if (empty($demo_id)) {
            wp_send_json_error(array('message' => __('Demo ID required', 'reign-demo-install')));
        }
        
        // Set longer execution time
        @set_time_limit(600);
        
        // Check if already downloaded locally
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $local_plugins_manifest = $temp_dir . $demo_id . '/plugins-manifest.json';
        
        if (file_exists($local_plugins_manifest)) {
            // Use local file
            $manifest = json_decode(file_get_contents($local_plugins_manifest), true);
        } else {
            // Get demo info and download
            $demo_browser = new Reign_Demo_Browser();
            $demo = $demo_browser->get_demo_by_id($demo_id);
            
            if (!$demo || empty($demo['plugins_manifest_url'])) {
                wp_send_json_error(array('message' => __('Demo or plugin manifest not found', 'reign-demo-install')));
            }
            
            // Download plugins manifest
            $response = wp_remote_get($demo['plugins_manifest_url'], array(
                'timeout' => 30,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => __('Failed to fetch plugin manifest', 'reign-demo-install')));
            }
            
            $manifest = json_decode(wp_remote_retrieve_body($response), true);
        }
        
        if (!$manifest || !isset($manifest['plugins'])) {
            wp_send_json_error(array('message' => __('Invalid plugin manifest', 'reign-demo-install')));
        }
        
        // Combine required and optional plugins
        $all_plugins = array();
        if (isset($manifest['plugins']['required']) && is_array($manifest['plugins']['required'])) {
            $all_plugins = array_merge($all_plugins, $manifest['plugins']['required']);
        }
        if (isset($manifest['plugins']['optional']) && is_array($manifest['plugins']['optional'])) {
            $all_plugins = array_merge($all_plugins, $manifest['plugins']['optional']);
        }
        
        // If plugins is a direct array (old format)
        if (isset($manifest['plugins']) && is_array($manifest['plugins']) && isset($manifest['plugins'][0])) {
            $all_plugins = $manifest['plugins'];
        }
        
        if (empty($all_plugins)) {
            wp_send_json_error(array('message' => __('No plugins found in manifest', 'reign-demo-install')));
        }
        
        // Install plugins
        $plugin_installer = new Reign_Demo_Plugin_Installer();
        $results = $plugin_installer->install_from_manifest($all_plugins);
        
        // Check for errors
        $errors = $plugin_installer->get_errors();
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => __('Some plugins failed to install', 'reign-demo-install'),
                'errors' => $errors,
                'results' => $results
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Plugins installed successfully', 'reign-demo-install'),
            'results' => $results
        ));
    }
    
    /**
     * Detect table prefix from SQL content
     */
    private function detect_table_prefix($sql_content, $table_name) {
        // Common patterns to detect table prefix
        $patterns = array(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)' . preg_quote($table_name, '/') . '`?/i',
            '/INSERT\s+INTO\s+`?(\w+)' . preg_quote($table_name, '/') . '`?/i',
            '/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?(\w+)' . preg_quote($table_name, '/') . '`?/i',
            '/ALTER\s+TABLE\s+`?(\w+)' . preg_quote($table_name, '/') . '`?/i',
            '/TRUNCATE\s+TABLE\s+`?(\w+)' . preg_quote($table_name, '/') . '`?/i'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql_content, $matches)) {
                // Extract prefix from full table name
                $full_table_name = $matches[1] . $table_name;
                $prefix = str_replace($table_name, '', $full_table_name);
                return $prefix;
            }
        }
        
        // Try to detect from the table name in the SQL file
        // Look for common WordPress table patterns
        if (preg_match('/(?:CREATE|INSERT|DROP|ALTER|TRUNCATE)\s+(?:TABLE|INTO)\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?`?(\w+_)(?:posts|postmeta|users|usermeta|terms|termmeta|options|comments|commentmeta)/i', $sql_content, $matches)) {
            return $matches[1];
        }
        
        // Default to wp_ if no prefix detected
        return 'wp_';
    }
    
    /**
     * Replace table prefix in SQL content
     */
    private function replace_table_prefix($sql_content, $old_prefix, $new_prefix) {
        if ($old_prefix === $new_prefix) {
            return $sql_content;
        }
        
        // Patterns to match table names with the old prefix
        $patterns = array(
            // CREATE TABLE statements
            '/(CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)`?' . preg_quote($old_prefix, '/') . '(\w+)`?/i',
            // INSERT INTO statements
            '/(INSERT\s+(?:IGNORE\s+)?INTO\s+)`?' . preg_quote($old_prefix, '/') . '(\w+)`?/i',
            // DROP TABLE statements
            '/(DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?)`?' . preg_quote($old_prefix, '/') . '(\w+)`?/i',
            // ALTER TABLE statements
            '/(ALTER\s+TABLE\s+)`?' . preg_quote($old_prefix, '/') . '(\w+)`?/i',
            // TRUNCATE TABLE statements
            '/(TRUNCATE\s+TABLE\s+)`?' . preg_quote($old_prefix, '/') . '(\w+)`?/i',
            // UPDATE statements
            '/(UPDATE\s+)`?' . preg_quote($old_prefix, '/') . '(\w+)`?/i',
            // DELETE FROM statements
            '/(DELETE\s+FROM\s+)`?' . preg_quote($old_prefix, '/') . '(\w+)`?/i',
            // SELECT statements (less common in dumps but possible)
            '/(FROM\s+|JOIN\s+)`?' . preg_quote($old_prefix, '/') . '(\w+)`?/i',
            // Table references in backticks
            '/`' . preg_quote($old_prefix, '/') . '(\w+)`/i'
        );
        
        $replacements = array(
            '$1`' . $new_prefix . '$2`',
            '$1`' . $new_prefix . '$2`',
            '$1`' . $new_prefix . '$2`',
            '$1`' . $new_prefix . '$2`',
            '$1`' . $new_prefix . '$2`',
            '$1`' . $new_prefix . '$2`',
            '$1`' . $new_prefix . '$2`',
            '$1`' . $new_prefix . '$2`',
            '`' . $new_prefix . '$1`'
        );
        
        // Replace all occurrences
        $sql_content = preg_replace($patterns, $replacements, $sql_content);
        
        // Also handle serialized data that might contain table names
        // This is important for options that reference table names
        if ($old_prefix !== $new_prefix) {
            // Replace serialized string references
            $sql_content = $this->replace_serialized_table_prefix($sql_content, $old_prefix, $new_prefix);
        }
        
        return $sql_content;
    }
    
    /**
     * Replace table prefix in serialized data
     */
    private function replace_serialized_table_prefix($sql_content, $old_prefix, $new_prefix) {
        // Pattern to match serialized strings containing table names
        $pattern = '/s:(\d+):"(' . preg_quote($old_prefix, '/') . '\w+)"/';
        
        return preg_replace_callback($pattern, function($matches) use ($old_prefix, $new_prefix) {
            $new_table_name = str_replace($old_prefix, $new_prefix, $matches[2]);
            $new_length = strlen($new_table_name);
            return 's:' . $new_length . ':"' . $new_table_name . '"';
        }, $sql_content);
    }
    
    /**
     * Fix prefix-dependent data after import
     */
    private function fix_prefix_dependent_data($old_prefix, $new_prefix) {
        global $wpdb;
        
        if ($old_prefix === $new_prefix) {
            return;
        }
        
        // Fix prefix-dependent data in database
        
        // 1. Update user meta keys that contain the prefix
        $user_meta_keys = array(
            'capabilities',
            'user_level',
            'user-settings',
            'user-settings-time',
            'dashboard_quick_press_last_post_id',
            'media_library_mode',
            'metaboxhidden_',
            'managenav-menuscolumnshidden',
            'meta-box-order_',
            'screen_layout_',
            'closedpostboxes_'
        );
        
        foreach ($user_meta_keys as $meta_key_suffix) {
            // Handle exact matches
            if (strpos($meta_key_suffix, '_') === false) {
                $old_key = $old_prefix . $meta_key_suffix;
                $new_key = $new_prefix . $meta_key_suffix;
                
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->usermeta} 
                     SET meta_key = %s 
                     WHERE meta_key = %s",
                    $new_key,
                    $old_key
                ));
            } else {
                // Handle prefix patterns (like metaboxhidden_*)
                $old_pattern = $old_prefix . $meta_key_suffix . '%';
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->usermeta} 
                     SET meta_key = REPLACE(meta_key, %s, %s) 
                     WHERE meta_key LIKE %s",
                    $old_prefix,
                    $new_prefix,
                    $old_pattern
                ));
            }
        }
        
        // 2. Update the user_roles option
        $old_roles_option = $old_prefix . 'user_roles';
        $new_roles_option = $new_prefix . 'user_roles';
        
        $roles = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $old_roles_option
        ));
        
        if ($roles) {
            // Delete old option
            $wpdb->delete($wpdb->options, array('option_name' => $old_roles_option));
            
            // Insert with new key
            $wpdb->insert($wpdb->options, array(
                'option_name' => $new_roles_option,
                'option_value' => $roles,
                'autoload' => 'yes'
            ));
        }
        
        // 3. Update options that may contain table names in their values
        $options_to_check = array(
            'cron',
            'rewrite_rules',
            $new_prefix . 'user_roles',
            'widget_%',
            'theme_mods_%',
            'transient_%',
            'site_transient_%'
        );
        
        foreach ($options_to_check as $option_pattern) {
            if (strpos($option_pattern, '%') !== false) {
                $options = $wpdb->get_results($wpdb->prepare(
                    "SELECT option_name, option_value 
                     FROM {$wpdb->options} 
                     WHERE option_name LIKE %s 
                     AND option_value LIKE %s",
                    $option_pattern,
                    '%' . $old_prefix . '%'
                ));
            } else {
                $options = $wpdb->get_results($wpdb->prepare(
                    "SELECT option_name, option_value 
                     FROM {$wpdb->options} 
                     WHERE option_name = %s 
                     AND option_value LIKE %s",
                    $option_pattern,
                    '%' . $old_prefix . '%'
                ));
            }
            
            foreach ($options as $option) {
                $new_value = $this->fix_serialized_data($option->option_value, $old_prefix, $new_prefix);
                if ($new_value !== $option->option_value) {
                    $wpdb->update(
                        $wpdb->options,
                        array('option_value' => $new_value),
                        array('option_name' => $option->option_name)
                    );
                }
            }
        }
        
        // 4. Fix BuddyPress/BuddyBoss specific meta keys if plugin is active
        if (function_exists('bp_is_active')) {
            $bp_meta_keys = array(
                'total_friend_count',
                'total_group_count',
                'last_activity',
                'notification_groups_group_updated',
                'notification_groups_membership_request',
                'notification_membership_request_completed'
            );
            
            foreach ($bp_meta_keys as $bp_key) {
                $old_key = $old_prefix . $bp_key;
                $new_key = $new_prefix . $bp_key;
                
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->usermeta} 
                     SET meta_key = %s 
                     WHERE meta_key = %s",
                    $new_key,
                    $old_key
                ));
            }
        }
        
        // 5. Clear all caches
        wp_cache_flush();
        
        // Clear user meta cache
        $user_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->users}");
        foreach ($user_ids as $user_id) {
            clean_user_cache($user_id);
        }
        
        // Clear options cache
        wp_cache_delete('alloptions', 'options');
        
        // Prefix conversion completed
    }
    
    /**
     * Fix serialized data with old prefix
     */
    private function fix_serialized_data($data, $old_prefix, $new_prefix) {
        // If it's serialized, unserialize, fix, and reserialize
        $unserialized = @unserialize($data);
        
        if ($unserialized !== false) {
            $fixed = $this->recursive_prefix_replace($unserialized, $old_prefix, $new_prefix);
            return serialize($fixed);
        }
        
        // If not serialized, just do string replace
        return str_replace($old_prefix, $new_prefix, $data);
    }
    
    /**
     * Recursively replace prefix in arrays and objects
     */
    private function recursive_prefix_replace($data, $old_prefix, $new_prefix) {
        if (is_array($data)) {
            $fixed = array();
            foreach ($data as $key => $value) {
                $new_key = is_string($key) ? str_replace($old_prefix, $new_prefix, $key) : $key;
                $fixed[$new_key] = $this->recursive_prefix_replace($value, $old_prefix, $new_prefix);
            }
            return $fixed;
        } elseif (is_object($data)) {
            $fixed = new stdClass();
            foreach ($data as $key => $value) {
                $new_key = str_replace($old_prefix, $new_prefix, $key);
                $fixed->$new_key = $this->recursive_prefix_replace($value, $old_prefix, $new_prefix);
            }
            return $fixed;
        } elseif (is_string($data)) {
            return str_replace($old_prefix, $new_prefix, $data);
        }
        
        return $data;
    }
    
    /**
     * Recursively remove directory
     */
    private function recursive_rmdir($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursive_rmdir($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}