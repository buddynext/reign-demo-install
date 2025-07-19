<?php
/**
 * File Importer Class
 * 
 * Handles importing files from the uploads directory
 * 
 * @package Reign_Demo_Install
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Demo_File_Importer {
    
    private $source_dir;
    private $target_dir;
    private $imported_files = array();
    private $failed_files = array();
    private $total_size = 0;
    private $imported_size = 0;
    
    /**
     * Import files from package
     */
    public function import_files($source_package, $files_manifest = null) {
        // Set up directories
        $this->source_dir = WP_CONTENT_DIR . '/reign-demo-temp/current-import/files/';
        $this->target_dir = wp_upload_dir()['basedir'];
        
        // Extract files if package is provided
        if ($source_package && file_exists($source_package)) {
            $extracted = $this->extract_files_package($source_package);
            if (!$extracted) {
                return new WP_Error('extraction_failed', __('Failed to extract files package', 'reign-demo-install'));
            }
        }
        
        // Load manifest if provided
        $manifest = null;
        if ($files_manifest && file_exists($files_manifest)) {
            $manifest = json_decode(file_get_contents($files_manifest), true);
        }
        
        // Import files
        if ($manifest && isset($manifest['files'])) {
            $result = $this->import_from_manifest($manifest['files']);
        } else {
            $result = $this->import_all_files();
        }
        
        // Clean up temp files
        $this->cleanup_temp_files();
        
        return $result;
    }
    
    /**
     * Extract files package
     */
    private function extract_files_package($package_file) {
        // Ensure WP_Filesystem is available
        WP_Filesystem();
        
        $unzip_result = unzip_file($package_file, $this->source_dir);
        
        if (is_wp_error($unzip_result)) {
            $this->failed_files[] = array(
                'file' => $package_file,
                'error' => $unzip_result->get_error_message()
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Import files from manifest
     */
    private function import_from_manifest($files) {
        $results = array(
            'imported' => 0,
            'failed' => 0,
            'skipped' => 0
        );
        
        foreach ($files as $file_info) {
            $source = $this->source_dir . $file_info['path'];
            $target = $this->target_dir . '/' . $file_info['path'];
            
            // Skip if file doesn't exist in source
            if (!file_exists($source)) {
                $results['skipped']++;
                continue;
            }
            
            // Check if we should skip existing files
            if (file_exists($target)) {
                // Compare checksums if provided
                if (isset($file_info['checksum'])) {
                    $current_checksum = md5_file($target);
                    if ($current_checksum === $file_info['checksum']) {
                        $results['skipped']++;
                        continue;
                    }
                }
            }
            
            // Import the file
            if ($this->import_file($source, $target, $file_info)) {
                $results['imported']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Import all files from directory
     */
    private function import_all_files() {
        $results = array(
            'imported' => 0,
            'failed' => 0,
            'skipped' => 0
        );
        
        if (!is_dir($this->source_dir)) {
            return new WP_Error('source_not_found', __('Source directory not found', 'reign-demo-install'));
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            
            $source = $file->getPathname();
            $relative_path = str_replace($this->source_dir, '', $source);
            $target = $this->target_dir . '/' . $relative_path;
            
            // Import the file
            if ($this->import_file($source, $target)) {
                $results['imported']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Import individual file
     */
    private function import_file($source, $target, $file_info = array()) {
        // Create target directory if needed
        $target_dir = dirname($target);
        if (!is_dir($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Get file size
        $file_size = filesize($source);
        $this->total_size += $file_size;
        
        // Check available disk space
        if (!$this->check_disk_space($file_size)) {
            $this->failed_files[] = array(
                'source' => $source,
                'target' => $target,
                'error' => __('Insufficient disk space', 'reign-demo-install')
            );
            return false;
        }
        
        // Copy the file
        if (copy($source, $target)) {
            // Set proper permissions
            $perms = isset($file_info['permissions']) ? $file_info['permissions'] : 0644;
            @chmod($target, $perms);
            
            // Update imported size
            $this->imported_size += $file_size;
            
            // Track imported file
            $this->imported_files[] = array(
                'source' => $source,
                'target' => $target,
                'size' => $file_size,
                'type' => isset($file_info['type']) ? $file_info['type'] : mime_content_type($source)
            );
            
            // Process special file types
            $this->process_special_files($target, $file_info);
            
            return true;
        } else {
            $this->failed_files[] = array(
                'source' => $source,
                'target' => $target,
                'error' => __('Failed to copy file', 'reign-demo-install')
            );
            return false;
        }
    }
    
    /**
     * Process special file types
     */
    private function process_special_files($file_path, $file_info = array()) {
        $file_type = isset($file_info['type']) ? $file_info['type'] : mime_content_type($file_path);
        
        // Handle BuddyPress/BuddyBoss specific files
        if (strpos($file_path, '/buddypress/') !== false || strpos($file_path, '/buddyboss/') !== false) {
            $this->process_bp_files($file_path, $file_info);
        }
        
        // Handle custom CSS/JS files
        if (in_array($file_type, array('text/css', 'application/javascript'))) {
            $this->update_asset_urls($file_path);
        }
        
        // Handle font files
        if (strpos($file_type, 'font') !== false || preg_match('/\.(woff2?|ttf|otf|eot)$/i', $file_path)) {
            $this->register_font_file($file_path, $file_info);
        }
    }
    
    /**
     * Process BuddyPress files
     */
    private function process_bp_files($file_path, $file_info) {
        // Update BuddyPress file ownership if needed
        if (function_exists('bp_core_avatar_upload_path')) {
            $avatar_path = bp_core_avatar_upload_path();
            if (strpos($file_path, $avatar_path) !== false) {
                // This is an avatar file
                $this->update_bp_avatar_meta($file_path, $file_info);
            }
        }
        
        // Handle group avatars/covers
        if (strpos($file_path, '/group-avatars/') !== false || strpos($file_path, '/group-covers/') !== false) {
            $this->update_bp_group_media($file_path, $file_info);
        }
    }
    
    /**
     * Update asset URLs in CSS/JS files
     */
    private function update_asset_urls($file_path) {
        $content = file_get_contents($file_path);
        if (!$content) {
            return;
        }
        
        $old_url = isset($this->import_data['old_site_url']) ? $this->import_data['old_site_url'] : '';
        $new_url = get_site_url();
        
        if ($old_url && $old_url !== $new_url) {
            $content = str_replace($old_url, $new_url, $content);
            file_put_contents($file_path, $content);
        }
    }
    
    /**
     * Register font file
     */
    private function register_font_file($file_path, $file_info) {
        // Store font file information for theme customization
        $fonts = get_option('reign_demo_imported_fonts', array());
        
        $fonts[] = array(
            'file' => $file_path,
            'url' => str_replace(ABSPATH, site_url('/'), $file_path),
            'family' => isset($file_info['font_family']) ? $file_info['font_family'] : basename($file_path),
            'weight' => isset($file_info['font_weight']) ? $file_info['font_weight'] : 'normal',
            'style' => isset($file_info['font_style']) ? $file_info['font_style'] : 'normal'
        );
        
        update_option('reign_demo_imported_fonts', $fonts);
    }
    
    /**
     * Update BuddyPress avatar meta
     */
    private function update_bp_avatar_meta($file_path, $file_info) {
        if (!isset($file_info['user_id'])) {
            return;
        }
        
        // Map to new user ID
        $user_mapping = get_option('reign_demo_user_mapping', array());
        $new_user_id = isset($user_mapping[$file_info['user_id']]) ? $user_mapping[$file_info['user_id']] : $file_info['user_id'];
        
        // Update user meta if needed
        if (function_exists('bp_get_user_has_avatar')) {
            update_user_meta($new_user_id, 'bp_avatar_upload_time', time());
        }
    }
    
    /**
     * Update BuddyPress group media
     */
    private function update_bp_group_media($file_path, $file_info) {
        if (!isset($file_info['group_id'])) {
            return;
        }
        
        // Map to new group ID
        $group_mapping = get_option('reign_demo_group_mapping', array());
        $new_group_id = isset($group_mapping[$file_info['group_id']]) ? $group_mapping[$file_info['group_id']] : $file_info['group_id'];
        
        // Update group meta if needed
        if (function_exists('groups_update_groupmeta')) {
            if (strpos($file_path, '/group-avatars/') !== false) {
                groups_update_groupmeta($new_group_id, 'bp_group_avatar_upload_time', time());
            } elseif (strpos($file_path, '/group-covers/') !== false) {
                groups_update_groupmeta($new_group_id, 'bp_group_cover_image_upload_time', time());
            }
        }
    }
    
    /**
     * Check available disk space
     */
    private function check_disk_space($required_bytes) {
        $free_space = @disk_free_space(ABSPATH);
        
        if ($free_space === false) {
            // Can't determine free space, allow operation
            return true;
        }
        
        // Keep at least 100MB free after operation
        $buffer = 100 * 1024 * 1024;
        
        return ($free_space - $required_bytes) > $buffer;
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanup_temp_files() {
        if (is_dir($this->source_dir)) {
            $this->delete_directory($this->source_dir);
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
     * Get import statistics
     */
    public function get_import_stats() {
        return array(
            'imported_files' => count($this->imported_files),
            'failed_files' => count($this->failed_files),
            'total_size' => $this->format_bytes($this->total_size),
            'imported_size' => $this->format_bytes($this->imported_size),
            'failed_list' => $this->failed_files
        );
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
    
    /**
     * Restore file permissions
     */
    public function restore_file_permissions() {
        $upload_dir = wp_upload_dir();
        
        // Set proper permissions for upload directory
        $this->set_directory_permissions($upload_dir['basedir'], 0755, 0644);
        
        // Set permissions for BuddyPress directories
        if (defined('BP_AVATAR_UPLOAD_PATH')) {
            $this->set_directory_permissions(BP_AVATAR_UPLOAD_PATH, 0755, 0644);
        }
    }
    
    /**
     * Set directory permissions recursively
     */
    private function set_directory_permissions($path, $dir_perms = 0755, $file_perms = 0644) {
        if (!is_dir($path)) {
            return;
        }
        
        // Set directory permission
        @chmod($path, $dir_perms);
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @chmod($item->getPathname(), $dir_perms);
            } else {
                @chmod($item->getPathname(), $file_perms);
            }
        }
    }
}