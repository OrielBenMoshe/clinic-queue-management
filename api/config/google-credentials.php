<?php
/**
 * Google OAuth scopes – נטען מ-plugin-core אחרי constants.php.
 * Client ID / Secret: אדמין או wp-config בלבד.
 *
 * @package ClinicQueue
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('GOOGLE_CALENDAR_SCOPES') && defined('CLINIC_QUEUE_DEFAULT_GOOGLE_CALENDAR_SCOPES')) {
    define('GOOGLE_CALENDAR_SCOPES', CLINIC_QUEUE_DEFAULT_GOOGLE_CALENDAR_SCOPES);
}
