<?php
/**
 * Common Calendar HTML Components
 * 
 * This file contains shared HTML components used by both
 * calendar-view-html.php and calendar-dialog-html.php
 * 
 * @package Clinic_Queue_Management
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate calendar info table
 * 
 * @param object $calendar Calendar object
 * @param bool $is_dialog Whether this is for dialog (different layout)
 * @return string HTML content
 */
function cqm_generate_calendar_info_table($calendar, $is_dialog = false) {
    $helpers = Clinic_Queue_Helpers::get_instance();
    
    if ($is_dialog) {
        // Dialog layout - horizontal table
        ob_start();
        ?>
        <div class="calendar-info">
            <table class="info-table">
                <thead>
                    <tr>
                        <th>מזהה רופא</th>
                        <th>שם רופא</th>
                        <th>מזהה מרפאה</th>
                        <th>שם מרפאה</th>
                        <th>סוג טיפול</th>
                        <th>עודכן לאחרונה</th>
                        <th>נוצר ב</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php echo esc_html($calendar->doctor_id); ?></strong></td>
                        <td><?php echo esc_html($helpers->get_doctor_name($calendar->doctor_id)); ?></td>
                        <td><?php echo esc_html($calendar->clinic_id); ?></td>
                        <td><?php echo esc_html($helpers->get_clinic_name($calendar->clinic_id)); ?></td>
                        <td><?php echo esc_html($calendar->treatment_type); ?></td>
                        <td><?php echo esc_html($calendar->last_updated); ?></td>
                        <td><?php echo esc_html($calendar->created_at); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    } else {
        // Page layout - vertical form table
        ob_start();
        ?>
        <div class="calendar-info">
            <h2><?php echo esc_html($calendar->calendar_name); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">מזהה רופא:</th>
                    <td><strong><?php echo esc_html($calendar->doctor_id); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">מזהה מרפאה:</th>
                    <td><?php echo esc_html($calendar->clinic_id); ?></td>
                </tr>
                <tr>
                    <th scope="row">סוג טיפול:</th>
                    <td><?php echo esc_html($calendar->treatment_type); ?></td>
                </tr>
                <tr>
                    <th scope="row">עודכן לאחרונה:</th>
                    <td><?php echo esc_html($calendar->last_updated); ?></td>
                </tr>
                <tr>
                    <th scope="row">נוצר ב:</th>
                    <td><?php echo esc_html($calendar->created_at); ?></td>
                </tr>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Generate appointments statistics
 * 
 * @param array $appointments_data Array of appointments data
 * @return string HTML content
 */
function cqm_generate_appointments_stats($appointments_data) {
    $total_dates = count($appointments_data);
    $total_slots = 0;
    $booked_slots = 0;
    $free_slots = 0;
    
    foreach ($appointments_data as $appointment) {
        foreach ($appointment['time_slots'] as $slot) {
            $total_slots++;
            if ($slot->is_booked) {
                $booked_slots++;
            } else {
                $free_slots++;
            }
        }
    }
    
    ob_start();
    ?>
    <div class="appointments-overview">
        <h3>סטטיסטיקות תורים</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_dates; ?></span>
                <span class="stat-label">תאריכים זמינים</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php echo $total_slots; ?></span>
                <span class="stat-label">סה"כ תורים</span>
            </div>
            <div class="stat-item">
                <span class="stat-number booked"><?php echo $booked_slots; ?></span>
                <span class="stat-label">תורים תפוסים</span>
            </div>
            <div class="stat-item">
                <span class="stat-number free"><?php echo $free_slots; ?></span>
                <span class="stat-label">תורים פנויים</span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Generate empty appointments notice
 * 
 * @param bool $is_dialog Whether this is for dialog (different message)
 * @return string HTML content
 */
function cqm_generate_empty_appointments_notice($is_dialog = false) {
    ob_start();
    ?>
    <div class="notice notice-info">
        <p>אין תורים זמינים לתקופה הקרובה.</p>
        <?php if ($is_dialog): ?>
            <p><strong>טיפ:</strong> לחץ על כפתור "סנכרן" כדי לטעון נתונים חדשים מה-API.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
