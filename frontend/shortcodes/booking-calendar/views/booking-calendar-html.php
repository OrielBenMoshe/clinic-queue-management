<?php
/**
 * Booking Calendar Shortcode - View Template
 * Renders the booking calendar HTML structure
 * 
 * @var array  $settings                  Shortcode settings (mode, doctor_id, clinic_id, etc.)
 * @var array  $clinics                   Clinics options
 * @var array  $treatments                Treatment types options
 * @var string $loading_placeholder_icon  Inline SVG for loading state (from assets/images/icons)
 * @var bool   $empty_calendars           Whether there are no schedulers (show empty state card)
 * @var string $empty_state_message       Message for empty state (clinic/doctor)
 * @var string $empty_state_icon          Inline SVG icon for empty state (calendar-pink-icon.svg)
 * @var bool   $enable_mobile_cta         האם להפעיל את תצוגת המובייל המיוחדת
 *                                        (CTA דביק + פנל fullscreen). פעיל רק
 *                                        בעמודי singular של doctors/clinics.
 *
 * Note: Schedulers are loaded via JavaScript from bookingCalendarInitialData (not from PHP)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// When there are no calendars, render only the empty state card (no form, no calendar)
if (!empty($empty_calendars) && !empty($empty_state_message)) : ?>
<div class="booking-calendar-empty-state"
    style="max-width: 478px; margin: 0 auto; min-height: 200px;"
    data-empty-calendars="1">
    <div class="booking-calendar-empty-state__card">
        <?php if (!empty($empty_state_icon)) : ?>
            <div class="booking-calendar-empty-state__icon"><?php echo $empty_state_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG from plugin assets ?></div>
        <?php endif; ?>
        <p class="booking-calendar-empty-state__message"><?php echo esc_html($empty_state_message); ?></p>
    </div>
</div>
<?php else :
$selection_mode = $settings['mode'] ?? 'doctor';
$show_treatment_field = true; // Always show treatment field for now
$show_doctor_field = ($selection_mode === 'clinic'); // Clinic mode shows scheduler selection
$show_clinic_field = ($selection_mode === 'doctor'); // Doctor mode shows clinic selection
$slot_rows = max(1, intval($settings['slot_rows'] ?? 4));
$calendar_height = ($slot_rows === 2) ? 369 : 459;

// מודיפייר שמפעיל את תצוגת המובייל (CTA דביק + פנל fullscreen).
// מבוסס על הדגל שנקבע ב-class-booking-calendar-shortcode.php לפי is_singular().
$mobile_cta_class = ! empty( $enable_mobile_cta ) ? ' booking-calendar-shortcode--with-mobile-cta' : '';
?>
<div class="booking-calendar-shortcode<?php echo esc_attr( $mobile_cta_class ); ?>"
    style="max-width: 478px; margin: 0 auto; height: <?php echo (int) $calendar_height; ?>px; display: flex; flex-direction: column;"
    data-selection-mode="<?php echo esc_attr($selection_mode); ?>"
    data-specific-clinic-id="<?php echo esc_attr($settings['clinic_id'] ?? ''); ?>"
    data-specific-doctor-id="<?php echo esc_attr($settings['doctor_id'] ?? ''); ?>"
    data-specific-treatment-type="<?php echo esc_attr($settings['treatment_type'] ?? ''); ?>"
    data-slot-rows="<?php echo esc_attr(max(1, intval($settings['slot_rows'] ?? 4))); ?>"
    id="booking-calendar-<?php echo uniqid(); ?>">

    <?php if ( ! empty( $enable_mobile_cta ) ) : ?>
        <?php
        // ידית גרירה לסגירת כרטיס המובייל (גרירה מטה).
        // מוצגת רק כשה-widget פתוח במובייל.
        ?>
        <div class="booking-calendar-mobile-drag-handle" aria-hidden="true">
            <span class="booking-calendar-mobile-drag-handle__bar"></span>
        </div>
    <?php endif; ?>

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
            <button type="button" class="days-carousel-arrow days-carousel-arrow-prev disabled" disabled aria-label="יום קודם">
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>
            <div class="days-container-wrapper">
                <div class="days-container">
                    <!-- Days will be loaded via JavaScript -->
                </div>
            </div>
            <button type="button" class="days-carousel-arrow days-carousel-arrow-next" aria-label="יום הבא">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            </button>
        </div>
    </div>
    
    <div class="bottom-section">
        <!-- Time Slots for Selected Day -->
        <div class="time-slots-container">
            <!-- Loading placeholder for Elementor editor preview -->
            <div class="booking-calendar-loading-placeholder" style="text-align: center; padding: 40px; color: #666;">
                <?php if (!empty($loading_placeholder_icon)) : ?>
                    <div class="booking-calendar-loading-placeholder__icon" style="margin-bottom: 10px;"><?php echo $loading_placeholder_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG from plugin assets ?></div>
                <?php endif; ?>
                <p style="font-size: 16px; margin: 0;"><strong>טוען יומן תורים...</strong></p>
                <p style="font-size: 14px; margin: 5px 0 0 0;">היומן יופיע בדף החי</p>
            </div>
        </div>
        
        <!-- Action Buttons will be added by JavaScript -->
    </div>

    <?php if ( ! empty( $enable_mobile_cta ) ) : ?>
        <?php
        // במובייל / טאבלט במאונך היומן כולו מוסתר ובמקומו מוצג כפתור דביק
        // לתחתית המסך ("צפיה בתורים זמינים"), שפותח את ה-widget כ-fullscreen panel.
        // רלוונטי רק בעמודי single של doctors/clinics (ראו class-booking-calendar-shortcode.php).
        ?>
        <div class="booking-calendar-mobile-cta" aria-hidden="true">
            <button type="button"
                    class="booking-calendar-mobile-cta__btn"
                    aria-label="<?php esc_attr_e( 'צפיה בתורים זמינים', 'clinic-queue' ); ?>">
                <?php esc_html_e( 'צפיה בתורים זמינים', 'clinic-queue' ); ?>
            </button>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

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

