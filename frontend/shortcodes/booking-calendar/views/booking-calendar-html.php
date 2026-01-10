<?php
/**
 * Booking Calendar Shortcode - View Template
 * Renders the booking calendar HTML structure
 * 
 * @var array $settings     Shortcode settings (mode, doctor_id, clinic_id, etc.)
 * @var array $clinics      Clinics options
 * @var array $treatments   Treatment types options
 * 
 * Note: Schedulers are loaded via JavaScript from bookingCalendarInitialData (not from PHP)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$selection_mode = $settings['mode'] ?? 'doctor';
$show_treatment_field = true; // Always show treatment field for now
$show_doctor_field = ($selection_mode === 'clinic'); // Clinic mode shows scheduler selection
$show_clinic_field = ($selection_mode === 'doctor'); // Doctor mode shows clinic selection
?>
<div class="booking-calendar-shortcode"
    style="max-width: 478px; margin: 0 auto; min-height: 459px; display: flex; flex-direction: column;"
    data-selection-mode="<?php echo esc_attr($selection_mode); ?>"
    data-specific-clinic-id="<?php echo esc_attr($settings['clinic_id'] ?? ''); ?>"
    data-specific-doctor-id="<?php echo esc_attr($settings['doctor_id'] ?? ''); ?>"
    data-specific-treatment-type="<?php echo esc_attr($settings['treatment_type'] ?? ''); ?>"
    id="booking-calendar-<?php echo uniqid(); ?>">
    
    <div class="top-section">
        <!-- Selection Form -->
        <form class="widget-selection-form" id="booking-calendar-form-<?php echo uniqid(); ?>">
            <!-- Hidden field for selection mode -->
            <input type="hidden" name="selection_mode" value="<?php echo esc_attr($selection_mode); ?>">

            <?php if ($show_treatment_field): ?>
                <!-- Treatment type selection -->
                <select name="treatment_type" class="form-field-select treatment-field" data-field="treatment_type">
                    <option value="">בחר סוג טיפול</option>
                    <?php if (!empty($treatments)): ?>
                        <?php foreach ($treatments as $id => $name): ?>
                            <option value="<?php echo esc_attr($id); ?>">
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            <?php endif; ?>

            <?php if ($selection_mode === 'doctor'): ?>
                <!-- Doctor mode: Clinic is FIXED (hidden) -->
                <input type="hidden" name="clinic_id" value="<?php echo esc_attr($settings['clinic_id'] ?? '1'); ?>">
            <?php endif; ?>

            <?php if ($selection_mode === 'clinic'): ?>
                <!-- Clinic mode: Clinic is FIXED (hidden) -->
                <input type="hidden" name="clinic_id" value="<?php echo esc_attr($settings['clinic_id'] ?? '1'); ?>">
            <?php endif; ?>

            <?php if ($show_doctor_field): ?>
                <!-- Scheduler selection (in clinic mode) - Changed to "בחר רופא/מטפל" -->
                <!-- Options are populated via JavaScript from bookingCalendarInitialData -->
                <select name="scheduler_id" class="form-field-select scheduler-field" data-field="scheduler_id">
                    <option value="">בחר רופא/מטפל</option>
                </select>
            <?php endif; ?>

            <?php if ($show_clinic_field): ?>
                <!-- Clinic selection (in doctor mode) -->
                <!-- Options are populated via JavaScript from loaded schedulers -->
                <select name="clinic_id" class="form-field-select" data-field="clinic_id">
                    <option value="">בחר מרפאה</option>
                </select>
            <?php endif; ?>
        </form>

        <!-- Month and Year Header -->
        <h2 class="month-and-year">טוען...</h2>

        <!-- Days Carousel/Tabs -->
        <div class="days-carousel">
            <div class="days-container">
                <!-- Days will be loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <div class="bottom-section">
        <!-- Time Slots for Selected Day -->
        <div class="time-slots-container">
            <!-- Loading placeholder for Elementor editor preview -->
            <div class="booking-calendar-loading-placeholder" style="text-align: center; padding: 40px; color: #666;">
                <div style="font-size: 32px; margin-bottom: 10px;">📅</div>
                <p style="font-size: 16px; margin: 0;"><strong>טוען יומן תורים...</strong></p>
                <p style="font-size: 14px; margin: 5px 0 0 0;">היומן יופיע בדף החי</p>
            </div>
        </div>
        
        <!-- Action Buttons will be added by JavaScript -->
    </div>
</div>

<script>
// Ensure initialization in Elementor editor
(function() {
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function($) {
            // Remove loading placeholder when widget initializes
            $('.booking-calendar-shortcode').on('booking-calendar-initialized', function() {
                $(this).find('.booking-calendar-loading-placeholder').remove();
            });
            
            // If in Elementor editor, try to initialize after a delay
            if (typeof elementor !== 'undefined' || window.location.href.indexOf('elementor') > -1) {
                setTimeout(function() {
                    if (typeof window.BookingCalendarManager !== 'undefined' && 
                        typeof window.BookingCalendarManager.utils !== 'undefined') {
                        window.BookingCalendarManager.utils.reinitialize();
                    }
                }, 1500);
            }
        });
    }
})();
</script>

