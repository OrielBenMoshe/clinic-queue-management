<?php
/**
 * Feature Toggle - Switch off parts of the plugin for debugging
 * 
 * Add these to wp-config.php to disable features:
 * define('CLINIC_QUEUE_DISABLE_CSS', true);
 * define('CLINIC_QUEUE_DISABLE_JS', true);
 * define('CLINIC_QUEUE_DISABLE_SHORTCODE', true);
 * define('CLINIC_QUEUE_DISABLE_AJAX', true);
 * define('CLINIC_QUEUE_DISABLE_REST_API', true);
 * define('CLINIC_QUEUE_DISABLE_ADMIN_MENU', true);
 * define('CLINIC_QUEUE_DISABLE_WIDGET', true);
 * define('CLINIC_QUEUE_DISABLE_VERSION_CHECK', true);
 * 
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clinic_Queue_Feature_Toggle {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Check if a feature is disabled
     */
    public function is_disabled($feature) {
        $constant_name = 'CLINIC_QUEUE_DISABLE_' . strtoupper($feature);
        return defined($constant_name) && constant($constant_name) === true;
    }
    
    /**
     * Get status of all features
     */
    public function get_status() {
        $features = array(
            'CSS' => !$this->is_disabled('CSS'),
            'JS' => !$this->is_disabled('JS'),
            'SHORTCODE' => !$this->is_disabled('SHORTCODE'),
            'AJAX' => !$this->is_disabled('AJAX'),
            'REST_API' => !$this->is_disabled('REST_API'),
            'ADMIN_MENU' => !$this->is_disabled('ADMIN_MENU'),
            'WIDGET' => !$this->is_disabled('WIDGET'),
            'VERSION_CHECK' => !$this->is_disabled('VERSION_CHECK'),
        );
        
        return $features;
    }
    
    /**
     * Show admin notice with current feature status
     */
    public function show_status_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $status = $this->get_status();
        $disabled = array();
        foreach ($status as $feature => $enabled) {
            if (!$enabled) {
                $disabled[] = $feature;
            }
        }
        
        if (empty($disabled)) {
            return; // All features enabled
        }
        
        ?>
        <div class="notice notice-warning">
            <p><strong>Clinic Queue Management - Debug Mode Active</strong></p>
            <p>The following features are DISABLED:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($disabled as $feature): ?>
                    <li><?php echo esc_html($feature); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><small>To disable features, add constants to wp-config.php (see class-feature-toggle.php for details)</small></p>
        </div>
        <?php
    }
}

// Initialize
Clinic_Queue_Feature_Toggle::get_instance();

