<?php
/**
 * Step: schedule settings (wrapper for shared panel).
 *
 * @package Clinic_Queue_Management
 * @var array<string, mixed> $config
 */

if (!defined('ABSPATH')) {
    exit;
}

$panel_config = isset($config['schedule_settings']) && is_array($config['schedule_settings'])
    ? $config['schedule_settings']
    : Clinic_Schedule_Form_Manager::get_schedule_settings_config('wizard');
?>
<div class="step schedule-settings-step" data-step="schedule-settings" aria-hidden="true"
    data-schedule-title-google="<?php echo esc_attr__('הגדרת ימים ושעות עבודה', 'clinic-queue-management'); ?>"
    data-schedule-title-clinix="<?php echo esc_attr__('ימים ושעות עבודה', 'clinic-queue-management'); ?>">
    <?php Clinic_Schedule_Form_Manager::render_partial('schedule-settings-panel', $panel_config); ?>
</div>
