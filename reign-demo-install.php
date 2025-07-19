<?php
/**
 * Plugin Name: Reign Demo Install
 * Plugin URI: https://wbcomdesigns.com/downloads/reign-theme/
 * Description: One-click demo installer for Reign WordPress Theme with complete user session preservation
 * Version: 1.0.0
 * Author: WB Com Designs
 * Author URI: https://wbcomdesigns.com/
 * License: GPL v2 or later
 * Text Domain: reign-demo-install
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('REIGN_DEMO_INSTALL_VERSION', '1.0.0');
define('REIGN_DEMO_INSTALL_PATH', plugin_dir_path(__FILE__));
define('REIGN_DEMO_INSTALL_URL', plugin_dir_url(__FILE__));
define('REIGN_DEMO_INSTALL_FILE', __FILE__);

// Demo hub URL
define('REIGN_DEMO_HUB_URL', 'https://installer.wbcomdesigns.com/reign-demos/');

// Main plugin class
class Reign_Demo_Install {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize hooks
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // AJAX handlers
        add_action('wp_ajax_reign_demo_check_requirements', array($this, 'ajax_check_requirements'));
        add_action('wp_ajax_reign_demo_preserve_user', array($this, 'ajax_preserve_user'));
        add_action('wp_ajax_reign_demo_import_step', array($this, 'ajax_import_step'));
        add_action('wp_ajax_reign_demo_check_session', array($this, 'ajax_check_session'));
        add_action('wp_ajax_reign_demo_restore_session', array($this, 'ajax_restore_session'));
        add_action('wp_ajax_reign_demo_get_demo_list', array($this, 'ajax_get_demo_list'));
        add_action('wp_ajax_reign_demo_download_demo', array($this, 'ajax_download_demo'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add session monitoring
        add_action('admin_init', array($this, 'monitor_import_session'));
    }
    
    private function load_dependencies() {
        // Core classes
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-requirements-checker.php';
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-user-preserver.php';
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-demo-browser.php';
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-plugin-installer.php';
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-content-importer.php';
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-file-importer.php';
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-settings-importer.php';
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-rollback-manager.php';
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-ajax-handler.php';
        require_once REIGN_DEMO_INSTALL_PATH . 'includes/class-session-manager.php';
        
        // Admin interface
        if (is_admin()) {
            require_once REIGN_DEMO_INSTALL_PATH . 'admin/class-admin.php';
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('reign-demo-install', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function add_admin_menu() {
        add_theme_page(
            __('Reign Demo Install', 'reign-demo-install'),
            __('Install Demos', 'reign-demo-install'),
            'manage_options',
            'reign-demo-install',
            array($this, 'render_admin_page')
        );
        
        // Also add to main menu for visibility
        add_menu_page(
            __('Reign Demos', 'reign-demo-install'),
            __('Reign Demos', 'reign-demo-install'),
            'manage_options',
            'reign-demos',
            array($this, 'render_admin_page'),
            'dashicons-layout',
            3
        );
    }
    
    public function render_admin_page() {
        $admin = new Reign_Demo_Install_Admin();
        $admin->render();
    }
    
    public function enqueue_admin_assets($hook) {
        // Load on our pages
        if (!in_array($hook, array('appearance_page_reign-demo-install', 'toplevel_page_reign-demos'))) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'reign-demo-install-admin',
            REIGN_DEMO_INSTALL_URL . 'admin/css/admin.css',
            array(),
            REIGN_DEMO_INSTALL_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'reign-demo-install-admin',
            REIGN_DEMO_INSTALL_URL . 'admin/js/admin.js',
            array('jquery', 'wp-util'),
            REIGN_DEMO_INSTALL_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('reign-demo-install-admin', 'reign_demo_install', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reign_demo_install_nonce'),
            'current_user' => array(
                'id' => get_current_user_id(),
                'login' => wp_get_current_user()->user_login,
                'email' => wp_get_current_user()->user_email,
                'display_name' => wp_get_current_user()->display_name
            ),
            'messages' => array(
                'confirm_import' => __('This will import demo content and replace your existing content. Your admin account will be preserved. Continue?', 'reign-demo-install'),
                'import_in_progress' => __('Import in progress. Please do not close this window or logout.', 'reign-demo-install'),
                'session_check' => __('Checking session...', 'reign-demo-install'),
                'import_complete' => __('Demo import completed successfully!', 'reign-demo-install'),
                'import_failed' => __('Import failed. Please check the error log.', 'reign-demo-install'),
                'session_lost' => __('Session lost. Attempting to restore...', 'reign-demo-install'),
                'license_required' => __('License key required for premium plugin', 'reign-demo-install')
            ),
            'hub_url' => REIGN_DEMO_HUB_URL
        ));
        
        // Add inline style for demo browser
        wp_add_inline_style('reign-demo-install-admin', $this->get_inline_styles());
    }
    
    private function get_inline_styles() {
        return '
            .reign-demo-browser { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
            .reign-demo-card { border: 1px solid #ddd; border-radius: 4px; overflow: hidden; transition: box-shadow 0.3s; }
            .reign-demo-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
            .reign-demo-preview { position: relative; padding-bottom: 56.25%; background: #f0f0f0; }
            .reign-demo-preview img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
            .reign-demo-info { padding: 15px; }
            .reign-demo-import-progress { display: none; margin-top: 20px; }
            .reign-demo-progress-bar { width: 100%; height: 30px; background: #f0f0f0; border-radius: 15px; overflow: hidden; }
            .reign-demo-progress-fill { height: 100%; background: #0073aa; transition: width 0.3s; }
            .reign-demo-user-notice { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px 15px; margin: 20px 0; border-radius: 4px; }
        ';
    }
    
    public function admin_notices() {
        // Check if Reign theme is active
        $theme = wp_get_theme();
        if ($theme->get('Name') !== 'Reign' && $theme->get('Template') !== 'reign') {
            ?>
            <div class="notice notice-warning">
                <p><?php _e('Reign Demo Install requires the Reign theme to be active. Please activate Reign theme first.', 'reign-demo-install'); ?></p>
            </div>
            <?php
        }
        
        // Check if import is in progress
        if (get_transient('reign_demo_import_active_' . get_current_user_id())) {
            ?>
            <div class="notice notice-info">
                <p><?php _e('Demo import is in progress. Please do not close your browser or logout.', 'reign-demo-install'); ?></p>
            </div>
            <?php
        }
    }
    
    public function monitor_import_session() {
        // Check if import is active
        if (get_transient('reign_demo_import_active_' . get_current_user_id())) {
            // Refresh auth cookie to maintain session
            $session_manager = new Reign_Demo_Session_Manager();
            $session_manager->refresh_user_session();
        }
    }
    
    // AJAX Handlers
    public function ajax_check_requirements() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->check_requirements();
    }
    
    public function ajax_preserve_user() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->preserve_user();
    }
    
    public function ajax_import_step() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->process_import_step();
    }
    
    public function ajax_check_session() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->check_session();
    }
    
    public function ajax_restore_session() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->restore_session();
    }
    
    public function ajax_get_demo_list() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->get_demo_list();
    }
    
    public function ajax_download_demo() {
        $ajax_handler = new Reign_Demo_Ajax_Handler();
        $ajax_handler->download_demo();
    }
    
    // Activation
    public function activate() {
        // Create temp directory for imports
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Set default options
        add_option('reign_demo_install_settings', array(
            'preserve_admin' => true,
            'clean_install' => true,
            'import_media' => true,
            'import_users' => true,
            'import_settings' => true
        ));
        
        // Clear any stuck import locks
        delete_option('reign_demo_import_lock');
    }
    
    // Deactivation
    public function deactivate() {
        // Clean up temp files
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        if (is_dir($temp_dir)) {
            $this->cleanup_directory($temp_dir);
        }
        
        // Clear transients
        delete_transient('reign_demo_import_active_' . get_current_user_id());
    }
    
    private function cleanup_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        
        rmdir($dir);
    }
}

// Initialize plugin
Reign_Demo_Install::get_instance();