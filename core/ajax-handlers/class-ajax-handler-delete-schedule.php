<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: Delete schedule.
 *
 * Before deleting the WP post:
 * 1. Calls proxy Scheduler/Update with isActive=false.
 * 2. Removes JetEngine relations 184 (clinic→schedule) and 185 (doctor→schedule).
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\Ajax_Handlers
 */
class Clinic_Queue_Ajax_Handler_Delete_Schedule {

    /**
     * Callback for wp_ajax_clinic_queue_delete_schedule.
     *
     * @return void
     */
    public static function handle() {
        check_ajax_referer('clinic_queue_delete_schedule', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'יש להתחבר למערכת.'));
            return;
        }

        $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
        if ($schedule_id <= 0) {
            wp_send_json_error(array('message' => 'פרמטר חסר: schedule_id הוא שדה חובה.'));
            return;
        }

        $post = get_post($schedule_id);
        if (!$post || 'schedules' !== $post->post_type) {
            wp_send_json_error(array('message' => 'היומן המבוקש לא נמצא.'));
            return;
        }

        if (!Clinic_Queue_Ajax_Handler_Schedule_Helpers::current_user_can_manage_schedule($post)) {
            wp_send_json_error(array('message' => 'אין הרשאה לבצע פעולה זו.'));
            return;
        }

        $proxy_response = Clinic_Queue_Ajax_Handler_Schedule_Helpers::deactivate_scheduler_in_proxy($schedule_id);

        require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-jetengine-relations-service.php';
        $relations_removed = Clinic_Queue_JetEngine_Relations_Service::get_instance()
            ->remove_schedule_relations($schedule_id);

        $deleted = wp_delete_post($schedule_id, true);
        if (!$deleted) {
            wp_send_json_error(array('message' => 'מחיקת היומן נכשלה. אנא נסה שוב.'));
            return;
        }

        wp_send_json_success(
            array(
                'message'           => 'היומן נמחק בהצלחה.',
                'proxy_response'    => Clinic_Queue_Ajax_Handler_Schedule_Helpers::normalize_proxy_response($proxy_response),
                'relations_removed' => $relations_removed,
            )
        );
    }
}
