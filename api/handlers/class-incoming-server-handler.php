<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Incoming Server Handler
 * מטפל ב-webhooks נכנסים מהשרת החיצוני (פרוקסי)
 *
 * Endpoints:
 * - POST /wp-json/clinic-management/schedulers/invalid-credentials — מסמן יומן כשגיאת הרשאות
 *
 * @package ClinicQueue
 * @subpackage API\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Incoming_Server_Handler extends Clinic_Queue_Base_Handler {

    /**
     * REST API namespace — דורס את clinic-queue/v1 של מחלקת הבסיס
     *
     * @var string
     */
    protected $namespace = 'clinic-management';

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Register routes
     * רישום נקודת קצה POST /schedulers/invalid-credentials
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/schedulers/invalid-credentials', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_invalid_credentials'),
            'permission_callback' => array($this, 'permission_callback_server'),
        ));
    }

    /**
     * Permission callback — אימות Bearer token מה-Authorization header
     *
     * מצפה ל-header: Authorization: Bearer <token>
     * משווה מול הטוקן השמור דרך דף ההגדרות בוורדפרס אדמין.
     * משתמש ב-hash_equals() להשוואה בזמן קבוע (מניעת timing attacks).
     *
     * @param WP_REST_Request $request The request object.
     * @return true|WP_Error true אם הטוקן תקין, WP_Error אחרת.
     */
    public function permission_callback_server($request) {
        $expected_token = Clinic_Queue_Plugin_Settings_Service::get_instance()->get_proxy_webhook_token();

        if ($expected_token === '') {
            return new WP_Error(
                'token_not_configured',
                'Webhook token is not configured on this server.',
                array('status' => 503)
            );
        }

        $auth_header = $request->get_header('Authorization');

        if (empty($auth_header) || strpos($auth_header, 'Bearer ') !== 0) {
            return new WP_Error(
                'unauthorized',
                'Missing or malformed Authorization header.',
                array('status' => 401)
            );
        }

        $provided_token = substr($auth_header, strlen('Bearer '));

        if (!hash_equals($expected_token, $provided_token)) {
            return new WP_Error(
                'unauthorized',
                'Invalid token.',
                array('status' => 401)
            );
        }

        return true;
    }

    /**
     * Handle invalid credentials webhook
     *
     * מקבל התראה מהפרוקסי על פרטי התחברות שפקעו/בוטלו,
     * מאתר את כל היומנים המשויכים ל-source_credentials_id ומעדכן את סטטוס הפרוקסי שלהם.
     *
     * גוף הבקשה (JSON):
     * {
     *   "SourceCredentialsID": 123,
     *   "SourceType":          "Google",
     *   "SourceUserID":        "doctor_username",
     *   "Timestamp":           "2026-06-30T10:00:00Z"
     * }
     *
     * SourceCredentialsID — מזהה credentials בפרוקסי (int), תואם ל-meta source_credentials_id.
     *
     * עדכון meta לכל יומן שנמצא:
     * - scheduler_status_in_proxy → 'error'
     * - google_connected          → '' (falsy)
     *
     * תגובת הצלחה:
     * {
     *   "success": true,
     *   "updated_count": 2,
     *   "scheduler_ids": [456, 789],
     *   "source_credentials_id": 123
     * }
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_invalid_credentials($request) {
        $source_credentials_id = absint($request->get_param('SourceCredentialsID'));

        if ($source_credentials_id <= 0) {
            return $this->error_response(
                'SourceCredentialsID must be a positive integer.',
                400,
                'invalid_param'
            );
        }

        $scheduler_ids = get_posts(array(
            'post_type'      => 'schedules',
            'meta_query'     => array(
                array(
                    'key'     => 'source_credentials_id',
                    'value'   => $source_credentials_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ));

        if (empty($scheduler_ids)) {
            return $this->error_response(
                'No schedules found for source_credentials_id ' . $source_credentials_id . '.',
                404,
                'not_found'
            );
        }

        $updated_ids = array();

        foreach ($scheduler_ids as $scheduler_id) {
            $scheduler_id = (int) $scheduler_id;
            update_post_meta($scheduler_id, 'scheduler_status_in_proxy', 'error');
            update_post_meta($scheduler_id, 'google_connected', '');
            $updated_ids[] = $scheduler_id;
        }

        return $this->success_response(array(
            'success'               => true,
            'updated_count'         => count($updated_ids),
            'scheduler_ids'         => $updated_ids,
            'source_credentials_id' => $source_credentials_id,
        ));
    }
}
