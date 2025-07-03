# Reign Demo Importer Plugin - Development Roadmap

## Plugin Overview
**Plugin Name:** Reign Demo Importer  
**Version:** 1.0.0  
**Purpose:** One-click demo importer for Reign Theme with complete user session preservation

## Core Architecture

### File Structure
```
reign-demo-importer/
├── reign-demo-importer.php           # Main plugin file
├── includes/
│   ├── class-importer.php           # Core importer class
│   ├── class-demo-browser.php       # Demo selection interface
│   ├── class-requirements-checker.php # System requirements validation
│   ├── class-user-preserver.php     # User session preservation
│   ├── class-plugin-installer.php   # Plugin installation handler
│   ├── class-content-importer.php   # Content import processor
│   ├── class-file-importer.php      # File and media importer
│   ├── class-settings-importer.php  # Theme/plugin settings
│   ├── class-ajax-handler.php       # AJAX request handler
│   └── class-rollback-manager.php   # Rollback functionality
├── admin/
│   ├── class-admin.php              # Admin interface
│   ├── views/
│   │   ├── demo-browser.php         # Demo selection screen
│   │   ├── import-wizard.php        # Import wizard interface
│   │   └── success-screen.php       # Post-import success page
│   ├── css/
│   │   └── importer-admin.css      # Admin styles
│   └── js/
│       └── importer-admin.js       # Admin JavaScript
├── temp/                            # Temporary import files
└── assets/                          # Plugin assets
```

## User Interface Design

### 1. Demo Browser Screen
```
┌─────────────────────────────────────────────────────────────┐
│                    Reign Demo Importer                       │
├─────────────────────────────────────────────────────────────┤
│  Filter: [All] [Community] [Education] [E-Commerce] [...]    │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐           │
│  │ [Preview]   │ │ [Preview]   │ │ [Preview]   │           │
│  │             │ │             │ │             │           │
│  │ Business    │ │ Community   │ │ Education   │           │
│  │ Pro         │ │ Hub         │ │ Platform    │           │
│  │             │ │             │ │             │           │
│  │ [Import]    │ │ [Import]    │ │ [Import]    │           │
│  └─────────────┘ └─────────────┘ └─────────────┘           │
│                                                              │
│  Currently logged in as: admin@example.com                  │
│  ⚠️ Your admin account will be preserved during import      │
└─────────────────────────────────────────────────────────────┘
```

### 2. Import Wizard Screen
```
┌─────────────────────────────────────────────────────────────┐
│              Import Reign Business Pro Demo                  │
├─────────────────────────────────────────────────────────────┤
│  Step 1: Pre-Import Check ✓                                  │
│  Step 2: User Preservation ✓                                 │
│  Step 3: Plugin Installation [In Progress]                   │
│  Step 4: Content Import                                      │
│  Step 5: Settings Configuration                              │
│  Step 6: Finalization                                        │
├─────────────────────────────────────────────────────────────┤
│  Installing Plugins:                                         │
│  ✓ BuddyPress (Free)                                        │
│  ✓ WooCommerce (Free)                                       │
│  ⟳ Elementor Pro (Premium) - License Required               │
│    [Enter License Key: ________________] [Verify]           │
│  ○ Reign Theme Addon (Custom)                                │
│                                                              │
│  [████████████░░░░░░░░░░░░░░░░░░] 45%                      │
│                                                              │
│  ⚠️ Do not close this window or logout                      │
│  Your session is protected and will remain active           │
└─────────────────────────────────────────────────────────────┘
```

## User Session Preservation System

