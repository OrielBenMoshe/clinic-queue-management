<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler for Clinic Queue Management (Elementor Widget)
 */
class Clinic_Queue_Ajax_Handler {
    
    public function __construct() {
        // Only register AJAX handlers for Elementor widget
        add_action('wp_ajax_get_clinic_appointments', array($this, 'ajax_get_appointments'));
        add_action('wp_ajax_nopriv_get_clinic_appointments', array($this, 'ajax_get_appointments'));
    }
    
    
    /**
     * AJAX handler for getting appointments
     */
    public function ajax_get_appointments() {
        check_ajax_referer('clinic_queue_nonce', 'nonce');
        
        $doctor_id = intval($_POST['doctor_id']);
        $clinic_id = sanitize_text_field($_POST['clinic_id']);
        
        // Load data from JSON file
        $json_file = CLINIC_QUEUE_MANAGEMENT_PATH . 'data/mock-data.json';
        
        if (!file_exists($json_file)) {
            wp_send_json_error('Data file not found');
            return;
        }

        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);

        if (!$data || !isset($data['doctors'])) {
            wp_send_json_error('Invalid data format');
            return;
        }

        // Find doctor
        $doctor = null;
        foreach ($data['doctors'] as $doc) {
            if ($doc['id'] == $doctor_id) {
                $doctor = $doc;
                break;
            }
        }

        if (!$doctor) {
            wp_send_json_error('Doctor not found');
            return;
        }

        // Find clinic
        $clinic = null;
        if ($clinic_id) {
            foreach ($doctor['clinics'] as $cl) {
                if ($cl['id'] == $clinic_id) {
                    $clinic = $cl;
                    break;
                }
            }
        } else {
            // If no clinic specified, use first clinic
            $clinic = $doctor['clinics'][0] ?? null;
        }

        if (!$clinic) {
            wp_send_json_error('Clinic not found');
            return;
        }

        // Convert appointments to the expected format
        $appointments = array(
            'timezone' => 'Asia/Jerusalem',
            'doctor' => array(
                'id' => $doctor['id'],
                'name' => $doctor['name'],
                'specialty' => $doctor['specialty']
            ),
            'clinic' => array(
                'id' => $clinic['id'],
                'name' => $clinic['name'],
                'address' => $clinic['address']
            ),
            'clinics' => array_map(function($cl) {
                return array(
                    'id' => $cl['id'],
                    'name' => $cl['name']
                );
            }, $doctor['clinics']),
            'days' => array()
        );

        // Convert appointments data
        foreach ($clinic['appointments'] as $date => $slots) {
            $formatted_slots = array();
            foreach ($slots as $slot) {
                $formatted_slots[] = array(
                    'time' => $slot['time'],
                    'id' => $date . 'T' . $slot['time'],
                    'booked' => $slot['booked']
                );
            }
            
            $appointments['days'][] = array(
                'date' => $date,
                'slots' => $formatted_slots
            );
        }

        wp_send_json_success($appointments);
    }
    
}

// Initialize the AJAX handler
new Clinic_Queue_Ajax_Handler();