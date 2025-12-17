<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data from the controller with safety checks
if (!isset($data) || !is_array($data)) {
    wp_die('Invalid data provided to view');
}

$calendars_count = isset($data['calendars_count']) ? $data['calendars_count'] : 0;
$calendars = isset($data['calendars']) ? $data['calendars'] : array();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">דשבורד - ניהול תורי מרפאה</h1>
    <hr class="wp-header-end">
    
    <!-- Statistics Cards -->
    <div class="clinic-queue-dashboard">
        <div class="clinic-queue-stats">
            <div class="clinic-queue-stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-content">
                    <h3><?php echo $calendars_count; ?></h3>
                    <p>יומנים פעילים</p>
                </div>
            </div>
            
        </div>
        
        <!-- Calendars List -->
        <?php if (!empty($calendars)): ?>
        <div class="clinic-queue-calendars">
            <h2>יומנים זמינים</h2>
            <div class="calendars-list">
                <?php foreach ($calendars as $calendar): ?>
                    <div class="calendar-item">
                        <div class="calendar-info">
                            <strong><?php echo esc_html($calendar['doctor_name'] ?? ''); ?></strong>
                            <span><?php echo esc_html($calendar['clinic_name'] ?? ''); ?></span>
                            <span class="treatment-type"><?php echo esc_html($calendar['treatment_type'] ?? ''); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="clinic-queue-info">
            <p>אין יומנים זמינים. הנתונים מגיעים ישירות מה-API החיצוני.</p>
        </div>
        <?php endif; ?>
        
        <!-- Widget Preview -->
        <div class="clinic-queue-widget-preview">
            <h2>תצוגה מקדימה של הווידג'ט</h2>
            <div class="widget-preview-container" style="max-width: 600px; margin: 20px auto; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px;">
                <?php
                // Render widget with default settings
                if (class_exists('Clinic_Queue_Widget_Fields_Manager')) {
                    $dashboard = Clinic_Queue_Dashboard_Admin::get_instance();
                    $fields_manager = Clinic_Queue_Widget_Fields_Manager::get_instance();
                    
                    // Default settings for admin preview
                    $default_settings = array(
                        'selection_mode' => 'doctor',
                        'use_specific_treatment' => 'no',
                        'specific_doctor_id' => '1',
                        'specific_clinic_id' => '1',
                        'specific_treatment_type' => 'רפואה כללית'
                    );
                    
                    $widget_settings = $fields_manager->get_widget_data($default_settings);
                    
                    // Get options for selects
                    $data_provider = Clinic_Queue_Calendar_Data_Provider::get_instance();
                    $doctors = $data_provider->get_all_doctors();
                    $clinics = $data_provider->get_all_clinics();
                    $treatment_types = $data_provider->get_all_treatment_types();
                    
                    // Render widget HTML
                    $dashboard->render_widget_preview($default_settings, $widget_settings['settings'], $doctors, $clinics, $treatment_types);
                } else {
                    echo '<p>' . esc_html__('לא ניתן לטעון את הווידג\'ט. אנא ודא שהפלאגין טעון כראוי.', 'clinic-queue-management') . '</p>';
                }
                ?>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="clinic-queue-system-info">
            <h2>מידע מערכת</h2>
            <div class="system-info-grid">
                <div class="info-item">
                    <strong>גרסת התוסף:</strong> <?php echo CLINIC_QUEUE_MANAGEMENT_VERSION; ?>
                </div>
                <div class="info-item">
                    <strong>מצב פעולה:</strong> פנייה ישירה ל-API (אין שמירה מקומית)
                </div>
            </div>
        </div>
    </div>
</div>
