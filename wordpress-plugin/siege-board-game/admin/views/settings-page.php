<?php
/**
 * Admin settings page view
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$options = get_option('siege_game_settings', array());
$default_options = array(
    'enable_ai' => true,
    'enable_save_games' => true,
    'max_saved_games' => 10,
    'game_timeout' => 3600,
    'enable_statistics' => true,
    'enable_leaderboard' => true,
    'default_difficulty' => 'medium',
    'board_theme' => 'classic',
    'enable_sound' => true,
    'enable_animations' => true,
    'auto_save_interval' => 300,
    'max_game_duration' => 7200
);
$options = wp_parse_args($options, $default_options);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('siege_game_settings');
        do_settings_sections('siege_game_settings');
        ?>
        
        <div class="siege-settings-container">
            <!-- Game Settings -->
            <div class="siege-settings-section">
                <h2>游戏设置</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">启用AI对战</th>
                        <td>
                            <label>
                                <input type="checkbox" name="siege_game_settings[enable_ai]" value="1" 
                                       <?php checked($options['enable_ai']); ?> />
                                允许玩家与AI对战
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">启用游戏保存</th>
                        <td>
                            <label>
                                <input type="checkbox" name="siege_game_settings[enable_save_games]" value="1" 
                                       <?php checked($options['enable_save_games']); ?> />
                                允许玩家保存游戏进度
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">最大保存游戏数</th>
                        <td>
                            <input type="number" name="siege_game_settings[max_saved_games]" 
                                   value="<?php echo esc_attr($options['max_saved_games']); ?>" 
                                   min="1" max="50" class="small-text" />
                            <p class="description">每个用户最多可保存的游戏数量</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">游戏超时时间</th>
                        <td>
                            <input type="number" name="siege_game_settings[game_timeout]" 
                                   value="<?php echo esc_attr($options['game_timeout']); ?>" 
                                   min="300" max="86400" class="small-text" />
                            <span>秒</span>
                            <p class="description">游戏无操作自动结束的时间</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">默认难度</th>
                        <td>
                            <select name="siege_game_settings[default_difficulty]">
                                <option value="easy" <?php selected($options['default_difficulty'], 'easy'); ?>>简单</option>
                                <option value="medium" <?php selected($options['default_difficulty'], 'medium'); ?>>中等</option>
                                <option value="hard" <?php selected($options['default_difficulty'], 'hard'); ?>>困难</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Display Settings -->
            <div class="siege-settings-section">
                <h2>显示设置</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">棋盘主题</th>
                        <td>
                            <select name="siege_game_settings[board_theme]">
                                <option value="classic" <?php selected($options['board_theme'], 'classic'); ?>>经典</option>
                                <option value="modern" <?php selected($options['board_theme'], 'modern'); ?>>现代</option>
                                <option value="dark" <?php selected($options['board_theme'], 'dark'); ?>>暗色</option>
                                <option value="wood" <?php selected($options['board_theme'], 'wood'); ?>>木质</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">启用音效</th>
                        <td>
                            <label>
                                <input type="checkbox" name="siege_game_settings[enable_sound]" value="1" 
                                       <?php checked($options['enable_sound']); ?> />
                                启用游戏音效
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">启用动画</th>
                        <td>
                            <label>
                                <input type="checkbox" name="siege_game_settings[enable_animations]" value="1" 
                                       <?php checked($options['enable_animations']); ?> />
                                启用棋子移动动画
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Performance Settings -->
            <div class="siege-settings-section">
                <h2>性能设置</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">自动保存间隔</th>
                        <td>
                            <input type="number" name="siege_game_settings[auto_save_interval]" 
                                   value="<?php echo esc_attr($options['auto_save_interval']); ?>" 
                                   min="60" max="3600" class="small-text" />
                            <span>秒</span>
                            <p class="description">游戏自动保存的间隔时间</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">最大游戏时长</th>
                        <td>
                            <input type="number" name="siege_game_settings[max_game_duration]" 
                                   value="<?php echo esc_attr($options['max_game_duration']); ?>" 
                                   min="1800" max="86400" class="small-text" />
                            <span>秒</span>
                            <p class="description">单局游戏的最大时长</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Statistics Settings -->
            <div class="siege-settings-section">
                <h2>统计设置</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">启用统计</th>
                        <td>
                            <label>
                                <input type="checkbox" name="siege_game_settings[enable_statistics]" value="1" 
                                       <?php checked($options['enable_statistics']); ?> />
                                收集游戏统计数据
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">启用排行榜</th>
                        <td>
                            <label>
                                <input type="checkbox" name="siege_game_settings[enable_leaderboard]" value="1" 
                                       <?php checked($options['enable_leaderboard']); ?> />
                                显示玩家排行榜
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Shortcode Usage -->
            <div class="siege-settings-section">
                <h2>短代码使用说明</h2>
                <div class="siege-shortcode-info">
                    <h3>基本用法</h3>
                    <code>[siege_game]</code>
                    
                    <h3>带参数的用法</h3>
                    <code>[siege_game width="800" height="600" ai="true" save_games="true" theme="modern" difficulty="medium"]</code>
                    
                    <h3>参数说明</h3>
                    <ul>
                        <li><strong>width</strong>: 游戏宽度（默认：800px）</li>
                        <li><strong>height</strong>: 游戏高度（默认：600px）</li>
                        <li><strong>ai</strong>: 是否启用AI（true/false，默认：true）</li>
                        <li><strong>save_games</strong>: 是否允许保存游戏（true/false，默认：true）</li>
                        <li><strong>theme</strong>: 主题（classic/modern/dark/wood，默认：classic）</li>
                        <li><strong>difficulty</strong>: 难度（easy/medium/hard，默认：medium）</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php submit_button('保存设置'); ?>
    </form>
</div>

<style>
.siege-settings-container {
    max-width: 1000px;
}

.siege-settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 20px;
}

.siege-settings-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.siege-shortcode-info {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.siege-shortcode-info h3 {
    margin-top: 15px;
    margin-bottom: 10px;
}

.siege-shortcode-info h3:first-child {
    margin-top: 0;
}

.siege-shortcode-info code {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 8px 12px;
    display: block;
    margin: 5px 0;
    font-family: Consolas, Monaco, monospace;
}

.siege-shortcode-info ul {
    margin: 10px 0;
    padding-left: 20px;
}

.siege-shortcode-info li {
    margin-bottom: 5px;
}
</style>