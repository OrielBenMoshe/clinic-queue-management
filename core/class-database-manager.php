<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Manager for Clinic Queue Management
 * Handles custom tables creation and management
 */
class Clinic_Queue_Database_Manager {
    
    private static $instance = null;
    private $table_prefix;
    
    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'clinic_queue_';
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create custom tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table 1: appointment_calendars
        $table_calendars = $this->table_prefix . 'calendars';
        $sql_calendars = "CREATE TABLE $table_calendars (
            id int(11) NOT NULL AUTO_INCREMENT,
            doctor_id varchar(50) NOT NULL,
            clinic_id varchar(50) NOT NULL,
            treatment_type varchar(100) NOT NULL,
            calendar_name varchar(255) NOT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_calendar (doctor_id, clinic_id, treatment_type),
            KEY idx_doctor (doctor_id),
            KEY idx_clinic (clinic_id)
        ) $charset_collate;";
        
        // Table 2: appointment_dates
        $table_dates = $this->table_prefix . 'dates';
        $sql_dates = "CREATE TABLE $table_dates (
            id int(11) NOT NULL AUTO_INCREMENT,
            calendar_id int(11) NOT NULL,
            appointment_date date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_date (calendar_id, appointment_date),
            KEY idx_calendar (calendar_id),
            KEY idx_date (appointment_date),
            FOREIGN KEY (calendar_id) REFERENCES $table_calendars(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Table 3: appointment_times
        $table_times = $this->table_prefix . 'times';
        $sql_times = "CREATE TABLE $table_times (
            id int(11) NOT NULL AUTO_INCREMENT,
            date_id int(11) NOT NULL,
            time_slot varchar(5) NOT NULL,
            is_booked tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_time (date_id, time_slot),
            KEY idx_date (date_id),
            KEY idx_time (time_slot),
            KEY idx_booked (is_booked),
            FOREIGN KEY (date_id) REFERENCES $table_dates(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_calendars);
        dbDelta($sql_dates);
        dbDelta($sql_times);
        
        // Update database version
        update_option('clinic_queue_db_version', '1.1');
    }
    
    /**
     * Drop custom tables
     */
    public function drop_tables() {
        global $wpdb;
        
        $tables = [
            $this->table_prefix . 'times',
            $this->table_prefix . 'dates',
            $this->table_prefix . 'calendars'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('clinic_queue_db_version');
    }
    
    /**
     * Get calendar by doctor, clinic and treatment
     */
    public function get_calendar($doctor_id, $clinic_id, $treatment_type) {
        global $wpdb;
        
        $table_calendars = $this->table_prefix . 'calendars';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_calendars 
             WHERE doctor_id = %s AND clinic_id = %s AND treatment_type = %s",
            $doctor_id, $clinic_id, $treatment_type
        ));
    }
    
    /**
     * Create or update calendar
     */
    public function save_calendar($doctor_id, $clinic_id, $treatment_type, $calendar_name = '') {
        global $wpdb;
        
        $table_calendars = $this->table_prefix . 'calendars';
        
        if (empty($calendar_name)) {
            $calendar_name = "יומן $doctor_id - $clinic_id - $treatment_type";
        }
        
        $existing = $this->get_calendar($doctor_id, $clinic_id, $treatment_type);
        
        if ($existing) {
            // Update existing calendar
            $wpdb->update(
                $table_calendars,
                ['calendar_name' => $calendar_name, 'last_updated' => current_time('mysql')],
                ['id' => $existing->id],
                ['%s', '%s'],
                ['%d']
            );
            return $existing->id;
        } else {
            // Create new calendar
            $wpdb->insert(
                $table_calendars,
                [
                    'doctor_id' => $doctor_id,
                    'clinic_id' => $clinic_id,
                    'treatment_type' => $treatment_type,
                    'calendar_name' => $calendar_name
                ],
                ['%s', '%s', '%s', '%s']
            );
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Get dates for calendar
     */
    public function get_dates($calendar_id, $start_date = null, $end_date = null) {
        global $wpdb;
        
        $table_dates = $this->table_prefix . 'dates';
        
        $where_conditions = ["calendar_id = %d"];
        $where_values = [$calendar_id];
        
        if ($start_date) {
            $where_conditions[] = "appointment_date >= %s";
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = "appointment_date <= %s";
            $where_values[] = $end_date;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_dates WHERE $where_clause ORDER BY appointment_date ASC",
            $where_values
        ));
    }
    
    /**
     * Get time slots for specific date
     */
    public function get_time_slots($date_id) {
        global $wpdb;
        
        $table_times = $this->table_prefix . 'times';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_times WHERE date_id = %d ORDER BY time_slot ASC",
            $date_id
        ));
    }
    
    /**
     * Get appointments data for widget (formatted for frontend)
     */
    public function get_appointments_for_widget($doctor_id, $clinic_id, $treatment_type = '') {
        $calendar = $this->get_calendar($doctor_id, $clinic_id, $treatment_type);
        
        if (!$calendar) {
            return null;
        }
        
        // Get next 3 weeks of dates
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+3 weeks'));
        
        $dates = $this->get_dates($calendar->id, $start_date, $end_date);
        
        $appointments_data = [
            'calendar_id' => $calendar->id,
            'doctor_id' => $doctor_id,
            'clinic_id' => $clinic_id,
            'treatment_type' => $treatment_type,
            'timezone' => 'Asia/Jerusalem',
            'days' => []
        ];
        
        foreach ($dates as $date) {
            $time_slots = $this->get_time_slots($date->id);
            
            $slots = [];
            foreach ($time_slots as $slot) {
                $slots[] = [
                    'time' => $slot->time_slot,
                    'id' => $date->appointment_date . 'T' . $slot->time_slot,
                    'booked' => (bool) $slot->is_booked
                ];
            }
            
            if (!empty($slots)) {
                $appointments_data['days'][] = [
                    'date' => $date->appointment_date,
                    'slots' => $slots
                ];
            }
        }
        
        return $appointments_data;
    }
    
    /**
     * Add or update date availability
     */
    public function set_date_availability($calendar_id, $date) {
        global $wpdb;
        
        $table_dates = $this->table_prefix . 'dates';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_dates WHERE calendar_id = %d AND appointment_date = %s",
            $calendar_id, $date
        ));
        
        if ($existing) {
            return $existing->id;
        } else {
            $wpdb->insert(
                $table_dates,
                [
                    'calendar_id' => $calendar_id,
                    'appointment_date' => $date
                ],
                ['%d', '%s']
            );
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Add or update time slot
     */
    public function set_time_slot($date_id, $time_slot, $is_booked = false) {
        global $wpdb;
        
        $table_times = $this->table_prefix . 'times';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_times WHERE date_id = %d AND time_slot = %s",
            $date_id, $time_slot
        ));
        
        $data = [
            'date_id' => $date_id,
            'time_slot' => $time_slot,
            'is_booked' => $is_booked ? 1 : 0
        ];
        
        if ($existing) {
            $wpdb->update(
                $table_times,
                $data,
                ['id' => $existing->id],
                ['%d', '%s', '%d'],
                ['%d']
            );
            return $existing->id;
        } else {
            $wpdb->insert($table_times, $data, ['%d', '%s', '%d']);
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Update existing database to new structure
     */
    public function update_database_structure() {
        global $wpdb;
        
        $current_version = get_option('clinic_queue_db_version', '1.0');
        
        if (version_compare($current_version, '1.1', '<')) {
            $table_times = $this->table_prefix . 'times';
            
            // Check if time_slot column exists and is of type 'time'
            $column_info = $wpdb->get_row($wpdb->prepare(
                "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'time_slot'",
                DB_NAME, $table_times
            ));
            
            if ($column_info && strpos($column_info->COLUMN_TYPE, 'time') !== false) {
                // Convert existing time data from HH:MM:SS to HH:MM format
                $wpdb->query("UPDATE $table_times SET time_slot = SUBSTRING(time_slot, 1, 5)");
                
                // Change column type from time to varchar(5)
                $wpdb->query("ALTER TABLE $table_times MODIFY COLUMN time_slot varchar(5) NOT NULL");
                
                // Update version
                update_option('clinic_queue_db_version', '1.1');
            }
        }
    }
    
    /**
     * Check if tables exist
     */
    public function tables_exist() {
        global $wpdb;
        
        $table_calendars = $this->table_prefix . 'calendars';
        $table_dates = $this->table_prefix . 'dates';
        $table_times = $this->table_prefix . 'times';
        
        $calendars_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_calendars'") == $table_calendars;
        $dates_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_dates'") == $table_dates;
        $times_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_times'") == $table_times;
        
        return $calendars_exists && $dates_exists && $times_exists;
    }
}
