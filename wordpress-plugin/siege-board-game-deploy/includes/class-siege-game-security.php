<?php
/**
 * Security and Performance Optimization Class
 * 
 * Handles security measures and performance optimizations for the Siege Board Game plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siege_Game_Security {
    
    /**
     * Initialize security measures
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'setup_security'));
        add_action('wp_ajax_siege_game_action', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_nopriv_siege_game_action', array(__CLASS__, 'validate_ajax_request'), 1);
        add_filter('siege_game_save_data', array(__CLASS__, 'sanitize_game_data'));
        add_action('siege_game_cleanup', array(__CLASS__, 'cleanup_expired_data'));
    }
    
    /**
     * Setup basic security measures
     */
    public static function setup_security() {
        // Prevent direct access to plugin files
        if (!defined('ABSPATH')) {
            exit;
        }
        
        // Add security headers
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
        }
        
        // Schedule cleanup tasks
        if (!wp_next_scheduled('siege_game_cleanup')) {
            wp_schedule_event(time(), 'daily', 'siege_game_cleanup');
        }
    }
    
    /**
     * Validate AJAX requests
     */
    public static function validate_ajax_request() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'siege_game_nonce')) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Security check failed'
            )));
        }
        
        // Rate limiting
        if (!self::check_rate_limit()) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Too many requests'
            )));
        }
        
        // Validate user permissions
        if (!self::validate_user_permissions()) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => 'Insufficient permissions'
            )));
        }
    }
    
    /**
     * Check rate limiting
     */
    private static function check_rate_limit() {
        $user_id = get_current_user_id();
        $ip_address = self::get_client_ip();
        $key = 'siege_game_rate_limit_' . ($user_id ? $user_id : $ip_address);
        
        $requests = get_transient($key);
        if ($requests === false) {
            $requests = 0;
        }
        
        // Allow 60 requests per minute
        if ($requests >= 60) {
            return false;
        }
        
        set_transient($key, $requests + 1, 60);
        return true;
    }
    
    /**
     * Validate user permissions
     */
    private static function validate_user_permissions() {
        // Allow all users to play the game
        // Additional permission checks can be added here
        return true;
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Sanitize game data
     */
    public static function sanitize_game_data($data) {
        if (!is_array($data)) {
            return array();
        }
        
        $sanitized = array();
        
        // Sanitize each field
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'board':
                    $sanitized[$key] = self::sanitize_board_data($value);
                    break;
                case 'currentPlayer':
                    $sanitized[$key] = in_array($value, array('red', 'black')) ? $value : 'red';
                    break;
                case 'gameState':
                    $sanitized[$key] = in_array($value, array('playing', 'paused', 'finished')) ? $value : 'playing';
                    break;
                case 'moves':
                    $sanitized[$key] = self::sanitize_moves_data($value);
                    break;
                case 'score':
                    $sanitized[$key] = self::sanitize_score_data($value);
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize board data
     */
    private static function sanitize_board_data($board) {
        if (!is_array($board)) {
            return array();
        }
        
        $sanitized_board = array();
        foreach ($board as $row => $columns) {
            if (!is_array($columns)) {
                continue;
            }
            foreach ($columns as $col => $piece) {
                if (is_string($piece) && preg_match('/^[a-zA-Z0-9_-]*$/', $piece)) {
                    $sanitized_board[$row][$col] = $piece;
                }
            }
        }
        
        return $sanitized_board;
    }
    
    /**
     * Sanitize moves data
     */
    private static function sanitize_moves_data($moves) {
        if (!is_array($moves)) {
            return array();
        }
        
        $sanitized_moves = array();
        foreach ($moves as $move) {
            if (is_array($move)) {
                $sanitized_move = array();
                foreach ($move as $key => $value) {
                    $sanitized_move[sanitize_key($key)] = sanitize_text_field($value);
                }
                $sanitized_moves[] = $sanitized_move;
            }
        }
        
        return $sanitized_moves;
    }
    
    /**
     * Sanitize score data
     */
    private static function sanitize_score_data($score) {
        if (!is_array($score)) {
            return array('red' => 0, 'black' => 0);
        }
        
        return array(
            'red' => intval($score['red'] ?? 0),
            'black' => intval($score['black'] ?? 0)
        );
    }
    
    /**
     * Cleanup expired data
     */
    public static function cleanup_expired_data() {
        global $wpdb;
        
        // Clean up old games (older than 30 days)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}siege_games 
             WHERE status = 'finished' 
             AND updated_at < %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // Clean up orphaned moves
        $wpdb->query(
            "DELETE m FROM {$wpdb->prefix}siege_game_moves m
             LEFT JOIN {$wpdb->prefix}siege_games g ON m.game_id = g.id
             WHERE g.id IS NULL"
        );
        
        // Clean up expired transients
        delete_expired_transients();
        
        // Log cleanup
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Siege Game: Cleanup completed at ' . current_time('mysql'));
        }
    }
    
    /**
     * Validate game move
     */
    public static function validate_move($move_data) {
        if (!is_array($move_data)) {
            return false;
        }
        
        // Required fields
        $required_fields = array('from', 'to', 'piece', 'player');
        foreach ($required_fields as $field) {
            if (!isset($move_data[$field])) {
                return false;
            }
        }
        
        // Validate positions
        if (!self::validate_position($move_data['from']) || 
            !self::validate_position($move_data['to'])) {
            return false;
        }
        
        // Validate player
        if (!in_array($move_data['player'], array('red', 'black'))) {
            return false;
        }
        
        // Validate piece
        if (!self::validate_piece($move_data['piece'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate board position
     */
    private static function validate_position($position) {
        if (!is_string($position)) {
            return false;
        }
        
        // Position should be in format like "a1", "b2", etc.
        return preg_match('/^[a-j][0-9]$/', $position);
    }
    
    /**
     * Validate piece type
     */
    private static function validate_piece($piece) {
        $valid_pieces = array(
            'red_general', 'red_advisor', 'red_elephant', 'red_horse', 
            'red_chariot', 'red_cannon', 'red_soldier',
            'black_general', 'black_advisor', 'black_elephant', 'black_horse',
            'black_chariot', 'black_cannon', 'black_soldier'
        );
        
        return in_array($piece, $valid_pieces);
    }
    
    /**
     * Encrypt sensitive data
     */
    public static function encrypt_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data);
        }
        
        $key = self::get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public static function decrypt_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data);
        }
        
        $data = base64_decode($encrypted_data);
        $key = self::get_encryption_key();
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get encryption key
     */
    private static function get_encryption_key() {
        $key = get_option('siege_game_encryption_key');
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('siege_game_encryption_key', $key);
        }
        return $key;
    }
    
    /**
     * Log security events
     */
    public static function log_security_event($event, $details = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'details' => $details
        );
        
        error_log('Siege Game Security: ' . json_encode($log_entry));
    }
    
    /**
     * Check for suspicious activity
     */
    public static function check_suspicious_activity($user_id, $action) {
        $key = 'siege_game_activity_' . $user_id . '_' . $action;
        $count = get_transient($key);
        
        if ($count === false) {
            $count = 0;
        }
        
        $count++;
        set_transient($key, $count, 300); // 5 minutes
        
        // Flag suspicious activity (more than 100 actions in 5 minutes)
        if ($count > 100) {
            self::log_security_event('suspicious_activity', array(
                'user_id' => $user_id,
                'action' => $action,
                'count' => $count
            ));
            return true;
        }
        
        return false;
    }
}

// Initialize security measures
Siege_Game_Security::init()