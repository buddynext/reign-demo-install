<?php
/**
 * Requirements Checker Class
 * 
 * Checks system requirements before allowing demo import
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Requirements_Checker {
    
    private $requirements = array();
    private $errors = array();
    private $warnings = array();
    
    /**
     * Check all requirements
     */
    public function check_all_requirements() {
        $this->requirements = array(
            'php_version' => $this->check_php_version(),
            'wp_version' => $this->check_wp_version(),
            'memory_limit' => $this->check_memory_limit(),
            'max_execution_time' => $this->check_execution_time(),
            'reign_theme' => $this->check_reign_theme(),
            'disk_space' => $this->check_disk_space(),
            'user_logged_in' => $this->check_user_logged_in(),
            'user_is_admin' => $this->check_user_is_admin(),
            'writable_directories' => $this->check_writable_directories(),
            'database_access' => $this->check_database_access(),
            'safe_mode' => $this->check_safe_mode(),
            'file_uploads' => $this->check_file_uploads(),
            'curl_enabled' => $this->check_curl()
        );
        
        return array(
            'passed' => empty($this->errors),
            'requirements' => $this->requirements,
            'errors' => $this->errors,
            'warnings' => $this->warnings
        );
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version() {
        $required = '7.4';
        $current = PHP_VERSION;
        $passed = version_compare($current, $required, '>=');
        
        if (!$passed) {
            $this->errors[] = sprintf(
                __('PHP version %s or higher is required. You have %s.', 'reign-demo-install'),
                $required,
                $current
            );
        }
        
        return array(
            'label' => __('PHP Version', 'reign-demo-install'),
            'required' => $required . '+',
            'current' => $current,
            'passed' => $passed
        );
    }
    
    /**
     * Check WordPress version
     */
    private function check_wp_version() {
        $required = '6.0';
        $current = get_bloginfo('version');
        $passed = version_compare($current, $required, '>=');
        
        if (!$passed) {
            $this->errors[] = sprintf(
                __('WordPress version %s or higher is required. You have %s.', 'reign-demo-install'),
                $required,
                $current
            );
        }
        
        return array(
            'label' => __('WordPress Version', 'reign-demo-install'),
            'required' => $required . '+',
            'current' => $current,
            'passed' => $passed
        );
    }
    
    /**
     * Check memory limit
     */
    private function check_memory_limit() {
        $required = 256; // MB
        $current = $this->get_memory_limit_in_mb();
        $passed = $current >= $required;
        
        if (!$passed) {
            $this->errors[] = sprintf(
                __('Memory limit of %dMB or higher is required. You have %dMB.', 'reign-demo-install'),
                $required,
                $current
            );
        } elseif ($current < 512) {
            $this->warnings[] = __('512MB memory limit is recommended for large demos.', 'reign-demo-install');
        }
        
        return array(
            'label' => __('Memory Limit', 'reign-demo-install'),
            'required' => $required . 'MB',
            'current' => $current . 'MB',
            'passed' => $passed
        );
    }
    
    /**
     * Check max execution time
     */
    private function check_execution_time() {
        $required = 300; // seconds
        $current = ini_get('max_execution_time');
        $passed = ($current == 0 || $current >= $required); // 0 means unlimited
        
        if (!$passed) {
            $this->warnings[] = sprintf(
                __('Max execution time of %d seconds is recommended. You have %d seconds.', 'reign-demo-install'),
                $required,
                $current
            );
        }
        
        return array(
            'label' => __('Max Execution Time', 'reign-demo-install'),
            'required' => $required . 's',
            'current' => $current == 0 ? 'Unlimited' : $current . 's',
            'passed' => true // Not a hard requirement
        );
    }
    
    /**
     * Check if Reign theme is active
     */
    private function check_reign_theme() {
        // Include the theme checker class if not already loaded
        if (!class_exists('Reign_Theme_Checker')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-theme-checker.php';
        }
        
        $is_reign = Reign_Theme_Checker::is_reign_theme_active();
        $theme_info = Reign_Theme_Checker::get_theme_info();
        
        if (!$is_reign) {
            $this->errors[] = Reign_Theme_Checker::get_inactive_message('demo_install');
        }
        
        return array(
            'label' => __('Reign Theme', 'reign-demo-install'),
            'required' => __('Active', 'reign-demo-install'),
            'current' => $is_reign ? __('Active', 'reign-demo-install') : $theme_info['name'],
            'passed' => $is_reign
        );
    }
    
    /**
     * Check available disk space
     */
    private function check_disk_space() {
        $required = 500; // MB
        $free_space = @disk_free_space(ABSPATH);
        
        if ($free_space !== false) {
            $free_space_mb = $free_space / 1024 / 1024;
            $passed = $free_space_mb >= $required;
            
            if (!$passed) {
                $this->errors[] = sprintf(
                    __('At least %dMB of free disk space is required. You have %dMB.', 'reign-demo-install'),
                    $required,
                    $free_space_mb
                );
            }
            
            return array(
                'label' => __('Disk Space', 'reign-demo-install'),
                'required' => $required . 'MB+',
                'current' => round($free_space_mb) . 'MB',
                'passed' => $passed
            );
        }
        
        return array(
            'label' => __('Disk Space', 'reign-demo-install'),
            'required' => $required . 'MB+',
            'current' => __('Unable to check', 'reign-demo-install'),
            'passed' => true // Assume it's ok if we can't check
        );
    }
    
    /**
     * Check if user is logged in
     */
    private function check_user_logged_in() {
        $logged_in = is_user_logged_in();
        
        if (!$logged_in) {
            $this->errors[] = __('You must be logged in to import demos.', 'reign-demo-install');
        }
        
        return array(
            'label' => __('User Status', 'reign-demo-install'),
            'required' => __('Logged In', 'reign-demo-install'),
            'current' => $logged_in ? __('Logged In', 'reign-demo-install') : __('Not Logged In', 'reign-demo-install'),
            'passed' => $logged_in
        );
    }
    
    /**
     * Check if user has admin capabilities
     */
    private function check_user_is_admin() {
        $is_admin = current_user_can('manage_options');
        
        if (!$is_admin) {
            $this->errors[] = __('You must have administrator privileges to import demos.', 'reign-demo-install');
        }
        
        return array(
            'label' => __('User Role', 'reign-demo-install'),
            'required' => __('Administrator', 'reign-demo-install'),
            'current' => $is_admin ? __('Administrator', 'reign-demo-install') : __('Insufficient Privileges', 'reign-demo-install'),
            'passed' => $is_admin
        );
    }
    
    /**
     * Check writable directories
     */
    private function check_writable_directories() {
        $directories = array(
            WP_CONTENT_DIR => 'wp-content',
            WP_CONTENT_DIR . '/uploads' => 'uploads',
            WP_CONTENT_DIR . '/themes' => 'themes',
            WP_CONTENT_DIR . '/plugins' => 'plugins'
        );
        
        $all_writable = true;
        $not_writable = array();
        
        foreach ($directories as $dir => $name) {
            if (!wp_is_writable($dir)) {
                $all_writable = false;
                $not_writable[] = $name;
            }
        }
        
        if (!$all_writable) {
            $this->errors[] = sprintf(
                __('The following directories must be writable: %s', 'reign-demo-install'),
                implode(', ', $not_writable)
            );
        }
        
        return array(
            'label' => __('Directory Permissions', 'reign-demo-install'),
            'required' => __('Writable', 'reign-demo-install'),
            'current' => $all_writable ? __('All Writable', 'reign-demo-install') : __('Some Not Writable', 'reign-demo-install'),
            'passed' => $all_writable
        );
    }
    
    /**
     * Check database access
     */
    private function check_database_access() {
        global $wpdb;
        
        // Try a simple query
        $test = $wpdb->get_var("SELECT 1");
        $has_access = ($test == 1);
        
        if (!$has_access) {
            $this->errors[] = __('Unable to access database.', 'reign-demo-install');
        }
        
        return array(
            'label' => __('Database Access', 'reign-demo-install'),
            'required' => __('Available', 'reign-demo-install'),
            'current' => $has_access ? __('Available', 'reign-demo-install') : __('Not Available', 'reign-demo-install'),
            'passed' => $has_access
        );
    }
    
    /**
     * Check PHP safe mode
     */
    private function check_safe_mode() {
        $safe_mode = ini_get('safe_mode');
        $is_off = !$safe_mode || $safe_mode == 'Off' || $safe_mode == '0';
        
        if (!$is_off) {
            $this->warnings[] = __('PHP safe mode is enabled. This may cause issues during import.', 'reign-demo-install');
        }
        
        return array(
            'label' => __('PHP Safe Mode', 'reign-demo-install'),
            'required' => __('Off', 'reign-demo-install'),
            'current' => $is_off ? __('Off', 'reign-demo-install') : __('On', 'reign-demo-install'),
            'passed' => true // Not a hard requirement
        );
    }
    
    /**
     * Check file uploads
     */
    private function check_file_uploads() {
        $enabled = ini_get('file_uploads');
        
        if (!$enabled) {
            $this->errors[] = __('File uploads must be enabled.', 'reign-demo-install');
        }
        
        return array(
            'label' => __('File Uploads', 'reign-demo-install'),
            'required' => __('Enabled', 'reign-demo-install'),
            'current' => $enabled ? __('Enabled', 'reign-demo-install') : __('Disabled', 'reign-demo-install'),
            'passed' => (bool)$enabled
        );
    }
    
    /**
     * Check CURL
     */
    private function check_curl() {
        $enabled = function_exists('curl_init');
        
        if (!$enabled) {
            $this->errors[] = __('CURL extension is required for downloading demo files.', 'reign-demo-install');
        }
        
        return array(
            'label' => __('CURL Extension', 'reign-demo-install'),
            'required' => __('Enabled', 'reign-demo-install'),
            'current' => $enabled ? __('Enabled', 'reign-demo-install') : __('Disabled', 'reign-demo-install'),
            'passed' => $enabled
        );
    }
    
    /**
     * Get memory limit in MB
     */
    private function get_memory_limit_in_mb() {
        $memory_limit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            if ($matches[2] == 'M') {
                return $matches[1];
            } else if ($matches[2] == 'K') {
                return $matches[1] / 1024;
            } else if ($matches[2] == 'G') {
                return $matches[1] * 1024;
            }
        }
        
        return $memory_limit;
    }
}