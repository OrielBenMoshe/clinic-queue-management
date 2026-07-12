<?php
/**
 * Step: action selection (start).
 *
 * @package Clinic_Queue_Management
 * @var array<string, mixed> $config
 */

if (!defined('ABSPATH')) {
    exit;
}

$svg_clinix_logo      = isset($config['svg_clinix_logo']) ? $config['svg_clinix_logo'] : '';
$svg_google_calendar  = isset($config['svg_google_calendar']) ? $config['svg_google_calendar'] : '';
?>
<div class="step step-start is-active" data-step="start">
    <div class="jet-form-builder__row field-type-heading is-filled">
        <div class="jet-form-builder__label">
            <div class="jet-form-builder__label-text"><?php echo esc_html__('איזה פעולה תרצו לעשות', 'clinic-queue-management'); ?></div>
        </div>
    </div>
    <div class="jet-form-builder__row field-type-radio-field action-cards">
        <label class="jet-form-builder__field-wrap action-card" data-value="clinix">
            <input class="jet-form-builder__field radio-field" type="radio" name="jet_action_choice" value="clinix">
            <div class="card-title"><?php echo esc_html__('הוספת יומן', 'clinic-queue-management'); ?></div>
            <div aria-hidden="true" class="card-icon"><?php echo $svg_clinix_logo; ?></div>
            <div class="card-desc">לחיבור מתקדם של היומן מתוך מערכת Doctor Clinix</div>
        </label>
        <label class="jet-form-builder__field-wrap action-card" data-value="google">
            <input class="jet-form-builder__field radio-field" type="radio" name="jet_action_choice" value="google">
            <div class="card-title"><?php echo esc_html__('חיבור יומן', 'clinic-queue-management'); ?></div>
            <div aria-hidden="true" class="card-icon"><?php echo $svg_google_calendar; ?></div>
            <div class="card-desc">לקבלת תורים מהפורטל ישירות ליומן Google</div>
        </label>
    </div>
    <div class="jet-form-builder__row field-type-submit-field continue-wrap">
        <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
            <button type="button" class="jet-form-builder__action-button jet-form-builder__submit continue-btn" disabled>
                <?php echo esc_html__('המשך', 'clinic-queue-management'); ?>
            </button>
        </div>
    </div>
</div>
