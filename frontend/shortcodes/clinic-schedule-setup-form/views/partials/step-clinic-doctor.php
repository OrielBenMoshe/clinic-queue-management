<?php
/**
 * Step: clinic, doctor, manual schedule name.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="step google-step" data-step="google" aria-hidden="true">
    <div class="jet-form-builder__row field-type-heading is-filled">
        <div class="jet-form-builder__label">
            <div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">
                <?php echo esc_html__('חיבור יומן רופא חדש', 'clinic-queue-management'); ?>
            </div>
        </div>
    </div>
    <div class="jet-form-builder__row field-type-select-field is-filled clinic-select-field">
        <div class="jet-form-builder__label">
            <div class="jet-form-builder__label-text helper-text"><?php echo esc_html__('בחר מרפאה', 'clinic-queue-management'); ?></div>
        </div>
        <div class="jet-form-builder__field-wrap">
            <select class="jet-form-builder__field select-field clinic-select" name="clinic_id" aria-required="true">
                <option value=""><?php echo esc_html__('טוען מרפאות...', 'clinic-queue-management'); ?></option>
            </select>
        </div>
    </div>
    <div class="jet-form-builder__row field-type-select-field is-filled doctor-select-field field-disabled">
        <div class="jet-form-builder__label">
            <div class="jet-form-builder__label-text helper-text"><?php echo esc_html__('בחר רופא מתוך רשימת אנשי צוות בפורטל', 'clinic-queue-management'); ?></div>
        </div>
        <div class="jet-form-builder__field-wrap">
            <select class="jet-form-builder__field select-field doctor-select cq-searchable" name="doctor_id" disabled>
                <option value=""><?php echo esc_html__('יש לבחור מרפאה לפני בחירת הרופא', 'clinic-queue-management'); ?></option>
            </select>
        </div>
    </div>
    <div class="separator" aria-hidden="true"><span><?php echo esc_html__('או', 'clinic-queue-management'); ?></span></div>
    <div class="jet-form-builder__row field-type-text-field is-filled">
        <div class="jet-form-builder__label">
            <div class="jet-form-builder__label-text helper-text"><?php echo esc_html__('חיבור יומן שלא נמצא בפורטל', 'clinic-queue-management'); ?></div>
        </div>
        <div class="jet-form-builder__field-wrap">
            <input type="text" class="jet-form-builder__field text-field manual-schedule_name"
                name="manual_calendar_name" id="manual-schedule_name-input">
            <label for="manual-schedule_name-input" class="floating-label">
                <p><?php echo esc_html__('שם יומן', 'clinic-queue-management'); ?></p>
            </label>
        </div>
    </div>
    <div class="jet-form-builder__row field-type-submit-field continue-wrap">
        <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
            <button type="button"
                class="jet-form-builder__action-button jet-form-builder__submit continue-btn continue-btn-google"
                disabled><?php echo esc_html__('המשך', 'clinic-queue-management'); ?></button>
        </div>
    </div>
</div>
