<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler: Delete schedule.
 *
 * Permanently deletes a `schedules` post owned by the current user
 * (admins with manage_options may delete any schedule).
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

        $is_owner = absint($post->post_author) === absint(get_current_user_id());
        if (!$is_owner && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'אין הרשאה לבצע פעולה זו.'));
            return;
        }

        $deleted = wp_delete_post($schedule_id, true);
        if (!$deleted) {
            wp_send_json_error(array('message' => 'מחיקת היומן נכשלה. אנא נסה שוב.'));
            return;
        }

        wp_send_json_success(array('message' => 'היומן נמחק בהצלחה.'));
    }
}
