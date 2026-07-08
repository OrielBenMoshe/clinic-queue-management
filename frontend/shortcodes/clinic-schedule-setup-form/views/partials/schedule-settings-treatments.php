<?php
/**
 * Schedule settings – treatments repeater partial.
 *
 * @package Clinic_Queue_Management
 * @var array<string, mixed> $config
 */

if (!defined('ABSPATH')) {
    exit;
}

$field_prefix       = isset($config['field_prefix']) ? (string) $config['field_prefix'] : '';
$searchable_class   = isset($config['searchable_class']) ? trim((string) $config['searchable_class']) : 'cq-searchable';
$treatments_id      = isset($config['treatments_id']) ? (string) $config['treatments_id'] : '';
$add_treatment_id   = isset($config['add_treatment_id']) ? (string) $config['add_treatment_id'] : '';
$icons              = isset($config['icons']) && is_array($config['icons']) ? $config['icons'] : array();
$trash_icon         = isset($icons['trash_icon']) ? $icons['trash_icon'] : '';
$clinix_disabled    = !empty($config['clinix_select_disabled']);
$clinix_placeholder = isset($config['clinix_select_placeholder']) ? (string) $config['clinix_select_placeholder'] : '';
$portal_placeholder = isset($config['portal_select_placeholder']) ? (string) $config['portal_select_placeholder'] : '';

$clinix_classes = trim('jet-form-builder__field select-field clinix-treatment-select ' . $searchable_class);
$portal_classes = trim('jet-form-builder__field select-field portal-treatment-select ' . $searchable_class);
?>
<?php if (!empty($config['show_treatments_heading'])) : ?>
<div class="jet-form-builder__row field-type-heading is-filled" style="margin-top:2rem;">
    <div class="jet-form-builder__label">
        <div class="jet-form-builder__label-text" style="font-size:26px;font-weight:800;color:#0c1c4a;">
            <?php echo esc_html__('הגדרת טיפולים', 'clinic-queue-management'); ?>
        </div>
    </div>
</div>
<?php endif; ?>
<div
    class="treatments-repeater"
    <?php echo $treatments_id ? 'id="' . esc_attr($treatments_id) . '"' : ''; ?>
>
    <?php if ($trash_icon) : ?>
    <span class="remove-treatment-btn-icon-source" hidden aria-hidden="true"><?php echo $trash_icon; ?></span>
    <?php endif; ?>
    <div class="treatment-row treatment-row-default" data-row-index="0" data-is-default="true">
        <div class="jet-form-builder__row field-type-select-field treatment-field clinix-only-field clinix-treatment-wrap">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text"><?php echo esc_html__('טיפול - Clinix', 'clinic-queue-management'); ?></div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <select class="<?php echo esc_attr($clinix_classes); ?>"
                    name="<?php echo esc_attr($field_prefix . 'clinix_treatment_id[]'); ?>"
                    <?php echo $clinix_disabled ? 'disabled' : ''; ?>>
                    <option value=""><?php echo esc_html($clinix_placeholder); ?></option>
                </select>
            </div>
        </div>
        <div class="jet-form-builder__row field-type-select-field treatment-field portal-treatment-wrap">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text"><?php echo esc_html__('טיפול - פורטל', 'clinic-queue-management'); ?></div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <select class="<?php echo esc_attr($portal_classes); ?>"
                    name="<?php echo esc_attr($field_prefix . 'treatment_type[]'); ?>">
                    <option value=""><?php echo esc_html($portal_placeholder); ?></option>
                </select>
            </div>
        </div>
        <div class="jet-form-builder__row treatment-field treatment-cost-wrap">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text"><?php echo esc_html__('מחיר', 'clinic-queue-management'); ?></div>
            </div>
            <div class="jet-form-builder__field-wrap treatment-number-wrap">
                <input type="number"
                    name="<?php echo esc_attr($field_prefix . 'treatment_cost[]'); ?>"
                    class="jet-form-builder__field text-field treatment-cost-input"
                    placeholder="0" min="0" step="5">
                <span class="treatment-field-suffix"><?php echo esc_html('₪'); ?></span>
            </div>
        </div>
        <div class="jet-form-builder__row treatment-field treatment-duration-wrap">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text"><?php echo esc_html__('משך', 'clinic-queue-management'); ?></div>
            </div>
            <div class="jet-form-builder__field-wrap treatment-number-wrap">
                <input type="number"
                    name="<?php echo esc_attr($field_prefix . 'treatment_duration[]'); ?>"
                    class="jet-form-builder__field text-field treatment-duration-input"
                    placeholder="0" min="5" step="5">
                <span class="treatment-field-suffix"><?php echo esc_html__('דקות', 'clinic-queue-management'); ?></span>
            </div>
        </div>
    </div>
</div>
<button type="button" class="add-treatment-btn" <?php echo $add_treatment_id ? 'id="' . esc_attr($add_treatment_id) . '"' : ''; ?>>
    <span class="add-treatment-btn__icon" aria-hidden="true">+</span>
    <span class="add-treatment-btn__label"><?php echo esc_html__('הוספת טיפול', 'clinic-queue-management'); ?></span>
</button>
