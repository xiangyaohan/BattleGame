<?php
/**
 * 游戏棋盘视图模板
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取短代码属性
$game_id = isset($game_id) ? $game_id : 'siege-game-' . wp_generate_uuid4();
$width = isset($atts['width']) ? $atts['width'] : '100%';
$height = isset($atts['height']) ? $atts['height'] : '600px';
$ai_enabled = isset($atts['ai_enabled']) ? $atts['ai_enabled'] : 'true';
$save_games = isset($atts['save_games']) ? $atts['save_games'] : 'true';
$theme = isset($atts['theme']) ? $atts['theme'] : 'default';
$difficulty = isset($atts['difficulty']) ? $atts['difficulty'] : 'medium';
?>

<div id="<?php echo esc_attr($game_id); ?>" class="siege-game-container" 
     style="width: <?php echo esc_attr($width); ?>; height: <?php echo esc_attr($height); ?>;"
     data-ai-enabled="<?php echo esc_attr($ai_enabled); ?>"
     data-save-games="<?php echo esc_attr($save_games); ?>"
     data-theme="<?php echo esc_attr($theme); ?>"
     data-difficulty="<?php echo esc_attr($difficulty); ?>">
    
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
                <span class="current-player">
                    <?php _e('当前玩家:', 'siege-board-game'); ?> 
                    <span id="player-indicator"><?php _e('人类', 'siege-board-game'); ?></span>
                </span>
                <span class="move-count">
                    <?php _e('移动次数:', 'siege-board-game'); ?> 
                    <span id="move-counter">0</span>
                </span>
                <span class="game-round">
                    <?php _e('回合:', 'siege-board-game'); ?> 
                    <span id="round-counter">1</span>
                </span>
            </div>
            
            <div class="game-actions">
                <button id="new-game-btn" class="btn btn-primary">
                    <?php _e('新游戏', 'siege-board-game'); ?>
                </button>
                
                <?php if ($save_games === 'true' && is_user_logged_in()): ?>
                <button id="save-game-btn" class="btn btn-secondary">
                    <?php _e('保存游戏', 'siege-board-game'); ?>
                </button>
                <button id="load-game-btn" class="btn btn-secondary">
                    <?php _e('加载游戏', 'siege-board-game'); ?>
                </button>
                <?php endif; ?>
                
                <button id="undo-move-btn" class="btn btn-warning">
                    <?php _e('撤销', 'siege-board-game'); ?>
                </button>
                <button id="hint-btn" class="btn btn-info">
                    <?php _e('提示', 'siege-board-game'); ?>
                </button>
                <button id="settings-btn" class="btn btn-secondary">
                    <?php _e('设置', 'siege-board-game'); ?>
                </button>
            </div>
        </div>
        
        <!-- 游戏棋盘 -->
        <div class="siege-game-board-container">
            <div id="siege-game-board" class="siege-game-board">
                <!-- 棋盘将通过JavaScript动态生成 -->
                <?php for ($row = 0; $row < 11; $row++): ?>
                    <?php for ($col = 0; $col < 11; $col++): ?>
                        <?php
                        $cell_id = "cell-{$row}-{$col}";
                        $cell_classes = ['board-cell'];
                        
                        // 添加特殊位置的样式类
                        if (($row == 5 && $col == 5)) {
                            $cell_classes[] = 'throne'; // 王座
                        } elseif (in_array([$row, $col], [[0, 3], [0, 4], [0, 5], [0, 6], [0, 7], [1, 5], [3, 0], [4, 0], [5, 0], [5, 1], [6, 0], [7, 0], [9, 5], [10, 3], [10, 4], [10, 5], [10, 6], [10, 7], [5, 9], [5, 10], [3, 10], [4, 10], [6, 10], [7, 10]])) {
                            $cell_classes[] = 'castle'; // 城堡位置
                        }
                        ?>
                        <div id="<?php echo esc_attr($cell_id); ?>" 
                             class="<?php echo esc_attr(implode(' ', $cell_classes)); ?>"
                             data-row="<?php echo esc_attr($row); ?>"
                             data-col="<?php echo esc_attr($col); ?>">
                        </div>
                    <?php endfor; ?>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- 游戏状态面板 -->
        <div class="siege-game-status">
            <div class="player-stats">
                <div class="human-player">
                    <h4><?php _e('人类玩家', 'siege-board-game'); ?></h4>
                    <div class="pieces-count">
                        <span class="attackers">
                            <?php _e('攻击者:', 'siege-board-game'); ?> 
                            <span id="human-attackers">24</span>
                        </span>
                        <span class="defenders">
                            <?php _e('防守者:', 'siege-board-game'); ?> 
                            <span id="human-defenders">0</span>
                        </span>
                    </div>
                    <div class="player-role">
                        <span id="human-role"><?php _e('选择阵营', 'siege-board-game'); ?></span>
                    </div>
                </div>
                
                <?php if ($ai_enabled === 'true'): ?>
                <div class="ai-player">
                    <h4><?php _e('AI玩家', 'siege-board-game'); ?></h4>
                    <div class="pieces-count">
                        <span class="attackers">
                            <?php _e('攻击者:', 'siege-board-game'); ?> 
                            <span id="ai-attackers">0</span>
                        </span>
                        <span class="defenders">
                            <?php _e('防守者:', 'siege-board-game'); ?> 
                            <span id="ai-defenders">1</span>
                        </span>
                    </div>
                    <div class="ai-status">
                        <span id="ai-thinking" style="display: none;">
                            <?php _e('AI思考中...', 'siege-board-game'); ?>
                        </span>
                        <span id="ai-difficulty">
                            <?php _e('难度:', 'siege-board-game'); ?> 
                            <span><?php echo esc_html(ucfirst($difficulty)); ?></span>
                        </span>
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
            <button class="rules-toggle" type="button">
                <?php _e('游戏规则', 'siege-board-game'); ?>
            </button>
            <div class="rules-content" style="display: none;">
                <h4><?php _e('游戏目标', 'siege-board-game'); ?></h4>
                <ul>
                    <li><?php _e('攻击者：占领中央王座或消灭所有防守者', 'siege-board-game'); ?></li>
                    <li><?php _e('防守者：阻止攻击者达成目标，或将国王安全护送到边缘', 'siege-board-game'); ?></li>
                </ul>
                
                <h4><?php _e('移动规则', 'siege-board-game'); ?></h4>
                <ul>
                    <li><?php _e('棋子只能在相邻的空格中移动（上下左右）', 'siege-board-game'); ?></li>
                    <li><?php _e('不能斜向移动', 'siege-board-game'); ?></li>
                    <li><?php _e('国王和普通棋子移动规则相同', 'siege-board-game'); ?></li>
                    <li><?php _e('只有国王可以占据王座', 'siege-board-game'); ?></li>
                </ul>
                
                <h4><?php _e('战斗规则', 'siege-board-game'); ?></h4>
                <ul>
                    <li><?php _e('当敌方棋子被两个己方棋子夹在中间时，该棋子被消灭', 'siege-board-game'); ?></li>
                    <li><?php _e('国王需要被四面包围才能被消灭（除非在王座边缘）', 'siege-board-game'); ?></li>
                    <li><?php _e('城堡和王座可以作为"盟友"参与包围', 'siege-board-game'); ?></li>
                </ul>
                
                <h4><?php _e('胜利条件', 'siege-board-game'); ?></h4>
                <ul>
                    <li><?php _e('攻击者胜利：国王被消灭', 'siege-board-game'); ?></li>
                    <li><?php _e('防守者胜利：国王到达棋盘边缘任意位置', 'siege-board-game'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- 游戏结束对话框 -->
    <div id="game-over-modal" class="siege-game-modal" style="display: none;">
        <div class="modal-content">
            <h3 id="game-over-title"><?php _e('游戏结束', 'siege-board-game'); ?></h3>
            <p id="game-over-message"></p>
            <div class="game-over-stats" id="game-over-stats" style="display: none;">
                <h4><?php _e('游戏统计', 'siege-board-game'); ?></h4>
                <div class="stats-summary">
                    <span><?php _e('总移动数:', 'siege-board-game'); ?> <span id="final-moves">0</span></span>
                    <span><?php _e('游戏时长:', 'siege-board-game'); ?> <span id="game-duration">0</span></span>
                </div>
            </div>
            <div class="modal-actions">
                <button id="play-again-btn" class="btn btn-primary">
                    <?php _e('再玩一局', 'siege-board-game'); ?>
                </button>
                <button id="close-modal-btn" class="btn btn-secondary">
                    <?php _e('关闭', 'siege-board-game'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- 保存的游戏列表模态框 -->
    <?php if ($save_games === 'true' && is_user_logged_in()): ?>
    <div id="saved-games-modal" class="siege-game-modal" style="display: none;">
        <div class="modal-content">
            <h3><?php _e('保存的游戏', 'siege-board-game'); ?></h3>
            <div id="saved-games-list">
                <div class="loading-spinner" style="margin: 20px auto;"></div>
                <p style="text-align: center;"><?php _e('加载中...', 'siege-board-game'); ?></p>
            </div>
            <div class="modal-actions">
                <button id="close-saved-games-btn" class="btn btn-secondary">
                    <?php _e('关闭', 'siege-board-game'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 游戏设置模态框 -->
    <div id="game-settings-modal" class="siege-game-modal" style="display: none;">
        <div class="modal-content">
            <h3><?php _e('游戏设置', 'siege-board-game'); ?></h3>
            <div class="settings-form">
                <?php if ($ai_enabled === 'true'): ?>
                <div class="setting-item">
                    <label for="ai-difficulty-select"><?php _e('AI难度:', 'siege-board-game'); ?></label>
                    <select id="ai-difficulty-select">
                        <option value="easy"><?php _e('简单', 'siege-board-game'); ?></option>
                        <option value="medium" selected><?php _e('中等', 'siege-board-game'); ?></option>
                        <option value="hard"><?php _e('困难', 'siege-board-game'); ?></option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="setting-item">
                    <label for="sound-enabled"><?php _e('音效:', 'siege-board-game'); ?></label>
                    <input type="checkbox" id="sound-enabled" checked>
                </div>
                
                <div class="setting-item">
                    <label for="animations-enabled"><?php _e('动画:', 'siege-board-game'); ?></label>
                    <input type="checkbox" id="animations-enabled" checked>
                </div>
                
                <div class="setting-item">
                    <label for="show-hints"><?php _e('显示提示:', 'siege-board-game'); ?></label>
                    <input type="checkbox" id="show-hints" checked>
                </div>
            </div>
            <div class="modal-actions">
                <button id="save-settings-btn" class="btn btn-primary">
                    <?php _e('保存设置', 'siege-board-game'); ?>
                </button>
                <button id="close-settings-btn" class="btn btn-secondary">
                    <?php _e('关闭', 'siege-board-game'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // 初始化游戏
    if (typeof SiegeGame !== 'undefined') {
        window.siegeGameInstance = new SiegeGame('<?php echo esc_js($game_id); ?>', {
            aiEnabled: <?php echo $ai_enabled === 'true' ? 'true' : 'false'; ?>,
            saveGames: <?php echo $save_games === 'true' ? 'true' : 'false'; ?>,
            difficulty: '<?php echo esc_js($difficulty); ?>',
            theme: '<?php echo esc_js($theme); ?>',
            userId: <?php echo is_user_logged_in() ? get_current_user_id() : 'null'; ?>,
            ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo esc_js(wp_create_nonce('siege_game_nonce')); ?>',
            strings: {
                loading: '<?php echo esc_js(__('加载中...', 'siege-board-game')); ?>',
                error: '<?php echo esc_js(__('发生错误，请重试', 'siege-board-game')); ?>',
                gameOver: '<?php echo esc_js(__('游戏结束', 'siege-board-game')); ?>',
                yourTurn: '<?php echo esc_js(__('轮到你了', 'siege-board-game')); ?>',
                aiThinking: '<?php echo esc_js(__('AI思考中...', 'siege-board-game')); ?>',
                attackersWin: '<?php echo esc_js(__('攻击者获胜！', 'siege-board-game')); ?>',
                defendersWin: '<?php echo esc_js(__('防守者获胜！', 'siege-board-game')); ?>',
                invalidMove: '<?php echo esc_js(__('无效移动', 'siege-board-game')); ?>',
                gameStarted: '<?php echo esc_js(__('游戏开始！', 'siege-board-game')); ?>',
                gameSaved: '<?php echo esc_js(__('游戏已保存', 'siege-board-game')); ?>',
                gameLoaded: '<?php echo esc_js(__('游戏已加载', 'siege-board-game')); ?>'
            }
        });
    } else {
        console.error('SiegeGame class not found. Make sure the game script is loaded.');
        $('#<?php echo esc_js($game_id); ?> .siege-game-loading p').text('<?php echo esc_js(__('游戏加载失败，请刷新页面重试', 'siege-board-game')); ?>');
    }