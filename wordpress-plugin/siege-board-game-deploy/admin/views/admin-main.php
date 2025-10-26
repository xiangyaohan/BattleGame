<?php
/**
 * Admin main page view
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get game statistics
$stats = Siege_Game_Database::get_game_statistics();
$recent_games = Siege_Game_Database::get_recent_games(10);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="siege-admin-dashboard">
        <!-- Statistics Cards -->
        <div class="siege-stats-grid">
            <div class="siege-stat-card">
                <div class="siege-stat-icon">🎮</div>
                <div class="siege-stat-content">
                    <h3><?php echo number_format($stats['total_games']); ?></h3>
                    <p>总游戏数</p>
                </div>
            </div>
            
            <div class="siege-stat-card">
                <div class="siege-stat-icon">👥</div>
                <div class="siege-stat-content">
                    <h3><?php echo number_format($stats['active_players']); ?></h3>
                    <p>活跃玩家</p>
                </div>
            </div>
            
            <div class="siege-stat-card">
                <div class="siege-stat-icon">⏱️</div>
                <div class="siege-stat-content">
                    <h3><?php echo $stats['avg_game_duration']; ?></h3>
                    <p>平均游戏时长</p>
                </div>
            </div>
            
            <div class="siege-stat-card">
                <div class="siege-stat-icon">🏆</div>
                <div class="siege-stat-content">
                    <h3><?php echo number_format($stats['completed_games']); ?></h3>
                    <p>已完成游戏</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="siege-quick-actions">
            <h2>快速操作</h2>
            <div class="siege-action-buttons">
                <a href="<?php echo admin_url('admin.php?page=siege-game-settings'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-settings"></span>
                    插件设置
                </a>
                <a href="<?php echo admin_url('admin.php?page=siege-game-records'); ?>" class="button">
                    <span class="dashicons dashicons-list-view"></span>
                    游戏记录
                </a>
                <a href="<?php echo admin_url('admin.php?page=siege-game-stats'); ?>" class="button">
                    <span class="dashicons dashicons-chart-bar"></span>
                    统计报告
                </a>
                <button id="clear-cache" class="button">
                    <span class="dashicons dashicons-update"></span>
                    清除缓存
                </button>
            </div>
        </div>
        
        <!-- Recent Games -->
        <div class="siege-recent-games">
            <h2>最近游戏</h2>
            <?php if (!empty($recent_games)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>游戏ID</th>
                            <th>玩家</th>
                            <th>状态</th>
                            <th>开始时间</th>
                            <th>最后更新</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_games as $game): ?>
                            <tr>
                                <td><?php echo esc_html($game->id); ?></td>
                                <td>
                                    <?php 
                                    $user = get_user_by('id', $game->user_id);
                                    echo $user ? esc_html($user->display_name) : '未知用户';
                                    ?>
                                </td>
                                <td>
                                    <span class="siege-status siege-status-<?php echo esc_attr($game->status); ?>">
                                        <?php 
                                        switch($game->status) {
                                            case 'playing': echo '进行中'; break;
                                            case 'completed': echo '已完成'; break;
                                            case 'saved': echo '已保存'; break;
                                            default: echo $game->status;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(mysql2date('Y-m-d H:i', $game->created_at)); ?></td>
                                <td><?php echo esc_html(mysql2date('Y-m-d H:i', $game->updated_at)); ?></td>
                                <td>
                                    <button class="button button-small view-game" data-game-id="<?php echo esc_attr($game->id); ?>">
                                        查看
                                    </button>
                                    <button class="button button-small delete-game" data-game-id="<?php echo esc_attr($game->id); ?>">
                                        删除
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>暂无游戏记录。</p>
            <?php endif; ?>
        </div>
        
        <!-- System Info -->
        <div class="siege-system-info">
            <h2>系统信息</h2>
            <div class="siege-info-grid">
                <div class="siege-info-item">
                    <strong>插件版本:</strong>
                    <span><?php echo SIEGE_GAME_VERSION; ?></span>
                </div>
                <div class="siege-info-item">
                    <strong>WordPress版本:</strong>
                    <span><?php echo get_bloginfo('version'); ?></span>
                </div>
                <div class="siege-info-item">
                    <strong>PHP版本:</strong>
                    <span><?php echo PHP_VERSION; ?></span>
                </div>
                <div class="siege-info-item">
                    <strong>数据库版本:</strong>
                    <span><?php global $wpdb; echo $wpdb->db_version(); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Clear cache
    $('#clear-cache').on('click', function() {
        if (confirm('确定要清除缓存吗？')) {
            $.post(ajaxurl, {
                action: 'siege_clear_cache',
                nonce: '<?php echo wp_create_nonce('siege_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('缓存已清除');
                    location.reload();
                } else {
                    alert('清除缓存失败：' + response.data);
                }
            });
        }
    });
    
    // Delete game
    $('.delete-game').on('click', function() {
        var gameId = $(this).data('game-id');
        if (confirm('确定要删除这个游戏记录吗？')) {
            $.post(ajaxurl, {
                action: 'siege_delete_game',
                game_id: gameId,
                nonce: '<?php echo wp_create_nonce('siege_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('删除失败：' + response.data);
                }
            });
        }
    });
});
</script>