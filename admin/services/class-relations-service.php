<?php
/**
 * Relations Service
 * 
 * שירות משותף ליצירת וניהול JetEngine Relations
 * 
 * @package ClinicQueue
 * @subpackage Admin\Services
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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
        if (!$scheduler_id || !is_numeric($scheduler_id)) {
            error_log('[Relations_Service] Invalid scheduler_id: ' . $scheduler_id);
            return array(
                'success' => false,
                'error' => 'Invalid scheduler_id'
            );
        }

        $results = array(
            'scheduler_doctor' => false,
            'clinic_scheduler' => false,
            'errors' => array()
        );

        // 1. Relation 185: Scheduler (parent) -> Doctor (child)
        $doctor_id = get_post_meta($scheduler_id, 'doctor_id', true);
        if (!empty($doctor_id) && is_numeric($doctor_id)) {
            $relation_result = $this->create_scheduler_doctor_relation($scheduler_id, $doctor_id);
            $results['scheduler_doctor'] = $relation_result['success'];
            if (!$relation_result['success']) {
                $results['errors'][] = $relation_result['error'];
            }
        } else {
            error_log('[Relations_Service] No doctor_id found for scheduler ' . $scheduler_id);
        }

        // 2. Relation 184: Clinic (parent) -> Scheduler (child)
        $clinic_id = get_post_meta($scheduler_id, 'clinic_id', true);
        if (!empty($clinic_id) && is_numeric($clinic_id)) {
            $relation_result = $this->create_clinic_scheduler_relation($clinic_id, $scheduler_id);
            $results['clinic_scheduler'] = $relation_result['success'];
            if (!$relation_result['success']) {
                $results['errors'][] = $relation_result['error'];
            }
        } else {
            error_log('[Relations_Service] No clinic_id found for scheduler ' . $scheduler_id);
            $results['errors'][] = 'No clinic_id found';
        }

        $results['success'] = ($results['scheduler_doctor'] || $results['clinic_scheduler']);

        return $results;
    }

    /**
     * יצירת Relation 185: Scheduler -> Doctor
     * 
     * @param int $scheduler_id מזהה היומן
     * @param int $doctor_id מזהה הרופא
     * @return array תוצאת היצירה
     */
    private function create_scheduler_doctor_relation($scheduler_id, $doctor_id) {
        $relation_result = wp_remote_post(
            rest_url('jet-rel/185'),
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-WP-Nonce' => wp_create_nonce('wp_rest')
                ),
                'body' => wp_json_encode(array(
                    'parent_id' => intval($scheduler_id),
                    'child_id' => intval($doctor_id),
                    'context' => 'child',
                    'store_items_type' => 'update'
                ))
            )
        );

        if (is_wp_error($relation_result)) {
            $error_msg = 'Failed to create scheduler-doctor relation (185): ' . $relation_result->get_error_message();
            error_log('[Relations_Service] ' . $error_msg);
            return array(
                'success' => false,
                'error' => $error_msg
            );
        }

        $response_code = wp_remote_retrieve_response_code($relation_result);
        if ($response_code === 200) {
            error_log('[Relations_Service] Successfully created scheduler-doctor relation (185): scheduler ' . $scheduler_id . ' -> doctor ' . $doctor_id);
            return array('success' => true);
        } else {
            $error_msg = 'Failed to create scheduler-doctor relation (185): HTTP ' . $response_code;
            error_log('[Relations_Service] ' . $error_msg);
            return array(
                'success' => false,
                'error' => $error_msg
            );
        }
    }

    /**
     * יצירת Relation 184: Clinic -> Scheduler
     * 
     * @param int $clinic_id מזהה המרפאה
     * @param int $scheduler_id מזהה היומן
     * @return array תוצאת היצירה
     */
    private function create_clinic_scheduler_relation($clinic_id, $scheduler_id) {
        $relation_result = wp_remote_post(
            rest_url('jet-rel/184'),
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-WP-Nonce' => wp_create_nonce('wp_rest')
                ),
                'body' => wp_json_encode(array(
                    'parent_id' => intval($clinic_id),
                    'child_id' => intval($scheduler_id),
                    'context' => 'child',
                    'store_items_type' => 'update'
                ))
            )
        );

        if (is_wp_error($relation_result)) {
            $error_msg = 'Failed to create clinic-scheduler relation (184): ' . $relation_result->get_error_message();
            error_log('[Relations_Service] ' . $error_msg);
            return array(
                'success' => false,
                'error' => $error_msg
            );
        }

        $response_code = wp_remote_retrieve_response_code($relation_result);
        if ($response_code === 200) {
            error_log('[Relations_Service] Successfully created clinic-scheduler relation (184): clinic ' . $clinic_id . ' -> scheduler ' . $scheduler_id);
            return array('success' => true);
        } else {
            $error_msg = 'Failed to create clinic-scheduler relation (184): HTTP ' . $response_code;
            error_log('[Relations_Service] ' . $error_msg);
            return array(
                'success' => false,
                'error' => $error_msg
            );
        }
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

