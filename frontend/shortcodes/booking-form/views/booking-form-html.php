<?php
/**
 * Booking Form HTML View
 * Template for [booking_form] shortcode
 * 
 * @var array $data Data prepared by the shortcode class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract data
$user_id = $data['user_id'];
$current_user = $data['current_user'];
$family_members = $data['family_members'];
$popup_id = $data['popup_id'];
$appointment_data = $data['appointment_data'];
$has_appointment_data = !empty($appointment_data['date']) && !empty($appointment_data['time']);
?>

<div class="jet-form-builder jet-form-builder--default booking-form-wrapper">
    <div id="booking-message"></div>

    <?php if ($has_appointment_data) : ?>
        <!-- Appointment Summary Card -->
        <div class="booking-appointment-summary">
            <!-- Heading -->
            <div class="jet-form-builder__row field-type-heading is-filled">
                <div class="jet-form-builder__label">
                    <div class="jet-form-builder__label-text">פרטי התור</div>
                </div>
            </div>
            
            <!-- Date, Time, Treatment Card -->
            <div class="appointment-info-card">
                <?php if (!empty($appointment_data['date'])) : 
                    // Format date from YYYY-MM-DD to DD/MM/YYYY
                    $date_parts = explode('-', $appointment_data['date']);
                    $formatted_date = '';
                    if (count($date_parts) === 3) {
                        $formatted_date = $date_parts[2] . '/' . $date_parts[1] . '/' . $date_parts[0];
                    } else {
                        $formatted_date = $appointment_data['date'];
                    }
                ?>
                    <div class="appointment-info-item">
                        <span class="appointment-info-icon">
                            <img src="<?php echo esc_url(CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/icons/Calendar.svg'); ?>" alt="calendar icon" width="24" height="24">
                        </span>
                        <span class="appointment-info-value"><?php echo esc_html($formatted_date); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($appointment_data['time'])) : ?>
                    <div class="appointment-info-item">
                        <span class="appointment-info-icon">
                            <img src="<?php echo esc_url(CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/icons/Clock.svg'); ?>" alt="clock icon" width="24" height="24">
                        </span>
                        <span class="appointment-info-value"><?php echo esc_html($appointment_data['time']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($appointment_data['treatment_type'])) : ?>
                    <div class="appointment-info-item">
                        <span class="appointment-info-icon">
                            <img src="<?php echo esc_url(CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/icons/Medical.svg'); ?>" alt="medical icon" width="24" height="24">
                        </span>
                        <span class="appointment-info-value"><?php echo esc_html($appointment_data['treatment_type']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Doctor Info Card -->
            <?php if (!empty($appointment_data['doctor_name']) || !empty($appointment_data['doctor_specialty']) || !empty($appointment_data['clinic_address'])) : ?>
                <div class="appointment-doctor-card">
                    <?php if (!empty($appointment_data['doctor_thumbnail'])) : ?>
                        <div class="doctor-thumbnail">
                            <img src="<?php echo esc_url($appointment_data['doctor_thumbnail']); ?>" alt="<?php echo esc_attr($appointment_data['doctor_name']); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="doctor-info">
                        <?php if (!empty($appointment_data['doctor_name'])) : ?>
                            <div class="doctor-name"><?php echo esc_html($appointment_data['doctor_name']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($appointment_data['doctor_specialty'])) : ?>
                            <div class="doctor-specialty"><?php echo esc_html($appointment_data['doctor_specialty']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($appointment_data['clinic_address'])) : ?>
                            <div class="clinic-address">
                                <span class="clinic-address-icon">
                                    <img src="<?php echo esc_url(CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/icons/MapPoint.svg'); ?>" alt="location icon" width="24" height="24">
                                </span>
                                <?php if (!empty($appointment_data['clinic_name'])) : ?>
                                    <span class="clinic-name"><?php echo esc_html($appointment_data['clinic_name']); ?>, </span>
                                <?php endif; ?>
                                <span class="clinic-address-text"><?php echo esc_html($appointment_data['clinic_address']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form id="ajax-booking-form" class="jet-form-builder-form">
        <input type="hidden" name="action" value="submit_appointment_ajax">
        <?php wp_nonce_field('save_booking_ajax_nonce', 'security'); ?>

        <!-- Date and Time - Hidden Fields -->
        <input type="hidden" name="appt_date" id="appt_date" value="<?php echo $has_appointment_data && !empty($appointment_data['date']) ? esc_attr($appointment_data['date']) : esc_attr(date('Y-m-d')); ?>">
        <input type="hidden" name="appt_time" id="appt_time" value="<?php echo $has_appointment_data && !empty($appointment_data['time']) ? esc_attr($appointment_data['time']) : '10:30'; ?>">
        <?php if (!empty($appointment_data['treatment_type'])) : ?>
            <input type="hidden" name="treatment_type" id="treatment_type" value="<?php echo esc_attr($appointment_data['treatment_type']); ?>">
        <?php endif; ?>
        <?php if (!empty($appointment_data['scheduler_id'])) : ?>
            <input type="hidden" name="scheduler_id" id="scheduler_id" value="<?php echo esc_attr($appointment_data['scheduler_id']); ?>">
        <?php endif; ?>
        <?php if (!empty($appointment_data['proxy_schedule_id'])) : ?>
            <input type="hidden" name="proxy_schedule_id" id="proxy_schedule_id" value="<?php echo esc_attr($appointment_data['proxy_schedule_id']); ?>">
        <?php endif; ?>
        <?php if (!empty($appointment_data['duration'])) : ?>
            <input type="hidden" name="duration" id="duration" value="<?php echo esc_attr($appointment_data['duration']); ?>">
        <?php endif; ?>
        <?php if (!empty($appointment_data['referrer_url'])) : ?>
            <input type="hidden" name="referrer_url" id="referrer_url" value="<?php echo esc_url($appointment_data['referrer_url']); ?>">
        <?php endif; ?>

        <!-- Patient Selection -->
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">עבור מי התור</div>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-radio-field is-filled pills-group" id="patients-list-container">
            <label class="jet-form-builder__field-wrap">
                <input type="radio" name="patient_select" id="pat_self" value="self" class="jet-form-builder__field radio-field" checked>
                <span class="jet-form-builder__field-label">עבורי - <?php echo esc_html($current_user->display_name); ?></span>
            </label>
            <?php if (!empty($family_members) && is_array($family_members)) : ?>
                <?php foreach ($family_members as $index => $member) : 
                    $name = isset($member['first_name']) ? $member['first_name'] : 'בן משפחה';
                ?>
                <label class="jet-form-builder__field-wrap">
                    <input type="radio" name="patient_select" id="pat_<?php echo esc_attr($index); ?>" value="family_<?php echo esc_attr($index); ?>" class="jet-form-builder__field radio-field">
                    <span class="jet-form-builder__field-label"><?php echo esc_html($name); ?></span>
                </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">
                    <a href="#" id="clinic-queue-add-patient-trigger" class="add-patient-trigger" data-popup-id="<?php echo esc_attr($popup_id); ?>" style="cursor: pointer; color: #6c757d; font-weight: 500;">
                        <span>+</span> הוספת בן משפחה
                    </a>
                </div>
            </div>
        </div>

        <!-- First Visit -->
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">האם זה טיפול ראשון במרפאה?</div>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-radio-field is-filled pills-group">
            <label class="jet-form-builder__field-wrap">
                <input type="radio" name="first_visit" id="visit_no" value="לא" class="jet-form-builder__field radio-field" checked>
                <span class="jet-form-builder__field-label">לא - כבר ביקרתי במרפאה</span>
            </label>
            <label class="jet-form-builder__field-wrap">
                <input type="radio" name="first_visit" id="visit_yes" value="כן" class="jet-form-builder__field radio-field">
                <span class="jet-form-builder__field-label">כן - טיפול ראשון שלי במרפאה</span>
            </label>
        </div>

        <!-- Phone Number -->
        <div class="jet-form-builder__row field-type-text-field is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">מספר טלפון של המטופל</div>
                <div class="jet-form-builder__label-text helper-text">פרטי התור יישלחו למספר זה</div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <input dir="rtl" type="tel" name="phone" class="jet-form-builder__field text-field" placeholder="הזן מספר טלפון" required>
            </div>
        </div>

        <!-- ID Number -->
        <div class="jet-form-builder__row field-type-text-field is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">תעודת זהות</div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <input type="text" name="id_number" class="jet-form-builder__field text-field" placeholder="תעודת זהות" required>
            </div>
        </div>

        <!-- Notes -->
        <div class="jet-form-builder__row field-type-textarea-field is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">הערה אישית למרפאה</div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <textarea name="notes" class="jet-form-builder__field textarea-field" rows="2" placeholder="הקלד כאן"></textarea>
            </div>
        </div>

        <!-- Consent -->
        <div class="jet-form-builder__row field-type-checkbox-field is-filled">
            <div class="jet-form-builder__field-wrap">
                <label class="jet-form-builder__field-label">
                    <input type="checkbox" name="consent" id="consent_check" class="jet-form-builder__field checkbox-field" required>
                    <span>מאשר/ת שיתוף של המידע שלי עם המרפאה</span>
                </label>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="jet-form-builder__row field-type-submit-field">
            <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
                <button type="submit" id="submit-btn" class="jet-form-builder__action-button jet-form-builder__submit">
                    קבע את התור <span class="loader">⌛</span>
                </button>
            </div>
        </div>
    </form>
</div>
