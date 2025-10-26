<?php
/**
 * Uninstall script for Siege Board Game plugin
 * 
 * This file is executed when the plugin is deleted from WordPress admin.
 * It cleans up all plugin data including database tables, options, and user meta.
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include WordPress database functions
global $wpdb;

/**
 * Remove plugin options
 */
function siege_game_remove_options() {
    global $wpdb;
    
    // Remove plugin settings
    delete_option('siege_game_settings');
    delete_option('siege_game_version');
    delete_option('siege_game_db_version');
    delete_option('siege_game_cache_buster');
    delete_option('siege_game_assets_generated');
    delete_option('siege_game_encryption_key');
    delete_option('siege_game_cleanup_days');
    
    // Remove any options with siege_game_ prefix
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'siege_game_%'");
    
    // Remove any cached data
    delete_transient('siege_game_stats');
    delete_transient('siege_game_leaderboard');
    delete_transient('siege_game_cache');
    delete_transient('siege_game_performance');
    
    // Remove any transients with siege_game_ prefix
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_siege_game_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_siege_game_%'");
    
    // Remove any site options (for multisite)
    if (is_multisite()) {
        delete_site_option('siege_game_settings');
        delete_site_option('siege_game_version');
        delete_site_option('siege_game_db_version');
        
        // Remove site options with siege_game_ prefix
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'siege_game_%'");
    }
}

/**
 * Remove user meta data
 */
function siege_game_remove_user_meta() {
    global $wpdb;
    
    // Remove user meta related to the plugin
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'siege_game_%'");
}

/**
 * Drop plugin database tables
 */
function siege_game_drop_tables() {
    global $wpdb;
    
    // Define table names
    $games_table = $wpdb->prefix . 'siege_games';
    $moves_table = $wpdb->prefix . 'siege_game_moves';
    
    // Drop tables
    $wpdb->query("DROP TABLE IF EXISTS {$moves_table}");
    $wpdb->query("DROP TABLE IF EXISTS {$games_table}");
}

/**
 * Remove uploaded files and directories
 */
function siege_game_remove_uploads() {
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/siege-game/';
    
    if (is_dir($plugin_upload_dir)) {
        siege_game_remove_directory($plugin_upload_dir);
    }
}

/**
 * Recursively remove directory and its contents
 */
function siege_game_remove_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            siege_game_remove_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * Remove scheduled events
 */
function siege_game_remove_scheduled_events() {
    // Remove any scheduled cron events
    wp_clear_scheduled_hook('siege_game_cleanup_expired_games');
    wp_clear_scheduled_hook('siege_game_update_statistics');
    wp_clear_scheduled_hook('siege_game_backup_data');
}

/**
 * Clean up cache and temporary data
 */
function siege_game_cleanup_cache() {
    // Remove object cache
    wp_cache_delete('siege_game_active_games', 'siege_game');
    wp_cache_delete('siege_game_user_stats', 'siege_game');
    wp_cache_delete('siege_game_leaderboard', 'siege_game');
    
    // Remove any temporary files
    $temp_dir = sys_get_temp_dir() . '/siege-game/';
    if (is_dir($temp_dir)) {
        siege_game_remove_directory($temp_dir);
    }
}

/**
 * Log uninstall action
 */
function siege_game_log_uninstall() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Siege Board Game plugin uninstalled at ' . current_time('mysql'));
    }
}

/**
 * Main uninstall function
 */
function siege_game_uninstall() {
    // Check if user has permission to delete plugins
    if (!current_user_can('delete_plugins')) {
        return;
    }
    
    // Verify the uninstall request
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }
    
    try {
        // Log the uninstall action
        siege_game_log_uninstall();
        
        // Remove scheduled events first
        siege_game_remove_scheduled_events();
        
        // Clean up cache and temporary data
        siege_game_cleanup_cache();
        
        // Remove user meta data
        siege_game_remove_user_meta();
        
        // Remove plugin options
        siege_game_remove_options();
        
        // Remove uploaded files
        siege_game_remove_uploads();
        
        // Drop database tables (this should be last)
        siege_game_drop_tables();
        
        // Final cleanup
        wp_cache_flush();
        
    } catch (Exception $e) {
        // Log any errors during uninstall
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Error during Siege Board Game plugin uninstall: ' . $e->getMessage());
        }
    }
}

// Execute uninstall
siege_game_uninstall();

/**
 * Optional: Show confirmation message
 * Note: This won't be displayed in WordPress admin, but can be useful for debugging
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Siege Board Game plugin has been completely uninstalled.');
}

// Clear any remaining cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Clear opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
}
?>