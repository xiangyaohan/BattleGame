<?php
/**
 * Performance Optimization Class
 * 
 * Handles caching, optimization, and performance improvements for the Siege Board Game plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siege_Game_Performance {
    
    private static $cache_group = 'siege_game';
    private static $cache_expiry = 3600; // 1 hour
    
    /**
     * Initialize performance optimizations
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'setup_caching'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'optimize_assets'), 20);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'optimize_admin_assets'), 20);
        add_filter('siege_game_board_data', array(__CLASS__, 'cache_board_data'));
        add_action('siege_game_move_made', array(__CLASS__, 'invalidate_game_cache'));
        add_action('wp_footer', array(__CLASS__, 'add_preload_hints'));
    }
    
    /**
     * Setup caching mechanisms
     */
    public static function setup_caching() {
        // Enable object caching for game data
        wp_cache_add_global_groups(array(self::$cache_group));
        
        // Setup database query caching
        add_filter('posts_pre_query', array(__CLASS__, 'cache_game_queries'), 10, 2);
    }
    
    /**
     * Optimize frontend assets
     */
    public static function optimize_assets() {
        global $post;
        
        // Only load on pages with the shortcode
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'siege_game')) {
            return;
        }
        
        // Minify and combine CSS
        self::enqueue_optimized_css();
        
        // Minify and combine JavaScript
        self::enqueue_optimized_js();
        
        // Add resource hints
        self::add_resource_hints();
    }
    
    /**
     * Optimize admin assets
     */
    public static function optimize_admin_assets($hook) {
        if (strpos($hook, 'siege-board-game') === false) {
            return;
        }
        
        // Combine admin CSS
        wp_dequeue_style('siege-game-admin-style');
        wp_enqueue_style(
            'siege-game-admin-optimized',
            SIEGE_GAME_PLUGIN_URL . 'admin/css/admin-optimized.css',
            array(),
            SIEGE_GAME_VERSION . '.' . self::get_cache_buster()
        );
        
        // Combine admin JS
        wp_dequeue_script('siege-game-admin-script');
        wp_enqueue_script(
            'siege-game-admin-optimized',
            SIEGE_GAME_PLUGIN_URL . 'admin/js/admin-optimized.js',
            array('jquery'),
            SIEGE_GAME_VERSION . '.' . self::get_cache_buster(),
            true
        );
    }
    
    /**
     * Enqueue optimized CSS
     */
    private static function enqueue_optimized_css() {
        // Check if optimized CSS exists
        $optimized_css = SIEGE_GAME_PLUGIN_PATH . 'css/siege-game-optimized.css';
        
        if (!file_exists($optimized_css) || self::should_regenerate_assets()) {
            self::generate_optimized_css();
        }
        
        wp_dequeue_style('siege-game-style');
        wp_enqueue_style(
            'siege-game-optimized',
            SIEGE_GAME_PLUGIN_URL . 'css/siege-game-optimized.css',
            array(),
            SIEGE_GAME_VERSION . '.' . self::get_cache_buster()
        );
    }
    
    /**
     * Enqueue optimized JavaScript
     */
    private static function enqueue_optimized_js() {
        // Check if optimized JS exists
        $optimized_js = SIEGE_GAME_PLUGIN_PATH . 'js/siege-game-optimized.js';
        
        if (!file_exists($optimized_js) || self::should_regenerate_assets()) {
            self::generate_optimized_js();
        }
        
        wp_dequeue_script('siege-game-script');
        wp_enqueue_script(
            'siege-game-optimized',
            SIEGE_GAME_PLUGIN_URL . 'js/siege-game-optimized.js',
            array('jquery'),
            SIEGE_GAME_VERSION . '.' . self::get_cache_buster(),
            true
        );
    }
    
    /**
     * Generate optimized CSS
     */
    private static function generate_optimized_css() {
        $css_files = array(
            SIEGE_GAME_PLUGIN_PATH . 'css/siege-game.css'
        );
        
        $combined_css = '';
        foreach ($css_files as $file) {
            if (file_exists($file)) {
                $css_content = file_get_contents($file);
                $combined_css .= self::minify_css($css_content) . "\n";
            }
        }
        
        // Add critical CSS inline
        $combined_css = self::add_critical_css() . $combined_css;
        
        file_put_contents(
            SIEGE_GAME_PLUGIN_PATH . 'css/siege-game-optimized.css',
            $combined_css
        );
    }
    
    /**
     * Generate optimized JavaScript
     */
    private static function generate_optimized_js() {
        $js_files = array(
            SIEGE_GAME_PLUGIN_PATH . 'js/siege-game.js'
        );
        
        $combined_js = '';
        foreach ($js_files as $file) {
            if (file_exists($file)) {
                $js_content = file_get_contents($file);
                $combined_js .= self::minify_js($js_content) . "\n";
            }
        }
        
        file_put_contents(
            SIEGE_GAME_PLUGIN_PATH . 'js/siege-game-optimized.js',
            $combined_js
        );
    }
    
    /**
     * Minify CSS
     */
    private static function minify_css($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
        
        // Remove unnecessary spaces
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/;\s*}/', '}', $css);
        $css = preg_replace('/\s*{\s*/', '{', $css);
        $css = preg_replace('/;\s*/', ';', $css);
        $css = preg_replace('/:\s*/', ':', $css);
        
        return trim($css);
    }
    
    /**
     * Minify JavaScript
     */
    private static function minify_js($js) {
        // Remove single line comments
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remove extra whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove unnecessary spaces around operators
        $js = preg_replace('/\s*([{}();,:])\s*/', '$1', $js);
        
        return trim($js);
    }
    
    /**
     * Add critical CSS
     */
    private static function add_critical_css() {
        return '
        .siege-game-container{font-family:Inter,system-ui,sans-serif;background:#F5F5DC;border-radius:8px;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);overflow:hidden;position:relative}
        .siege-game-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;height:400px;background:rgba(255,255,255,.9)}
        .loading-spinner{width:40px;height:40px;border:4px solid #f3f3f3;border-top:4px solid #8B0000;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:16px}
        @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
        ';
    }
    
    /**
     * Add resource hints
     */
    private static function add_resource_hints() {
        // Preload critical resources
        echo '<link rel="preload" href="' . SIEGE_GAME_PLUGIN_URL . 'css/siege-game-optimized.css" as="style">';
        echo '<link rel="preload" href="' . SIEGE_GAME_PLUGIN_URL . 'js/siege-game-optimized.js" as="script">';
        
        // DNS prefetch for external resources
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">';
    }
    
    /**
     * Add preload hints in footer
     */
    public static function add_preload_hints() {
        global $post;
        
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'siege_game')) {
            return;
        }
        
        echo '<script>
        // Preload next likely resources
        if ("requestIdleCallback" in window) {
            requestIdleCallback(function() {
                var link = document.createElement("link");
                link.rel = "prefetch";
                link.href = "' . admin_url('admin-ajax.php') . '";
                document.head.appendChild(link);
            });
        }
        </script>';
    }
    
    /**
     * Cache board data
     */
    public static function cache_board_data($board_data) {
        $cache_key = 'board_data_' . md5(serialize($board_data));
        
        $cached_data = wp_cache_get($cache_key, self::$cache_group);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Process board data
        $processed_data = self::process_board_data($board_data);
        
        wp_cache_set($cache_key, $processed_data, self::$cache_group, self::$cache_expiry);
        
        return $processed_data;
    }
    
    /**
     * Process board data for optimization
     */
    private static function process_board_data($board_data) {
        // Optimize board data structure
        if (!is_array($board_data)) {
            return array();
        }
        
        // Remove unnecessary data
        $optimized_data = array();
        foreach ($board_data as $key => $value) {
            if (!empty($value) && $value !== null) {
                $optimized_data[$key] = $value;
            }
        }
        
        return $optimized_data;
    }
    
    /**
     * Cache database queries
     */
    public static function cache_game_queries($posts, $query) {
        if (!$query->is_main_query() || !isset($query->query_vars['siege_game_query'])) {
            return $posts;
        }
        
        $cache_key = 'game_query_' . md5(serialize($query->query_vars));
        $cached_posts = wp_cache_get($cache_key, self::$cache_group);
        
        if ($cached_posts !== false) {
            return $cached_posts;
        }
        
        return $posts;
    }
    
    /**
     * Invalidate game cache
     */
    public static function invalidate_game_cache($game_id) {
        // Clear specific game cache
        wp_cache_delete('game_data_' . $game_id, self::$cache_group);
        wp_cache_delete('game_moves_' . $game_id, self::$cache_group);
        
        // Clear related caches
        wp_cache_flush_group(self::$cache_group);
    }
    
    /**
     * Get cache buster
     */
    private static function get_cache_buster() {
        return get_option('siege_game_cache_buster', time());
    }
    
    /**
     * Should regenerate assets
     */
    private static function should_regenerate_assets() {
        $last_generated = get_option('siege_game_assets_generated', 0);
        $source_modified = max(
            filemtime(SIEGE_GAME_PLUGIN_PATH . 'css/siege-game.css'),
            filemtime(SIEGE_GAME_PLUGIN_PATH . 'js/siege-game.js')
        );
        
        return $source_modified > $last_generated;
    }
    
    /**
     * Lazy load images
     */
    public static function lazy_load_images($content) {
        if (strpos($content, 'siege-game') === false) {
            return $content;
        }
        
        // Add lazy loading to images
        $content = preg_replace(
            '/<img([^>]+)src=/i',
            '<img$1loading="lazy" src=',
            $content
        );
        
        return $content;
    }
    
    /**
     * Optimize database queries
     */
    public static function optimize_database() {
        global $wpdb;
        
        // Add indexes if they don't exist
        $indexes = array(
            "ALTER TABLE {$wpdb->prefix}siege_games ADD INDEX idx_user_status (user_id, status)",
            "ALTER TABLE {$wpdb->prefix}siege_games ADD INDEX idx_created_at (created_at)",
            "ALTER TABLE {$wpdb->prefix}siege_game_moves ADD INDEX idx_game_move (game_id, move_number)"
        );
        
        foreach ($indexes as $index_sql) {
            $wpdb->query($index_sql);
        }
    }
    
    /**
     * Enable compression
     */
    public static function enable_compression() {
        if (!headers_sent() && extension_loaded('zlib')) {
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
                ob_start('ob_gzhandler');
            }
        }
    }
    
    /**
     * Get performance metrics
     */
    public static function get_performance_metrics() {
        return array(
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cache_hits' => wp_cache_get('cache_hits', self::$cache_group) ?: 0,
            'cache_misses' => wp_cache_get('cache_misses', self::$cache_group) ?: 0,
            'query_count' => get_num_queries(),
            'load_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        );
    }
    
    /**
     * Clear all caches
     */
    public static function clear_all_caches() {
        // Clear object cache
        wp_cache_flush_group(self::$cache_group);
        
        // Clear transients
        delete_transient('siege_game_stats');
        delete_transient('siege_game_leaderboard');
        
        // Clear optimized assets
        $optimized_files = array(
            SIEGE_GAME_PLUGIN_PATH . 'css/siege-game-optimized.css',
            SIEGE_GAME_PLUGIN_PATH . 'js/siege-game-optimized.js'
        );
        
        foreach ($optimized_files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Update cache buster
        update_option('siege_game_cache_buster', time());
        
        return true;
    }
}

// Initialize performance optimizations
Siege_Game_Performance::init()