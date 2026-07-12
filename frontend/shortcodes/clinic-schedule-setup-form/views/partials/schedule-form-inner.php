<?php
/**
 * Schedule wizard form markup (shared inner content).
 *
 * @package Clinic_Queue_Management
 * @var array<string, mixed> $config
 */

if (!defined('ABSPATH')) {
    exit;
}

$view_config = array(
    'svg_google_calendar' => $config['svg_google_calendar'] ?? '',
    'svg_clinix_logo'       => $config['svg_clinix_logo'] ?? '',
    'schedule_settings'     => $config['schedule_settings']
        ?? Clinic_Schedule_Form_Manager::get_schedule_settings_config('wizard'),
);
?>

<div class="jet-form-builder jet-form-builder--default clinic-add-schedule-form clinic-queue-jetform-mui" data-schedule-wizard-popup-id="">

    <?php Clinic_Schedule_Form_Manager::render_partial('loader-overlay'); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('back-button'); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('step-start', $view_config); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('step-clinix-token'); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('step-clinic-doctor'); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('step-calendar-selection'); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('step-schedule-settings', $view_config); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('step-google-connect'); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('step-final-success'); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('alert-modal'); ?>

</div>
