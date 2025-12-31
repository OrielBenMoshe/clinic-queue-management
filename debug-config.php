<?php
/**
 * Debug Configuration File
 * 
 * ערוך את הקובץ הזה כדי לכבות חלקים שונים של התוסף
 * 
 * הוראות:
 * 1. שנה את הערכים מ-false ל-true כדי לכבות חלק
 * 2. שמור את הקובץ
 * 3. רענן את הדף
 * 
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// הגדרות דיבאג - שנה כאן
// ============================================

// CSS - קבצי עיצוב
if (!defined('CLINIC_QUEUE_DISABLE_CSS')) {
    define('CLINIC_QUEUE_DISABLE_CSS', false); // שנה ל-true כדי לכבות
}

// JS - קבצי JavaScript
if (!defined('CLINIC_QUEUE_DISABLE_JS')) {
    define('CLINIC_QUEUE_DISABLE_JS', false); // שנה ל-true כדי לכבות
}

// SHORTCODE - טופס הוספת יומן
if (!defined('CLINIC_QUEUE_DISABLE_SHORTCODE')) {
    define('CLINIC_QUEUE_DISABLE_SHORTCODE', false); // שנה ל-true כדי לכבות
}

// AJAX - מטפלי AJAX
if (!defined('CLINIC_QUEUE_DISABLE_AJAX')) {
    define('CLINIC_QUEUE_DISABLE_AJAX', false); // שנה ל-true כדי לכבות
}

// REST_API - REST API endpoints
if (!defined('CLINIC_QUEUE_DISABLE_REST_API')) {
    define('CLINIC_QUEUE_DISABLE_REST_API', false); // שנה ל-true כדי לכבות
}

// ADMIN_MENU - תפריט ניהול
if (!defined('CLINIC_QUEUE_DISABLE_ADMIN_MENU')) {
    define('CLINIC_QUEUE_DISABLE_ADMIN_MENU', false); // שנה ל-true כדי לכבות
}

// WIDGET - ווידג'ט Elementor
if (!defined('CLINIC_QUEUE_DISABLE_WIDGET')) {
    define('CLINIC_QUEUE_DISABLE_WIDGET', false); // שנה ל-true כדי לכבות
}

// VERSION_CHECK - בדיקות גרסאות
if (!defined('CLINIC_QUEUE_DISABLE_VERSION_CHECK')) {
    define('CLINIC_QUEUE_DISABLE_VERSION_CHECK', false); // שנה ל-true כדי לכבות
}

// ============================================
// תבניות מוכנות לשימוש
// ============================================

/*
// תבנית 1: כבה הכל חוץ מ-CSS
define('CLINIC_QUEUE_DISABLE_SHORTCODE', true);
define('CLINIC_QUEUE_DISABLE_AJAX', true);
define('CLINIC_QUEUE_DISABLE_REST_API', true);
define('CLINIC_QUEUE_DISABLE_ADMIN_MENU', true);
define('CLINIC_QUEUE_DISABLE_WIDGET', true);
define('CLINIC_QUEUE_DISABLE_VERSION_CHECK', true);
define('CLINIC_QUEUE_DISABLE_JS', true);
// CSS נשאר פעיל

// תבנית 2: כבה הכל כולל CSS
define('CLINIC_QUEUE_DISABLE_CSS', true);
// + כל השאר מהתבנית 1

// תבנית 3: כבה רק SHORTCODE
define('CLINIC_QUEUE_DISABLE_SHORTCODE', true);
*/

