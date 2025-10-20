<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data from the controller
$status = $data['status'];
?>

<div class="wrap">
    <h1 class="wp-heading-inline">ניהול יומנים</h1>
    <a href="<?php echo admin_url('admin.php?page=clinic-queue-calendars&action=add'); ?>" class="page-title-action">
        הוסף יומן חדש
    </a>
    <hr class="wp-header-end">
    
    <?php if (empty($status)): ?>
        <div class="notice notice-info">
            <p>אין יומנים זמינים. צור וויג'ט כדי לאתחל יומנים.</p>
        </div>
    <?php else: ?>
        <div class="table-controls">
            <div class="search-box">
                <input type="text" id="table-search" placeholder="חפש בטבלה..." />
                <span class="search-icon dashicons dashicons-search"></span>
            </div>
        </div>
        
        <div class="calendars-summary">
            <div class="summary-stats">
                <div class="stat-item">
                    <span class="stat-label">סה"כ יומנים:</span>
                    <span class="stat-value"><?php echo count($status); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">מסונכרנים:</span>
                    <span class="stat-value synced"><?php echo count(array_filter($status, function($cal) { return $cal->sync_status === 'synced'; })); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">לא מעודכנים:</span>
                    <span class="stat-value outdated"><?php echo count(array_filter($status, function($cal) { return $cal->sync_status === 'outdated'; })); ?></span>
                </div>
            </div>
        </div>
        
        <div class="calendars-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col">מזהה רופא</th>
                        <th scope="col">שם רופא</th>
                        <th scope="col">מזהה מרפאה</th>
                        <th scope="col">שם מרפאה</th>
                        <th scope="col">סוג טיפול</th>
                        <th scope="col">סנכרון אחרון</th>
                        <th scope="col">סטטוס</th>
                        <th scope="col" class="no-sort">פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status as $calendar): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($calendar->doctor_id); ?></strong>
                            </td>
                            <td><?php echo esc_html($this->get_doctor_name($calendar->doctor_id)); ?></td>
                            <td><?php echo esc_html($calendar->clinic_id); ?></td>
                            <td><?php echo esc_html($this->get_clinic_name($calendar->clinic_id)); ?></td>
                            <td><?php echo esc_html($calendar->treatment_type); ?></td>
                            <td><?php echo esc_html($calendar->last_updated); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $calendar->sync_status; ?>">
                                    <?php 
                                    switch ($calendar->sync_status) {
                                        case 'synced':
                                            echo 'מסונכרן';
                                            break;
                                        case 'stale':
                                            echo 'ישן';
                                            break;
                                        case 'outdated':
                                            echo 'לא מעודכן';
                                            break;
                                        default:
                                            echo 'לא ידוע';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="calendar-actions">
                                    <button class="button button-small view-calendar" 
                                            data-id="<?php echo esc_attr($calendar->id); ?>"
                                            data-doctor="<?php echo esc_attr($calendar->doctor_id); ?>" 
                                            data-clinic="<?php echo esc_attr($calendar->clinic_id); ?>" 
                                            data-treatment="<?php echo esc_attr($calendar->treatment_type); ?>">
                                        הצג
                                    </button>
                                    <button class="button button-small sync-calendar" 
                                            data-doctor="<?php echo esc_attr($calendar->doctor_id); ?>" 
                                            data-clinic="<?php echo esc_attr($calendar->clinic_id); ?>" 
                                            data-treatment="<?php echo esc_attr($calendar->treatment_type); ?>">
                                        סנכרן
                                    </button>
                                    <button class="button button-small button-link-delete delete-calendar" 
                                            data-id="<?php echo esc_attr($calendar->id); ?>">
                                        מחק
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Calendar View Dialog -->
<dialog id="calendar-view-dialog" class="calendar-dialog">
    <div class="dialog-content">
        <div class="dialog-header">
            <div class="dialog-nav">
                <button class="nav-button next-calendar" id="next-calendar" disabled>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
                <h2 class="dialog-title">פרטי יומן</h2>
                <button class="nav-button prev-calendar" id="prev-calendar" disabled>
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>
            </div>
            <button class="dialog-close" id="close-dialog">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="dialog-body" id="dialog-body">
            <!-- Content will be loaded here -->
        </div>
    </div>
</dialog>
