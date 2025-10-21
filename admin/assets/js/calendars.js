/**
 * Calendars JavaScript Functions
 */

jQuery(document).ready(function($) {
    // Table sorting functionality
    $('.calendars-table-container th').not('.no-sort').on('click', function() {
        var column = $(this).index();
        var table = $(this).closest('table');
        var tbody = table.find('tbody');
        var rows = tbody.find('tr').toArray();
        var currentSort = $(this).data('sort') || 'none'; // none, asc, desc
        
        // Remove existing sort classes
        table.find('th').removeClass('sort-asc sort-desc').removeData('sort');
        
        var newSort = 'none';
        var sortAscending = false;
        
        // Determine new sort state
        if (currentSort === 'none') {
            newSort = 'desc';
            sortAscending = false;
        } else if (currentSort === 'desc') {
            newSort = 'asc';
            sortAscending = true;
        } else if (currentSort === 'asc') {
            newSort = 'none';
            // Don't sort, just reset
        }
        
        if (newSort !== 'none') {
            // Sort rows
            rows.sort(function(a, b) {
                var aVal = $(a).find('td').eq(column).text().trim();
                var bVal = $(b).find('td').eq(column).text().trim();
                
                // Try to parse as numbers first
                var aNum = parseFloat(aVal);
                var bNum = parseFloat(bVal);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return sortAscending ? aNum - bNum : bNum - aNum;
                }
                
                // String comparison
                return sortAscending ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            });
            
            // Reorder rows
            $.each(rows, function(index, row) {
                tbody.append(row);
            });
        }
        
        // Add sort class and data
        if (newSort !== 'none') {
            $(this).addClass('sort-' + newSort).data('sort', newSort);
        }
    });
    
    // Add sort indicators to headers
    $('.calendars-table-container th').not('.no-sort').each(function() {
        $(this).append('<div class="sort-indicators"><span class="sort-up dashicons dashicons-arrow-up-alt2"></span><span class="sort-down dashicons dashicons-arrow-down-alt2"></span></div>');
        $(this).attr('title', 'לחץ למיון - לחיצה חוזרת תהפוך את הכיוון');
    });
    
    // Table search functionality
    $('#table-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        var table = $('.calendars-table-container table');
        var rows = table.find('tbody tr');
        
        rows.each(function() {
            var rowText = $(this).text().toLowerCase();
            if (rowText.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    // Sync calendar
    $('.sync-calendar').on('click', function() {
        var button = $(this);
        var doctor = button.data('doctor');
        var clinic = button.data('clinic');
        var treatment = button.data('treatment');
        
        if (confirm('האם אתה בטוח שברצונך לסנכרן את היומן?')) {
            button.prop('disabled', true).text('מסנכרן...');
            
            $.post(clinicQueueCalendars.ajaxurl, {
                action: 'clinic_queue_sync_calendar',
                doctor_id: doctor,
                clinic_id: clinic,
                treatment_type: treatment,
                nonce: clinicQueueCalendars.sync_nonce
            }, function(response) {
                if (response.success) {
                    alert('היומן סונכרן בהצלחה!');
                    location.reload();
                } else {
                    alert('שגיאה בסנכרון: ' + response.data);
                    button.prop('disabled', false).text('סנכרן');
                }
            });
        }
    });
    
    // Delete calendar
    $('.delete-calendar').on('click', function() {
        var button = $(this);
        var id = button.data('id');
        
        if (confirm('האם אתה בטוח שברצונך למחוק את היומן? פעולה זו לא ניתנת לביטול.')) {
            button.prop('disabled', true).text('מוחק...');
            
            $.post(clinicQueueCalendars.ajaxurl, {
                action: 'clinic_queue_delete_calendar',
                calendar_id: id,
                nonce: clinicQueueCalendars.delete_nonce
            }, function(response) {
                if (response.success) {
                    alert('היומן נמחק בהצלחה!');
                    location.reload();
                } else {
                    alert('שגיאה במחיקה: ' + response.data);
                    button.prop('disabled', false).text('מחק');
                }
            });
        }
    });
    
    // Calendar View Dialog
    let currentCalendarIndex = 0;
    let calendarsList = [];
    
    // Initialize calendars list from table
    function initializeCalendarsList() {
        calendarsList = [];
        $('.view-calendar').each(function() {
            calendarsList.push({
                id: $(this).data('id'),
                doctor: $(this).data('doctor'),
                clinic: $(this).data('clinic'),
                treatment: $(this).data('treatment')
            });
        });
    }
    
    // View calendar dialog
    $('.view-calendar').on('click', function() {
        initializeCalendarsList();
        const calendarId = $(this).data('id');
        currentCalendarIndex = calendarsList.findIndex(cal => cal.id == calendarId);
        
        if (currentCalendarIndex === -1) return;
        
        loadCalendarDialog(calendarId);
        updateNavigationButtons();
        $('#calendar-view-dialog')[0].showModal();
    });
    
    // Close dialog
    $('#close-dialog').on('click', function() {
        $('#calendar-view-dialog')[0].close();
    });
    
    // Close dialog when clicking outside
    $('#calendar-view-dialog').on('click', function(e) {
        if (e.target === this) {
            this.close();
        }
    });
    
    // Navigation buttons
    $('#prev-calendar').on('click', function() {
        if (currentCalendarIndex > 0) {
            currentCalendarIndex--;
            const calendar = calendarsList[currentCalendarIndex];
            loadCalendarDialog(calendar.id);
            updateNavigationButtons();
        }
    });
    
    $('#next-calendar').on('click', function() {
        if (currentCalendarIndex < calendarsList.length - 1) {
            currentCalendarIndex++;
            const calendar = calendarsList[currentCalendarIndex];
            loadCalendarDialog(calendar.id);
            updateNavigationButtons();
        }
    });
    
    // Update navigation buttons state
    function updateNavigationButtons() {
        $('#prev-calendar').prop('disabled', currentCalendarIndex === 0);
        $('#next-calendar').prop('disabled', currentCalendarIndex === calendarsList.length - 1);
    }
    
    // Load calendar data in dialog
    function loadCalendarDialog(calendarId) {
        console.log('Loading calendar dialog for ID:', calendarId);
        $('#dialog-body').html('<div class="loading">טוען...</div>');
        
        $.post(clinicQueueCalendars.ajaxurl, {
            action: 'clinic_queue_get_calendar_details',
            calendar_id: calendarId,
            nonce: clinicQueueCalendars.view_nonce
        }, function(response) {
            console.log('=== CALENDAR DIALOG DATA ===');
            console.log('Calendar ID:', calendarId);
            console.log('Full response object:', response);
            console.log('Response success:', response.success);
            console.log('Response data type:', typeof response.data);
            console.log('Response data length:', response.data ? response.data.length : 'N/A');
            console.log('Response data preview:', response.data ? response.data.substring(0, 200) + '...' : 'N/A');
            console.log('=== END CALENDAR DIALOG DATA ===');
            
            if (response.success) {
                $('#dialog-body').html(response.data);
                // Initialize calendar tabs after content is loaded
                initializeCalendarTabs();
                
                // Print all available slots when dialog loads
                setTimeout(function() {
                    console.log('=== ALL AVAILABLE SLOTS IN DIALOG ===');
                    $('.day-time-slots').each(function() {
                        const date = $(this).data('date');
                        const timeSlotBadges = $(this).find('.time-slot-badge');
                        console.log(`Date: ${date} - Found ${timeSlotBadges.length} slots`);
                        
                        timeSlotBadges.each(function(index) {
                            const slot = $(this);
                            const time = slot.text().trim();
                            const isBooked = slot.hasClass('booked');
                            const isFree = slot.hasClass('free');
                            const status = isBooked ? 'תפוס (BOOKED)' : (isFree ? 'פנוי (FREE)' : 'לא מוגדר');
                            
                            console.log(`  Slot ${index + 1}: ${time} - ${status}`);
                        });
                    });
                    console.log('=== END ALL SLOTS ===');
                }, 200);
            } else {
                console.error('Error loading calendar:', response.data);
                $('#dialog-body').html('<div class="error">שגיאה בטעינת פרטי היומן: ' + response.data + '</div>');
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX request failed:', status, error);
            console.error('Response:', xhr.responseText);
        });
    }
    
    // Keyboard navigation
    $(document).on('keydown', function(e) {
        if ($('#calendar-view-dialog')[0].open) {
            if (e.key === 'ArrowLeft' && !$('#prev-calendar').prop('disabled')) {
                $('#prev-calendar').click();
            } else if (e.key === 'ArrowRight' && !$('#next-calendar').prop('disabled')) {
                $('#next-calendar').click();
            } else if (e.key === 'Escape') {
                $('#calendar-view-dialog')[0].close();
            }
        }
    });
    
    // Resizable table columns
    let isResizing = false;
    let currentColumn = null;
    let startX = 0;
    let startWidth = 0;
    
    // Initialize column widths from localStorage
    function initializeColumnWidths() {
        const savedWidths = localStorage.getItem('clinic_queue_column_widths');
        if (savedWidths) {
            const widths = JSON.parse(savedWidths);
            $('.calendars-table-container table').css('table-layout', 'fixed');
            $('.calendars-table-container th').each(function(index) {
                if (widths[index]) {
                    $(this).css('width', widths[index] + 'px');
                }
            });
        } else {
            // Use auto layout for content-based sizing
            $('.calendars-table-container table').css('table-layout', 'auto');
        }
    }
    
    // Save column widths to localStorage
    function saveColumnWidths() {
        const widths = [];
        $('.calendars-table-container th').each(function() {
            widths.push($(this).outerWidth());
        });
        localStorage.setItem('clinic_queue_column_widths', JSON.stringify(widths));
    }
    
    // Initialize on page load
    initializeColumnWidths();
    
    // Calendar day tabs functionality
    function initializeCalendarTabs() {
        console.log('Initializing calendar tabs...');
        console.log('Found day tabs:', $('.day-tab').length);
        
        // Use event delegation to handle dynamically loaded content
        $(document).off('click', '.day-tab').on('click', '.day-tab', function() {
            console.log('Day tab clicked!');
            const selectedDate = $(this).data('date');
            console.log('Selected date:', selectedDate);
            
            // Remove active class from all tabs and time slots
            $('.day-tab').removeClass('active selected');
            $('.day-time-slots').removeClass('active');
            
            // Add active class to clicked tab
            $(this).addClass('active selected');
            
            // Show corresponding time slots - wait a bit for DOM to update
            setTimeout(function() {
                const targetSlots = $(`.day-time-slots[data-date="${selectedDate}"]`);
                console.log('Found time slots:', targetSlots.length);
                console.log('Target slots element:', targetSlots);
                
                if (targetSlots.length > 0) {
                    targetSlots.addClass('active');
                    console.log('Activated time slots for date:', selectedDate);
                    
                    // Print all slots and their status
                    console.log('=== SLOTS STATUS FOR DATE:', selectedDate, '===');
                    const timeSlots = targetSlots.find('.time-slot');
                    console.log('Total slots found:', timeSlots.length);
                    
                    timeSlots.each(function(index) {
                        const slot = $(this);
                        const time = slot.find('.slot-time').text().trim();
                        const isBooked = slot.hasClass('booked');
                        const isAvailable = slot.hasClass('available');
                        const status = isBooked ? 'תפוס (BOOKED)' : (isAvailable ? 'פנוי (AVAILABLE)' : 'לא מוגדר');
                        
                        console.log(`Slot ${index + 1}: ${time} - ${status}`);
                    });
                    console.log('=== END SLOTS STATUS ===');
                    
                } else {
                    console.warn('No time slots found for date:', selectedDate);
                    // Try to find all available time slots containers
                    console.log('All time slots containers:', $('.day-time-slots').length);
                    $('.day-time-slots').each(function(index) {
                        console.log('Time slots container', index, ':', $(this).data('date'));
                    });
                }
            }, 100);
        });
    }
    
    // Mouse down on column header
    $('.calendars-table-container th').on('mousedown', function(e) {
        if ($(this).is(':last-child')) return; // Don't resize last column
        
        // Switch to fixed layout when user starts resizing
        $('.calendars-table-container table').css('table-layout', 'fixed');
        
        isResizing = true;
        currentColumn = $(this);
        startX = e.pageX;
        startWidth = currentColumn.outerWidth();
        
        $('body').addClass('resizing');
        currentColumn.addClass('resizing');
        
        e.preventDefault();
    });
    
    // Mouse move during resize
    $(document).on('mousemove', function(e) {
        if (!isResizing || !currentColumn) return;
        
        const deltaX = e.pageX - startX;
        const newWidth = startWidth + deltaX;
        const minWidth = 50; // Minimum column width
        
        if (newWidth >= minWidth) {
            currentColumn.css('width', newWidth + 'px');
        }
    });
    
    // Mouse up - end resize
    $(document).on('mouseup', function() {
        if (isResizing) {
            isResizing = false;
            $('body').removeClass('resizing');
            if (currentColumn) {
                currentColumn.removeClass('resizing');
                saveColumnWidths();
            }
            currentColumn = null;
        }
    });
    
    // Reset column widths
    function resetColumnWidths() {
        localStorage.removeItem('clinic_queue_column_widths');
        $('.calendars-table-container th').css('width', '');
        $('.calendars-table-container table').css('table-layout', 'auto');
        location.reload();
    }
    
    // Add reset button (optional)
    if ($('.calendars-table-container').length) {
        $('.table-controls').append('<button id="reset-columns" class="button button-small" style="margin-right: 10px;">איפוס רוחב עמודות</button>');
        
        $('#reset-columns').on('click', function() {
            if (confirm('האם אתה בטוח שברצונך לאפס את רוחב העמודות?')) {
                resetColumnWidths();
            }
        });
    }
});
