<?php
/**
 * Schedule settings panel – days + treatments (shared wizard / edit modal).
 *
 * @package Clinic_Queue_Management
 * @var array<string, mixed> $config
 */

if (!defined('ABSPATH')) {
    exit;
}

$layout = isset($config['layout']) ? (string) $config['layout'] : 'wizard_scroll';

if ('modal_sections' === $layout) {
    ?>
    <div class="edit-modal__section">
        <div class="edit-modal__section-header">
            <h3 class="edit-modal__section-title" id="edit-modal-days-title">
                <?php echo esc_html__('ימים ושעות עבודה', 'clinic-queue-management'); ?>
            </h3>
            <?php if (!empty($config['show_readonly_badge'])) : ?>
            <span class="edit-modal__readonly-badge" id="edit-modal-clinix-badge" hidden>
                <?php echo esc_html__('נקבע ע"י Clinix — לא ניתן לעריכה', 'clinic-queue-management'); ?>
            </span>
            <?php endif; ?>
        </div>
        <?php Clinic_Schedule_Form_Manager::render_partial('schedule-settings-days', $config); ?>
    </div>
    <div class="edit-modal__section">
        <div class="edit-modal__section-header">
            <h3 class="edit-modal__section-title">
                <?php echo esc_html__('טיפולים', 'clinic-queue-management'); ?>
            </h3>
        </div>
        <?php Clinic_Schedule_Form_Manager::render_partial('schedule-settings-treatments', $config); ?>
    </div>
    <?php
    return;
}

if (!empty($config['show_scroll_wrapper'])) {
    echo '<div class="schedule-settings-scroll-content">';
}

if (!empty($config['show_title'])) {
    ?>
    <div class="jet-form-builder__row field-type-heading is-filled">
        <div class="jet-form-builder__label">
            <div class="jet-form-builder__label-text schedule-settings-step-title"
                style="font-size:26px;font-weight:800;color:#0c1c4a;">
                <?php echo esc_html__('הגדרת ימים ושעות עבודה', 'clinic-queue-management'); ?>
            </div>
        </div>
    </div>
    <?php
}

Clinic_Schedule_Form_Manager::render_partial('schedule-settings-days', $config);
Clinic_Schedule_Form_Manager::render_partial('schedule-settings-treatments', $config);

if (!empty($config['show_scroll_wrapper'])) {
    echo '</div><!-- /.schedule-settings-scroll-content -->';
}

if (!empty($config['show_continue_btn'])) {
    ?>
    <div class="jet-form-builder__row field-type-submit-field continue-wrap">
        <div class="jet-form-builder__action-button-wrapper jet-form-builder__submit-wrap">
            <button type="button"
                class="jet-form-builder__action-button jet-form-builder__submit continue-btn save-schedule-btn">
                <?php echo esc_html__('המשך', 'clinic-queue-management'); ?>
            </button>
        </div>
    </div>
    <?php
}
