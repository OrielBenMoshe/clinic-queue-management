<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/services/class-encryption-service.php';

/**
 * מקור אמת יחיד לקריאה ושמירה של הגדרות התוסף (API + Google OAuth).
 *
 * עדיפות קריאה: אופציות שנשמרו בדף ההגדרות > קבועים ב-wp-config.php > CLINIC_QUEUE_DEFAULT_* (constants.php).
 * Scopes ל-Google Calendar: לא נשמרים באדמין – רק GOOGLE_CALENDAR_SCOPES (wp-config) או CLINIC_QUEUE_DEFAULT_*.
 *
 * @package ClinicQueue
 * @subpackage Admin\Services
 */
class Clinic_Queue_Plugin_Settings_Service {

    const OPTION_API_ENDPOINT = 'clinic_queue_api_endpoint';
    const OPTION_API_TOKEN_ENCRYPTED = 'clinic_queue_api_token_encrypted';
    const OPTION_GOOGLE_CLIENT_ID = 'clinic_queue_google_client_id';
    const OPTION_GOOGLE_CLIENT_SECRET_ENCRYPTED = 'clinic_queue_google_client_secret_encrypted';
    const OPTION_PROXY_WEBHOOK_TOKEN_ENCRYPTED = 'clinic_queue_proxy_webhook_token_encrypted';

    const TRANSIENT_SAVE_ERROR = 'clinic_queue_settings_save_error';

    /** @var string[] מפתחות שדות מותרים לשמירה/מחיקה מדף ההגדרות */
    const ADMIN_FIELD_KEYS = array(
        'api_token',
        'api_endpoint',
        'google_client_id',
        'google_client_secret',
        'proxy_webhook_token',
    );

    /** @var string[] שדות רגישים – מוצגים כמסכה בלבד לפני עריכה */
    const SENSITIVE_FIELD_KEYS = array(
        'api_token',
        'google_client_secret',
        'proxy_webhook_token',
    );

    /**
     * @var Clinic_Queue_Plugin_Settings_Service|null
     */
    private static $instance = null;

    /**
     * @var Clinic_Queue_Encryption_Service
     */
    private $encryption_service;

    /**
     * @return Clinic_Queue_Plugin_Settings_Service
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->encryption_service = Clinic_Queue_Encryption_Service::get_instance();
        $this->cleanup_obsolete_options();
    }

    /**
     * שומר שדה בודד מדף ההגדרות (ללא תלות בשדות אחרים).
     *
     * @param string $field_key אחד מ-ADMIN_FIELD_KEYS.
     * @param string $value     ערך גולמי מהטופס.
     * @return bool
     */
    public function save_single_field($field_key, $value) {
        if (!in_array($field_key, self::ADMIN_FIELD_KEYS, true)) {
            $this->set_save_error(__('שדה לא חוקי.', 'clinic-queue'));
            return false;
        }

        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            $this->set_save_error(__('יש להזין ערך לפני שמירה.', 'clinic-queue'));
            return false;
        }

