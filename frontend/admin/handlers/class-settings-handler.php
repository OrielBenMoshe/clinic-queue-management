<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/services/class-plugin-settings-service.php';

/**
 * Settings Handler – routing, רינדור וטעינת assets לדף ההגדרות.
 *
 * לוגיקת שמירה/קריאה: Clinic_Queue_Plugin_Settings_Service.
 *
 * @package ClinicQueue
 * @subpackage Admin\Handlers
 */
class Clinic_Queue_Settings_Handler {

    /**
     * @var Clinic_Queue_Settings_Handler|null
     */
    private static $instance = null;

    /**
     * @var Clinic_Queue_Plugin_Settings_Service
     */
    private $settings_service;

    /**
     * @return Clinic_Queue_Settings_Handler
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings_service = Clinic_Queue_Plugin_Settings_Service::get_instance();

        add_action('admin_post_clinic_queue_save_setting_field', array($this, 'handle_save_field_submission'));
        add_action('admin_post_clinic_queue_delete_setting_field', array($this, 'handle_delete_field_submission'));
        add_action('admin_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    /**
     * שמירת שדה בודד (admin-post).
     *
     * @return void
     */
    public function handle_save_field_submission() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to save settings', 'clinic-queue'));
        }

        $field_key = isset($_POST['field_key'])
            ? sanitize_key(wp_unslash($_POST['field_key']))
            : '';

        if (!$this->verify_field_nonce($field_key, 'save')) {
            wp_die(esc_html__('Security check failed', 'clinic-queue'));
        }

        $post_name = $this->settings_service->get_field_post_name($field_key);
        $raw_value = isset($_POST[$post_name]) ? wp_unslash($_POST[$post_name]) : '';

        $save_result = $this->settings_service->save_single_field($field_key, $raw_value);

        $this->redirect_after_field_action($field_key, $save_result ? 'saved' : 'save_failed');
    }

    /**
     * מחיקת שדה בודד (admin-post).
     *
     * @return void
     */
    public function handle_delete_field_submission() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to save settings', 'clinic-queue'));
        }

        $field_key = isset($_POST['field_key'])
            ? sanitize_key(wp_unslash($_POST['field_key']))
            : '';

        if (!$this->verify_field_nonce($field_key, 'delete')) {
            wp_die(esc_html__('Security check failed', 'clinic-queue'));
        }

        $delete_result = $this->settings_service->delete_single_field($field_key);

        $this->redirect_after_field_action($field_key, $delete_result ? 'deleted' : 'delete_failed');
    }

    /**
     * @param string $field_key
     * @param string $action save|delete
     * @return bool
     */
    private function verify_field_nonce($field_key, $action) {
        if (!in_array($field_key, Clinic_Queue_Plugin_Settings_Service::ADMIN_FIELD_KEYS, true)) {
            return false;
        }

        $nonce_action = 'clinic_queue_' . $action . '_field_' . $field_key;
        $nonce_field = 'clinic_queue_field_nonce';

        return isset($_POST[$nonce_field])
            && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST[$nonce_field])),
                $nonce_action
            );
    }

    /**
     * @param string $field_key
     * @param string $result saved|save_failed|deleted|delete_failed
     * @return void
     */
    private function redirect_after_field_action($field_key, $result) {
        $redirect_url = add_query_arg(
            array(
                'page' => 'clinic-queue-settings',
                'settings-field' => $field_key,
                'settings-result' => $result,
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * טוען CSS/JS רק בדף ההגדרות (ב-head, לפני הרינדור).
     *
     * כשיש add_submenu_page עם אותו slug כמו add_menu_page, וורדפרס משתמש ב-hook
     * `clinic-queue-settings_page_clinic-queue-settings` (לא רק toplevel_page_*).
     *
     * @param string $hook_suffix
     * @return void
     */
    public function maybe_enqueue_assets($hook_suffix) {
        if (!$this->is_settings_admin_screen($hook_suffix)) {
            return;
        }

        $this->enqueue_assets();
    }

    /**
     * האם הבקשה הנוכחית היא מסך ההגדרות של התוסף.
     *
     * @param string $hook_suffix ערך מ-admin_enqueue_scripts.
     * @return bool
     */
    private function is_settings_admin_screen($hook_suffix) {
        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        if ($page === 'clinic-queue-settings') {
            return true;
        }

        return in_array(
            $hook_suffix,
            array(
                'toplevel_page_clinic-queue-settings',
                'clinic-queue-settings_page_clinic-queue-settings',
            ),
            true
        );
    }

    /**
     * גרסת cache-bust לנכס סטטי (filemtime + גרסת תוסף).
     *
     * @param string $relative_path נתיב יחסי מתוך שורש התוסף.
     * @return string
     */
    private function get_asset_version($relative_path) {
        $base_version = defined('CLINIC_QUEUE_MANAGEMENT_VERSION')
            ? CLINIC_QUEUE_MANAGEMENT_VERSION
            : '1.0.0';
        $absolute_path = CLINIC_QUEUE_MANAGEMENT_PATH . ltrim($relative_path, '/');

        if (file_exists($absolute_path)) {
            return $base_version . '.' . filemtime($absolute_path);
        }

        return $base_version;
    }

    /**
     * @return void
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'clinic-queue-settings',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/admin/assets/css/settings.css',
            array(),
            $this->get_asset_version('frontend/admin/assets/css/settings.css')
        );

        wp_enqueue_script(
            'clinic-queue-settings',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/admin/assets/js/settings.js',
            array('jquery'),
            $this->get_asset_version('frontend/admin/assets/js/settings.js'),
            true
        );

        wp_localize_script(
            'clinic-queue-settings',
            'clinicQueueSettings',
            array(
                'strings' => array(
                    'confirmDeleteToken' => __('האם אתה בטוח שברצונך למחוק את הטוקן?', 'clinic-queue'),
                    'confirmDeleteEndpoint' => __('האם אתה בטוח שברצונך למחוק את כתובת השרת השמורה?', 'clinic-queue'),
                    'confirmDeleteGoogleId' => __('האם אתה בטוח שברצונך למחוק את ה-Client ID?', 'clinic-queue'),
                    'confirmDeleteGoogleSecret' => __('האם אתה בטוח שברצונך למחוק את ה-Client Secret?', 'clinic-queue'),
                    'confirmDeleteProxyWebhookToken' => __('האם אתה בטוח שברצונך למחוק את ה-Proxy Webhook Token?', 'clinic-queue'),
                ),
            )
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function get_settings_data() {
        $field_key = isset($_GET['settings-field'])
            ? sanitize_key(wp_unslash($_GET['settings-field']))
            : '';

        $result = isset($_GET['settings-result'])
            ? sanitize_key(wp_unslash($_GET['settings-result']))
            : '';

        $notice_message = '';
        $notice_type = '';

        if ($field_key !== '' && $result !== '') {
            $label = $this->get_field_label($field_key);
            switch ($result) {
                case 'saved':
                    $notice_message = sprintf(
                        /* translators: %s: field label */
                        __('השדה "%s" נשמר בהצלחה.', 'clinic-queue'),
                        $label
                    );
                    $notice_type = 'success';
                    break;
                case 'deleted':
                    $notice_message = sprintf(
                        /* translators: %s: field label */
                        __('השדה "%s" נמחק.', 'clinic-queue'),
                        $label
                    );
                    $notice_type = 'success';
                    break;
                case 'save_failed':
                case 'delete_failed':
                    $notice_message = sprintf(
                        /* translators: %s: field label */
                        __('לא ניתן לעדכן את השדה "%s". נסה שוב.', 'clinic-queue'),
                        $label
                    );
                    $notice_type = 'error';
                    break;
            }
        }

        return array(
            'notice_message' => $notice_message,
            'notice_type' => $notice_type,
            'save_error' => get_transient(Clinic_Queue_Plugin_Settings_Service::TRANSIENT_SAVE_ERROR),
        );
    }

    /**
     * @return void
     */
    public function render_page() {
        $data = $this->get_settings_data();
        extract($data);

        $handler = $this;

        include CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/admin/views/settings-html.php';
    }

    /**
     * רינדור שורת שדה אחת עם עריכה/שמירה/מחיקה per-field.
     *
     * @param string $field_key
     * @return void
     */
    public function render_setting_field($field_key) {
        if (!in_array($field_key, Clinic_Queue_Plugin_Settings_Service::ADMIN_FIELD_KEYS, true)) {
            return;
        }

        $config = $this->get_field_config($field_key);
        if ($config === null) {
            return;
        }

        $has_stored = $this->settings_service->field_has_stored_value($field_key);
        $is_sensitive = $this->settings_service->is_sensitive_field($field_key);
        $display_value = $is_sensitive ? '' : $this->settings_service->get_field_display_value($field_key);
        $post_name = $this->settings_service->get_field_post_name($field_key);
        $input_id = $post_name;
        $input_type = $config['input_type'];
        $save_form_id = 'clinic-queue-save-form-' . $field_key;
        $save_nonce = wp_nonce_field('clinic_queue_save_field_' . $field_key, 'clinic_queue_field_nonce', true, false);
        $delete_nonce = wp_nonce_field('clinic_queue_delete_field_' . $field_key, 'clinic_queue_field_nonce', true, false);
        ?>
        <div class="settings-group clinic-setting-field"
             data-field="<?php echo esc_attr($field_key); ?>"
             data-sensitive="<?php echo $is_sensitive ? '1' : '0'; ?>"
             data-has-stored="<?php echo $has_stored ? '1' : '0'; ?>">

            <label class="settings-label" for="<?php echo esc_attr($input_id); ?>">
                <?php echo esc_html($config['label']); ?>
            </label>

            <?php if (!empty($config['hint'])) : ?>
                <p class="clinic-setting-field__hint"><?php echo esc_html($config['hint']); ?></p>
            <?php endif; ?>

            <div class="clinic-setting-field__row">
                <div class="clinic-setting-field__value">
                    <div class="clinic-setting-field__view<?php echo $has_stored ? '' : ' clinic-field-hidden'; ?>">
                        <?php if ($is_sensitive) : ?>
                            <span class="clinic-setting-field__masked" aria-hidden="true">••••••••••••</span>
                            <span class="screen-reader-text"><?php esc_html_e('ערך שמור (מוסתר)', 'clinic-queue'); ?></span>
                        <?php else : ?>
                            <input
                                type="<?php echo esc_attr($input_type); ?>"
                                id="<?php echo esc_attr($input_id); ?>_display"
                                value="<?php echo esc_attr($display_value); ?>"
                                class="regular-text clinic-settings-input clinic-input-wide clinic-setting-field__display"
                                readonly
                                disabled
                                tabindex="-1"
                            />
                        <?php endif; ?>
                    </div>

                    <div class="clinic-setting-field__edit<?php echo $has_stored ? ' clinic-field-hidden' : ''; ?>">
                        <form method="post"
                              id="<?php echo esc_attr($save_form_id); ?>"
                              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                              class="clinic-setting-field__save-form">
                            <input type="hidden" name="action" value="clinic_queue_save_setting_field" />
                            <input type="hidden" name="field_key" value="<?php echo esc_attr($field_key); ?>" />
                            <?php echo $save_nonce; ?>
                            <input
                                type="<?php echo esc_attr($input_type); ?>"
                                id="<?php echo esc_attr($input_id); ?>"
                                name="<?php echo esc_attr($post_name); ?>"
                                value="<?php echo $has_stored || $is_sensitive ? '' : esc_attr($display_value); ?>"
                                class="regular-text clinic-settings-input clinic-input-wide clinic-setting-field__input"
                                placeholder="<?php echo esc_attr($config['placeholder']); ?>"
                                autocomplete="<?php echo esc_attr($config['autocomplete']); ?>"
                                readonly
                                disabled
                            />
                        </form>
                    </div>
                </div>

                <div class="clinic-setting-field__actions">
                    <button type="button" class="button clinic-settings-btn clinic-settings-btn--edit clinic-setting-field__edit-btn">
                        <?php esc_html_e('ערוך', 'clinic-queue'); ?>
                    </button>

                    <button type="button" class="button clinic-settings-btn clinic-settings-btn--delete clinic-setting-field__delete-btn<?php echo $has_stored ? '' : ' clinic-field-hidden'; ?>">
                        <?php esc_html_e('מחק', 'clinic-queue'); ?>
                    </button>

                    <button type="button" class="button clinic-settings-btn clinic-settings-btn--cancel clinic-setting-field__cancel-btn clinic-field-hidden">
                        <?php esc_html_e('ביטול', 'clinic-queue'); ?>
                    </button>

                    <button type="submit"
                            form="<?php echo esc_attr($save_form_id); ?>"
                            class="button clinic-settings-btn clinic-settings-btn--save clinic-setting-field__save-btn clinic-field-hidden">
                        <?php esc_html_e('שמור', 'clinic-queue'); ?>
                    </button>

                    <form method="post"
                          action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                          class="clinic-setting-field__delete-form clinic-field-hidden">
                        <input type="hidden" name="action" value="clinic_queue_delete_setting_field" />
                        <input type="hidden" name="field_key" value="<?php echo esc_attr($field_key); ?>" />
                        <?php echo $delete_nonce; ?>
                        <button type="submit" class="screen-reader-text"><?php esc_html_e('מחק', 'clinic-queue'); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param string $field_key
     * @return array<string, string>|null
     */
    private function get_field_config($field_key) {
        $configs = array(
            'api_token' => array(
                'label' => __('DoctorOnline Proxy API Token', 'clinic-queue'),
                'hint' => __('מתקבל ממנהל שירות DoctorOnline.', 'clinic-queue'),
                'input_type' => 'password',
                'placeholder' => __('הדבק את טוקן ה-API', 'clinic-queue'),
                'autocomplete' => 'new-password',
            ),
            'api_endpoint' => array(
                'label' => __('כתובת שרת', 'clinic-queue'),
                'hint' => __('כתובת בסיס של שירות התורים (ברירת מחדל מוגדרת מראש).', 'clinic-queue'),
                'input_type' => 'url',
                'placeholder' => 'https://do-proxy-staging.doctor-clinix.com',
                'autocomplete' => 'off',
            ),
            'google_client_id' => array(
                'label' => __('Client ID', 'clinic-queue'),
                'hint' => '',
                'input_type' => 'text',
                'placeholder' => __('מ-Google Cloud Console', 'clinic-queue'),
                'autocomplete' => 'off',
            ),
            'google_client_secret' => array(
                'label' => __('Client Secret', 'clinic-queue'),
                'hint' => '',
                'input_type' => 'password',
                'placeholder' => __('מ-Google Cloud Console', 'clinic-queue'),
                'autocomplete' => 'new-password',
            ),
            'proxy_webhook_token' => array(
                'label' => __('Proxy Webhook Token', 'clinic-queue'),
                'hint' => __('טוקן לאימות בקשות מהשרת החיצוני', 'clinic-queue'),
                'input_type' => 'password',
                'placeholder' => __('הדבק את ה-Webhook Token', 'clinic-queue'),
                'autocomplete' => 'new-password',
            ),
        );

        return isset($configs[$field_key]) ? $configs[$field_key] : null;
    }

    /**
     * @param string $field_key
     * @return string
     */
    private function get_field_label($field_key) {
        $config = $this->get_field_config($field_key);
        return $config !== null ? $config['label'] : $field_key;
    }
}
