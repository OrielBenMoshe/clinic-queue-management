<?php
/**
 * Schedule Form HTML View
 * Template for [clinic_add_schedule_form] shortcode
 *
 * @package Clinic_Queue_Management
 * @var array $data Data prepared by the shortcode class
 */

if (!defined('ABSPATH')) {
    exit;
}

$popup_id = $data['popup_id'] ?? wp_unique_id('csfp-');

$view_config = array(
    'popup_id'            => $popup_id,
    'button_label'        => $data['button_label'] ?? __('הוספת יומן', 'clinic-queue-management'),
    'plus_icon'           => $data['plus_icon'] ?? '',
    'close_icon'          => $data['close_icon'] ?? '',
    'svg_google_calendar' => $data['svg_google_calendar'] ?? '',
    'svg_clinix_logo'     => $data['svg_clinix_logo'] ?? '',
    'schedule_settings'   => Clinic_Schedule_Form_Manager::get_schedule_settings_config('wizard'),
);
?>

<div class="clinic-schedule-form-shortcode">
    <?php Clinic_Schedule_Form_Manager::render_partial('shortcode-trigger-button', $view_config); ?>
    <?php Clinic_Schedule_Form_Manager::render_partial('shortcode-popup-overlay', $view_config); ?>
</div>
