<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-database-manager.php';

/**
 * Appointment CPT Handler
 * מימוש באמצעות Custom Post Type
 * 
 * @package Clinic_Queue_Management
 */
class Clinic_Queue_Appointment_CPT_Handler implements Clinic_Queue_Appointment_Storage_Interface {
    
    /**
     * Post type name
     * 
     * @var string
     */
    private $post_type = 'appointments';
    
    /**
     * Create new appointment
     * 
     * @param array $data Appointment data
     * @return int|WP_Error Post ID or error
     */
    public function create($data) {
        $post_id = wp_insert_post(array(
            'post_title'  => $data['title'] ?? 'תור חדש',
            'post_type'   => $this->post_type,
            'post_status' => 'publish',
            'post_author' => $data['user_id'] ?? get_current_user_id(),
        ));
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // שמירת meta fields
        $meta_fields = array(
            'app_date', 'app_time', 'patient_name', 'patient_phone',
            'patient_id_num', 'is_first_visit', 'appointment_notes',
            'user_account_id', 'scheduler_id', 'proxy_appointment_id',
            'clinic_id', 'doctor_id', 'treatment_type', 'duration',
            'from_utc', 'to_utc'
        );
        
        foreach ($meta_fields as $field) {
            if (isset($data[$field])) {
                update_post_meta($post_id, $field, $data[$field]);
            }
        }
        
        return $post_id;
    }
    
    /**
     * Get appointment by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function get($id) {
        $post = get_post($id);
        if (!$post || $post->post_type !== $this->post_type) {
            return null;
        }
        
        return array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'app_date' => get_post_meta($id, 'app_date', true),
            'app_time' => get_post_meta($id, 'app_time', true),
            'patient_name' => get_post_meta($id, 'patient_name', true),
            'patient_phone' => get_post_meta($id, 'patient_phone', true),
            'patient_id_num' => get_post_meta($id, 'patient_id_num', true),
            'is_first_visit' => get_post_meta($id, 'is_first_visit', true),
            'appointment_notes' => get_post_meta($id, 'appointment_notes', true),
            'user_account_id' => get_post_meta($id, 'user_account_id', true),
            'scheduler_id' => get_post_meta($id, 'scheduler_id', true),
            'proxy_appointment_id' => get_post_meta($id, 'proxy_appointment_id', true),
            'clinic_id' => get_post_meta($id, 'clinic_id', true),
            'doctor_id' => get_post_meta($id, 'doctor_id', true),
            'treatment_type' => get_post_meta($id, 'treatment_type', true),
            'duration' => get_post_meta($id, 'duration', true),
            'from_utc' => get_post_meta($id, 'from_utc', true),
            'to_utc' => get_post_meta($id, 'to_utc', true),
        );
    }
    
    /**
     * Update appointment
     * 
     * @param int $id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update($id, $data) {
        $update_data = array('ID' => $id);
        
        if (isset($data['title'])) {
            $update_data['post_title'] = $data['title'];
        }
        
        $result = wp_update_post($update_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // עדכון meta fields
        $meta_fields = array(
            'app_date', 'app_time', 'patient_name', 'patient_phone',
            'patient_id_num', 'is_first_visit', 'appointment_notes',
            'user_account_id', 'scheduler_id', 'proxy_appointment_id',
            'clinic_id', 'doctor_id', 'treatment_type', 'duration',
            'from_utc', 'to_utc'
        );
        
        foreach ($meta_fields as $field) {
            if (isset($data[$field])) {
                update_post_meta($id, $field, $data[$field]);
            }
        }
        
        return true;
    }
    
    /**
     * Delete appointment
     * 
     * @param int $id
     * @return bool|WP_Error
     */
    public function delete($id) {
        $result = wp_delete_post($id, true);
        return !is_wp_error($result) && $result !== false;
    }
    
    /**
     * Find appointments by user ID
     * 
     * @param int $user_id
     * @return array
     */
    public function find_by_user($user_id) {
        $query = new WP_Query(array(
            'post_type' => $this->post_type,
            'author' => $user_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        $appointments = array();
        foreach ($query->posts as $post) {
            $appointments[] = $this->get($post->ID);
        }
        
        return $appointments;
    }
}
