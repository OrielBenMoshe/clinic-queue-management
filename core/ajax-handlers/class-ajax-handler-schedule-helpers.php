<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared helpers for schedule-related AJAX handlers (proxy update, permissions).
 *
 * @package Clinic_Queue_Management
 * @subpackage Core\Ajax_Handlers
 */
class Clinic_Queue_Ajax_Handler_Schedule_Helpers {

    /**
     * Verify the current user can manage the given schedule post.
     *
     * @param WP_Post $schedule_post Schedule post object.
     * @return bool
     */
    public static function current_user_can_manage_schedule($schedule_post) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $user_id = get_current_user_id();

        if (absint($schedule_post->post_author) === $user_id) {
            return true;
        }

        $doctor_posts = get_posts(
            array(
                'post_type'        => 'doctors',
                'author'           => $user_id,
                'post_status'      => array('publish', 'draft', 'pending', 'private'),
                'posts_per_page'   => 1,
                'fields'           => 'ids',
                'suppress_filters' => false,
                'no_found_rows'    => true,
            )
        );

        return !empty($doctor_posts[0]);
    }

    /**
     * Call proxy Scheduler/Update with the given isActive flag.
     *
     * @param int  $schedule_id WP schedule post ID.
     * @param bool $is_active   Active state to send upstream.
     * @return Clinic_Queue_Base_Response_Model|WP_Error|null
     */
    public static function update_scheduler_active_in_proxy($schedule_id, $is_active) {
        $schedule_id = absint($schedule_id);
        if ($schedule_id <= 0) {
            return null;
        }

        $proxy_scheduler_id = absint(get_post_meta($schedule_id, 'proxy_schedule_id', true));
        if ($proxy_scheduler_id <= 0) {
            $proxy_scheduler_id = $schedule_id;
        }

        if (!class_exists('Clinic_Queue_Scheduler_Proxy_Service')) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-scheduler-proxy-service.php';
        }

        if (!class_exists('Clinic_Queue_Update_Scheduler_Model')) {
            return null;
        }

        $model = new Clinic_Queue_Update_Scheduler_Model(
            array(
                'schedulerID' => $proxy_scheduler_id,
                'isActive'    => (bool) $is_active,
            )
        );

        return (new Clinic_Queue_Scheduler_Proxy_Service())
            ->update_scheduler($model, $proxy_scheduler_id);
    }

    /**
     * Deactivate scheduler in proxy (isActive=false).
     *
     * @param int $schedule_id WP schedule post ID.
     * @return Clinic_Queue_Base_Response_Model|WP_Error|null
     */
    public static function deactivate_scheduler_in_proxy($schedule_id) {
        return self::update_scheduler_active_in_proxy($schedule_id, false);
    }

    /**
     * Activate scheduler in proxy (isActive=true).
     *
     * @param int $schedule_id WP schedule post ID.
     * @return Clinic_Queue_Base_Response_Model|WP_Error|null
     */
    public static function activate_scheduler_in_proxy($schedule_id) {
        return self::update_scheduler_active_in_proxy($schedule_id, true);
    }

    /**
     * Normalize proxy response for JSON output.
     *
     * @param mixed $proxy_response Response from update_scheduler_active_in_proxy().
     * @return array<string, mixed>|null
     */
    public static function normalize_proxy_response($proxy_response) {
        if (!$proxy_response || is_wp_error($proxy_response)) {
            return null;
        }

        return method_exists($proxy_response, 'to_array')
            ? $proxy_response->to_array()
            : (array) $proxy_response;
    }
}
