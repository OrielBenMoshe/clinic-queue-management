<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook Logs Handler
 * מטפל בעמוד תצוגת לוג webhooks נכנסים
 *
 * @package ClinicQueue
 * @subpackage Admin\Handlers
 * @since 2.0.0
 */
class Clinic_Queue_Webhook_Logs_Handler {

    /**
     * @var Clinic_Queue_Webhook_Logs_Handler|null
     */
    private static $instance = null;

    /**
     * @return Clinic_Queue_Webhook_Logs_Handler
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
    }

    /**
     * Render webhook logs admin page.
     *
     * @return void
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('אין לך הרשאות מספיקות לגשת לדף זה.', 'clinic-queue'));
        }

        $this->maybe_handle_clear_logs();

        $this->enqueue_assets();

        $logs = Clinic_Queue_Helpers::get_webhook_logs();
        $logs_cleared = isset($_GET['cleared']) && sanitize_key(wp_unslash($_GET['cleared'])) === '1';

        include CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/views/webhook-logs-html.php';
    }

    /**
     * Handle POST request to clear all webhook logs.
     *
     * @return void
     */
    private function maybe_handle_clear_logs() {
        if (!isset($_POST['clinic_queue_clear_webhook_logs'])) {
            return;
        }

        if (!check_admin_referer('clinic_queue_clear_webhook_logs')) {
            wp_die(esc_html__('Security check failed', 'clinic-queue'));
        }

        Clinic_Queue_Helpers::clear_webhook_logs();

        wp_safe_redirect(
            add_query_arg(
                'cleared',
                '1',
                admin_url('admin.php?page=clinic-queue-webhook-logs')
            )
        );
        exit;
    }

    /**
     * Enqueue CSS for webhook logs page.
     *
     * @return void
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'clinic-queue-webhook-logs',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/admin/assets/css/webhook-logs.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
    }
}
