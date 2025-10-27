<?php
/**
 * 管理后台主页面
 *
 * @package SiegeBoardGame
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取游戏统计数据
$db = new Siege_Game_Database();
$stats = $db->get_game_statistics();
$recent_games = $db->get_user_games(get_current_user_id(), 5);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="siege-admin-dashboard">
        <!-- 统计卡片 -->
        <div class="siege-stats-cards">
            <div class="siege-stat-card">
                <h3><?php _e('总游戏数', 'siege-board-game'); ?></h3>
                <div class="stat-number"><?php echo esc_html($stats['total_games'] ?? 0); ?></div>
            </div>
            
            <div class="siege-stat-card">
                <h3><?php _e('活跃玩家', 'siege-board-game'); ?></h3>
                <div class="stat-number"><?php echo esc_html($stats['active_players'] ?? 0); ?></div>
            </div>
            
            <div class="siege-stat-card">
                <h3><?php _e('今日游戏', 'siege-board-game'); ?></h3>
                <div class="stat-number"><?php echo esc_html($stats['today_games'] ?? 0); ?></div>
            </div>
            
            <div class="siege-stat-card">
                <h3><?php _e('平均游戏时长', 'siege-board-game'); ?></h3>
                <div class="stat-number"><?php echo esc_html($stats['avg_duration'] ?? '0') . ' ' . __('分钟', 'siege-board-game'); ?></div>
            </div>
        </div>
        
        <!-- 快速操作 -->
        <div class="siege-quick-actions">
            <h2><?php _e('快速操作', 'siege-board-game'); ?></h2>
            <div class="action-buttons">
                <a href="<?php echo admin_url('admin.php?page=siege-game-settings'); ?>" class="button button-primary">
                    <?php _e('插件设置', 'siege-board-game'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=siege-game-records'); ?>" class="button">
                    <?php _e('查看游戏记录', 'siege-board-game'); ?>
                </a>
                <button type="button" class="button" id="clear-cache-btn">
                    <?php _e('清除缓存', 'siege-board-game'); ?>
                </button>
            </div>
        </div>
        
        <!-- 最近游戏 -->
        <div class="siege-recent-games">
            <h2><?php _e('最近游戏', 'siege-board-game'); ?></h2>
            <?php if (!empty($recent_games)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('游戏ID', 'siege-board-game'); ?></th>
                            <th><?php _e('玩家', 'siege-board-game'); ?></th>
                            <th><?php _e('状态', 'siege-board-game'); ?></th>
                            <th><?php _e('开始时间', 'siege-board-game'); ?></th>
                            <th><?php _e('操作', 'siege-board-game'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_games as $game): ?>
                            <tr>
                                <td><?php echo esc_html($game->id); ?></td>
                                <td>
                                    <?php 
                                    $user = get_user_by('id', $game->user_id);
                                    echo esc_html($user ? $user->display_name : __('未知用户', 'siege-board-game'));
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($game->status); ?>">
                                        <?php 
                                        switch($game->status) {
                                            case 'active':
                                                _e('进行中', 'siege-board-game');
                                                break;
                                            case 'completed':
                                                _e('已完成', 'siege-board-game');
                                                break;
                                            case 'paused':
                                                _e('已暂停', 'siege-board-game');
                                                break;
                                            default:
                                                echo esc_html($game->status);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $game->created_at)); ?></td>
                                <td>
                                    <a href="#" class="button button-small view-game" data-game-id="<?php echo esc_attr($game->id); ?>">
                                        <?php _e('查看', 'siege-board-game'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('暂无游戏记录', 'siege-board-game'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- 系统信息 -->
        <div class="siege-system-info">
            <h2><?php _e('系统信息', 'siege-board-game'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php _e('插件版本', 'siege-board-game'); ?></th>
                    <td><?php echo esc_html(SIEGE_GAME_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php _e('WordPress版本', 'siege-board-game'); ?></th>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>
                <tr>
                    <th><?php _e('PHP版本', 'siege-board-game'); ?></th>
                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php _e('数据库版本', 'siege-board-game'); ?></th>
                    <td><?php echo esc_html($GLOBALS['wpdb']->db_version()); ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // 清除缓存按钮
    $('#clear-cache-btn').on('click', function() {
        if (confirm('<?php _e('确定要清除缓存吗？', 'siege-board-game'); ?>')) {
            $.post(ajaxurl, {
                action: 'siege_game_action',
                game_action: 'clear_cache',
                nonce: '<?php echo wp_create_nonce('siege_game_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('缓存已清除', 'siege-board-game'); ?>');
                } else {
                    alert('<?php _e('清除缓存失败', 'siege-board-game'); ?>');
                }
            });
        }
    });
    
    // 查看游戏按钮
    $('.view-game').on('click', function(e) {
        e.preventDefault();
        var gameId = $(this).data('game-id');
        // 这里可以添加查看游戏详情的逻辑
        alert('<?php _e('查看游戏功能待实现', 'siege-board-game'); ?> ID: ' + gameId);
    });
});
</script>