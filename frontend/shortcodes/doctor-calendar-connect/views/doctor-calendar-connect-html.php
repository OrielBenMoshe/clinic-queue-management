<?php
/**
 * Doctor Calendar Connect View
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

$calendar_icon = isset($data['calendar_icon']) ? $data['calendar_icon'] : '';
?>

<div class="clinic-doctor-connect" dir="rtl">
    <div class="clinic-doctor-connect__card clinic-doctor-connect__card--approval is-active" data-step="approval">
        <div class="clinic-doctor-connect__icon-wrap">
            <div class="clinic-doctor-connect__icon"><?php echo $calendar_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        </div>

        <div class="clinic-doctor-connect__header">
            <h2 class="clinic-doctor-connect__title">אישור הגדרות יומן חדש</h2>
            <p class="clinic-doctor-connect__subtitle">
                להלן סיכום ההגדרות שהקליניקה הגדירה עבורך.
                <br>
                אנא בדוק/בדקי שהמידע מדויק לפני חיבור סופי של היומן.
            </p>
        </div>

        <div class="clinic-doctor-connect__summary">
            <h3 class="clinic-doctor-connect__summary-title">ימי עבודה</h3>
            <div class="clinic-doctor-connect__summary-days" data-role="working-days"></div>
        </div>

        <div class="clinic-doctor-connect__actions">
            <button type="button" class="clinic-doctor-connect__btn clinic-doctor-connect__btn--primary" data-action="connect-google">
                קישור היומן לגוגל
            </button>
            <button type="button" class="clinic-doctor-connect__btn clinic-doctor-connect__btn--secondary" data-action="reject-request">
                דחיית הבקשה
            </button>
        </div>
        <div class="clinic-doctor-connect__inactive-note" data-role="inactive-note" style="display:none;">
            לא התקבלו פרטי יומן לחיבור. יש לפתוח את העמוד דרך קישור תקין מהמערכת.
        </div>

        <div class="clinic-doctor-connect__error" data-role="error-message" style="display:none;"></div>
    </div>

    <div class="clinic-doctor-connect__card clinic-doctor-connect__card--calendar" data-step="calendar-selection" style="display:none;">
        <h2 class="clinic-doctor-connect__title">בחירת יומן מתוך Google</h2>
        <p class="clinic-doctor-connect__subtitle">בחר יומן אחד להמשך החיבור.</p>
        <div class="calendar-list-container"></div>
        <div class="clinic-doctor-connect__actions">
            <button type="button" class="clinic-doctor-connect__btn clinic-doctor-connect__btn--primary save-calendar-btn" disabled>
                המשך
            </button>
        </div>
    </div>

    <div class="clinic-doctor-connect__card clinic-doctor-connect__card--success" data-step="final-success" style="display:none;">
        <h2 class="clinic-doctor-connect__title">היומן חובר בהצלחה</h2>
        <p class="clinic-doctor-connect__subtitle">החיבור הסתיים וניתן להתחיל לעבוד.</p>
    </div>

    <div class="clinic-doctor-connect__card clinic-doctor-connect__card--rejected" data-step="rejected" style="display:none;">
        <h2 class="clinic-doctor-connect__title">הבקשה נדחתה</h2>
        <p class="clinic-doctor-connect__subtitle">בקשת החיבור נדחתה ונשלחה הודעה לבעלי המרפאה.</p>
    </div>
</div>
