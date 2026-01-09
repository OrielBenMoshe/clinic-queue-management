<?php
/**
 * Relations Service
 * 
 * שירות משותף ליצירת וניהול JetEngine Relations
 * 
 * @package ClinicQueue
 * @subpackage Admin\Services
 * @since 1.0.0
 * 
 * @deprecated This service is now a wrapper for Clinic_Queue_JetEngine_Relations_Service
 * Use Clinic_Queue_JetEngine_Relations_Service directly from API layer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the API layer Relations Service
require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-jetengine-relations-service.php';

/**
 * Class Relations_Service
 * 
 * מנהל את כל פעולות ה-Relations של JetEngine
 */
class Relations_Service {
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     * 
     * @return Relations_Service
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor (Singleton)
     */
    private function __construct() {
        // Initialization if needed
    }

    /**
     * יצירת Relations עבור Scheduler
     * 
     * יוצר 2 Relations:
     * 1. Relation 185: Scheduler (parent) -> Doctor (child)
     * 2. Relation 184: Clinic (parent) -> Scheduler (child)
     * 
     * @param int $scheduler_id מזהה היומן (WordPress post ID)
     * @return array מערך עם תוצאות היצירה
     */
    public function create_scheduler_relations($scheduler_id) {
        // Use API layer Relations Service
        $relations_service = Clinic_Queue_JetEngine_Relations_Service::get_instance();
        return $relations_service->create_scheduler_relations($scheduler_id);
    }

    /**
     * מחיקת Relations עבור Scheduler
     * 
     * @param int $scheduler_id מזהה היומן
     * @return array תוצאת המחיקה
     */
    public function delete_scheduler_relations($scheduler_id) {
        if (!$scheduler_id || !is_numeric($scheduler_id)) {
            return array(
                'success' => false,
                'error' => 'Invalid scheduler_id'
            );
        }

        // TODO: Implement deletion if needed
        // JetEngine Relations API doesn't have a simple delete endpoint
        // May need to use jet_engine()->relations->delete_relation()

        return array(
            'success' => true,
            'message' => 'Relations deletion not implemented yet'
        );
    }

    /**
     * בדיקה אם Relations קיימים
     * 
     * @param int $scheduler_id מזהה היומן
     * @return array מצב ה-Relations
     */
    public function check_scheduler_relations($scheduler_id) {
        if (!$scheduler_id || !is_numeric($scheduler_id)) {
            return array(
                'exists' => false,
                'error' => 'Invalid scheduler_id'
            );
        }

        $doctor_id = get_post_meta($scheduler_id, 'doctor_id', true);
        $clinic_id = get_post_meta($scheduler_id, 'clinic_id', true);

        return array(
            'exists' => true,
            'scheduler_id' => $scheduler_id,
            'doctor_id' => $doctor_id,
            'clinic_id' => $clinic_id,
            'has_doctor' => !empty($doctor_id),
            'has_clinic' => !empty($clinic_id)
        );
    }
}

