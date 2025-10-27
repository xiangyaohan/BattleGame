<?php
/**
 * WordPress Plugin Deployment Test Script
 * 
 * This script tests the complete deployment and functionality of the Siege Board Game plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // For testing outside WordPress, define basic constants
    define('ABSPATH', dirname(__FILE__) . '/');
    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);
}

class Siege_Game_Deployment_Test {
    
    private $test_results = array();
    private $errors = array();
    
    public function __construct() {
        $this->run_all_tests();
        $this->display_results();
    }
    
    /**
     * Run all deployment tests
     */
    private function run_all_tests() {
        echo "<h1>Siege Board Game Plugin - Deployment Test</h1>\n";
        echo "<div style='font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px;'>\n";
        
        $this->test_file_structure();
        $this->test_plugin_headers();
        $this->test_class_files();
        $this->test_assets();
        $this->test_database_schema();
        $this->test_security_features();
        $this->test_performance_features();
        $this->test_wordpress_integration();
        $this->test_responsive_design();
        $this->test_admin_interface();
    }
    
    /**
     * Test file structure
     */
    private function test_file_structure() {
        $this->add_test_header("File Structure Test");
        
        $required_files = array(
            'siege-board-game.php' => 'Main plugin file',
            'uninstall.php' => 'Uninstall script',
            'README.md' => 'Documentation',
            'INSTALLATION.md' => 'Installation guide',
            'includes/class-siege-game-database.php' => 'Database class',
            'includes/class-siege-game-ajax.php' => 'AJAX handler',
            'includes/class-siege-game-shortcode.php' => 'Shortcode handler',
            'includes/class-siege-game-security.php' => 'Security class',
            'includes/class-siege-game-performance.php' => 'Performance class',
            'includes/views/game-board.php' => 'Game board view',
            'admin/class-siege-game-admin.php' => 'Admin class',
            'admin/views/admin-main.php' => 'Admin main page',
            'admin/views/admin-settings.php' => 'Admin settings page',
            'admin/css/admin-style.css' => 'Admin styles',
            'css/siege-game.css' => 'Game styles',
            'js/siege-game.js' => 'Game JavaScript'
        );
        
        $required_directories = array(
            'includes/',
            'includes/views/',
            'admin/',
            'admin/views/',
            'admin/css/',
            'admin/js/',
            'css/',
            'js/',
            'languages/'
        );
        
        // Test directories
        foreach ($required_directories as $dir) {
            $path = dirname(__FILE__) . '/' . $dir;
            if (is_dir($path)) {
                $this->add_success("Directory exists: {$dir}");
            } else {
                $this->add_error("Missing directory: {$dir}");
            }
        }
        
        // Test files
        foreach ($required_files as $file => $description) {
            $path = dirname(__FILE__) . '/' . $file;
            if (file_exists($path)) {
                $size = filesize($path);
                $this->add_success("File exists: {$file} ({$description}) - {$size} bytes");
            } else {
                $this->add_error("Missing file: {$file} ({$description})");
            }
        }
    }
    
    /**
     * Test plugin headers
     */
    private function test_plugin_headers() {
        $this->add_test_header("Plugin Headers Test");
        
        $main_file = dirname(__FILE__) . '/siege-board-game.php';
        if (file_exists($main_file)) {
            $content = file_get_contents($main_file);
            
            $required_headers = array(
                'Plugin Name:' => 'Plugin Name',
                'Description:' => 'Description',
                'Version:' => 'Version',
                'Author:' => 'Author',
                'Text Domain:' => 'Text Domain'
            );
            
            foreach ($required_headers as $header => $name) {
                if (strpos($content, $header) !== false) {
                    $this->add_success("Header found: {$name}");
                } else {
                    $this->add_error("Missing header: {$name}");
                }
            }
            
            // Check for security
            if (strpos($content, "if (!defined('ABSPATH'))") !== false) {
                $this->add_success("Security check: ABSPATH protection found");
            } else {
                $this->add_error("Security issue: Missing ABSPATH protection");
            }
        }
    }
    
    /**
     * Test class files
     */
    private function test_class_files() {
        $this->add_test_header("Class Files Test");
        
        $class_files = array(
            'includes/class-siege-game-database.php' => 'Siege_Game_Database',
            'includes/class-siege-game-ajax.php' => 'Siege_Game_Ajax',
            'includes/class-siege-game-shortcode.php' => 'Siege_Game_Shortcode',
            'includes/class-siege-game-security.php' => 'Siege_Game_Security',
            'includes/class-siege-game-performance.php' => 'Siege_Game_Performance',
            'admin/class-siege-game-admin.php' => 'Siege_Game_Admin'
        );
        
        foreach ($class_files as $file => $class_name) {
            $path = dirname(__FILE__) . '/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);
                
                if (strpos($content, "class {$class_name}") !== false) {
                    $this->add_success("Class defined: {$class_name} in {$file}");
                } else {
                    $this->add_error("Class not found: {$class_name} in {$file}");
                }
                
                // Check for security
                if (strpos($content, "if (!defined('ABSPATH'))") !== false) {
                    $this->add_success("Security: {$file} has ABSPATH protection");
                } else {
                    $this->add_warning("Security: {$file} missing ABSPATH protection");
                }
            }
        }
    }
    
    /**
     * Test assets
     */
    private function test_assets() {
        $this->add_test_header("Assets Test");
        
        $css_file = dirname(__FILE__) . '/css/siege-game.css';
        if (file_exists($css_file)) {
            $css_content = file_get_contents($css_file);
            $css_size = filesize($css_file);
            
            $this->add_success("CSS file exists: siege-game.css ({$css_size} bytes)");
            
            // Check for responsive design
            if (strpos($css_content, '@media') !== false) {
                $this->add_success("Responsive design: Media queries found in CSS");
            } else {
                $this->add_warning("Responsive design: No media queries found");
            }
            
            // Check for animations
            if (strpos($css_content, '@keyframes') !== false || strpos($css_content, 'animation:') !== false) {
                $this->add_success("Animations: CSS animations found");
            } else {
                $this->add_info("Animations: No CSS animations found");
            }
        }
        
        $js_file = dirname(__FILE__) . '/js/siege-game.js';
        if (file_exists($js_file)) {
            $js_content = file_get_contents($js_file);
            $js_size = filesize($js_file);
            
            $this->add_success("JavaScript file exists: siege-game.js ({$js_size} bytes)");
            
            // Check for AJAX
            if (strpos($js_content, 'ajax') !== false || strpos($js_content, 'XMLHttpRequest') !== false) {
                $this->add_success("AJAX: JavaScript AJAX functionality found");
            } else {
                $this->add_warning("AJAX: No AJAX functionality found in JavaScript");
            }
            
            // Check for game logic
            if (strpos($js_content, 'SiegeGame') !== false) {
                $this->add_success("Game logic: SiegeGame object found");
            } else {
                $this->add_warning("Game logic: SiegeGame object not found");
            }
        }
    }
    
    /**
     * Test database schema
     */
    private function test_database_schema() {
        $this->add_test_header("Database Schema Test");
        
        $database_file = dirname(__FILE__) . '/includes/class-siege-game-database.php';
        if (file_exists($database_file)) {
            $content = file_get_contents($database_file);
            
            // Check for table creation
            if (strpos($content, 'CREATE TABLE') !== false) {
                $this->add_success("Database: Table creation SQL found");
            } else {
                $this->add_error("Database: No table creation SQL found");
            }
            
            // Check for CRUD operations
            $crud_methods = array('save_game', 'get_game', 'update_game', 'delete_game');
            foreach ($crud_methods as $method) {
                if (strpos($content, $method) !== false) {
                    $this->add_success("Database: {$method} method found");
                } else {
                    $this->add_warning("Database: {$method} method not found");
                }
            }
            
            // Check for user integration
            if (strpos($content, 'user_id') !== false) {
                $this->add_success("User integration: user_id field found in database operations");
            } else {
                $this->add_error("User integration: user_id field not found");
            }
        }
    }
    
    /**
     * Test security features
     */
    private function test_security_features() {
        $this->add_test_header("Security Features Test");
        
        $security_file = dirname(__FILE__) . '/includes/class-siege-game-security.php';
        if (file_exists($security_file)) {
            $content = file_get_contents($security_file);
            
            $security_features = array(
                'nonce' => 'Nonce verification',
                'sanitize' => 'Data sanitization',
                'validate' => 'Data validation',
                'rate_limit' => 'Rate limiting',
                'escape' => 'Output escaping'
            );
            
            foreach ($security_features as $feature => $description) {
                if (strpos($content, $feature) !== false) {
                    $this->add_success("Security: {$description} found");
                } else {
                    $this->add_warning("Security: {$description} not found");
                }
            }
        } else {
            $this->add_error("Security: Security class file not found");
        }
    }
    
    /**
     * Test performance features
     */
    private function test_performance_features() {
        $this->add_test_header("Performance Features Test");
        
        $performance_file = dirname(__FILE__) . '/includes/class-siege-game-performance.php';
        if (file_exists($performance_file)) {
            $content = file_get_contents($performance_file);
            
            $performance_features = array(
                'cache' => 'Caching system',
                'minify' => 'Asset minification',
                'optimize' => 'Optimization',
                'compress' => 'Compression',
                'lazy' => 'Lazy loading'
            );
            
            foreach ($performance_features as $feature => $description) {
                if (strpos($content, $feature) !== false) {
                    $this->add_success("Performance: {$description} found");
                } else {
                    $this->add_warning("Performance: {$description} not found");
                }
            }
        } else {
            $this->add_error("Performance: Performance class file not found");
        }
    }
    
    /**
     * Test WordPress integration
     */
    private function test_wordpress_integration() {
        $this->add_test_header("WordPress Integration Test");
        
        $main_file = dirname(__FILE__) . '/siege-board-game.php';
        if (file_exists($main_file)) {
            $content = file_get_contents($main_file);
            
            $wp_features = array(
                'add_action' => 'WordPress hooks',
                'add_filter' => 'WordPress filters',
                'add_shortcode' => 'Shortcode registration',
                'wp_enqueue_script' => 'Script enqueuing',
                'wp_enqueue_style' => 'Style enqueuing',
                'admin_menu' => 'Admin menu',
                'wp_ajax' => 'AJAX handlers'
            );
            
            foreach ($wp_features as $feature => $description) {
                if (strpos($content, $feature) !== false) {
                    $this->add_success("WordPress: {$description} found");
                } else {
                    $this->add_warning("WordPress: {$description} not found in main file");
                }
            }
        }
        
        // Test shortcode file
        $shortcode_file = dirname(__FILE__) . '/includes/class-siege-game-shortcode.php';
        if (file_exists($shortcode_file)) {
            $content = file_get_contents($shortcode_file);
            if (strpos($content, 'siege_game') !== false) {
                $this->add_success("Shortcode: [siege_game] shortcode implementation found");
            } else {
                $this->add_error("Shortcode: [siege_game] shortcode implementation not found");
            }
        }
    }
    
    /**
     * Test responsive design
     */
    private function test_responsive_design() {
        $this->add_test_header("Responsive Design Test");
        
        $css_file = dirname(__FILE__) . '/css/siege-game.css';
        if (file_exists($css_file)) {
            $content = file_get_contents($css_file);
            
            // Check for mobile breakpoints
            $breakpoints = array('768px', '480px', '320px');
            foreach ($breakpoints as $breakpoint) {
                if (strpos($content, $breakpoint) !== false) {
                    $this->add_success("Responsive: {$breakpoint} breakpoint found");
                } else {
                    $this->add_info("Responsive: {$breakpoint} breakpoint not found");
                }
            }
            
            // Check for flexible layouts
            $responsive_features = array('flex', 'grid', 'max-width', 'min-width');
            foreach ($responsive_features as $feature) {
                if (strpos($content, $feature) !== false) {
                    $this->add_success("Responsive: {$feature} layout found");
                } else {
                    $this->add_info("Responsive: {$feature} layout not found");
                }
            }
        }
    }
    
    /**
     * Test admin interface
     */
    private function test_admin_interface() {
        $this->add_test_header("Admin Interface Test");
        
        $admin_files = array(
            'admin/views/admin-main.php' => 'Main admin page',
            'admin/views/admin-settings.php' => 'Settings page',
            'admin/css/admin-style.css' => 'Admin styles'
        );
        
        foreach ($admin_files as $file => $description) {
            $path = dirname(__FILE__) . '/' . $file;
            if (file_exists($path)) {
                $this->add_success("Admin: {$description} exists");
                
                if (strpos($file, '.php') !== false) {
                    $content = file_get_contents($path);
                    if (strpos($content, 'siege') !== false) {
                        $this->add_success("Admin: {$description} contains game-related content");
                    }
                }
            } else {
                $this->add_error("Admin: {$description} missing");
            }
        }
    }
    
    /**
     * Add test header
     */
    private function add_test_header($title) {
        echo "<h2 style='color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-top: 30px;'>{$title}</h2>\n";
    }
    
    /**
     * Add success message
     */
    private function add_success($message) {
        echo "<div style='color: #46b450; margin: 5px 0;'>✓ {$message}</div>\n";
        $this->test_results['success'][] = $message;
    }
    
    /**
     * Add error message
     */
    private function add_error($message) {
        echo "<div style='color: #dc3232; margin: 5px 0;'>✗ {$message}</div>\n";
        $this->test_results['error'][] = $message;
        $this->errors[] = $message;
    }
    
    /**
     * Add warning message
     */
    private function add_warning($message) {
        echo "<div style='color: #ffb900; margin: 5px 0;'>⚠ {$message}</div>\n";
        $this->test_results['warning'][] = $message;
    }
    
    /**
     * Add info message
     */
    private function add_info($message) {
        echo "<div style='color: #0073aa; margin: 5px 0;'>ℹ {$message}</div>\n";
        $this->test_results['info'][] = $message;
    }
    
    /**
     * Display test results summary
     */
    private function display_results() {
        echo "<h2 style='color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-top: 30px;'>Test Results Summary</h2>\n";
        
        $success_count = isset($this->test_results['success']) ? count($this->test_results['success']) : 0;
        $error_count = isset($this->test_results['error']) ? count($this->test_results['error']) : 0;
        $warning_count = isset($this->test_results['warning']) ? count($this->test_results['warning']) : 0;
        $info_count = isset($this->test_results['info']) ? count($this->test_results['info']) : 0;
        
        echo "<div style='background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<p><strong>✓ Successful tests:</strong> {$success_count}</p>\n";
        echo "<p><strong>✗ Failed tests:</strong> {$error_count}</p>\n";
        echo "<p><strong>⚠ Warnings:</strong> {$warning_count}</p>\n";
        echo "<p><strong>ℹ Information:</strong> {$info_count}</p>\n";
        echo "</div>\n";
        
        if ($error_count === 0) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
            echo "<h3>🎉 Deployment Test Passed!</h3>\n";
            echo "<p>The Siege Board Game plugin appears to be properly structured and ready for deployment.</p>\n";
            echo "</div>\n";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
            echo "<h3>❌ Deployment Issues Found</h3>\n";
            echo "<p>Please fix the following issues before deploying:</p>\n";
            echo "<ul>\n";
            foreach ($this->errors as $error) {
                echo "<li>{$error}</li>\n";
            }
            echo "</ul>\n";
            echo "</div>\n";
        }
        
        echo "<div style='background: #e7f3ff; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h3>📋 Next Steps</h3>\n";
        echo "<ol>\n";
        echo "<li>Upload the plugin folder to <code>/wp-content/plugins/</code></li>\n";
        echo "<li>Activate the plugin in WordPress admin</li>\n";
        echo "<li>Configure settings in <strong>Siege Game > Settings</strong></li>\n";
        echo "<li>Add the shortcode <code>[siege_game]</code> to any page or post</li>\n";
        echo "<li>Test the game functionality</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
        
        echo "</div>\n";
    }
}

// Run the deployment test
new Siege_Game_Deployment_Test();