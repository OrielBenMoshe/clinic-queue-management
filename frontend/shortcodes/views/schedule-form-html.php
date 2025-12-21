<?php
/**
 * Schedule Form HTML View
 * Template for [clinic_add_schedule_form] shortcode
 * 
 * @var array $data Data prepared by the shortcode class
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract data
$svg_google_calendar = $data['svg_google_calendar'] ?? '';
$svg_clinix_logo = $data['svg_clinix_logo'] ?? '';
$svg_calendar_icon = $data['svg_calendar_icon'] ?? '';
$svg_trash_icon = $data['svg_trash_icon'] ?? '';
$svg_checkbox_checked = $data['svg_checkbox_checked'] ?? '';
$svg_checkbox_unchecked = $data['svg_checkbox_unchecked'] ?? '';
$days_of_week = $data['days_of_week'] ?? array();
?>

<div class="jet-form-builder jet-form-builder--default clinic-add-schedule-form">

    <!-- Step 1: Action Selection -->
    <div class="step step-start is-active" data-step="start">
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">איזה פעולה תרצו לעשות</div>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-radio-field action-cards">
            <label class="jet-form-builder__field-wrap action-card" data-value="google">
                <input class="jet-form-builder__field radio-field" type="radio" name="jet_action_choice" value="google">
                <div class="card-title">חיבור יומן</div>
                <div aria-hidden="true" class="card-icon">
                    <?php echo $svg_google_calendar; ?>
                </div>
                <div class="card-desc">Lorem ipsum dolor sit amet consectetur.</div>
            </label>
            <label class="jet-form-builder__field-wrap action-card" data-value="clinix">
                <input class="jet-form-builder__field radio-field" type="radio" name="jet_action_choice" value="clinix">
                <div class="card-title">הוספת יומן</div>
                <div aria-hidden="true" class="card-icon">
                    <?php echo $svg_clinix_logo; ?>
                </div>
                <div class="card-desc">Lorem ipsum dolor sit amet consectetur.</div>
            </label>
        </div>
        <div class="jet-form-builder__row field-type-submit-field continue-wrap">
            <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
                <button type="button" class="jet-form-builder__action-button jet-form-builder__submit continue-btn"
                    disabled>המשך</button>
            </div>
        </div>
    </div>

    <!-- Step 2: Google Calendar Details -->
    <div class="step google-step" data-step="google" aria-hidden="true">
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">חיבור
                    יומן רופא חדש</div>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-select-field is-filled clinic-select-field">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text helper-text">בחר מרפאה</div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <select class="jet-form-builder__field select-field clinic-select" name="clinic_id">
                    <option value="">טוען מרפאות...</option>
                </select>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-select-field is-filled doctor-search-field">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text helper-text">בחר רופא מתוך רשימת אנשי צוות בפורטל</div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <select class="jet-form-builder__field select-field doctor-select" name="doctor_id" disabled>
                    <option value="">בחר מרפאה תחילה</option>
                </select>
            </div>
        </div>
        <div class="separator" aria-hidden="true"><span>או</span></div>
        <div class="jet-form-builder__row field-type-text-field is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text helper-text">חיבור יומן שלא נמצא בפורטל</div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <input type="text" class="jet-form-builder__field text-field manual-calendar"
                    name="manual_calendar_name" id="manual-calendar-input">
                <label for="manual-calendar-input" class="floating-label">
                    <p>שם יומן</p>
                </label>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-submit-field continue-wrap">
            <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
                <button type="button"
                    class="jet-form-builder__action-button jet-form-builder__submit continue-btn continue-btn-google"
                    disabled>המשך</button>
            </div>
        </div>
    </div>

    <!-- Step 3: Schedule Settings -->
    <div class="step schedule-settings-step" data-step="schedule-settings" aria-hidden="true">
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">הגדרת
                    ימים ושעות עבודה</div>
            </div>
        </div>
        <div class="days-schedule-container">
            <?php
            foreach ($days_of_week as $day_key => $day_label) {
                $default_end = ($day_key === 'friday') ? '16:00' : '18:00';
                ?>
                <div class="day-row" data-day-row="<?php echo esc_attr($day_key); ?>">
                    <label class="day-checkbox custom-checkbox">
                        <input type="checkbox" name="day_<?php echo esc_attr($day_key); ?>"
                            value="<?php echo esc_attr($day_key); ?>" data-day="<?php echo esc_attr($day_key); ?>">
                        <span class="checkbox-icon">
                            <span class="unchecked-icon"><?php echo $svg_checkbox_unchecked; ?></span>
                            <span class="checked-icon"><?php echo $svg_checkbox_checked; ?></span>
                        </span>
                        <span class="checkbox-label"><?php echo esc_html($day_label); ?></span>
                    </label>
                    <?php
                    if (isset($data['generate_day_time_range_callback']) && is_callable($data['generate_day_time_range_callback'])) {
                        echo call_user_func($data['generate_day_time_range_callback'], $day_key, $day_label, $default_end, $svg_trash_icon);
                    }
                    ?>
                </div>
                <?php
            }
            ?>
        </div>
        <div class="jet-form-builder__row field-type-heading is-filled" style="margin-top:2rem;">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">הגדרת שם
                    ומשך טיפול</div>
            </div>
        </div>
        <div class="treatments-repeater">
            <div class="treatment-row">
                <div class="jet-form-builder__row field-type-text-field is-filled treatment-field">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">סוג טיפול</div>
                    </div>
                    <div class="jet-form-builder__field-wrap">
                        <?php $treatment_name_id = 'treatment-name-' . uniqid(); ?>
                        <input type="text" class="jet-form-builder__field" name="treatment_name[]"
                            id="<?php echo esc_attr($treatment_name_id); ?>" placeholder="שם הטיפול">
                    </div>
                </div>
                <div class="jet-form-builder__row field-type-select-field is-filled treatment-field">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">תת-תחום</div>
                    </div>
                    <div class="jet-form-builder__field-wrap">
                        <select class="jet-form-builder__field select-field subspeciality-select"
                            name="treatment_subspeciality[]">
                            <option value="">בחר תת-תחום</option>
                        </select>
                    </div>
                </div>
                <div class="jet-form-builder__row field-type-number-field is-filled treatment-field">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">מחיר</div>
                    </div>
                    <div class="jet-form-builder__field-wrap">
                        <?php $treatment_price_id = 'treatment-price-' . uniqid(); ?>
                        <input type="number" class="jet-form-builder__field" name="treatment_price[]"
                            id="<?php echo esc_attr($treatment_price_id); ?>" min="0" step="1" placeholder="150">
                    </div>
                </div>
                <div class="jet-form-builder__row field-type-select-field is-filled treatment-field">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">משך זמן</div>
                    </div>
                    <div class="jet-form-builder__field-wrap">
                        <select class="jet-form-builder__field select-field" name="treatment_duration[]">
                            <?php
                            if (isset($data['generate_duration_options_callback']) && is_callable($data['generate_duration_options_callback'])) {
                                echo call_user_func($data['generate_duration_options_callback'], 45);
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <button type="button" class="remove-treatment-btn"
                    style="display:none;"><?php echo $svg_trash_icon; ?></button>
            </div>
        </div>
        <a type="button" class="add-treatment-btn">
            <span>+</span> הוספת טיפול
        </a>
        <div class="jet-form-builder__row field-type-submit-field continue-wrap">
            <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
                <button type="button"
                    class="jet-form-builder__action-button jet-form-builder__submit save-schedule-btn">שמירת הגדרות
                    יומן</button>
            </div>
        </div>
    </div>

    <!-- Success Screen -->
    <div class="step success-step" data-step="success" aria-hidden="true" style="display:none;">
        <div class="success-content">
            <div class="success-icon">
                <?php echo $svg_calendar_icon; ?>
            </div>
            <h2 class="success-title">היומן הוגדר בהצלחה!</h2>
            <p class="success-subtitle">נא לחבר את היומן מתוך יומן גוגל</p>

            <div class="success-schedule-summary">
                <h3>ימי עבודה</h3>
                <div class="schedule-days-list">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>

            <div class="success-actions">
                <button type="button" class="jet-form-builder__action-button jet-form-builder__submit sync-google-btn">
                    סנכרון יומן מגוגל
                </button>
                <a href="#" class="transfer-request-link">העבר בקשת סנכרון לכרטיס רופא</a>
            </div>
        </div>
    </div>
</div>