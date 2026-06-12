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

        if (!Clinic_Queue_Ajax_Handler_Schedule_Helpers::current_user_can_manage_schedule($schedule_post)) {
            wp_send_json_error(array('message' => 'אין הרשאה לבצע פעולה זו.'));
            return;
        }

        if ('publish' !== $schedule_post->post_status) {
            wp_send_json_error(array('message' => 'ניתן להקפיא יומן פעיל בלבד.'));
            return;
        }

        $proxy_response = Clinic_Queue_Ajax_Handler_Schedule_Helpers::deactivate_scheduler_in_proxy($schedule_id);

        wp_update_post(array(
            'ID'          => $schedule_id,
            'post_status' => 'draft',
        ));
        update_post_meta($schedule_id, 'scheduler_status_in_proxy', 'inactive');

        wp_send_json_success(array(
            'message'        => 'היומן הוקפא בהצלחה.',
            'proxy_response' => Clinic_Queue_Ajax_Handler_Schedule_Helpers::normalize_proxy_response($proxy_response),
        ));
    }
}
