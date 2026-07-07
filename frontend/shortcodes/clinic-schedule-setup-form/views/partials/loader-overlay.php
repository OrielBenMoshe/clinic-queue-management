<?php
/**
 * Loader overlay partial.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="schedule-form-loader-overlay" aria-hidden="true" aria-busy="false">
    <div class="spinner"></div>
    <p class="schedule-form-loader-overlay__text"><?php echo esc_html__('טוען...', 'clinic-queue-management'); ?></p>
</div>
