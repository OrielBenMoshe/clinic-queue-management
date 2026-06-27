<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * JetEngine Relations Service
 * 
 * שירות מרכזי לכל פעולות JetEngine Relations API
 * מטפל בקריאה, יצירה, וניהול Relations
 * 
 * @package Clinic_Queue_Management
 * @subpackage API\Services
 */
class Clinic_Queue_JetEngine_Relations_Service {

    /** @var int JetEngine relation: Clinic (parent) → Scheduler (child) */
    public const REL_CLINIC_SCHEDULE = 184;

    /** @var int JetEngine relation: Doctor (parent) → Scheduler (child) */
    public const REL_DOCTOR_SCHEDULE = 185;

    private static $instance = null;

    /** @var string Resolved JetEngine relations table name (empty if not found). */
    private $resolved_rel_table = '';
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Queue_JetEngine_Relations_Service
     */
    public static function get_instance() {
        if (self::$instance === null) {
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
     * Get scheduler IDs by clinic ID using JetEngine Relations
     * Uses Relation 184: Clinic (parent) -> Scheduler (child)
     * 
     * @param int $clinic_id The clinic ID
     * @return array Array of scheduler IDs (integers)
     */
    public function get_scheduler_ids_by_clinic($clinic_id) {
        if (empty($clinic_id) || !is_numeric($clinic_id)) {
            return array();
        }

        $clinic_id_int = absint($clinic_id);
        if ($clinic_id_int <= 0) {
            return array();
        }

        // מקורות מהירים: DB (כיוון נכון) + meta על schedules
        $scheduler_ids = array_merge(
            $this->get_scheduler_ids_by_clinic_from_db($clinic_id_int),
            $this->get_scheduler_ids_by_clinic_from_meta($clinic_id_int)
        );

        // אם אין תוצאות — REST /children כ-fallback
        if (empty($scheduler_ids)) {
            $scheduler_ids = $this->get_scheduler_ids_by_clinic_via_rest_children($clinic_id_int);
        }

        return $this->normalize_int_id_list($scheduler_ids);
    }

    /**
     * Relation 184 in DB: clinic parent → schedule child.
     *
     * @param int $clinic_id
     * @return array<int>
     */
    private function get_scheduler_ids_by_clinic_from_db($clinic_id) {
        $table = $this->get_relations_table();
        if ($table === '') {
            return array();
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name validated in get_relations_table().
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT child_object_id FROM {$table} WHERE rel_id = %d AND parent_object_id = %d",
                self::REL_CLINIC_SCHEDULE,
                $clinic_id
            )
        );

        if (!is_array($rows)) {
            return array();
        }

        return array_map('absint', $rows);
    }

    /**
     * Fallback when relation 184 is missing: match schedules by clinic_id post meta.
     *
     * @param int $clinic_id
     * @return array<int>
     */
    private function get_scheduler_ids_by_clinic_from_meta($clinic_id) {
        $query = new WP_Query(
            array(
                'post_type'              => 'schedules',
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    array(
                        'key'     => 'clinic_id',
                        'value'   => $clinic_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                ),
            )
        );

        if (empty($query->posts) || !is_array($query->posts)) {
            return array();
        }

        return array_map('absint', $query->posts);
    }

    /**
     * GET /jet-rel/184/children/{clinic_id} — REST fallback בלבד.
     *
     * @param int $clinic_id
     * @return array<int>
     */
    private function get_scheduler_ids_by_clinic_via_rest_children($clinic_id) {
        $endpoint_url = rest_url('jet-rel/' . self::REL_CLINIC_SCHEDULE . '/children/' . $clinic_id);
        $parsed_url   = parse_url($endpoint_url);
        $internal_url = site_url() . ($parsed_url['path'] ?? '');

        $response = wp_remote_get(
            $internal_url,
            array(
                'headers'   => array('X-WP-Nonce' => wp_create_nonce('wp_rest')),
                'timeout'   => 15,
                'sslverify' => false,
                'cookies'   => $_COOKIE,
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return $this->extract_child_object_ids_from_rest_payload($data);
    }
    
    /**
     * Get scheduler IDs by doctor ID using JetEngine Relations
     * Uses Relation 185: Doctor (parent) → Scheduler (child)
     *
     * Merges DB (correct direction) with meta fallback; REST/legacy only when both empty.
     *
     * @param int $doctor_id The doctor ID
     * @return array Array of scheduler IDs (integers)
     */
    public function get_scheduler_ids_by_doctor($doctor_id) {
        if (empty($doctor_id) || !is_numeric($doctor_id)) {
            return array();
        }

        $doctor_id_int = absint($doctor_id);
        if ($doctor_id_int <= 0) {
            return array();
        }

        // מקורות מהירים: DB (כיוון נכון) + meta על schedules
        $scheduler_ids = array_merge(
            $this->get_scheduler_ids_by_doctor_from_db($doctor_id_int),
            $this->get_scheduler_ids_by_doctor_from_meta($doctor_id_int)
        );

        // אם אין תוצאות — REST /children ושורות legacy הפוכות (ללא dump מלא של כל קשר 185)
        if (empty($scheduler_ids)) {
            $scheduler_ids = array_merge(
                $this->get_scheduler_ids_by_doctor_via_rest_children($doctor_id_int),
                $this->get_scheduler_ids_by_doctor_legacy_inverted_from_db($doctor_id_int)
            );
        }

        return $this->normalize_int_id_list($scheduler_ids);
    }

    /**
     * Relation 185 in DB: doctor parent → schedule child.
     *
     * @param int $doctor_id
     * @return array<int>
     */
    private function get_scheduler_ids_by_doctor_from_db($doctor_id) {
        $table = $this->get_relations_table();
        if ($table === '') {
            return array();
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name validated in get_relations_table().
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT child_object_id FROM {$table} WHERE rel_id = %d AND parent_object_id = %d",
                self::REL_DOCTOR_SCHEDULE,
                $doctor_id
            )
        );

        if (!is_array($rows)) {
            return array();
        }

        return array_map('absint', $rows);
    }

    /**
     * GET /jet-rel/185/children/{doctor_id} — same pattern as clinic relation 184.
     *
     * @param int $doctor_id
     * @return array<int>
     */
    private function get_scheduler_ids_by_doctor_via_rest_children($doctor_id) {
        $endpoint_url = rest_url('jet-rel/' . self::REL_DOCTOR_SCHEDULE . '/children/' . $doctor_id);
        $parsed_url   = parse_url($endpoint_url);
        $internal_url = site_url() . ($parsed_url['path'] ?? '');

        $response = wp_remote_get(
            $internal_url,
            array(
                'headers'   => array('X-WP-Nonce' => wp_create_nonce('wp_rest')),
                'timeout'   => 15,
                'sslverify' => false,
                'cookies'   => $_COOKIE,
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return $this->extract_child_object_ids_from_rest_payload($data);
    }

    /**
     * Legacy: scheduler parent / doctor child (DB בלבד).
     *
     * @param int $doctor_id
     * @return array<int>
     */
    private function get_scheduler_ids_by_doctor_legacy_inverted_from_db($doctor_id) {
        $table = $this->get_relations_table();
        if ($table === '') {
            return array();
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name validated in get_relations_table().
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT parent_object_id FROM {$table} WHERE rel_id = %d AND child_object_id = %d",
                self::REL_DOCTOR_SCHEDULE,
                $doctor_id
            )
        );

        if (!is_array($rows)) {
            return array();
        }

        return array_map('absint', $rows);
    }

    /**
     * Fallback when relation 185 is missing: match schedules by post meta.
     *
     * @param int $doctor_id
     * @return array<int>
     */
    private function get_scheduler_ids_by_doctor_from_meta($doctor_id) {
        $query = new WP_Query(
            array(
                'post_type'              => 'schedules',
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    array(
                        'key'     => 'doctor_id',
                        'value'   => $doctor_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ),
                ),
            )
        );

        if (empty($query->posts) || !is_array($query->posts)) {
            return array();
        }

        return array_map('absint', $query->posts);
    }

    /**
     * Ensure relation 185 exists for every published schedule that has doctor_id meta.
     * Safe to run multiple times — creates missing links only (does not delete rows).
     *
     * @param int|null $doctor_id Optional: limit repair to one doctor CPT.
     * @return array{processed: int, created: int, skipped: int, errors: array<int, string>}
     */
    public function reconcile_doctor_schedule_relations($doctor_id = null) {
        $result = array(
            'processed' => 0,
            'created'   => 0,
            'skipped'   => 0,
            'errors'    => array(),
        );

        $meta_query = array(
            array(
                'key'     => 'doctor_id',
                'compare' => 'EXISTS',
            ),
        );

        if ($doctor_id !== null && is_numeric($doctor_id) && absint($doctor_id) > 0) {
            $meta_query = array(
                array(
                    'key'     => 'doctor_id',
                    'value'   => absint($doctor_id),
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            );
        }

        $schedule_ids = get_posts(
            array(
                'post_type'      => 'schedules',
                'post_status'    => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => $meta_query,
            )
        );

        if (empty($schedule_ids) || !is_array($schedule_ids)) {
            return $result;
        }

        $existing_by_doctor = array();

        foreach ($schedule_ids as $schedule_id) {
            $result['processed']++;
            $schedule_id = absint($schedule_id);
            $meta_doctor = absint(get_post_meta($schedule_id, 'doctor_id', true));

            if ($meta_doctor <= 0 || get_post_type($meta_doctor) !== 'doctors') {
                $result['skipped']++;
                continue;
            }

            if (!isset($existing_by_doctor[$meta_doctor])) {
                $existing_by_doctor[$meta_doctor] = $this->get_scheduler_ids_by_doctor_from_db($meta_doctor);
            }

            if (in_array($schedule_id, $existing_by_doctor[$meta_doctor], true)) {
                $result['skipped']++;
                continue;
            }

            $create_result = $this->create_scheduler_doctor_relation($schedule_id, $meta_doctor);
            if (!empty($create_result['success'])) {
                $result['created']++;
                $existing_by_doctor[$meta_doctor][] = $schedule_id;
            } else {
                $result['errors'][$schedule_id] = isset($create_result['error'])
                    ? (string) $create_result['error']
                    : 'Unknown error';
            }
        }

        return $result;
    }

    /**
     * @param mixed $data REST /children response body.
     * @return array<int>
     */
    private function extract_child_object_ids_from_rest_payload($data) {
        $ids = array();

        if (!is_array($data)) {
            return $ids;
        }

        foreach ($data as $item) {
            if (is_array($item) && isset($item['child_object_id'])) {
                $ids[] = absint($item['child_object_id']);
            } elseif (is_numeric($item)) {
                $ids[] = absint($item);
            }
        }

        return $ids;
    }

    /**
     * @param array<int|string> $ids
     * @return array<int>
     */
    private function normalize_int_id_list($ids) {
        return array_values(
            array_unique(
                array_filter(
                    array_map('absint', $ids),
                    static function ($id) {
                        return $id > 0;
                    }
                )
            )
        );
    }

    /**
     * Resolve JetEngine relations table (e.g. wp_jet_rel_default).
     *
     * @return string
     */
    private function get_relations_table() {
        if ($this->resolved_rel_table !== '') {
            return $this->resolved_rel_table;
        }

        global $wpdb;

        $candidate = $wpdb->prefix . 'jet_rel_default';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.ShowTables
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $candidate));

        $this->resolved_rel_table = ($found === $candidate) ? $candidate : '';

        return $this->resolved_rel_table;
    }
    
    /**
     * Get doctor IDs by clinic ID using JetEngine Relations
     * Uses Relation 201: Clinic (parent) -> Doctor (child)
     *
     * @param int $clinic_id The clinic ID
     * @return array Array of doctor IDs (integers)
     */
    public function get_doctor_ids_by_clinic($clinic_id) {
        if (empty($clinic_id) || !is_numeric($clinic_id)) {
            return array();
        }
        $clinic_id_int = intval($clinic_id);
        $relation_id = 201;
        $endpoint_url = rest_url("jet-rel/{$relation_id}/");
        $site_url = site_url();
        $parsed_url = parse_url($endpoint_url);
        $internal_url = $site_url . $parsed_url['path'];
        
        $response = wp_remote_get(
            $internal_url,
            array(
                'headers' => array('X-WP-Nonce' => wp_create_nonce('wp_rest')),
                'timeout' => 15,
                'sslverify' => false,
                'cookies' => $_COOKIE,
            )
        );
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $relation_data = json_decode($body, true);
        $doctor_ids = array();
        
        if (is_array($relation_data)) {
            $clinic_key_str = strval($clinic_id_int);
            $clinic_key_int = $clinic_id_int;
            $children_array = null;
            if (isset($relation_data[$clinic_key_str])) {
                $children_array = $relation_data[$clinic_key_str];
            } elseif (isset($relation_data[$clinic_key_int])) {
                $children_array = $relation_data[$clinic_key_int];
            }
            if (is_array($children_array)) {
                foreach ($children_array as $item) {
                    if (is_array($item) && isset($item['child_object_id'])) {
                        $doctor_ids[] = intval($item['child_object_id']);
                    } elseif (is_numeric($item)) {
                        $doctor_ids[] = intval($item);
                    }
                }
            }
        }
        return array_unique(array_filter($doctor_ids));
    }
    
    /**
     * Get full doctor data by clinic ID (IDs via Relation 201, then fetch posts)
     *
     * @param int $clinic_id The clinic ID
     * @return array Array of doctor post data (REST format or fallback)
     */
    public function get_doctors_by_clinic($clinic_id) {
        $doctor_ids = $this->get_doctor_ids_by_clinic($clinic_id);
        if (empty($doctor_ids)) {
            return array();
        }
        
        $site_url = site_url();
        $doctors_url = rest_url('wp/v2/doctors/');
        $doctors_url = add_query_arg(array(
            'include' => implode(',', $doctor_ids),
            'per_page' => 100,
            '_embed' => '1',
        ), $doctors_url);
        $parsed = parse_url($doctors_url);
        $internal_doctors_url = $site_url . $parsed['path'] . (!empty($parsed['query']) ? '?' . $parsed['query'] : '');
        
        $doctors_response = wp_remote_get(
            $internal_doctors_url,
            array(
                'headers' => array('X-WP-Nonce' => wp_create_nonce('wp_rest')),
                'timeout' => 15,
                'sslverify' => false,
                'cookies' => $_COOKIE,
            )
        );
        
        if (!is_wp_error($doctors_response) && wp_remote_retrieve_response_code($doctors_response) === 200) {
            $doctors_body = wp_remote_retrieve_body($doctors_response);
            $doctors = json_decode($doctors_body, true);
            if (is_array($doctors)) {
                return $doctors;
            }
        }
        
        $doctors = array();
        foreach ($doctor_ids as $doctor_id) {
            $doctor = get_post($doctor_id);
            if ($doctor && $doctor->post_type === 'doctors' && $doctor->post_status === 'publish') {
                $doctors[] = array(
                    'id' => $doctor->ID,
                    'title' => array('rendered' => $doctor->post_title),
                    'name' => $doctor->post_title,
                );
            }
        }
        return $doctors;
    }
    
    /**
     * Get doctor IDs that already have an active scheduler for a specific clinic.
     * Uses Relation 184 (Clinic->Scheduler) to get scheduler IDs,
     * then reads the 'doctor_id' meta field from each scheduler.
     *
     * @param int $clinic_id The clinic ID
     * @return array Array of doctor IDs (integers) that have schedulers for this clinic
     */
    public function get_booked_doctor_ids_for_clinic($clinic_id) {
        if (empty($clinic_id) || !is_numeric($clinic_id)) {
            return array();
        }

        $scheduler_ids = $this->get_scheduler_ids_by_clinic(intval($clinic_id));
        if (empty($scheduler_ids)) {
            return array();
        }

        $booked_doctor_ids = array();
        foreach ($scheduler_ids as $scheduler_id) {
            $doctor_id = get_post_meta(intval($scheduler_id), 'doctor_id', true);
            if (!empty($doctor_id) && is_numeric($doctor_id)) {
                $booked_doctor_ids[] = intval($doctor_id);
            }
        }

        return array_values(array_unique(array_filter($booked_doctor_ids)));
    }

    /**
     * Create Relation 185: Doctor (parent) → Scheduler (child)
     *
     * @param int $scheduler_id מזהה היומן
     * @param int $doctor_id מזהה הרופא
     * @return array תוצאת היצירה
     */
    public function create_scheduler_doctor_relation($scheduler_id, $doctor_id) {
        if (empty($scheduler_id) || !is_numeric($scheduler_id) || empty($doctor_id) || !is_numeric($doctor_id)) {
            return array(
                'success' => false,
                'error' => 'Invalid scheduler_id or doctor_id'
            );
        }

        $endpoint_url = rest_url('jet-rel/' . self::REL_DOCTOR_SCHEDULE);
        $parsed_url   = parse_url($endpoint_url);
        $internal_url = site_url() . ($parsed_url['path'] ?? '');

        $relation_result = wp_remote_post(
            $internal_url,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-WP-Nonce' => wp_create_nonce('wp_rest')
                ),
                'body' => wp_json_encode(array(
                    'parent_id' => intval($doctor_id),
                    'child_id' => intval($scheduler_id),
                    'context' => 'child',
                    'store_items_type' => 'update'
                )),
                'timeout' => 15,
                'sslverify' => false, // For internal calls on same server
                'cookies' => $_COOKIE // Pass current user's cookies for authentication
            )
        );
        
