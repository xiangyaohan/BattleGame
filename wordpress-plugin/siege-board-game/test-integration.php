<?php
/**
 * WordPress Integration Test File
 * 
 * This file can be used to test the plugin integration
 * Place this in your WordPress root directory and access via browser
 */

// Load WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Check if plugin is active
if (!is_plugin_active('siege-board-game/siege-board-game.php')) {
    die('Siege Board Game plugin is not active!');
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>攻城掠地桌游 - WordPress集成测试</title>
    <?php wp_head(); ?>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f1f1f1;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .test-section h2 {
            margin-top: 0;
            color: #2271b1;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .game-container {
            margin: 20px 0;
            border: 2px solid #2271b1;
            border-radius: 8px;
            padding: 10px;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border-left: 4px solid #2271b1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>攻城掠地桌游 - WordPress集成测试</h1>
        
        <!-- Plugin Status -->
        <div class="test-section">
            <h2>插件状态检查</h2>
            <?php
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/siege-board-game/siege-board-game.php');
            if ($plugin_data['Name']) {
                echo '<div class="status success">✓ 插件已正确加载</div>';
                echo '<p><strong>插件名称:</strong> ' . esc_html($plugin_data['Name']) . '</p>';
                echo '<p><strong>版本:</strong> ' . esc_html($plugin_data['Version']) . '</p>';
                echo '<p><strong>描述:</strong> ' . esc_html($plugin_data['Description']) . '</p>';
            } else {
                echo '<div class="status error">✗ 插件加载失败</div>';
            }
            ?>
        </div>
        
        <!-- Database Tables -->
        <div class="test-section">
            <h2>数据库表检查</h2>
            <?php
            global $wpdb;
            $games_table = $wpdb->prefix . 'siege_games';
            $moves_table = $wpdb->prefix . 'siege_game_moves';
            
            $games_exists = $wpdb->get_var("SHOW TABLES LIKE '$games_table'") == $games_table;
            $moves_exists = $wpdb->get_var("SHOW TABLES LIKE '$moves_table'") == $moves_table;
            
            if ($games_exists) {
                echo '<div class="status success">✓ siege_games 表存在</div>';
            } else {
                echo '<div class="status error">✗ siege_games 表不存在</div>';
            }
            
            if ($moves_exists) {
                echo '<div class="status success">✓ siege_game_moves 表存在</div>';
            } else {
                echo '<div class="status error">✗ siege_game_moves 表不存在</div>';
            }
            ?>
        </div>
        
        <!-- User Integration -->
        <div class="test-section">
            <h2>用户集成测试</h2>
            <?php
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                echo '<div class="status success">✓ 用户已登录</div>';
                echo '<p><strong>用户名:</strong> ' . esc_html($current_user->user_login) . '</p>';
                echo '<p><strong>显示名:</strong> ' . esc_html($current_user->display_name) . '</p>';
                echo '<p><strong>用户ID:</strong> ' . esc_html($current_user->ID) . '</p>';
            } else {
                echo '<div class="status info">ℹ 用户未登录 - 游戏将以访客模式运行</div>';
                echo '<p><a href="' . wp_login_url(get_permalink()) . '">点击登录</a></p>';
            }
            ?>
        </div>
        
        <!-- Shortcode Test -->
        <div class="test-section">
            <h2>短代码测试</h2>
            <?php
            if (shortcode_exists('siege_game')) {
                echo '<div class="status success">✓ [siege_game] 短代码已注册</div>';
                echo '<p>短代码使用示例:</p>';
                echo '<pre>[siege_game]</pre>';
                echo '<pre>[siege_game width="800" height="600" ai="true" theme="modern"]</pre>';
            } else {
                echo '<div class="status error">✗ [siege_game] 短代码未注册</div>';
            }
            ?>
        </div>
        
        <!-- Assets Check -->
        <div class="test-section">
            <h2>资源文件检查</h2>
            <?php
            $plugin_url = plugin_dir_url(WP_PLUGIN_DIR . '/siege-board-game/siege-board-game.php');
            $css_file = WP_PLUGIN_DIR . '/siege-board-game/css/siege-game.css';
            $js_file = WP_PLUGIN_DIR . '/siege-board-game/js/siege-game.js';
            
            if (file_exists($css_file)) {
                echo '<div class="status success">✓ CSS文件存在</div>';
            } else {
                echo '<div class="status error">✗ CSS文件不存在</div>';
            }
            
            if (file_exists($js_file)) {
                echo '<div class="status success">✓ JavaScript文件存在</div>';
            } else {
                echo '<div class="status error">✗ JavaScript文件不存在</div>';
            }
            ?>
        </div>
        
        <!-- Settings Test -->
        <div class="test-section">
            <h2>设置选项测试</h2>
            <?php
            $settings = get_option('siege_game_settings', array());
            if (!empty($settings)) {
                echo '<div class="status success">✓ 插件设置已保存</div>';
                echo '<pre>' . print_r($settings, true) . '</pre>';
            } else {
                echo '<div class="status info">ℹ 使用默认设置</div>';
                $default_settings = array(
                    'enable_ai' => true,
                    'enable_save_games' => true,
                    'max_saved_games' => 10,
                    'default_difficulty' => 'medium',
                    'board_theme' => 'classic'
                );
                echo '<pre>' . print_r($default_settings, true) . '</pre>';
            }
            ?>
        </div>
        
        <!-- Game Demo -->
        <div class="test-section">
            <h2>游戏演示</h2>
            <p>以下是使用短代码嵌入的游戏:</p>
            <div class="game-container">
                <?php
                // 模拟短代码调用
                if (function_exists('do_shortcode')) {
                    echo do_shortcode('[siege_game width="100%" height="500"]');
                } else {
                    echo '<div class="status error">✗ 短代码功能不可用</div>';
                }
                ?>
            </div>
        </div>
        
        <!-- AJAX Test -->
        <div class="test-section">
            <h2>AJAX功能测试</h2>
            <button id="test-ajax" class="button">测试AJAX连接</button>
            <div id="ajax-result"></div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#test-ajax').on('click', function() {
                    $('#ajax-result').html('<div class="status info">测试中...</div>');
                    
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'siege_game_test',
                        nonce: '<?php echo wp_create_nonce('siege_game_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $('#ajax-result').html('<div class="status success">✓ AJAX连接正常</div>');
                        } else {
                            $('#ajax-result').html('<div class="status error">✗ AJAX连接失败: ' + response.data + '</div>');
                        }
                    }).fail(function() {
                        $('#ajax-result').html('<div class="status error">✗ AJAX请求失败</div>');
                    });
                });
            });
            </script>
        </div>
        
        <!-- Performance Info -->
        <div class="test-section">
            <h2>性能信息</h2>
            <?php
            echo '<p><strong>PHP版本:</strong> ' . PHP_VERSION . '</p>';
            echo '<p><strong>WordPress版本:</strong> ' . get_bloginfo('version') . '</p>';
            echo '<p><strong>内存使用:</strong> ' . size_format(memory_get_usage(true)) . '</p>';
            echo '<p><strong>内存限制:</strong> ' . ini_get('memory_limit') . '</p>';
            
            global $wpdb;
            echo '<p><strong>数据库版本:</strong> ' . $wpdb->db_version() . '</p>';
            ?>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>