<?php
/**
 * Admin Interface Class
 * 
 * Handles the admin interface for demo installation
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Install_Admin {
    
    /**
     * Render admin page
     */
    public function render() {
        ?>
        <div class="wrap reign-demo-install-wrap">
            <h1><?php _e('Reign Demo Install', 'reign-demo-install'); ?></h1>
            
            <?php $this->render_notices(); ?>
            
            <div class="reign-demo-container">
                <?php
                $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'demos';
                $this->render_tabs($tab);
                
                switch ($tab) {
                    case 'demos':
                        $this->render_demos_tab();
                        break;
                        
                    case 'backups':
                        $this->render_backups_tab();
                        break;
                        
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                        
                    case 'help':
                        $this->render_help_tab();
                        break;
                        
                    default:
                        $this->render_demos_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render notices
     */
    private function render_notices() {
        // Don't use transients - they cause issues
        // Following Wbcom's simpler approach
        
        // Check theme
        $theme = wp_get_theme();
        $theme_name = strtolower($theme->get('Name'));
        $theme_template = strtolower($theme->get('Template'));
        $theme_stylesheet = strtolower($theme->get_stylesheet());
        
        // Check if it's Reign theme (case-insensitive)
        if (!in_array('reign', array($theme_name, $theme_template, $theme_stylesheet)) && 
            !in_array('reign-theme', array($theme_name, $theme_template, $theme_stylesheet))) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('Theme Not Active', 'reign-demo-install'); ?></strong>
                    <?php _e('Reign theme must be active to use demo installer. Please activate Reign theme first.', 'reign-demo-install'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Render tabs
     */
    private function render_tabs($current_tab) {
        $tabs = array(
            'demos' => __('Demos', 'reign-demo-install'),
            'backups' => __('Backups', 'reign-demo-install'),
            'settings' => __('Settings', 'reign-demo-install'),
            'help' => __('Help', 'reign-demo-install')
        );
        
        ?>
        <nav class="nav-tab-wrapper wp-clearfix">
            <?php
            foreach ($tabs as $tab => $label) {
                $url = admin_url('admin.php?page=reign-demos&tab=' . $tab);
                $class = ($tab === $current_tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
                printf('<a href="%s" class="%s">%s</a>', esc_url($url), esc_attr($class), esc_html($label));
            }
            ?>
        </nav>
        <?php
    }
    
    /**
     * Render demos tab
     */
    private function render_demos_tab() {
        ?>
        <div class="reign-demo-content">
            <div class="reign-demo-header">
                <h2><?php _e('Available Demos', 'reign-demo-install'); ?></h2>
                <p><?php _e('Choose a demo to import. Your current admin account will be preserved during the import process.', 'reign-demo-install'); ?></p>
            </div>
            
            <div class="reign-demo-controls">
                <div class="reign-demo-search">
                    <input type="text" id="reign-demo-search" placeholder="<?php esc_attr_e('Search demos...', 'reign-demo-install'); ?>" />
                </div>
                
                <div class="reign-demo-filter">
                    <label><?php _e('Category:', 'reign-demo-install'); ?></label>
                    <select id="reign-demo-category">
                        <option value="all"><?php _e('All Categories', 'reign-demo-install'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="reign-demo-grid" id="reign-demo-grid">
                <div class="reign-demo-loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading demos...', 'reign-demo-install'); ?>
                </div>
            </div>
            
            <!-- Import Modal -->
            <div id="reign-demo-import-modal" class="reign-modal" style="display: none;">
                <div class="reign-modal-content">
                    <div class="reign-modal-header">
                        <h3><?php _e('Import Demo', 'reign-demo-install'); ?></h3>
                        <button class="reign-modal-close">&times;</button>
                    </div>
                    
                    <div class="reign-modal-body">
                        <div class="reign-plugin-requirements" style="display: none;">
                            <h4><?php _e('Required Plugins', 'reign-demo-install'); ?></h4>
                            <p><?php _e('The following plugins are required for this demo:', 'reign-demo-install'); ?></p>
                            <div class="reign-plugin-list"></div>
                            <div class="reign-plugin-actions">
                                <button class="button reign-install-plugins" style="display: none;"><?php _e('Install Missing Plugins', 'reign-demo-install'); ?></button>
                                <button class="button button-primary reign-continue-import" style="display: none;"><?php _e('Continue to Import', 'reign-demo-install'); ?></button>
                            </div>
                        </div>
                        
                        <div class="reign-import-options" style="display: none;">
                            <h4><?php _e('Import Options', 'reign-demo-install'); ?></h4>
                            
                            <label class="reign-checkbox">
                                <input type="checkbox" id="import-content" checked />
                                <span><?php _e('Import Content (posts, pages, menus)', 'reign-demo-install'); ?></span>
                            </label>
                            
                            <label class="reign-checkbox">
                                <input type="checkbox" id="import-media" checked />
                                <span><?php _e('Import Media Files', 'reign-demo-install'); ?></span>
                            </label>
                            
                            <label class="reign-checkbox">
                                <input type="checkbox" id="import-users" checked />
                                <span><?php _e('Import Demo Users', 'reign-demo-install'); ?></span>
                            </label>
                            
                            <label class="reign-checkbox">
                                <input type="checkbox" id="import-settings" checked />
                                <span><?php _e('Import Settings & Customizations', 'reign-demo-install'); ?></span>
                            </label>
                            
                            <label class="reign-checkbox">
                                <input type="checkbox" id="clean-install" />
                                <span><?php _e('Clean Install (remove existing content)', 'reign-demo-install'); ?></span>
                                <small><?php _e('Warning: This will delete your existing content except your admin user.', 'reign-demo-install'); ?></small>
                            </label>
                            
                            <label class="reign-checkbox">
                                <input type="checkbox" id="backup-before-import" checked />
                                <span><?php _e('Create Database Backup Before Import', 'reign-demo-install'); ?></span>
                                <small><?php _e('Creates a backup of your database. Uncheck for very large sites to avoid timeout errors.', 'reign-demo-install'); ?></small>
                            </label>
                            
                            <label class="reign-checkbox" id="backup-essential-only-wrapper" style="margin-left: 20px;">
                                <input type="checkbox" id="backup-essential-only" />
                                <span><?php _e('Backup Essential Tables Only', 'reign-demo-install'); ?></span>
                                <small><?php _e('Only backup critical tables (users, options, usermeta) for faster backup on large sites.', 'reign-demo-install'); ?></small>
                            </label>
                        </div>
                        
                        <div class="reign-import-progress" style="display: none;">
                            <h4><?php _e('Import Progress', 'reign-demo-install'); ?></h4>
                            <div class="reign-progress-bar">
                                <div class="reign-progress-fill"></div>
                            </div>
                            <div class="reign-progress-status"></div>
                            <div class="reign-progress-log"></div>
                        </div>
                        
                        <div class="reign-user-notice">
                            <p><strong><?php _e('Important:', 'reign-demo-install'); ?></strong></p>
                            <p><?php _e('Your current admin account will be preserved:', 'reign-demo-install'); ?></p>
                            <ul>
                                <li><?php _e('Username:', 'reign-demo-install'); ?> <strong><?php echo wp_get_current_user()->user_login; ?></strong></li>
                                <li><?php _e('Email:', 'reign-demo-install'); ?> <strong><?php echo wp_get_current_user()->user_email; ?></strong></li>
                            </ul>
                            <p><?php _e('You will remain logged in throughout the import process.', 'reign-demo-install'); ?></p>
                        </div>
                    </div>
                    
                    <div class="reign-modal-footer">
                        <button class="button button-secondary reign-cancel-import"><?php _e('Cancel', 'reign-demo-install'); ?></button>
                        <button class="button button-primary reign-start-import"><?php _e('Start Import', 'reign-demo-install'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render backups tab
     */
    private function render_backups_tab() {
        $rollback_manager = new Reign_Demo_Rollback_Manager();
        $backups = $rollback_manager->list_backups();
        
        ?>
        <div class="reign-demo-content">
            <div class="reign-demo-header">
                <h2><?php _e('Backups', 'reign-demo-install'); ?></h2>
                <p><?php _e('Manage your site backups created before demo imports.', 'reign-demo-install'); ?></p>
            </div>
            
            <?php if (empty($backups)) : ?>
                <div class="reign-no-backups">
                    <p><?php _e('No backups found. Backups are created automatically before demo imports.', 'reign-demo-install'); ?></p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Backup Date', 'reign-demo-install'); ?></th>
                            <th><?php _e('Size', 'reign-demo-install'); ?></th>
                            <th><?php _e('Type', 'reign-demo-install'); ?></th>
                            <th><?php _e('Actions', 'reign-demo-install'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup_id => $backup) : ?>
                            <tr>
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($backup['date']))); ?></td>
                                <td><?php echo esc_html($this->format_bytes($backup['size'])); ?></td>
                                <td><?php echo $backup['compressed'] ? __('Compressed', 'reign-demo-install') : __('Full', 'reign-demo-install'); ?></td>
                                <td>
                                    <button class="button reign-restore-backup" data-backup-id="<?php echo esc_attr($backup_id); ?>">
                                        <?php _e('Restore', 'reign-demo-install'); ?>
                                    </button>
                                    <button class="button reign-delete-backup" data-backup-id="<?php echo esc_attr($backup_id); ?>">
                                        <?php _e('Delete', 'reign-demo-install'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        $settings = get_option('reign_demo_install_settings', array());
        $licenses = get_option('reign_demo_plugin_licenses', array());
        
        ?>
        <div class="reign-demo-content">
            <div class="reign-demo-header">
                <h2><?php _e('Settings', 'reign-demo-install'); ?></h2>
            </div>
            
            <form method="post" action="options.php" class="reign-settings-form">
                <?php settings_fields('reign_demo_install_settings'); ?>
                
                <h3><?php _e('Import Settings', 'reign-demo-install'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Import Options', 'reign-demo-install'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="reign_demo_install_settings[preserve_admin]" value="1" 
                                       <?php checked(isset($settings['preserve_admin']) ? $settings['preserve_admin'] : true); ?> />
                                <?php _e('Always preserve current admin user', 'reign-demo-install'); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox" name="reign_demo_install_settings[clean_install]" value="1" 
                                       <?php checked(isset($settings['clean_install']) ? $settings['clean_install'] : false); ?> />
                                <?php _e('Clean install by default', 'reign-demo-install'); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox" name="reign_demo_install_settings[import_media]" value="1" 
                                       <?php checked(isset($settings['import_media']) ? $settings['import_media'] : true); ?> />
                                <?php _e('Import media files by default', 'reign-demo-install'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Backup Settings', 'reign-demo-install'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="reign_demo_install_settings[auto_backup]" value="1" 
                                       <?php checked(isset($settings['auto_backup']) ? $settings['auto_backup'] : true); ?> />
                                <?php _e('Automatically create backup before import', 'reign-demo-install'); ?>
                            </label>
                            <br />
                            <label>
                                <?php _e('Keep backups for', 'reign-demo-install'); ?>
                                <input type="number" name="reign_demo_install_settings[backup_days]" 
                                       value="<?php echo esc_attr(isset($settings['backup_days']) ? $settings['backup_days'] : 30); ?>" 
                                       min="1" max="365" style="width: 60px;" />
                                <?php _e('days', 'reign-demo-install'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Premium Plugin Licenses', 'reign-demo-install'); ?></h3>
                <p><?php _e('Enter license keys for premium plugins to enable automatic installation.', 'reign-demo-install'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('BuddyBoss Platform', 'reign-demo-install'); ?></th>
                        <td>
                            <input type="text" name="reign_demo_plugin_licenses[buddyboss-platform]" 
                                   value="<?php echo esc_attr($licenses['buddyboss-platform'] ?? ''); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('LearnDash LMS', 'reign-demo-install'); ?></th>
                        <td>
                            <input type="text" name="reign_demo_plugin_licenses[learndash]" 
                                   value="<?php echo esc_attr($licenses['learndash'] ?? ''); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Elementor Pro', 'reign-demo-install'); ?></th>
                        <td>
                            <input type="text" name="reign_demo_plugin_licenses[elementor-pro]" 
                                   value="<?php echo esc_attr($licenses['elementor-pro'] ?? ''); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render help tab
     */
    private function render_help_tab() {
        ?>
        <div class="reign-demo-content">
            <div class="reign-demo-header">
                <h2><?php _e('Help & Documentation', 'reign-demo-install'); ?></h2>
            </div>
            
            <div class="reign-help-content">
                <h3><?php _e('Getting Started', 'reign-demo-install'); ?></h3>
                <p><?php _e('The Reign Demo Install plugin allows you to quickly import pre-built demo sites with just a few clicks.', 'reign-demo-install'); ?></p>
                
                <h4><?php _e('Import Process', 'reign-demo-install'); ?></h4>
                <ol>
                    <li><?php _e('Browse available demos in the Demos tab', 'reign-demo-install'); ?></li>
                    <li><?php _e('Click "Import" on your chosen demo', 'reign-demo-install'); ?></li>
                    <li><?php _e('Select your import options', 'reign-demo-install'); ?></li>
                    <li><?php _e('Click "Start Import" and wait for completion', 'reign-demo-install'); ?></li>
                </ol>
                
                <h4><?php _e('Important Notes', 'reign-demo-install'); ?></h4>
                <ul>
                    <li><?php _e('Your admin account is always preserved during import', 'reign-demo-install'); ?></li>
                    <li><?php _e('Backups are created automatically before import', 'reign-demo-install'); ?></li>
                    <li><?php _e('You can restore from backups at any time', 'reign-demo-install'); ?></li>
                    <li><?php _e('Premium plugins require valid license keys', 'reign-demo-install'); ?></li>
                </ul>
                
                <h4><?php _e('Troubleshooting', 'reign-demo-install'); ?></h4>
                <p><?php _e('If you encounter issues during import:', 'reign-demo-install'); ?></p>
                <ul>
                    <li><?php _e('Ensure your server meets the minimum requirements', 'reign-demo-install'); ?></li>
                    <li><?php _e('Check that Reign theme is active', 'reign-demo-install'); ?></li>
                    <li><?php _e('Verify you have sufficient disk space', 'reign-demo-install'); ?></li>
                    <li><?php _e('Try increasing PHP memory limit and execution time', 'reign-demo-install'); ?></li>
                </ul>
                
                <h4><?php _e('Support', 'reign-demo-install'); ?></h4>
                <p>
                    <?php 
                    printf(
                        __('For additional help, please visit our <a href="%s" target="_blank">documentation</a> or <a href="%s" target="_blank">support forum</a>.', 'reign-demo-install'),
                        'https://wbcomdesigns.com/docs/reign/',
                        'https://wbcomdesigns.com/support/'
                    ); 
                    ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Format bytes to human readable
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}