### 1. Pre-Import User Protection
```php
class Reign_Demo_User_Preserver {
    
    private $preserved_user_data = array();
    private $session_token;
    
    public function preserve_current_admin() {
        $current_user = wp_get_current_user();
        
        // Store comprehensive user data
        $this->preserved_user_data = array(
            'ID' => $current_user->ID,
            'user_login' => $current_user->user_login,
            'user_email' => $current_user->user_email,
            'user_nicename' => $current_user->user_nicename,
            'display_name' => $current_user->display_name,
            'roles' => $current_user->roles,
            'allcaps' => $current_user->allcaps,
            'user_pass' => $current_user->user_pass, // Hashed password
            'session_tokens' => $this->get_user_sessions($current_user->ID)
        );
        
        // Create a special meta flag
        update_user_meta($current_user->ID, '_reign_demo_protected_admin', true);
        update_user_meta($current_user->ID, '_reign_demo_import_time', time());
        
        // Store in database for safety
        update_option('reign_demo_preserved_admin', $this->preserved_user_data);
        
        // Set a transient to track import progress
        set_transient('reign_demo_import_active_' . $current_user->ID, true, HOUR_IN_SECONDS);
        
        return true;
    }
    
    public function protect_user_during_import($user_id) {
        // Prevent any modifications to protected user
        add_filter('user_row_actions', array($this, 'remove_user_actions'), 10, 2);
        add_filter('bulk_actions-users', array($this, 'remove_bulk_actions'));
        add_action('delete_user', array($this, 'prevent_user_deletion'), 1);
        add_action('remove_user_from_blog', array($this, 'prevent_user_removal'), 1);
    }
    
    public function maintain_session() {
        // Refresh auth cookie periodically during import
        $preserved = get_option('reign_demo_preserved_admin');
        if ($preserved && isset($preserved['ID'])) {
            wp_set_auth_cookie($preserved['ID'], true, is_ssl());
            wp_set_current_user($preserved['ID']);
        }
    }
}
```

### 2. Import Process with User Safety
```php
class Reign_Demo_Importer {
    
    private $user_preserver;
    private $protected_user_id;
    
    public function __construct() {
        $this->user_preserver = new Reign_Demo_User_Preserver();
    }
    
    public function start_import($demo_id) {
        // Step 1: Preserve current admin
        $this->user_preserver->preserve_current_admin();
        $current_user = wp_get_current_user();
        $this->protected_user_id = $current_user->ID;
        
        // Step 2: Set import lock
        update_option('reign_demo_import_lock', array(
            'active' => true,
            'started' => time(),
            'demo' => $demo_id,
            'user' => $this->protected_user_id
        ));
        
        // Step 3: Begin import process
        $this->run_import_steps($demo_id);
    }
    
    public function import_users($users_data) {
        $preserved_admin = get_option('reign_demo_preserved_admin');
        
        foreach ($users_data as $user) {
            // Skip if email matches preserved admin
            if ($user['user_email'] === $preserved_admin['user_email']) {
                continue;
            }
            
            // Check if username conflicts with preserved admin
            if ($user['user_login'] === $preserved_admin['user_login']) {
                $user['user_login'] .= '_demo';
            }
            
            // Import the user
            $user_id = username_exists($user['user_login']);
            
            if (!$user_id) {
                $user_id = wp_create_user(
                    $user['user_login'],
                    wp_generate_password(),
                    $user['user_email']
                );
            }
            
            // Never give admin role to imported users
            if (in_array('administrator', $user['roles'])) {
                $user['roles'] = array('editor');
            }
            
            // Update user data
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $user['display_name'],
                'role' => $user['roles'][0]
            ));
        }
    }
}
```

## Import Workflow

### Step 1: Demo Selection & Requirements Check
```php
public function check_requirements($demo_data) {
    $checks = array(
        'php_version' => version_compare(PHP_VERSION, '7.4', '>='),
        'wp_version' => version_compare(get_bloginfo('version'), '6.0', '>='),
        'memory_limit' => $this->check_memory_limit('256M'),
        'reign_theme' => $this->is_reign_theme_active(),
        'disk_space' => $this->check_disk_space($demo_data['package_size']),
        'user_logged_in' => is_user_logged_in(),
        'user_is_admin' => current_user_can('manage_options')
    );
    
    return $checks;
}
```

