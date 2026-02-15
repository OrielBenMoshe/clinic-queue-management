<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
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
    private $db_version = '2.2.0';
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
            if (version_compare($installed_version, '2.1.0', '<')) {
                $this->migrate_drop_status_column();
            }
            if (version_compare($installed_version, '2.2.0', '<')) {
                $this->migrate_add_first_visit_column();
            }
            update_option($this->option_name, $this->db_version);
        }
    }
    
    /**
     * Migration 2.1.0: Remove status column from appointments table
     */
    private function migrate_drop_status_column() {
        global $wpdb;
        $table_name = $this->get_appointments_table_name();
        if (!$this->appointments_table_exists()) {
            return;
        }
        $column = $wpdb->get_row($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'status'",
            DB_NAME,
            $table_name
        ));
        if ($column) {
            $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN status");
        }
        $proxy_col = $wpdb->get_row($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'proxy_appointment_id'",
            DB_NAME,
            $table_name
        ));
        if (!$proxy_col) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN proxy_appointment_id VARCHAR(100) DEFAULT NULL COMMENT 'מזהה תור במערכת הפרוקסי' AFTER proxy_schedule_id");
        }
    }

    /**
     * Migration 2.2.0: Add first_visit column (boolean: 0=no, 1=yes)
     */
    private function migrate_add_first_visit_column() {
        global $wpdb;
        $table_name = $this->get_appointments_table_name();
        if (!$this->appointments_table_exists()) {
            return;
        }
        $col = $wpdb->get_row($wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'first_visit'",
            DB_NAME,
            $table_name
        ));
        if (!$col) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN first_visit TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ביקור ראשון: 0=לא, 1=כן' AFTER remark");
        }
    }
    
    /**
     * Create appointments table
     * טבלה לניהול תורים - מותאמת לקנה מידה ארצי
     * גרסה 2.0.0 - מבנה מחודש עם שדות מעודכנים
     */

    private function create_appointments_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'clinic_queue_appointments';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            
            wp_clinic_id BIGINT(20) UNSIGNED NOT NULL,
            wp_doctor_id BIGINT(20) UNSIGNED NOT NULL,
            wp_schedule_id BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'קישור לפוסט לוח הזמנים',
            
            patient_first_name VARCHAR(100) NOT NULL,
            patient_last_name VARCHAR(100) NOT NULL,
            patient_phone VARCHAR(50) NOT NULL,
            patient_email VARCHAR(255) DEFAULT NULL,
            patient_id_number VARCHAR(20) DEFAULT NULL,
            
            appointment_datetime VARCHAR(20) NOT NULL COMMENT 'ISO 8601: YYYY-MM-DDTHH:mmZ',
            duration INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'משך הפגישה בדקות',
            treatment_type VARCHAR(255) DEFAULT NULL,
            remark TEXT DEFAULT NULL COMMENT 'הערות כלליות',
            first_visit TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ביקור ראשון: 0=לא, 1=כן',
            
            proxy_schedule_id VARCHAR(100) DEFAULT NULL COMMENT 'מזהה יומן במערכת חיצונית',
            proxy_appointment_id VARCHAR(100) DEFAULT NULL COMMENT 'מזהה תור במערכת הפרוקסי',
            drWebReasonID INT DEFAULT NULL COMMENT 'מזהה סיבת תור במערכת drWeb',
            
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,
            updated_by BIGINT(20) UNSIGNED DEFAULT NULL,
            
            PRIMARY KEY (id),
            KEY wp_clinic_id (wp_clinic_id),
            KEY wp_doctor_id (wp_doctor_id),
            KEY wp_schedule_id (wp_schedule_id),
            KEY appointment_datetime (appointment_datetime),
            KEY patient_phone (patient_phone),
            KEY patient_email (patient_email),
            KEY proxy_schedule_id (proxy_schedule_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
        
        // Log if table was created
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            if ($table_exists) {
                error_log('[Clinic Queue] Appointments table created/updated successfully (v2.0.0)');
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
     * Insert a new appointment into the appointments table
     *
     * @param array $data Keys: wp_clinic_id, wp_doctor_id, wp_schedule_id, patient_first_name,
     *                    patient_last_name, patient_phone, patient_email?, patient_id_number?,
     *                    appointment_datetime, duration, treatment_type?, remark?, proxy_schedule_id?,
     *                    proxy_appointment_id?, created_by?
     * @return int|false Inserted row ID or false on failure
     */
    /**
     * Normalize first_visit to 0 or 1 (boolean)
     *
     * @param mixed $value 'כן'|'לא'|'yes'|'no'|1|0|true|false
     * @return int 0 or 1
     */
    private function normalize_first_visit($value) {
        if ($value === 1 || $value === true || $value === '1' || $value === 'yes' || $value === 'כן') {
            return 1;
        }
        return 0;
    }

    /**
     * Generate a timestamp-based appointment ID – 9 הספרות האחרונות של הזמן הנוכחי (מילישניות)
     * ייחודי בדרך כלל; במקרה נדיר של התנגשות מנסה שוב עם +1
     *
     * @return int
     */
    private function generate_appointment_id() {
        $ts = (int) (microtime(true) * 1000);
        return $ts % 1000000000;
    }
    
    public function create_appointment($data) {
        global $wpdb;
        $table_name = $this->get_appointments_table_name();
        if (!$this->appointments_table_exists()) {
            return false;
        }
        $now = current_time('mysql');
        $id = $this->generate_appointment_id();
        $row = array(
            'id' => $id,
            'wp_clinic_id' => isset($data['wp_clinic_id']) ? absint($data['wp_clinic_id']) : 0,
            'wp_doctor_id' => isset($data['wp_doctor_id']) ? absint($data['wp_doctor_id']) : 0,
            'wp_schedule_id' => isset($data['wp_schedule_id']) ? absint($data['wp_schedule_id']) : null,
            'patient_first_name' => isset($data['patient_first_name']) ? sanitize_text_field($data['patient_first_name']) : '',
            'patient_last_name' => isset($data['patient_last_name']) ? sanitize_text_field($data['patient_last_name']) : '',
            'patient_phone' => isset($data['patient_phone']) ? sanitize_text_field($data['patient_phone']) : '',
            'patient_email' => isset($data['patient_email']) ? sanitize_email($data['patient_email']) : null,
            'patient_id_number' => isset($data['patient_id_number']) ? sanitize_text_field($data['patient_id_number']) : null,
            'appointment_datetime' => isset($data['appointment_datetime']) ? sanitize_text_field($data['appointment_datetime']) : '',
            'duration' => isset($data['duration']) ? absint($data['duration']) : 30,
            'treatment_type' => isset($data['treatment_type']) ? sanitize_text_field($data['treatment_type']) : null,
            'remark' => isset($data['remark']) ? sanitize_textarea_field($data['remark']) : null,
            'first_visit' => $this->normalize_first_visit($data['first_visit'] ?? 0),
            'proxy_schedule_id' => isset($data['proxy_schedule_id']) ? sanitize_text_field($data['proxy_schedule_id']) : null,
            'proxy_appointment_id' => isset($data['proxy_appointment_id']) ? sanitize_text_field($data['proxy_appointment_id']) : null,
            'drWebReasonID' => isset($data['drWebReasonID']) ? absint($data['drWebReasonID']) : null,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => isset($data['created_by']) ? absint($data['created_by']) : get_current_user_id(),
            'updated_by' => isset($data['updated_by']) ? absint($data['updated_by']) : get_current_user_id(),
        );
        $result = $wpdb->insert($table_name, $row);
        if ($result === false && strpos($wpdb->last_error, 'Duplicate entry') !== false) {
            $row['id'] = $id + 1;
            $result = $wpdb->insert($table_name, $row);
        }
        return $result !== false ? (int) $row['id'] : false;
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

