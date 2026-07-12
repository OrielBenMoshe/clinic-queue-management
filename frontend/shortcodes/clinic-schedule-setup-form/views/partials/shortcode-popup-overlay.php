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
$close_icon   = $config['close_icon'] ?? '';
?>

<div id="<?php echo esc_attr($popup_id); ?>"
     class="clinic-schedule-form__popup-overlay"
     role="dialog"
     aria-modal="true"
     aria-hidden="true"
     aria-label="<?php echo esc_attr($button_label); ?>">

    <div class="clinic-schedule-form__popup">
        <button type="button"
                class="clinic-schedule-form__popup-close"
                aria-label="<?php esc_attr_e('סגור', 'clinic-queue-management'); ?>">
            <?php if ($close_icon) : ?>
                <span aria-hidden="true"><?php echo $close_icon; ?></span>
            <?php endif; ?>
        </button>

        <div class="clinic-schedule-form__popup-body">
            <?php Clinic_Schedule_Form_Manager::render_partial('schedule-form-inner', $config); ?>
        </div>
    </div>
</div>
