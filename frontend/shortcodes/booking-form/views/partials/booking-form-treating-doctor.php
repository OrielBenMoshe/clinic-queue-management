<?php
/**
 * שורת רופא מטפל בכרטיס המרפאה (טופס קביעת תור).
 *
 * @package Clinic_Queue_Management
 *
 * @var string $doctor_name שם הרופא.
 * @var string $doctor_url  קישור לדף הרופא (אופציונלי).
 */

if (!defined('ABSPATH')) {
    exit;
}

$doctor_name = isset($doctor_name) ? trim((string) $doctor_name) : '';
$doctor_url  = isset($doctor_url) ? trim((string) $doctor_url) : '';

if ($doctor_name === '') {
    return;
}
?>
<div class="clinic-treating-doctor">
    <span class="clinic-treating-doctor__label"><?php esc_html_e('רופא מטפל:', 'clinic-queue-management'); ?></span>
    <?php if ($doctor_url !== '') : ?>
        <a class="clinic-treating-doctor__name clinic-treating-doctor-link" href="<?php echo esc_url($doctor_url); ?>">
            <?php echo esc_html($doctor_name); ?>
        </a>
    <?php else : ?>
        <span class="clinic-treating-doctor__name"><?php echo esc_html($doctor_name); ?></span>
    <?php endif; ?>
</div>
