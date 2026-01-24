<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once __DIR__ . '/class-base-handler.php';

/**
 * Relations Handler
 * מטפל בכל endpoints הקשורים ל-JetEngine Relations
 * 
 * Endpoints:
 * - GET /relations/clinic/{clinic_id}/doctors - קבלת רופאים לפי מרפאה
 * 
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Relations_Handler extends Clinic_Queue_Base_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Register routes
     * רישום נקודות קצה API
     * 
     * @return void
     */
    public function register_routes() {
        // GET /relations/clinic/{clinic_id}/doctors
        register_rest_route($this->namespace, '/relations/clinic/(?P<clinic_id>\d+)/doctors', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_doctors_by_clinic'),
            'permission_callback' => '__return_true', // Public endpoint - anyone can read doctors list
            'args' => array(
                'clinic_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
            )
        ));
    }
    
    /**
     * Get doctors by clinic ID using JetEngine Relations
     * Uses Jet Relations API internally: GET /wp-json/jet-rel/201/
     * 
     * Relation 201: Clinic (parent) -> Doctor (child)
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_doctors_by_clinic($request) {
        $clinic_id = $this->get_int_param($request, 'clinic_id');
        
        if (empty($clinic_id)) {
            return $this->error_response(
                'Clinic ID is required',
                400,
                'missing_clinic_id'
            );
        }
        
        // Use Jet Relations API: GET /wp-json/jet-rel/201/
        // Relation 201: Clinic (parent) -> Doctor (child)
        $relation_id = 201;
        $endpoint_url = rest_url("jet-rel/{$relation_id}/");
        
        // For internal server-side calls, use site_url
        $site_url = site_url();
        $parsed_url = parse_url($endpoint_url);
        $internal_url = $site_url . $parsed_url['path'];
        
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
            return $this->error_response(
                'Failed to fetch doctors from Jet Relations API: ' . $response->get_error_message(),
                500,
                'jet_rel_error'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            return $this->error_response(
                "Jet Relations API returned status {$response_code}",
                $response_code,
                'jet_rel_error',
                array('body' => $error_body)
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $relation_data = json_decode($body, true);
        
        // Extract doctor IDs from relation data
        // Format: {"1249": [{"child_object_id": "3421"}, {"child_object_id": "744"}], "1267": [...]}
        $doctor_ids = array();
        
        if (is_array($relation_data)) {
            $clinic_key_str = strval($clinic_id);
            $clinic_key_int = intval($clinic_id);
            
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
        
        // Remove duplicates and filter invalid IDs
        $doctor_ids = array_unique(array_filter($doctor_ids));
        
        if (empty($doctor_ids)) {
            return new WP_REST_Response(array(), 200);
        }
        
        // Fetch full doctor details from WP REST API
        $doctors_url = rest_url('wp/v2/doctors/');
        $doctors_url = add_query_arg(array(
            'include' => implode(',', $doctor_ids),
            'per_page' => 100,
            '_embed' => '1'
        ), $doctors_url);
        
        // Make internal request to get full doctor data
        $parsed_doctors_url = parse_url($doctors_url);
        $internal_doctors_url = $site_url . $parsed_doctors_url['path'] . (!empty($parsed_doctors_url['query']) ? '?' . $parsed_doctors_url['query'] : '');
        
        $doctors_response = wp_remote_get(
            $internal_doctors_url,
            array(
                'headers' => array(
                    'X-WP-Nonce' => wp_create_nonce('wp_rest')
                ),
                'timeout' => 15,
                'sslverify' => false,
                'cookies' => $_COOKIE
            )
        );
        
        if (!is_wp_error($doctors_response)) {
            $doctors_response_code = wp_remote_retrieve_response_code($doctors_response);
            if ($doctors_response_code === 200) {
                $doctors_body = wp_remote_retrieve_body($doctors_response);
                $doctors = json_decode($doctors_body, true);
                
                if (is_array($doctors)) {
                    return new WP_REST_Response($doctors, 200);
                }
            }
        }
        
        // Fallback: return basic doctor info
        $doctors = array();
        foreach ($doctor_ids as $doctor_id) {
            $doctor = get_post($doctor_id);
            if ($doctor && $doctor->post_type === 'doctors' && $doctor->post_status === 'publish') {
                $doctors[] = array(
                    'id' => $doctor->ID,
                    'title' => array('rendered' => $doctor->post_title),
                    'name' => $doctor->post_title
                );
            }
        }
        
        return new WP_REST_Response($doctors, 200);
    }
}
