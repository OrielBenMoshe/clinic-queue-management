<?php
/**
 * Booking Form Shortcode Class
 * Main class for [booking_form] shortcode
 * 
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_Booking_Form_Shortcode
 * Manages the booking form shortcode functionality
 */
class Clinic_Booking_Form_Shortcode {
    
    /**
     * Singleton instance
     * 
     * @var Clinic_Booking_Form_Shortcode
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Booking_Form_Shortcode
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->register_shortcode();
        $this->register_ajax_handlers();
    }
    
    /**
     * Register the shortcode
     */
    private function register_shortcode() {
        add_shortcode('booking_form', array($this, 'render_shortcode'));
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_submit_appointment_ajax', array($this, 'handle_appointment_submission_ajax'));
        add_action('wp_ajax_nopriv_submit_appointment_ajax', array($this, 'handle_appointment_submission_ajax'));
        add_action('wp_ajax_refresh_family_list_html', array($this, 'handle_refresh_family_list_html'));
    }
    
    /**
     * Render the shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts = array()) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'popup_id' => '3953', // ID של הפופאפ להוספת בן משפחה
        ), $atts, 'booking_form');
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div style="text-align:center;color:red;">יש להתחבר.</div>';
        }
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Prepare data for view
        $data = $this->prepare_data($atts);
        
        // Start output buffering
        ob_start();
        
        // Include the view
        include __DIR__ . '/views/booking-form-html.php';
        
        // Return buffered content
        return ob_get_clean();
    }
    
    /**
     * Enqueue CSS and JavaScript assets
     */
    private function enqueue_assets() {
        static $assets_loaded = false;
        
        if ($assets_loaded) {
            return;
        }
        
        // Enqueue base.css first for CSS variables
        wp_enqueue_style(
            'clinic-queue-base-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/base.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // Enqueue shared forms.css for common form styles
        wp_enqueue_style(
            'clinic-queue-forms-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/forms.css',
            array('clinic-queue-base-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // Enqueue booking-form specific CSS (depends on base and forms)
        wp_enqueue_style(
            'booking-form-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shortcodes/booking-form.css',
            array('clinic-queue-base-css', 'clinic-queue-forms-css'),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'booking-form-js',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/booking-form/js/booking-form.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('booking-form-js', 'bookingFormData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('save_booking_ajax_nonce'),
            'refreshNonce' => wp_create_nonce('refresh_family_list_nonce'),
        ));
        
        $assets_loaded = true;
    }
    
    /**
     * Prepare data for the view
     * 
     * @param array $atts Shortcode attributes
     * @return array Data for view
     */
    private function prepare_data($atts) {
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $repeater_key = 'family_members';
        $family_members = get_user_meta($user_id, $repeater_key, true);
        
        // Read query parameters from URL (passed from booking calendar)
        $appointment_data = $this->get_appointment_data_from_query();
        
        return array(
            'user_id' => $user_id,
            'current_user' => $current_user,
            'family_members' => $family_members,
            'popup_id' => $atts['popup_id'],
            'appointment_data' => $appointment_data,
        );
    }
    
    /**
     * Get appointment data from URL query parameters
     * 
     * @return array Appointment data or empty array
     */
    private function get_appointment_data_from_query() {
        $data = array();
        
        // Read all relevant query parameters
        $data['date'] = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
        $data['time'] = isset($_GET['time']) ? sanitize_text_field($_GET['time']) : '';
        $data['treatment_type'] = isset($_GET['treatment_type']) ? sanitize_text_field($_GET['treatment_type']) : '';
        $data['doctor_name'] = isset($_GET['doctor_name']) ? sanitize_text_field($_GET['doctor_name']) : '';
        $data['doctor_specialty'] = isset($_GET['doctor_specialty']) ? sanitize_text_field($_GET['doctor_specialty']) : '';
        $data['doctor_thumbnail'] = isset($_GET['doctor_thumbnail']) ? esc_url_raw($_GET['doctor_thumbnail']) : '';
        $data['clinic_address'] = isset($_GET['clinic_address']) ? sanitize_text_field($_GET['clinic_address']) : '';
        $data['clinic_name'] = isset($_GET['clinic_name']) ? sanitize_text_field($_GET['clinic_name']) : '';
        $data['scheduler_id'] = isset($_GET['scheduler_id']) ? intval($_GET['scheduler_id']) : 0;
        $data['proxy_schedule_id'] = isset($_GET['proxy_schedule_id']) ? sanitize_text_field($_GET['proxy_schedule_id']) : '';
        $data['duration'] = isset($_GET['duration']) ? intval($_GET['duration']) : 0;
        $data['from'] = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
        $data['to'] = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : '';
        
        // Try to get clinic_address from post meta if not in query
        if (empty($data['clinic_address']) && !empty($data['scheduler_id'])) {
            $scheduler_post = get_post($data['scheduler_id']);
            if ($scheduler_post) {
                // Try to get clinic_id from scheduler
                $clinic_id = get_post_meta($data['scheduler_id'], 'clinic_id', true);
                if ($clinic_id) {
                    $clinic_address = get_post_meta($clinic_id, 'clinic_address', true);
                    if ($clinic_address) {
                        $data['clinic_address'] = $clinic_address;
                    }
                }
            }
        }
        
        // Try to get doctor data from scheduler if not in query
        if (empty($data['doctor_name']) && !empty($data['scheduler_id'])) {
            $scheduler_post = get_post($data['scheduler_id']);
            if ($scheduler_post) {
                // Try to get doctor_id from scheduler
                $doctor_id = get_post_meta($data['scheduler_id'], 'doctor_id', true);
                if ($doctor_id) {
                    $doctor_post = get_post($doctor_id);
                    if ($doctor_post) {
                        if (empty($data['doctor_name'])) {
                            $data['doctor_name'] = $doctor_post->post_title;
                        }
                        if (empty($data['doctor_specialty'])) {
                            $data['doctor_specialty'] = get_post_meta($doctor_id, 'specialty', true);
                        }
                        if (empty($data['doctor_thumbnail'])) {
                            $thumbnail_id = get_post_thumbnail_id($doctor_id);
                            if ($thumbnail_id) {
                                $data['doctor_thumbnail'] = wp_get_attachment_image_url($thumbnail_id, 'medium');
                            }
                        }
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * AJAX Handler: Submit appointment
     */
    public function handle_appointment_submission_ajax() {
        check_ajax_referer('save_booking_ajax_nonce', 'security');
        
        $cpt_slug = 'appointments';
        $repeater_key = 'family_members';
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(array('message' => 'עליך להתחבר למערכת.'));
            return;
        }
        
        // Collect data
        $selected_patient = sanitize_text_field($_POST['patient_select'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $id_num = sanitize_text_field($_POST['id_number'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $first_visit = sanitize_text_field($_POST['first_visit'] ?? '');
        $appt_date = sanitize_text_field($_POST['appt_date'] ?? '');
        $appt_time = sanitize_text_field($_POST['appt_time'] ?? '');
        
        $current_user = get_userdata($user_id);
        $patient_name = $current_user->display_name;
        
        if (strpos($selected_patient, 'family_') !== false) {
            $index = str_replace('family_', '', $selected_patient);
            $family = get_user_meta($user_id, $repeater_key, true);
            if (isset($family[$index]['first_name'])) {
                $patient_name = $family[$index]['first_name'];
            }
        }
        
        $post_id = wp_insert_post(array(
            'post_title'  => 'תור חדש: ' . $patient_name . ' (' . $appt_date . ')',
            'post_type'   => $cpt_slug,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));
        
        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, 'app_date', $appt_date);
            update_post_meta($post_id, 'app_time', $appt_time);
            update_post_meta($post_id, 'patient_name', $patient_name);
            update_post_meta($post_id, 'patient_phone', $phone);
            update_post_meta($post_id, 'patient_id_num', $id_num);
            update_post_meta($post_id, 'is_first_visit', $first_visit);
            update_post_meta($post_id, 'appointment_notes', $notes);
            update_post_meta($post_id, 'user_account_id', $user_id);
            
            wp_send_json_success(array('message' => 'התור נקבע בהצלחה עבור ' . $patient_name . '!'));
        } else {
            wp_send_json_error(array('message' => 'שגיאה ביצירת התור.'));
        }
        
        wp_die();
    }
    
    /**
     * AJAX Handler: Refresh family list HTML
     */
    public function handle_refresh_family_list_html() {
        // Check user
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error();
            return;
        }
        
        $repeater_key = 'family_members';
        $current_user = wp_get_current_user();
        $family_members = get_user_meta($user_id, $repeater_key, true);
        
        ob_start();
        ?>
        <label class="jet-form-builder__field-wrap">
            <input type="radio" name="patient_select" id="pat_self" value="self" class="jet-form-builder__field radio-field" checked>
            <span class="jet-form-builder__field-label">עבורי - <?php echo esc_html($current_user->display_name); ?></span>
        </label>
        <?php if (!empty($family_members) && is_array($family_members)) : ?>
            <?php foreach ($family_members as $index => $member) : 
                $name = isset($member['first_name']) ? $member['first_name'] : 'בן משפחה';
            ?>
            <label class="jet-form-builder__field-wrap">
                <input type="radio" name="patient_select" id="pat_<?php echo esc_attr($index); ?>" value="family_<?php echo esc_attr($index); ?>" class="jet-form-builder__field radio-field">
                <span class="jet-form-builder__field-label"><?php echo esc_html($name); ?></span>
            </label>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php
        
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
        wp_die();
    }
}
