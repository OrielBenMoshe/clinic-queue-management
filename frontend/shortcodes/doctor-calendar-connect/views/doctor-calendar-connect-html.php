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
$clinic_name   = isset($data['clinic_name']) ? $data['clinic_name'] : '';
$calendar_name = isset($data['calendar_name']) ? $data['calendar_name'] : '';
$is_valid_sig  = isset($data['is_valid_sig']) ? (bool) $data['is_valid_sig'] : false;
$has_params    = isset($data['has_params']) ? (bool) $data['has_params'] : false;
?>

<div class="clinic-doctor-connect" dir="rtl">

    <?php if ($has_params && !$is_valid_sig) : ?>
    <div class="clinic-doctor-connect__card clinic-doctor-connect__card--error is-active" data-step="invalid">
        <div class="clinic-doctor-connect__icon-wrap">
            <span class="clinic-doctor-connect__icon clinic-doctor-connect__icon--error" aria-hidden="true">&#9888;</span>
        </div>
        <h2 class="clinic-doctor-connect__title">קישור לא תקין</h2>
        <p class="clinic-doctor-connect__subtitle">
            הקישור שבו השתמשת אינו תקין או שפרמטרים חסרים בו.<br>
            יש לפנות למרפאה לקבלת קישור חדש.
        </p>
    </div>
    <?php else : ?>

    <div class="clinic-doctor-connect__card clinic-doctor-connect__card--approval is-active" data-step="approval">
        <div class="clinic-doctor-connect__icon-wrap">
            <div class="clinic-doctor-connect__icon"><?php echo $calendar_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        </div>

        <div class="clinic-doctor-connect__header">
            <h2 class="clinic-doctor-connect__title">אישור הגדרות יומן חדש</h2>
            <p class="clinic-doctor-connect__subtitle">
                להלן סיכום ההגדרות שהקליניקה
                <?php if ($clinic_name) : ?>
                <strong class="clinic-doctor-connect__clinic-inline" data-role="clinic-name-inline"><?php echo esc_html($clinic_name); ?></strong>
                <?php else : ?>
                <strong class="clinic-doctor-connect__clinic-inline" data-role="clinic-name-inline"></strong>
                <?php endif; ?>
                הגדירה עבורך.<br>
                אנא בדוק/בדקי שהמידע מדויק לפני חיבור סופי של היומן.
            </p>
        </div>

        <div class="clinic-doctor-connect__summary">

            <?php if ($calendar_name) : ?>
            <div class="clinic-doctor-connect__info-row">
                <span class="clinic-doctor-connect__info-label">שם יומן</span>
                <span class="clinic-doctor-connect__info-value" data-role="calendar-name"><?php echo esc_html($calendar_name); ?></span>
            </div>
            <?php else : ?>
            <div class="clinic-doctor-connect__info-row" data-role="calendar-name-row" style="display:none;">
                <span class="clinic-doctor-connect__info-label">שם יומן</span>
                <span class="clinic-doctor-connect__info-value" data-role="calendar-name"></span>
            </div>
            <?php endif; ?>

            <div class="clinic-doctor-connect__summary-days-section" data-role="days-section">
                <h3 class="clinic-doctor-connect__summary-title">שעות פעילות</h3>
                <div class="clinic-doctor-connect__summary-days" data-role="working-days" aria-live="polite"></div>
            </div>

        </div>

        <div class="clinic-doctor-connect__actions">
            <button type="button" class="clinic-doctor-connect__btn clinic-doctor-connect__btn--primary" data-action="connect-google">
                קישור היומן לגוגל
            </button>
            <button type="button" class="clinic-doctor-connect__btn clinic-doctor-connect__btn--ghost" data-action="reject-request">
                דחיית הבקשה
            </button>
        </div>

        <div class="clinic-doctor-connect__inactive-note" data-role="inactive-note" style="display:none;">
            לא התקבלו פרטי יומן לחיבור. יש לפתוח את העמוד דרך קישור תקין מהמערכת.
        </div>

        <div class="clinic-doctor-connect__error" data-role="error-message" style="display:none;" role="alert"></div>
    </div>

    <div class="clinic-doctor-connect__card clinic-doctor-connect__card--calendar" data-step="calendar-selection" style="display:none;">
        <h2 class="clinic-doctor-connect__title">בחירת יומן מתוך Google</h2>
        <p class="clinic-doctor-connect__subtitle">בחר יומן אחד להמשך החיבור.</p>
        <p class="clinic-doctor-connect__calendars-hint">
            במידה וכל היומנים כבר נמצאים בשימוש, יש ליצור יומן חדש בחשבון הגוגל וללחוץ
            <button type="button" class="clinic-doctor-connect__link-btn" data-action="refresh-calendars">כאן</button>
            לרענון.
        </p>
        <div class="calendar-list-container"></div>
        <div class="clinic-doctor-connect__actions">
            <button type="button" class="clinic-doctor-connect__btn clinic-doctor-connect__btn--primary save-calendar-btn" disabled>
                המשך
            </button>
        </div>
    </div>

    <div class="clinic-doctor-connect__card clinic-doctor-connect__card--success" data-step="final-success" style="display:none;">

        <!-- Confetti banner -->
        <div class="clinic-doctor-connect__success-confetti" aria-hidden="true"></div>

        <!-- גוף מרכזי -->
        <div class="clinic-doctor-connect__success-body">
            <div class="clinic-doctor-connect__success-icon-wrapper">
                <img class="clinic-doctor-connect__success-icon"
                    src="<?php echo esc_url(CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/vii.png'); ?>"
                    alt=""
                    aria-hidden="true"
                    width="120"
                    height="120">
            </div>
            <h2 class="clinic-doctor-connect__title">היומן חובר בהצלחה!</h2>
            <p class="clinic-doctor-connect__subtitle">החיבור הסתיים וניתן להתחיל לעבוד.</p>
        </div>

    </div>

    <div class="clinic-doctor-connect__card clinic-doctor-connect__card--rejected" data-step="rejected" style="display:none;">
        <div class="clinic-doctor-connect__icon-wrap">
            <span class="clinic-doctor-connect__rejected-icon" aria-hidden="true">✕</span>
        </div>
        <h2 class="clinic-doctor-connect__title">הבקשה נדחתה</h2>
        <p class="clinic-doctor-connect__subtitle">
            בקשת חיבור היומן נדחתה.<br>
            הודעה נשלחה לבעלי המרפאה.
        </p>
    </div>

    <!-- פופאפ אזהרה לדחיית הבקשה -->
    <div class="clinic-doctor-connect__modal" data-role="reject-modal" role="dialog" aria-modal="true" aria-labelledby="reject-modal-title" style="display:none;">
        <div class="clinic-doctor-connect__modal-overlay" data-action="cancel-reject"></div>
        <div class="clinic-doctor-connect__modal-box">
            <h3 class="clinic-doctor-connect__modal-title" id="reject-modal-title">דחיית הבקשה</h3>
            <p class="clinic-doctor-connect__modal-body">
                האם אתה בטוח שברצונך לדחות את בקשת חיבור היומן?<br>
                הודעה תישלח למרפאה על הדחייה.
            </p>
            <div class="clinic-doctor-connect__modal-actions">
                <button type="button" class="clinic-doctor-connect__btn clinic-doctor-connect__btn--danger" data-action="confirm-reject">
                    כן, דחה את הבקשה
                </button>
                <button type="button" class="clinic-doctor-connect__btn clinic-doctor-connect__btn--ghost" data-action="cancel-reject">
                    ביטול
                </button>
            </div>
            <div class="clinic-doctor-connect__modal-error" data-role="modal-error" style="display:none;" role="alert"></div>
        </div>
    </div>

    <?php endif; ?>

    <!-- לואדר מעבר בין שלבים -->
    <div class="clinic-doctor-connect__overlay" data-role="card-loader" aria-hidden="true">
        <div class="clinic-doctor-connect__spinner"></div>
    </div>

</div>
