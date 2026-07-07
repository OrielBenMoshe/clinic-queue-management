<?php
/**
 * Back button partial.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="schedule-form-back-wrap">
    <button type="button" class="schedule-form-back-btn"
        aria-label="<?php echo esc_attr__('חזור', 'clinic-queue-management'); ?>">
        <span class="schedule-form-back-btn__icon dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
        <span class="schedule-form-back-btn__text"><?php echo esc_html__('חזור', 'clinic-queue-management'); ?></span>
    </button>
</div>
