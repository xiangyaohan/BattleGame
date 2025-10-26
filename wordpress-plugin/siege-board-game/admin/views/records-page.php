<?php
/**
 * 游戏记录管理页面
 *
 * @package SiegeBoardGame
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 处理批量操作
if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && isset($_POST['game_ids'])) {
    if (wp_verify_nonce($_POST['_wpnonce'], 'bulk_delete_games')) {
        $db = new Siege_Game_Database();
        $deleted_count = 0;
        foreach ($_POST['game_ids'] as $game_id) {
            if ($db->delete_game(intval($game_id))) {
                $deleted_count++;
            }
        }
        echo '<div class="notice notice-success"><p>' . 
             sprintf(__('成功删除 %d 个游戏记录', 'siege-board-game'), $deleted_count) . 
             '</p></div>';
    }
}

// 获取分页参数
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($paged - 1) * $per_page;

// 获取搜索参数
$search_user = isset($_GET['search_user']) ? sanitize_text_field($_GET['search_user']) : '';
$search_status = isset($_GET['search_status']) ? sanitize_text_field($_GET['search_status']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// 获取游戏记录
$db = new Siege_Game_Database();
$filters = array(
    'user' => $search_user,
    'status' => $search_status,
    'date_from' => $date_from,
    'date_to' => $date_to
);
$games = $db->get_games_with_filters($filters, $per_page, $offset);
$total_games = $db->count_games_with_filters($filters);
$total_pages = ceil($total_games / $per_page);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- 搜索过滤器 -->
    <div class="siege-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="siege-game-records">
            
            <div class="filter-row">
                <input type="text" name="search_user" placeholder="<?php _e('搜索用户...', 'siege-board-game'); ?>" 
                       value="<?php echo esc_attr($search_user); ?>">
                
                <select name="search_status">
                    <option value=""><?php _e('所有状态', 'siege-board-game'); ?></option>
                    <option value="active" <?php selected($search_status, 'active'); ?>><?php _e('进行中', 'siege-board-game'); ?></option>
                    <option value="completed" <?php selected($search_status, 'completed'); ?>><?php _e('已完成', 'siege-board-game'); ?></option>
                    <option value="paused" <?php selected($search_status, 'paused'); ?>><?php _e('已暂停', 'siege-board-game'); ?></option>
                </select>
                
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" 
                       placeholder="<?php _e('开始日期', 'siege-board-game'); ?>">
                
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" 
                       placeholder="<?php _e('结束日期', 'siege-board-game'); ?>">
                
                <input type="submit" class="button" value="<?php _e('搜索', 'siege-board-game'); ?>">
                <a href="<?php echo admin_url('admin.php?page=siege-game-records'); ?>" class="button">
                    <?php _e('重置', 'siege-board-game'); ?>
                </a>
            </div>
        </form>
    </div>
    
    <!-- 游戏记录表格 -->
    <form method="post" action="">
        <?php wp_nonce_field('bulk_delete_games'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="action">
                    <option value=""><?php _e('批量操作', 'siege-board-game'); ?></option>
                    <option value="bulk_delete"><?php _e('删除', 'siege-board-game'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php _e('应用', 'siege-board-game'); ?>" 
                       onclick="return confirm('<?php _e('确定要执行此操作吗？', 'siege-board-game'); ?>')">
            </div>
            
            <div class="tablenav-pages">
                <?php if ($total_pages > 1): ?>
                    <span class="displaying-num">
                        <?php printf(__('共 %s 项', 'siege-board-game'), number_format($total_games)); ?>
                    </span>
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ));
                    ?>
                <?php endif; ?>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th class="manage-column"><?php _e('游戏ID', 'siege-board-game'); ?></th>
                    <th class="manage-column"><?php _e('玩家', 'siege-board-game'); ?></th>
                    <th class="manage-column"><?php _e('状态', 'siege-board-game'); ?></th>
                    <th class="manage-column"><?php _e('开始时间', 'siege-board-game'); ?></th>
                    <th class="manage-column"><?php _e('结束时间', 'siege-board-game'); ?></th>
                    <th class="manage-column"><?php _e('游戏时长', 'siege-board-game'); ?></th>
                    <th class="manage-column"><?php _e('移动次数', 'siege-board-game'); ?></th>
                    <th class="manage-column"><?php _e('操作', 'siege-board-game'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($games)): ?>
                    <?php foreach ($games as $game): ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="game_ids[]" value="<?php echo esc_attr($game->id); ?>">
                            </th>
                            <td><?php echo esc_html($game->id); ?></td>
                            <td>
                                <?php 
                                $user = get_user_by('id', $game->user_id);
                                if ($user) {
                                    echo '<a href="' . get_edit_user_link($user->ID) . '">' . esc_html($user->display_name) . '</a>';
                                } else {
                                    echo '<span class="unknown-user">' . __('未知用户', 'siege-board-game') . '</span>';
                                }
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
                                <?php 
                                if ($game->updated_at && $game->status === 'completed') {
                                    echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $game->updated_at));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($game->updated_at && $game->status === 'completed') {
                                    $duration = strtotime($game->updated_at) - strtotime($game->created_at);
                                    echo esc_html(gmdate('H:i:s', $duration));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $move_count = $db->get_game_move_count($game->id);
                                echo esc_html($move_count);
                                ?>
                            </td>
                            <td>
                                <a href="#" class="button button-small view-game" data-game-id="<?php echo esc_attr($game->id); ?>">
                                    <?php _e('查看', 'siege-board-game'); ?>
                                </a>
                                <a href="#" class="button button-small delete-game" data-game-id="<?php echo esc_attr($game->id); ?>">
                                    <?php _e('删除', 'siege-board-game'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="no-items"><?php _e('未找到游戏记录', 'siege-board-game'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php if ($total_pages > 1): ?>
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ));
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // 全选/取消全选
    $('#cb-select-all').on('change', function() {
        $('input[name="game_ids[]"]').prop('checked', this.checked);
    });
    
    // 查看游戏详情
    $('.view-game').on('click', function(e) {
        e.preventDefault();
        var gameId = $(this).data('game-id');
        
        // 创建模态框显示游戏详情
        var modal = $('<div class="siege-modal"><div class="siege-modal-content"><span class="siege-modal-close">&times;</span><div class="siege-modal-body">加载中...</div></div></div>');
        $('body').append(modal);
        
        // 加载游戏详情
        $.post(ajaxurl, {
            action: 'siege_game_action',
            game_action: 'get_game_details',
            game_id: gameId,
            nonce: '<?php echo wp_create_nonce('siege_game_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                modal.find('.siege-modal-body').html(response.data.html);
            } else {
                modal.find('.siege-modal-body').html('<?php _e('加载失败', 'siege-board-game'); ?>');
            }
        });
        
        modal.show();
    });
    
    // 关闭模态框
    $(document).on('click', '.siege-modal-close, .siege-modal', function(e) {
        if (e.target === this) {
            $('.siege-modal').remove();
        }
    });
    
    // 删除单个游戏
    $('.delete-game').on('click', function(e) {
        e.preventDefault();
        if (confirm('<?php _e('确定要删除这个游戏记录吗？', 'siege-board-game'); ?>')) {
            var gameId = $(this).data('game-id');
            var row = $(this).closest('tr');
            
            $.post(ajaxurl, {
                action: 'siege_game_action',
                game_action: 'delete_game',
                game_id: gameId,
                nonce: '<?php echo wp_create_nonce('siege_game_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    row.fadeOut(function() {
                        row.remove();
                    });
                } else {
                    alert('<?php _e('删除失败', 'siege-board-game'); ?>');
                }
            });
        }
    });
});
</script>

<style>
.siege-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px;
    margin: 20px 0;
}

.filter-row {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-row input[type="text"],
.filter-row input[type="date"],
.filter-row select {
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-completed {
    background: #cce5ff;
    color: #004085;
}

.status-paused {
    background: #fff3cd;
    color: #856404;
}

.unknown-user {
    color: #999;
    font-style: italic;
}

.siege-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.siege-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    border-radius: 5px;
    position: relative;
}

.siege-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    position: absolute;
    right: 15px;
    top: 10px;
}

.siege-modal-close:hover {
    color: #000;
}
</style>