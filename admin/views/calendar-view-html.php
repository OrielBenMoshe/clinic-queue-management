<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data from the controller
$calendar = $data['calendar'];
$appointments = $data['appointments'];

// Include common components
$common_file = plugin_dir_path(__FILE__) . 'calendar-common-html.php';
if (!function_exists('cqm_generate_calendar_info_table') && file_exists($common_file)) {
    include_once $common_file;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">פרטי יומן</h1>
    <a href="<?php echo admin_url('admin.php?page=clinic-queue-calendars'); ?>" class="page-title-action">
        חזור לרשימת יומנים
    </a>
    <hr class="wp-header-end">

    <div class="calendar-details">
        <?php echo cqm_generate_calendar_info_table($calendar, false); ?>

        <div class="appointments-calendar">
            <h3>לוח תורים - 4 השבועות הקרובים</h3>

            <?php if (empty($appointments)): ?>
                <?php echo cqm_generate_empty_appointments_notice(false); ?>
            <?php else: ?>
                <div class="calendar-grid">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="date-card">
                            <div class="date-header">
                                <h4><?php echo date('d/m/Y', strtotime($appointment['date']->appointment_date)); ?></h4>
                                <span
                                    class="day-name"><?php echo date('l', strtotime($appointment['date']->appointment_date)); ?></span>
                            </div>

                            <div class="time-slots">
                                <?php if (empty($appointment['time_slots'])): ?>
                                    <p class="no-slots">אין תורים זמינים</p>
                                <?php else: ?>
                                    <?php foreach ($appointment['time_slots'] as $slot): ?>
                                        <div
                                            class="time-slot <?php echo $slot->is_booked ? 'booked' : 'free'; ?>">
                                            <span class="time"><?php echo date('H:i', strtotime($slot->time_slot)); ?></span>
                                            <?php if ($slot->is_booked): ?>
                                                <span class="status">תפוס</span>
                                                <?php if ($slot->patient_name): ?>
                                                    <span class="patient"><?php echo esc_html($slot->patient_name); ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="status">פנוי</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php echo cqm_generate_appointments_stats($appointments); ?>

    </div>
</div>