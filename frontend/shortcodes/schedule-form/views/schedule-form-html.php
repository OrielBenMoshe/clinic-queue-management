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

    <!-- Back button - visible from step 2 onward, positioned top-right -->
    <button type="button" class="schedule-form-back-btn" aria-label="<?php echo esc_attr__('חזור', 'clinic-queue-management'); ?>" aria-hidden="true">
        <span class="schedule-form-back-btn__icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6-6-6z" fill="currentColor"/>
            </svg>
        </span>
    </button>

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

    <!-- Step 2: Add Calendar (Clinix) - Single API field -->
    <div class="step clinix-step" data-step="clinix" aria-hidden="true">
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">איזה פעולה תרצו לעשות</div>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-heading is-filled">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text helper-text">Lorem ipsum dolor sit amet consectetur.</div>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-text-field is-filled">
            <div class="jet-form-builder__field-wrap">
                <input type="text" class="jet-form-builder__field text-field clinix-api-input"
                    name="add_api" id="clinix-add-api-input" placeholder="<?php echo esc_attr__('API DoctorClinix Token', 'clinic-queue-management'); ?>">
            </div>
        </div>
        <div class="jet-form-builder__row field-type-submit-field continue-wrap">
            <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
                <button type="button" class="jet-form-builder__action-button jet-form-builder__submit continue-btn continue-btn-clinix"
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
        <div class="jet-form-builder__row field-type-select-field is-filled doctor-select-field">
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

    <!-- Step 2.5: Calendar Selection (after Google connection) -->
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
                    class="jet-form-builder__action-button jet-form-builder__submit save-calendar-btn"
                    disabled>שמירה</button>
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
                <div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">הגדרת טיפולים</div>
            </div>
        </div>
        <div class="treatments-repeater">
            <!-- שורה ראשונה - טיפול דיפולטיבי (read-only) -->
            <div class="treatment-row treatment-row-default" data-row-index="0" data-is-default="true">
                <div class="jet-form-builder__row field-type-select-field is-filled treatment-field">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">שם טיפול</div>
                    </div>
                    <div class="jet-form-builder__field-wrap">
                        <select class="jet-form-builder__field select-field treatment-name-select"
                            name="treatment_name[]" data-row-index="0" disabled>
                            <option value="">טוען טיפולים...</option>
                        </select>
                    </div>
                </div>
            </div>
            <!-- שורה שנייה - טיפול נוסף שניתן לעריכה -->
            <div class="treatment-row" data-row-index="1">
                <div class="jet-form-builder__row field-type-select-field is-filled treatment-field">
                    <div class="jet-form-builder__label">
                        <div class="jet-form-builder__label-text">שם טיפול</div>
                    </div>
                    <div class="jet-form-builder__field-wrap">
                        <select class="jet-form-builder__field select-field treatment-name-select"
                            name="treatment_name[]" data-row-index="1" disabled>
                            <option value="">בחר שם טיפול</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="remove-treatment-btn"><?php echo $svg_trash_icon; ?></button>
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
        <!-- Icon -->
        <div class="success-icon-wrapper">
            <div class="success-icon">
                <?php echo $svg_calendar_icon; ?>
            </div>
        </div>

        <!-- Title and Subtitle -->
        <div class="success-header">
            <h2 class="success-title">היומן הוגדר בהצלחה!</h2>
            <p class="success-subtitle">נא לחבר את היומן מתוך יומן גוגל</p>
        </div>

        <!-- Schedule Info -->
        <div class="success-schedule-summary">
            <h3 class="schedule-summary-title">ימי עבודה</h3>
            <div class="schedule-days-list">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>

        <!-- Google Sync Status (יופיע אחרי חיבור מוצלח) -->
        <div class="google-sync-status" style="display:none;">
            <svg class="sync-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path fill="#34A853" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
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
                <path fill="#EA4335" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <p class="error-message">שגיאה בחיבור לגוגל</p>
            <p class="error-details"></p>
        </div>

        <!-- Action Buttons -->
        <div class="success-actions">
            <button type="button" class="jet-form-builder__action-button jet-form-builder__submit sync-google-btn">
                <svg class="google-icon-small" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px; margin-left: 8px;">
                    <path fill="#4285F4" d="M45.12 24.5c0-1.56-.14-3.06-.4-4.5H24v8.51h11.84c-.51 2.75-2.06 5.08-4.39 6.64v5.52h7.11c4.16-3.83 6.56-9.47 6.56-16.17z"/>
                    <path fill="#34A853" d="M24 46c5.94 0 10.92-1.97 14.56-5.33l-7.11-5.52c-1.97 1.32-4.49 2.1-7.45 2.1-5.73 0-10.58-3.87-12.31-9.07H4.34v5.7C7.96 41.07 15.4 46 24 46z"/>
                    <path fill="#FBBC05" d="M11.69 28.18C11.25 26.86 11 25.45 11 24s.25-2.86.69-4.18v-5.7H4.34C2.85 17.09 2 20.45 2 24c0 3.55.85 6.91 2.34 9.88l7.35-5.7z"/>
                    <path fill="#EA4335" d="M24 10.75c3.23 0 6.13 1.11 8.41 3.29l6.31-6.31C34.91 4.18 29.93 2 24 2 15.4 2 7.96 6.93 4.34 14.12l7.35 5.7c1.73-5.2 6.58-9.07 12.31-9.07z"/>
                </svg>
                סנכרון יומן מגוגל
            </button>
            <button type="button" class="jet-form-builder__action-button jet-form-builder__submit--secondary transfer-request-btn">
                העבר בקשת סנכרון לכרטיס רופא
            </button>
        </div>
    </div>

    <!-- Final Success Screen (after proxy scheduler creation) -->
    <div class="step final-success-step" data-step="final-success" aria-hidden="true" style="display:none;">
        <!-- Success Icon with confetti background -->
        <div class="final-success-icon-wrapper">
            <div class="final-success-icon">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#00BFA5" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
            </div>
        </div>
        
        <!-- Title and Subtitle -->
        <div class="final-success-header">
            <h2 class="final-success-title">היומן חובר בהצלחה!</h2>
            <p class="final-success-subtitle">יש להריץ בדיקה</p>
        </div>
        
        <!-- Action Button -->
        <div class="final-success-actions">
            <button type="button" class="jet-form-builder__action-button jet-form-builder__submit run-test-btn">
                הרץ בדיקה
            </button>
        </div>
    </div>
</div>