<?php
/**
 * Reign Demo Install CLI Command
 * 
 * Provides WP-CLI command for importing demos
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reign Demo Install CLI Commands
 */
class Reign_Demo_Install_CLI_Command {
    
    /**
     * Import a demo via CLI
     * 
     * ## OPTIONS
     * 
     * [<demo>]
     * : The demo ID to import. Default: demo1
     * 
     * [--clean]
     * : Clean existing content before import
     * 
     * [--skip-plugins]
     * : Skip plugin installation check
     * 
     * [--skip-media]
     * : Skip media import
     * 
     * [--skip-settings]
     * : Skip settings import
     * 
     * ## EXAMPLES
     * 
     *     # Import demo1 (default)
     *     $ wp reign-demo import
     *     
     *     # Import specific demo with clean install
     *     $ wp reign-demo import demo1 --clean
     * 
     * @when after_wp_load
     */
    public function import($args, $assoc_args) {
        // Get demo ID
        $demo_id = isset($args[0]) ? $args[0] : 'demo1';
        
        WP_CLI::log("Starting import of $demo_id...");
        
        // Check if user is logged in (set admin user)
        $admin_user = get_users(array(
            'role' => 'administrator',
            'number' => 1
        ));
        
        if (empty($admin_user)) {
            WP_CLI::error('No administrator user found.');
        }
        
        wp_set_current_user($admin_user[0]->ID);
        WP_CLI::log("Running as user: " . $admin_user[0]->user_login);
        
        // Use direct import method
        $this->direct_import($demo_id, $admin_user[0], $assoc_args);
        return;
        
        // Load required files
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ajax-handler.php';
        
        // Initialize filesystem
        WP_Filesystem();
        
        // Create AJAX handler
        $ajax_handler = new Reign_Demo_Install_Ajax_Handler();
        
        // Set up nonce
        $nonce = wp_create_nonce('reign_demo_install_nonce');
        
        // Import options
        $options = array(
            'clean_existing' => isset($assoc_args['clean']),
            'import_users' => true,
            'import_media' => !isset($assoc_args['skip-media']),
            'import_content' => true,
            'import_settings' => !isset($assoc_args['skip-settings']),
            'backup_database' => false
        );
        
        // LEGACY CODE - NOT USED
        return;
        
        // Clean temp directory
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        if (is_dir($temp_dir)) {
            WP_CLI::log("Cleaning temp directory...");
            $this->delete_directory($temp_dir);
        }
        
        // Process each step
        $steps = array(
            'preserve_user' => 'Preserving admin user',
            'download' => 'Downloading demo files',
            'plugins' => 'Checking plugins',
            'content' => 'Importing database content',
            'files' => 'Importing media files',
            'settings' => 'Importing theme settings',
            'cleanup' => 'Cleaning up'
        );
        
        global $wpdb;
        $initial_tables = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
        
        foreach ($steps as $step => $description) {
            WP_CLI::log("\n$description...");
            
            // Skip plugin check if requested
            if ($step === 'plugins' && isset($assoc_args['skip-plugins'])) {
                WP_CLI::log("Skipping plugin check.");
                continue;
            }
            
            // Process step
            $result = $this->process_step($ajax_handler, $step, $demo_id, $nonce, $options);
            
            if ($result['success']) {
                WP_CLI::success($result['message']);
                
                // Show import results for content step
                if ($step === 'content' && isset($result['results'])) {
                    $r = $result['results'];
                    WP_CLI::log("  - Tables imported: " . $r['imported']);
                    WP_CLI::log("  - Tables skipped: " . $r['skipped']);
                    if (!empty($r['errors'])) {
                        foreach ($r['errors'] as $error) {
                            WP_CLI::warning("  - Error: " . $error);
                        }
                    }
                }
            } else {
                if ($step === 'files' || $step === 'settings') {
                    // Non-critical steps
                    WP_CLI::warning($result['message']);
                } else {
                    WP_CLI::error($result['message']);
                }
            }
            
            // Progress bar for longer steps
            if ($step === 'content' || $step === 'files') {
                $this->show_progress();
            }
        }
        
        // Final verification
        WP_CLI::log("\nVerifying import...");
        
        // Check if still logged in
        if (is_user_logged_in()) {
            WP_CLI::success("User session maintained.");
        } else {
            WP_CLI::warning("User session lost during import.");
        }
        
        // Database statistics
        $final_tables = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
        $new_tables = $final_tables - $initial_tables;
        
        WP_CLI::log("\nDatabase statistics:");
        WP_CLI::log("  - Total tables: $final_tables (+" . $new_tables . " new)");
        
        // Content statistics
        $content_stats = $wpdb->get_results("
            SELECT post_type, COUNT(*) as count 
            FROM {$wpdb->posts} 
            WHERE post_status = 'publish' 
            GROUP BY post_type 
            ORDER BY count DESC
            LIMIT 10
        ");
        
        WP_CLI::log("\nContent imported:");
        foreach ($content_stats as $stat) {
            WP_CLI::log("  - " . ucfirst($stat->post_type) . ": " . $stat->count);
        }
        
        // Clear cache
        wp_cache_flush();
        flush_rewrite_rules();
        
        WP_CLI::success("\nImport completed successfully!");
        WP_CLI::log("\nAccess your site:");
        WP_CLI::log("  Frontend: " . home_url());
        WP_CLI::log("  Admin: " . admin_url());
    }
    
    /**
     * List available demos
     * 
     * ## EXAMPLES
     * 
     *     $ wp reign-demo list
     * 
     * @when after_wp_load
     */
    public function list($args, $assoc_args) {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-demo-browser.php';
        
        $browser = new Reign_Demo_Browser();
        $demos = $browser->get_available_demos();
        
        if (empty($demos)) {
            WP_CLI::warning('No demos available.');
            return;
        }
        
        $items = array();
        foreach ($demos as $demo) {
            $items[] = array(
                'ID' => $demo['id'],
                'Name' => $demo['name'],
                'Category' => $demo['category'],
                'Description' => substr($demo['description'], 0, 50) . '...'
            );
        }
        
        WP_CLI\Utils\format_items('table', $items, array('ID', 'Name', 'Category', 'Description'));
    }
    
    /**
     * Reset database
     * 
     * ## OPTIONS
     * 
     * [--yes]
     * : Skip confirmation
     * 
     * ## EXAMPLES
     * 
     *     $ wp reign-demo reset --yes
     * 
     * @when after_wp_load
     */
    public function reset($args, $assoc_args) {
        WP_CLI::confirm('Are you sure you want to reset the database?', $assoc_args);
        
        WP_CLI::runcommand('db reset --yes');
        WP_CLI::success('Database reset.');
        
        // Reinstall WordPress
        $admin_email = get_option('admin_email', 'admin@example.com');
        $site_title = get_option('blogname', 'Demo Site');
        
        WP_CLI::runcommand(sprintf(
            'core install --url=%s --title="%s" --admin_user=admin --admin_password=admin123 --admin_email=%s --skip-email',
            home_url(),
            $site_title,
            $admin_email
        ));
        
        WP_CLI::success('WordPress reinstalled.');
    }
    
    /**
     * Process an import step
     */
    private function process_step($ajax_handler, $step, $demo_id, $nonce, $options) {
        $_POST = array(
            'action' => $step === 'preserve_user' ? 'reign_demo_preserve_user' : 'reign_demo_import_step',
            'demo_id' => $demo_id,
            'step' => $step,
            'nonce' => $nonce,
            'options' => $options
        );
        $_REQUEST = $_POST;
        
        // Override wp_send_json functions to capture output instead of exiting
        add_filter('wp_die_ajax_handler', array($this, 'ajax_die_handler'), 1);
        add_filter('wp_die_json_handler', array($this, 'ajax_die_handler'), 1);
        
        $result = null;
        
        // Capture JSON responses
        add_action('wp_send_json', function($response) use (&$result) {
            $result = $response;
            throw new Exception('json_sent');
        }, 1);
        
        add_action('wp_send_json_success', function($data) use (&$result) {
            $result = array('success' => true, 'data' => $data);
            throw new Exception('json_sent');
        }, 1);
        
        add_action('wp_send_json_error', function($data) use (&$result) {
            $result = array('success' => false, 'data' => $data);
            throw new Exception('json_sent');
        }, 1);
        
        ob_start();
        
        try {
            if ($step === 'preserve_user') {
                $ajax_handler->preserve_user();
            } else {
                $ajax_handler->process_import_step();
            }
        } catch (Exception $e) {
            ob_end_clean();
            
            if ($e->getMessage() === 'json_sent' && $result) {
                return array(
                    'success' => $result['success'],
                    'message' => $result['data']['message'] ?? 'Unknown response',
                    'results' => $result['data']['results'] ?? null
                );
            }
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
        
        $output = ob_get_clean();
        
        // Extract JSON from output
        if (preg_match('/{.*}/s', $output, $matches)) {
            $json_result = json_decode($matches[0], true);
            if ($json_result) {
                return array(
                    'success' => $json_result['success'],
                    'message' => $json_result['data']['message'] ?? 'Unknown response',
                    'results' => $json_result['data']['results'] ?? null
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => 'Invalid response from handler'
        );
    }
    
    /**
     * Custom die handler for AJAX requests
     */
    public function ajax_die_handler($handler) {
        return function($message, $title = '', $args = array()) {
            throw new Exception('wp_die_called');
        };
    }
    
    /**
     * Show progress indicator
     */
    private function show_progress() {
        for ($i = 0; $i < 3; $i++) {
            sleep(1);
            echo '.';
        }
        echo "\n";
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
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
    
    /**
     * Verify import was successful
     */
    private function verify_import($admin_user) {
        global $wpdb;
        $all_tests_passed = true;
        $test_results = array();
        
        // Test 1: Admin User Verification
        WP_CLI::log("\n1. ADMIN USER VERIFICATION");
        WP_CLI::log(str_repeat('-', 30));
        
        // Check if admin user exists and has correct role
        $user = get_user_by('login', $admin_user->user_login);
        if ($user) {
            $test_results['admin_exists'] = true;
            WP_CLI::success("Admin user exists: " . $user->user_login);
            
            // Check admin capabilities
            if ($user->has_cap('administrator')) {
                $test_results['admin_role'] = true;
                WP_CLI::success("Admin has administrator role");
            } else {
                $test_results['admin_role'] = false;
                WP_CLI::error("Admin does NOT have administrator role!");
                $all_tests_passed = false;
            }
            
            // Check if user can access admin
            if (user_can($user, 'manage_options')) {
                $test_results['admin_caps'] = true;
                WP_CLI::success("Admin can manage options");
            } else {
                $test_results['admin_caps'] = false;
                WP_CLI::error("Admin cannot manage options!");
                $all_tests_passed = false;
            }
        } else {
            $test_results['admin_exists'] = false;
            WP_CLI::error("Admin user NOT found!");
            $all_tests_passed = false;
        }
        
        // Test 2: Theme Verification
        WP_CLI::log("\n2. THEME VERIFICATION");
        WP_CLI::log(str_repeat('-', 30));
        
        $current_theme = wp_get_theme();
        $theme_name = $current_theme->get('Name');
        $stylesheet = get_option('stylesheet');
        $template = get_option('template');
        
        if (!empty($theme_name) && stripos($theme_name, 'reign') !== false) {
            $test_results['theme_active'] = true;
            WP_CLI::success("Reign theme is active: " . $theme_name);
        } else if (stripos($stylesheet, 'reign') !== false || stripos($template, 'reign') !== false) {
            $test_results['theme_active'] = true;
            WP_CLI::success("Reign theme is active (stylesheet: $stylesheet)");
        } else {
            $test_results['theme_active'] = false;
            WP_CLI::error("Reign theme is NOT active! Current: " . ($theme_name ?: 'Unknown') . " (stylesheet: $stylesheet, template: $template)");
            $all_tests_passed = false;
        }
        
        // Test 3: Plugin Verification
        WP_CLI::log("\n3. PLUGIN VERIFICATION");
        WP_CLI::log(str_repeat('-', 30));
        
        $required_plugins = array(
            'buddyboss-platform/bp-loader.php' => 'BuddyBoss Platform',
            'elementor/elementor.php' => 'Elementor',
            'woocommerce/woocommerce.php' => 'WooCommerce'
        );
        
        foreach ($required_plugins as $plugin_path => $plugin_name) {
            if (is_plugin_active($plugin_path)) {
                $test_results['plugin_' . $plugin_path] = true;
                WP_CLI::success("$plugin_name is active");
            } else {
                $test_results['plugin_' . $plugin_path] = false;
                WP_CLI::warning("$plugin_name is NOT active");
            }
        }
        
        // Test 4: Content Verification
        WP_CLI::log("\n4. CONTENT VERIFICATION");
        WP_CLI::log(str_repeat('-', 30));
        
        // Check posts
        $post_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'");
        if ($post_count > 0) {
            $test_results['posts_imported'] = true;
            WP_CLI::success("Posts imported: $post_count");
        } else {
            $test_results['posts_imported'] = false;
            WP_CLI::warning("No posts found!");
        }
        
        // Check pages
        $page_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'");
        if ($page_count > 0) {
            $test_results['pages_imported'] = true;
            WP_CLI::success("Pages imported: $page_count");
        } else {
            $test_results['pages_imported'] = false;
            WP_CLI::warning("No pages found!");
        }
        
        // Check users
        $user_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        if ($user_count > 1) {
            $test_results['users_imported'] = true;
            WP_CLI::success("Total users: $user_count");
        } else {
            $test_results['users_imported'] = false;
            WP_CLI::warning("Only 1 user found (no additional users imported)");
        }
        
        // Check if BuddyBoss content exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}bp_groups'")) {
            $group_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bp_groups");
            if ($group_count > 0) {
                $test_results['bp_groups'] = true;
                WP_CLI::success("BuddyBoss groups imported: $group_count");
            } else {
                $test_results['bp_groups'] = false;
                WP_CLI::warning("No BuddyBoss groups found");
            }
        }
        
        // Test 5: Database Options Verification
        WP_CLI::log("\n5. DATABASE OPTIONS VERIFICATION");
        WP_CLI::log(str_repeat('-', 30));
        
        $home_url = get_option('home');
        $site_url = get_option('siteurl');
        
        if ($home_url && $site_url) {
            $test_results['urls_set'] = true;
            WP_CLI::success("Site URLs are set:");
            WP_CLI::log("  - Home URL: $home_url");
            WP_CLI::log("  - Site URL: $site_url");
        } else {
            $test_results['urls_set'] = false;
            WP_CLI::error("Site URLs are NOT properly set!");
            $all_tests_passed = false;
        }
        
        // Test 6: Menu Verification
        WP_CLI::log("\n6. MENU VERIFICATION");
        WP_CLI::log(str_repeat('-', 30));
        
        $menus = wp_get_nav_menus();
        if (count($menus) > 0) {
            $test_results['menus_imported'] = true;
            WP_CLI::success("Navigation menus imported: " . count($menus));
            foreach ($menus as $menu) {
                $items = wp_get_nav_menu_items($menu->term_id);
                WP_CLI::log("  - " . $menu->name . " (" . count($items) . " items)");
            }
        } else {
            $test_results['menus_imported'] = false;
            WP_CLI::warning("No navigation menus found");
        }
        
        // Test 7: Homepage Verification
        WP_CLI::log("\n7. HOMEPAGE VERIFICATION");
        WP_CLI::log(str_repeat('-', 30));
        
        $front_page = get_option('page_on_front');
        $show_on_front = get_option('show_on_front');
        
        if ($show_on_front === 'page' && $front_page) {
            $homepage = get_post($front_page);
            if ($homepage) {
                $test_results['homepage_set'] = true;
                WP_CLI::success("Homepage is set to: " . $homepage->post_title);
            } else {
                $test_results['homepage_set'] = false;
                WP_CLI::warning("Homepage page not found (ID: $front_page)");
            }
        } else {
            $test_results['homepage_set'] = false;
            WP_CLI::warning("Homepage not set to a static page");
        }
        
        // Final Summary
        WP_CLI::log("\n" . str_repeat('=', 50));
        WP_CLI::log("VERIFICATION SUMMARY");
        WP_CLI::log(str_repeat('=', 50));
        
        $passed_tests = array_filter($test_results, function($result) { return $result === true; });
        $failed_tests = array_filter($test_results, function($result) { return $result === false; });
        
        WP_CLI::log("\nTotal tests: " . count($test_results));
        WP_CLI::success("Passed: " . count($passed_tests));
        if (count($failed_tests) > 0) {
            WP_CLI::error("Failed: " . count($failed_tests));
        }
        
        if ($all_tests_passed) {
            WP_CLI::success("\n✓ ALL CRITICAL TESTS PASSED - Import verified successfully!");
        } else {
            WP_CLI::error("\n✗ Some critical tests failed - Please check the errors above!");
        }
        
        return $all_tests_passed;
    }
    
    /**
     * Direct import method that doesn't use AJAX handlers
     */
    private function direct_import($demo_id, $admin_user, $args) {
        global $wpdb;
        
        // Get initial table count
        $initial_tables = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
        
        // Load required files
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once __DIR__ . '/class-demo-browser.php';
        require_once __DIR__ . '/class-user-preserver.php';
        require_once __DIR__ . '/class-content-importer.php';
        require_once __DIR__ . '/class-file-importer.php';
        require_once __DIR__ . '/class-settings-importer.php';
        
        WP_Filesystem();
        
        // Step 0: Activate Reign theme FIRST (before anything else)
        WP_CLI::log("\nChecking theme...");
        $theme = wp_get_theme();
        if (strpos(strtolower($theme->get('Name')), 'reign') === false) {
            WP_CLI::log("Activating Reign theme...");
            $theme_result = switch_theme('reign-theme');
            if (is_wp_error($theme_result)) {
                // Try alternate theme names
                $themes = wp_get_themes();
                $reign_theme = null;
                foreach ($themes as $theme_slug => $theme_obj) {
                    if (stripos($theme_obj->get('Name'), 'reign') !== false) {
                        $reign_theme = $theme_slug;
                        break;
                    }
                }
                if ($reign_theme) {
                    switch_theme($reign_theme);
                    WP_CLI::success("Activated theme: " . $reign_theme);
                } else {
                    WP_CLI::error("Reign theme not found. Please install it first.");
                }
            } else {
                WP_CLI::success("Reign theme activated.");
            }
        } else {
            WP_CLI::log("Reign theme already active.");
        }
        
        // Step 1: Preserve admin user
        WP_CLI::log("\nPreserving admin user...");
        $user_preserver = new Reign_Demo_User_Preserver();
        $preserved_data = $user_preserver->preserve_current_admin();
        
        if (!$preserved_data) {
            WP_CLI::error('Failed to preserve admin user');
        }
        WP_CLI::success('Admin user preserved: ' . $admin_user->user_login);
        
        // Step 2: Download demo files
        WP_CLI::log("\nDownloading demo files...");
        $demo_browser = new Reign_Demo_Browser();
        $demo = $demo_browser->get_demo_by_id($demo_id);
        
        if (!$demo) {
            WP_CLI::error('Demo not found: ' . $demo_id);
        }
        
        // Create temp directory
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $demo_dir = $temp_dir . $demo_id . '/';
        
        if (is_dir($demo_dir)) {
            $this->delete_directory($demo_dir);
        }
        wp_mkdir_p($demo_dir);
        
        // Download files
        $files_to_download = array(
            'manifest.json' => $demo['manifest_url'],
            'plugins-manifest.json' => $demo['plugins_manifest_url'],
            'files-manifest.json' => $demo['files_manifest_url'],
            'content-package.zip' => $demo['package_url']
        );
        
        foreach ($files_to_download as $filename => $url) {
            if (empty($url)) continue;
            
            WP_CLI::log("  Downloading $filename...");
            $response = wp_remote_get($url, array(
                'timeout' => 300,
                'stream' => true,
                'filename' => $demo_dir . $filename
            ));
            
            if (is_wp_error($response)) {
                WP_CLI::error('Failed to download ' . $filename . ': ' . $response->get_error_message());
            }
        }
        
        // Extract package
        if (file_exists($demo_dir . 'content-package.zip')) {
            WP_CLI::log("  Extracting package...");
            $extract_dir = $demo_dir . 'extracted/';
            $unzip_result = unzip_file($demo_dir . 'content-package.zip', $extract_dir);
            
            if (is_wp_error($unzip_result)) {
                WP_CLI::error('Failed to extract package: ' . $unzip_result->get_error_message());
            }
        }
        
        WP_CLI::success('Demo files downloaded');
        
        // Step 3: Install and activate required plugins BEFORE SQL import
        if (!isset($args['skip-plugins'])) {
            WP_CLI::log("\nChecking and activating required plugins...");
            
            // Read plugins manifest to get required plugins
            $plugins_manifest_file = $demo_dir . 'plugins-manifest.json';
            $required_plugins = array();
            
            if (file_exists($plugins_manifest_file)) {
                $manifest_content = file_get_contents($plugins_manifest_file);
                $plugins_manifest = json_decode($manifest_content, true);
                
                if ($plugins_manifest && isset($plugins_manifest['required'])) {
                    foreach ($plugins_manifest['required'] as $plugin) {
                        if (isset($plugin['slug']) && isset($plugin['name'])) {
                            $required_plugins[$plugin['slug']] = $plugin['name'];
                        }
                    }
                }
            }
            
            // Fallback to default plugins if manifest not found
            if (empty($required_plugins)) {
                $required_plugins = array(
                    'buddyboss-platform' => 'BuddyBoss Platform',
                    'elementor' => 'Elementor',
                    'woocommerce' => 'WooCommerce',
                    'wbcom-essential' => 'Wbcom Essential',
                    'reign-buddypress-group-tags' => 'Reign BuddyPress Group Tags',
                    'reign-learndash-addon' => 'Reign LearnDash',
                    'paid-memberships-pro' => 'Paid Memberships Pro'
                );
            }
            
            // IMPORTANT: Skip BuddyPress if BuddyBoss Platform is in the list
            if (isset($required_plugins['buddyboss-platform'])) {
                unset($required_plugins['buddypress']);
            }
            
            foreach ($required_plugins as $plugin_slug => $plugin_name) {
                $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_slug . '.php';
                
                // Check alternate filenames
                if (!file_exists($plugin_file)) {
                    // Try bp-loader.php for BuddyBoss Platform
                    if ($plugin_slug === 'buddyboss-platform') {
                        $plugin_file = WP_PLUGIN_DIR . '/buddyboss-platform/bp-loader.php';
                    } else {
                        // Try other common patterns
                        $alternatives = glob(WP_PLUGIN_DIR . '/' . $plugin_slug . '/*.php');
                        foreach ($alternatives as $alt_file) {
                            $data = get_file_data($alt_file, array('Plugin Name' => 'Plugin Name'));
                            if (!empty($data['Plugin Name'])) {
                                $plugin_file = $alt_file;
                                break;
                            }
                        }
                    }
                }
                
                if (file_exists($plugin_file)) {
                    $plugin_path = str_replace(WP_PLUGIN_DIR . '/', '', $plugin_file);
                    if (!is_plugin_active($plugin_path)) {
                        WP_CLI::log("  Activating $plugin_name...");
                        $result = activate_plugin($plugin_path);
                        if (is_wp_error($result)) {
                            WP_CLI::warning("Failed to activate $plugin_name: " . $result->get_error_message());
                        } else {
                            WP_CLI::success("Activated $plugin_name");
                        }
                    } else {
                        WP_CLI::log("  $plugin_name already active");
                    }
                } else {
                    WP_CLI::warning("$plugin_name not found - please install it manually");
                }
            }
            
            // Give plugins time to initialize their database tables
            WP_CLI::log("  Waiting for plugins to initialize...");
            sleep(2);
            
            // CRITICAL: Enable all BuddyBoss/BuddyPress components
            WP_CLI::log("\nEnabling BuddyBoss/BuddyPress components...");
            $component_enabler = new Reign_Demo_Component_Enabler();
            $component_results = $component_enabler->enable_all_components();
            
            if ($component_results['platform'] === 'none') {
                WP_CLI::warning("Neither BuddyBoss nor BuddyPress is active - component data may be lost!");
            } else {
                WP_CLI::success("Platform detected: " . $component_results['platform']);
                if (!empty($component_results['enabled'])) {
                    WP_CLI::success("Enabled components: " . implode(', ', $component_results['enabled']));
                } else {
                    WP_CLI::log("All components were already enabled");
                }
                
                // Get status report
                $status = $component_enabler->get_component_status();
                WP_CLI::log("Active components: " . implode(', ', $status['components']));
                WP_CLI::log("Database tables: " . count($status['tables']) . " BuddyBoss/BuddyPress tables found");
            }
            
            // Give components time to initialize
            WP_CLI::log("  Waiting for components to initialize...");
            sleep(2);
        }
        
        // Step 4: Import database content (AFTER plugins are active AND components enabled)
        WP_CLI::log("\nImporting database content...");
        
        // Use the AJAX handler's SQL import method
        require_once __DIR__ . '/class-ajax-handler.php';
        $ajax_handler = new Reign_Demo_Install_Ajax_Handler();
        
        // Call the private method using reflection
        $reflection = new ReflectionClass($ajax_handler);
        $method = $reflection->getMethod('import_sql_content');
        $method->setAccessible(true);
        
        $result = $method->invoke($ajax_handler, $demo_id, array(
            'preserve_user_id' => $admin_user->ID,
            'import_users' => true,
            'clean_existing' => isset($args['clean'])
        ));
        
        if (is_wp_error($result)) {
            WP_CLI::error('Database import failed: ' . $result->get_error_message());
        }
        
        WP_CLI::success("Database imported - Tables: " . $result['imported'] . " imported, " . $result['skipped'] . " skipped");
        
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                WP_CLI::warning("Import error: " . $error);
            }
        }
        
        // CRITICAL: Always ensure site URLs are set after import
        WP_CLI::log("\nEnsuring site URLs are set...");
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
            'siteurl',
            'http://buddyx.local',
            'yes'
        ));
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
            'home',
            'http://buddyx.local',
            'yes'
        ));
        
        // CRITICAL: Check if theme is still active after import
        $current_theme = wp_get_theme();
        $stylesheet = get_option('stylesheet');
        $template = get_option('template');
        
        WP_CLI::log("\nChecking theme status after import...");
        WP_CLI::log("  Current stylesheet: " . $stylesheet);
        WP_CLI::log("  Current template: " . $template);
        
        // If theme was deactivated or is not a Reign theme, reactivate it
        if (empty($stylesheet) || empty($template) || 
            (stripos($stylesheet, 'reign') === false && stripos($template, 'reign') === false)) {
            
            WP_CLI::log("  Theme needs to be activated!");
            
            // Find and activate Reign theme
            $themes = wp_get_themes();
            $reign_theme = null;
            foreach ($themes as $theme_slug => $theme_obj) {
                if (stripos($theme_obj->get('Name'), 'reign') !== false) {
                    $reign_theme = $theme_slug;
                    break;
                }
            }
            
            if ($reign_theme) {
                switch_theme($reign_theme);
                WP_CLI::success("Reactivated theme: " . $reign_theme);
            } else {
                WP_CLI::warning("Could not find Reign theme to activate!");
            }
        } else {
            WP_CLI::log("  Theme is properly set.");
        }
        
        // CRITICAL: Ensure homepage and menu locations are set
        WP_CLI::log("\nChecking homepage and menu settings...");
        
        // Check if homepage is set
        $show_on_front = get_option('show_on_front');
        $page_on_front = get_option('page_on_front');
        
        if ($show_on_front !== 'page' || empty($page_on_front)) {
            // Find the home page
            $home_page = get_page_by_title('Home');
            if (!$home_page) {
                $home_page = get_page_by_path('home');
            }
            
            if ($home_page) {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $home_page->ID);
                WP_CLI::success("Set homepage to: " . $home_page->post_title);
            } else {
                WP_CLI::warning("Could not find Home page to set as front page");
            }
        }
        
        // Check menu locations
        $locations = get_nav_menu_locations();
        if (empty($locations)) {
            WP_CLI::log("  No menu locations set, attempting to assign...");
            
            // Get imported menus
            $menus = wp_get_nav_menus();
            $menu_map = array();
            foreach ($menus as $menu) {
                $menu_map[strtolower($menu->name)] = $menu->term_id;
            }
            
            // Try to intelligently assign menus
            if (isset($menu_map['menu'])) {
                set_theme_mod('nav_menu_locations', array_merge(
                    (array) get_theme_mod('nav_menu_locations', array()),
                    array(
                        'menu-1' => $menu_map['menu'],
                        'menu-1-logout' => $menu_map['menu']
                    )
                ));
            }
            
            if (isset($menu_map['profile menu'])) {
                set_theme_mod('nav_menu_locations', array_merge(
                    (array) get_theme_mod('nav_menu_locations', array()),
                    array('menu-2' => $menu_map['profile menu'])
                ));
            }
            
            if (isset($menu_map['user menu'])) {
                set_theme_mod('nav_menu_locations', array_merge(
                    (array) get_theme_mod('nav_menu_locations', array()),
                    array('panel-menu' => $menu_map['user menu'])
                ));
            }
            
            $new_locations = get_nav_menu_locations();
            if (!empty($new_locations)) {
                WP_CLI::success("Menu locations assigned: " . count($new_locations));
            }
        }
        
        // Restore admin user capabilities in case prefix changed
        $user = new WP_User($admin_user->ID);
        if (!$user->has_cap('administrator')) {
            $user->set_role('administrator');
        }
        
        // Step 5: Import media files (skip if requested)
        if (!isset($args['skip-media'])) {
            WP_CLI::log("\nImporting media files...");
            $file_importer = new Reign_Demo_File_Importer();
            
            $uploads_dir = $demo_dir . 'extracted/uploads/';
            if (is_dir($uploads_dir)) {
                $result = $file_importer->import_files($uploads_dir);
                if ($result) {
                    WP_CLI::success('Media files imported');
                } else {
                    WP_CLI::warning('Some media files failed to import');
                }
            } else {
                WP_CLI::log('  No media files to import');
            }
        }
        
        // Step 6: Import theme settings (skip if requested)
        if (!isset($args['skip-settings'])) {
            WP_CLI::log("\nImporting theme settings...");
            $settings_importer = new Reign_Demo_Settings_Importer();
            
            $settings_file = $demo_dir . 'extracted/settings.json';
            if (file_exists($settings_file)) {
                $result = $settings_importer->import_settings($settings_file);
                if ($result) {
                    WP_CLI::success('Theme settings imported');
                } else {
                    WP_CLI::warning('Failed to import theme settings');
                }
            } else {
                WP_CLI::log('  No settings file found');
            }
        }
        
        // Step 7: Cleanup
        WP_CLI::log("\nCleaning up...");
        if (is_dir($temp_dir)) {
            $this->delete_directory($temp_dir);
        }
        
        // Clear cache
        // CRITICAL: Force menu location and nav_menu_item fixes
        WP_CLI::log("\nFixing menu assignments and nav_menu_item posts...");
        
        // Call the AJAX handler's menu fix method
        require_once __DIR__ . '/class-ajax-handler.php';
        $ajax_handler = new Reign_Demo_Install_Ajax_Handler();
        
        // Use reflection to call the private method
        $reflection = new ReflectionClass($ajax_handler);
        $method = $reflection->getMethod('ensure_menu_locations_set');
        $method->setAccessible(true);
        $method->invoke($ajax_handler);
        
        WP_CLI::success("Menu fixes applied.");
        
        wp_cache_flush();
        flush_rewrite_rules();
        
        // Final statistics
        $final_tables = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
        $new_tables = $final_tables - $initial_tables;
        
        WP_CLI::success("\nImport completed successfully!");
        WP_CLI::log("\nDatabase statistics:");
        WP_CLI::log("  - Total tables: $final_tables (+$new_tables new)");
        
        // Content statistics
        $content_stats = $wpdb->get_results("
            SELECT post_type, COUNT(*) as count 
            FROM {$wpdb->posts} 
            WHERE post_status = 'publish' 
            GROUP BY post_type 
            ORDER BY count DESC
            LIMIT 10
        ");
        
        WP_CLI::log("\nContent imported:");
        foreach ($content_stats as $stat) {
            WP_CLI::log("  - " . ucfirst($stat->post_type) . ": " . $stat->count);
        }
        
        // CRITICAL: Force-set essential options that might have been cleared
        WP_CLI::log("\nEnsuring critical options are set...");
        
        // 1. Force set theme
        if (!get_option('stylesheet') || !get_option('template')) {
            // Find Reign theme
            $themes = wp_get_themes();
            $reign_theme = null;
            foreach ($themes as $theme_slug => $theme_obj) {
                if (stripos($theme_obj->get('Name'), 'reign') !== false) {
                    $reign_theme = $theme_slug;
                    break;
                }
            }
            
            if ($reign_theme) {
                update_option('stylesheet', $reign_theme);
                update_option('template', $reign_theme);
                WP_CLI::success("Set theme: " . $reign_theme);
            }
        }
        
        // 2. Force set homepage if not set
        if (!get_option('page_on_front') || get_option('show_on_front') !== 'page') {
            $home_page = get_page_by_title('Home');
            if ($home_page) {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $home_page->ID);
                WP_CLI::success("Set homepage to: " . $home_page->post_title);
            }
        }
        
        // 3. Activate essential plugins if they're not active
        $required_plugins = array(
            'elementor/elementor.php' => 'Elementor',
            'woocommerce/woocommerce.php' => 'WooCommerce'
        );
        
        $activated_any = false;
        foreach ($required_plugins as $plugin_path => $plugin_name) {
            if (!is_plugin_active($plugin_path) && file_exists(WP_PLUGIN_DIR . '/' . $plugin_path)) {
                $result = activate_plugin($plugin_path);
                if (!is_wp_error($result)) {
                    WP_CLI::success("Activated: " . $plugin_name);
                    $activated_any = true;
                }
            }
        }
        
        if (!$activated_any) {
            WP_CLI::log("Essential plugins already active");
        }
        
        // Run comprehensive import verification
        WP_CLI::log("\n" . str_repeat('=', 50));
        WP_CLI::log("RUNNING IMPORT VERIFICATION TESTS");
        WP_CLI::log(str_repeat('=', 50));
        
        $this->verify_import($admin_user);
        
        WP_CLI::log("\nAccess your site:");
        WP_CLI::log("  Frontend: " . home_url());
        WP_CLI::log("  Admin: " . admin_url());
    }
}

// Register command if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('reign-demo', 'Reign_Demo_Install_CLI_Command');
}