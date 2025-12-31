<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ⚠️ TEMPORARY HARDCODED VALUES - TO BE REPLACED WITH SETTINGS PAGE IN FUTURE
 * 
 * These constants are hardcoded for development purposes.
 * TODO: Replace with settings page implementation after core functionality is working.
 */

// API Proxy Server Endpoint
if (!defined('CLINIC_QUEUE_API_ENDPOINT')) {
    define('CLINIC_QUEUE_API_ENDPOINT', 'https://do-proxy-staging.doctor-clinix.com');
}

// API Authentication Token
// TODO: Replace with actual token value
if (!defined('CLINIC_QUEUE_API_TOKEN')) {
    define('CLINIC_QUEUE_API_TOKEN', 'pMtGAAMhbpLg21nFPaUhEr6UJaeUcrrHhTvmzewMkEc7gwTGv2EpGm8Xp7C6wHRutncWp78ceV30Qp3XroYoM9mzQCqvJ3NGnEpp');
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


