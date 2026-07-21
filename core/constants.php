<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ברירות מחדל של הפרויקט – לא מגדירים CLINIC_QUEUE_API_* / GOOGLE_* גלובליים.
 * קריאה בפועל: Clinic_Queue_Plugin_Settings_Service (אופציות > wp-config > DEFAULT_*).
 *
 * אופציונלי ב-wp-config.php: CLINIC_QUEUE_API_ENDPOINT, CLINIC_QUEUE_API_TOKEN,
 * GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_CALENDAR_SCOPES, DOCTOR_ONLINE_PROXY_*.
 *
 * Scopes ל-Google Calendar: רק כאן (CLINIC_QUEUE_DEFAULT_*) או GOOGLE_CALENDAR_SCOPES ב-wp-config – לא בדף ההגדרות.
 */

if (!defined('CLINIC_QUEUE_DEFAULT_API_ENDPOINT')) {
    define('CLINIC_QUEUE_DEFAULT_API_ENDPOINT', 'https://do-proxy-staging.doctor-clinix.com');
}

if (!defined('CLINIC_QUEUE_DEFAULT_API_TOKEN')) {
    define('CLINIC_QUEUE_DEFAULT_API_TOKEN', '');
}

if (!defined('CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_ID')) {
    define('CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_ID', '');
}

if (!defined('CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_SECRET')) {
    define('CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_SECRET', '');
}

if (!defined('CLINIC_QUEUE_DEFAULT_GOOGLE_CALENDAR_SCOPES')) {
    define(
        'CLINIC_QUEUE_DEFAULT_GOOGLE_CALENDAR_SCOPES',
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly https://www.googleapis.com/auth/calendar.events'
    );
}

class Clinic_Queue_Constants {

    /**
     * Get Hebrew month names map (English month => Hebrew month)
     * @return array
     */
    public static function get_hebrew_months() {
        return array(
            'January' => 'ינואר',
            'February' => 'פברואר',
            'March' => 'מרץ',
            'April' => 'אפריל',
            'May' => 'מאי',
            'June' => 'יוני',
            'July' => 'יולי',
            'August' => 'אוגוסט',
            'September' => 'ספטמבר',
            'October' => 'אוקטובר',
            'November' => 'נובמבר',
            'December' => 'דצמבר'
        );
    }

    /**
     * Get Hebrew day abbreviations (English day => Hebrew abbrev)
     * @return array
     */
    public static function get_hebrew_day_abbrev() {
        return array(
            'Sunday' => "א'",
            'Monday' => "ב'",
            'Tuesday' => "ג'",
            'Wednesday' => "ד'",
            'Thursday' => "ה'",
            'Friday' => "ו'",
            'Saturday' => "ש'",
        );
    }
}


