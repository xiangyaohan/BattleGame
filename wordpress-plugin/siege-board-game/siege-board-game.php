<?php
/**
 * Plugin Name: 攻城掠地桌游
 * Plugin URI: https://github.com/your-username/siege-board-game
 * Description: 一个完整的攻城掠地桌游WordPress插件，支持AI对战、多人游戏和游戏记录保存。
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: siege-board-game
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('SIEGE_GAME_VERSION', '1.0.0');
define('SIEGE_GAME_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SIEGE_GAME_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SIEGE_GAME_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * 主插件类
 */
class SiegeBoardGame {
    
    /**
     * 单例实例
     */
    private static $instance = null;
    
    /**
     * 获取单例实例
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 构造函数
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * 初始化钩子
     */
    private function init_hooks() {
        // 插件激活和停用钩子
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        // Uninstall hook is handled in uninstall.php file
        
        // WordPress钩子
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // AJAX钩子由 Siege_Game_Ajax 类处理
        
        // 短代码
        add_shortcode('siege_game', array($this, 'render_game_shortcode'));
        
        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * 插件初始化
     */
    public function init() {
        // 加载文本域
        load_plugin_textdomain('siege-board-game', false, dirname(SIEGE_GAME_PLUGIN_BASENAME) . '/languages');
        
        // 包含必要的文件
        $this->include_files();
    }
    
    /**
     * 包含必要的文件
     */
    private function include_files() {
        require_once SIEGE_GAME_PLUGIN_PATH . 'includes/class-siege-game-database.php';
        require_once SIEGE_GAME_PLUGIN_PATH . 'includes/class-siege-game-ajax.php';
        require_once SIEGE_GAME_PLUGIN_PATH . 'includes/class-siege-game-shortcode.php';
        require_once SIEGE_GAME_PLUGIN_PATH . 'includes/class-siege-game-security.php';
        require_once SIEGE_GAME_PLUGIN_PATH . 'includes/class-siege-game-performance.php';
        require_once SIEGE_GAME_PLUGIN_PATH . 'admin/class-siege-game-admin.php';
    }
    
    /**
     * 前端脚本和样式
     */
    public function enqueue_scripts() {
        // 只在包含短代码的页面加载
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'siege_game')) {
            wp_enqueue_style(
                'siege-game-style',
                SIEGE_GAME_PLUGIN_URL . 'css/siege-game.css',
                array(),
                SIEGE_GAME_VERSION
            );
            
            wp_enqueue_script(
                'siege-game-script',
                SIEGE_GAME_PLUGIN_URL . 'js/siege-game.js',
                array('jquery'),
                SIEGE_GAME_VERSION,
                true
            );
            
            // 本地化脚本
            wp_localize_script('siege-game-script', 'siegeGameAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('siege_game_nonce'),
                'strings' => array(
                    'loading' => __('加载中...', 'siege-board-game'),
                    'error' => __('发生错误，请重试', 'siege-board-game'),
                    'game_over' => __('游戏结束', 'siege-board-game'),
                    'your_turn' => __('轮到你了', 'siege-board-game'),
                    'ai_thinking' => __('AI思考中...', 'siege-board-game')
                )
            ));
        }
    }
    
    /**
     * 管理后台脚本和样式
     */
    public function admin_enqueue_scripts($hook) {
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
                array('jquery'),
                SIEGE_GAME_VERSION,
                true
            );
        }
    }
    
    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_menu_page(
            __('攻城掠地桌游', 'siege-board-game'),
            __('攻城掠地', 'siege-board-game'),
            'manage_options',
            'siege-board-game',
            array($this, 'admin_page'),
            'dashicons-games',
            30
        );
        
        add_submenu_page(
            'siege-board-game',
            __('游戏设置', 'siege-board-game'),
            __('设置', 'siege-board-game'),
            'manage_options',
            'siege-board-game-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'siege-board-game',
            __('游戏记录', 'siege-board-game'),
            __('游戏记录', 'siege-board-game'),
            'manage_options',
            'siege-board-game-records',
            array($this, 'records_page')
        );
    }
    
    /**
     * 主管理页面
     */
    public function admin_page() {
        include SIEGE_GAME_PLUGIN_PATH . 'admin/views/main-page.php';
    }
    
    /**
     * 设置页面
     */
    public function settings_page() {
        include SIEGE_GAME_PLUGIN_PATH . 'admin/views/settings-page.php';
    }
    
    /**
     * 记录页面
     */
    public function records_page() {
        include SIEGE_GAME_PLUGIN_PATH . 'admin/views/records-page.php';
    }
    
    /**
     * 渲染游戏短代码
     */
    public function render_game_shortcode($atts) {
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '600px',
            'ai_enabled' => 'true',
            'save_games' => 'true',
            'theme' => 'default'
        ), $atts, 'siege_game');
        
        ob_start();
        include SIEGE_GAME_PLUGIN_PATH . 'includes/views/game-board.php';
        return ob_get_clean();
    }
    
    // AJAX处理方法已移至 Siege_Game_Ajax 类
    
    /**
     * 注册REST API路由
     */
    public function register_rest_routes() {
        register_rest_route('siege-game/v1', '/game/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_game_state'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('siege-game/v1', '/game/(?P<id>\d+)/move', array(
            'methods' => 'POST',
            'callback' => array($this, 'make_move_api'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    /**
     * 插件激活
     */
    public function activate() {
        // 创建数据库表
        $this->create_database_tables();
        
        // 设置默认选项
        $this->set_default_options();
        
        // 刷新重写规则
        flush_rewrite_rules();
    }
    
    /**
     * 插件停用
     */
    public function deactivate() {
        // 清理临时数据
        $this->cleanup_temp_data();
        
        // 刷新重写规则
        flush_rewrite_rules();
    }
    
    /**
     * 插件卸载
     */
    public static function uninstall() {
        // 删除数据库表
        self::drop_database_tables();
        
        // 删除选项
        self::delete_plugin_options();
    }
    
    /**
     * 创建数据库表
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 游戏记录表
        $table_name = $wpdb->prefix . 'siege_games';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            game_state longtext NOT NULL,
            game_result varchar(20) DEFAULT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            finished_at datetime DEFAULT NULL,
            moves_count int(11) DEFAULT 0,
            ai_difficulty varchar(20) DEFAULT 'medium',
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // 游戏移动记录表
        $table_name = $wpdb->prefix . 'siege_game_moves';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            game_id mediumint(9) NOT NULL,
            move_number int(11) NOT NULL,
            player varchar(20) NOT NULL,
            move_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY game_id (game_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * 设置默认选项
     */
    private function set_default_options() {
        $default_options = array(
            'ai_enabled' => true,
            'save_games' => true,
            'default_difficulty' => 'medium',
            'max_games_per_user' => 10,
            'game_timeout' => 3600, // 1小时
            'enable_sound' => true,
            'enable_animations' => true
        );
        
        foreach ($default_options as $key => $value) {
            add_option('siege_game_' . $key, $value);
        }
    }
    
    /**
     * 游戏逻辑处理（占位符）
     */
    private function process_game_move($move_data) {
        // 这里将包含实际的游戏逻辑
        return array(
            'success' => true,
            'new_state' => array(),
            'ai_move' => array(),
            'game_over' => false
        );
    }
    
    /**
     * 初始化新游戏（占位符）
     */
    private function initialize_new_game($settings) {
        // 这里将包含游戏初始化逻辑
        return array(
            'board' => array(),
            'current_player' => 'human',
            'game_id' => wp_generate_uuid4()
        );
    }
    
    /**
     * 权限检查
     */
    public function check_permissions() {
        return current_user_can('read');
    }
    
    /**
     * 清理临时数据
     */
    private function cleanup_temp_data() {
        // 清理过期的游戏会话
        global $wpdb;
        $table_name = $wpdb->prefix . 'siege_games';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE finished_at IS NULL AND started_at < %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
    }
    
    /**
     * 删除数据库表
     */
    private static function drop_database_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}siege_games");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}siege_game_moves");
    }
    
    /**
     * 删除插件选项
     */
    private static function delete_plugin_options() {
        $options = array(
            'siege_game_ai_enabled',
            'siege_game_save_games',
            'siege_game_default_difficulty',
            'siege_game_max_games_per_user',
            'siege_game_game_timeout',
            'siege_game_enable_sound',
            'siege_game_enable_animations'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
    }
}

// 初始化插件
function siege_board_game_init() {
    return SiegeBoardGame::get_instance();
}

// 启动插件
add_action('plugins_loaded', 'siege_board_game_init');

// 便利函数
function siege_game() {
    return SiegeBoardGame::get_instance();
}