        if (is_wp_error($relation_result)) {
            return array(
                'success' => false,
                'error' => 'Failed to create scheduler-doctor relation (185): ' . $relation_result->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($relation_result);
        if ($response_code === 200) {
            return array('success' => true);
        } else {
            $response_body = wp_remote_retrieve_body($relation_result);
            return array(
                'success' => false,
                'error' => 'Failed to create scheduler-doctor relation (185): HTTP ' . $response_code,
                'response_body' => $response_body
            );
        }
    }
    
    /**
     * Create Relation 184: Clinic (parent) -> Scheduler (child)
     * 
     * @param int $clinic_id מזהה המרפאה
     * @param int $scheduler_id מזהה היומן
     * @return array תוצאת היצירה
     */
    public function create_clinic_scheduler_relation($clinic_id, $scheduler_id) {
        if (empty($clinic_id) || !is_numeric($clinic_id) || empty($scheduler_id) || !is_numeric($scheduler_id)) {
            return array(
                'success' => false,
                'error' => 'Invalid clinic_id or scheduler_id'
            );
        }
        
        // Use site_url for internal server-side calls (same as get_scheduler_ids_by_clinic)
        $endpoint_url = rest_url('jet-rel/184');
        $site_url = site_url();
        $parsed_url = parse_url($endpoint_url);
        $internal_url = $site_url . $parsed_url['path'];
        
        $relation_result = wp_remote_post(
            $internal_url,
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
                )),
                'timeout' => 15,
                'sslverify' => false, // For internal calls on same server
                'cookies' => $_COOKIE // Pass current user's cookies for authentication
            )
        );
        
        if (is_wp_error($relation_result)) {
            return array(
                'success' => false,
                'error' => 'Failed to create clinic-scheduler relation (184): ' . $relation_result->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($relation_result);
        if ($response_code === 200) {
            return array('success' => true);
        } else {
            $response_body = wp_remote_retrieve_body($relation_result);
            return array(
                'success' => false,
                'error' => 'Failed to create clinic-scheduler relation (184): HTTP ' . $response_code,
                'response_body' => $response_body
            );
        }
    }
    
    /**
     * Create all relations for a scheduler
     * Creates both Relation 185 (Scheduler -> Doctor) and Relation 184 (Clinic -> Scheduler)
     * 
     * @param int $scheduler_id מזהה היומן
     * @return array מערך עם תוצאות היצירה
     */
    public function create_scheduler_relations($scheduler_id) {
        if (empty($scheduler_id) || !is_numeric($scheduler_id)) {
            return array(
                'success' => false,
                'error' => 'Invalid scheduler_id'
            );
        }
        
        $results = array(
            'scheduler_doctor' => false,
            'clinic_scheduler' => false,
            'errors' => array(),
            'warnings' => array()
        );
        
        // 1. Relation 185: Doctor (parent) → Scheduler (child)
        $doctor_id = get_post_meta($scheduler_id, 'doctor_id', true);
        if (!empty($doctor_id) && is_numeric($doctor_id)) {
            $relation_result = $this->create_scheduler_doctor_relation($scheduler_id, $doctor_id);
            $results['scheduler_doctor'] = $relation_result['success'];
            if (!$relation_result['success']) {
                $error_msg = isset($relation_result['error']) ? $relation_result['error'] : 'Unknown error';
                $results['errors'][] = 'Relation 185 (Scheduler->Doctor): ' . $error_msg;
                if (isset($relation_result['response_body'])) {
                    $results['errors'][] = 'Response: ' . $relation_result['response_body'];
                }
            }
        } else {
            $results['warnings'][] = 'No doctor_id found for scheduler (Relation 185 skipped)';
        }
        
        // 2. Relation 184: Clinic (parent) -> Scheduler (child)
        $clinic_id = get_post_meta($scheduler_id, 'clinic_id', true);
        if (!empty($clinic_id) && is_numeric($clinic_id)) {
            $relation_result = $this->create_clinic_scheduler_relation($clinic_id, $scheduler_id);
            $results['clinic_scheduler'] = $relation_result['success'];
            if (!$relation_result['success']) {
                $error_msg = isset($relation_result['error']) ? $relation_result['error'] : 'Unknown error';
                $results['errors'][] = 'Relation 184 (Clinic->Scheduler): ' . $error_msg;
                if (isset($relation_result['response_body'])) {
                    $results['errors'][] = 'Response: ' . $relation_result['response_body'];
                }
            }
        } else {
            $results['errors'][] = 'No clinic_id found for scheduler (Relation 184 is required)';
        }
        
        // Success if at least one relation was created successfully
        // Note: clinic_scheduler is more critical than scheduler_doctor
        $results['success'] = ($results['clinic_scheduler'] === true);
        
        return $results;
    }

    /**
     * Remove JetEngine relations 184 (clinic→schedule) and 185 (doctor→schedule)
     * where the given schedule is the child object.
     *
     * @param int $schedule_id מזהה פוסט schedules.
     * @return array{removed_clinic: int, removed_doctor: int}
     */
    public function remove_schedule_relations($schedule_id) {
        $schedule_id = absint($schedule_id);
        $result      = array(
            'removed_clinic' => 0,
            'removed_doctor' => 0,
        );

        if ($schedule_id <= 0) {
            return $result;
        }

        $table = $this->get_relations_table();
        if ('' === $table) {
            return $result;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name validated in get_relations_table().
        $removed_clinic = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE rel_id = %d AND child_object_id = %d",
                self::REL_CLINIC_SCHEDULE,
                $schedule_id
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name validated in get_relations_table().
        $removed_doctor = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE rel_id = %d AND child_object_id = %d",
                self::REL_DOCTOR_SCHEDULE,
                $schedule_id
            )
        );

        $result['removed_clinic'] = (false !== $removed_clinic) ? (int) $removed_clinic : 0;
        $result['removed_doctor'] = (false !== $removed_doctor) ? (int) $removed_doctor : 0;

        return $result;
    }
}

