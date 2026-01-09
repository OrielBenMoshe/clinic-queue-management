<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only load services if they haven't been loaded yet
// This prevents fatal errors if the file is included multiple times
if (!class_exists('Clinic_Queue_JetEngine_Relations_Service')) {
    require_once __DIR__ . '/class-jetengine-relations-service.php';
}

if (!class_exists('Clinic_Queue_DoctorOnline_API_Service')) {
    require_once __DIR__ . '/class-doctoronline-api-service.php';
}

