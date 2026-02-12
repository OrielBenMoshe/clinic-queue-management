<?php
/**
 * Appointments Management Page View
 * תצוגת עמוד ניהול התורים
 * 
 * @package ClinicQueue
 * @subpackage Admin\Views
 * @since 2.0.0
 * 
 * Variables available:
 * @var array $appointments List of appointments
 * @var array $stats Statistics data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap clinic-queue-appointments-page">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('ניהול תורים', 'clinic-queue'); ?>
    </h1>
    
    <button type="button" class="page-title-action clinic-queue-create-test-btn">
        <?php esc_html_e('יצירת רשומת בדיקה', 'clinic-queue'); ?>
    </button>
    
    <hr class="wp-header-end">
    
    <!-- Statistics Cards -->
    <div class="clinic-queue-stats">
        <div class="clinic-queue-stat-card">
            <div class="stat-value"><?php echo esc_html($stats['total']); ?></div>
            <div class="stat-label"><?php esc_html_e('סך הכל תורים', 'clinic-queue'); ?></div>
        </div>
        
        <div class="clinic-queue-stat-card stat-pending">
            <div class="stat-value"><?php echo esc_html($stats['pending']); ?></div>
            <div class="stat-label"><?php esc_html_e('ממתינים', 'clinic-queue'); ?></div>
        </div>
        
        <div class="clinic-queue-stat-card stat-confirmed">
            <div class="stat-value"><?php echo esc_html($stats['confirmed']); ?></div>
            <div class="stat-label"><?php esc_html_e('מאושרים', 'clinic-queue'); ?></div>
        </div>
        
        <div class="clinic-queue-stat-card stat-completed">
            <div class="stat-value"><?php echo esc_html($stats['completed']); ?></div>
            <div class="stat-label"><?php esc_html_e('הושלמו', 'clinic-queue'); ?></div>
        </div>
        
        <div class="clinic-queue-stat-card stat-cancelled">
            <div class="stat-value"><?php echo esc_html($stats['cancelled']); ?></div>
            <div class="stat-label"><?php esc_html_e('בוטלו', 'clinic-queue'); ?></div>
        </div>
    </div>
    
    <!-- Appointments Table -->
    <div class="clinic-queue-table-container">
        <table class="wp-list-table widefat fixed striped clinic-queue-appointments-table">
            <thead>
                <tr>
                    <th class="column-id"><?php esc_html_e('מזהה', 'clinic-queue'); ?></th>
                    <th class="column-patient"><?php esc_html_e('שם מטופל', 'clinic-queue'); ?></th>
                    <th class="column-phone"><?php esc_html_e('טלפון', 'clinic-queue'); ?></th>
                    <th class="column-datetime"><?php esc_html_e('תאריך ושעה', 'clinic-queue'); ?></th>
                    <th class="column-duration"><?php esc_html_e('משך (דק\')', 'clinic-queue'); ?></th>
                    <th class="column-status"><?php esc_html_e('סטטוס', 'clinic-queue'); ?></th>
                    <th class="column-clinic"><?php esc_html_e('מרפאה', 'clinic-queue'); ?></th>
                    <th class="column-doctor"><?php esc_html_e('רופא', 'clinic-queue'); ?></th>
                    <th class="column-actions"><?php esc_html_e('פעולות', 'clinic-queue'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)) : ?>
                    <tr class="no-items">
                        <td colspan="9" class="colspanchange">
                            <?php esc_html_e('לא נמצאו תורים. לחץ על "יצירת רשומת בדיקה" כדי להתחיל.', 'clinic-queue'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($appointments as $appointment) : ?>
                        <tr data-appointment-id="<?php echo esc_attr($appointment['id']); ?>">
                            <td class="column-id">
                                <?php echo esc_html($appointment['id']); ?>
                            </td>
                            <td class="column-patient">
                                <strong>
                                    <?php echo esc_html($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                </strong>
                                <?php if (!empty($appointment['patient_email'])) : ?>
                                    <br>
                                    <small><?php echo esc_html($appointment['patient_email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="column-phone">
                                <?php echo esc_html($appointment['patient_phone']); ?>
                            </td>
                            <td class="column-datetime">
                                <?php 
                                // Format datetime
                                $datetime = $appointment['appointment_datetime'];
                                if (strtotime($datetime)) {
                                    echo esc_html(date_i18n('d/m/Y H:i', strtotime($datetime)));
                                } else {
                                    echo esc_html($datetime);
                                }
                                ?>
                            </td>
                            <td class="column-duration">
                                <?php echo esc_html($appointment['duration']); ?>
                            </td>
                            <td class="column-status">
                                <span class="status-badge status-<?php echo esc_attr($appointment['status']); ?>">
                                    <?php 
                                    $status_labels = array(
                                        'pending' => __('ממתין', 'clinic-queue'),
                                        'confirmed' => __('מאושר', 'clinic-queue'),
                                        'completed' => __('הושלם', 'clinic-queue'),
                                        'cancelled' => __('בוטל', 'clinic-queue'),
                                        'no_show' => __('לא הגיע', 'clinic-queue'),
                                    );
                                    echo esc_html($status_labels[$appointment['status']] ?? $appointment['status']);
                                    ?>
                                </span>
                            </td>
                            <td class="column-clinic">
                                <?php echo esc_html($appointment['wp_clinic_id']); ?>
                            </td>
                            <td class="column-doctor">
                                <?php echo esc_html($appointment['wp_doctor_id']); ?>
                            </td>
                            <td class="column-actions">
                                <button 
                                    type="button" 
                                    class="button button-small clinic-queue-delete-btn"
                                    data-appointment-id="<?php echo esc_attr($appointment['id']); ?>"
                                    title="<?php esc_attr_e('מחק תור', 'clinic-queue'); ?>"
                                >
                                    <?php esc_html_e('מחק', 'clinic-queue'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Loading Overlay -->
    <div class="clinic-queue-loading-overlay" style="display: none;">
        <div class="clinic-queue-spinner"></div>
    </div>
</div>
