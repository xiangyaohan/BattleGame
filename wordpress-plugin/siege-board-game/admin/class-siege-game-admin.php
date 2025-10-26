<?php
/**
 * 管理后台类
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siege_Game_Admin {
    
    /**
     * 初始化管理后台
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
    }
    
    /**
     * 添加管理菜单
     */
    public static function add_admin_menu() {
        add_menu_page(
            __('攻城掠地桌游', 'siege-board-game'),
            __('攻城掠地', 'siege-board-game'),
            'manage_options',
            'siege-board-game',
            array(__CLASS__, 'admin_page'),
            'dashicons-games',
            30
        );
        
        add_submenu_page(
            'siege-board-game',
            __('游戏设置', 'siege-board-game'),
            __('设置', 'siege-board-game'),
            'manage_options',
            'siege-board-game-settings',
            array(__CLASS__, 'settings_page')
        );
        
        add_submenu_page(
            'siege-board-game',
            __('游戏记录', 'siege-board-game'),
            __('游戏记录', 'siege-board-game'),
            'manage_options',
            'siege-board-game-records',
            array(__CLASS__, 'records_page')
        );
        
        add_submenu_page(
            'siege-board-game',
            __('游戏统计', 'siege-board-game'),
            __('统计', 'siege-board-game'),
            'manage_options',
            'siege-board-game-stats',
            array(__CLASS__, 'stats_page')
        );
    }
    
    /**
     * 注册设置
     */
    public static function register_settings() {
        register_setting('siege_game_settings', 'siege_game_ai_enabled');
        register_setting('siege_game_settings', 'siege_game_save_games');
        register_setting('siege_game_settings', 'siege_game_default_difficulty');
        register_setting('siege_game_settings', 'siege_game_max_games_per_user');
        register_setting('siege_game_settings', 'siege_game_game_timeout');
        register_setting('siege_game_settings', 'siege_game_enable_sound');
        register_setting('siege_game_settings', 'siege_game_enable_animations');
        register_setting('siege_game_settings', 'siege_game_auto_cleanup');
        register_setting('siege_game_settings', 'siege_game_cleanup_days');
    }
    
    /**
     * 加载管理后台脚本
     */
    public static function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'siege-board-game') !== false) {
            wp_enqueue_style(
                'siege-game-admin-style',
                SIEGE_GAME_PLUGIN_URL . 'admin/css/admin.css',
                array(),
                SIEGE_GAME_VERSION
            );
            
            wp_enqueue_script(
                'siege-game-admin-script',
                SIEGE_GAME_PLUGIN_URL . 'admin/js/admin.js',
                array('jquery', 'wp-color-picker'),
                SIEGE_GAME_VERSION,
                true
            );
            
            wp_enqueue_style('wp-color-picker');
            
            wp_localize_script('siege-game-admin-script', 'siegeGameAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('siege_game_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('确定要删除这个游戏记录吗？', 'siege-board-game'),
                    'confirm_cleanup' => __('确定要清理过期的游戏记录吗？', 'siege-board-game'),
                    'cleanup_success' => __('清理完成', 'siege-board-game'),
                    'cleanup_error' => __('清理失败', 'siege-board-game')
                )
            ));
        }
    }
    
    /**
     * 主管理页面
     */
    public static function admin_page() {
        // 获取统计数据
        $total_games = self::get_total_games();
        $active_games = self::get_active_games();
        $total_users = self::get_total_users_with_games();
        $recent_games = self::get_recent_games(10);
        
        include SIEGE_GAME_PLUGIN_PATH . 'admin/views/main-page.php';
    }
    
    /**
     * 设置页面
     */
    public static function settings_page() {
        if (isset($_POST['submit'])) {
            // 处理设置保存
            self::save_settings();
            echo '<div class="notice notice-success"><p>' . __('设置已保存', 'siege-board-game') . '</p></div>';
        }
        
        include SIEGE_GAME_PLUGIN_PATH . 'admin/views/settings-page.php';
    }
    
    /**
     * 记录页面
     */
    public static function records_page() {
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // 处理删除操作
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['game_id'])) {
            if (wp_verify_nonce($_POST['_wpnonce'], 'delete_game_' . $_POST['game_id'])) {
                Siege_Game_Database::delete_game(intval($_POST['game_id']));
                echo '<div class="notice notice-success"><p>' . __('游戏记录已删除', 'siege-board-game') . '</p></div>';
            }
        }
        
        // 处理批量清理
        if (isset($_POST['action']) && $_POST['action'] === 'cleanup') {
            if (wp_verify_nonce($_POST['_wpnonce'], 'cleanup_games')) {
                $cleanup_days = get_option('siege_game_cleanup_days', 30);
                $deleted = Siege_Game_Database::cleanup_expired_games($cleanup_days * 24);
                echo '<div class="notice notice-success"><p>' . sprintf(__('已清理 %d 个过期游戏记录', 'siege-board-game'), $deleted) . '</p></div>';
            }
        }
        
        $games = self::get_all_games($per_page, $offset);
        $total_games = self::get_total_games();
        $total_pages = ceil($total_games / $per_page);
        
        include SIEGE_GAME_PLUGIN_PATH . 'admin/views/records-page.php';
    }
    
    /**
     * 统计页面
     */
    public static function stats_page() {
        $stats = self::get_comprehensive_stats();
        include SIEGE_GAME_PLUGIN_PATH . 'admin/views/stats-page.php';
    }
    
    /**
     * 保存设置
     */
    private static function save_settings() {
        $settings = array(
            'siege_game_ai_enabled' => isset($_POST['siege_game_ai_enabled']) ? 1 : 0,
            'siege_game_save_games' => isset($_POST['siege_game_save_games']) ? 1 : 0,
            'siege_game_default_difficulty' => sanitize_text_field($_POST['siege_game_default_difficulty']),
            'siege_game_max_games_per_user' => intval($_POST['siege_game_max_games_per_user']),
            'siege_game_game_timeout' => intval($_POST['siege_game_game_timeout']),
            'siege_game_enable_sound' => isset($_POST['siege_game_enable_sound']) ? 1 : 0,
            'siege_game_enable_animations' => isset($_POST['siege_game_enable_animations']) ? 1 : 0,
            'siege_game_auto_cleanup' => isset($_POST['siege_game_auto_cleanup']) ? 1 : 0,
            'siege_game_cleanup_days' => intval($_POST['siege_game_cleanup_days'])
        );
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
    }
    
    /**
     * 获取总游戏数
     */
    private static function get_total_games() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siege_games';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
    
    /**
     * 获取活跃游戏数
     */
    private static function get_active_games() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siege_games';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE finished_at IS NULL");
    }
    
    /**
     * 获取有游戏记录的用户数
     */
    private static function get_total_users_with_games() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siege_games';
        return $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name");
    }
    
    /**
     * 获取最近的游戏
     */
    private static function get_recent_games($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siege_games';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT g.*, u.display_name 
             FROM $table_name g 
             LEFT JOIN {$wpdb->users} u ON g.user_id = u.ID 
             ORDER BY g.started_at DESC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * 获取所有游戏（分页）
     */
    private static function get_all_games($per_page, $offset) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siege_games';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT g.*, u.display_name 
             FROM $table_name g 
             LEFT JOIN {$wpdb->users} u ON g.user_id = u.ID 
             ORDER BY g.started_at DESC 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
    }
    
    /**
     * 获取综合统计数据
     */
    private static function get_comprehensive_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siege_games';
        
        // 基本统计
        $basic_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_games,
                COUNT(CASE WHEN finished_at IS NOT NULL THEN 1 END) as completed_games,
                COUNT(CASE WHEN finished_at IS NULL THEN 1 END) as active_games,
                COUNT(DISTINCT user_id) as total_players,
                AVG(moves_count) as avg_moves,
                AVG(TIMESTAMPDIFF(MINUTE, started_at, finished_at)) as avg_duration
             FROM $table_name"
        );
        
        // 胜负统计
        $win_stats = $wpdb->get_results(
            "SELECT 
                game_result,
                COUNT(*) as count
             FROM $table_name 
             WHERE game_result IS NOT NULL
             GROUP BY game_result"
        );
        
        // 难度统计
        $difficulty_stats = $wpdb->get_results(
            "SELECT 
                ai_difficulty,
                COUNT(*) as count,
                AVG(moves_count) as avg_moves
             FROM $table_name 
             GROUP BY ai_difficulty"
        );
        
        // 每日游戏统计（最近30天）
        $daily_stats = $wpdb->get_results(
            "SELECT 
                DATE(started_at) as game_date,
                COUNT(*) as games_count
             FROM $table_name 
             WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(started_at)
             ORDER BY game_date DESC"
        );
        
        // 最活跃用户
        $top_players = $wpdb->get_results(
            "SELECT 
                u.display_name,
                COUNT(*) as games_played,
                SUM(CASE WHEN game_result = 'win' THEN 1 ELSE 0 END) as wins,
                AVG(moves_count) as avg_moves
             FROM $table_name g
             LEFT JOIN {$wpdb->users} u ON g.user_id = u.ID
             GROUP BY g.user_id
             ORDER BY games_played DESC
             LIMIT 10"
        );
        
        return array(
            'basic' => $basic_stats,
            'wins' => $win_stats,
            'difficulty' => $difficulty_stats,
            'daily' => $daily_stats,
            'top_players' => $top_players
        );
    }
}

// 初始化管理后台
Siege_Game_Admin::init();