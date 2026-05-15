<?php
/**
 * תצוגת שורטקוד [booking_form]
 *
 * @package Clinic_Queue_Management
 *
 * @var array $data נתונים מ-prepare_data(), או מערך אורח עם require_login_register ו-appointment_data
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
    $doctor_name       = !empty($ad['doctor_name']) ? $ad['doctor_name'] : '';
    $doctor_specialty    = !empty($ad['doctor_specialty']) ? $ad['doctor_specialty'] : '';
    $doctor_specialties  = array_values(
        array_filter(
            array_map(
                'trim',
                preg_split('/\s*,\s*/', (string) $doctor_specialty, -1, PREG_SPLIT_NO_EMPTY)
            )
        )
    );
    $doctor_thumbnail  = !empty($ad['doctor_thumbnail']) ? $ad['doctor_thumbnail'] : '';
    $clinic_address    = !empty($ad['clinic_address']) ? $ad['clinic_address'] : '';
    $clinic_name       = !empty($ad['clinic_name']) ? $ad['clinic_name'] : '';

    $appt_date_display = $appt_date;
    if ($appt_date !== '') {
        $dt_gate = DateTimeImmutable::createFromFormat('Y-m-d', trim($appt_date));
        if ($dt_gate instanceof DateTimeImmutable) {
            $appt_date_display = $dt_gate->format('d/m/Y');
        }
    }

    $guest_login_html_fragment = isset($data['guest_login_html_fragment'])
        ? (string) $data['guest_login_html_fragment']
        : '';

    $icon_url_calendar = plugins_url('assets/images/icons/calendar-pink-icon.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
    $icon_url_clock    = plugins_url('assets/images/icons/Clock.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
    $icon_url_medical  = plugins_url('assets/images/icons/Medical.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
    $icon_url_map      = plugins_url('assets/images/icons/MapPoint.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
    ?>
<div
    class="booking-form-wrapper jet-form-builder jet-form-builder--default clinic-queue-jetform-mui clinic-queue-booking--register-gate"
    dir="rtl"
    data-clinic-queue-register-gate-root=""
>
    <?php if ($doctor_name !== '' || $appt_date !== '' || $treatment_type !== '') : ?>
        <div class="booking-appointment-summary">
            <?php if ($doctor_name !== '') : ?>
                <div class="appointment-doctor-card">
                    <?php if ($doctor_thumbnail !== '') : ?>
                        <div class="doctor-thumbnail">
                            <img
                                src="<?php echo esc_url($doctor_thumbnail); ?>"
                                alt=""
                                width="80"
                                height="80"
                                loading="lazy"
                            />
                        </div>
                    <?php endif; ?>
                    <div class="doctor-info">
                        <div class="doctor-name"><?php echo esc_html($doctor_name); ?></div>
                        <?php if (!empty($doctor_specialties)) : ?>
                            <div class="doctor-specialties">
                                <?php foreach ($doctor_specialties as $spec_label) : ?>
                                    <span class="doctor-specialty"><?php echo esc_html($spec_label); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($clinic_address !== '' || $clinic_name !== '') : ?>
                            <div class="clinic-address">
                                <img
                                    class="clinic-address-icon"
                                    src="<?php echo esc_url($icon_url_map); ?>"
                                    alt=""
                                    width="24"
                                    height="24"
                                    decoding="async"
                                />
                                <span class="clinic-address-text">
                                    <?php if ($clinic_name !== '') : ?>
                                        <span class="clinic-name"><?php echo esc_html($clinic_name); ?></span>
                                        <?php if ($clinic_address !== '') : ?>
                                            <span> · </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($clinic_address !== '') : ?>
                                        <?php echo esc_html($clinic_address); ?>
                                    <?php endif; ?>
                                </span>
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
$popup_id       = isset($data['popup_id']) ? (string) $data['popup_id'] : '3953';
$ad             = isset($data['appointment_data']) && is_array($data['appointment_data']) ? $data['appointment_data'] : array();

$appt_date         = !empty($ad['date']) ? $ad['date'] : '';
$appt_time         = !empty($ad['time']) ? $ad['time'] : '';
$treatment_type         = !empty($ad['treatment_type']) ? $ad['treatment_type'] : '';
$treatment_type_display = !empty($ad['treatment_type_display']) ? $ad['treatment_type_display'] : '';
$doctor_name       = !empty($ad['doctor_name']) ? $ad['doctor_name'] : '';
$doctor_specialty   = !empty($ad['doctor_specialty']) ? $ad['doctor_specialty'] : '';
$doctor_specialties = array_values(
    array_filter(
        array_map(
            'trim',
            preg_split('/\s*,\s*/', (string) $doctor_specialty, -1, PREG_SPLIT_NO_EMPTY)
        )
    )
);
$doctor_thumbnail  = !empty($ad['doctor_thumbnail']) ? $ad['doctor_thumbnail'] : '';
$clinic_address    = !empty($ad['clinic_address']) ? $ad['clinic_address'] : '';
$clinic_name       = !empty($ad['clinic_name']) ? $ad['clinic_name'] : '';
$scheduler_id      = !empty($ad['scheduler_id']) ? (int) $ad['scheduler_id'] : 0;
$proxy_schedule_id = !empty($ad['proxy_schedule_id']) ? (string) $ad['proxy_schedule_id'] : '';
$duration          = !empty($ad['duration']) ? (int) $ad['duration'] : 0;
$clinix_reason_id  = !empty($ad['clinix_reason_id']) ? (string) $ad['clinix_reason_id'] : '';
$referrer_url      = !empty($ad['referrer_url']) ? $ad['referrer_url'] : '';

$appt_date_display = $appt_date;
if ($appt_date !== '') {
    $dt_main = DateTimeImmutable::createFromFormat('Y-m-d', trim($appt_date));
    if ($dt_main instanceof DateTimeImmutable) {
        $appt_date_display = $dt_main->format('d/m/Y');
    }
}

$booking_nonce = wp_create_nonce('save_booking_ajax_nonce');

$icon_url_calendar = plugins_url('assets/images/icons/calendar-pink-icon.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
$icon_url_clock    = plugins_url('assets/images/icons/Clock.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
$icon_url_medical  = plugins_url('assets/images/icons/Medical.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
$icon_url_map      = plugins_url('assets/images/icons/MapPoint.svg', CLINIC_QUEUE_MANAGEMENT_FILE);
?>

<div
    class="booking-form-wrapper jet-form-builder jet-form-builder--default clinic-queue-jetform-mui"
    dir="rtl"
>

    <?php if ($doctor_name !== '' || $appt_date !== '' || $treatment_type !== '') : ?>
        <div class="booking-appointment-summary">
            <?php if ($doctor_name !== '') : ?>
                <div class="appointment-doctor-card">
                    <?php if ($doctor_thumbnail !== '') : ?>
                        <div class="doctor-thumbnail">
                            <img
                                src="<?php echo esc_url($doctor_thumbnail); ?>"
                                alt=""
                                width="80"
                                height="80"
                                loading="lazy"
                            />
                        </div>
                    <?php endif; ?>
                    <div class="doctor-info">
                        <div class="doctor-name"><?php echo esc_html($doctor_name); ?></div>
                        <?php if (!empty($doctor_specialties)) : ?>
                            <div class="doctor-specialties">
                                <?php foreach ($doctor_specialties as $spec_label) : ?>
                                    <span class="doctor-specialty"><?php echo esc_html($spec_label); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($clinic_address !== '' || $clinic_name !== '') : ?>
                            <div class="clinic-address">
                                <img
                                    class="clinic-address-icon"
                                    src="<?php echo esc_url($icon_url_map); ?>"
                                    alt=""
                                    width="24"
                                    height="24"
                                    decoding="async"
                                />
                                <span class="clinic-address-text">
                                    <?php if ($clinic_name !== '') : ?>
                                        <span class="clinic-name"><?php echo esc_html($clinic_name); ?></span>
                                        <?php if ($clinic_address !== '') : ?>
                                            <span> · </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($clinic_address !== '') : ?>
                                        <?php echo esc_html($clinic_address); ?>
                                    <?php endif; ?>
                                </span>
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
                </div>
            <?php endif; ?>
        </div>

        <hr class="booking-form-section-divider" />
    <?php endif; ?>

    <h2 class="booking-form-section-title"><?php esc_html_e('פרטי מזמין התור', 'clinic-queue-management'); ?></h2>

    <div id="booking-message" class="msg-box msg-error" role="alert" style="display:none;"></div>

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
                <label class="jet-form-builder__field-wrap">
                    <input
                        type="radio"
                        name="patient_select"
                        id="pat_self"
                        value="self"
                        class="jet-form-builder__field radio-field"
                        checked
                    />
                    <span class="jet-form-builder__field-label">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s display name */
                                __('עבורי - %s', 'clinic-queue-management'),
                                $current_user->display_name
                            )
                        );
                        ?>
                    </span>
                </label>
                <?php foreach ($family_members as $index => $member) : ?>
                    <?php
                    $member_name = isset($member['first_name']) ? (string) $member['first_name'] : __('בן משפחה', 'clinic-queue-management');
                    ?>
                    <label class="jet-form-builder__field-wrap">
                        <input
                            type="radio"
                            name="patient_select"
                            id="pat_<?php echo esc_attr((string) $index); ?>"
                            value="family_<?php echo esc_attr((string) $index); ?>"
                            class="jet-form-builder__field radio-field"
                        />
                        <span class="jet-form-builder__field-label"><?php echo esc_html($member_name); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            </div>
        </fieldset>

        <div class="booking-form-add-patient-wrap">
            <a href="#" class="add-patient-trigger" data-popup-id="<?php echo esc_attr($popup_id); ?>">
                <?php echo esc_html__('+ הוספת בן משפחה', 'clinic-queue-management'); ?>
            </a>
        </div>

        <div class="jet-form-builder__row field-type-text-field">
            <div class="jet-form-builder__label-text helper-text">
                <?php esc_html_e('מספר טלפון של המטופל', 'clinic-queue-management'); ?>
            </div>
            <div class="jet-form-builder__field-wrap">
                <input
                    type="text"
                    name="phone"
                    id="phone"
                    class="jet-form-builder__field"
                    inputmode="tel"
                    autocomplete="tel"
                    required
                    aria-label="<?php esc_attr_e('הזן מספר טלפון', 'clinic-queue-management'); ?>"
                />
                <div class="floating-label">
                    <p><?php esc_html_e('הזן מספר טלפון', 'clinic-queue-management'); ?></p>
                </div>
            </div>
        </div>

        <div class="jet-form-builder__row field-type-text-field">
            <div class="jet-form-builder__field-wrap">
                <input
                    type="text"
                    name="id_number"
                    id="id_number"
                    class="jet-form-builder__field"
                    inputmode="numeric"
                    autocomplete="off"
                    required
                    aria-label="<?php esc_attr_e('מספר תעודת זהות', 'clinic-queue-management'); ?>"
                />
                <div class="floating-label">
                    <p><?php esc_html_e('מספר תעודת זהות', 'clinic-queue-management'); ?></p>
                </div>
            </div>
        </div>

        <fieldset class="booking-form-fieldset booking-form-fieldset--pills">
            <legend class="booking-form-field-label"><?php esc_html_e('האם זה טיפול ראשון במרפאה?', 'clinic-queue-management'); ?></legend>
            <div class="pills-group">
            <label class="jet-form-builder__field-wrap">
                <input type="radio" name="first_visit" value="לא" class="jet-form-builder__field radio-field" checked />
                <span class="jet-form-builder__field-label"><?php echo esc_html__('לא, כבר בקרתי במרפאה', 'clinic-queue-management'); ?></span>
            </label>
            <label class="jet-form-builder__field-wrap">
                <input type="radio" name="first_visit" value="כן" class="jet-form-builder__field radio-field" />
                <span class="jet-form-builder__field-label"><?php echo esc_html__('כן - טיפול ראשון שלי במרפאה', 'clinic-queue-management'); ?></span>
            </label>
            </div>
        </fieldset>

        <div class="jet-form-builder__row field-type-text-field clinic-queue-jetform-mui__textarea">
            <div class="jet-form-builder__label-text helper-text">
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

        <?php // עוגן לגלילה: מתי לשחרר CTA דביק ולחזור למיקום הטבעי ?>
        <div class="booking-form-submit-bar-anchor" aria-hidden="true"></div>
        <div class="booking-form-submit-bar">
            <button type="submit" id="submit-btn" class="jet-form-builder__action-button">
                <?php echo esc_html__('קבע את התור', 'clinic-queue-management'); ?>
                <span class="loader" style="display:none;" aria-hidden="true">⌛</span>
            </button>
        </div>
    </form>
</div>
