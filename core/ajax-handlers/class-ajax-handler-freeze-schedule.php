<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: Freeze schedule (set isActive=false in proxy, mark as draft).
 *
 * Validates the request, calls the proxy to deactivate the scheduler,
 * updates the WP post status to draft, and updates the scheduler_status_in_proxy meta.
 * The WP post changes are applied regardless of proxy result so local state stays consistent.
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\Ajax_Handlers
 */
class Clinic_Queue_Ajax_Handler_Freeze_Schedule {

    /**
     * Callback for wp_ajax_clinic_queue_freeze_schedule.
     *
     * @return void
     */
    public static function handle() {
        check_ajax_referer('clinic_queue_freeze_schedule', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'יש להתחבר למערכת.'));
            return;
        }

        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;

        if ($schedule_id <= 0) {
            wp_send_json_error(array('message' => 'פרמטר חסר: schedule_id הוא שדה חובה.'));
            return;
        }

        $schedule_post = get_post($schedule_id);

        if (!$schedule_post || 'schedules' !== $schedule_post->post_type) {
            wp_send_json_error(array('message' => 'יומן לא נמצא.'));
            return;
        }

        if (!self::current_user_can_manage_schedule($schedule_post)) {
            wp_send_json_error(array('message' => 'אין הרשאה לבצע פעולה זו.'));
            return;
        }

        if ('publish' !== $schedule_post->post_status) {
            wp_send_json_error(array('message' => 'ניתן להקפיא יומן פעיל בלבד.'));
            return;
        }

        $proxy_response = self::deactivate_scheduler_in_proxy($schedule_id);

        wp_update_post(array(
            'ID'          => $schedule_id,
            'post_status' => 'draft',
        ));
        update_post_meta($schedule_id, 'scheduler_status_in_proxy', 'inactive');

        $proxy_data = null;
        if ($proxy_response && !is_wp_error($proxy_response)) {
            $proxy_data = method_exists($proxy_response, 'to_array')
                ? $proxy_response->to_array()
                : (array) $proxy_response;
        }

        wp_send_json_success(array(
            'message'        => 'היומן הוקפא בהצלחה.',
            'proxy_response' => $proxy_data,
        ));
    }

    /**
     * Verify the current user can manage the given schedule:
     * either has manage_options, or the schedule post belongs to current user,
     * or the current user owns a linked doctor post.
     *
     * @param WP_Post $schedule_post Schedule post object.
     * @return bool
     */
    private static function current_user_can_manage_schedule($schedule_post) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $user_id = get_current_user_id();

        if (absint($schedule_post->post_author) === $user_id) {
            return true;
        }

        $doctor_posts = get_posts(array(
            'post_type'        => 'doctors',
            'author'           => $user_id,
            'post_status'      => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page'   => 1,
            'fields'           => 'ids',
            'suppress_filters' => false,
            'no_found_rows'    => true,
        ));

        return !empty($doctor_posts[0]);
    }

    /**
     * Call the proxy to deactivate the scheduler (isActive = false).
     *
     * Resolves the proxy scheduler ID from the `proxy_schedule_id` post meta
     * (falls back to the WP post ID when not set). Returns null silently if the
     * proxy service classes are unavailable.
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
