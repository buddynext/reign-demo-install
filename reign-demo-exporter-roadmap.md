# Reign Demo Exporter Plugin - Development Roadmap

## Plugin Overview
**Plugin Name:** Reign Demo Exporter  
**Version:** 1.0.0  
**Purpose:** One-click export tool to generate JSON manifests and content packages for Reign Theme demos

## Core Architecture

### File Structure
```
reign-demo-exporter/
├── reign-demo-exporter.php          # Main plugin file
├── includes/
│   ├── class-exporter.php           # Core exporter class
│   ├── class-content-scanner.php    # Scans site content
│   ├── class-plugin-scanner.php     # Analyzes installed plugins
│   ├── class-file-scanner.php       # Scans theme files and uploads
│   ├── class-package-creator.php    # Creates ZIP packages
│   ├── class-manifest-generator.php # Generates JSON files
│   └── class-ajax-handler.php       # Handles AJAX requests
├── admin/
│   ├── class-admin.php              # Admin interface
│   ├── views/
│   │   └── export-page.php          # Main export screen
│   ├── css/
│   │   └── exporter-admin.css      # Admin styles
│   └── js/
│       └── exporter-admin.js       # Admin JavaScript
├── exports/                         # Temporary export directory
└── assets/                          # Plugin assets
```

## User Interface Design

### Simple One-Page Export Screen
```
┌─────────────────────────────────────────────────────────────┐
│                    Reign Demo Exporter                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌────────────────────────────────────────────────────┐     │
│  │         Export Demo Content for Reign Theme         │     │
│  │                                                     │     │
│  │  This will create:                                  │     │
│  │  • manifest.json                                    │     │
│  │  • plugins-manifest.json                            │     │
│  │  • files-manifest.json                              │     │
│  │  • content-package.zip                              │     │
│  │                                                     │     │
│  │         [Start Export]                              │     │
│  │                                                     │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
│  Export Progress:                                            │
│  ┌────────────────────────────────────────────────────┐     │
│  │ [████████████████░░░░░░░░░░░░░░] 65%              │     │
│  │ Scanning plugins...                                 │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
│  Export History:                                             │
│  ┌────────────────────────────────────────────────────┐     │
│  │ • 2025-01-03 14:30 - Export completed              │     │
│  │   Download: [manifest.json] [plugins] [files] [zip] │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## Export Process Workflow

### 1. Pre-Export Phase
```php
// Check system requirements
check_php_version()        // PHP 7.4+
check_memory_limit()       // 256M minimum
check_execution_time()     // Set to unlimited
check_disk_space()         // Ensure sufficient space
verify_reign_theme()       // Confirm Reign Theme active
```

### 2. Content Scanning Phase
```php
// Scan all content types
scan_posts_and_pages()     // All post types
scan_media_library()       // Images, videos, documents
scan_menus()              // Navigation menus
scan_widgets()            // Widget areas and content
scan_users()              // User roles and demo users
scan_comments()           // Sample comments
scan_theme_mods()         // Customizer settings
scan_options()            // Theme and plugin options
```

### 3. Plugin Analysis Phase
```php
// Analyze all active plugins
get_active_plugins()       // List of active plugins
categorize_plugins()       // Free, premium, custom
check_plugin_sources()     // WordPress.org, custom, premium
get_plugin_versions()      // Current versions
identify_dependencies()    // Plugin dependencies
generate_purchase_urls()   // For premium plugins
```

### 4. File System Scan Phase
```php
// Scan file system
scan_uploads_directory()   // Media files and custom uploads
scan_theme_files()        // Child theme modifications
scan_custom_css()         // Additional CSS
scan_custom_fonts()       // Web fonts
calculate_file_sizes()    // Directory sizes
create_file_inventory()   // Complete file list
```

### 5. Export Generation Phase
```php
// Generate export files
create_export_directory()  // /wp-content/reign-demo-export/
generate_manifest_json()   // Main manifest
generate_plugins_json()    // Plugins manifest
generate_files_json()      // Files manifest
create_content_package()   // ZIP file with content
set_public_permissions()   // Make files accessible
```

## Detailed Component Specifications

### Content Scanner (`class-content-scanner.php`)
```php
class Reign_Demo_Content_Scanner {
    
