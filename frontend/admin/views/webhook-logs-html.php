<?php
/**
 * Webhook Logs Page View
 * תצוגת עמוד לוג webhooks נכנסים
 *
 * @package ClinicQueue
 * @subpackage Admin\Views
 * @since 2.0.0
 *
 * Variables available:
 * @var array $logs         List of webhook log entries
 * @var bool  $logs_cleared Whether logs were just cleared
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$logs = isset($logs) && is_array($logs) ? $logs : array();
$logs_count = count($logs);
$logs_cleared = !empty($logs_cleared);

$auth_labels = array(
    'success'              => __('תקין', 'clinic-queue'),
    'unauthorized'         => __('לא מורשה', 'clinic-queue'),
    'token_not_configured' => __('טוקן לא מוגדר', 'clinic-queue'),
    'missing_header'       => __('חסר Authorization', 'clinic-queue'),
);

$auth_fail_values = array('unauthorized', 'token_not_configured', 'missing_header');

/**
 * Format JSON for display in details block.
 *
 * @param mixed $data Data to encode.
 * @return string
 */
$format_json = function ($data) {
    $encoded = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return is_string($encoded) ? $encoded : '{}';
};

/**
 * CSS class for HTTP status badge.
 *
 * @param int $status HTTP status code.
 * @return string
 */
$status_badge_class = function ($status) {
    $status = (int) $status;
    if ($status >= 200 && $status < 300) {
        return 'clinic-queue-webhook-logs__badge--success';
    }
    if (in_array($status, array(401, 403, 503), true)) {
        return 'clinic-queue-webhook-logs__badge--auth-fail';
    }
    return 'clinic-queue-webhook-logs__badge--error';
};

/**
 * CSS class for auth badge.
 *
 * @param string $auth Auth status key.
 * @return string
 */
$auth_badge_class = function ($auth) use ($auth_fail_values) {
    if ($auth === 'success') {
        return 'clinic-queue-webhook-logs__badge--success';
    }
    if (in_array($auth, $auth_fail_values, true)) {
        return 'clinic-queue-webhook-logs__badge--auth-fail';
    }
    return 'clinic-queue-webhook-logs__badge--error';
};
?>

<div class="wrap clinic-queue-webhook-logs-page">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('לוג Webhooks נכנסים', 'clinic-queue'); ?>
    </h1>

    <form method="post" class="clinic-queue-webhook-logs__clear-form">
        <?php wp_nonce_field('clinic_queue_clear_webhook_logs'); ?>
        <button
            type="submit"
            name="clinic_queue_clear_webhook_logs"
            value="1"
            class="page-title-action clinic-queue-webhook-logs__clear-btn"
            <?php disabled($logs_count === 0); ?>
        >
            <?php esc_html_e('נקה לוגים', 'clinic-queue'); ?>
        </button>
    </form>

    <hr class="wp-header-end">

    <?php if ($logs_cleared) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('הלוגים נוקו בהצלחה.', 'clinic-queue'); ?></p>
        </div>
    <?php endif; ?>

    <div class="clinic-queue-webhook-logs__card">
        <p class="clinic-queue-webhook-logs__count">
            <?php
            printf(
                esc_html__('מציג %1$d לוגים אחרונים (מקסימום %2$d)', 'clinic-queue'),
                (int) $logs_count,
                (int) Clinic_Queue_Helpers::WEBHOOK_LOGS_MAX
            );
            ?>
        </p>

        <?php if ($logs_count === 0) : ?>
            <div class="clinic-queue-webhook-logs__empty">
                <p><?php esc_html_e('אין עדיין רשומות לוג. בקשות webhook יופיעו כאן לאחר קבלתן.', 'clinic-queue'); ?></p>
            </div>
        <?php else : ?>
            <div class="clinic-queue-webhook-logs__table-wrap">
                <table class="clinic-queue-webhook-logs__table widefat striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('תאריך', 'clinic-queue'); ?></th>
                            <th scope="col"><?php esc_html_e('סטטוס', 'clinic-queue'); ?></th>
                            <th scope="col"><?php esc_html_e('אימות', 'clinic-queue'); ?></th>
                            <th scope="col"><?php esc_html_e('IP', 'clinic-queue'); ?></th>
                            <th scope="col"><?php esc_html_e('Endpoint', 'clinic-queue'); ?></th>
                            <th scope="col"><?php esc_html_e('פרטים', 'clinic-queue'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) : ?>
                            <?php
                            $log_time = isset($log['time']) ? (string) $log['time'] : '';
                            $log_status = isset($log['status']) ? (int) $log['status'] : 0;
                            $log_auth = isset($log['auth']) ? sanitize_key($log['auth']) : '';
                            $log_ip = isset($log['ip']) ? (string) $log['ip'] : '—';
                            $log_endpoint = isset($log['endpoint']) ? (string) $log['endpoint'] : '';
                            $log_body = isset($log['body']) && is_array($log['body']) ? $log['body'] : array();
                            $log_response = isset($log['response']) && is_array($log['response']) ? $log['response'] : array();
                            $auth_label = isset($auth_labels[$log_auth]) ? $auth_labels[$log_auth] : $log_auth;
                            ?>
                            <tr>
                                <td class="clinic-queue-webhook-logs__cell-time">
                                    <?php echo esc_html($log_time); ?>
                                </td>
                                <td>
                                    <span class="clinic-queue-webhook-logs__badge <?php echo esc_attr($status_badge_class($log_status)); ?>">
                                        <?php echo esc_html((string) $log_status); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="clinic-queue-webhook-logs__badge <?php echo esc_attr($auth_badge_class($log_auth)); ?>">
                                        <?php echo esc_html($auth_label); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log_ip); ?></td>
                                <td class="clinic-queue-webhook-logs__cell-endpoint">
                                    <code><?php echo esc_html($log_endpoint); ?></code>
                                </td>
                                <td>
                                    <details class="clinic-queue-webhook-logs__details">
                                        <summary><?php esc_html_e('הצג JSON', 'clinic-queue'); ?></summary>
                                        <div class="clinic-queue-webhook-logs__json-block">
                                            <strong><?php esc_html_e('Body', 'clinic-queue'); ?></strong>
                                            <pre><?php echo esc_html($format_json($log_body)); ?></pre>
                                        </div>
                                        <div class="clinic-queue-webhook-logs__json-block">
                                            <strong><?php esc_html_e('Response', 'clinic-queue'); ?></strong>
                                            <pre><?php echo esc_html($format_json($log_response)); ?></pre>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
