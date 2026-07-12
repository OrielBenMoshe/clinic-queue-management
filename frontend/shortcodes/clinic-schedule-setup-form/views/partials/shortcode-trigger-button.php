<?php
/**
 * Trigger button for self-contained schedule form shortcode.
 *
 * @package Clinic_Queue_Management
 * @var array<string, mixed> $config
 */

if (!defined('ABSPATH')) {
    exit;
}

$popup_id = $config['popup_id'] ?? '';
$button_label = $config['button_label'] ?? __('הוספת יומן', 'clinic-queue-management');
$plus_icon = $config['plus_icon'] ?? '';
?>

<button type="button" class="clinic-schedule-form__trigger-btn" aria-haspopup="dialog"
    aria-controls="<?php echo esc_attr($popup_id); ?>">
    <?php if ($plus_icon): ?>
        <span class="clinic-schedule-form__trigger-icon" aria-hidden="true"><?php echo $plus_icon; ?></span>
    <?php endif; ?>
    <span class="clinic-schedule-form__trigger-label"><?php echo esc_html($button_label); ?></span>
</button>