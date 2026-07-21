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
     * Generate/refresh doctor connect token and persist URL meta.
     *
     * שני המקרים מפנים לעמוד החיבור (page 5564).
     * כאשר מועבר $url_context (clinic_name / calendar_name / source_scheduler_id)
     * נבנה קישור חתום; אחרת — קישור בסיסי (scheduler_id + token בלבד).
     *
     * @param int   $scheduler_id Scheduler post ID.
     * @param int   $ttl_days     Token expiration in days.
     * @param array $url_context  {
     *     @type string $clinic_name
     *     @type string $calendar_name
     *     @type string $source_scheduler_id
     * }
     * @return array|WP_Error
     */
    public static function generate_connect_request_link($scheduler_id, $ttl_days = 14, $url_context = array()) {
        $scheduler_id = absint($scheduler_id);
        if (empty($scheduler_id) || get_post_type($scheduler_id) !== 'schedules') {
            return new WP_Error('invalid_scheduler', 'Invalid scheduler ID');
        }

        $token = self::generate_token($scheduler_id, $ttl_days);
        if (is_wp_error($token)) {
            return $token;
        }

        update_post_meta($scheduler_id, 'doctor_connect_status', 'pending');

        $url_context = is_array($url_context) ? $url_context : array();
        $has_page_context = isset($url_context['clinic_name'])
            || isset($url_context['calendar_name'])
            || isset($url_context['source_scheduler_id']);

        if ($has_page_context) {
            $connect_url = self::build_doctor_connect_page_url(
                $scheduler_id,
                $token,
                isset($url_context['clinic_name']) ? (string) $url_context['clinic_name'] : '',
                isset($url_context['calendar_name']) ? (string) $url_context['calendar_name'] : '',
                isset($url_context['source_scheduler_id']) ? (string) $url_context['source_scheduler_id'] : ''
            );
        } else {
            $connect_url = self::build_doctor_connect_url($scheduler_id, $token);
        }

        update_post_meta($scheduler_id, 'doctor_connect_url', esc_url_raw($connect_url));

        return array(
            'token'       => $token,
            'connect_url' => $connect_url,
            'expires_at'  => (string) get_post_meta($scheduler_id, 'doctor_connect_token_expires_at', true),
        );
    }

    /**
     * Send doctor connect request email with connect URL.
     * Uses HTML format with Elementor-compatible From headers so the email
     * goes through whatever mailer (SMTP, Elementor, etc.) is configured on the site.
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
        $subject       = sprintf('בקשת סנכרון יומן לגוגל: %s', wp_strip_all_tags($schedule_name));

        // Build From header using site settings — this respects any SMTP/Elementor mail config.
        $from_email = sanitize_email(apply_filters('wp_mail_from', get_option('admin_email')));
        $from_name  = sanitize_text_field(apply_filters('wp_mail_from_name', get_bloginfo('name')));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        );

        $safe_connect_url  = esc_url($connect_url);
        $safe_schedule_name = esc_html(wp_strip_all_tags($schedule_name));

        $message  = '<div dir="rtl" style="font-family:Arial,sans-serif;font-size:15px;color:#222;line-height:1.7;">';
        $message .= '<p>שלום,</p>';
        $message .= '<p>נוצרה עבורך בקשת סנכרון יומן לגוגל.</p>';
        $message .= '<p><strong>שם היומן:</strong> ' . $safe_schedule_name . '</p>';
        $message .= '<p>לחץ על הכפתור כדי לבצע את חיבור היומן:</p>';
        $message .= '<p style="text-align:center;margin:24px 0;">';
        $message .= '<a href="' . $safe_connect_url . '" ';
        $message .= 'style="background-color:#1a73e8;color:#fff;padding:12px 28px;border-radius:6px;';
        $message .= 'text-decoration:none;font-size:15px;font-weight:bold;display:inline-block;">';
        $message .= 'חיבור יומן גוגל</a></p>';
        $message .= '<p style="color:#666;font-size:13px;">הקישור תקף ל-14 יום בלבד.<br>';
        $message .= 'אם הכפתור לא עובד, העתק את הקישור הבא לדפדפן:<br>';
        $message .= '<a href="' . $safe_connect_url . '" style="color:#1a73e8;word-break:break-all;">' . $safe_connect_url . '</a></p>';
        $message .= '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">';
        $message .= '<p style="color:#999;font-size:12px;">הודעה זו נשלחה אוטומטית ממערכת ניהול המרפאות.</p>';
        $message .= '</div>';

        $sent = (bool) wp_mail($recipients, $subject, $message, $headers);
        if (!$sent) {
            return new WP_Error('email_send_failed', 'Failed to send doctor connect request email');
        }

        return array(
            'sent'       => true,
            'recipients' => $recipients,
        );
    }

    /**
     * WordPress page ID for the doctor calendar connect shortcode page.
     */
    const DOCTOR_CONNECT_PAGE_ID = 5564;

    /**
     * Resolve base URL for the doctor-connect WordPress page (ID 5564).
     *
     * @return string
     */
    private static function get_doctor_connect_page_base_url() {
        $page_url = get_permalink(self::DOCTOR_CONNECT_PAGE_ID);
        if (!$page_url) {
            $page_url = home_url('/?page_id=' . self::DOCTOR_CONNECT_PAGE_ID);
        }

        return $page_url;
    }

    /**
     * Build basic doctor connect URL (scheduler_id + token) to page 5564.
     *
     * @param int    $scheduler_id Scheduler post ID.
     * @param string $token        Raw access token.
     * @return string
     */
    public static function build_doctor_connect_url($scheduler_id, $token) {
        $base_url = apply_filters(
            'clinic_queue_doctor_connect_base_url',
            self::get_doctor_connect_page_base_url(),
            $scheduler_id
        );

        return add_query_arg(
            array(
                'scheduler_id' => absint($scheduler_id),
                'token'        => rawurlencode($token),
            ),
            $base_url
        );
    }

    /**
     * Build a signed URL to the doctor-connect page (WP page ID 5564).
     *
     * Working-days are intentionally omitted; the page fetches them via REST.
     * HMAC-SHA256 signature (sig) covers scheduler_id + token.
     *
     * @param int    $scheduler_id        Scheduler post ID.
     * @param string $token               Raw access token.
     * @param string $clinic_name         Human-readable clinic name.
     * @param string $calendar_name       Human-readable calendar / schedule name.
     * @param string $source_scheduler_id External source calendar ID from schedule meta.
     * @return string Signed URL.
     */
    public static function build_doctor_connect_page_url($scheduler_id, $token, $clinic_name = '', $calendar_name = '', $source_scheduler_id = '') {
        $sig = hash_hmac(
            'sha256',
            absint($scheduler_id) . '|' . $token,
            wp_salt('auth')
        );

        return add_query_arg(
            array(
                'scheduler_id'        => absint($scheduler_id),
                'token'               => rawurlencode($token),
                'clinic_name'         => rawurlencode($clinic_name),
                'calendar_name'       => rawurlencode($calendar_name),
                'source_scheduler_id' => rawurlencode((string) $source_scheduler_id),
                'sig'                 => $sig,
            ),
            self::get_doctor_connect_page_base_url()
        );
    }

    /**
     * Resolve recipients for the doctor connect-request email.
     *
     * Priority order (first valid email wins):
     *   1. WordPress user email of the WP user who created the doctor post (post_author).
     *   2. Email stored in the doctor CPT meta fields: 'email', 'doctor_email', 'user_email'.
     *   3. Admin email as last resort.
     *
     * The schedule owner is intentionally excluded — this link is meant for the doctor,
     * not for the person who created the schedule.
     *
     * @param int $scheduler_id Scheduler post ID.
     * @return array Unique, valid email addresses.
     */
    private static function resolve_connect_request_recipients($scheduler_id) {
        $emails    = array();
        $doctor_id = absint(get_post_meta($scheduler_id, 'doctor_id', true));

        if ($doctor_id > 0) {
            // Priority 1: WP user who created the doctor post.
            $doctor_post = get_post($doctor_id);
            if ($doctor_post && (int) $doctor_post->post_author > 0) {
                $doctor_author = get_user_by('id', (int) $doctor_post->post_author);
                if ($doctor_author && !empty($doctor_author->user_email) && is_email($doctor_author->user_email)) {
                    $emails[] = sanitize_email($doctor_author->user_email);
                }
            }

            // Priority 2: email stored in doctor CPT meta fields.
            $doctor_email_keys = array('email', 'doctor_email', 'user_email');
            foreach ($doctor_email_keys as $key) {
                $meta_email = sanitize_email((string) get_post_meta($doctor_id, $key, true));
                if (!empty($meta_email) && is_email($meta_email)) {
                    $emails[] = $meta_email;
                    break;
                }
            }
        }

        // Priority 3: admin email as last resort (no recipients found above).
        if (empty($emails)) {
            $admin_email = sanitize_email((string) get_option('admin_email'));
            if (!empty($admin_email) && is_email($admin_email)) {
                $emails[] = $admin_email;
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
