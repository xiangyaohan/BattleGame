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
            $params[] = $user_id;
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_games,
                SUM(CASE WHEN game_result = 'win' THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN game_result = 'lose' THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN game_result = 'draw' THEN 1 ELSE 0 END) as draws,
                AVG(moves_count) as avg_moves,
                AVG(TIMESTAMPDIFF(MINUTE, started_at, finished_at)) as avg_duration
             FROM $table_name 
             $where_clause",
            $params
        ));
        
        return $stats;
    }
}