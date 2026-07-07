<?php
/**
 * Alert modal partial.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="schedule-form__modal-overlay"
     id="schedule-form-alert-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="schedule-form-alert-modal-title"
     hidden>
    <div class="schedule-form__modal">
        <h2 class="schedule-form__modal-title" id="schedule-form-alert-modal-title"></h2>
        <p class="schedule-form__modal-body" id="schedule-form-alert-modal-body"></p>
        <div class="schedule-form__modal-actions">
            <button type="button" class="schedule-form__modal-primary" id="schedule-form-alert-modal-primary">
                <?php echo esc_html__('הבנתי', 'clinic-queue-management'); ?>
            </button>
            <button type="button" class="schedule-form__modal-secondary" id="schedule-form-alert-modal-secondary" hidden>
                <?php echo esc_html__('ביטול', 'clinic-queue-management'); ?>
            </button>
        </div>
    </div>
</div>
