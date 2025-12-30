<?php
/**
 * Google Calendar Service
 * מטפל בכל התקשורת עם Google Calendar API
 * 
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_Queue_Google_Calendar_Service
 * מנהל OAuth flow, הצפנה, ותקשורת עם Google Calendar API
 */
class Clinic_Queue_Google_Calendar_Service {
    
    /**
     * Google OAuth endpoints
     */
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const OAUTH_USERINFO_URL = 'https://www.googleapis.com/oauth2/v1/userinfo';
    private const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3';
    
    /**
     * Constructor
     */
    public function __construct() {
        // ניתן להוסיף initialization logic כאן
    }
    
    /**
     * החלפת authorization code ב-access tokens
     * 
     * @param string $code Authorization code מ-OAuth flow
     * @return array|WP_Error מערך עם tokens או שגיאה
     */
    public function exchange_code_for_tokens($code) {
        if (empty($code)) {
            return new WP_Error('missing_code', 'Authorization code is required');
        }
        
        // בדיקה שיש credentials
        if (!defined('GOOGLE_CLIENT_ID') || !defined('GOOGLE_CLIENT_SECRET')) {
            return new WP_Error('missing_credentials', 'Google Client ID or Secret not configured');
        }
        
        // For popup mode with Google Identity Services, we need to use the origin as redirect_uri
        // or use 'postmessage' for client-side flows
        $body = array(
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => 'postmessage', // Special value for popup/client-side flows
            'grant_type' => 'authorization_code'
        );
        
        $response = wp_remote_post(self::OAUTH_TOKEN_URL, array(
            'body' => $body,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            $error_message = isset($data['error_description']) ? $data['error_description'] : 'Failed to exchange code for tokens';
            return new WP_Error('token_exchange_failed', $error_message, array('status' => $status_code));
        }
        
        // וידוא שקיבלנו את כל הנתונים הנדרשים
        if (!isset($data['access_token']) || !isset($data['refresh_token'])) {
            error_log('[Google Calendar] Missing tokens in response. Data: ' . print_r($data, true));
            return new WP_Error('incomplete_response', 'Missing tokens in response');
        }
        
        error_log('[Google Calendar] Successfully exchanged code for tokens. Access token: ' . substr($data['access_token'], 0, 20) . '...');
        
        // חישוב מתי ה-token יפוג
        $expires_in = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
        $expires_at = date('Y-m-d H:i:s', time() + $expires_in);
        
        return array(
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => $expires_at,
            'token_type' => isset($data['token_type']) ? $data['token_type'] : 'Bearer',
            'scope' => isset($data['scope']) ? $data['scope'] : ''
        );
    }
    
    /**
     * רענון access token באמצעות refresh token
     * 
     * @param string $refresh_token Refresh token
     * @return array|WP_Error מערך עם access token חדש או שגיאה
     */
    public function refresh_access_token($refresh_token) {
        if (empty($refresh_token)) {
            return new WP_Error('missing_refresh_token', 'Refresh token is required');
        }
        
        if (!defined('GOOGLE_CLIENT_ID') || !defined('GOOGLE_CLIENT_SECRET')) {
            return new WP_Error('missing_credentials', 'Google Client ID or Secret not configured');
        }
        
        $body = array(
            'refresh_token' => $refresh_token,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'grant_type' => 'refresh_token'
        );
        
        $response = wp_remote_post(self::OAUTH_TOKEN_URL, array(
            'body' => $body,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            $error_message = isset($data['error_description']) ? $data['error_description'] : 'Failed to refresh token';
            return new WP_Error('token_refresh_failed', $error_message, array('status' => $status_code));
        }
        
        if (!isset($data['access_token'])) {
            return new WP_Error('incomplete_response', 'Missing access token in response');
        }
        
        // חישוב מתי ה-token החדש יפוג
        $expires_in = isset($data['expires_in']) ? (int)$data['expires_in'] : 3600;
        $expires_at = date('Y-m-d H:i:s', time() + $expires_in);
        
        return array(
            'access_token' => $data['access_token'],
            'expires_at' => $expires_at,
            'token_type' => isset($data['token_type']) ? $data['token_type'] : 'Bearer'
        );
    }
    
    /**
     * בדיקה האם access token עדיין תקף
     * 
     * @param string $access_token Access token
     * @return bool|WP_Error true אם תקף, false אם פג, WP_Error אם שגיאה
     */
    public function validate_token($access_token) {
        if (empty($access_token)) {
            return new WP_Error('missing_token', 'Access token is required');
        }
        
        $response = wp_remote_get(self::OAUTH_USERINFO_URL, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        // 200 = תקף, 401 = לא תקף
        return $status_code === 200;
    }
    
    /**
     * שליפת פרטי משתמש מגוגל
     * 
     * @param string $access_token Access token
     * @return array|WP_Error מערך עם פרטי משתמש או שגיאה
     */
    public function get_user_info($access_token) {
        if (empty($access_token)) {
            return new WP_Error('missing_token', 'Access token is required');
        }
        
        error_log('[Google Calendar] Getting user info with token: ' . substr($access_token, 0, 20) . '...');
        
        $response = wp_remote_get(self::OAUTH_USERINFO_URL, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            error_log('[Google Calendar] Failed to get user info. Status: ' . $status_code . ', Body: ' . $body);
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Failed to get user info';
            return new WP_Error('user_info_failed', $error_message, array('status' => $status_code));
        }
        
        error_log('[Google Calendar] Successfully got user info: ' . $data['email']);
        
        return array(
            'email' => isset($data['email']) ? $data['email'] : '',
            'name' => isset($data['name']) ? $data['name'] : '',
            'picture' => isset($data['picture']) ? $data['picture'] : '',
            'verified_email' => isset($data['verified_email']) ? $data['verified_email'] : false
        );
    }
    
    /**
     * יצירת אירוע ביומן (לשימוש עתידי)
     * 
     * @param string $calendar_id מזהה יומן (בדרך כלל 'primary')
     * @param array $event_data נתוני האירוע
     * @param string $access_token Access token
     * @return array|WP_Error מידע על האירוע שנוצר או שגיאה
     */
    public function create_event($calendar_id, $event_data, $access_token) {
        if (empty($calendar_id) || empty($event_data) || empty($access_token)) {
            return new WP_Error('missing_parameters', 'Calendar ID, event data, and access token are required');
        }
        
        $url = self::CALENDAR_API_BASE . '/calendars/' . urlencode($calendar_id) . '/events';
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => wp_json_encode($event_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code !== 200 && $status_code !== 201) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Failed to create event';
            return new WP_Error('event_creation_failed', $error_message, array('status' => $status_code));
        }
        
        return $data;
    }
    
    /**
     * קריאת אירועים מהיומן (לשימוש עתידי)
     * 
     * @param string $calendar_id מזהה יומן (בדרך כלל 'primary')
     * @param string $access_token Access token
     * @param array $params פרמטרים נוספים (timeMin, timeMax, sync_token וכו')
     * @return array|WP_Error רשימת אירועים או שגיאה
     */
    public function get_events($calendar_id, $access_token, $params = array()) {
        if (empty($calendar_id) || empty($access_token)) {
            return new WP_Error('missing_parameters', 'Calendar ID and access token are required');
        }
        
        $url = self::CALENDAR_API_BASE . '/calendars/' . urlencode($calendar_id) . '/events';
        
        // הוספת query parameters
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Failed to get events';
            return new WP_Error('events_fetch_failed', $error_message, array('status' => $status_code));
        }
        
        return $data;
    }
    
    /**
     * הצפנת token
     * משתמש ב-OpenSSL עם WordPress salts
     * 
     * @param string $token Token להצפנה
     * @return string Token מוצפן (base64)
     */
    public function encrypt_token($token) {
        if (empty($token)) {
            return '';
        }
        
        $method = 'AES-256-CBC';
        $key = wp_salt('auth');
        $iv = substr(wp_salt('secure_auth'), 0, 16);
        
        $encrypted = openssl_encrypt($token, $method, $key, 0, $iv);
        
        if ($encrypted === false) {
            return '';
        }
        
        return base64_encode($encrypted);
    }
    
    /**
     * פענוח token
     * 
     * @param string $encrypted_token Token מוצפן (base64)
     * @return string Token מפוענח
     */
    public function decrypt_token($encrypted_token) {
        if (empty($encrypted_token)) {
            return '';
        }
        
        $method = 'AES-256-CBC';
        $key = wp_salt('auth');
        $iv = substr(wp_salt('secure_auth'), 0, 16);
        
        $decoded = base64_decode($encrypted_token);
        
        if ($decoded === false) {
            return '';
        }
        
        $decrypted = openssl_decrypt($decoded, $method, $key, 0, $iv);
        
        if ($decrypted === false) {
            return '';
        }
        
        return $decrypted;
    }
}