        switch ($field_key) {
            case 'api_token':
                return $this->persist_api_token($value);
            case 'api_endpoint':
                return $this->persist_api_endpoint(esc_url_raw($value));
            case 'google_client_id':
                return $this->persist_google_client_id(sanitize_text_field($value));
            case 'google_client_secret':
                return $this->persist_google_client_secret($value);
            case 'proxy_webhook_token':
                return $this->persist_proxy_webhook_token($value);
            default:
                return false;
        }
    }

    /**
     * מוחק ערך שמור של שדה בודד (אופציה במסד).
     *
     * @param string $field_key
     * @return bool
     */
    public function delete_single_field($field_key) {
        if (!in_array($field_key, self::ADMIN_FIELD_KEYS, true)) {
            $this->set_save_error(__('שדה לא חוקי.', 'clinic-queue'));
            return false;
        }

        switch ($field_key) {
            case 'api_token':
                $this->delete_api_token();
                return true;
            case 'api_endpoint':
                delete_option(self::OPTION_API_ENDPOINT);
                return true;
            case 'google_client_id':
                delete_option(self::OPTION_GOOGLE_CLIENT_ID);
                return true;
            case 'google_client_secret':
                $this->delete_google_client_secret();
                return true;
            case 'proxy_webhook_token':
                $this->delete_proxy_webhook_token();
                return true;
            default:
                return false;
        }
    }

    /**
     * @param string $field_key
     * @return bool
     */
    public function is_sensitive_field($field_key) {
        return in_array($field_key, self::SENSITIVE_FIELD_KEYS, true);
    }

    /**
     * האם לשדה יש ערך שמור באופציות התוסף (לא כולל wp-config).
     *
     * @param string $field_key
     * @return bool
     */
    public function field_has_stored_value($field_key) {
        switch ($field_key) {
            case 'api_token':
                return $this->has_stored_api_token();
            case 'api_endpoint':
                return get_option(self::OPTION_API_ENDPOINT, '') !== '';
            case 'google_client_id':
                return get_option(self::OPTION_GOOGLE_CLIENT_ID, '') !== '';
            case 'google_client_secret':
                return $this->has_stored_google_client_secret();
            case 'proxy_webhook_token':
                return $this->has_stored_proxy_webhook_token();
            default:
                return false;
        }
    }

    /**
     * ערך תצוגה לשדות לא רגישים (מהאופציה השמורה או ברירת מחדל).
     *
     * @param string $field_key
     * @return string
     */
    public function get_field_display_value($field_key) {
        switch ($field_key) {
            case 'api_endpoint':
                return $this->get_api_endpoint();
            case 'google_client_id':
                return $this->get_google_client_id();
            default:
                return '';
        }
    }

    /**
     * שם שדה POST לפי מפתח לוגי.
     *
     * @param string $field_key
     * @return string
     */
    public function get_field_post_name($field_key) {
        $map = array(
            'api_token' => 'clinic_queue_api_token',
            'api_endpoint' => 'clinic_queue_api_endpoint',
            'google_client_id' => 'clinic_queue_google_client_id',
            'google_client_secret' => 'clinic_queue_google_client_secret',
            'proxy_webhook_token' => 'clinic_queue_proxy_webhook_token',
        );

        return isset($map[$field_key]) ? $map[$field_key] : '';
    }

    /**
     * @return bool
     */
    public function has_stored_api_token() {
        return !empty(get_option(self::OPTION_API_TOKEN_ENCRYPTED, null));
    }

    /**
     * @return bool
     */
    public function has_stored_google_client_secret() {
        return !empty(get_option(self::OPTION_GOOGLE_CLIENT_SECRET_ENCRYPTED, null));
    }

    /**
     * כתובת בסיס של DoctorOnline Proxy.
     *
     * @return string
     */
    public function get_api_endpoint() {
        $from_option = get_option(self::OPTION_API_ENDPOINT, '');
        if (!empty($from_option)) {
            return $from_option;
        }

        if (defined('CLINIC_QUEUE_API_ENDPOINT') && CLINIC_QUEUE_API_ENDPOINT !== '') {
            return CLINIC_QUEUE_API_ENDPOINT;
        }

        if (defined('DOCTOR_ONLINE_PROXY_BASE_URL') && DOCTOR_ONLINE_PROXY_BASE_URL !== '') {
            return DOCTOR_ONLINE_PROXY_BASE_URL;
        }

        $filtered = apply_filters('clinic_queue_api_endpoint', null);
        if (!empty($filtered)) {
            return $filtered;
        }

        if (defined('CLINIC_QUEUE_DEFAULT_API_ENDPOINT') && CLINIC_QUEUE_DEFAULT_API_ENDPOINT !== '') {
            return CLINIC_QUEUE_DEFAULT_API_ENDPOINT;
        }

        return '';
    }

    /**
     * טוקן אימות ל-Proxy API.
     *
     * @param int|null $scheduler_id Fallback לטוקן קליניקס על היומן.
     * @return string|null
     */
    public function get_api_token($scheduler_id = null) {
        $encrypted = get_option(self::OPTION_API_TOKEN_ENCRYPTED, null);
        if (!empty($encrypted)) {
            $token = $this->encryption_service->decrypt_token($encrypted);
            if (!empty($token)) {
                return $token;
            }
        }

        $legacy = get_option('clinic_queue_api_token', null);
        if (!empty($legacy)) {
            return $legacy;
        }

        if (defined('CLINIC_QUEUE_API_TOKEN') && CLINIC_QUEUE_API_TOKEN !== '' && CLINIC_QUEUE_API_TOKEN !== 'YOUR_API_TOKEN_HERE') {
            return CLINIC_QUEUE_API_TOKEN;
        }

        if (defined('DOCTOR_ONLINE_PROXY_AUTH_TOKEN') && DOCTOR_ONLINE_PROXY_AUTH_TOKEN !== '') {
            return DOCTOR_ONLINE_PROXY_AUTH_TOKEN;
        }

        if ($scheduler_id) {
            $clinix_token = get_post_meta((int) $scheduler_id, 'clinix_api_token', true);
            if (!empty($clinix_token) && is_string($clinix_token)) {
                return $clinix_token;
            }
        }

        $filter_token = apply_filters('clinic_queue_api_token', null, $scheduler_id);
        if (!empty($filter_token)) {
            return $filter_token;
        }

        if (defined('CLINIC_QUEUE_DEFAULT_API_TOKEN') && CLINIC_QUEUE_DEFAULT_API_TOKEN !== '') {
            return CLINIC_QUEUE_DEFAULT_API_TOKEN;
        }

        if ($scheduler_id) {
            return (string) $scheduler_id;
        }

        return null;
    }

    /**
     * Google OAuth Client ID.
     *
     * @return string
     */
    public function get_google_client_id() {
        $from_option = get_option(self::OPTION_GOOGLE_CLIENT_ID, '');
        if (!empty($from_option)) {
            return $from_option;
        }

        if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '') {
            return GOOGLE_CLIENT_ID;
        }

        if (defined('CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_ID') && CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_ID !== '') {
            return CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_ID;
        }

        return '';
    }

    /**
     * Google OAuth Client Secret (מפוענח).
     *
     * @return string
     */
    public function get_google_client_secret() {
        $encrypted = get_option(self::OPTION_GOOGLE_CLIENT_SECRET_ENCRYPTED, null);
        if (!empty($encrypted)) {
            $secret = $this->encryption_service->decrypt_token($encrypted);
            if (!empty($secret)) {
                return $secret;
            }
        }

        if (defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== '') {
            return GOOGLE_CLIENT_SECRET;
        }

        if (defined('CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_SECRET') && CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_SECRET !== '') {
            return CLINIC_QUEUE_DEFAULT_GOOGLE_CLIENT_SECRET;
        }

        return '';
    }

    /**
     * Scopes ל-Google Calendar OAuth (לא מדף ההגדרות).
     *
     * עדיפות: GOOGLE_CALENDAR_SCOPES (wp-config / api/config/google-credentials.php) > CLINIC_QUEUE_DEFAULT_*.
     *
     * @return string
     */
    public function get_google_calendar_scopes() {
        if (defined('GOOGLE_CALENDAR_SCOPES') && GOOGLE_CALENDAR_SCOPES !== '') {
            return (string) GOOGLE_CALENDAR_SCOPES;
        }

        if (defined('CLINIC_QUEUE_DEFAULT_GOOGLE_CALENDAR_SCOPES') && CLINIC_QUEUE_DEFAULT_GOOGLE_CALENDAR_SCOPES !== '') {
            return (string) CLINIC_QUEUE_DEFAULT_GOOGLE_CALENDAR_SCOPES;
        }

        return '';
    }

    /**
     * האם מוגדרים פרטי Google OAuth (מספיק לזרימת חיבור).
     *
     * @return bool
     */
    public function has_google_oauth_credentials() {
        return $this->get_google_client_id() !== '' && $this->get_google_client_secret() !== '';
    }

    /**
     * מגדיר קבועי GOOGLE_* לתאימות קוד קיים – רק אם עדיין לא הוגדרו.
     *
     * @return void
     */
    public function bootstrap_google_constants() {
        if (!defined('GOOGLE_CLIENT_ID')) {
            $client_id = $this->get_google_client_id();
            if ($client_id !== '') {
                define('GOOGLE_CLIENT_ID', $client_id);
            }
        }

        if (!defined('GOOGLE_CLIENT_SECRET')) {
            $secret = $this->get_google_client_secret();
            if ($secret !== '') {
                define('GOOGLE_CLIENT_SECRET', $secret);
            }
        }

        if (!defined('GOOGLE_CALENDAR_SCOPES')) {
            $scopes = $this->get_google_calendar_scopes();
            if ($scopes !== '') {
                define('GOOGLE_CALENDAR_SCOPES', $scopes);
            }
        }
    }

    /**
     * @return void
     */
    public function delete_api_token() {
        delete_option(self::OPTION_API_TOKEN_ENCRYPTED);
    }

    /**
     * @return void
     */
    public function delete_google_client_secret() {
        delete_option(self::OPTION_GOOGLE_CLIENT_SECRET_ENCRYPTED);
    }

    /**
     * ProxyWebhookToken לאימות בקשות מהשרת החיצוני.
     * הטוקן מנוהל רק דרך דף ההגדרות בוורדפרס אדמין (מוצפן ב-DB).
     *
     * @return string
     */
    public function get_proxy_webhook_token() {
        $encrypted = get_option(self::OPTION_PROXY_WEBHOOK_TOKEN_ENCRYPTED, null);
        if (!empty($encrypted)) {
            $token = $this->encryption_service->decrypt_token($encrypted);
            if (!empty($token)) {
                return $token;
            }
        }

        return '';
    }

    /**
     * @return bool
     */
    public function has_stored_proxy_webhook_token() {
        return !empty(get_option(self::OPTION_PROXY_WEBHOOK_TOKEN_ENCRYPTED, null));
    }

    /**
     * @return void
     */
    public function delete_proxy_webhook_token() {
        delete_option(self::OPTION_PROXY_WEBHOOK_TOKEN_ENCRYPTED);
    }

    /**
     * @param string $token
     * @return bool
     */
    private function persist_proxy_webhook_token($token) {
        $token = sanitize_text_field($token);
        if ($token === '') {
            return true;
        }

        $encrypted = $this->encryption_service->encrypt_token($token);
        if ($encrypted === '') {
            $this->set_save_error('שגיאה: הצפנת ProxyWebhookToken נכשלה.');
            return false;
        }

        $saved = $this->upsert_option(self::OPTION_PROXY_WEBHOOK_TOKEN_ENCRYPTED, $encrypted, false);
        if (!$saved) {
            $this->set_save_error('שגיאה: ProxyWebhookToken לא נשמר למסד הנתונים.');
            return false;
        }

        return true;
    }

    /**
     * @param string $token
     * @return bool
     */
    private function persist_api_token($token) {
        $token = sanitize_text_field($token);
        if ($token === '') {
            return true;
        }

        $encrypted = $this->encryption_service->encrypt_token($token);
        if ($encrypted === '') {
            $this->set_save_error('שגיאה: הצפנת הטוקן נכשלה.');
            return false;
        }

        $saved = $this->upsert_option(self::OPTION_API_TOKEN_ENCRYPTED, $encrypted, false);
        if (!$saved) {
            $this->set_save_error('שגיאה: הטוקן לא נשמר למסד הנתונים.');
            return false;
        }

        return true;
    }

    /**
     * @param string $endpoint
     * @return bool
     */
    private function persist_api_endpoint($endpoint) {
        if ($endpoint === '') {
            $this->set_save_error(__('כתובת שרת לא חוקית.', 'clinic-queue'));
            return false;
        }

        return $this->upsert_option(self::OPTION_API_ENDPOINT, $endpoint, false);
    }

    /**
     * @param string $client_id
     * @return bool
     */
    private function persist_google_client_id($client_id) {
        if ($client_id === '') {
            return true;
        }

        return $this->upsert_option(self::OPTION_GOOGLE_CLIENT_ID, $client_id, false);
    }

    /**
     * @param string $secret
     * @return bool
     */
    private function persist_google_client_secret($secret) {
        $secret = sanitize_text_field($secret);
        if ($secret === '') {
            return true;
        }

        $encrypted = $this->encryption_service->encrypt_token($secret);
        if ($encrypted === '') {
            $this->set_save_error('שגיאה: הצפנת Client Secret של Google נכשלה.');
            return false;
        }

        $saved = $this->upsert_option(self::OPTION_GOOGLE_CLIENT_SECRET_ENCRYPTED, $encrypted, false);
        if (!$saved) {
            $this->set_save_error('שגיאה: Client Secret של Google לא נשמר.');
            return false;
        }

        return true;
    }

    /**
     * @param string $option_name
     * @param string $value
     * @param bool   $autoload
     * @return bool
     */
    private function upsert_option($option_name, $value, $autoload = false) {
        $missing_marker = '__clinic_queue_option_missing__';
        if (get_option($option_name, $missing_marker) === $missing_marker) {
            return (bool) add_option($option_name, $value, '', $autoload ? 'yes' : 'no');
        }

        return (bool) update_option($option_name, $value, $autoload);
    }

    /**
     * @param string $message
     * @return void
     */
    private function set_save_error($message) {
        set_transient(self::TRANSIENT_SAVE_ERROR, $message, 30);
    }

    /**
     * מנקה אופציות ישנות שלא בשימוש.
     *
     * @return void
     */
    private function cleanup_obsolete_options() {
        $obsolete_options = array(
            'clinic_queue_cron_logs',
            'clinic_queue_token_save_error',
            'clinic_queue_handler_called',
            'clinic_queue_handler_exit_reason',
            'clinic_queue_google_scopes',
            'clinic_queue_google_calendar_scopes',
        );

        foreach ($obsolete_options as $option_name) {
            if (get_option($option_name, false) !== false) {
                delete_option($option_name);
            }
        }
    }
}