### Step 2: User Preservation & Session Lock
```php
public function preserve_and_lock_session() {
    // Preserve admin user
    $this->user_preserver->preserve_current_admin();
    
    // Create session lock
    $session_lock = wp_generate_password(32, false);
    set_transient('reign_demo_session_lock_' . get_current_user_id(), $session_lock, HOUR_IN_SECONDS);
    
    // Set cookie to maintain session
    setcookie('reign_demo_import_session', $session_lock, time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    
    // Prevent concurrent imports
    if (get_option('reign_demo_import_lock')['active']) {
        wp_die('Another import is in progress. Please wait.');
    }
}
```

### Step 3: Plugin Installation
```php
class Reign_Demo_Plugin_Installer {
    
    public function install_required_plugins($plugins_manifest) {
        $results = array();
        
        foreach ($plugins_manifest['plugins']['required'] as $plugin) {
            switch ($plugin['source']) {
                case 'wordpress.org':
                    $results[] = $this->install_from_wordpress_org($plugin);
                    break;
                    
                case 'self-hosted':
                    $results[] = $this->install_from_url($plugin['download_url']);
                    break;
                    
                case 'purchase':
                    $results[] = $this->handle_premium_plugin($plugin);
                    break;
            }
            
            // Maintain user session after each plugin install
            $this->user_preserver->maintain_session();
        }
        
        return $results;
    }
    
    private function handle_premium_plugin($plugin) {
        // Check if license key provided
        $license_key = get_transient('reign_demo_license_' . $plugin['slug']);
        
        if (!$license_key) {
            return array(
                'status' => 'pending',
                'plugin' => $plugin['slug'],
                'message' => 'License key required',
                'purchase_url' => $plugin['purchase_url']
            );
        }
        
        // Attempt to download with license
        return $this->download_premium_plugin($plugin, $license_key);
    }
}
```

### Step 4: Content Import
```php
public function import_content($content_package) {
    // Extract package
    $extracted = $this->extract_package($content_package);
    
    // Import in specific order
    $import_order = array(
        'users',        // Import users (skip preserved admin)
        'categories',   // Taxonomies first
        'tags',
        'media',        // Media library
        'posts',        // Posts and pages
        'pages',
        'menus',        // Navigation menus
        'widgets',      // Widget areas
        'theme_mods',   // Customizer settings
        'options'       // Plugin options
    );
    
    foreach ($import_order as $type) {
        $this->import_content_type($type, $extracted[$type]);
        
        // Refresh session
        $this->user_preserver->maintain_session();
        
        // Update progress
        $this->update_import_progress($type);
    }
}
```

### Step 5: Post-Import Restoration
```php
public function finalize_import() {
    // Restore admin capabilities
    $preserved_admin = get_option('reign_demo_preserved_admin');
    $user = get_user_by('ID', $preserved_admin['ID']);
    
    if ($user) {
        // Ensure admin role is intact
        $user->set_role('administrator');
        
        // Restore all capabilities
        foreach ($preserved_admin['allcaps'] as $cap => $grant) {
            if ($grant) {
                $user->add_cap($cap);
            }
        }
        
        // Clear protection flags
        delete_user_meta($user->ID, '_reign_demo_protected_admin');
        delete_user_meta($user->ID, '_reign_demo_import_time');
    }
    
    // Clear import lock
    delete_option('reign_demo_import_lock');
    
    // Clear transients
    delete_transient('reign_demo_import_active_' . $preserved_admin['ID']);
    
    // Ensure user stays logged in
    wp_set_auth_cookie($preserved_admin['ID'], true, is_ssl());
    wp_set_current_user($preserved_admin['ID']);
    
    // Redirect to success page
    wp_redirect(admin_url('admin.php?page=reign-demo-success'));
    exit;
}
```

## AJAX Implementation

