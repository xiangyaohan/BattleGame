<?php
/**
 * AJAX处理类
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siege_Game_Ajax {
    
    /**
     * 初始化AJAX钩子
     */
    public static function init() {
        add_action('wp_ajax_siege_game_action', array(__CLASS__, 'handle_ajax_request'));
        add_action('wp_ajax_nopriv_siege_game_action', array(__CLASS__, 'handle_ajax_request'));
    }
    
    /**
     * 处理AJAX请求
     */
    public static function handle_ajax_request() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'siege_game_nonce')) {
            wp_send_json_error(__('安全验证失败', 'siege-board-game'));
        }
        
        $action = sanitize_text_field($_POST['game_action']);
        
        switch ($action) {
            case 'make_move':
                self::handle_make_move();
                break;
            case 'start_game':
                self::handle_start_game();
                break;
            case 'save_game':
                self::handle_save_game();
                break;
            case 'load_game':
                self::handle_load_game();
                break;
            case 'get_saved_games':
                self::handle_get_saved_games();
                break;
            case 'delete_saved_game':
                self::handle_delete_saved_game();
                break;
            case 'get_hint':
                self::handle_get_hint();
                break;
            case 'undo_move':
                self::handle_undo_move();
                break;
            default:
                wp_send_json_error(__('无效的操作', 'siege-board-game'));
        }
    }
    
    /**
     * 处理移动
     */
    private static function handle_make_move() {
        $move_data = json_decode(stripslashes($_POST['move_data']), true);
        $game_id = sanitize_text_field($_POST['game_id']);
        
        if (!$move_data || !$game_id) {
            wp_send_json_error(__('无效的移动数据', 'siege-board-game'));
        }
        
        // 验证移动的有效性
        $validation_result = self::validate_move($move_data);
        if (!$validation_result['valid']) {
            wp_send_json_error($validation_result['message']);
        }
        
        // 处理移动逻辑
        $result = self::process_game_move($move_data, $game_id);
        
        // 如果启用了AI，计算AI移动
        if ($result['success'] && !$result['game_over'] && isset($_POST['ai_enabled']) && $_POST['ai_enabled'] === 'true') {
            $ai_move = self::calculate_ai_move($result['new_state'], $_POST['difficulty']);
            $result['ai_move'] = $ai_move;
            
            // 处理AI移动
            if ($ai_move) {
                $ai_result = self::process_game_move($ai_move, $game_id);
                $result['new_state'] = $ai_result['new_state'];
                $result['game_over'] = $ai_result['game_over'];
                $result['winner'] = $ai_result['winner'];
            }
        }
        
        // 保存移动到数据库
        if ($result['success'] && is_user_logged_in()) {
            $user_id = get_current_user_id();
            Siege_Game_Database::save_move($game_id, $result['move_number'], 'human', $move_data);
            
            if (isset($result['ai_move'])) {
                Siege_Game_Database::save_move($game_id, $result['move_number'] + 1, 'ai', $result['ai_move']);
            }
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * 处理开始游戏
     */
    private static function handle_start_game() {
        $game_settings = json_decode(stripslashes($_POST['game_settings']), true);
        
        // 初始化新游戏状态
        $game_state = self::initialize_new_game($game_settings);
        
        // 如果用户已登录，保存游戏到数据库
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $difficulty = isset($game_settings['difficulty']) ? $game_settings['difficulty'] : 'medium';
            $game_id = Siege_Game_Database::save_game($user_id, $game_state, $difficulty);
            $game_state['game_id'] = $game_id;
        }
        
        wp_send_json_success($game_state);
    }
    
    /**
     * 处理保存游戏
     */
    private static function handle_save_game() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('请先登录', 'siege-board-game'));
        }
        
        $game_state = json_decode(stripslashes($_POST['game_state']), true);
        $game_id = sanitize_text_field($_POST['game_id']);
        
        if (!$game_state) {
            wp_send_json_error(__('无效的游戏状态', 'siege-board-game'));
        }
        
        $user_id = get_current_user_id();
        
        if ($game_id && is_numeric($game_id)) {
            // 更新现有游戏
            $result = Siege_Game_Database::update_game($game_id, $game_state);
        } else {
            // 创建新游戏记录
            $difficulty = isset($_POST['difficulty']) ? sanitize_text_field($_POST['difficulty']) : 'medium';
            $result = Siege_Game_Database::save_game($user_id, $game_state, $difficulty);
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('游戏已保存', 'siege-board-game'),
                'game_id' => $result
            ));
        } else {
            wp_send_json_error(__('保存失败', 'siege-board-game'));
        }
    }
    
    /**
     * 处理加载游戏
     */
    private static function handle_load_game() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('请先登录', 'siege-board-game'));
        }
        
        $game_id = intval($_POST['game_id']);
        
        if (!$game_id) {
            wp_send_json_error(__('无效的游戏ID', 'siege-board-game'));
        }
        
        $game = Siege_Game_Database::get_game($game_id);
        
        if (!$game || $game->user_id != get_current_user_id()) {
            wp_send_json_error(__('游戏不存在或无权限', 'siege-board-game'));
        }
        
        wp_send_json_success(array(
            'game_state' => $game->game_state,
            'game_id' => $game->id,
            'message' => __('游戏已加载', 'siege-board-game')
        ));
    }
    
    /**
     * 获取保存的游戏列表
     */
    private static function handle_get_saved_games() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('请先登录', 'siege-board-game'));
        }
        
        $user_id = get_current_user_id();
        $games = Siege_Game_Database::get_user_games($user_id, 20);
        
        $formatted_games = array();
        foreach ($games as $game) {
            $formatted_games[] = array(
                'id' => $game->id,
                'started_at' => $game->started_at,
                'finished_at' => $game->finished_at,
                'moves_count' => $game->moves_count,
                'game_result' => $game->game_result,
                'ai_difficulty' => $game->ai_difficulty,
                'formatted_date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($game->started_at))
            );
        }
        
        wp_send_json_success($formatted_games);
    }
    
    /**
     * 删除保存的游戏
     */
    private static function handle_delete_saved_game() {
        if (!is_user_logged_in()) {
            wp_send_json_error(__('请先登录', 'siege-board-game'));
        }
        
        $game_id = intval($_POST['game_id']);
        
        if (!$game_id) {
            wp_send_json_error(__('无效的游戏ID', 'siege-board-game'));
        }
        
        // 验证游戏所有权
        $game = Siege_Game_Database::get_game($game_id);
        if (!$game || $game->user_id != get_current_user_id()) {
            wp_send_json_error(__('游戏不存在或无权限', 'siege-board-game'));
        }
        
        $result = Siege_Game_Database::delete_game($game_id);
        
        if ($result !== false) {
            wp_send_json_success(__('游戏已删除', 'siege-board-game'));
        } else {
            wp_send_json_error(__('删除失败', 'siege-board-game'));
        }
    }
    
    /**
     * 获取提示
     */
    private static function handle_get_hint() {
        $game_state = json_decode(stripslashes($_POST['game_state']), true);
        
        if (!$game_state) {
            wp_send_json_error(__('无效的游戏状态', 'siege-board-game'));
        }
        
        $hint = self::calculate_hint($game_state);
        
        wp_send_json_success(array(
            'hint' => $hint,
            'message' => __('提示已生成', 'siege-board-game')
        ));
    }
    
    /**
     * 处理撤销移动
     */
    private static function handle_undo_move() {
        $game_id = sanitize_text_field($_POST['game_id']);
        
        if (!$game_id || !is_user_logged_in()) {
            wp_send_json_error(__('无法撤销移动', 'siege-board-game'));
        }
        
        // 获取游戏移动历史
        $moves = Siege_Game_Database::get_game_moves($game_id);
        
        if (count($moves) < 2) {
            wp_send_json_error(__('没有可撤销的移动', 'siege-board-game'));
        }
        
        // 重建游戏状态（排除最后两步移动）
        $game_state = self::rebuild_game_state($moves, -2);
        
        wp_send_json_success(array(
            'game_state' => $game_state,
            'message' => __('移动已撤销', 'siege-board-game')
        ));
    }
    
    /**
     * 验证移动的有效性
     */
    private static function validate_move($move_data) {
        // 基本验证
        if (!isset($move_data['from']) || !isset($move_data['to'])) {
            return array('valid' => false, 'message' => __('移动数据不完整', 'siege-board-game'));
        }
        
        $from = $move_data['from'];
        $to = $move_data['to'];
        
        // 检查坐标范围
        if ($from['row'] < 0 || $from['row'] > 10 || $from['col'] < 0 || $from['col'] > 10 ||
            $to['row'] < 0 || $to['row'] > 10 || $to['col'] < 0 || $to['col'] > 10) {
            return array('valid' => false, 'message' => __('移动超出棋盘范围', 'siege-board-game'));
        }
        
        // 检查是否为相邻移动
        $row_diff = abs($to['row'] - $from['row']);
        $col_diff = abs($to['col'] - $from['col']);
        
        if (($row_diff == 1 && $col_diff == 0) || ($row_diff == 0 && $col_diff == 1)) {
            return array('valid' => true);
        }
        
        return array('valid' => false, 'message' => __('只能移动到相邻位置', 'siege-board-game'));
    }
    
    /**
     * 处理游戏移动逻辑
     */
    private static function process_game_move($move_data, $game_id) {
        // 这里实现具体的游戏逻辑
        // 包括移动棋子、检查捕获、检查胜利条件等
        
        // 占位符实现
        return array(
            'success' => true,
            'new_state' => array(
                'board' => array(), // 新的棋盘状态
                'current_player' => 'ai', // 切换玩家
                'move_count' => 1
            ),
            'captured_pieces' => array(),
            'game_over' => false,
            'winner' => null,
            'move_number' => 1
        );
    }
    
    /**
     * 计算AI移动
     */
    private static function calculate_ai_move($game_state, $difficulty = 'medium') {
        // 根据难度级别实现不同的AI算法
        switch ($difficulty) {
            case 'easy':
                return self::calculate_easy_ai_move($game_state);
            case 'hard':
                return self::calculate_hard_ai_move($game_state);
            case 'medium':
            default:
                return self::calculate_medium_ai_move($game_state);
        }
    }
    
    /**
     * 简单AI移动
     */
    private static function calculate_easy_ai_move($game_state) {
        // 随机选择一个有效移动
        $possible_moves = self::get_possible_moves($game_state, 'ai');
        
        if (empty($possible_moves)) {
            return null;
        }
        
        return $possible_moves[array_rand($possible_moves)];
    }
    
    /**
     * 中等AI移动
     */
    private static function calculate_medium_ai_move($game_state) {
        // 实现基本的策略逻辑
        $possible_moves = self::get_possible_moves($game_state, 'ai');
        
        if (empty($possible_moves)) {
            return null;
        }
        
        // 优先考虑能够捕获对方棋子的移动
        foreach ($possible_moves as $move) {
            if (self::move_captures_piece($move, $game_state)) {
                return $move;
            }
        }
        
        // 否则选择随机移动
        return $possible_moves[array_rand($possible_moves)];
    }
    
    /**
     * 困难AI移动
     */
    private static function calculate_hard_ai_move($game_state) {
        // 实现更高级的AI算法（如minimax）
        return self::calculate_medium_ai_move($game_state); // 占位符
    }
    
    /**
     * 获取可能的移动
     */
    private static function get_possible_moves($game_state, $player) {
        // 实现获取所有可能移动的逻辑
        return array(); // 占位符
    }
    
    /**
     * 检查移动是否能捕获棋子
     */
    private static function move_captures_piece($move, $game_state) {
        // 实现检查捕获逻辑
        return false; // 占位符
    }
    
    /**
     * 初始化新游戏
     */
    private static function initialize_new_game($settings) {
        // 创建初始棋盘状态
        $board = array();
        for ($row = 0; $row < 11; $row++) {
            for ($col = 0; $col < 11; $col++) {
                $board[$row][$col] = null;
            }
        }
        
        // 放置防守者（包括国王）
        $board[5][5] = 'king'; // 国王在中央
        
        // 放置防守者
        $defender_positions = [
            [3, 5], [4, 4], [4, 5], [4, 6], [5, 3], [5, 4], [5, 6], [5, 7], [6, 4], [6, 5], [6, 6], [7, 5]
        ];
        
        foreach ($defender_positions as $pos) {
            $board[$pos[0]][$pos[1]] = 'defender';
        }
        
        // 放置攻击者
        $attacker_positions = [
            [0, 3], [0, 4], [0, 5], [0, 6], [0, 7], [1, 5],
            [3, 0], [4, 0], [5, 0], [6, 0], [7, 0], [5, 1],
            [9, 5], [10, 3], [10, 4], [10, 5], [10, 6], [10, 7],
            [5, 9], [5, 10], [3, 10], [4, 10], [6, 10], [7, 10]
        ];
        
        foreach ($attacker_positions as $pos) {
            $board[$pos[0]][$pos[1]] = 'attacker';
        }
        
        return array(
            'board' => $board,
            'current_player' => 'human',
            'move_count' => 0,
            'game_started' => true,
            'player_role' => isset($settings['player_role']) ? $settings['player_role'] : 'attacker',
            'ai_role' => isset($settings['player_role']) && $settings['player_role'] === 'attacker' ? 'defender' : 'attacker'
        );
    }
    
    /**
     * 计算提示
     */
    private static function calculate_hint($game_state) {
        // 实现提示算法
        $possible_moves = self::get_possible_moves($game_state, $game_state['current_player']);
        
        if (empty($possible_moves)) {
            return array(
                'type' => 'message',
                'content' => __('没有可用的移动', 'siege-board-game')
            );
        }
        
        // 返回一个建议的移动
        $suggested_move = $possible_moves[0];
        
        return array(
            'type' => 'move',
            'from' => $suggested_move['from'],
            'to' => $suggested_move['to'],
            'reason' => __('建议的移动', 'siege-board-game')
        );
    }
    
    /**
     * 重建游戏状态
     */
    private static function rebuild_game_state($moves, $exclude_last = 0) {
        // 从移动历史重建游戏状态
        $initial_state = self::initialize_new_game(array());
        
        $moves_to_apply = array_slice($moves, 0, $exclude_last);
        
        foreach ($moves_to_apply as $move) {
            // 应用每个移动
            $initial_state = self::apply_move_to_state($initial_state, $move->move_data);
        }
        
        return $initial_state;
    }
    
    /**
     * 将移动应用到游戏状态
     */
    private static function apply_move_to_state($state, $move_data) {
        // 实现移动应用逻辑
        return $state; // 占位符
    }
}

// 初始化AJAX处理
Siege_Game_Ajax::init();