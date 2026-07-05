<?php
/**
 * Settings Page Template
 *
 * @package ClinicQueue
 * @subpackage Admin\Views
 */

if (!defined('ABSPATH')) {
    exit;
}

$notice_message = isset($notice_message) ? $notice_message : '';
$notice_type = isset($notice_type) ? $notice_type : '';
$save_error = isset($save_error) ? $save_error : '';
?>

<div class="wrap clinic-settings-wrap">
    <h1 class="clinic-settings-title"><?php esc_html_e('הגדרות מערכת', 'clinic-queue'); ?></h1>
    <p class="clinic-settings-lead"><?php esc_html_e('חיבור לשרת התורים ול-Google Calendar.', 'clinic-queue'); ?></p>

    <?php if ($notice_message !== '') : ?>
        <div class="notice notice-<?php echo $notice_type === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($notice_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($save_error)) : ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($save_error); ?></p>
        </div>
        <?php delete_transient(Clinic_Queue_Plugin_Settings_Service::TRANSIENT_SAVE_ERROR); ?>
    <?php endif; ?>

    <div class="clinic-queue-settings-container">

        <section class="clinic-settings-card" aria-labelledby="clinic-settings-api-heading">
            <header class="clinic-settings-card__header">
                <h2 id="clinic-settings-api-heading" class="clinic-settings-card__title">
                    <?php esc_html_e('חיבור לשרת', 'clinic-queue'); ?>
                </h2>
                <p class="clinic-settings-card__hint"><?php esc_html_e('נדרש לתיאום תורים מול שירות DoctorOnline.', 'clinic-queue'); ?></p>
            </header>
            <div class="clinic-settings-card__body">
                <?php $handler->render_setting_field('api_token'); ?>

                <div class="settings-divider" role="presentation"></div>

                <?php $handler->render_setting_field('api_endpoint'); ?>

                <div class="settings-divider" role="presentation"></div>

                <?php $handler->render_setting_field('proxy_webhook_token'); ?>
            </div>
        </section>

        <section class="clinic-settings-card" aria-labelledby="clinic-settings-google-heading">
            <header class="clinic-settings-card__header">
                <h2 id="clinic-settings-google-heading" class="clinic-settings-card__title">
                    <?php esc_html_e('יומן Google', 'clinic-queue'); ?>
                </h2>
                <p class="clinic-settings-card__hint"><?php esc_html_e('מאפשר לרופאים לחבר יומן מהאתר.', 'clinic-queue'); ?></p>
            </header>
            <div class="clinic-settings-card__body">
                <?php $handler->render_setting_field('google_client_id'); ?>

                <div class="settings-divider" role="presentation"></div>

                <?php $handler->render_setting_field('google_client_secret'); ?>
            </div>
        </section>

    </div>
</div>
