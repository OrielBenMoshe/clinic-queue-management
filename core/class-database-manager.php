<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Appointment Storage Interface
 * מאפשר מעבר קל מ-CPT ל-Custom Table בעתיד
 * 
 * @package Clinic_Queue_Management
 */
interface Clinic_Queue_Appointment_Storage_Interface {
    
    /**
     * Create new appointment
     * 
     * @param array $data Appointment data
     * @return int|WP_Error Post ID or error
     */
    public function create($data);
    
    /**
     * Get appointment by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function get($id);
    
    /**
     * Update appointment
     * 
     * @param int $id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update($id, $data);
    
    /**
     * Delete appointment
     * 
     * @param int $id
     * @return bool|WP_Error
     */
    public function delete($id);
    
    /**
     * Find appointments by user ID
     * 
     * @param int $user_id
     * @return array
     */
    public function find_by_user($user_id);
}

/**
 * Database Manager
 * מנהל יצירה ועדכון של טבלאות מותאמות אישית
 * 
 * @package Clinic_Queue_Management
 * @subpackage Core
 */
class Clinic_Queue_Database_Manager {
    
    private static $instance = null;
    private $db_version = '1.0.0';
    private $option_name = 'clinic_queue_db_version';
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Queue_Database_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register activation hook
        register_activation_hook(CLINIC_QUEUE_MANAGEMENT_FILE, array($this, 'create_tables'));
        
        // Check for database updates on admin init
        add_action('admin_init', array($this, 'maybe_update_database'));
    }
    
    /**
     * Create custom tables
     * Called on plugin activation
     */
    public function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $this->create_appointments_table();
        
        // Update version
        update_option($this->option_name, $this->db_version);
    }
    
    /**
     * Check if database needs update
     */
    public function maybe_update_database() {
        $installed_version = get_option($this->option_name, '0.0.0');
        
        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->create_tables();
        }
    }
    
    /**
     * Create appointments table
     * טבלה לניהול תורים - מותאמת לקנה מידה ארצי
     */
    private function create_appointments_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'clinic_queue_appointments';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_uid VARCHAR(100) NOT NULL,
            clinic_id BIGINT(20) UNSIGNED NOT NULL,
            doctor_id BIGINT(20) UNSIGNED NOT NULL,
            patient_name VARCHAR(255) NOT NULL,
            patient_phone VARCHAR(50) NOT NULL,
            patient_email VARCHAR(255) DEFAULT NULL,
            patient_id_number VARCHAR(20) DEFAULT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            appointment_datetime DATETIME NOT NULL,
            treatment_type VARCHAR(255) DEFAULT NULL,
            status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
            notes TEXT DEFAULT NULL,
            source VARCHAR(50) DEFAULT 'web',
            external_id VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            updated_by BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY appointment_uid (appointment_uid),
            KEY clinic_id (clinic_id),
            KEY doctor_id (doctor_id),
            KEY appointment_date (appointment_date),
            KEY appointment_datetime (appointment_datetime),
            KEY status (status),
            KEY patient_phone (patient_phone),
            KEY patient_email (patient_email),
            KEY external_id (external_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
        
        // Log if table was created
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            if ($table_exists) {
                error_log('[Clinic Queue] Appointments table created/updated successfully');
            } else {
                error_log('[Clinic Queue] Failed to create appointments table');
            }
        }
    }
    
    /**
     * Get appointments table name
     * 
     * @return string Table name with prefix
     */
    public function get_appointments_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'clinic_queue_appointments';
    }
    
    /**
     * Check if appointments table exists
     * 
     * @return bool
     */
    public function appointments_table_exists() {
        global $wpdb;
        $table_name = $this->get_appointments_table_name();
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
    
    /**
     * Drop all custom tables (for uninstall)
     * ⚠️ Use with caution - this deletes all data!
     */
    public function drop_tables() {
        global $wpdb;
        
        $table_name = $this->get_appointments_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        
        // Delete version option
        delete_option($this->option_name);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Clinic Queue] All custom tables dropped');
        }
    }
}

