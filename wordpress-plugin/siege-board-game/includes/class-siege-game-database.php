<?php
/**
 * 数据库操作类
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siege_Game_Database {
    
    /**
     * 保存游戏状态
     */
    public static function save_game($user_id, $game_state, $ai_difficulty = 'medium') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_games';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'game_state' => json_encode($game_state),
                'ai_difficulty' => $ai_difficulty,
                'started_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * 更新游戏状态
     */
    public static function update_game($game_id, $game_state, $game_result = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_games';
        
        $update_data = array(
            'game_state' => json_encode($game_state)
        );
        
        $format = array('%s');
        
        if ($game_result !== null) {
            $update_data['game_result'] = $game_result;
            $update_data['finished_at'] = current_time('mysql');
            $format[] = '%s';
            $format[] = '%s';
        }
        
        return $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $game_id),
            $format,
            array('%d')
        );
    }
    
    /**
     * 获取游戏状态
     */
    public static function get_game($game_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_games';
        
        $game = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $game_id
        ));
        
        if ($game) {
            $game->game_state = json_decode($game->game_state, true);
        }
        
        return $game;
    }
    
    /**
     * 获取用户的游戏列表
     */
    public static function get_user_games($user_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_games';
        
        $games = $wpdb->get_results($wpdb->prepare(
            "SELECT id, game_result, started_at, finished_at, moves_count, ai_difficulty 
             FROM $table_name 
             WHERE user_id = %d 
             ORDER BY started_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
        
        return $games;
    }
    
    /**
     * 保存游戏移动
     */
    public static function save_move($game_id, $move_number, $player, $move_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_game_moves';
        
        return $wpdb->insert(
            $table_name,
            array(
                'game_id' => $game_id,
                'move_number' => $move_number,
                'player' => $player,
                'move_data' => json_encode($move_data),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * 获取游戏移动记录
     */
    public static function get_game_moves($game_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_game_moves';
        
        $moves = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE game_id = %d ORDER BY move_number ASC",
            $game_id
        ));
        
        foreach ($moves as $move) {
            $move->move_data = json_decode($move->move_data, true);
        }
        
        return $moves;
    }
    
    /**
     * 删除游戏
     */
    public static function delete_game($game_id) {
        global $wpdb;
        
        // 删除移动记录
        $moves_table = $wpdb->prefix . 'siege_game_moves';
        $wpdb->delete($moves_table, array('game_id' => $game_id), array('%d'));
        
        // 删除游戏记录
        $games_table = $wpdb->prefix . 'siege_games';
        return $wpdb->delete($games_table, array('id' => $game_id), array('%d'));
    }
    
    /**
     * 清理过期游戏
     */
    public static function cleanup_expired_games($hours = 24) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_games';
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE finished_at IS NULL AND started_at < %s",
            date('Y-m-d H:i:s', strtotime("-{$hours} hours"))
        ));
    }
    
    /**
     * 获取游戏统计
     */
    public static function get_game_stats($user_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_games';
        
        $where_clause = '';
        $params = array();
        
        if ($user_id) {
            $where_clause = 'WHERE user_id = %d';
            $params[] = intval($user_id);
        }
        
        $query = "SELECT 
            COUNT(*) as total_games,
            SUM(CASE WHEN game_result = 'win' THEN 1 ELSE 0 END) as wins,
            SUM(CASE WHEN game_result = 'lose' THEN 1 ELSE 0 END) as losses,
            SUM(CASE WHEN game_result = 'draw' THEN 1 ELSE 0 END) as draws,
            AVG(moves_count) as avg_moves,
            AVG(TIMESTAMPDIFF(MINUTE, started_at, finished_at)) as avg_duration
         FROM $table_name 
         $where_clause";
        
        if (!empty($params)) {
            $stats = $wpdb->get_row($wpdb->prepare($query, $params));
        } else {
            $stats = $wpdb->get_row($query);
        }
        
        // 错误处理
        if ($wpdb->last_error) {
            error_log('Siege Game Database Error: ' . $wpdb->last_error);
            return false;
        }
        
        return $stats;
    }
    
    /**
     * 获取游戏统计数据（用于管理后台）
     */
    public static function get_game_statistics() {
        global $wpdb;
        
        $games_table = $wpdb->prefix . 'siege_games';
        
        // 获取基本统计
        $stats = array();
        
        // 总游戏数
        $total_games = $wpdb->get_var("SELECT COUNT(*) FROM $games_table");
        $stats['total_games'] = intval($total_games);
        
        // 活跃玩家数（最近30天有游戏的用户）
        $active_players = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $games_table WHERE started_at >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        $stats['active_players'] = intval($active_players);
        
        // 今日游戏数
        $today_games = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $games_table WHERE DATE(started_at) = %s",
            date('Y-m-d')
        ));
        $stats['today_games'] = intval($today_games);
        
        // 平均游戏时长（分钟）
        $avg_duration = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, started_at, finished_at)) 
             FROM $games_table 
             WHERE finished_at IS NOT NULL"
        );
        $stats['avg_duration'] = round(floatval($avg_duration), 1);
        
        // 已完成游戏数
        $completed_games = $wpdb->get_var(
            "SELECT COUNT(*) FROM $games_table WHERE finished_at IS NOT NULL"
        );
        $stats['completed_games'] = intval($completed_games);
        
        return $stats;
    }
    
    /**
     * 获取最近游戏
     */
    public static function get_recent_games($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_games';
        
        $games = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY started_at DESC LIMIT %d",
            intval($limit)
        ));
        
        if ($wpdb->last_error) {
            error_log('Siege Game Database Error: ' . $wpdb->last_error);
            return array();
        }
        
        return $games;
    }
    
    /**
     * 根据过滤条件获取游戏
     */
    public static function get_games_with_filters($filters, $limit = 20, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_games';
        $where_conditions = array();
        $params = array();
        
        // 用户过滤
        if (!empty($filters['user'])) {
            $user = get_user_by('login', sanitize_text_field($filters['user']));
            if (!$user) {
                $user = get_user_by('email', sanitize_email($filters['user']));
            }
            if ($user) {
                $where_conditions[] = 'user_id = %d';
                $params[] = $user->ID;
            }
        }
        
        // 状态过滤
        if (!empty($filters['status'])) {
            $status = sanitize_text_field($filters['status']);
            if ($status === 'active') {
                $where_conditions[] = 'finished_at IS NULL';
            } elseif ($status === 'completed') {
                $where_conditions[] = 'finished_at IS NOT NULL';
            }
        }
        
        // 日期过滤
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(started_at) >= %s';
            $params[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(started_at) <= %s';
            $params[] = sanitize_text_field($filters['date_to']);
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY started_at DESC LIMIT %d OFFSET %d";
        $params[] = intval($limit);
        $params[] = intval($offset);
        
        $games = $wpdb->get_results($wpdb->prepare($query, $params));
        
        if ($wpdb->last_error) {
            error_log('Siege Game Database Error: ' . $wpdb->last_error);
            return array();
        }
        
        return $games;
    }
    
    /**
     * 计算过滤条件下的游戏总数
     */
    public static function count_games_with_filters($filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_games';
        $where_conditions = array();
        $params = array();
        
        // 用户过滤
        if (!empty($filters['user'])) {
            $user = get_user_by('login', sanitize_text_field($filters['user']));
            if (!$user) {
                $user = get_user_by('email', sanitize_email($filters['user']));
            }
            if ($user) {
                $where_conditions[] = 'user_id = %d';
                $params[] = $user->ID;
            }
        }
        
        // 状态过滤
        if (!empty($filters['status'])) {
            $status = sanitize_text_field($filters['status']);
            if ($status === 'active') {
                $where_conditions[] = 'finished_at IS NULL';
            } elseif ($status === 'completed') {
                $where_conditions[] = 'finished_at IS NOT NULL';
            }
        }
        
        // 日期过滤
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(started_at) >= %s';
            $params[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(started_at) <= %s';
            $params[] = sanitize_text_field($filters['date_to']);
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $query = "SELECT COUNT(*) FROM $table_name $where_clause";
        
        if (!empty($params)) {
            $count = $wpdb->get_var($wpdb->prepare($query, $params));
        } else {
            $count = $wpdb->get_var($query);
        }
        
        if ($wpdb->last_error) {
            error_log('Siege Game Database Error: ' . $wpdb->last_error);
            return 0;
        }
        
        return intval($count);
    }
    
    /**
     * 获取游戏移动数量
     */
    public static function get_game_move_count($game_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'siege_game_moves';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE game_id = %d",
            intval($game_id)
        ));
        
        if ($wpdb->last_error) {
            error_log('Siege Game Database Error: ' . $wpdb->last_error);
            return 0;
        }
        
        return intval($count);
    }
}