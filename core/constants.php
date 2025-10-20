<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
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


