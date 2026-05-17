<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: Detach doctor from clinic.
 *
 * Always removes the JetEngine relation between a clinic and a doctor (rel_id 201).
 * Additionally, when the schedule post's post_status is 'publish' (active), calls
 * the proxy to deactivate the scheduler, sets the post to draft, and updates the
 * scheduler_status_in_proxy meta. When the schedule is not active (or schedule_id
 * is 0), only the relation removal is performed.
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\Ajax_Handlers
 */
class Clinic_Queue_Ajax_Handler_Detach_Doctor_From_Clinic {

    /**
     * Callback for wp_ajax_clinic_queue_detach_doctor_from_clinic.
     *
     * @return void
     */
    public static function handle() {
        check_ajax_referer('clinic_queue_detach_doctor_from_clinic', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'יש להתחבר למערכת.'));
            return;
        }

        $clinic_id   = isset($_POST['clinic_id'])   ? absint($_POST['clinic_id'])   : 0;
        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        $doctor_id   = isset($_POST['doctor_id'])   ? absint($_POST['doctor_id'])   : 0;

        if ($clinic_id <= 0) {
            wp_send_json_error(array('message' => 'פרמטר חסר: clinic_id הוא שדה חובה.'));
            return;
        }

        if ($doctor_id <= 0) {
            $doctor_id = self::resolve_doctor_id_for_current_user();
        }

        if ($doctor_id <= 0) {
            wp_send_json_error(array('message' => 'לא נמצא פרופיל רופא עבור המשתמש הנוכחי.'));
            return;
        }

        if (!self::current_user_owns_doctor($doctor_id)) {
            wp_send_json_error(array('message' => 'אין הרשאה לבצע פעולה זו.'));
            return;
        }

        $schedule_post = $schedule_id > 0 ? get_post($schedule_id) : null;
        $is_active     = $schedule_post
                         && 'schedules' === $schedule_post->post_type
                         && 'publish'   === $schedule_post->post_status;

        $proxy_data = null;
        if ($is_active) {
            $proxy_response = self::deactivate_scheduler_in_proxy($schedule_id);
            wp_update_post(array(
                'ID'          => $schedule_id,
                'post_status' => 'draft',
            ));
            update_post_meta($schedule_id, 'scheduler_status_in_proxy', 'inactive');

            if ($proxy_response && !is_wp_error($proxy_response)) {
                $proxy_data = method_exists($proxy_response, 'to_array')
                    ? $proxy_response->to_array()
                    : (array) $proxy_response;
            }
        }

        self::remove_clinic_doctor_relation($clinic_id, $doctor_id);

        wp_send_json_success(array(
            'message'        => 'ההתנתקות מהמרפאה בוצעה בהצלחה.',
            'proxy_response' => $proxy_data,
        ));
    }

    /**
     * Resolve doctor post ID for the currently logged-in user.
     *
     * @return int Doctor post ID, or 0 if not found.
     */
    private static function resolve_doctor_id_for_current_user() {
        $user_id      = get_current_user_id();
        $doctor_posts = get_posts(array(
            'post_type'        => 'doctors',
            'author'           => $user_id,
            'post_status'      => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page'   => 1,
            'orderby'          => 'modified',
            'order'            => 'DESC',
            'fields'           => 'ids',
            'suppress_filters' => false,
            'no_found_rows'    => true,
        ));

        if (!empty($doctor_posts[0])) {
            return absint($doctor_posts[0]);
        }

        foreach (array('linked_doctor_id', 'doctor_post_id') as $meta_key) {
            $maybe = absint(get_user_meta($user_id, sanitize_key($meta_key), true));
            if ($maybe > 0 && 'doctors' === get_post_type($maybe)) {
                return $maybe;
            }
        }

        return 0;
    }

    /**
     * Verify that the current user is the author of the given doctor post,
     * or has admin capabilities.
     *
     * @param int $doctor_id Doctor post ID.
     * @return bool
     */
    private static function current_user_owns_doctor($doctor_id) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $post = get_post($doctor_id);
        if (!$post || 'doctors' !== $post->post_type) {
            return false;
        }

        return absint($post->post_author) === absint(get_current_user_id());
    }

    /**
     * Remove JetEngine relation 201 (clinic parent → doctor child).
     * Uses the JetEngine PHP API when available; falls back to direct DB delete.
     *
     * @param int $clinic_id Clinic post ID (parent).
     * @param int $doctor_id Doctor post ID (child).
     * @return void
     */
    private static function remove_clinic_doctor_relation($clinic_id, $doctor_id) {
        if (
            function_exists('jet_engine')
            && is_object(jet_engine())
            && property_exists(jet_engine(), 'relations')
            && is_object(jet_engine()->relations)
            && method_exists(jet_engine()->relations, 'delete_relation')
        ) {
            jet_engine()->relations->delete_relation(array(
                'rel_id'           => 201,
                'parent_object_id' => $clinic_id,
                'child_object_id'  => $doctor_id,
            ));
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jet_rel_default';
        $wpdb->delete(
            $table,
            array(
                'rel_id'           => 201,
                'parent_object_id' => $clinic_id,
                'child_object_id'  => $doctor_id,
            ),
            array('%d', '%d', '%d')
        );
    }

    /**
     * Call the proxy to deactivate the scheduler (isActive = false).
     *
     * Resolves the proxy scheduler ID from the `proxy_schedule_id` post meta
     * (falls back to the WP post ID when not set). Returns null silently if the
     * proxy service is unavailable or the schedule ID is invalid.
     *
     * @param int $schedule_id WP schedule post ID.
     * @return Clinic_Queue_Base_Response_Model|WP_Error|null
     */
    private static function deactivate_scheduler_in_proxy($schedule_id) {
        if ($schedule_id <= 0) {
            return null;
        }

        $proxy_scheduler_id = absint(get_post_meta($schedule_id, 'proxy_schedule_id', true));
        if ($proxy_scheduler_id <= 0) {
            $proxy_scheduler_id = $schedule_id;
        }

        if (!class_exists('Clinic_Queue_API_Manager')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/class-api-manager.php';
        }
        if (!class_exists('Clinic_Queue_Scheduler_Proxy_Service')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-scheduler-proxy-service.php';
        }

        if (!class_exists('Clinic_Queue_Update_Scheduler_Model')) {
            return null;
        }

        $model = new Clinic_Queue_Update_Scheduler_Model(array(
            'schedulerID' => $proxy_scheduler_id,
            'isActive'    => false,
        ));

        $proxy_service = new Clinic_Queue_Scheduler_Proxy_Service();
        return $proxy_service->update_scheduler($model, $proxy_scheduler_id);
    }
}
