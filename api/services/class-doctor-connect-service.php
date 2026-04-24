<?php
/**
 * Doctor Connect Service
 *
 * Handles scheduler access token generation/validation and doctor rejection email notifications.
 *
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clinic_Queue_Doctor_Connect_Service {

    /**
     * Generate a scheduler access token and persist its hash/expiration.
     *
     * @param int $scheduler_id Scheduler post ID.
     * @param int $ttl_days     Token expiration in days.
     * @return string|WP_Error Raw token string on success.
     */
    public static function generate_token($scheduler_id, $ttl_days = 14) {
        $scheduler_id = absint($scheduler_id);
        if (empty($scheduler_id) || get_post_type($scheduler_id) !== 'schedules') {
            return new WP_Error('invalid_scheduler', 'Invalid scheduler ID');
        }

        $token = wp_generate_password(48, false, false);
        $token_hash = wp_hash_password($token);
        $expires_at = gmdate('Y-m-d H:i:s', time() + (DAY_IN_SECONDS * absint($ttl_days)));

        update_post_meta($scheduler_id, 'doctor_connect_token_hash', $token_hash);
        update_post_meta($scheduler_id, 'doctor_connect_token_expires_at', $expires_at);

        return $token;
    }

    /**
     * Validate raw token against scheduler stored hash and expiration.
     *
     * @param int    $scheduler_id Scheduler post ID.
     * @param string $token        Raw token from URL/request.
     * @return bool
     */
    public static function validate_token($scheduler_id, $token) {
        $scheduler_id = absint($scheduler_id);
        $token = sanitize_text_field((string) $token);

        if (empty($scheduler_id) || empty($token) || get_post_type($scheduler_id) !== 'schedules') {
            return false;
        }

        $token_hash = (string) get_post_meta($scheduler_id, 'doctor_connect_token_hash', true);
        $expires_at = (string) get_post_meta($scheduler_id, 'doctor_connect_token_expires_at', true);

        if (empty($token_hash) || empty($expires_at)) {
            return false;
        }

        $expires_timestamp = strtotime($expires_at . ' UTC');
        if (!$expires_timestamp || $expires_timestamp < time()) {
            return false;
        }

        return wp_check_password($token, $token_hash);
    }

    /**
     * Revoke scheduler access token.
     *
     * @param int $scheduler_id Scheduler post ID.
     * @return void
     */
    public static function revoke_token($scheduler_id) {
        $scheduler_id = absint($scheduler_id);
        if (empty($scheduler_id)) {
            return;
        }

        delete_post_meta($scheduler_id, 'doctor_connect_token_hash');
        delete_post_meta($scheduler_id, 'doctor_connect_token_expires_at');
    }

    /**
     * Send rejection email to schedule owner and clinic owner (if available).
     *
     * @param int         $scheduler_id Scheduler post ID.
     * @param string|null $reason       Optional reason entered by doctor.
     * @return bool
     */
    public static function send_rejection_email($scheduler_id, $reason = null) {
        $scheduler_id = absint($scheduler_id);
        if (empty($scheduler_id) || get_post_type($scheduler_id) !== 'schedules') {
            return false;
        }

        $scheduler_post = get_post($scheduler_id);
        if (!$scheduler_post) {
            return false;
        }

        $recipient_emails = array();

        $schedule_owner = get_user_by('id', (int) $scheduler_post->post_author);
        if ($schedule_owner && !empty($schedule_owner->user_email)) {
            $recipient_emails[] = $schedule_owner->user_email;
        }

        $clinic_id = absint(get_post_meta($scheduler_id, 'clinic_id', true));
        if ($clinic_id > 0) {
            $clinic_post = get_post($clinic_id);
            if ($clinic_post) {
                $clinic_owner = get_user_by('id', (int) $clinic_post->post_author);
                if ($clinic_owner && !empty($clinic_owner->user_email)) {
                    $recipient_emails[] = $clinic_owner->user_email;
                }
            }
        }

        $recipient_emails = array_values(array_unique(array_filter($recipient_emails)));
        if (empty($recipient_emails)) {
            $admin_email = get_option('admin_email');
            if (!empty($admin_email)) {
                $recipient_emails[] = $admin_email;
            }
        }

        if (empty($recipient_emails)) {
            return false;
        }

        $schedule_name = get_post_meta($scheduler_id, 'schedule_name', true);
        if (empty($schedule_name)) {
            $schedule_name = get_post_meta($scheduler_id, 'manual_calendar_name', true);
        }
        if (empty($schedule_name)) {
            $schedule_name = $scheduler_post->post_title;
        }

        $subject = sprintf('דחיית חיבור יומן רופא: %s', wp_strip_all_tags($schedule_name));
        $message = "שלום,\n\n";
        $message .= "בקשת חיבור יומן עבור הרופא נדחתה.\n";
        $message .= "מזהה יומן: {$scheduler_id}\n";
        $message .= "שם יומן: " . wp_strip_all_tags($schedule_name) . "\n";

        if (!empty($reason)) {
            $message .= "סיבת דחייה: " . sanitize_text_field($reason) . "\n";
        }

        $message .= "\nהודעה זו נשלחה אוטומטית ממערכת ניהול המרפאות.";

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        return (bool) wp_mail($recipient_emails, $subject, $message, $headers);
    }

    /**
     * Generate/refresh doctor connect URL and persist related meta.
     *
     * @param int $scheduler_id Scheduler post ID.
     * @param int $ttl_days     Token expiration in days.
     * @return array|WP_Error
     */
    public static function generate_connect_request_link($scheduler_id, $ttl_days = 14) {
        $scheduler_id = absint($scheduler_id);
        if (empty($scheduler_id) || get_post_type($scheduler_id) !== 'schedules') {
            return new WP_Error('invalid_scheduler', 'Invalid scheduler ID');
        }

        $token = self::generate_token($scheduler_id, $ttl_days);
        if (is_wp_error($token)) {
            return $token;
        }

        update_post_meta($scheduler_id, 'doctor_connect_status', 'pending');
        $connect_url = self::build_doctor_connect_url($scheduler_id, $token);
        update_post_meta($scheduler_id, 'doctor_connect_url', esc_url_raw($connect_url));

        return array(
            'connect_url' => $connect_url,
            'expires_at' => (string) get_post_meta($scheduler_id, 'doctor_connect_token_expires_at', true),
        );
    }

    /**
     * Send doctor connect request email with connect URL.
     *
     * @param int    $scheduler_id Scheduler post ID.
     * @param string $connect_url  Prepared connect URL.
     * @return array|WP_Error
     */
    public static function send_connect_request_email($scheduler_id, $connect_url) {
        $scheduler_id = absint($scheduler_id);
        if (empty($scheduler_id) || get_post_type($scheduler_id) !== 'schedules') {
            return new WP_Error('invalid_scheduler', 'Invalid scheduler ID');
        }

        $recipients = self::resolve_connect_request_recipients($scheduler_id);
        if (empty($recipients)) {
            return new WP_Error('missing_recipient', 'No email recipients found for this scheduler');
        }

        $schedule_name = self::resolve_scheduler_display_name($scheduler_id);
        $subject = sprintf('בקשת סנכרון יומן לגוגל: %s', wp_strip_all_tags($schedule_name));

        $message = "שלום,\n\n";
        $message .= "נוצרה עבורך בקשת סנכרון יומן לגוגל.\n";
        $message .= "שם יומן: " . wp_strip_all_tags($schedule_name) . "\n";
        $message .= "לינק לביצוע החיבור:\n{$connect_url}\n\n";
        $message .= "הלינק תקף לזמן מוגבל.\n";
        $message .= "\nהודעה זו נשלחה אוטומטית ממערכת ניהול המרפאות.";

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $sent = (bool) wp_mail($recipients, $subject, $message, $headers);
        if (!$sent) {
            return new WP_Error('email_send_failed', 'Failed to send doctor connect request email');
        }

        return array(
            'sent' => true,
            'recipients' => $recipients,
        );
    }

    /**
     * Build doctor connect URL.
     *
     * @param int    $scheduler_id Scheduler post ID.
     * @param string $token        Raw access token.
     * @return string
     */
    public static function build_doctor_connect_url($scheduler_id, $token) {
        $base_url = apply_filters('clinic_queue_doctor_connect_base_url', home_url('/doctor-calendar-connect/'), $scheduler_id);

        return add_query_arg(
            array(
                'scheduler_id' => absint($scheduler_id),
                'token' => rawurlencode($token),
            ),
            $base_url
        );
    }

    /**
     * Resolve recipients for connect request email.
     * Priority: doctor meta email -> doctor post author email -> schedule owner.
     *
     * @param int $scheduler_id Scheduler post ID.
     * @return array
     */
    private static function resolve_connect_request_recipients($scheduler_id) {
        $emails = array();
        $doctor_id = absint(get_post_meta($scheduler_id, 'doctor_id', true));

        if ($doctor_id > 0) {
            $doctor_email_keys = array('email', 'doctor_email', 'user_email');
            foreach ($doctor_email_keys as $key) {
                $meta_email = sanitize_email((string) get_post_meta($doctor_id, $key, true));
                if (!empty($meta_email) && is_email($meta_email)) {
                    $emails[] = $meta_email;
                    break;
                }
            }

            $doctor_post = get_post($doctor_id);
            if ($doctor_post) {
                $doctor_author = get_user_by('id', (int) $doctor_post->post_author);
                if ($doctor_author && !empty($doctor_author->user_email)) {
                    $emails[] = $doctor_author->user_email;
                }
            }
        }

        $scheduler_post = get_post($scheduler_id);
        if ($scheduler_post) {
            $schedule_owner = get_user_by('id', (int) $scheduler_post->post_author);
            if ($schedule_owner && !empty($schedule_owner->user_email)) {
                $emails[] = $schedule_owner->user_email;
            }
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Resolve human-readable scheduler name.
     *
     * @param int $scheduler_id Scheduler post ID.
     * @return string
     */
    private static function resolve_scheduler_display_name($scheduler_id) {
        $schedule_name = (string) get_post_meta($scheduler_id, 'schedule_name', true);
        if (!empty($schedule_name)) {
            return $schedule_name;
        }

        $manual_name = (string) get_post_meta($scheduler_id, 'manual_calendar_name', true);
        if (!empty($manual_name)) {
            return $manual_name;
        }

        $post = get_post($scheduler_id);
        return $post ? (string) $post->post_title : 'יומן';
    }
}
