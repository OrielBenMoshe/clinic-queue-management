<?php
/**
 * Booking Calendar Shortcode - View Template
 * Renders the booking calendar HTML structure
 * 
 * @var array  $settings                  Shortcode settings (mode, doctor_id, clinic_id, etc.)
 * @var array  $treatments                Treatment types (נטענים ב-JS מהיומנים)
 * @var string $loading_placeholder_icon  Inline SVG for loading state (from assets/images/icons)
 * @var bool   $empty_calendars           Whether there are no schedulers (show empty state card)
 * @var string $empty_state_message       Message for empty state (clinic/doctor)
 * @var string $empty_state_icon          Inline SVG icon for empty state (calendar-pink-icon.svg)
 * @var bool   $enable_mobile_cta         האם להפעיל תצוגת מובייל (כרטיס קומפקטי +
 *                                        פנל fullscreen). פעיל בעמודי singular
 *                                        doctors/clinics, ארכיון, חיפוש ורשימות.
 *
 * @var string $widget_id                 Unique widget id for per-instance data
 * @var bool   $show_elementor_editor_hint הצגת הסבר "יופיע בדף החי" רק בעורך Elementor
 *
 * Note: Schedulers are loaded via JavaScript from bookingCalendarInitialDataByWidget
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// When there are no calendars, render only the empty state card (no form, no calendar)
if (!empty($empty_calendars) && !empty($empty_state_message)) : ?>
<div class="booking-calendar-empty-state" data-empty-calendars="1">
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
$calendar_height = ($slot_rows === 2) ? 384 : 459;
$slots_min_height = ($slot_rows === 2) ? 74 : 164;

// מודיפייר שמפעיל כרטיס מובייל קומפקטי + פנל fullscreen (should_enable_mobile_cta).
$mobile_cta_class = ! empty( $enable_mobile_cta ) ? ' booking-calendar-shortcode--with-mobile-cta' : '';
?>
<div class="booking-calendar-shortcode<?php echo esc_attr( $mobile_cta_class ); ?>"
    style="max-width: 478px; margin: 0 auto; height: <?php echo (int) $calendar_height; ?>px; display: flex; flex-direction: column; --booking-calendar-slots-min-height: <?php echo (int) $slots_min_height; ?>px;"
    data-selection-mode="<?php echo esc_attr($selection_mode); ?>"
    data-specific-clinic-id="<?php echo esc_attr($settings['clinic_id'] ?? ''); ?>"
    data-specific-doctor-id="<?php echo esc_attr($settings['doctor_id'] ?? ''); ?>"
    data-specific-treatment-type="<?php echo esc_attr($settings['treatment_type'] ?? ''); ?>"
    data-slot-rows="<?php echo esc_attr(max(1, intval($settings['slot_rows'] ?? 4))); ?>"
    id="<?php echo esc_attr($widget_id); ?>">

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
        <form class="widget-selection-form" id="booking-calendar-form-<?php echo esc_attr($widget_id); ?>">
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
                <?php if (!empty($show_elementor_editor_hint)) : ?>
                    <p class="booking-calendar-loading-placeholder__editor-hint">
                        <?php esc_html_e('היומן יופיע בדף החי', 'clinic-queue-management'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Buttons will be added by JavaScript -->
    </div>

    <?php if ( ! empty( $enable_mobile_cta ) ) : ?>
        <?php
        /**
         * כרטיס מובייל קומפקטי – מוצג כברירת מחדל במובייל/טאבלט במאונך
         * במקום כפתור ה-CTA הדביק הפשוט.
         *
         * מבנה הכרטיס:
         * 1. שורת שדות בחירה (סוג טיפול + רופא/מטפל או מרפאה)
         * 2. קרוסלת קלפי ימים עם תורים זמינים (ממולאת ע"י booking-calendar-mobile-compact.js)
         * 3. אינדיקטור נקודות לגלילה
         *
         * לחיצה על קלף יום → פותח את ה-widget כ-fullscreen panel עם בחירה אוטומטית של אותו יום.
         * לחיצה על קלף "כל התורים" → פותח את המודל המורחב.
         */
        ?>
        <div class="booking-calendar-mobile-cta" aria-hidden="true" dir="rtl">
            <div class="mobile-compact-card">

                <!-- שורת שדות בחירה (סוג טיפול ראשון, רופא/מרפאה שני) -->
                <div class="mobile-compact-fields">

                    <!-- שדה בחירת סוג טיפול -->
                    <div class="mobile-compact-select-wrap">
                        <select class="mobile-compact-select"
                                data-compact-for="treatment_type"
                                aria-label="<?php esc_attr_e( 'בחר סוג טיפול', 'clinic-queue' ); ?>">
                            <option value=""><?php esc_html_e( 'בחר סוג טיפול', 'clinic-queue' ); ?></option>
                        </select>
                        <span class="mobile-compact-select-display" aria-hidden="true">
                            <span class="mobile-compact-select-text">
                                <?php esc_html_e( 'בחר סוג טיפול', 'clinic-queue' ); ?>
                            </span>
                            <span class="mobile-compact-select-arrow">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 7.5L10 12.5L15 7.5" stroke="#5A6976" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </span>
                    </div>

                    <?php if ( $show_doctor_field ) : ?>
                    <!-- שדה בחירת רופא / מטפל (מצב מרפאה) -->
                    <div class="mobile-compact-select-wrap mobile-compact-select-wrap--secondary">
                        <select class="mobile-compact-select"
                                data-compact-for="scheduler_id"
                                aria-label="<?php esc_attr_e( 'בחר רופא / מטפל', 'clinic-queue' ); ?>">
                            <option value=""><?php esc_html_e( 'בחר רופא / מטפל', 'clinic-queue' ); ?></option>
                        </select>
                        <span class="mobile-compact-select-display" aria-hidden="true">
                            <span class="mobile-compact-select-text">
                                <?php esc_html_e( 'בחר רופא / מטפל', 'clinic-queue' ); ?>
                            </span>
                            <span class="mobile-compact-select-arrow">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 7.5L10 12.5L15 7.5" stroke="#5A6976" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </span>
                    </div>
                    <?php elseif ( $show_clinic_field ) : ?>
                    <!-- שדה בחירת מרפאה (מצב רופא) -->
                    <div class="mobile-compact-select-wrap mobile-compact-select-wrap--secondary">
                        <select class="mobile-compact-select"
                                data-compact-for="clinic_id"
                                aria-label="<?php esc_attr_e( 'בחר מרפאה', 'clinic-queue' ); ?>">
                            <option value=""><?php esc_html_e( 'בחר מרפאה', 'clinic-queue' ); ?></option>
                        </select>
                        <span class="mobile-compact-select-display" aria-hidden="true">
                            <span class="mobile-compact-select-text">
                                <?php esc_html_e( 'בחר מרפאה', 'clinic-queue' ); ?>
                            </span>
                            <span class="mobile-compact-select-arrow">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 7.5L10 12.5L15 7.5" stroke="#5A6976" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </span>
                    </div>
                    <?php endif; ?>

                </div><!-- /.mobile-compact-fields -->

                <!-- קרוסלת קלפי ימים (ממולאת ע"י JS) -->
                <div class="mobile-compact-carousel-container">
                    <div class="mobile-compact-carousel" role="listbox"
                         aria-label="<?php esc_attr_e( 'ימים עם תורים זמינים', 'clinic-queue' ); ?>">
                        <div class="mobile-compact-loading" aria-hidden="true">
                            <span><?php esc_html_e( 'טוען תורים...', 'clinic-queue' ); ?></span>
                        </div>
                    </div>
                </div><!-- /.mobile-compact-carousel-container -->

                <!-- אינדיקטור נקודות -->
                <div class="mobile-compact-dots" aria-hidden="true">
                    <span class="mobile-compact-dot mobile-compact-dot--active"></span>
                    <span class="mobile-compact-dot"></span>
                    <span class="mobile-compact-dot"></span>
                </div>

            </div><!-- /.mobile-compact-card -->
        </div><!-- /.booking-calendar-mobile-cta -->
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

