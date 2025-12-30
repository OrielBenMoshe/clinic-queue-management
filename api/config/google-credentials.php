<?php
/**
 * Google Calendar API Credentials
 * קובץ זה מכיל את ה-credentials לגישה ל-Google Calendar API
 * 
 * ⚠️ חשוב: קובץ זה לא צריך להיות ב-git!
 * 
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Google OAuth 2.0 Credentials
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', '1045904741168-2p08qtmqjm65vj45huic24d40hs5khaa.apps.googleusercontent.com');
}

if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', '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');
}

// Google API Scopes
// We need calendar access + user email/profile info
if (!defined('GOOGLE_CALENDAR_SCOPES')) {
    define('GOOGLE_CALENDAR_SCOPES', 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile');
}

/**
 * הערה: משתמשים ב-Google Identity Services (GIS) עם ux_mode: 'popup'
 * לכן לא נדרש redirect_uri - הספרייה מטפלת בזה אוטומטית
 */

