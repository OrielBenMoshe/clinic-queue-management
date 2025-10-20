/**
 * Dashboard JavaScript Functions
 */

function syncAllCalendars() {
    if (confirm('האם אתה בטוח שברצונך לסנכרן את כל היומנים?')) {
        jQuery.post(ajaxurl, {
            action: 'clinic_queue_sync_all',
            nonce: clinicQueueDashboard.nonce
        }, function(response) {
            if (response.success) {
                alert('הסנכרון הושלם בהצלחה!');
                location.reload();
            } else {
                alert('שגיאה בסנכרון: ' + response.data);
            }
        });
    }
}

function clearCache() {
    if (confirm('האם אתה בטוח שברצונך לנקות את ה-Cache?')) {
        jQuery.post(ajaxurl, {
            action: 'clinic_queue_clear_cache',
            nonce: clinicQueueDashboard.nonce
        }, function(response) {
            if (response.success) {
                alert('ה-Cache נוקה בהצלחה!');
                location.reload();
            } else {
                alert('שגיאה בניקוי Cache: ' + response.data);
            }
        });
    }
}

function generateNewAppointments() {
    if (confirm('האם אתה בטוח שברצונך ליצור תורים חדשים?')) {
        jQuery.post(ajaxurl, {
            action: 'clinic_queue_generate_appointments',
            nonce: clinicQueueDashboard.nonce
        }, function(response) {
            if (response.success) {
                alert('התורים החדשים נוצרו בהצלחה!');
                location.reload();
            } else {
                alert('שגיאה ביצירת תורים: ' + response.data);
            }
        });
    }
}