### Progressive Import with Session Maintenance
```javascript
class ReignDemoImporter {
    constructor() {
        this.currentStep = 0;
        this.sessionCheckInterval = null;
        this.importSteps = [
            'preserve_user',
            'check_requirements',
            'download_package',
            'install_plugins',
            'import_content',
            'import_settings',
            'finalize'
        ];
    }
    
    startImport(demoId) {
        // Start session monitoring
        this.startSessionMonitoring();
        
        // Begin import process
        this.processNextStep(demoId);
    }
    
    startSessionMonitoring() {
        // Check session every 30 seconds
        this.sessionCheckInterval = setInterval(() => {
            jQuery.ajax({
                url: reign_demo_importer.ajax_url,
                type: 'POST',
                data: {
                    action: 'reign_demo_check_session',
                    nonce: reign_demo_importer.nonce
                },
                success: (response) => {
                    if (!response.success) {
                        this.handleSessionLoss();
                    }
                }
            });
        }, 30000);
    }
    
    processNextStep(demoId) {
        if (this.currentStep >= this.importSteps.length) {
            this.completeImport();
            return;
        }
        
        const step = this.importSteps[this.currentStep];
        
        jQuery.ajax({
            url: reign_demo_importer.ajax_url,
            type: 'POST',
            data: {
                action: 'reign_demo_import_step',
                step: step,
                demo_id: demoId,
                nonce: reign_demo_importer.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.updateProgress(response.data);
                    this.currentStep++;
                    
                    // Continue to next step
                    setTimeout(() => {
                        this.processNextStep(demoId);
                    }, 500);
                } else {
                    this.handleError(response.data);
                }
            },
            error: () => {
                this.handleError('Connection lost. Please check your internet connection.');
            }
        });
    }
    
    handleSessionLoss() {
        // Attempt to restore session
        jQuery.ajax({
            url: reign_demo_importer.ajax_url,
            type: 'POST',
            data: {
                action: 'reign_demo_restore_session',
                nonce: reign_demo_importer.nonce
            },
            success: (response) => {
                if (response.success) {
                    console.log('Session restored successfully');
                } else {
                    alert('Your session has expired. Please refresh the page and login again.');
                    window.location.reload();
                }
            }
        });
    }
}
```

## Security Features

### 1. Session Protection
```php
// Prevent logout during import
add_filter('logout_url', function($logout_url) {
    if (get_transient('reign_demo_import_active_' . get_current_user_id())) {
        return '#';
    }
    return $logout_url;
});

// Prevent user switching during import
add_filter('user_switching_switched_message', function() {
    if (get_transient('reign_demo_import_active_' . get_current_user_id())) {
        wp_die('Cannot switch users during demo import.');
    }
});
```

### 2. Import Lock System
```php
class Reign_Demo_Import_Lock {
    
    public function acquire_lock($user_id, $demo_id) {
        $lock_data = array(
            'user_id' => $user_id,
            'demo_id' => $demo_id,
            'started' => time(),
            'status' => 'active'
        );
        
        // Check for existing lock
        $existing_lock = get_option('reign_demo_import_lock');
        
        if ($existing_lock && $existing_lock['status'] === 'active') {
            // Check if lock is stale (older than 1 hour)
            if (time() - $existing_lock['started'] > 3600) {
                // Release stale lock
                $this->release_lock();
            } else {
                return false; // Lock exists
            }
        }
        
        update_option('reign_demo_import_lock', $lock_data);
        return true;
    }
    
    public function release_lock() {
        delete_option('reign_demo_import_lock');
    }
}
```

## Success Screen

