<?php
/**
 * Booking Calendar Shortcode - View Template
 * Renders the booking calendar HTML structure
 * 
 * @var array $settings     Shortcode settings (mode, doctor_id, clinic_id, etc.)
 * @var array $doctors      Doctors options (doctor mode)
 * @var array $schedulers   Schedulers options (clinic mode)
 * @var array $clinics      Clinics options
 * @var array $treatments   Treatment types options
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
                <!-- Scheduler selection (in clinic mode) -->
                <select name="scheduler_id" class="form-field-select scheduler-field" data-field="scheduler_id">
                    <option value="">בחר יומן</option>
                    <?php if (!empty($schedulers)): ?>
                        <?php foreach ($schedulers as $id => $name): ?>
                            <option value="<?php echo esc_attr($id); ?>">
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            <?php endif; ?>

            <?php if ($show_clinic_field): ?>
                <!-- Clinic selection (in doctor mode) -->
                <select name="clinic_id" class="form-field-select" data-field="clinic_id">
                    <?php if (!empty($clinics)): ?>
                        <?php foreach ($clinics as $id => $name): ?>
                            <option value="<?php echo esc_attr($id); ?>">
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
            <!-- Time slots will be loaded via JavaScript -->
        </div>
        
        <!-- Action Buttons will be added by JavaScript -->
    </div>
</div>

