<?php
/**
 * Rollback Manager Class
 * 
 * Handles backup and rollback functionality
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Rollback_Manager {
    
    private $backup_dir;
    private $backup_id;
    private $backup_data = array();
    
    public function __construct() {
        $this->backup_dir = WP_CONTENT_DIR . '/reign-demo-backups/';
        $this->ensure_backup_directory();
    }
    
    /**
     * Create backup before import
     */
    public function create_backup($backup_options = array()) {
        $this->backup_id = 'backup_' . date('Y-m-d_H-i-s') . '_' . uniqid();
        $backup_path = $this->backup_dir . $this->backup_id . '/';
        
        // Create backup directory
        if (!wp_mkdir_p($backup_path)) {
            return new WP_Error('backup_failed', __('Failed to create backup directory', 'reign-demo-install'));
        }
        
        // Set default options - only database backup needed
        $defaults = array(
            'backup_database' => true,
            'backup_files' => false,  // Files don't change, no need to backup
            'backup_settings' => false, // Settings are part of database
            'backup_users' => false,    // Users are part of database
            'compression' => true
        );
        
        $options = wp_parse_args($backup_options, $defaults);
        
        // Initialize backup data
        $this->backup_data = array(
            'id' => $this->backup_id,
            'date' => current_time('mysql'),
            'wp_version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'options' => $options
        );
        
        $results = array();
        
        // Only backup database - files don't change on same site
        if ($options['backup_database']) {
            $results['database'] = $this->backup_database($backup_path);
        }
        
        // Save backup manifest
        $this->save_backup_manifest($backup_path);
        
        // Compress backup if requested
        if ($options['compression']) {
            $this->compress_backup($backup_path);
        }
        
        // Store backup info
        $this->store_backup_info();
        
        return array(
            'backup_id' => $this->backup_id,
            'backup_path' => $backup_path,
            'results' => $results
        );
    }
    
    /**
     * Backup database
     */
    private function backup_database($backup_path) {
        global $wpdb;
        
        try {
            $tables_backed_up = 0;
            $db_backup_file = $backup_path . 'database.sql';
            
            // For large databases, only backup essential tables
            $essential_tables = array(
                $wpdb->prefix . 'options',
                $wpdb->prefix . 'users',
                $wpdb->prefix . 'usermeta'
            );
            
            // Get all tables or just essential ones
            $backup_all = get_option('reign_demo_backup_all_tables', false);
            if ($backup_all) {
                $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
            } else {
                $tables = array();
                foreach ($essential_tables as $table) {
                    if ($wpdb->get_var("SHOW TABLES LIKE '$table'")) {
                        $tables[] = array($table);
                    }
                }
            }
        
        $backup_content = "-- WordPress Database Backup\n";
        $backup_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Host: " . DB_HOST . "\n";
        $backup_content .= "-- Database: " . DB_NAME . "\n\n";
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Skip non-WordPress tables if prefix is set
            if ($wpdb->prefix && strpos($table_name, $wpdb->prefix) !== 0) {
                continue;
            }
            
            // Get table structure
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_A);
            $backup_content .= "\n-- Table: {$table_name}\n";
            $backup_content .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
            $backup_content .= $create_table['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = $wpdb->get_results("SELECT * FROM `{$table_name}`", ARRAY_A);
            
            if ($rows) {
                $backup_content .= "-- Data for table: {$table_name}\n";
                
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        if (is_null($value)) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $wpdb->_real_escape($value) . "'";
                        }
                    }
                    
                    $backup_content .= "INSERT INTO `{$table_name}` VALUES (" . implode(',', $values) . ");\n";
                }
            }
            
            $tables_backed_up++;
        }
        
        // Save to file
        file_put_contents($db_backup_file, $backup_content);
        
        // Compress if large
        if (filesize($db_backup_file) > 10485760) { // 10MB
            $this->compress_file($db_backup_file);
        }
        
            return array(
                'tables_backed_up' => $tables_backed_up,
                'backup_file' => basename($db_backup_file)
            );
        } catch (Exception $e) {
            error_log('Reign Demo Install - Database backup error: ' . $e->getMessage());
            throw new Exception('Database backup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Backup files
     */
    private function backup_files($backup_path) {
        $files_backed_up = 0;
        
        // Backup uploads directory
        $upload_dir = wp_upload_dir();
        $source = $upload_dir['basedir'];
        $destination = $backup_path . 'uploads/';
        
        if (is_dir($source)) {
            $this->copy_directory($source, $destination);
            $files_backed_up++;
        }
        
        // Backup theme files
        $theme_dir = get_theme_root() . '/' . get_stylesheet();
        $theme_destination = $backup_path . 'themes/' . get_stylesheet() . '/';
        
        if (is_dir($theme_dir)) {
            $this->copy_directory($theme_dir, $theme_destination);
            $files_backed_up++;
        }
        
        // Backup must-use plugins
        if (defined('WPMU_PLUGIN_DIR') && is_dir(WPMU_PLUGIN_DIR)) {
            $mu_destination = $backup_path . 'mu-plugins/';
            $this->copy_directory(WPMU_PLUGIN_DIR, $mu_destination);
            $files_backed_up++;
        }
        
        return array(
            'files_backed_up' => $files_backed_up
        );
    }
    
    /**
     * Backup settings
     */
    private function backup_settings($backup_path) {
        global $wpdb;
        
        $settings = array(
            'options' => array(),
            'theme_mods' => array(),
            'widgets' => array(),
            'menus' => array()
        );
        
        // Backup all options
        $options = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name NOT LIKE '_transient_%' 
             AND option_name NOT LIKE '_site_transient_%'"
        );
        
        foreach ($options as $option) {
            $settings['options'][$option->option_name] = maybe_unserialize($option->option_value);
        }
        
        // Backup theme mods
        $theme_slug = get_option('stylesheet');
        $settings['theme_mods'][$theme_slug] = get_theme_mods();
        
        // Backup widgets
        $settings['widgets'] = array(
            'sidebars' => get_option('sidebars_widgets'),
            'instances' => array()
        );
        
        // Get all widget instances
        foreach (wp_get_sidebars_widgets() as $sidebar => $widgets) {
            foreach ($widgets as $widget_id) {
                $widget_type = substr($widget_id, 0, strrpos($widget_id, '-'));
                if (!isset($settings['widgets']['instances'][$widget_type])) {
                    $settings['widgets']['instances'][$widget_type] = get_option('widget_' . $widget_type);
                }
            }
        }
        
        // Backup menus
        $menus = wp_get_nav_menus();
        foreach ($menus as $menu) {
            $menu_items = wp_get_nav_menu_items($menu->term_id);
            $settings['menus'][] = array(
                'menu' => $menu,
                'items' => $menu_items
            );
        }
        
        // Save settings
        $settings_file = $backup_path . 'settings.json';
        file_put_contents($settings_file, wp_json_encode($settings, JSON_PRETTY_PRINT));
        
        return array(
            'options_backed_up' => count($settings['options']),
            'backup_file' => 'settings.json'
        );
    }
    
    /**
     * Backup users
     */
    private function backup_users($backup_path) {
        $users_data = array();
        
        $users = get_users(array('number' => -1));
        
        foreach ($users as $user) {
            $user_data = array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'user_pass' => $user->user_pass,
                'user_nicename' => $user->user_nicename,
                'user_url' => $user->user_url,
                'user_registered' => $user->user_registered,
                'user_status' => $user->user_status,
                'display_name' => $user->display_name,
                'roles' => $user->roles,
                'caps' => $user->caps,
                'meta' => get_user_meta($user->ID)
            );
            
            // Add BuddyPress data if available
            if (function_exists('bp_get_user_meta')) {
                $user_data['bp_meta'] = bp_get_user_meta($user->ID, '', true);
            }
            
            $users_data[] = $user_data;
        }
        
        // Save users data
        $users_file = $backup_path . 'users.json';
        file_put_contents($users_file, wp_json_encode($users_data, JSON_PRETTY_PRINT));
        
        return array(
            'users_backed_up' => count($users_data),
            'backup_file' => 'users.json'
        );
    }
    
    /**
     * Restore from backup
     */
    public function restore_backup($backup_id) {
        $backup_info = $this->get_backup_info($backup_id);
        
        if (!$backup_info) {
            return new WP_Error('backup_not_found', __('Backup not found', 'reign-demo-install'));
        }
        
        $backup_path = $this->backup_dir . $backup_id . '/';
        
        if (!is_dir($backup_path)) {
            return new WP_Error('backup_dir_not_found', __('Backup directory not found', 'reign-demo-install'));
        }
        
        $results = array();
        
        // Only restore database - we only backup database
        if (file_exists($backup_path . 'database.sql') || file_exists($backup_path . 'database.sql.gz')) {
            $results['database'] = $this->restore_database($backup_path);
        } else {
            return new WP_Error('no_backup_found', __('No database backup found', 'reign-demo-install'));
        }
        
        // Clear caches
        wp_cache_flush();
        
        return $results;
    }
    
    /**
     * Restore database
     */
    private function restore_database($backup_path) {
        global $wpdb;
        
        $db_file = $backup_path . 'database.sql';
        
        // Check for compressed version
        if (!file_exists($db_file) && file_exists($db_file . '.gz')) {
            $this->decompress_file($db_file . '.gz');
        }
        
        if (!file_exists($db_file)) {
            return new WP_Error('db_backup_not_found', __('Database backup not found', 'reign-demo-install'));
        }
        
        // Read SQL file
        $sql_content = file_get_contents($db_file);
        
        // Split into individual queries
        $queries = preg_split('/;\s*$/m', $sql_content);
        
        $executed = 0;
        $failed = 0;
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) {
                continue;
            }
            
            $result = $wpdb->query($query);
            
            if ($result === false) {
                $failed++;
            } else {
                $executed++;
            }
        }
        
        return array(
            'queries_executed' => $executed,
            'queries_failed' => $failed
        );
    }
    
    /**
     * Restore files
     */
    private function restore_files($backup_path) {
        $restored = 0;
        
        // Restore uploads
        if (is_dir($backup_path . 'uploads/')) {
            $upload_dir = wp_upload_dir();
            $this->delete_directory($upload_dir['basedir']);
            $this->copy_directory($backup_path . 'uploads/', $upload_dir['basedir']);
            $restored++;
        }
        
        // Restore theme
        $theme_backup = $backup_path . 'themes/' . get_stylesheet() . '/';
        if (is_dir($theme_backup)) {
            $theme_dir = get_theme_root() . '/' . get_stylesheet();
            $this->delete_directory($theme_dir);
            $this->copy_directory($theme_backup, $theme_dir);
            $restored++;
        }
        
        // Restore mu-plugins
        if (is_dir($backup_path . 'mu-plugins/') && defined('WPMU_PLUGIN_DIR')) {
            $this->delete_directory(WPMU_PLUGIN_DIR);
            $this->copy_directory($backup_path . 'mu-plugins/', WPMU_PLUGIN_DIR);
            $restored++;
        }
        
        return array(
            'directories_restored' => $restored
        );
    }
    
    /**
     * Restore settings
     */
    private function restore_settings($backup_path) {
        $settings_file = $backup_path . 'settings.json';
        $settings = json_decode(file_get_contents($settings_file), true);
        
        if (!$settings) {
            return new WP_Error('invalid_settings', __('Invalid settings backup', 'reign-demo-install'));
        }
        
        $restored = 0;
        
        // Restore options
        if (isset($settings['options'])) {
            foreach ($settings['options'] as $option_name => $option_value) {
                update_option($option_name, $option_value);
                $restored++;
            }
        }
        
        // Restore theme mods
        if (isset($settings['theme_mods'])) {
            foreach ($settings['theme_mods'] as $theme => $mods) {
                if ($theme === get_option('stylesheet')) {
                    foreach ($mods as $mod_name => $mod_value) {
                        set_theme_mod($mod_name, $mod_value);
                    }
                }
            }
        }
        
        // Restore widgets
        if (isset($settings['widgets'])) {
            if (isset($settings['widgets']['sidebars'])) {
                update_option('sidebars_widgets', $settings['widgets']['sidebars']);
            }
            
            if (isset($settings['widgets']['instances'])) {
                foreach ($settings['widgets']['instances'] as $widget_type => $instances) {
                    update_option('widget_' . $widget_type, $instances);
                }
            }
        }
        
        return array(
            'options_restored' => $restored
        );
    }
    
    /**
     * Restore users
     */
    private function restore_users($backup_path) {
        $users_file = $backup_path . 'users.json';
        $users_data = json_decode(file_get_contents($users_file), true);
        
        if (!$users_data) {
            return new WP_Error('invalid_users', __('Invalid users backup', 'reign-demo-install'));
        }
        
        $restored = 0;
        
        foreach ($users_data as $user_data) {
            // Check if user exists
            $existing_user = get_user_by('email', $user_data['user_email']);
            
            if (!$existing_user) {
                // Create user
                $user_id = wp_insert_user(array(
                    'user_login' => $user_data['user_login'],
                    'user_email' => $user_data['user_email'],
                    'user_pass' => $user_data['user_pass'],
                    'user_nicename' => $user_data['user_nicename'],
                    'user_url' => $user_data['user_url'],
                    'user_registered' => $user_data['user_registered'],
                    'user_status' => $user_data['user_status'],
                    'display_name' => $user_data['display_name'],
                    'role' => $user_data['roles'][0] ?? 'subscriber'
                ));
                
                if (!is_wp_error($user_id)) {
                    // Restore user meta
                    if (isset($user_data['meta'])) {
                        foreach ($user_data['meta'] as $meta_key => $meta_value) {
                            update_user_meta($user_id, $meta_key, maybe_unserialize($meta_value[0]));
                        }
                    }
                    
                    $restored++;
                }
            }
        }
        
        return array(
            'users_restored' => $restored
        );
    }
    
    /**
     * Ensure backup directory exists
     */
    private function ensure_backup_directory() {
        if (!is_dir($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
            
            // Add .htaccess to protect backups
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($this->backup_dir . '.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Copy directory recursively
     */
    private function copy_directory($source, $destination) {
        if (!is_dir($destination)) {
            wp_mkdir_p($destination);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $dest_path = $destination . '/' . $iterator->getSubPathName();
            
            if ($file->isDir()) {
                wp_mkdir_p($dest_path);
            } else {
                copy($file->getPathname(), $dest_path);
            }
        }
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
     * Compress file
     */
    private function compress_file($file_path) {
        $gz_file = $file_path . '.gz';
        
        $fp_in = fopen($file_path, 'rb');
        $fp_out = gzopen($gz_file, 'wb9');
        
        while (!feof($fp_in)) {
            gzwrite($fp_out, fread($fp_in, 524288)); // 512KB chunks
        }
        
        fclose($fp_in);
        gzclose($fp_out);
        
        // Remove original file
        unlink($file_path);
    }
    
    /**
     * Decompress file
     */
    private function decompress_file($gz_file) {
        $file_path = str_replace('.gz', '', $gz_file);
        
        $fp_in = gzopen($gz_file, 'rb');
        $fp_out = fopen($file_path, 'wb');
        
        while (!gzeof($fp_in)) {
            fwrite($fp_out, gzread($fp_in, 524288)); // 512KB chunks
        }
        
        gzclose($fp_in);
        fclose($fp_out);
    }
    
    /**
     * Compress backup directory
     */
    private function compress_backup($backup_path) {
        $zip_file = $this->backup_dir . $this->backup_id . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
            $this->add_directory_to_zip($zip, $backup_path, '');
            $zip->close();
            
            // Remove uncompressed backup
            $this->delete_directory($backup_path);
        }
    }
    
    /**
     * Add directory to zip
     */
    private function add_directory_to_zip($zip, $dir, $base_path) {
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $file_path = $dir . '/' . $file;
            $relative_path = $base_path . '/' . $file;
            
            if (is_dir($file_path)) {
                $zip->addEmptyDir($relative_path);
                $this->add_directory_to_zip($zip, $file_path, $relative_path);
            } else {
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
    
    /**
     * Save backup manifest
     */
    private function save_backup_manifest($backup_path) {
        $manifest = array(
            'backup_data' => $this->backup_data,
            'files' => $this->get_backup_files($backup_path),
            'size' => $this->get_directory_size($backup_path)
        );
        
        file_put_contents($backup_path . 'manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get backup files
     */
    private function get_backup_files($backup_path) {
        $files = array();
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backup_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $files[] = str_replace($backup_path, '', $file->getPathname());
            }
        }
        
        return $files;
    }
    
    /**
     * Get directory size
     */
    private function get_directory_size($dir) {
        $size = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Store backup info
     */
    private function store_backup_info() {
        $backups = get_option('reign_demo_backups', array());
        
        $backups[$this->backup_id] = array(
            'id' => $this->backup_id,
            'date' => $this->backup_data['date'],
            'size' => $this->get_directory_size($this->backup_dir . $this->backup_id . '/'),
            'compressed' => file_exists($this->backup_dir . $this->backup_id . '.zip')
        );
        
        update_option('reign_demo_backups', $backups);
    }
    
    /**
     * Get backup info
     */
    private function get_backup_info($backup_id) {
        $backups = get_option('reign_demo_backups', array());
        return isset($backups[$backup_id]) ? $backups[$backup_id] : null;
    }
    
    /**
     * List available backups
     */
    public function list_backups() {
        return get_option('reign_demo_backups', array());
    }
    
    /**
     * Delete backup
     */
    public function delete_backup($backup_id) {
        $backup_path = $this->backup_dir . $backup_id . '/';
        $backup_zip = $this->backup_dir . $backup_id . '.zip';
        
        // Delete directory
        if (is_dir($backup_path)) {
            $this->delete_directory($backup_path);
        }
        
        // Delete zip file
        if (file_exists($backup_zip)) {
            unlink($backup_zip);
        }
        
        // Remove from stored backups
        $backups = get_option('reign_demo_backups', array());
        unset($backups[$backup_id]);
        update_option('reign_demo_backups', $backups);
        
        return true;
    }
    
    /**
     * Clean old backups
     */
    public function clean_old_backups($days = 30) {
        $backups = $this->list_backups();
        $cutoff_date = strtotime('-' . $days . ' days');
        $deleted = 0;
        
        foreach ($backups as $backup_id => $backup_info) {
            if (strtotime($backup_info['date']) < $cutoff_date) {
                $this->delete_backup($backup_id);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}