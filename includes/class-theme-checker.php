<?php
/**
 * Theme Checker Utility Class
 * 
 * Provides centralized functionality to check if Reign theme is active
 * 
 * @package Reign_Demo_Install
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Reign_Theme_Checker {
    
    /**
     * Check if Reign theme is currently active
     * 
     * This method checks multiple identifiers to determine if any variant
     * of the Reign theme is active, including:
     * - Theme name containing 'reign'
     * - Theme template being 'reign' or 'reign-theme'
     * - Theme stylesheet being 'reign' or 'reign-theme'
     * 
     * @since 1.0.0
     * @return bool True if Reign theme is active, false otherwise
     */
    public static function is_reign_theme_active() {
        // Get current theme information
        $theme = wp_get_theme();
        
        // Get theme properties in lowercase for case-insensitive comparison
        $theme_name = strtolower($theme->get('Name'));
        $theme_template = strtolower($theme->get('Template'));
        $theme_stylesheet = strtolower($theme->get_stylesheet());
        
        // Check for various reign theme identifiers
        $is_reign = (
            strpos($theme_name, 'reign') !== false || 
            $theme_template === 'reign' || 
            $theme_template === 'reign-theme' ||
            $theme_stylesheet === 'reign' ||
            $theme_stylesheet === 'reign-theme'
        );
        
        /**
         * Filter the result of Reign theme check
         * 
         * @since 1.0.0
         * @param bool $is_reign Whether Reign theme is detected as active
         * @param WP_Theme $theme The current theme object
         */
        return apply_filters('reign_theme_checker_is_active', $is_reign, $theme);
    }
    
    /**
     * Get the current theme information
     * 
     * Returns an array with theme details for debugging purposes
     * 
     * @since 1.0.0
     * @return array Theme information array
     */
    public static function get_theme_info() {
        $theme = wp_get_theme();
        
        return array(
            'name' => $theme->get('Name'),
            'template' => $theme->get('Template'),
            'stylesheet' => $theme->get_stylesheet(),
            'version' => $theme->get('Version'),
            'is_reign' => self::is_reign_theme_active()
        );
    }
    
    /**
     * Get the error message for when Reign theme is not active
     * 
     * @since 1.0.0
     * @param string $context The context where the error is being displayed
     * @return string The error message
     */
    public static function get_inactive_message($context = 'default') {
        $messages = array(
            'default' => __('Reign theme must be active to use this feature.', 'reign-demo-install'),
            'demo_install' => __('Reign theme must be active to import demos.', 'reign-demo-install'),
            'admin_notice' => __('Reign Demo Install requires the Reign theme to be active. Please activate Reign theme first.', 'reign-demo-install'),
            'requirements' => __('Reign theme must be active to use demo installer. Please activate Reign theme first.', 'reign-demo-install')
        );
        
        $message = isset($messages[$context]) ? $messages[$context] : $messages['default'];
        
        /**
         * Filter the inactive theme message
         * 
         * @since 1.0.0
         * @param string $message The error message
         * @param string $context The context where the message is displayed
         */
        return apply_filters('reign_theme_checker_inactive_message', $message, $context);
    }
}