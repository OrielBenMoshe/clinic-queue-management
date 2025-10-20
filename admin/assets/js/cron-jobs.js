/**
 * Cron Jobs JavaScript Functions
 */

function refreshCronStatus() {
    location.reload();
}

function runCronTask(task) {
    if (confirm('האם אתה בטוח שברצונך להריץ את המשימה?')) {
        jQuery.post(ajaxurl, {
            action: 'clinic_queue_run_cron_task',
            task: task,
            nonce: clinicQueueCron.nonce
        }, function(response) {
            if (response.success) {
                alert('המשימה הושלמה בהצלחה!');
                location.reload();
            } else {
                alert('שגיאה בהרצת המשימה: ' + response.data);
            }
        });
    }
}

function resetAllCronJobs() {
    if (confirm('האם אתה בטוח שברצונך לאתחל את כל המשימות?')) {
        jQuery.post(ajaxurl, {
            action: 'clinic_queue_reset_cron',
            nonce: clinicQueueCron.nonce
        }, function(response) {
            if (response.success) {
                alert('כל המשימות אופסו בהצלחה!');
                location.reload();
            } else {
                alert('שגיאה באיפוס המשימות: ' + response.data);
            }
        });
    }
}
