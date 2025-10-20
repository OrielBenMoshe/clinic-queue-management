<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data from the controller
$calendars_count = $data['calendars_count'];
$total_appointments = $data['total_appointments'];
$booked_appointments = $data['booked_appointments'];
$sync_status = $data['sync_status'];
$recent_bookings = $data['recent_bookings'];
?>

<div class="wrap">
    <h1 class="wp-heading-inline">דשבורד - ניהול תורי מרפאה</h1>
    <hr class="wp-header-end">
    
    <!-- Statistics Cards -->
    <div class="clinic-queue-dashboard">
        <div class="clinic-queue-stats">
            <div class="clinic-queue-stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-content">
                    <h3><?php echo $calendars_count; ?></h3>
                    <p>יומנים פעילים</p>
                </div>
            </div>
            
            <div class="clinic-queue-stat-card">
                <div class="stat-icon">⏰</div>
                <div class="stat-content">
                    <h3><?php echo $total_appointments; ?></h3>
                    <p>סה"כ תורים</p>
                </div>
            </div>
            
            <div class="clinic-queue-stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo $booked_appointments; ?></h3>
                    <p>תורים מוזמנים</p>
                </div>
            </div>
            
            <div class="clinic-queue-stat-card">
                <div class="stat-icon">🔄</div>
                <div class="stat-content">
                    <h3><?php echo $this->get_synced_calendars_count($sync_status); ?></h3>
                    <p>יומנים מסונכרנים</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="clinic-queue-actions">
            <h2>פעולות מהירות</h2>
            <div class="action-buttons">
                <button class="button button-primary" onclick="syncAllCalendars()">
                    🔄 סנכרן כל היומנים
                </button>
                <button class="button button-secondary" onclick="clearCache()">
                    🗑️ נקה Cache
                </button>
                <button class="button button-secondary" onclick="generateNewAppointments()">
                    ➕ צור תורים חדשים
                </button>
                <a href="<?php echo admin_url('admin.php?page=clinic-queue-calendars'); ?>" class="button">
                    📋 ניהול יומנים
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="clinic-queue-recent">
            <h2>פעילות אחרונה</h2>
            <?php if (!empty($recent_bookings)): ?>
                <div class="recent-bookings">
                    <?php foreach ($recent_bookings as $booking): ?>
                        <div class="booking-item">
                            <div class="booking-info">
                                <strong><?php echo esc_html($booking->patient_name); ?></strong>
                                <span class="booking-time"><?php echo esc_html($booking->time_slot); ?></span>
                            </div>
                            <div class="booking-date">
                                <?php echo esc_html($booking->appointment_date); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>אין הזמנות אחרונות</p>
            <?php endif; ?>
        </div>
        
        <!-- Sync Status -->
        <div class="clinic-queue-sync-status">
            <h2>סטטוס סנכרון</h2>
            <div class="sync-status-grid">
                <?php foreach ($sync_status as $calendar): ?>
                    <div class="sync-item status-<?php echo $calendar->sync_status; ?>">
                        <div class="sync-info">
                            <strong><?php echo esc_html($calendar->doctor_id); ?></strong>
                            <span><?php echo esc_html($calendar->clinic_id); ?></span>
                        </div>
                        <div class="sync-time">
                            <?php echo esc_html($calendar->last_updated); ?>
                        </div>
                        <div class="sync-status-badge">
                            <?php echo $this->get_sync_status_icon($calendar->sync_status); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="clinic-queue-system-info">
            <h2>מידע מערכת</h2>
            <div class="system-info-grid">
                <div class="info-item">
                    <strong>גרסת התוסף:</strong> 1.0.0
                </div>
                <div class="info-item">
                    <strong>גרסת מסד נתונים:</strong> <?php echo get_option('clinic_queue_db_version', 'לא ידוע'); ?>
                </div>
                <div class="info-item">
                    <strong>זמן סנכרון אחרון:</strong> <?php echo $this->get_last_sync_time(); ?>
                </div>
                <div class="info-item">
                    <strong>סטטוס Cron:</strong> <?php echo $this->get_cron_status(); ?>
                </div>
            </div>
        </div>
    </div>
</div>
