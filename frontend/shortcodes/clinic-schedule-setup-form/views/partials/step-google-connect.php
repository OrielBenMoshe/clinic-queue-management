<?php
/**
 * Step: Google connect.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="step google-connect-step" data-step="google-connect" aria-hidden="true" style="display:none;">
    <div class="google-connect-header">
        <h2 class="google-connect-title"><?php echo esc_html__('חיבור יומן לגוגל', 'clinic-queue-management'); ?></h2>
        <p class="google-connect-subtitle"><?php echo esc_html__('בחר כיצד לחבר את היומן', 'clinic-queue-management'); ?></p>
    </div>
    <div class="google-connect-schedule-summary">
        <h3 class="schedule-summary-title"><?php echo esc_html__('שעות פעילות', 'clinic-queue-management'); ?></h3>
        <div class="schedule-days-list"></div>
    </div>
    <div class="google-sync-status" style="display:none;">
        <svg class="sync-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path fill="#34A853" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
        </svg>
        <p class="sync-message">
            <strong><?php echo esc_html__('חיבור לגוגל הושלם בהצלחה', 'clinic-queue-management'); ?></strong><br>
            <span class="google-user-email"></span>
        </p>
    </div>
    <div class="google-connection-loading" style="display:none;">
        <div class="spinner"></div>
        <p><?php echo esc_html__('מתחבר לחשבון Google...', 'clinic-queue-management'); ?></p>
    </div>
    <div class="google-connection-error" style="display:none;">
        <svg class="error-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path fill="#EA4335"
                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
        </svg>
        <p class="error-message"><?php echo esc_html__('שגיאה בחיבור לגוגל', 'clinic-queue-management'); ?></p>
        <p class="error-details"></p>
    </div>
    <div class="google-connect-actions">
        <button type="button" class="jet-form-builder__action-button jet-form-builder__submit sync-google-btn">
            <?php echo esc_html__('סנכרון יומן לגוגל - ביצוע ישיר', 'clinic-queue-management'); ?>
        </button>
        <button type="button"
            class="jet-form-builder__action-button jet-form-builder__submit--secondary transfer-request-btn">
            <?php echo esc_html__('סנכרון יומן לגוגל - שליחת בקשה לרופא', 'clinic-queue-management'); ?>
        </button>
    </div>
</div>