### Post-Import Success Page
```php
public function render_success_screen() {
    $current_user = wp_get_current_user();
    $import_data = get_option('reign_demo_last_import');
    
    ?>
    <div class="reign-demo-success-wrapper">
        <h1>🎉 Demo Import Successful!</h1>
        
        <div class="success-message">
            <p>Welcome back, <strong><?php echo esc_html($current_user->display_name); ?></strong>!</p>
            <p>The <strong><?php echo esc_html($import_data['demo_name']); ?></strong> demo has been successfully imported.</p>
        </div>
        
        <div class="import-summary">
            <h3>Import Summary:</h3>
            <ul>
                <li>✓ <?php echo $import_data['posts_imported']; ?> posts imported</li>
                <li>✓ <?php echo $import_data['pages_imported']; ?> pages imported</li>
                <li>✓ <?php echo $import_data['plugins_installed']; ?> plugins installed</li>
                <li>✓ <?php echo $import_data['media_imported']; ?> media files imported</li>
                <li>✓ Theme settings configured</li>
                <li>✓ Your admin account preserved</li>
            </ul>
        </div>
        
        <div class="next-steps">
            <h3>Next Steps:</h3>
            <a href="<?php echo home_url(); ?>" class="button button-primary">View Your Site</a>
            <a href="<?php echo admin_url('customize.php'); ?>" class="button">Customize Theme</a>
            <a href="<?php echo admin_url('themes.php?page=reign-settings'); ?>" class="button">Theme Settings</a>
        </div>
    </div>
    <?php
}
```

## Error Recovery

### Rollback Functionality
```php
class Reign_Demo_Rollback_Manager {
    
    private $backup_data = array();
    
    public function create_restore_point() {
        $this->backup_data = array(
            'options' => $this->backup_options(),
            'users' => $this->backup_users(),
            'active_plugins' => get_option('active_plugins'),
            'theme_mods' => get_theme_mods(),
            'timestamp' => time()
        );
        
        update_option('reign_demo_restore_point', $this->backup_data);
    }
    
    public function rollback() {
        $restore_point = get_option('reign_demo_restore_point');
        
        if ($restore_point) {
            // Restore options
            foreach ($restore_point['options'] as $option => $value) {
                update_option($option, $value);
            }
            
            // Restore plugins
            update_option('active_plugins', $restore_point['active_plugins']);
            
            // Restore theme mods
            set_theme_mods($restore_point['theme_mods']);
            
            // Ensure admin user is still intact
            $this->verify_admin_user();
        }
    }
}
```

## Performance Optimization

### 1. Chunked Processing
```php
public function import_large_dataset($data, $type) {
    $chunk_size = 50;
    $chunks = array_chunk($data, $chunk_size);
    
    foreach ($chunks as $index => $chunk) {
        $this->process_chunk($chunk, $type);
        
        // Update progress
        $progress = (($index + 1) / count($chunks)) * 100;
        $this->update_progress($type, $progress);
        
        // Maintain session
        $this->user_preserver->maintain_session();
        
        // Prevent timeout
        set_time_limit(30);
    }
}
```

### 2. Background Processing Option
```php
// For very large imports, offer background processing
class Reign_Demo_Background_Processor extends WP_Background_Process {
    
    protected $action = 'reign_demo_import';
    
    protected function task($item) {
        // Process import task
        $this->import_single_item($item);
        
        // Maintain user session
        $this->maintain_admin_session();
        
        return false;
    }
}
```

## Testing Strategy

### 1. User Preservation Tests
- Test admin remains logged in during import
- Test role preservation
- Test capability preservation
- Test session timeout handling

### 2. Import Process Tests
- Test with various demo sizes
- Test plugin installation failures
- Test network interruptions
- Test concurrent import attempts

### 3. Compatibility Tests
- Different hosting environments
- Various PHP versions
- Memory limit variations
- Plugin conflict testing

## Future Enhancements

1. **Import Profiles**
   - Save import preferences
   - Skip certain content types
   - Custom plugin mappings

2. **Partial Imports**
   - Import only specific features
   - Update existing imports
   - Merge content options

3. **Multi-site Support**
   - Network-wide imports
   - Site-specific demos
   - Shared user management

4. **Advanced User Management**
   - Map demo users to existing users
   - Bulk user role assignments
   - User content attribution