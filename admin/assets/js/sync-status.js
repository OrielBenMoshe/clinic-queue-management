/**
 * Sync Status JavaScript Functions
 */

function refreshSyncStatus() {
    location.reload();
}

function syncAllCalendars() {
    if (confirm('האם אתה בטוח שברצונך לסנכרן את כל היומנים?')) {
        jQuery.post(ajaxurl, {
            action: 'clinic_queue_sync_all',
            nonce: clinicQueueSyncStatus.nonce
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

function clearAllCache() {
    if (confirm('האם אתה בטוח שברצונך לנקות את כל ה-Cache?')) {
        jQuery.post(ajaxurl, {
            action: 'clinic_queue_clear_cache',
            nonce: clinicQueueSyncStatus.nonce
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

function testApiConnection() {
    jQuery.post(ajaxurl, {
        action: 'clinic_queue_test_api',
        nonce: clinicQueueSyncStatus.nonce
    }, function(response) {
        if (response.success) {
            alert('חיבור API תקין!\nזמן תגובה: ' + response.data.response_time + 'ms');
        } else {
            alert('שגיאה בחיבור API: ' + response.data);
        }
    });
}

jQuery(document).ready(function($) {
    $('.sync-now').on('click', function() {
        var button = $(this);
        var doctor = button.data('doctor');
        var clinic = button.data('clinic');
        var treatment = button.data('treatment');
        
        button.prop('disabled', true).text('מסנכרן...');
        
        $.post(ajaxurl, {
            action: 'clinic_queue_sync_calendar',
            doctor_id: doctor,
            clinic_id: clinic,
            treatment_type: treatment,
            nonce: clinicQueueSyncStatus.nonce
        }, function(response) {
            if (response.success) {
                alert('היומן סונכרן בהצלחה!');
                location.reload();
            } else {
                alert('שגיאה בסנכרון: ' + response.data);
                button.prop('disabled', false).text('סנכרן עכשיו');
            }
        });
    });
});
