<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data from the controller
$auto_sync_next = $data['auto_sync_next'];
$cleanup_next = $data['cleanup_next'];
$extend_next = $data['extend_next'];
$logs = $data['logs'];
?>

<div class="wrap">
    <h1 class="wp-heading-inline">משימות אוטומטיות</h1>
    <button class="page-title-action" onclick="refreshCronStatus()">
        🔄 רענן
    </button>
    <hr class="wp-header-end">
    
    <div class="clinic-queue-cron-dashboard">
        <!-- Cron Status Overview -->
        <div class="cron-status-overview">
            <h2>סטטוס משימות אוטומטיות</h2>
            <div class="cron-status-grid">
                <div class="cron-status-item">
                    <h3>🔄 Auto Sync</h3>
                    <p><strong>תדירות:</strong> כל 30 דקות</p>
                    <p><strong>הבא:</strong> <?php echo $auto_sync_next ? date('d/m/Y H:i:s', $auto_sync_next) : 'לא מתוזמן'; ?></p>
                    <p><strong>סטטוס:</strong> <?php echo $auto_sync_next ? 'פעיל' : 'לא פעיל'; ?></p>
                </div>
                
                <div class="cron-status-item">
                    <h3>🧹 Cleanup</h3>
                    <p><strong>תדירות:</strong> יומי</p>
                    <p><strong>הבא:</strong> <?php echo $cleanup_next ? date('d/m/Y H:i:s', $cleanup_next) : 'לא מתוזמן'; ?></p>
                    <p><strong>סטטוס:</strong> <?php echo $cleanup_next ? 'פעיל' : 'לא פעיל'; ?></p>
                </div>
                
                <div class="cron-status-item">
                    <h3>➕ Extend Calendars</h3>
                    <p><strong>תדירות:</strong> שבועי</p>
                    <p><strong>הבא:</strong> <?php echo $extend_next ? date('d/m/Y H:i:s', $extend_next) : 'לא מתוזמן'; ?></p>
                    <p><strong>סטטוס:</strong> <?php echo $extend_next ? 'פעיל' : 'לא פעיל'; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Manual Actions -->
        <div class="cron-manual-actions">
            <h2>פעולות ידניות</h2>
            <div class="manual-actions-grid">
                <div class="action-card">
                    <h3>🔄 הרץ Auto Sync</h3>
                    <p>מסנכרן את כל היומנים עם ה-API</p>
                    <button class="button button-primary" onclick="runCronTask('auto_sync')">
                        הרץ עכשיו
                    </button>
                </div>
                
                <div class="action-card">
                    <h3>🧹 הרץ Cleanup</h3>
                    <p>מנקה תורים ישנים ו-Cache פג תוקף</p>
                    <button class="button button-secondary" onclick="runCronTask('cleanup')">
                        הרץ עכשיו
                    </button>
                </div>
                
                <div class="action-card">
                    <h3>➕ הרץ Extend Calendars</h3>
                    <p>מוסיף שבוע נוסף לכל יומן</p>
                    <button class="button button-secondary" onclick="runCronTask('extend_calendars')">
                        הרץ עכשיו
                    </button>
                </div>
                
                <div class="action-card">
                    <h3>🔄 אתחל כל המשימות</h3>
                    <p>מגדיר מחדש את כל המשימות האוטומטיות</p>
                    <button class="button button-secondary" onclick="resetAllCronJobs()">
                        אתחל
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Cron Logs -->
        <div class="cron-logs">
            <h2>לוגים אחרונים</h2>
            <div class="logs-container">
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <?php $status_class = $log['status'] === 'success' ? 'success' : 'error'; ?>
                        <div class="log-item <?php echo $status_class; ?>">
                            <div class="log-time"><?php echo esc_html($log['created_at']); ?></div>
                            <div class="log-task"><?php echo esc_html($log['task_name']); ?></div>
                            <div class="log-message"><?php echo esc_html($log['message']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>אין לוגים זמינים</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
