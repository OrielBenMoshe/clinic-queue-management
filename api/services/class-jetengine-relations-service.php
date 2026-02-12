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
    
    private static $instance = null;
    
    // Debug information storage (for passing to JavaScript)
    private static $debug_logs = array();
    
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
        // Debug: Log function entry
        add_action('wp_footer', function() use ($clinic_id) {
            echo '<script>console.log("[Relations Service] get_scheduler_ids_by_clinic called with clinic_id:", ' . json_encode($clinic_id) . ');</script>';
        }, 999);
        
        if (empty($clinic_id) || !is_numeric($clinic_id)) {
            return array();
        }
        
        $clinic_id_int = intval($clinic_id);
        $scheduler_ids = array();
        
        // Skip PHP direct method - use REST API directly (proven to work via Postman)
        // Try PHP direct method first (faster, no HTTP overhead)
        $use_php_method = false; // Force REST API for now
        
        if ($use_php_method && function_exists('jet_engine') && is_callable(array(jet_engine(), 'relations'))) {
            $relations = jet_engine()->relations;
            if (is_object($relations) && method_exists($relations, 'get_related_posts')) {
                // Relation 184: Clinic (parent) -> Scheduler (child)
                // To find schedulers for a clinic, we need to find children of the clinic
                try {
                    $related_posts = $relations->get_related_posts(array(
                        'relation_id' => 184,
                        'parent_id' => $clinic_id_int,
                        'context' => 'parent_to_child'
                    ));
                    
                    if (!empty($related_posts) && is_array($related_posts)) {
                        foreach ($related_posts as $item) {
                            $scheduler_id = null;
                            
                            // Handle different return types from get_related_posts()
                            // JetEngine can return: WP_Post objects, post IDs (int), or arrays
                            if (is_numeric($item)) {
                                // Direct ID: 123
                                $scheduler_id = intval($item);
                            } elseif (is_object($item)) {
                                // WP_Post object or similar
                                if (isset($item->ID)) {
                                    $scheduler_id = intval($item->ID);
                                } elseif (isset($item->id)) {
                                    $scheduler_id = intval($item->id);
                                } elseif (method_exists($item, 'get_id')) {
                                    $scheduler_id = intval($item->get_id());
                                }
                            } elseif (is_array($item)) {
                                // Array with ID field - try all possible keys
                                if (isset($item['ID'])) {
                                    $scheduler_id = intval($item['ID']);
                                } elseif (isset($item['id'])) {
                                    $scheduler_id = intval($item['id']);
                                } elseif (isset($item['post_id'])) {
                                    $scheduler_id = intval($item['post_id']);
                                } elseif (isset($item['child_object_id'])) {
                                    $scheduler_id = intval($item['child_object_id']);
                                } elseif (isset($item['child_id'])) {
                                    $scheduler_id = intval($item['child_id']);
                                } elseif (isset($item['parent_object_id'])) {
                                    $scheduler_id = intval($item['parent_object_id']);
                                } elseif (isset($item['parent_id'])) {
                                    $scheduler_id = intval($item['parent_id']);
                                }
                            }
                            
                            // Only add valid positive IDs
                            if ($scheduler_id && $scheduler_id > 0) {
                                $scheduler_ids[] = $scheduler_id;
                            }
                        }
                    }
                    
                    // Ensure all IDs are integers and unique
                    $scheduler_ids = array_unique(array_map('intval', $scheduler_ids));
                    
                    if (!empty($scheduler_ids)) {
                        return $scheduler_ids;
                    }
                } catch (Exception $e) {
                    // If PHP method fails, fall back to REST API
                    // Continue to fallback below
                }
            }
        }
        
        // Use REST API to get children directly (more efficient)
        // Endpoint: GET /wp-json/jet-rel/184/children/{clinic_id}
        // This returns only the schedulers for this specific clinic
        $endpoint_url = rest_url("jet-rel/184/children/{$clinic_id_int}");
        
        // For internal server-side calls, use site_url to ensure proper authentication
        $site_url = site_url();
        $parsed_url = parse_url($endpoint_url);
        $internal_url = $site_url . $parsed_url['path'];
        
        // Debug: Log REST API call
        add_action('wp_footer', function() use ($internal_url, $clinic_id_int) {
            echo '<script>console.log("[Relations Service] Calling REST API for clinic ' . intval($clinic_id_int) . ': ' . esc_js($internal_url) . '");</script>';
        }, 999);
        
        // Use wp_remote_get with site's own URL and current user's cookies
        $response = wp_remote_get(
            $internal_url,
            array(
                'headers' => array(
                    'X-WP-Nonce' => wp_create_nonce('wp_rest')
                ),
                'timeout' => 15,
                'sslverify' => false, // For internal calls on same server
                'cookies' => $_COOKIE // Pass current user's cookies for authentication
            )
        );
        
        if (is_wp_error($response)) {
            add_action('wp_footer', function() use ($response) {
                echo '<script>console.error("[Relations Service] WP_Error:", ' . json_encode($response->get_error_message()) . ');</script>';
            }, 999);
            return array();
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            add_action('wp_footer', function() use ($response_code) {
                echo '<script>console.error("[Relations Service] HTTP Error:", ' . intval($response_code) . ');</script>';
            }, 999);
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Handle response format from /children/{parent_id} endpoint
        // Expected format: [{"child_object_id": "scheduler_id"}, ...]
        $scheduler_ids = array();
        
        if (!is_array($data)) {
            add_action('wp_footer', function() {
                echo '<script>console.warn("[Relations Service] Response is not an array");</script>';
            }, 999);
            return array();
        }
        
        // Process the array of children
        foreach ($data as $item) {
            if (is_array($item) && isset($item['child_object_id'])) {
                $scheduler_ids[] = intval($item['child_object_id']);
            } elseif (is_numeric($item)) {
                $scheduler_ids[] = intval($item);
            }
        }
        
        // Fallback: Check for nested object format (shouldn't happen with /children endpoint)
        if (empty($scheduler_ids) && isset($data[$clinic_id_int])) {
            $children_array = $data[$clinic_id_int];
            if (is_array($children_array)) {
                foreach ($children_array as $item) {
                    if (is_array($item) && isset($item['child_object_id'])) {
                        $scheduler_ids[] = intval($item['child_object_id']);
                    } elseif (is_numeric($item)) {
                        $scheduler_ids[] = intval($item);
                    }
                }
            }
        }
        
        // Additional fallback for other possible formats
        if (empty($scheduler_ids) && isset($data['children']) && is_array($data['children'])) {
            foreach ($data['children'] as $item) {
                if (is_array($item) && isset($item['child_object_id'])) {
                    $scheduler_ids[] = intval($item['child_object_id']);
                } elseif (is_numeric($item)) {
                    $scheduler_ids[] = intval($item);
                }
            }
        }
        
        // Final check: if data is direct array of IDs
        if (empty($scheduler_ids) && isset($data[0])) {
            if (is_numeric($data[0])) {
                // Direct array of IDs: [1, 2, 3]
                $scheduler_ids = array_map('intval', $data);
            } elseif (is_array($data[0])) {
                // Array of objects: [{'child_object_id': 1}, {'child_object_id': 2}]
                foreach ($data as $item) {
                    if (is_array($item)) {
                        // Try different possible keys (most common: child_object_id)
                        if (isset($item['child_object_id'])) {
                            $scheduler_ids[] = intval($item['child_object_id']);
                        } elseif (isset($item['id'])) {
                            $scheduler_ids[] = intval($item['id']);
                        } elseif (isset($item['child_id'])) {
                            $scheduler_ids[] = intval($item['child_id']);
                        }
                    } elseif (is_numeric($item)) {
                        $scheduler_ids[] = intval($item);
                    }
                }
            }
        } else {
            // Try to extract from any structure (fallback)
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    // Value is an array - could be array of objects or array of IDs
                    foreach ($value as $item) {
                        if (is_array($item) && isset($item['child_object_id'])) {
                            $scheduler_ids[] = intval($item['child_object_id']);
                        } elseif (is_numeric($item)) {
                            $scheduler_ids[] = intval($item);
                        }
                    }
                } elseif (is_numeric($key) && is_numeric($value)) {
                    $scheduler_ids[] = intval($value);
                }
            }
        }
        
        // Ensure all IDs are integers and unique
        $scheduler_ids = array_unique(array_map('intval', $scheduler_ids));
        
        return $scheduler_ids;
    }
    
    /**
     * Get scheduler IDs by doctor ID using JetEngine Relations
     * Uses Relation 185: Scheduler (parent) -> Doctor (child)
     * 
     * @param int $doctor_id The doctor ID
     * @return array Array of scheduler IDs (integers)
     */
    public function get_scheduler_ids_by_doctor($doctor_id) {
        // Debug: Log function entry
        add_action('wp_footer', function() use ($doctor_id) {
            echo '<script>console.log("[Relations Service] get_scheduler_ids_by_doctor called with doctor_id:", ' . json_encode($doctor_id) . ');</script>';
        }, 999);
        
        if (empty($doctor_id) || !is_numeric($doctor_id)) {
            return array();
        }
        
        $doctor_id_int = intval($doctor_id);
        $scheduler_ids = array();
        
        // Try PHP direct method first (faster, no HTTP overhead)
        if (function_exists('jet_engine') && is_callable(array(jet_engine(), 'relations'))) {
            $relations = jet_engine()->relations;
            if (is_object($relations) && method_exists($relations, 'get_related_posts')) {
                // Relation 185: Scheduler (parent) -> Doctor (child)
                // To find schedulers for a doctor, we need to find parents of the doctor
                $related_posts = $relations->get_related_posts(array(
                    'relation_id' => 185,
                    'child_id' => $doctor_id_int,
                    'context' => 'child_to_parent'
                ));
                
                if (!empty($related_posts) && is_array($related_posts)) {
                    foreach ($related_posts as $item) {
                        $scheduler_id = null;
                        
                        // Handle different return types from get_related_posts()
                        // JetEngine can return: WP_Post objects, post IDs (int), or arrays
                        if (is_numeric($item)) {
                            // Direct ID: 123
                            $scheduler_id = intval($item);
                        } elseif (is_object($item)) {
                            // WP_Post object or similar
                            if (isset($item->ID)) {
                                $scheduler_id = intval($item->ID);
                            } elseif (isset($item->id)) {
                                $scheduler_id = intval($item->id);
                            } elseif (method_exists($item, 'get_id')) {
                                $scheduler_id = intval($item->get_id());
                            }
                        } elseif (is_array($item)) {
                            // Array with ID field
                            if (isset($item['ID'])) {
                                $scheduler_id = intval($item['ID']);
                            } elseif (isset($item['id'])) {
                                $scheduler_id = intval($item['id']);
                            } elseif (isset($item['post_id'])) {
                                $scheduler_id = intval($item['post_id']);
                            } elseif (isset($item['parent_object_id'])) {
                                $scheduler_id = intval($item['parent_object_id']);
                            } elseif (isset($item['parent_id'])) {
                                $scheduler_id = intval($item['parent_id']);
                            }
                        }
                        
                        if ($scheduler_id && $scheduler_id > 0) {
                            $scheduler_ids[] = $scheduler_id;
                        }
                    }
                }
                
                // Ensure all IDs are integers and unique
                $scheduler_ids = array_unique(array_map('intval', $scheduler_ids));
                
                if (!empty($scheduler_ids)) {
                    return $scheduler_ids;
                }
            }
        }
        
        // Fallback to REST API if PHP direct method not available
        // Endpoint: /jet-rel/185/ (returns all relations, key = scheduler_id)
        // Note: In relation 185, scheduler is parent and doctor is child
        // So we need to find all schedulers (keys) that have this doctor_id in their children
        $endpoint_url = rest_url('jet-rel/185/');
        
        // For internal server-side calls, use site_url to ensure proper authentication
        $site_url = site_url();
        $parsed_url = parse_url($endpoint_url);
        $internal_url = $site_url . $parsed_url['path'];
        
        // Debug: Log REST API call
        add_action('wp_footer', function() use ($internal_url) {
            echo '<script>console.log("[Relations Service] About to call REST API: ' . esc_js($internal_url) . '");</script>';
        }, 999);
        
        // Use wp_remote_get with site's own URL and current user's cookies
        $response = wp_remote_get(
            $internal_url,
            array(
                'headers' => array(
                    'X-WP-Nonce' => wp_create_nonce('wp_rest')
                ),
                'timeout' => 15,
                'sslverify' => false, // For internal calls on same server
                'cookies' => $_COOKIE // Pass current user's cookies for authentication
            )
        );
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Handle response format from Postman:
        // Format: {"4214": [{"child_object_id": "3421"}]}
        // Key = scheduler_id (parent), Value = array of objects with child_object_id = doctor_id
        // We need to find all keys (scheduler_ids) where child_object_id = doctor_id
        if (!is_array($data)) {
            return array();
        }
        
        // Iterate through all schedulers and find those that have this doctor as child
        foreach ($data as $scheduler_id_str => $children_array) {
            if (!is_array($children_array)) {
                continue;
            }
            
            // Check if this scheduler has the doctor_id in its children
            foreach ($children_array as $item) {
                if (is_array($item) && isset($item['child_object_id'])) {
                    $child_doctor_id = intval($item['child_object_id']);
                    if ($child_doctor_id === $doctor_id_int) {
                        // This scheduler has the doctor as child, so add scheduler_id
                        $scheduler_ids[] = intval($scheduler_id_str);
                        break; // Found it, no need to check other children
                    }
                }
            }
        }
        
        // Ensure all IDs are integers and unique
        $scheduler_ids = array_unique(array_map('intval', $scheduler_ids));
        
        return $scheduler_ids;
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
     * Create Relation 185: Scheduler (parent) -> Doctor (child)
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
        
        // Use site_url for internal server-side calls (same as get_scheduler_ids_by_clinic)
        $endpoint_url = rest_url('jet-rel/185');
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
                    'parent_id' => intval($scheduler_id),
                    'child_id' => intval($doctor_id),
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
        
        // 1. Relation 185: Scheduler (parent) -> Doctor (child)
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
}

