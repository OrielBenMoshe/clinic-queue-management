<?php
/**
 * Schedule settings – days and time ranges partial.
 *
 * @package Clinic_Queue_Management
 * @var array<string, mixed> $config
 */

if (!defined('ABSPATH')) {
    exit;
}

$icons               = isset($config['icons']) && is_array($config['icons']) ? $config['icons'] : array();
$days_of_week        = isset($config['days_of_week']) && is_array($config['days_of_week']) ? $config['days_of_week'] : array();
$field_prefix        = isset($config['field_prefix']) ? (string) $config['field_prefix'] : '';
$day_row_extra_class = isset($config['day_row_extra_class']) ? trim((string) $config['day_row_extra_class']) : '';
$no_days_class       = isset($config['no_days_message_class']) ? (string) $config['no_days_message_class'] : 'schedule-form-no-work-days-message';
$no_days_id          = isset($config['no_days_message_id']) ? (string) $config['no_days_message_id'] : '';
$no_days_style       = isset($config['no_days_message_style']) ? (string) $config['no_days_message_style'] : 'display:none;';

$svg_trash_icon               = isset($icons['trash_icon']) ? $icons['trash_icon'] : '';
$svg_checkbox_checked         = isset($icons['checkbox_checked']) ? $icons['checkbox_checked'] : '';
$svg_checkbox_unchecked       = isset($icons['checkbox_unchecked']) ? $icons['checkbox_unchecked'] : '';
$svg_checkbox_checked_disabled = isset($icons['checkbox_checked_disabled']) ? $icons['checkbox_checked_disabled'] : '';

$day_row_classes = trim('day-row ' . $day_row_extra_class);
?>
<div class="days-schedule-container">
    <div
        class="<?php echo esc_attr($no_days_class); ?>"
        <?php echo $no_days_id ? 'id="' . esc_attr($no_days_id) . '"' : ''; ?>
        <?php echo $no_days_style ? 'style="' . esc_attr($no_days_style) . '"' : ''; ?>
        <?php echo ('edit_modal' === ($config['context'] ?? '') && $no_days_id) ? 'hidden' : ''; ?>
        role="alert"
    >
        <p>
            <?php
            if ('edit_modal' === ($config['context'] ?? '')) {
                echo esc_html__('לא מוגדרים ימי עבודה ביומן זה.', 'clinic-queue-management');
            } else {
                echo esc_html__('לא מוגדרים ימים ושעות עבודה, נא לחזור אחורה לבחירת יומן אחר.', 'clinic-queue-management');
            }
            ?>
        </p>
    </div>
    <?php
    foreach ($days_of_week as $day_key => $day_label) {
        $default_end = ('friday' === $day_key) ? '16:00' : '18:00';
        ?>
        <div class="<?php echo esc_attr($day_row_classes); ?>" data-day-row="<?php echo esc_attr($day_key); ?>">
            <label class="day-checkbox custom-checkbox">
                <input type="checkbox"
                    name="<?php echo esc_attr($field_prefix . 'day_' . $day_key); ?>"
                    value="<?php echo esc_attr($day_key); ?>"
                    data-day="<?php echo esc_attr($day_key); ?>">
                <span class="checkbox-icon">
                    <span class="unchecked-icon"><?php echo $svg_checkbox_unchecked; ?></span>
                    <span class="checked-icon"><?php echo $svg_checkbox_checked; ?></span>
                    <span class="checked-disabled-icon"><?php echo $svg_checkbox_checked_disabled; ?></span>
                </span>
                <span class="checkbox-label"><?php echo esc_html($day_label); ?></span>
            </label>
            <?php echo Clinic_Schedule_Form_Manager::generate_day_time_range($day_key, $day_label, $default_end, $svg_trash_icon); ?>
        </div>
        <?php
    }
    ?>
</div>
