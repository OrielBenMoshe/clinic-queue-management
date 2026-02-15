<?php
/**
 * Handler לממשק אדמין – שיוך טיפולים להתמחויות, ייבוא ועמודות.
 *
 * @package ClinicQueue
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_Queue_Treatment_Specialty_Handler
 *
 * מנהל:
 * - תפריט ודף ייבוא התמחויות
 * - שדות בחירת התמחויות בטופס add/edit של סוג טיפול
 * - עמודת "התמחויות משויכות" ברשימת סוגי הטיפולים
 */
class Clinic_Queue_Treatment_Specialty_Handler {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_seed_menu'), 20);
        add_action('admin_post_clinic_queue_seed_specialties', array($this, 'handle_seed_request'));
        add_action('treatment_types_add_form_fields', array($this, 'render_specialty_fields_add'));
        add_action('treatment_types_edit_form_fields', array($this, 'render_specialty_fields_edit'), 10, 2);
        add_action('created_treatment_types', array($this, 'save_specialty_ids'), 10, 3);
        add_action('edited_treatment_types', array($this, 'save_specialty_ids'), 10, 3);
        add_filter('manage_edit-treatment_types_columns', array($this, 'add_specialties_column'));
        add_filter('manage_treatment_types_custom_column', array($this, 'render_specialties_column'), 10, 3);
        add_filter('manage_edit-specialties_columns', array($this, 'add_treatments_count_column'));
        add_filter('manage_specialties_custom_column', array($this, 'render_treatments_count_column'), 10, 3);
        add_filter('get_terms_args', array($this, 'filter_treatments_by_specialty'), 10, 2);
        add_action('admin_notices', array($this, 'render_specialty_filter_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_treatment_meta_assets'));
    }

    /**
     * תפריט ומסך להרצת ייבוא התמחויות וטיפולים.
     */
    public function add_seed_menu() {
        add_submenu_page(
            'clinic-queue-settings',
            __('ייבוא התמחויות וטיפולים', 'clinic-queue-management'),
            __('ייבוא התמחויות', 'clinic-queue-management'),
            'manage_options',
            'clinic-queue-seed-specialties',
            array($this, 'render_seed_page')
        );
    }

    /**
     * מציג מסך עם כפתור להרצת הייבוא.
     */
    public function render_seed_page() {
        $message = isset($_GET['clinic_queue_seed_done']) ? sanitize_text_field(wp_unslash($_GET['clinic_queue_seed_done'])) : '';
        $url     = wp_nonce_url(admin_url('admin-post.php?action=clinic_queue_seed_specialties'), 'clinic_queue_seed_specialties');
        include CLINIC_QUEUE_MANAGEMENT_PATH . 'admin/views/seed-specialties-html.php';
    }

    /**
     * מטפל בלחיצה על "הרץ ייבוא" – בודק nonce והרשאות ומריץ seed.
     */
    public function handle_seed_request() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('אין הרשאה.', 'clinic-queue-management'));
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'clinic_queue_seed_specialties')) {
            wp_die(esc_html__('אימות נכשל.', 'clinic-queue-management'));
        }
        $taxonomy = Clinic_Queue_Specialty_Taxonomy::get_instance();
        $ok       = $taxonomy->seed_from_json();
        wp_safe_redirect(add_query_arg('clinic_queue_seed_done', $ok ? '1' : '0', admin_url('admin.php?page=clinic-queue-seed-specialties')));
        exit;
    }

    /**
     * טוען CSS/JS למסך עריכת סוגי טיפולים.
     * edit-tags.php = רשימה + טופס הוספה | term.php = טופס עריכת מונח בודד.
     *
     * @param string $hook_suffix ה-hook של הדף הנוכחי.
     */
    public function enqueue_treatment_meta_assets($hook_suffix) {
        if ($hook_suffix !== 'edit-tags.php' && $hook_suffix !== 'term.php') {
            return;
        }
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field(wp_unslash($_GET['taxonomy'])) : '';
        if ($taxonomy !== Clinic_Queue_Specialty_Taxonomy::get_treatments_taxonomy()) {
            return;
        }
        wp_enqueue_style(
            'clinic-queue-treatment-specialty-meta',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/admin/treatment-specialty-meta.css',
            array(),
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );
        wp_enqueue_script(
            'clinic-queue-treatment-specialty-meta',
            CLINIC_QUEUE_MANAGEMENT_URL . 'admin/js/treatment-specialty-meta.js',
            array('jquery'),
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );
    }

    /**
     * שדה בחירת התמחות (אחת) בטופס הוספת סוג טיפול.
     */
    public function render_specialty_fields_add() {
        $this->render_specialty_select(0, false);
    }

    /**
     * שדה בחירת התמחות (אחת) בטופס עריכת סוג טיפול.
     *
     * @param \WP_Term $term    ה-term הנערך.
     * @param string   $taxonomy הטקסונומיה.
     */
    public function render_specialty_fields_edit($term, $taxonomy) {
        $selected_id = Clinic_Queue_Specialty_Taxonomy::get_specialty_id_of_treatment($term->term_id);
        $this->render_specialty_select($selected_id, true);
    }

    /**
     * מציג select לבחירת התמחות אחת שאליה משויך הטיפול.
     *
     * @param int  $selected_id  specialty term ID נבחר (0 אם אין).
     * @param bool $wrap_in_table true לטופס עריכה (tr/td), false לטופס הוספה (div).
     */
    private function render_specialty_select($selected_id, $wrap_in_table) {
        $specialties = get_terms(array(
            'taxonomy'   => Clinic_Queue_Specialty_Taxonomy::get_specialties_taxonomy(),
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $no_data_msg = '<p>' . esc_html__('לא נמצאו התמחויות. הוסף התמחויות קודם במונחים → התמחויות.', 'clinic-queue-management') . '</p>';

        if (is_wp_error($specialties) || empty($specialties)) {
            if ($wrap_in_table) {
                echo '<tr class="form-field clinic-queue-specialty-field"><th scope="row"></th><td>' . $no_data_msg . '</td></tr>';
            } else {
                echo '<div class="form-field clinic-queue-specialty-field-add">' . $no_data_msg . '</div>';
            }
            return;
        }

        $selected_id = (int) $selected_id;
        $label_text  = __('תחום התמחות', 'clinic-queue-management');
        $desc_text   = __('בחר את ההתמחות שאליה שייך סוג הטיפול.', 'clinic-queue-management');

        if ($wrap_in_table) {
            echo '<tr class="form-field clinic-queue-specialty-field"><th scope="row"><label for="clinic-queue-specialty-id">' . esc_html($label_text) . '</label></th><td>';
        } else {
            echo '<div class="form-field clinic-queue-specialty-field-add"><label for="clinic-queue-specialty-id">' . esc_html($label_text) . '</label>';
        }
        echo '<input type="hidden" name="clinic_queue_specialty_ids_submitted" value="1">';
        echo '<p class="description">' . esc_html($desc_text) . '</p>';
        echo '<select name="clinic_queue_specialty_id" id="clinic-queue-specialty-id" class="clinic-queue-specialty-select">';
        echo '<option value="">' . esc_html__('— בחר התמחות —', 'clinic-queue-management') . '</option>';
        foreach ($specialties as $spec) {
            echo '<option value="' . esc_attr((string) $spec->term_id) . '" ' . selected($selected_id, (int) $spec->term_id, false) . '>' . esc_html($spec->name) . '</option>';
        }
        echo '</select>';
        if ($wrap_in_table) {
            echo '</td></tr>';
        } else {
            echo '</div>';
        }
    }

    /**
     * שומר את specialty_id (התמחות אחת) ב-term meta.
     *
     * @param int    $term_id  ID של ה-term.
     * @param int    $tt_id    Term taxonomy ID.
     * @param string $taxonomy slug של הטקסונומיה (ריק בעת עריכה).
     */
    public function save_specialty_ids($term_id, $tt_id, $taxonomy = '') {
        if ($taxonomy !== '' && $taxonomy !== Clinic_Queue_Specialty_Taxonomy::get_treatments_taxonomy()) {
            return;
        }
        if (!isset($_POST['clinic_queue_specialty_ids_submitted'])) {
            return;
        }
        $posted_id = isset($_POST['clinic_queue_specialty_id']) ? absint($_POST['clinic_queue_specialty_id']) : 0;
        if (!current_user_can('manage_categories')) {
            return;
        }
        $nonce_valid = false;
        if (isset($_POST['_wpnonce_add-tag'])) {
            $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce_add-tag'])), 'add-tag');
        } elseif (isset($_POST['_wpnonce'])) {
            $nonce_valid = wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-tag_' . $term_id);
        }
        if (!$nonce_valid) {
            return;
        }

        if ($posted_id > 0) {
            $spec_term = get_term($posted_id, Clinic_Queue_Specialty_Taxonomy::get_specialties_taxonomy());
            if ($spec_term && !is_wp_error($spec_term)) {
                update_term_meta($term_id, 'specialty_id', $posted_id);
                update_term_meta($term_id, 'specialty_ids', array($posted_id));
                return;
            }
        }
        update_term_meta($term_id, 'specialty_id', 0);
        update_term_meta($term_id, 'specialty_ids', array());
    }

    /**
     * מוסיף עמודת "התמחויות משויכות" לרשימת סוגי הטיפולים.
     *
     * @param array $columns עמודות קיימות.
     * @return array עמודות מעודכנות.
     */
    public function add_specialties_column($columns) {
        $new = array();
        foreach ($columns as $key => $val) {
            $new[$key] = $val;
            if ($key === 'name') {
                $new['clinic_queue_specialties'] = __('תחום התמחות', 'clinic-queue-management');
            }
        }
        return $new;
    }

    /**
     * מציג את ההתמחויות המשויכות בעמודה.
     *
     * @param string $content     תוכן ברירת מחדל.
     * @param string $column_name שם העמודה.
     * @param int    $term_id    ID של ה-term.
     * @return string
     */
    public function render_specialties_column($content, $column_name, $term_id) {
        if ($column_name !== 'clinic_queue_specialties') {
            return $content;
        }
        $specialty_ids = Clinic_Queue_Specialty_Taxonomy::get_specialties_of_treatment($term_id);
        if (empty($specialty_ids)) {
            return '<span class="clinic-queue-no-specialties">—</span>';
        }
        $names = array();
        foreach ($specialty_ids as $sid) {
            $term = get_term($sid, Clinic_Queue_Specialty_Taxonomy::get_specialties_taxonomy());
            if ($term && !is_wp_error($term)) {
                $names[] = esc_html($term->name);
            }
        }
        return implode(', ', $names);
    }

    /**
     * מוסיף עמודת "כמות סוגי טיפולים" לרשימת ההתמחויות.
     *
     * @param array $columns עמודות קיימות.
     * @return array עמודות מעודכנות.
     */
    public function add_treatments_count_column($columns) {
        $new = array();
        foreach ($columns as $key => $val) {
            $new[$key] = $val;
            if ($key === 'name') {
                $new['clinic_queue_treatments_count'] = __('כמות סוגי טיפולים', 'clinic-queue-management');
            }
        }
        return $new;
    }

    /**
     * מציג את כמות הטיפולים בעמודה, עם קישור לטבלה מסוננת.
     *
     * @param string $content     תוכן ברירת מחדל.
     * @param string $column_name שם העמודה.
     * @param int    $term_id    ID של ה-term (התמחות).
     * @return string
     */
    public function render_treatments_count_column($content, $column_name, $term_id) {
        if ($column_name !== 'clinic_queue_treatments_count') {
            return $content;
        }
        $treatments = Clinic_Queue_Specialty_Taxonomy::get_treatments_by_specialty($term_id);
        $count      = count($treatments);
        if ($count === 0) {
            return '<span class="clinic-queue-no-specialties">0</span>';
        }
        $url = add_query_arg(
            array(
                'taxonomy'         => Clinic_Queue_Specialty_Taxonomy::get_treatments_taxonomy(),
                'specialty_filter' => $term_id,
            ),
            admin_url('edit-tags.php')
        );
        if (!empty($_GET['post_type'])) {
            $url = add_query_arg('post_type', sanitize_text_field(wp_unslash($_GET['post_type'])), $url);
        }
        return '<a href="' . esc_url($url) . '">' . esc_html((string) $count) . '</a>';
    }

    /**
     * מסנן את רשימת סוגי הטיפולים לפי התמחות כשמגיעים מקישור.
     *
     * @param array  $args      ארגומנטים ל-get_terms.
     * @param array  $taxonomies רשימת טקסונומיות.
     * @return array
     */
    public function filter_treatments_by_specialty($args, $taxonomies) {
        $filter_id = isset($_GET['specialty_filter']) ? absint($_GET['specialty_filter']) : 0;
        if ($filter_id === 0) {
            return $args;
        }
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field(wp_unslash($_GET['taxonomy'])) : '';
        if ($taxonomy !== Clinic_Queue_Specialty_Taxonomy::get_treatments_taxonomy()) {
            return $args;
        }
        $tax_array = is_array($taxonomies) ? $taxonomies : array($taxonomies);
        if (!in_array(Clinic_Queue_Specialty_Taxonomy::get_treatments_taxonomy(), $tax_array, true)) {
            return $args;
        }
        $meta_query = isset($args['meta_query']) ? $args['meta_query'] : array();
        if (!is_array($meta_query)) {
            $meta_query = array();
        }
        $meta_query[] = array(
            'key'     => 'specialty_id',
            'value'   => $filter_id,
            'compare' => '=',
        );
        $args['meta_query'] = $meta_query;
        return $args;
    }

    /**
     * מציג הודעה כשטבלת הטיפולים מסוננת לפי התמחות.
     */
    public function render_specialty_filter_notice() {
        $filter_id = isset($_GET['specialty_filter']) ? absint($_GET['specialty_filter']) : 0;
        if ($filter_id === 0) {
            return;
        }
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field(wp_unslash($_GET['taxonomy'])) : '';
        if ($taxonomy !== Clinic_Queue_Specialty_Taxonomy::get_treatments_taxonomy()) {
            return;
        }
        $term = get_term($filter_id, Clinic_Queue_Specialty_Taxonomy::get_specialties_taxonomy());
        if (!$term || is_wp_error($term)) {
            return;
        }
        $clear_url = add_query_arg(
            array(
                'taxonomy'   => Clinic_Queue_Specialty_Taxonomy::get_treatments_taxonomy(),
                'post_type'  => isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : 'clinics',
            ),
            admin_url('edit-tags.php')
        );
        ?>
        <div class="notice notice-info">
            <p>
                <?php
                printf(
                    /* translators: 1: specialty name, 2: clear filter link */
                    esc_html__('מציג טיפולים המשויכים להתמחות "%1$s". %2$s', 'clinic-queue-management'),
                    esc_html($term->name),
                    '<a href="' . esc_url($clear_url) . '">' . esc_html__('הצג את כל הטיפולים', 'clinic-queue-management') . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}