    public function scan_all_content() {
        $content = array(
            'posts' => $this->get_posts_data(),
            'pages' => $this->get_pages_data(),
            'media' => $this->get_media_data(),
            'menus' => $this->get_menus_data(),
            'widgets' => $this->get_widgets_data(),
            'users' => $this->get_demo_users(), // Simple export, users already have IDs 100+
            'custom_post_types' => $this->get_cpt_data(),
            'taxonomies' => $this->get_taxonomies_data(),
            'theme_mods' => $this->get_theme_mods(),
            'options' => $this->get_relevant_options()
        );
        
        // Special handling for different plugins
        if (is_plugin_active('buddypress/bp-loader.php')) {
            $content['buddypress'] = $this->get_buddypress_data();
        }
        
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            $content['woocommerce'] = $this->get_woocommerce_data();
        }
        
        if (is_plugin_active('learndash/learndash.php')) {
            $content['learndash'] = $this->get_learndash_data();
        }
        
        return $content;
    }
    
    private function get_demo_users() {
        // Export all users with IDs 100+ (already standardized)
        $users = get_users(array(
            'meta_key' => '_reign_demo_user',
            'meta_value' => true,
            'fields' => 'all_with_meta'
        ));
        
        $users_data = array();
        
        foreach ($users as $user) {
            // Skip any legacy users with ID < 100
            if ($user->ID < 100) {
                continue;
            }
            
            $user_data = array(
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'user_pass' => $user->user_pass,
                'user_nicename' => $user->user_nicename,
                'display_name' => $user->display_name,
                'user_url' => $user->user_url,
                'user_registered' => $user->user_registered,
                'user_status' => $user->user_status,
                'roles' => $user->roles,
                'meta' => get_user_meta($user->ID)
            );
            
            $users_data[] = $user_data;
        }
        
        return $users_data;
    }
    
    private function get_posts_data() {
        $posts = array();
        $all_posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($all_posts as $post) {
            $post_data = $post->to_array();
            $post_data['meta'] = get_post_meta($post->ID);
            $post_data['comments'] = $this->get_comments($post->ID);
            
            $posts[] = $post_data;
        }
        
        return $posts;
    }
    
    private function get_comments($post_id) {
        $comments = get_comments(array('post_id' => $post_id));
        $comments_data = array();
        
        foreach ($comments as $comment) {
            $comments_data[] = $comment->to_array();
        }
        
        return $comments_data;
    }
    
    private function get_buddypress_data() {
        global $wpdb;
        $bp_data = array();
        
        // Export BuddyPress tables as-is (user IDs already 100+)
        $bp_tables = array(
            'bp_activity',
            'bp_activity_meta',
            'bp_friends',
            'bp_groups',
            'bp_groups_members',
            'bp_groups_groupmeta',
            'bp_messages_messages',
            'bp_messages_meta',
            'bp_messages_notices',
            'bp_messages_recipients',
            'bp_notifications',
            'bp_xprofile_data',
            'bp_xprofile_fields',
            'bp_xprofile_groups'
        );
        
        foreach ($bp_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
                $bp_data[$table] = $wpdb->get_results("SELECT * FROM {$table_name}");
            }
        }
        
        return $bp_data;
    }
    
    private function get_woocommerce_data() {
        global $wpdb;
        $wc_data = array();
        
        // Export WooCommerce orders with customer data
        $wc_data['orders'] = $this->get_wc_orders();
        
        // Export product reviews
        $wc_data['reviews'] = $this->get_wc_reviews();
        
        // Export customer data
        $wc_data['customers'] = $this->get_wc_customers();
        
        return $wc_data;
    }
}
```

### Plugin Scanner (`class-plugin-scanner.php`)
```php
class Reign_Demo_Plugin_Scanner {
    
    private $plugin_categories = array(
        'wordpress_org' => array(),
        'premium' => array(),
        'custom' => array()
    );
    
    private $known_premium_plugins = array(
        'elementor-pro' => 'https://elementor.com/pro/',
        'buddyboss-platform-pro' => 'https://www.buddyboss.com/platform/',
        'learndash' => 'https://www.learndash.com/',
        // ... more premium plugins
    );
    
