<?php
/**
 * Treating doctor row in clinic card (booking form).
 *
 * @package Clinic_Queue_Management
 *
 * @var string $doctor_name           Doctor name, or schedule_name when no doctor is assigned.
 * @var string $doctor_url            Link to doctor page (optional).
 * @var bool   $has_treating_doctor   true when a treating doctor exists; false when showing schedule_name.
 */

if (!defined('ABSPATH')) {
    exit;
}

$doctor_name = isset($doctor_name) ? trim((string) $doctor_name) : '';
$doctor_url  = isset($doctor_url) ? trim((string) $doctor_url) : '';
$has_treating_doctor = !empty($has_treating_doctor);

if ($doctor_name === '') {
    return;
}
?>
<div class="clinic-treating-doctor">
    <span class="clinic-treating-doctor__label">
        <?php
        if ($has_treating_doctor) {
            esc_html_e('רופא מטפל:', 'clinic-queue-management');
        } else {
            esc_html_e('עבור:', 'clinic-queue-management');
        }
        ?>
    </span>
    <?php if ($doctor_url !== '') : ?>
        <a class="clinic-treating-doctor__name clinic-treating-doctor-link" href="<?php echo esc_url($doctor_url); ?>">
            <?php echo esc_html($doctor_name); ?>
        </a>
    <?php else : ?>
        <span class="clinic-treating-doctor__name"><?php echo esc_html($doctor_name); ?></span>
    <?php endif; ?>
</div>
