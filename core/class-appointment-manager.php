<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Appointment Manager for Clinic Queue Management
 * Handles appointment creation, management and availability
 */
class Clinic_Queue_Appointment_Manager {
    
    private static $instance = null;
    private $db_manager;
    
    // Default time slots (can be customized per calendar)
    private $default_time_slots = [
        '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
        '12:00', '12:30', '13:00', '13:30', '14:00', '14:30',
        '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
        '18:00', '18:30', '19:00', '19:30', '20:00'
    ];
    
    public function __construct() {
        $this->db_manager = Clinic_Queue_Database_Manager::get_instance();
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
     * Initialize calendar with 3 weeks of appointments
     */
    public function initialize_calendar($doctor_id, $clinic_id, $treatment_type, $calendar_name = '', $time_slots = null) {
        // Create or get calendar
        $calendar_id = $this->db_manager->save_calendar($doctor_id, $clinic_id, $treatment_type, $calendar_name);
        
        if (!$calendar_id) {
            return false;
        }
        
        // Use provided time slots or default
        $slots = $time_slots ?: $this->default_time_slots;
        
        // Generate 3 weeks of dates starting from today
        $start_date = new DateTime();
        $end_date = new DateTime();
        $end_date->add(new DateInterval('P21D')); // 3 weeks
        
        $current_date = clone $start_date;
        
        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');
            
            // Check if it's a weekend (Friday evening, Saturday, Sunday morning)
            $day_of_week = $current_date->format('N'); // 1=Monday, 7=Sunday
            
            // Create date record
            $date_id = $this->db_manager->set_date_availability($calendar_id, $date_str);
            
            if ($date_id) {
                // Add time slots for dates
                foreach ($slots as $time_slot) {
                    $this->db_manager->set_time_slot(
                        $date_id,
                        $time_slot,
                        false // not booked
                    );
                }
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $calendar_id;
    }
    
    /**
     * Update calendar from external data (e.g., from API)
     */
    public function update_calendar_from_data($doctor_id, $clinic_id, $treatment_type, $appointments_data) {
        $calendar = $this->db_manager->get_calendar($doctor_id, $clinic_id, $treatment_type);
        
        if (!$calendar) {
            // Create new calendar if doesn't exist
            $calendar_id = $this->initialize_calendar($doctor_id, $clinic_id, $treatment_type);
            if (!$calendar_id) {
                return false;
            }
        } else {
            $calendar_id = $calendar->id;
        }
        
        // Process each day in the data
        foreach ($appointments_data['days'] as $day_data) {
            $date = $day_data['date'];
            
            // Set date availability
            $date_id = $this->db_manager->set_date_availability($calendar_id, $date);
            
            if ($date_id) {
                // Process time slots for this date
                foreach ($day_data['slots'] as $slot_data) {
                    $this->db_manager->set_time_slot(
                        $date_id,
                        $slot_data['time'],
                        $slot_data['booked'] ?? false
                    );
                }
            }
        }
        
        return true;
    }
    
    /**
     * Book an appointment
     */
    public function book_appointment($doctor_id, $clinic_id, $treatment_type, $date, $time) {
        $calendar = $this->db_manager->get_calendar($doctor_id, $clinic_id, $treatment_type);
        
        if (!$calendar) {
            return ['success' => false, 'message' => 'Calendar not found'];
        }
        
        // Get date record
        global $wpdb;
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        $date_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_dates WHERE calendar_id = %d AND appointment_date = %s",
            $calendar->id, $date
        ));
        
        if (!$date_record) {
            return ['success' => false, 'message' => 'Date not found'];
        }
        
        // Check if time slot exists
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        $time_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_booked FROM $table_times 
             WHERE date_id = %d AND time_slot = %s",
            $date_record->id, $time
        ));
        
        if (!$time_record) {
            return ['success' => false, 'message' => 'Time slot not found'];
        }
        
        if ($time_record->is_booked) {
            return ['success' => false, 'message' => 'Time slot is booked'];
        }
        
        // Book the appointment
        $result = $wpdb->update(
            $table_times,
            [
                'is_booked' => 1,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $time_record->id],
            ['%d', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            return ['success' => true, 'message' => 'Appointment booked successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to book appointment'];
        }
    }
    
    /**
     * Cancel an appointment
     */
    public function cancel_appointment($doctor_id, $clinic_id, $treatment_type, $date, $time) {
        $calendar = $this->db_manager->get_calendar($doctor_id, $clinic_id, $treatment_type);
        
        if (!$calendar) {
            return ['success' => false, 'message' => 'Calendar not found'];
        }
        
        // Get date and time records
        global $wpdb;
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        
        $date_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_dates WHERE calendar_id = %d AND appointment_date = %s",
            $calendar->id, $date
        ));
        
        if (!$date_record) {
            return ['success' => false, 'message' => 'Date not found'];
        }
        
        $time_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_times WHERE date_id = %d AND time_slot = %s",
            $date_record->id, $time
        ));
        
        if (!$time_record) {
            return ['success' => false, 'message' => 'Time slot not found'];
        }
        
        // Cancel the appointment
        $result = $wpdb->update(
            $table_times,
            [
                'is_booked' => 0,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $time_record->id],
            ['%d', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            return ['success' => true, 'message' => 'Appointment cancelled successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to cancel appointment'];
        }
    }
    
    /**
     * Get appointment statistics
     */
    public function get_appointment_stats($calendar_id) {
        global $wpdb;
        
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        $table_times = $wpdb->prefix . 'clinic_queue_times';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT d.id) as total_dates,
                COUNT(t.id) as total_slots,
                COUNT(CASE WHEN t.is_booked = 0 THEN 1 END) as free_slots,
                COUNT(CASE WHEN t.is_booked = 1 THEN 1 END) as booked_slots
             FROM $table_dates d
             LEFT JOIN $table_times t ON d.id = t.date_id
             WHERE d.calendar_id = %d",
            $calendar_id
        ));
        
        return $stats;
    }
    
    /**
     * Clean up old appointments (older than 3 weeks)
     */
    public function cleanup_old_appointments() {
        global $wpdb;
        
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        $cutoff_date = date('Y-m-d', strtotime('-3 weeks'));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_dates WHERE appointment_date < %s",
            $cutoff_date
        ));
        
        return $result;
    }
    
    /**
     * Extend calendar by adding more weeks
     */
    public function extend_calendar($calendar_id, $weeks_to_add = 1) {
        $calendar = $this->db_manager->get_calendar_by_id($calendar_id);
        
        if (!$calendar) {
            return false;
        }
        
        // Get the last date in the calendar
        global $wpdb;
        $table_dates = $wpdb->prefix . 'clinic_queue_dates';
        $last_date = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(appointment_date) FROM $table_dates WHERE calendar_id = %d",
            $calendar_id
        ));
        
        if (!$last_date) {
            return false;
        }
        
        $start_date = new DateTime($last_date);
        $start_date->add(new DateInterval('P1D')); // Start from next day
        $end_date = clone $start_date;
        $end_date->add(new DateInterval('P' . (7 * $weeks_to_add) . 'D'));
        
        $current_date = clone $start_date;
        
        while ($current_date <= $end_date) {
            $date_str = $current_date->format('Y-m-d');
            
            // Create date record
            $date_id = $this->db_manager->set_date_availability($calendar_id, $date_str);
            
            if ($date_id) {
                // Add time slots
                foreach ($this->default_time_slots as $time_slot) {
                    $this->db_manager->set_time_slot($date_id, $time_slot, false);
                }
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return true;
    }
    
    /**
     * Get calendar by ID (helper method for database manager)
     */
    public function get_calendar_by_id($calendar_id) {
        global $wpdb;
        $table_calendars = $wpdb->prefix . 'clinic_queue_calendars';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_calendars WHERE id = %d",
            $calendar_id
        ));
    }
}