    public function scan_plugins() {
        $active_plugins = get_option('active_plugins');
        
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $this->categorize_plugin($plugin, $plugin_data);
        }
        
        return $this->generate_plugin_manifest();
    }
}
```

### Package Creator (`class-package-creator.php`)
```php
class Reign_Demo_Package_Creator {
    
    public function create_package($content_data) {
        $zip = new ZipArchive();
        $filename = 'content-package.zip';
        
        if ($zip->open($filename, ZipArchive::CREATE) === TRUE) {
            // Add database export
            $this->add_database_export($zip, $content_data);
            
            // Add media files
            $this->add_media_files($zip);
            
            // Add theme files
            $this->add_theme_files($zip);
            
            // Add custom files
            $this->add_custom_files($zip);
            
            $zip->close();
        }
        
        return $filename;
    }
}
```

## AJAX Implementation

### Progressive Export with Status Updates
```javascript
jQuery(document).ready(function($) {
    $('#start-export').on('click', function() {
        var steps = [
            'preparing',
            'scanning_content',
            'analyzing_plugins',
            'scanning_files',
            'creating_manifests',
            'packaging_content',
            'finalizing'
        ];
        
        var currentStep = 0;
        
        function processStep() {
            if (currentStep < steps.length) {
                $.ajax({
                    url: reign_demo_exporter.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'reign_demo_export_step',
                        step: steps[currentStep],
                        nonce: reign_demo_exporter.nonce
                    },
                    success: function(response) {
                        updateProgress(response.progress, response.message);
                        currentStep++;
                        processStep();
                    }
                });
            } else {
                showDownloadLinks();
            }
        }
        
        processStep();
    });
});
```

## Export Directory Structure

### Created in `/wp-content/reign-demo-export/`
```
reign-demo-export/
├── manifest.json
├── plugins-manifest.json
├── files-manifest.json
├── content-package.zip
├── .htaccess              # Allow public access
└── index.php              # Directory listing protection
```

## Security Measures

### 1. User Permissions
- Only administrators can access export functionality
- Capability check: `manage_options`

### 2. File Security
```php
// .htaccess for public access
<FilesMatch "\.(json|zip)$">
    Order allow,deny
    Allow from all
</FilesMatch>

// Prevent directory listing
Options -Indexes
```

### 3. Data Sanitization
- Exclude sensitive data (passwords, private keys)
- Anonymize user emails if needed
- Remove temporary/cache data

## Error Handling

### Comprehensive Error Management
```php
try {
    $exporter = new Reign_Demo_Exporter();
    $exporter->run();
} catch (Exception $e) {
    $this->log_error($e->getMessage());
    wp_die('Export failed: ' . $e->getMessage());
}
```

## Performance Optimization

### 1. Chunked Processing
- Process large datasets in batches
- Use AJAX for progressive updates
- Implement resume capability

### 2. Memory Management
```php
// Increase memory limit temporarily
@ini_set('memory_limit', '512M');
@set_time_limit(0);

// Clear object cache periodically
wp_cache_flush();
```

## Testing Strategy

### 1. Unit Tests
- Test each scanner component
- Verify manifest generation
- Check ZIP creation

### 2. Integration Tests
- Full export process
- Different site configurations
- Various plugin combinations

### 3. Compatibility Tests
- PHP versions (7.4, 8.0, 8.1, 8.2)
- WordPress versions (6.0+)
- Server environments

## Maintenance Features

### 1. Export Cleanup
```php
// Auto-cleanup old exports
add_action('reign_demo_daily_cleanup', function() {
    $export_dir = WP_CONTENT_DIR . '/reign-demo-export/';
    $files = glob($export_dir . '*');
    
    foreach ($files as $file) {
        if (filemtime($file) < strtotime('-7 days')) {
            unlink($file);
        }
    }
});
```

### 2. Export Logs
- Track export history
- Log errors and warnings
- Performance metrics

## Future Enhancements

1. **Selective Export**
   - Choose specific content types
   - Date range filters
   - Exclude certain plugins

2. **Cloud Upload**
   - Direct upload to S3/CDN
   - Automatic backup to cloud

3. **Export Profiles**
   - Save export configurations
   - Quick re-export options

4. **Differential Exports**
   - Export only changes
   - Version control integration