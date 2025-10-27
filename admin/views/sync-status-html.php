<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data from the controller with safety checks
if (!isset($data) || !is_array($data)) {
    wp_die('Invalid data provided to view');
}

$sync_status = isset($data['sync_status']) ? $data['sync_status'] : array();
$api_stats = isset($data['api_stats']) ? $data['api_stats'] : array();
$logs = isset($data['logs']) ? $data['logs'] : array();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">סטטוס סנכרון</h1>
    <button class="page-title-action" onclick="refreshSyncStatus()">
        🔄 רענן
    </button>
    <hr class="wp-header-end">
    
    <!-- Sync Overview -->
    <div class="sync-overview">
        <div class="sync-stats">
            <div class="stat-card">
                <h3><?php echo $api_stats['total_calendars']; ?></h3>
                <p>סה"כ יומנים</p>
            </div>
            <div class="stat-card success">
                <h3><?php echo $api_stats['synced']; ?></h3>
                <p>מסונכרנים</p>
            </div>
            <div class="stat-card warning">
                <h3><?php echo $api_stats['stale']; ?></h3>
                <p>ישנים</p>
            </div>
            <div class="stat-card error">
                <h3><?php echo $api_stats['outdated']; ?></h3>
                <p>לא מעודכנים</p>
            </div>
        </div>
    </div>
    
    <!-- Sync Status Table -->
    <div class="sync-status-table">
        <h2>סטטוס יומנים</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>יומן</th>
                    <th>סטטוס</th>
                    <th>סנכרון אחרון</th>
                    <th>זמן תגובה</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sync_status as $calendar): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($calendar->doctor_id); ?></strong><br>
                            <small><?php echo esc_html($calendar->clinic_id); ?> - <?php echo esc_html($calendar->treatment_type); ?></small>
                        </td>
                        <td>
                            <span class="status-indicator status-<?php echo $calendar->sync_status; ?>">
                                <?php echo $this->get_status_icon($calendar->sync_status); ?>
                                <?php echo $this->get_status_text($calendar->sync_status); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($calendar->last_updated); ?>
                            <br>
                            <small><?php echo $this->get_time_ago($calendar->last_updated); ?></small>
                        </td>
                        <td>
                            <?php echo $this->get_response_time($calendar->last_updated); ?>
                        </td>
                        <td>
                            <button class="button button-small sync-now" 
                                    data-doctor="<?php echo esc_attr($calendar->doctor_id); ?>" 
                                    data-clinic="<?php echo esc_attr($calendar->clinic_id); ?>" 
                                    data-treatment="<?php echo esc_attr($calendar->treatment_type); ?>">
                                סנכרן עכשיו
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Sync Logs -->
    <div class="sync-logs">
        <h2>לוגי סנכרון</h2>
        <div class="logs-container">
            <?php if (!empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                    <?php $log_class = $log['type'] === 'error' ? 'error' : 'success'; ?>
                    <div class="log-entry <?php echo $log_class; ?>">
                        <span class="log-time">[<?php echo esc_html($log['timestamp']); ?>]</span>
                        <span class="log-message"><?php echo esc_html($log['message']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>אין לוגים זמינים</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sync Actions -->
    <div class="sync-actions">
        <h2>פעולות סנכרון</h2>
        <div class="action-buttons">
            <button class="button button-primary" onclick="syncAllCalendars()">
                🔄 סנכרן הכל
            </button>
            <button class="button button-secondary" onclick="clearAllCache()">
                🗑️ נקה Cache
            </button>
            <button class="button button-secondary" onclick="testApiConnection()">
                🔍 בדוק חיבור API
            </button>
        </div>
    </div>
</div>
