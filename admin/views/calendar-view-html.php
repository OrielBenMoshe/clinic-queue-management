<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data from the controller
$calendar = $data['calendar'];
$appointments = $data['appointments'];
?>

<div class="wrap">
    <h1 class="wp-heading-inline">פרטי יומן</h1>
    <a href="<?php echo admin_url('admin.php?page=clinic-queue-calendars'); ?>" class="page-title-action">
        חזור לרשימת יומנים
    </a>
    <hr class="wp-header-end">

    <div class="calendar-details">
        <div class="calendar-info">
            <h2><?php echo esc_html($calendar->calendar_name); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">מזהה רופא:</th>
                    <td><strong><?php echo esc_html($calendar->doctor_id); ?></strong></td>
                </tr>
                <tr>
                    <th scope="row">מזהה מרפאה:</th>
                    <td><?php echo esc_html($calendar->clinic_id); ?></td>
                </tr>
                <tr>
                    <th scope="row">סוג טיפול:</th>
                    <td><?php echo esc_html($calendar->treatment_type); ?></td>
                </tr>
                <tr>
                    <th scope="row">עודכן לאחרונה:</th>
                    <td><?php echo esc_html($calendar->last_updated); ?></td>
                </tr>
                <tr>
                    <th scope="row">נוצר ב:</th>
                    <td><?php echo esc_html($calendar->created_at); ?></td>
                </tr>
            </table>
        </div>

        <div class="appointments-calendar">
            <h3>לוח תורים - 4 השבועות הקרובים</h3>

            <?php if (empty($appointments)): ?>
                <div class="notice notice-info">
                    <p>אין תורים זמינים לתקופה הקרובה.</p>
                </div>
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

        <div class="appointments-overview">
            <h3>סטטיסטיקות תורים</h3>
            <?php
            $total_dates = count($appointments);
            $total_slots = 0;
            $booked_slots = 0;
            $free_slots = 0;

            foreach ($appointments as $appointment) {
                foreach ($appointment['time_slots'] as $slot) {
                    $total_slots++;
                    if ($slot->is_booked) {
                        $booked_slots++;
                    } else {
                        $free_slots++;
                    }
                }
            }
            ?>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $total_dates; ?></span>
                    <span class="stat-label">תאריכים זמינים</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $total_slots; ?></span>
                    <span class="stat-label">סה"כ תורים</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number booked"><?php echo $booked_slots; ?></span>
                    <span class="stat-label">תורים תפוסים</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number free"><?php echo $free_slots; ?></span>
                    <span class="stat-label">תורים פנויים</span>
                </div>
            </div>
        </div>

    </div>
</div>