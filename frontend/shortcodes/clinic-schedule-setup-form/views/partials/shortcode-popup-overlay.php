<?php
/**
 * Popup overlay shell for self-contained schedule form shortcode.
 *
 * @package Clinic_Queue_Management
 * @var array<string, mixed> $config
 */

if (!defined('ABSPATH')) {
    exit;
}

$popup_id     = $config['popup_id'] ?? '';
$button_label = $config['button_label'] ?? __('הוספת יומן', 'clinic-queue-management');
?>

<div id="<?php echo esc_attr($popup_id); ?>"
     class="clinic-schedule-form__popup-overlay"
     role="dialog"
     aria-modal="true"
     aria-hidden="true"
     hidden
     aria-label="<?php echo esc_attr($button_label); ?>">

    <div class="clinic-schedule-form__popup">
        <?php
        Clinic_Queue_Helpers::render_modal_close_button(array(
            'class' => 'clinic-queue__modal-close--overlay',
        ));
        ?>

        <div class="clinic-schedule-form__popup-body">
            <?php Clinic_Schedule_Form_Manager::render_partial('schedule-form-inner', $config); ?>
        </div>
    </div>
</div>
