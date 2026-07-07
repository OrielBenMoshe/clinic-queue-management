<?php
/**
 * Step: final success.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="step final-success-step" data-step="final-success" aria-hidden="true" style="display:none;">
    <div class="final-success-confetti" aria-hidden="true"></div>
    <div class="final-success-body">
        <div class="final-success-icon-wrapper">
            <img class="final-success-icon"
                src="<?php echo esc_url(CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/vii.png'); ?>"
                alt=""
                aria-hidden="true"
                width="120"
                height="120">
        </div>
        <div class="final-success-header">
            <h2 class="final-success-title"><?php echo esc_html__('היומן נוצר בהצלחה!', 'clinic-queue-management'); ?></h2>
            <p class="final-success-transfer-subtitle">
                <?php
                echo wp_kses(
                    __('חיבור ה<strong>יומן לגוגל</strong> עדיין ממתין לביצוע על ידי הרופא / המטפל.<br>העתק את הקישור ושלח אותו לרופא להשלמת החיבור.', 'clinic-queue-management'),
                    array('strong' => array(), 'br' => array())
                );
                ?>
            </p>
        </div>
        <div class="final-success-actions">
            <button type="button" class="copy-connect-link-btn"
                data-connect-url=""
                style="display:none;"
                aria-label="<?php echo esc_attr__('העתק קישור לחיבור יומן גוגל', 'clinic-queue-management'); ?>">
                <svg class="copy-connect-link-btn__icon" aria-hidden="true"
                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="16" height="16">
                    <path fill="currentColor"
                        d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z" />
                </svg>
                <span class="copy-connect-link-btn__label"><?php echo esc_html__('העתק קישור לחיבור יומן גוגל', 'clinic-queue-management'); ?></span>
                <span class="copy-connect-link-btn__copied" aria-live="polite">
                    <svg aria-hidden="true" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="14" height="14">
                        <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                    <?php echo esc_html__('הקישור הועתק!', 'clinic-queue-management'); ?>
                </span>
            </button>
            <button type="button" class="jet-form-builder__action-button jet-form-builder__submit finish-btn">
                <?php echo esc_html__('סיום', 'clinic-queue-management'); ?>
            </button>
        </div>
    </div>
</div>
