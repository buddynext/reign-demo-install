<?php
/**
 * Content Importer Class
 * 
 * Handles importing demo content including posts, pages, menus, etc.
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_Content_Importer {
    
    private $import_data = array();
    private $processed = array();
    private $user_mapping = array();
    private $term_mapping = array();
    private $post_mapping = array();
    private $menu_mapping = array();
    private $preserved_admin_id;
    
    /**
     * Import content from package
     */
    public function import_content($content_file, $options = array()) {
        if (!file_exists($content_file)) {
            return new WP_Error('file_not_found', __('Content file not found', 'reign-demo-install'));
        }
        
        // Load import data
        $content = file_get_contents($content_file);
        $this->import_data = json_decode($content, true);
        
        if (!$this->import_data) {
            return new WP_Error('invalid_json', __('Invalid content file', 'reign-demo-install'));
        }
        
        // Get preserved admin ID
        $preserved_admin = get_option('reign_demo_preserved_admin');
        $this->preserved_admin_id = $preserved_admin ? $preserved_admin['ID'] : get_current_user_id();
        
        // Set default options
        $defaults = array(
            'import_users' => true,
            'import_media' => true,
            'import_terms' => true,
            'import_posts' => true,
            'import_menus' => true,
            'import_widgets' => true,
            'clean_existing' => false
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Clean existing content if requested
        if ($options['clean_existing']) {
            $this->clean_existing_content();
        }
        
        // Import in correct order
        $steps = array();
        
        if ($options['import_users'] && isset($this->import_data['users'])) {
            $steps['users'] = $this->import_users();
        }
        
        if ($options['import_media'] && isset($this->import_data['media'])) {
            $steps['media'] = $this->import_media();
        }
        
        if ($options['import_terms'] && isset($this->import_data['terms'])) {
            $steps['terms'] = $this->import_terms();
        }
        
        if ($options['import_posts'] && isset($this->import_data['posts'])) {
            $steps['posts'] = $this->import_posts();
        }
        
        if ($options['import_menus'] && isset($this->import_data['menus'])) {
            $steps['menus'] = $this->import_menus();
        }
        
        if ($options['import_widgets'] && isset($this->import_data['widgets'])) {
            $steps['widgets'] = $this->import_widgets();
        }
        
        // Process relationships after all content is imported
        $this->process_relationships();
        
        return $steps;
    }
    
    /**
     * Clean existing content
     */
    private function clean_existing_content() {
        global $wpdb;
        
        // Delete posts (except for the preserved admin's posts)
        $posts = get_posts(array(
            'post_type' => 'any',
            'numberposts' => -1,
            'post_status' => 'any',
            'author__not_in' => array($this->preserved_admin_id)
        ));
        
        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
        
        // Delete terms
        $taxonomies = get_taxonomies();
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false
            ));
            
            foreach ($terms as $term) {
                if (!in_array($term->term_id, array(1))) { // Keep default uncategorized
                    wp_delete_term($term->term_id, $taxonomy);
                }
            }
        }
        
        // Delete menus
        $menus = wp_get_nav_menus();
        foreach ($menus as $menu) {
            wp_delete_nav_menu($menu->term_id);
        }
    }
    
    /**
     * Import users
     */
    private function import_users() {
        $imported = 0;
        $failed = 0;
        
        foreach ($this->import_data['users'] as $user_data) {
            // Skip if user ID conflicts with preserved admin
            if ($user_data['ID'] == $this->preserved_admin_id) {
                // Map old admin ID to preserved admin ID
                $this->user_mapping[$user_data['ID']] = $this->preserved_admin_id;
                continue;
            }
            
            // Check if user already exists by email
            $existing_user = get_user_by('email', $user_data['user_email']);
            if ($existing_user) {
                $this->user_mapping[$user_data['ID']] = $existing_user->ID;
                continue;
            }
            
            // Create new user
            $userdata = array(
                'user_login' => $user_data['user_login'],
                'user_email' => $user_data['user_email'],
                'user_pass' => wp_generate_password(),
                'display_name' => $user_data['display_name'],
                'first_name' => $user_data['first_name'] ?? '',
                'last_name' => $user_data['last_name'] ?? '',
                'role' => $user_data['roles'][0] ?? 'subscriber'
            );
            
            // Ensure unique login
            $original_login = $userdata['user_login'];
            $counter = 1;
            while (username_exists($userdata['user_login'])) {
                $userdata['user_login'] = $original_login . '_' . $counter;
                $counter++;
            }
            
            $new_user_id = wp_insert_user($userdata);
            
            if (!is_wp_error($new_user_id)) {
                $this->user_mapping[$user_data['ID']] = $new_user_id;
                
                // Import user meta
                if (isset($user_data['meta'])) {
                    foreach ($user_data['meta'] as $meta_key => $meta_value) {
                        // Skip certain meta keys
                        if (in_array($meta_key, array('session_tokens', 'wp_user_level'))) {
                            continue;
                        }
                        
                        update_user_meta($new_user_id, $meta_key, maybe_unserialize($meta_value));
                    }
                }
                
                // Import BuddyPress/BuddyBoss xProfile data
                if (isset($user_data['xprofile'])) {
                    $this->import_xprofile_data($new_user_id, $user_data['xprofile']);
                }
                
                $imported++;
            } else {
                $failed++;
            }
        }
        
        return array(
            'imported' => $imported,
            'failed' => $failed,
            'total' => count($this->import_data['users'])
        );
    }
    
    /**
     * Import xProfile data
     */
    private function import_xprofile_data($user_id, $xprofile_data) {
        if (!function_exists('xprofile_set_field_data')) {
            return;
        }
        
        foreach ($xprofile_data as $field_id => $field_value) {
            xprofile_set_field_data($field_id, $user_id, $field_value);
        }
    }
    
    /**
     * Import media
     */
    private function import_media() {
        $imported = 0;
        $failed = 0;
        
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        foreach ($this->import_data['media'] as $media_data) {
            // Check if file exists in package
            $file_path = $media_data['file_path'] ?? '';
            if (empty($file_path)) {
                $failed++;
                continue;
            }
            
            // Download or copy file
            $upload = $this->handle_media_upload($media_data);
            
            if (!is_wp_error($upload)) {
                // Create attachment
                $attachment_data = array(
                    'post_title' => $media_data['post_title'],
                    'post_content' => $media_data['post_content'] ?? '',
                    'post_excerpt' => $media_data['post_excerpt'] ?? '',
                    'post_status' => 'inherit',
                    'post_mime_type' => $upload['type'],
                    'guid' => $upload['url']
                );
                
                // Map author
                if (isset($media_data['post_author'])) {
                    $attachment_data['post_author'] = $this->get_mapped_user_id($media_data['post_author']);
                }
                
                $attach_id = wp_insert_attachment($attachment_data, $upload['file']);
                
                if (!is_wp_error($attach_id)) {
                    // Generate attachment metadata
                    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    
                    // Map old ID to new ID
                    $this->post_mapping[$media_data['ID']] = $attach_id;
                    
                    // Import attachment meta
                    if (isset($media_data['meta'])) {
                        foreach ($media_data['meta'] as $meta_key => $meta_value) {
                            update_post_meta($attach_id, $meta_key, maybe_unserialize($meta_value));
                        }
                    }
                    
                    $imported++;
                } else {
                    $failed++;
                }
            } else {
                $failed++;
            }
        }
        
        return array(
            'imported' => $imported,
            'failed' => $failed,
            'total' => count($this->import_data['media'])
        );
    }
    
    /**
     * Handle media upload
     */
    private function handle_media_upload($media_data) {
        // Get file from demo package
        $temp_dir = WP_CONTENT_DIR . '/reign-demo-temp/';
        $source_file = $temp_dir . 'current-import/files/' . $media_data['file_path'];
        
        if (!file_exists($source_file)) {
            // Try to download from URL if provided
            if (isset($media_data['url'])) {
                $tmp = download_url($media_data['url']);
                if (!is_wp_error($tmp)) {
                    $file_array = array(
                        'name' => basename($media_data['file_path']),
                        'tmp_name' => $tmp
                    );
                    
                    $upload = wp_handle_sideload($file_array, array('test_form' => false));
                    @unlink($tmp);
                    
                    return $upload;
                }
            }
            
            return new WP_Error('file_not_found', 'Media file not found');
        }
        
        // Copy file to uploads directory
        $wp_upload_dir = wp_upload_dir();
        $filename = basename($source_file);
        
        // Ensure unique filename
        $filename = wp_unique_filename($wp_upload_dir['path'], $filename);
        $new_file = $wp_upload_dir['path'] . '/' . $filename;
        
        if (copy($source_file, $new_file)) {
            $filetype = wp_check_filetype($filename);
            
            return array(
                'file' => $new_file,
                'url' => $wp_upload_dir['url'] . '/' . $filename,
                'type' => $filetype['type']
            );
        }
        
        return new WP_Error('upload_failed', 'Failed to copy media file');
    }
    
    /**
     * Import terms
     */
    private function import_terms() {
        $imported = 0;
        $failed = 0;
        
        // Sort terms by parent to ensure parents are created first
        usort($this->import_data['terms'], function($a, $b) {
            return $a['parent'] - $b['parent'];
        });
        
        foreach ($this->import_data['terms'] as $term_data) {
            // Check if term already exists
            $existing_term = term_exists($term_data['slug'], $term_data['taxonomy']);
            
            if ($existing_term) {
                $this->term_mapping[$term_data['term_id']] = $existing_term['term_id'];
                continue;
            }
            
            // Map parent if exists
            $parent = 0;
            if ($term_data['parent'] > 0 && isset($this->term_mapping[$term_data['parent']])) {
                $parent = $this->term_mapping[$term_data['parent']];
            }
            
            // Insert term
            $term_args = array(
                'description' => $term_data['description'],
                'parent' => $parent,
                'slug' => $term_data['slug']
            );
            
            $new_term = wp_insert_term($term_data['name'], $term_data['taxonomy'], $term_args);
            
            if (!is_wp_error($new_term)) {
                $this->term_mapping[$term_data['term_id']] = $new_term['term_id'];
                
                // Import term meta
                if (isset($term_data['meta'])) {
                    foreach ($term_data['meta'] as $meta_key => $meta_value) {
                        update_term_meta($new_term['term_id'], $meta_key, maybe_unserialize($meta_value));
                    }
                }
                
                $imported++;
            } else {
                $failed++;
            }
        }
        
        return array(
            'imported' => $imported,
            'failed' => $failed,
            'total' => count($this->import_data['terms'])
        );
    }
    
    /**
     * Import posts
     */
    private function import_posts() {
        $imported = 0;
        $failed = 0;
        
        // Sort posts by parent to ensure parents are created first
        usort($this->import_data['posts'], function($a, $b) {
            return $a['post_parent'] - $b['post_parent'];
        });
        
        foreach ($this->import_data['posts'] as $post_data) {
            // Skip if already processed
            if (isset($this->post_mapping[$post_data['ID']])) {
                continue;
            }
            
            // Prepare post data
            $post_args = array(
                'post_title' => $post_data['post_title'],
                'post_content' => $post_data['post_content'],
                'post_excerpt' => $post_data['post_excerpt'],
                'post_status' => $post_data['post_status'],
                'post_type' => $post_data['post_type'],
                'post_date' => $post_data['post_date'],
                'post_date_gmt' => $post_data['post_date_gmt'],
                'comment_status' => $post_data['comment_status'],
                'ping_status' => $post_data['ping_status'],
                'post_name' => $post_data['post_name'],
                'post_modified' => $post_data['post_modified'],
                'post_modified_gmt' => $post_data['post_modified_gmt'],
                'menu_order' => $post_data['menu_order'],
                'post_mime_type' => $post_data['post_mime_type'] ?? ''
            );
            
            // Map author
            $post_args['post_author'] = $this->get_mapped_user_id($post_data['post_author']);
            
            // Map parent
            if ($post_data['post_parent'] > 0 && isset($this->post_mapping[$post_data['post_parent']])) {
                $post_args['post_parent'] = $this->post_mapping[$post_data['post_parent']];
            }
            
            // Insert post
            $new_post_id = wp_insert_post($post_args, true);
            
            if (!is_wp_error($new_post_id)) {
                $this->post_mapping[$post_data['ID']] = $new_post_id;
                
                // Import post meta
                if (isset($post_data['meta'])) {
                    foreach ($post_data['meta'] as $meta_key => $meta_value) {
                        // Process special meta values
                        $meta_value = $this->process_meta_value($meta_value);
                        update_post_meta($new_post_id, $meta_key, $meta_value);
                    }
                }
                
                // Import terms
                if (isset($post_data['terms'])) {
                    foreach ($post_data['terms'] as $taxonomy => $terms) {
                        $term_ids = array();
                        foreach ($terms as $term_id) {
                            if (isset($this->term_mapping[$term_id])) {
                                $term_ids[] = intval($this->term_mapping[$term_id]);
                            }
                        }
                        wp_set_object_terms($new_post_id, $term_ids, $taxonomy);
                    }
                }
                
                // Import comments
                if (isset($post_data['comments'])) {
                    $this->import_post_comments($new_post_id, $post_data['comments']);
                }
                
                $imported++;
            } else {
                $failed++;
            }
        }
        
        return array(
            'imported' => $imported,
            'failed' => $failed,
            'total' => count($this->import_data['posts'])
        );
    }
    
    /**
     * Import post comments
     */
    private function import_post_comments($post_id, $comments) {
        foreach ($comments as $comment_data) {
            $comment_args = array(
                'comment_post_ID' => $post_id,
                'comment_author' => $comment_data['comment_author'],
                'comment_author_email' => $comment_data['comment_author_email'],
                'comment_author_url' => $comment_data['comment_author_url'],
                'comment_content' => $comment_data['comment_content'],
                'comment_date' => $comment_data['comment_date'],
                'comment_approved' => $comment_data['comment_approved'],
                'comment_type' => $comment_data['comment_type'] ?? 'comment',
                'user_id' => $this->get_mapped_user_id($comment_data['user_id'] ?? 0)
            );
            
            wp_insert_comment($comment_args);
        }
    }
    
    /**
     * Import menus
     */
    private function import_menus() {
        $imported = 0;
        $failed = 0;
        
        foreach ($this->import_data['menus'] as $menu_data) {
            // Create menu
            $menu_id = wp_create_nav_menu($menu_data['name']);
            
            if (!is_wp_error($menu_id)) {
                $this->menu_mapping[$menu_data['term_id']] = $menu_id;
                
                // Import menu items
                if (isset($menu_data['items'])) {
                    $this->import_menu_items($menu_id, $menu_data['items']);
                }
                
                // Set menu locations
                if (isset($menu_data['locations'])) {
                    $locations = get_theme_mod('nav_menu_locations', array());
                    foreach ($menu_data['locations'] as $location) {
                        $locations[$location] = $menu_id;
                    }
                    set_theme_mod('nav_menu_locations', $locations);
                }
                
                $imported++;
            } else {
                $failed++;
            }
        }
        
        return array(
            'imported' => $imported,
            'failed' => $failed,
            'total' => count($this->import_data['menus'])
        );
    }
    
    /**
     * Import menu items
     */
    private function import_menu_items($menu_id, $items, $parent_id = 0) {
        foreach ($items as $item_data) {
            $menu_item_data = array(
                'menu-item-title' => $item_data['title'],
                'menu-item-status' => 'publish',
                'menu-item-parent-id' => $parent_id,
                'menu-item-position' => $item_data['menu_order'] ?? 0,
                'menu-item-type' => $item_data['type'],
                'menu-item-target' => $item_data['target'] ?? '',
                'menu-item-classes' => $item_data['classes'] ?? '',
                'menu-item-description' => $item_data['description'] ?? '',
                'menu-item-xfn' => $item_data['xfn'] ?? ''
            );
            
            // Set object ID based on type
            switch ($item_data['type']) {
                case 'post_type':
                case 'post_type_archive':
                    if (isset($this->post_mapping[$item_data['object_id']])) {
                        $menu_item_data['menu-item-object-id'] = $this->post_mapping[$item_data['object_id']];
                        $menu_item_data['menu-item-object'] = $item_data['object'];
                    }
                    break;
                    
                case 'taxonomy':
                    if (isset($this->term_mapping[$item_data['object_id']])) {
                        $menu_item_data['menu-item-object-id'] = $this->term_mapping[$item_data['object_id']];
                        $menu_item_data['menu-item-object'] = $item_data['object'];
                    }
                    break;
                    
                case 'custom':
                    $menu_item_data['menu-item-url'] = $item_data['url'];
                    break;
            }
            
            $new_menu_item_id = wp_update_nav_menu_item($menu_id, 0, $menu_item_data);
            
            // Import child items
            if (!is_wp_error($new_menu_item_id) && isset($item_data['children'])) {
                $this->import_menu_items($menu_id, $item_data['children'], $new_menu_item_id);
            }
        }
    }
    
    /**
     * Import widgets
     */
    private function import_widgets() {
        if (!isset($this->import_data['widgets'])) {
            return array('imported' => 0, 'failed' => 0, 'total' => 0);
        }
        
        // Import widget areas
        if (isset($this->import_data['widgets']['sidebars'])) {
            update_option('sidebars_widgets', $this->import_data['widgets']['sidebars']);
        }
        
        // Import widget instances
        if (isset($this->import_data['widgets']['instances'])) {
            foreach ($this->import_data['widgets']['instances'] as $widget_type => $instances) {
                update_option('widget_' . $widget_type, $instances);
            }
        }
        
        return array(
            'imported' => count($this->import_data['widgets']['instances'] ?? array()),
            'failed' => 0,
            'total' => count($this->import_data['widgets']['instances'] ?? array())
        );
    }
    
    /**
     * Process relationships after import
     */
    private function process_relationships() {
        // Update featured images
        $this->update_featured_images();
        
        // Update Elementor data
        $this->update_elementor_data();
        
        // Update other page builders
        $this->update_page_builder_data();
    }
    
    /**
     * Update featured images
     */
    private function update_featured_images() {
        global $wpdb;
        
        $featured_images = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = '_thumbnail_id'"
        );
        
        foreach ($featured_images as $featured) {
            if (isset($this->post_mapping[$featured->meta_value])) {
                update_post_meta(
                    $featured->post_id,
                    '_thumbnail_id',
                    $this->post_mapping[$featured->meta_value]
                );
            }
        }
    }
    
    /**
     * Update Elementor data
     */
    private function update_elementor_data() {
        if (!defined('ELEMENTOR_VERSION')) {
            return;
        }
        
        global $wpdb;
        
        $elementor_data = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = '_elementor_data'"
        );
        
        foreach ($elementor_data as $data) {
            $content = json_decode($data->meta_value, true);
            if ($content) {
                $updated_content = $this->process_elementor_content($content);
                update_post_meta($data->post_id, '_elementor_data', wp_json_encode($updated_content));
            }
        }
    }
    
    /**
     * Process Elementor content
     */
    private function process_elementor_content($elements) {
        foreach ($elements as &$element) {
            // Process image widgets
            if (isset($element['elType']) && $element['elType'] === 'widget' && 
                isset($element['widgetType']) && $element['widgetType'] === 'image') {
                if (isset($element['settings']['image']['id'])) {
                    $old_id = $element['settings']['image']['id'];
                    if (isset($this->post_mapping[$old_id])) {
                        $element['settings']['image']['id'] = $this->post_mapping[$old_id];
                    }
                }
            }
            
            // Process nested elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $element['elements'] = $this->process_elementor_content($element['elements']);
            }
        }
        
        return $elements;
    }
    
    /**
     * Update page builder data
     */
    private function update_page_builder_data() {
        // Handle other page builders like Visual Composer, Beaver Builder, etc.
        do_action('reign_demo_update_page_builder_data', $this->post_mapping, $this->term_mapping);
    }
    
    /**
     * Process meta value
     */
    private function process_meta_value($value) {
        if (is_serialized($value)) {
            $value = unserialize($value);
        }
        
        // Process arrays recursively
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->process_meta_value($val);
            }
        }
        
        // Replace post IDs in strings
        if (is_string($value) && preg_match_all('/\[(\w+).*?\s+id=[\'"]?(\d+)[\'"]?.*?\]/', $value, $matches)) {
            foreach ($matches[2] as $index => $old_id) {
                if (isset($this->post_mapping[$old_id])) {
                    $value = str_replace(
                        $matches[0][$index],
                        str_replace('id="' . $old_id . '"', 'id="' . $this->post_mapping[$old_id] . '"', $matches[0][$index]),
                        $value
                    );
                }
            }
        }
        
        return $value;
    }
    
    /**
     * Get mapped user ID
     */
    private function get_mapped_user_id($old_user_id) {
        if (isset($this->user_mapping[$old_user_id])) {
            return $this->user_mapping[$old_user_id];
        }
        
        // Return preserved admin ID as fallback
        return $this->preserved_admin_id;
    }
    
    /**
     * Get import progress
     */
    public function get_import_progress() {
        return array(
            'users' => count($this->user_mapping),
            'posts' => count($this->post_mapping),
            'terms' => count($this->term_mapping),
            'menus' => count($this->menu_mapping)
        );
    }
}