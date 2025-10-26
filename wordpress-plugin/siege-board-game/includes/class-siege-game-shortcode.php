<?php
/**
 * 短代码处理类
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siege_Game_Shortcode {
    
    /**
     * 渲染游戏短代码
     */
    public static function render($atts) {
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '600px',
            'ai_enabled' => 'true',
            'save_games' => 'true',
            'theme' => 'default',
            'difficulty' => 'medium'
        ), $atts, 'siege_game');
        
        // 确保用户已登录（如果需要保存游戏）
        if ($atts['save_games'] === 'true' && !is_user_logged_in()) {
            return '<div class="siege-game-login-required">' . 
                   __('请登录以保存游戏进度', 'siege-board-game') . 
                   '</div>';
        }
        
        // 生成唯一的游戏ID
        $game_id = 'siege-game-' . wp_generate_uuid4();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($game_id); ?>" class="siege-game-container" 
             style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;"
             data-ai-enabled="<?php echo esc_attr($atts['ai_enabled']); ?>"
             data-save-games="<?php echo esc_attr($atts['save_games']); ?>"
             data-theme="<?php echo esc_attr($atts['theme']); ?>"
             data-difficulty="<?php echo esc_attr($atts['difficulty']); ?>">
            
            <!-- 游戏加载界面 -->
            <div class="siege-game-loading">
                <div class="loading-spinner"></div>
                <p><?php _e('正在加载游戏...', 'siege-board-game'); ?></p>
            </div>
            
            <!-- 游戏主界面 -->
            <div class="siege-game-main" style="display: none;">
                
                <!-- 游戏控制面板 -->
                <div class="siege-game-controls">
                    <div class="game-info">
                        <span class="current-player"><?php _e('当前玩家:', 'siege-board-game'); ?> <span id="player-indicator">人类</span></span>
                        <span class="move-count"><?php _e('移动次数:', 'siege-board-game'); ?> <span id="move-counter">0</span></span>
                    </div>
                    
                    <div class="game-actions">
                        <button id="new-game-btn" class="btn btn-primary"><?php _e('新游戏', 'siege-board-game'); ?></button>
                        <?php if ($atts['save_games'] === 'true' && is_user_logged_in()): ?>
                        <button id="save-game-btn" class="btn btn-secondary"><?php _e('保存游戏', 'siege-board-game'); ?></button>
                        <button id="load-game-btn" class="btn btn-secondary"><?php _e('加载游戏', 'siege-board-game'); ?></button>
                        <?php endif; ?>
                        <button id="undo-move-btn" class="btn btn-warning"><?php _e('撤销', 'siege-board-game'); ?></button>
                        <button id="hint-btn" class="btn btn-info"><?php _e('提示', 'siege-board-game'); ?></button>
                    </div>
                </div>
                
                <!-- 游戏棋盘 -->
                <div class="siege-game-board-container">
                    <div id="siege-game-board" class="siege-game-board">
                        <!-- 棋盘将通过JavaScript动态生成 -->
                    </div>
                </div>
                
                <!-- 游戏状态面板 -->
                <div class="siege-game-status">
                    <div class="player-stats">
                        <div class="human-player">
                            <h4><?php _e('人类玩家', 'siege-board-game'); ?></h4>
                            <div class="pieces-count">
                                <span class="attackers"><?php _e('攻击者:', 'siege-board-game'); ?> <span id="human-attackers">24</span></span>
                                <span class="defenders"><?php _e('防守者:', 'siege-board-game'); ?> <span id="human-defenders">0</span></span>
                            </div>
                        </div>
                        
                        <?php if ($atts['ai_enabled'] === 'true'): ?>
                        <div class="ai-player">
                            <h4><?php _e('AI玩家', 'siege-board-game'); ?></h4>
                            <div class="pieces-count">
                                <span class="attackers"><?php _e('攻击者:', 'siege-board-game'); ?> <span id="ai-attackers">0</span></span>
                                <span class="defenders"><?php _e('防守者:', 'siege-board-game'); ?> <span id="ai-defenders">1</span></span>
                            </div>
                            <div class="ai-status">
                                <span id="ai-thinking" style="display: none;"><?php _e('AI思考中...', 'siege-board-game'); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="game-messages">
                        <div id="game-message-area" class="message-area">
                            <p><?php _e('欢迎来到攻城掠地！点击"新游戏"开始。', 'siege-board-game'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- 游戏规则面板（可折叠） -->
                <div class="siege-game-rules">
                    <button class="rules-toggle" type="button"><?php _e('游戏规则', 'siege-board-game'); ?></button>
                    <div class="rules-content" style="display: none;">
                        <h4><?php _e('游戏目标', 'siege-board-game'); ?></h4>
                        <ul>
                            <li><?php _e('攻击者：占领中央城堡或消灭所有防守者', 'siege-board-game'); ?></li>
                            <li><?php _e('防守者：阻止攻击者达成目标', 'siege-board-game'); ?></li>
                        </ul>
                        
                        <h4><?php _e('移动规则', 'siege-board-game'); ?></h4>
                        <ul>
                            <li><?php _e('棋子只能在相邻的空格中移动', 'siege-board-game'); ?></li>
                            <li><?php _e('不能斜向移动', 'siege-board-game'); ?></li>
                            <li><?php _e('包围敌方棋子可以将其消灭', 'siege-board-game'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- 游戏结束对话框 -->
            <div id="game-over-modal" class="siege-game-modal" style="display: none;">
                <div class="modal-content">
                    <h3 id="game-over-title"><?php _e('游戏结束', 'siege-board-game'); ?></h3>
                    <p id="game-over-message"></p>
                    <div class="modal-actions">
                        <button id="play-again-btn" class="btn btn-primary"><?php _e('再玩一局', 'siege-board-game'); ?></button>
                        <button id="close-modal-btn" class="btn btn-secondary"><?php _e('关闭', 'siege-board-game'); ?></button>
                    </div>
                </div>
            </div>
            
            <!-- 保存的游戏列表模态框 -->
            <?php if ($atts['save_games'] === 'true' && is_user_logged_in()): ?>
            <div id="saved-games-modal" class="siege-game-modal" style="display: none;">
                <div class="modal-content">
                    <h3><?php _e('保存的游戏', 'siege-board-game'); ?></h3>
                    <div id="saved-games-list">
                        <!-- 通过AJAX加载 -->
                    </div>
                    <div class="modal-actions">
                        <button id="close-saved-games-btn" class="btn btn-secondary"><?php _e('关闭', 'siege-board-game'); ?></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 初始化游戏
            if (typeof SiegeGame !== 'undefined') {
                window.siegeGameInstance = new SiegeGame('<?php echo esc_js($game_id); ?>', {
                    aiEnabled: <?php echo $atts['ai_enabled'] === 'true' ? 'true' : 'false'; ?>,
                    saveGames: <?php echo $atts['save_games'] === 'true' ? 'true' : 'false'; ?>,
                    difficulty: '<?php echo esc_js($atts['difficulty']); ?>',
                    theme: '<?php echo esc_js($atts['theme']); ?>',
                    userId: <?php echo is_user_logged_in() ? get_current_user_id() : 'null'; ?>
                });
            } else {
                console.error('SiegeGame class not found. Make sure the game script is loaded.');
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * 渲染游戏统计短代码
     */
    public static function render_stats($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('请登录查看游戏统计', 'siege-board-game') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $stats = Siege_Game_Database::get_game_stats($user_id);
        
        if (!$stats || $stats->total_games == 0) {
            return '<p>' . __('还没有游戏记录', 'siege-board-game') . '</p>';
        }
        
        $win_rate = $stats->total_games > 0 ? round(($stats->wins / $stats->total_games) * 100, 1) : 0;
        
        ob_start();
        ?>
        <div class="siege-game-stats">
            <h3><?php _e('游戏统计', 'siege-board-game'); ?></h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-label"><?php _e('总游戏数', 'siege-board-game'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats->total_games); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('胜利', 'siege-board-game'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats->wins); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('失败', 'siege-board-game'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats->losses); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('平局', 'siege-board-game'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats->draws); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('胜率', 'siege-board-game'); ?></span>
                    <span class="stat-value"><?php echo esc_html($win_rate); ?>%</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('平均移动数', 'siege-board-game'); ?></span>
                    <span class="stat-value"><?php echo esc_html(round($stats->avg_moves, 1)); ?></span>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
}