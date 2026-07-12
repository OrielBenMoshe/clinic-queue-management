<?php
/**
 * Step: calendar selection.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="step calendar-selection-step" data-step="calendar-selection" aria-hidden="true" style="display:none;">
    <div class="jet-form-builder__row field-type-heading is-filled">
        <div class="jet-form-builder__label">
            <div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">
                <?php echo esc_html__('בחירת יומן מתוך רשימה', 'clinic-queue-management'); ?>
            </div>
        </div>
    </div>
    <div class="jet-form-builder__row field-type-heading is-filled">
        <div class="jet-form-builder__label">
            <div class="jet-form-builder__label-text helper-text" style="color:#666;font-size:14px !important;">
                <?php echo esc_html__('המערכת מציגה את היומנים הזמינים לחיבור, בחרו את היומן הרלוונטי והמשיכו לשלב הבא.', 'clinic-queue-management'); ?>
            </div>
        </div>
    </div>
    <div class="calendar-list-container">
        <div class="calendar-loading" style="text-align:center;padding:2rem;">
            <div class="spinner"></div>
            <p><?php echo esc_html__('טוען יומנים...', 'clinic-queue-management'); ?></p>
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
