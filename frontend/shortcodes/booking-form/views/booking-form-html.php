<?php
/**
 * [booking_form] shortcode view
 *
 * @package Clinic_Queue_Management
 *
 * @var array $data Data from prepare_data(), or guest array with require_login_register and appointment_data
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!empty($data['require_login_register'])) {
    $ad                = isset($data['appointment_data']) && is_array($data['appointment_data']) ? $data['appointment_data'] : array();
    $appt_date         = !empty($ad['date']) ? $ad['date'] : '';
    $appt_time         = !empty($ad['time']) ? $ad['time'] : '';
    $treatment_type         = !empty($ad['treatment_type']) ? $ad['treatment_type'] : '';
    $treatment_type_display = !empty($ad['treatment_type_display']) ? $ad['treatment_type_display'] : '';
    $treatment_cost         = !empty($ad['treatment_cost']) ? absint($ad['treatment_cost']) : 0;
    $treatment_cost_display = !empty($ad['treatment_cost_display']) ? (string) $ad['treatment_cost_display'] : '';
    $clinic_name        = !empty($ad['clinic_name']) ? $ad['clinic_name'] : '';
    $clinic_specialty   = !empty($ad['clinic_specialty']) ? $ad['clinic_specialty'] : '';
    $clinic_specialties = array_values(
        array_filter(
            array_map(
                'trim',
                preg_split('/\s*,\s*/', (string) $clinic_specialty, -1, PREG_SPLIT_NO_EMPTY)
            )
        )
    );
    $clinic_specialties_visible   = array_slice($clinic_specialties, 0, 3);
    $clinic_specialties_remaining = max(0, count($clinic_specialties) - 3);
    $clinic_thumbnail   = !empty($ad['clinic_thumbnail']) ? $ad['clinic_thumbnail'] : '';
    $clinic_address     = !empty($ad['clinic_address']) ? $ad['clinic_address'] : '';
    $doctor_name           = !empty($ad['doctor_name']) ? trim((string) $ad['doctor_name']) : '';
    $doctor_url            = !empty($ad['doctor_url']) ? trim((string) $ad['doctor_url']) : '';
    $has_treating_doctor   = !empty($ad['has_treating_doctor']);
    $appt_date_display     = !empty($ad['appt_date_display']) ? (string) $ad['appt_date_display'] : $appt_date;
    $treatment_label    = $treatment_type_display !== '' ? $treatment_type_display : $treatment_type;
    $treating_doctor_partial = __DIR__ . '/partials/booking-form-treating-doctor.php';

    $guest_login_html_fragment = isset($data['guest_login_html_fragment'])
        ? (string) $data['guest_login_html_fragment']
        : '';

    $icon_url_calendar = plugins_url('assets/images/icons/calendar-pink-icon.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
    $icon_url_clock    = plugins_url('assets/images/icons/Clock.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
    $icon_url_medical  = plugins_url('assets/images/icons/Medical.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
    $icon_url_map      = plugins_url('assets/images/icons/MapPoint.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
    $icon_url_tag      = plugins_url('assets/images/icons/tag-icon.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
    ?>
<div
    class="booking-form-wrapper jet-form-builder jet-form-builder--default clinic-queue-jetform-mui clinic-queue-booking--register-gate"
    dir="rtl"
    data-clinic-queue-register-gate-root=""
>
    <?php if ($clinic_name !== '' || $appt_date !== '' || $treatment_type !== '') : ?>
        <div class="booking-appointment-summary">
            <?php if ($clinic_name !== '' || $clinic_thumbnail !== '' || !empty($clinic_specialties) || $doctor_name !== '' || $clinic_address !== '') : ?>
                <div class="appointment-clinic-card">
                    <?php if ($clinic_thumbnail !== '') : ?>
                        <div class="clinic-thumbnail">
                            <img
                                src="<?php echo esc_url($clinic_thumbnail); ?>"
                                alt="<?php echo esc_attr($clinic_name); ?>"
                                width="96"
                                loading="lazy"
                            />
                        </div>
                    <?php endif; ?>
                    <div class="clinic-card-info">
                        <?php if ($clinic_name !== '') : ?>
                            <div class="clinic-card-name"><?php echo esc_html($clinic_name); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($clinic_specialties)) : ?>
                            <div class="clinic-specialties">
                                <?php foreach ($clinic_specialties_visible as $spec_label) : ?>
                                    <span class="clinic-specialty"><?php echo esc_html($spec_label); ?></span>
                                <?php endforeach; ?>
                                <?php if ($clinic_specialties_remaining > 0) : ?>
                                    <span class="clinic-specialty clinic-specialty--more"><?php echo esc_html('+' . (string) $clinic_specialties_remaining); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php include $treating_doctor_partial; ?>
                        <?php if ($clinic_address !== '') : ?>
                            <div class="clinic-address">
                                <img
                                    class="clinic-address-icon"
                                    src="<?php echo esc_url($icon_url_map); ?>"
                                    alt=""
                                    width="24"
                                    height="24"
                                    decoding="async"
                                />
                                <span class="clinic-address-text"><?php echo esc_html($clinic_address); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($appt_date !== '' || $appt_time !== '' || $treatment_type !== '') : ?>
                <h2 class="booking-form-section-title"><?php esc_html_e('פרטי התור', 'clinic-queue-management'); ?></h2>
                <div class="appointment-info-card">
                    <?php if ($appt_date !== '') : ?>
                        <div class="appointment-info-item">
                            <img
                                class="appointment-info-icon"
                                src="<?php echo esc_url($icon_url_calendar); ?>"
                                alt=""
                                width="24"
                                height="24"
                                decoding="async"
                            />
                            <span class="appointment-info-value"><?php echo esc_html($appt_date_display); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($appt_time !== '') : ?>
                        <div class="appointment-info-item">
                            <img
                                class="appointment-info-icon"
                                src="<?php echo esc_url($icon_url_clock); ?>"
                                alt=""
                                width="24"
                                height="24"
                                decoding="async"
                            />
                            <span class="appointment-info-value"><?php echo esc_html($appt_time); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($treatment_type !== '') : ?>
                        <div class="appointment-info-item">
                            <img
                                class="appointment-info-icon"
                                src="<?php echo esc_url($icon_url_medical); ?>"
                                alt=""
                                width="24"
                                height="24"
                                decoding="async"
                            />
                            <span class="appointment-info-value"><?php echo esc_html($treatment_label); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($treatment_cost > 0 && $treatment_cost_display !== '') : ?>
                        <div class="appointment-info-item">
                            <img
                                class="appointment-info-icon appointment-info-icon--price"
                                src="<?php echo esc_url($icon_url_tag); ?>"
                                alt=""
                                width="22"
                                height="22"
                                decoding="async"
                            />
                            <span class="appointment-info-value"><?php echo esc_html($treatment_cost_display); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <hr class="booking-form-section-divider" />
    <?php endif; ?>

    <p class="clinic-queue-booking-register-gate__notice">
        <?php esc_html_e('להשלמת קביעת התור יש להתחבר לחשבון.', 'clinic-queue-management'); ?>
    </p>

    <div class="clinic-queue-booking-register-gate__form jet-form-builder jet-form-builder--default">
        <div class="clinic-queue-booking-register-gate__mount" tabindex="-1">
            <?php echo $guest_login_html_fragment; ?>
        </div>
    </div>

    <p class="clinic-queue-booking-register-gate__switch-row clinic-queue-booking-register-gate__switch-row--login">
        <?php esc_html_e('עדיין אין לך משתמש?', 'clinic-queue-management'); ?>
        <?php echo ' '; ?>
        <a
            href="#"
            class="clinic-queue-booking-register-gate__switch-link"
            data-clinic-queue-register-gate-switch="register"
        ><?php esc_html_e('הרשמה', 'clinic-queue-management'); ?></a>
    </p>
    <p
        class="clinic-queue-booking-register-gate__switch-row clinic-queue-booking-register-gate__switch-row--register"
        hidden
        aria-hidden="true"
    >
        <?php esc_html_e('משתמש קיים?', 'clinic-queue-management'); ?>
        <?php echo ' '; ?>
        <a
            href="#"
            class="clinic-queue-booking-register-gate__switch-link"
            data-clinic-queue-register-gate-switch="login"
        ><?php esc_html_e('התחברות', 'clinic-queue-management'); ?></a>
    </p>
</div>
    <?php
    return;
}

$current_user   = $data['current_user'];
$family_members = !empty($data['family_members']) && is_array($data['family_members']) ? $data['family_members'] : array();
$ad             = isset($data['appointment_data']) && is_array($data['appointment_data']) ? $data['appointment_data'] : array();

$appt_date         = !empty($ad['date']) ? $ad['date'] : '';
$appt_time         = !empty($ad['time']) ? $ad['time'] : '';
$treatment_type         = !empty($ad['treatment_type']) ? $ad['treatment_type'] : '';
$treatment_type_display = !empty($ad['treatment_type_display']) ? $ad['treatment_type_display'] : '';
$treatment_cost         = !empty($ad['treatment_cost']) ? absint($ad['treatment_cost']) : 0;
$treatment_cost_display = !empty($ad['treatment_cost_display']) ? (string) $ad['treatment_cost_display'] : '';
$clinic_name        = !empty($ad['clinic_name']) ? $ad['clinic_name'] : '';
$clinic_specialty   = !empty($ad['clinic_specialty']) ? $ad['clinic_specialty'] : '';
$clinic_specialties = array_values(
    array_filter(
        array_map(
            'trim',
            preg_split('/\s*,\s*/', (string) $clinic_specialty, -1, PREG_SPLIT_NO_EMPTY)
        )
    )
);
$clinic_specialties_visible   = array_slice($clinic_specialties, 0, 3);
$clinic_specialties_remaining = max(0, count($clinic_specialties) - 3);
$clinic_thumbnail   = !empty($ad['clinic_thumbnail']) ? $ad['clinic_thumbnail'] : '';
$clinic_address     = !empty($ad['clinic_address']) ? $ad['clinic_address'] : '';
$doctor_name        = !empty($ad['doctor_name']) ? trim((string) $ad['doctor_name']) : '';
$doctor_url         = !empty($ad['doctor_url']) ? trim((string) $ad['doctor_url']) : '';
$has_treating_doctor = !empty($ad['has_treating_doctor']);
$treating_doctor_partial = __DIR__ . '/partials/booking-form-treating-doctor.php';
$scheduler_id      = !empty($ad['scheduler_id']) ? (int) $ad['scheduler_id'] : 0;
$proxy_schedule_id = !empty($ad['proxy_schedule_id']) ? (string) $ad['proxy_schedule_id'] : '';
$duration          = !empty($ad['duration']) ? (int) $ad['duration'] : 0;
$clinix_reason_id  = !empty($ad['clinix_reason_id']) ? (string) $ad['clinix_reason_id'] : '';
$referrer_url      = !empty($ad['referrer_url']) ? $ad['referrer_url'] : '';
$appt_date_display = !empty($ad['appt_date_display']) ? (string) $ad['appt_date_display'] : $appt_date;
$treatment_label   = $treatment_type_display !== '' ? $treatment_type_display : $treatment_type;

$booking_nonce = wp_create_nonce('save_booking_ajax_nonce');

$icon_url_calendar = plugins_url('assets/images/icons/calendar-pink-icon.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
$icon_url_clock    = plugins_url('assets/images/icons/Clock.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
$icon_url_medical  = plugins_url('assets/images/icons/Medical.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
$icon_url_map      = plugins_url('assets/images/icons/MapPoint.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
$icon_url_tag      = plugins_url('assets/images/icons/tag-icon.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
?>

<div
    class="booking-form-wrapper jet-form-builder jet-form-builder--default clinic-queue-jetform-mui"
    dir="rtl"
>

    <?php if ($clinic_name !== '' || $appt_date !== '' || $treatment_type !== '') : ?>
        <div class="booking-appointment-summary">
            <?php if ($clinic_name !== '' || $clinic_thumbnail !== '' || !empty($clinic_specialties) || $doctor_name !== '' || $clinic_address !== '') : ?>
                <div class="appointment-clinic-card">
                    <?php if ($clinic_thumbnail !== '') : ?>
                        <div class="clinic-thumbnail">
                            <img
                                src="<?php echo esc_url($clinic_thumbnail); ?>"
                                alt="<?php echo esc_attr($clinic_name); ?>"
                                width="96"
                                loading="lazy"
                            />
                        </div>
                    <?php endif; ?>
                    <div class="clinic-card-info">
                        <?php if ($clinic_name !== '') : ?>
                            <div class="clinic-card-name"><?php echo esc_html($clinic_name); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($clinic_specialties)) : ?>
                            <div class="clinic-specialties">
                                <?php foreach ($clinic_specialties_visible as $spec_label) : ?>
                                    <span class="clinic-specialty"><?php echo esc_html($spec_label); ?></span>
                                <?php endforeach; ?>
                                <?php if ($clinic_specialties_remaining > 0) : ?>
                                    <span class="clinic-specialty clinic-specialty--more"><?php echo esc_html('+' . (string) $clinic_specialties_remaining); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php include $treating_doctor_partial; ?>
                        <?php if ($clinic_address !== '') : ?>
                            <div class="clinic-address">
                                <img
                                    class="clinic-address-icon"
                                    src="<?php echo esc_url($icon_url_map); ?>"
                                    alt=""
                                    width="24"
                                    height="24"
                                    decoding="async"
                                />
                                <span class="clinic-address-text"><?php echo esc_html($clinic_address); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($appt_date !== '' || $appt_time !== '' || $treatment_type !== '') : ?>
                <h2 class="booking-form-section-title"><?php esc_html_e('פרטי התור', 'clinic-queue-management'); ?></h2>
                <div class="appointment-info-card">
                    <?php if ($appt_date !== '') : ?>
                        <div class="appointment-info-item">
                            <img
                                class="appointment-info-icon"
                                src="<?php echo esc_url($icon_url_calendar); ?>"
                                alt=""
                                width="24"
                                height="24"
                                decoding="async"
                            />
                            <span class="appointment-info-value"><?php echo esc_html($appt_date_display); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($appt_time !== '') : ?>
                        <div class="appointment-info-item">
                            <img
                                class="appointment-info-icon"
                                src="<?php echo esc_url($icon_url_clock); ?>"
                                alt=""
                                width="24"
                                height="24"
                                decoding="async"
                            />
                            <span class="appointment-info-value"><?php echo esc_html($appt_time); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($treatment_type !== '') : ?>
                        <div class="appointment-info-item">
                            <img
                                class="appointment-info-icon"
                                src="<?php echo esc_url($icon_url_medical); ?>"
                                alt=""
                                width="24"
                                height="24"
                                decoding="async"
                            />
                            <span class="appointment-info-value"><?php echo esc_html($treatment_type_display !== '' ? $treatment_type_display : $treatment_type); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($treatment_cost > 0 && $treatment_cost_display !== '') : ?>
                        <div class="appointment-info-item">
                            <img
                                class="appointment-info-icon appointment-info-icon--price"
                                src="<?php echo esc_url($icon_url_tag); ?>"
                                alt=""
                                width="22"
                                height="22"
                                decoding="async"
                            />
                            <span class="appointment-info-value"><?php echo esc_html($treatment_cost_display); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <hr class="booking-form-section-divider" />
    <?php endif; ?>

    <div
        id="booking-message"
        class="booking-form-message"
        role="alert"
        aria-live="polite"
        hidden
    >
        <p class="booking-form-message__text"></p>
        <p class="booking-form-message__action" hidden></p>
    </div>

    <h2 class="booking-form-section-title"><?php esc_html_e('פרטי מזמין התור', 'clinic-queue-management'); ?></h2>

    <form id="ajax-booking-form" class="jet-form-builder-form" method="post" action="#">
        <input type="hidden" name="action" value="submit_appointment_ajax" />
        <input type="hidden" name="security" value="<?php echo esc_attr($booking_nonce); ?>" />
        <input type="hidden" name="scheduler_id" value="<?php echo esc_attr((string) $scheduler_id); ?>" />
        <input type="hidden" name="proxy_schedule_id" value="<?php echo esc_attr($proxy_schedule_id); ?>" />
        <input type="hidden" name="duration" value="<?php echo esc_attr((string) $duration); ?>" />
        <input type="hidden" name="treatment_type" value="<?php echo esc_attr($treatment_type); ?>" />
        <input type="hidden" name="clinix_reason_id" value="<?php echo esc_attr($clinix_reason_id); ?>" />
        <input type="hidden" name="referrer_url" id="referrer_url" value="<?php echo esc_attr($referrer_url); ?>" />
        <input type="hidden" name="appt_date" id="appt_date" value="<?php echo esc_attr($appt_date); ?>" />
        <input type="hidden" name="appt_time" id="appt_time" value="<?php echo esc_attr($appt_time); ?>" />

        <fieldset class="booking-form-fieldset booking-form-fieldset--pills">
            <legend class="booking-form-field-label"><?php esc_html_e('עבור מי התור', 'clinic-queue-management'); ?></legend>
            <div class="pills-group">
            <div id="patients-list-container">
                <?php include __DIR__ . '/partials/patient-select-radios.php'; ?>
            </div>
            </div>
        </fieldset>

        <div class="booking-form-add-patient-wrap">
            <a href="#" class="trigger-add-member booking-form-add-family-trigger">
                <?php echo esc_html__('+ הוספת בן משפחה', 'clinic-queue-management'); ?>
            </a>
        </div>

        <fieldset class="booking-form-fieldset booking-form-fieldset--pills">
            <legend class="booking-form-field-label"><?php esc_html_e('האם זה טיפול ראשון במרפאה?', 'clinic-queue-management'); ?></legend>
            <div class="pills-group">
            <label class="jet-form-builder__field-wrap">
                <input type="radio" name="first_visit" value="כן" class="jet-form-builder__field radio-field" checked />
                <span class="jet-form-builder__field-label"><?php echo esc_html__('כן - טיפול ראשון שלי במרפאה', 'clinic-queue-management'); ?></span>
            </label>
            <label class="jet-form-builder__field-wrap">
                <input type="radio" name="first_visit" value="לא" class="jet-form-builder__field radio-field" />
                <span class="jet-form-builder__field-label"><?php echo esc_html__('לא, כבר בקרתי במרפאה', 'clinic-queue-management'); ?></span>
            </label>
            </div>
        </fieldset>

        <div class="jet-form-builder__row field-type-text-field">
            <div class="booking-form-field-label">
                <?php esc_html_e('מספר טלפון נוסף', 'clinic-queue-management'); ?>
            </div>
            <div class="booking-form-field-subhint">
                <?php esc_html_e('אופציונלי — דרך נוספת ליצירת קשר. לא מחליף את מספר הטלפון הראשי השמור בפרופיל.', 'clinic-queue-management'); ?>
            </div>
            <div class="jet-form-builder__field-wrap">
                <input
                    type="text"
                    name="additional_phone"
                    id="additional_phone"
                    class="jet-form-builder__field"
                    inputmode="tel"
                    autocomplete="tel"
                    aria-label="<?php esc_attr_e('מספר טלפון נוסף', 'clinic-queue-management'); ?>"
                />
                <div class="floating-label">
                    <p><?php esc_html_e('מספר טלפון נוסף', 'clinic-queue-management'); ?></p>
                </div>
            </div>
        </div>

        <div class="jet-form-builder__row field-type-text-field clinic-queue-jetform-mui__textarea">
            <div class="booking-form-field-label">
                <?php esc_html_e('הערה אישית למרפאה', 'clinic-queue-management'); ?>
            </div>
            <p class="booking-form-field-subhint">
                <?php esc_html_e('הערות או בקשות מיוחדות לקראת הפגישה - כתבו כאן', 'clinic-queue-management'); ?>
            </p>
            <div class="jet-form-builder__field-wrap">
                <textarea
                    name="notes"
                    id="notes"
                    class="jet-form-builder__field"
                    rows="4"
                    aria-label="<?php esc_attr_e('הערה אישית למרפאה', 'clinic-queue-management'); ?>"
                ></textarea>
                <div class="floating-label">
                    <p><?php esc_html_e('הערות', 'clinic-queue-management'); ?></p>
                </div>
            </div>
        </div>

        <div class="jet-form-builder__row field-type-checkbox-field clinic-queue-booking-form__consent">
            <div class="jet-form-builder__field-wrap">
                <label class="jet-form-builder__field-label" for="clinic_data_consent">
                    <input
                        type="checkbox"
                        name="clinic_data_consent"
                        id="clinic_data_consent"
                        value="1"
                        class="jet-form-builder__field check-field"
                        required
                        aria-label="<?php esc_attr_e('מאשר/ת שיתוף של המידע שלי עם המרפאה.', 'clinic-queue-management'); ?>"
                    />
                    <?php esc_html_e('מאשר/ת שיתוף של המידע שלי עם המרפאה.', 'clinic-queue-management'); ?>
                </label>
            </div>
        </div>

        <?php // Scroll anchor: when to release sticky CTA and return to natural position ?>
        <div class="booking-form-submit-bar-anchor" aria-hidden="true"></div>
        <div class="booking-form-submit-bar">
            <button type="submit" id="submit-btn" class="jet-form-builder__action-button">
                <?php echo esc_html__('קבע את התור', 'clinic-queue-management'); ?>
                <span class="loader" aria-hidden="true"></span>
            </button>
        </div>
    </form>

    <template id="clinic-queue-booking-id-modal-tpl">
        <div
            class="booking-modal-overlay clinic-queue-booking-id-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="clinic-queue-booking-id-modal-title"
        >
            <div class="booking-modal clinic-queue-booking-id-modal">
                <h2 id="clinic-queue-booking-id-modal-title" class="booking-modal__title clinic-queue-booking-id-modal__title">
                    <?php esc_html_e('נדרשת השלמת תעודת זהות', 'clinic-queue-management'); ?>
                </h2>
                <p class="booking-modal__message clinic-queue-booking-id-modal__message">
                    <?php esc_html_e('כדי להשלים את קביעת התור, יש להזין תעודת זהות. הנתון יישמר בפרטים האישיים שלך ולא תידרש להזין אותו שוב בקביעות תור עתידיות.', 'clinic-queue-management'); ?>
                </p>
                <div class="clinic-queue-booking-id-modal__field-wrap">
                    <input
                        type="text"
                        class="jet-form-builder__field clinic-queue-booking-id-modal__input"
                        id="clinic-queue-booking-id-input"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="9"
                        aria-label="<?php esc_attr_e('מספר תעודת זהות', 'clinic-queue-management'); ?>"
                    />
                    <p class="clinic-queue-booking-id-modal__error" role="alert" hidden></p>
                </div>
                <div class="clinic-queue-booking-id-modal__actions">
                    <button type="button" class="booking-modal__button clinic-queue-booking-id-modal__btn clinic-queue-booking-id-modal__btn--save">
                        <?php esc_html_e('שמירה והמשך', 'clinic-queue-management'); ?>
                    </button>
                    <button type="button" class="clinic-queue-booking-id-modal__btn clinic-queue-booking-id-modal__btn--close">
                        <?php esc_html_e('סגור', 'clinic-queue-management'); ?>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <template id="clinic-queue-booking-success-modal-tpl">
        <div
            class="booking-modal-overlay clinic-queue-booking-success-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="clinic-queue-booking-success-title"
        >
            <div class="booking-modal booking-modal--success clinic-queue-booking-success-modal">
                <div class="clinic-queue-booking-success-modal__confetti" aria-hidden="true">
                    <img
                        class="clinic-queue-booking-success-modal__confetti-img"
                        src=""
                        alt=""
                        width="505"
                        height="270"
                        decoding="async"
                    />
                </div>
                <div class="clinic-queue-booking-success-modal__icon-wrap" aria-hidden="true">
                    <img
                        class="clinic-queue-booking-success-modal__check-icon"
                        src=""
                        alt=""
                        width="120"
                        height="120"
                        decoding="async"
                    />
                </div>
                <div class="clinic-queue-booking-success-modal__content">
                    <h2 id="clinic-queue-booking-success-title" class="clinic-queue-booking-success-modal__title">
                        <span class="clinic-queue-booking-success-modal__title-prefix"></span>
                        <span class="clinic-queue-booking-success-modal__doctor-name"></span>
                        <span class="clinic-queue-booking-success-modal__title-suffix"></span>
                    </h2>
                    <p class="clinic-queue-booking-success-modal__datetime"></p>
                    <p class="clinic-queue-booking-success-modal__location is-hidden">
                        <img
                            class="clinic-queue-booking-success-modal__location-icon"
                            src="<?php echo esc_url($icon_url_map); ?>"
                            alt=""
                            width="20"
                            height="20"
                            decoding="async"
                            aria-hidden="true"
                        />
                        <span class="clinic-queue-booking-success-modal__location-text"></span>
                    </p>
                </div>
                <div class="clinic-queue-booking-success-modal__actions">
                    <button type="button" class="clinic-queue-booking-success-modal__btn clinic-queue-booking-success-modal__btn--calendar">
                        <?php esc_html_e('הוסף ליומן', 'clinic-queue-management'); ?>
                    </button>
                    <button type="button" class="clinic-queue-booking-success-modal__btn clinic-queue-booking-success-modal__btn--close">
                        <?php esc_html_e('סגור', 'clinic-queue-management'); ?>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
