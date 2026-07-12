<?php
/**
 * Shared modal close button partial (X icon).
 *
 * @package Clinic_Queue_Management
 * @var string $button_class  Full button class attribute value.
 * @var string $aria_label    Accessible label.
 * @var string $close_icon    Inline SVG markup.
 * @var string $extra_attrs   Pre-rendered additional HTML attributes.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<button type="button"
        class="<?php echo esc_attr($button_class); ?>"
        aria-label="<?php echo esc_attr($aria_label); ?>"
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted attribute string from helper
        echo $extra_attrs;
        ?>>
    <?php if ($close_icon) : ?>
        <span class="clinic-queue__modal-close-icon" aria-hidden="true"><?php echo $close_icon; ?></span>
    <?php endif; ?>
</button>
