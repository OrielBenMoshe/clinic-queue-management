<?php
/**
 * Calendar Dialog HTML View
 * 
 * This file contains the HTML structure for the calendar dialog popup
 * that displays detailed calendar information and appointments.
 * 
 * @package Clinic_Queue_Management
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate calendar dialog HTML
 * 
 * @param object $calendar Calendar object
 * @param array $appointments_data Array of appointments data
 * @return string HTML content
 */
function cqm_generate_calendar_dialog_html($calendar, $appointments_data) {
    // Validate input parameters
    if (!$calendar || !is_object($calendar)) {
        return '<div class="error">Invalid calendar data provided.</div>';
    }
    
    if (!is_array($appointments_data)) {
        $appointments_data = array();
    }
    
    // Include common components
    $common_file = plugin_dir_path(__FILE__) . 'calendar-common-html.php';
    if (!function_exists('cqm_generate_calendar_info_table')) {
        if (file_exists($common_file)) {
            include_once $common_file;
        } else {
            // If file doesn't exist, return error message
            return '<div class="error">Common HTML components file not found.</div>';
        }
    }
    
    // Verify all required functions exist
    if (!function_exists('cqm_generate_calendar_info_table') || 
        !function_exists('cqm_generate_appointments_stats') || 
        !function_exists('cqm_generate_empty_appointments_notice')) {
        return '<div class="error">Required functions not available.</div>';
    }
    
    ob_start();
    ?>
    <div class="calendar-details">
        <?php echo cqm_generate_calendar_info_table($calendar, true); ?>
            
        <div class="appointments-calendar" style="max-width: 478px; margin: 0 auto; min-height: 459px; display: flex; flex-direction: column;">
            <?php if (empty($appointments_data)): ?>
                <?php echo cqm_generate_empty_appointments_notice(true); ?>
            <?php else: ?>
                <?php
                // Get current month and year from first appointment
                $first_date = strtotime($appointments_data[0]['date']->appointment_date);
                $current_month = date('F', $first_date);
                $current_year = date('Y', $first_date);
                
                // Hebrew month names and day abbreviations from constants
                $hebrew_months = class_exists('Clinic_Queue_Constants') ? Clinic_Queue_Constants::get_hebrew_months() : array();
                $hebrew_day_abbrev = class_exists('Clinic_Queue_Constants') ? Clinic_Queue_Constants::get_hebrew_day_abbrev() : array();
                ?>
                
                <!-- Top Section -->
                <div class="top-section">
                    <!-- Month and Year Header -->
                    <h2 class="month-and-year"><?php echo $hebrew_months[$current_month] . ', ' . $current_year; ?></h2>
                    
                    <!-- Days Carousel/Tabs -->
                    <div class="days-carousel">
                        <div class="days-container">
                            <?php foreach ($appointments_data as $index => $appointment): ?>
                                <?php
                                $date = strtotime($appointment['date']->appointment_date);
                                $day_number = date('j', $date);
                                $day_name = date('l', $date);
                                $total_slots = count($appointment['time_slots']);
                                ?>
                                <div class="day-tab <?php echo $index === 0 ? 'active selected' : ''; ?>" data-date="<?php echo date('Y-m-d', $date); ?>">
                                    <div class="day-abbrev"><?php echo $hebrew_day_abbrev[$day_name]; ?></div>
                                    <div class="day-content">
                                        <div class="day-number"><?php echo $day_number; ?></div>
                                        <div class="day-slots-count"><?php echo $total_slots; ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Bottom Section -->
                <div class="bottom-section">
                    <!-- Time Slots for Selected Day -->
                    <div class="time-slots-container">
                        <?php foreach ($appointments_data as $index => $appointment): ?>
                            <div class="day-time-slots <?php echo $index === 0 ? 'active' : ''; ?>" data-date="<?php echo date('Y-m-d', strtotime($appointment['date']->appointment_date)); ?>">
                                <?php if (empty($appointment['time_slots'])): ?>
                                    <p class="no-slots">אין תורים זמינים</p>
                                <?php else: ?>
                                    <div class="time-slots-grid">
                                        <?php foreach ($appointment['time_slots'] as $slot): ?>
                                            <div class="time-slot <?php echo $slot->is_booked ? 'booked' : 'available'; ?>">
                                                <span class="slot-time"><?php echo date('H:i', strtotime($slot->time_slot)); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php echo cqm_generate_appointments_stats($appointments_data); ?>
    </div>
    <?php
    return ob_get_clean();
}
