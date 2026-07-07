<?php
/**
 * Step: Clinix API token.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="step clinix-step" data-step="clinix" aria-hidden="true">
    <div class="jet-form-builder__row field-type-heading is-filled">
        <div class="jet-form-builder__label">
            <div class="jet-form-builder__label-text"><?php echo esc_html__('חיבור לחשבון במערכת קליניקס', 'clinic-queue-management'); ?></div>
        </div>
    </div>
    <div class="jet-form-builder__row field-type-text-field is-filled">
        <p class="clinix-token-error" role="alert" aria-live="polite" hidden></p>
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
                disabled><?php echo esc_html__('המשך', 'clinic-queue-management'); ?></button>
        </div>
    </div>
</div>
