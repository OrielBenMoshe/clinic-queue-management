<?php
/**
 * Schedule Form HTML View
 * Template for [clinic_add_schedule_form] shortcode
 *
 * סדר שלבים:
 * - גוגל: start → google (מרפאה/רופא/שם) → schedule-settings → google-connect (בחירת אופן חיבור) → calendar-selection → final-success
 *         או:  ... → google-connect → שליחת בקשה לרופא → final-success
 * - קליניקס: start → google (מרפאה/רופא/שם) → clinix (טוקן) → calendar-selection → schedule-settings (צפייה) → final-success
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
$svg_calendar_image = $data['svg_calendar_image'] ?? '';
$svg_trash_icon = $data['svg_trash_icon'] ?? '';
$svg_checkbox_checked = $data['svg_checkbox_checked'] ?? '';
$svg_checkbox_unchecked = $data['svg_checkbox_unchecked'] ?? '';
$svg_checkbox_checked_disabled = $data['svg_checkbox_checked_disabled'] ?? '';
$days_of_week = $data['days_of_week'] ?? array();
?>

<div class="jet-form-builder jet-form-builder--default clinic-add-schedule-form">

    <!-- לואדר אחד לכל הטופס – מוצג על ידי הוספת class is-visible, טקסט ברירת מחדל ניתן להחלפה -->
    <div class="schedule-form-loader-overlay" aria-hidden="true" aria-busy="false">
        <div class="spinner"></div>
        <p class="schedule-form-loader-overlay__text">טוען...</p>
    </div>

    <!-- Back link - first element at top, visible from step 2 onward -->
    <div class="schedule-form-back-wrap">
        <button type="button" class="schedule-form-back-btn"
            aria-label="<?php echo esc_attr__('חזור', 'clinic-queue-management'); ?>">
            <span class="schedule-form-back-btn__icon dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
            <span
                class="schedule-form-back-btn__text"><?php echo esc_html__('חזור', 'clinic-queue-management'); ?></span>
        </button>
    </div>

    <!-- שלב 1: בחירת פעולה (חיבור יומן / הוספת יומן) -->
    <div class="step step-start is-active" data-step="start">
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">איזה פעולה תרצו לעשות</div>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-radio-field action-cards">
            <label class="jet-form-builder__field-wrap action-card" data-value="clinix">
                <input class="jet-form-builder__field radio-field" type="radio" name="jet_action_choice" value="clinix">
                <div class="card-title">הוספת יומן</div>
                <div aria-hidden="true" class="card-icon">
                    <?php echo $svg_clinix_logo; ?>
                </div>
                <div class="card-desc">Lorem ipsum dolor sit amet consectetur.</div>
            </label>
            <label class="jet-form-builder__field-wrap action-card" data-value="google">
                <input class="jet-form-builder__field radio-field" type="radio" name="jet_action_choice" value="google">
                <div class="card-title">חיבור יומן</div>
                <div aria-hidden="true" class="card-icon">
                    <?php echo $svg_google_calendar; ?>
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

    <!-- שלב 2 (קליניקס): הזנת טוקן API (אחרי בחירת מרפאה/רופא/שם יומן) -->
    <div class="step clinix-step" data-step="clinix" aria-hidden="true">
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">חיבור לחשבון במערכת קלינס</div>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-text-field is-filled">
            <div class="jet-form-builder__field-wrap">
                <input type="text" class="jet-form-builder__field text-field clinix-api-input" name="add_api"
                    id="clinix-add-api-input"
                    placeholder="<?php echo esc_attr__('API DoctorClinix Token', 'clinic-queue-management'); ?>">
            </div>
        </div>
        <div class="jet-form-builder__row field-type-submit-field continue-wrap">
            <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
                <button type="button"
                    class="jet-form-builder__action-button jet-form-builder__submit continue-btn continue-btn-clinix"
                    disabled>המשך</button>
            </div>
        </div>
    </div>

    <!-- שלב 2: מרפאה, רופא, שם יומן (משותף: גוגל ממשיך להגדרת ימים; קליניקס ממשיך להזנת טוקן) -->
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
                <select class="jet-form-builder__field select-field clinic-select" name="clinic_id"
                    aria-required="true">
                    <option value="">טוען מרפאות...</option>
                </select>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-select-field is-filled doctor-select-field">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text helper-text">בחר רופא מתוך רשימת אנשי צוות בפורטל</div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <select class="jet-form-builder__field select-field doctor-select cq-searchable" name="doctor_id"
                    disabled>
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
                <input type="text" class="jet-form-builder__field text-field manual-schedule_name"
                    name="manual_calendar_name" id="manual-schedule_name-input">
                <label for="manual-schedule_name-input" class="floating-label">
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

    <!-- בחירת יומן (גוגל: אחרי סנכרון; קליניקס: אחרי טוקן) -->
    <div class="step calendar-selection-step" data-step="calendar-selection" aria-hidden="true" style="display:none;">
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">
                    בחירת יומן מתוך רשימה
                </div>
            </div>
        </div>

        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text helper-text" style="color:#666;font-size:14px;">
                    Lorem ipsum malesuada dignissim morbi
                </div>
            </div>
        </div>

        <div class="calendar-list-container">
            <div class="calendar-loading" style="text-align:center;padding:2rem;">
                <div class="spinner"></div>
                <p>טוען יומנים...</p>
            </div>
        </div>

        <div class="calendar-error" style="display:none;">
            <p class="error-message"></p>
        </div>

        <div class="jet-form-builder__row field-type-submit-field continue-wrap">
            <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
                <button type="button"
                    class="jet-form-builder__action-button jet-form-builder__submit continue-btn save-calendar-btn"
                    disabled><?php echo esc_html__('המשך', 'clinic-queue-management'); ?></button>
            </div>
        </div>
    </div>

    <!-- שלב 3 (גוגל): הגדרת ימים, שעות וטיפולים | שלב 3 (קליניקס): ימים, שעות וטיפולים – צפייה בלבד (מהפרוקסי) -->
    <div class="step schedule-settings-step" data-step="schedule-settings" aria-hidden="true"
        data-schedule-title-google="הגדרת ימים ושעות עבודה" data-schedule-title-clinix="ימים ושעות עבודה">
        <div class="schedule-settings-scroll-content">
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text schedule-settings-step-title"
                    style="font-size:26px;font-weight:800;color:#0c1c4a;">הגדרת
                    ימים ושעות עבודה</div>
            </div>
        </div>
        <div class="days-schedule-container">
            <div class="schedule-form-no-work-days-message" style="display:none;" role="alert">
                <p>לא מוגדרים ימים ושעות עבודה, נא לחזור אחורה לבחירת יומן אחר.</p>
            </div>
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
                            <span class="checked-disabled-icon"><?php echo $svg_checkbox_checked_disabled; ?></span>
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
                <div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">הגדרת
                    טיפולים</div>
            </div>
        </div>
        <div class="treatments-repeater">
            <div class="treatment-row treatment-row-default" data-row-index="0" data-is-default="true">
                <div
                    class="jet-form-builder__row field-type-select-field treatment-field clinix-only-field clinix-treatment-wrap">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">טיפול - Clinix</div>
                    </div>
                    <div class="jet-form-builder__field-wrap">
                        <select class="jet-form-builder__field select-field clinix-treatment-select cq-searchable"
                            name="clinix_treatment_id[]">
                            <option value="">טוען...</option>
                        </select>
                    </div>
                </div>
                <div class="jet-form-builder__row field-type-select-field treatment-field portal-treatment-wrap">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">טיפול - פורטל</div>
                    </div>
                    <div class="jet-form-builder__field-wrap">
                        <select class="jet-form-builder__field select-field portal-treatment-select cq-searchable"
                            name="treatment_type[]">
                            <option value="">טוען...</option>
                        </select>
                    </div>
                </div>
                <div class="jet-form-builder__row treatment-field treatment-cost-wrap">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">מחיר</div>
                    </div>
                    <div class="jet-form-builder__field-wrap treatment-number-wrap">
                        <input type="number" name="treatment_cost[]"
                            class="jet-form-builder__field text-field treatment-cost-input" placeholder="0" min="0"
                            step="5">
                        <span class="treatment-field-suffix"><?php echo esc_html('₪'); ?></span>
                    </div>
                </div>
                <div class="jet-form-builder__row treatment-field treatment-duration-wrap">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">משך</div>
                    </div>
                    <div class="jet-form-builder__field-wrap treatment-number-wrap">
                        <input type="number" name="treatment_duration[]"
                            class="jet-form-builder__field text-field treatment-duration-input" placeholder="0" min="5"
                            step="5">
                        <span class="treatment-field-suffix">דקות</span>
                    </div>
                </div>
            </div>
        </div>
        <a type="button" class="add-treatment-btn">
            <span>+</span> הוספת טיפול
        </a>
        </div><!-- /.schedule-settings-scroll-content -->
        <div class="jet-form-builder__row field-type-submit-field continue-wrap">
            <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
                <button type="button"
                    class="jet-form-builder__action-button jet-form-builder__submit continue-btn save-schedule-btn">
                    המשך
                </button>
            </div>
        </div>
    </div>

    <!-- שלב חיבור גוגל (גוגל: אחרי הגדרת ימים ושעות – לפני יצירת פוסט יומן) -->
    <div class="step google-connect-step" data-step="google-connect" aria-hidden="true" style="display:none;">
        <!-- Title and Subtitle -->
        <div class="google-connect-header">
            <h2 class="google-connect-title">חיבור יומן לגוגל</h2>
            <p class="google-connect-subtitle">בחר כיצד לחבר את היומן</p>
        </div>

        <!-- Schedule Info -->
        <div class="google-connect-schedule-summary">
            <h3 class="schedule-summary-title">שעות פעילות</h3>
            <div class="schedule-days-list">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>

        <!-- Google Sync Status (יופיע אחרי חיבור מוצלח) -->
        <div class="google-sync-status" style="display:none;">
            <svg class="sync-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path fill="#34A853" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
            </svg>
            <p class="sync-message">
                <strong>חיבור לגוגל הושלם בהצלחה</strong><br>
                <span class="google-user-email"></span>
            </p>
        </div>

        <!-- Google Connection Loading -->
        <div class="google-connection-loading" style="display:none;">
            <div class="spinner"></div>
            <p>מתחבר לחשבון Google...</p>
        </div>

        <!-- Google Connection Error -->
        <div class="google-connection-error" style="display:none;">
            <svg class="error-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path fill="#EA4335"
                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
            </svg>
            <p class="error-message">שגיאה בחיבור לגוגל</p>
            <p class="error-details"></p>
        </div>

        <!-- Action Buttons -->
        <div class="google-connect-actions">
            <button type="button" class="jet-form-builder__action-button jet-form-builder__submit sync-google-btn">
                סנכרון יומן לגוגל - ביצוע ישיר
            </button>
            <button type="button"
                class="jet-form-builder__action-button jet-form-builder__submit--secondary transfer-request-btn">
                סנכרון יומן לגוגל - שליחת בקשה לרופא
            </button>
        </div>
    </div>

    <!-- מסך סיום (גוגל: אחרי שמירה בפרוקסי; קליניקס: אחרי יצירת פוסט) -->
    <div class="step final-success-step" data-step="final-success" aria-hidden="true" style="display:none;">

        <!-- Confetti banner – רוחב מלא, מוצמדת לראש עם margin-top: -16px -->
        <div class="final-success-confetti" aria-hidden="true"></div>

        <!-- גוף מרכזי מוגבל ברוחב -->
        <div class="final-success-body">

            <!-- Success Icon -->
            <div class="final-success-icon-wrapper">
                <img class="final-success-icon"
                    src="<?php echo esc_url(CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/vii.png'); ?>"
                    alt=""
                    aria-hidden="true"
                    width="120"
                    height="120">
            </div>

            <!-- Title – תמיד אותה כותרת -->
            <div class="final-success-header">
                <h2 class="final-success-title">היומן נוצר בהצלחה!</h2>

                <!-- Subtitle – גלוי רק ב-transfer flow (is-transfer-flow על האב) -->
                <p class="final-success-transfer-subtitle">
                    חיבור ה<strong>יומן לגוגל</strong> עדיין ממתין לביצוע על ידי הרופא / המטפל.<br>
                    העתק את הקישור ושלח אותו לרופא להשלמת החיבור.
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="final-success-actions">
                <!-- Copy link button – גלוי רק ב-transfer flow -->
                <button type="button" class="copy-connect-link-btn"
                    data-connect-url=""
                    style="display:none;"
                    aria-label="<?php echo esc_attr__('העתק קישור לחיבור יומן גוגל', 'clinic-queue-management'); ?>">
                    <!-- אייקון קישור -->
                    <svg class="copy-connect-link-btn__icon" aria-hidden="true"
                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                        <path fill="currentColor"
                            d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z" />
                    </svg>
                    <span class="copy-connect-link-btn__label"><?php echo esc_html__('העתק קישור לחיבור יומן גוגל', 'clinic-queue-management'); ?></span>
                    <!-- אייקון + טקסט לאחר העתקה -->
                    <span class="copy-connect-link-btn__copied" aria-live="polite">
                        <svg aria-hidden="true" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                            <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                        <?php echo esc_html__('הקישור הועתק!', 'clinic-queue-management'); ?>
                    </span>
                </button>

                <button type="button" class="jet-form-builder__action-button jet-form-builder__submit finish-btn">
                    <?php echo esc_html__('סיום', 'clinic-queue-management'); ?>
                </button>
            </div>

        </div><!-- /.final-success-body -->
    </div>
</div>