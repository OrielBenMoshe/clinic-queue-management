<?php
/**
 * רישום שתי טקסונומיות: התמחויות (specialties) וסוגי טיפולים (treatment_types).
 * קישור many-to-many: כל treatment term שומר specialty_ids ב-term meta.
 * ייבוא terms מקובץ JSON שנוצר מ-csv-to-json.mjs.
 *
 * @package ClinicQueue
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_Queue_Specialty_Taxonomy
 *
 * מנהל שתי טקסונומיות נפרדות עם קישור many-to-many:
 *
 * 1. **specialties** (התמחויות): משויכת ל-doctors, clinics, schedules
 * 2. **treatment_types** (סוגי טיפולים): משויכת ל-doctors, clinics, schedules
 * 3. **קישור many-to-many**: כל treatment term שומר `specialty_ids` ב-term meta
 *
 * ממשק אדמין (תפריט ייבוא, שדות שיוך, עמודות) – ב-admin/handlers/class-treatment-specialty-handler.php
 */
class Clinic_Queue_Specialty_Taxonomy {

    const TAXONOMY_SPECIALTIES = 'specialties';
    const TAXONOMY_TREATMENTS  = 'treatment_types';
    const SEEDED_OPTION        = 'clinic_queue_specialties_seeded';
    const JSON_PATH            = 'core/out-specialties.json';

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_taxonomies'), 5);
        add_filter('get_terms', array($this, 'augment_treatment_terms_with_specialty'), 10, 3);
        add_action('rest_api_init', array($this, 'register_rest_fields'));
    }

    /**
     * רישום שדה specialty ב-REST API של taxonomy treatment_types.
     * ללא רישום זה, מאפיין specialty שמוסף ב-augment_treatment_terms_with_specialty
     * לא יופיע בתגובת REST API (ה-controller ממפה רק שדות מהסכימה הרשומה).
     *
     * @return void
     */
    public function register_rest_fields() {
        register_rest_field(
            self::TAXONOMY_TREATMENTS,
            'specialty',
            array(
                'get_callback' => function( $term_arr ) {
                    $term_id = (int) $term_arr['id'];
                    $sid     = self::get_specialty_id_of_treatment( $term_id );
                    if ( $sid > 0 ) {
                        $s = get_term( $sid, self::TAXONOMY_SPECIALTIES );
                        if ( $s && ! is_wp_error( $s ) ) {
                            return array(
                                'id'   => (int) $s->term_id,
                                'name' => $s->name,
                            );
                        }
                    }
                    return null;
                },
                'schema' => array(
                    'description' => 'ההתמחות המשויכת לסוג הטיפול',
                    'type'        => 'object',
                    'context'     => array( 'view', 'edit' ),
                    'properties'  => array(
                        'id'   => array( 'type' => 'integer' ),
                        'name' => array( 'type' => 'string' ),
                    ),
                ),
            )
        );
    }

    /**
     * מוסיף לכל term של treatment_types את specialties: מערך של אובייקטים { id, name }.
     *
     * @param array $terms      מערך התוצאות מ-get_terms.
     * @param array $taxonomies טקסונומיות שנשאלו.
     * @param array $args       ארגומנטים של השאילתא.
     * @return array
     */
    public function augment_treatment_terms_with_specialty($terms, $taxonomies, $args) {
        if (is_wp_error($terms) || empty($terms) || !in_array(self::TAXONOMY_TREATMENTS, (array) $taxonomies, true)) {
            return $terms;
        }
        foreach ($terms as $term) {
            if (!is_object($term) || !isset($term->term_id)) {
                continue;
            }
            unset($term->specialties);
            
            $sid = self::get_specialty_id_of_treatment($term->term_id);
            $term->specialty = null;
            if ($sid > 0) {
                $s = get_term($sid, self::TAXONOMY_SPECIALTIES);
                if ($s && !is_wp_error($s)) {
                    $term->specialty = array('id' => (int) $s->term_id, 'name' => $s->name);
                }
            }
        }
        return $terms;
    }

    /**
     * רישום שתי הטקסונומיות: specialties (אם לא קיימת) ו-treatment_types (חדשה).
     * שיוך ל-doctors, clinics, schedules.
     */
    public function register_taxonomies() {
        $cpt_slugs = array('doctors', 'clinics', 'schedules');
        $cpt_slugs = apply_filters('clinic_queue_specialty_taxonomy_object_types', $cpt_slugs);

        if (!taxonomy_exists(self::TAXONOMY_SPECIALTIES)) {
            $labels = array(
                'name'              => __('התמחויות', 'clinic-queue-management'),
                'singular_name'     => __('התמחות', 'clinic-queue-management'),
                'search_items'      => __('חיפוש התמחויות', 'clinic-queue-management'),
                'all_items'         => __('כל ההתמחויות', 'clinic-queue-management'),
                'edit_item'         => __('עריכת התמחות', 'clinic-queue-management'),
                'update_item'       => __('עדכון התמחות', 'clinic-queue-management'),
                'add_new_item'      => __('הוספת התמחות', 'clinic-queue-management'),
                'new_item_name'     => __('שם התמחות חדש', 'clinic-queue-management'),
                'menu_name'         => __('התמחויות', 'clinic-queue-management'),
            );

            register_taxonomy(self::TAXONOMY_SPECIALTIES, $cpt_slugs, array(
                'labels'            => $labels,
                'hierarchical'      => false,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => false,
                'show_in_nav_menus' => true,
                'show_tagcloud'     => false,
                'show_in_rest'      => true,
                'rewrite'           => array('slug' => 'specialty'),
            ));
        } else {
            foreach ($cpt_slugs as $cpt) {
                register_taxonomy_for_object_type(self::TAXONOMY_SPECIALTIES, $cpt);
            }
        }

        if (!taxonomy_exists(self::TAXONOMY_TREATMENTS)) {
            $labels = array(
                'name'              => __('סוגי טיפולים', 'clinic-queue-management'),
                'singular_name'     => __('סוג טיפול', 'clinic-queue-management'),
                'search_items'      => __('חיפוש סוגי טיפולים', 'clinic-queue-management'),
                'all_items'         => __('כל סוגי הטיפולים', 'clinic-queue-management'),
                'edit_item'         => __('עריכת סוג טיפול', 'clinic-queue-management'),
                'update_item'       => __('עדכון סוג טיפול', 'clinic-queue-management'),
                'add_new_item'      => __('הוספת סוג טיפול', 'clinic-queue-management'),
                'new_item_name'     => __('שם סוג טיפול חדש', 'clinic-queue-management'),
                'menu_name'         => __('סוגי טיפולים', 'clinic-queue-management'),
            );

            $treatment_cpt = array('doctors', 'clinics', 'schedules');
            $treatment_cpt = apply_filters('clinic_queue_treatment_taxonomy_object_types', $treatment_cpt);

            register_taxonomy(self::TAXONOMY_TREATMENTS, $treatment_cpt, array(
                'labels'            => $labels,
                'hierarchical'      => false,
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => false,
                'show_in_nav_menus' => true,
                'show_tagcloud'     => false,
                'show_in_rest'      => true,
                'rewrite'           => array('slug' => 'treatment-type'),
            ));
        }
    }

    /**
     * טוען קובץ JSON ויוצר terms בשתי טקסונומיות עם קישור many-to-many.
     *
     * @param string|null $json_path נתיב מלא ל-JSON; ברירת מחדל: core/out-specialties.json בתוסף.
     * @return bool true אם הייבוא הצליח, אחרת false.
     */
    public function seed_from_json($json_path = null) {
        if ($json_path === null) {
            $json_path = CLINIC_QUEUE_MANAGEMENT_PATH . self::JSON_PATH;
        }
        if (!is_readable($json_path)) {
            return false;
        }

        $json = file_get_contents($json_path);
        if ($json === false) {
            return false;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (empty($item['name']) || !isset($item['treatments']) || !is_array($item['treatments'])) {
                continue;
            }

            $spec_slug = !empty($item['slug']) ? $item['slug'] : sanitize_title($item['name']);
            $spec      = wp_insert_term($item['name'], self::TAXONOMY_SPECIALTIES, array('slug' => $spec_slug));

            if (is_wp_error($spec)) {
                $existing_term = get_term_by('slug', $spec_slug, self::TAXONOMY_SPECIALTIES);
                if (!$existing_term) {
                    continue;
                }
                $specialty_term_id = (int) $existing_term->term_id;
            } else {
                $specialty_term_id = (int) $spec['term_id'];
            }

            foreach ($item['treatments'] as $treatment_name) {
                $treatment_name = is_string($treatment_name) ? trim($treatment_name) : '';
                if ($treatment_name === '') {
                    continue;
                }

                $treatment_slug = sanitize_title($treatment_name);
                $treatment      = wp_insert_term($treatment_name, self::TAXONOMY_TREATMENTS, array('slug' => $treatment_slug));

                if (is_wp_error($treatment)) {
                    $existing_treatment = get_term_by('slug', $treatment_slug, self::TAXONOMY_TREATMENTS);
                    if (!$existing_treatment) {
                        continue;
                    }
                    $treatment_term_id = (int) $existing_treatment->term_id;
                } else {
                    $treatment_term_id = (int) $treatment['term_id'];
                }

                update_term_meta($treatment_term_id, 'specialty_id', $specialty_term_id);
                update_term_meta($treatment_term_id, 'specialty_ids', array($specialty_term_id));
            }
        }

        update_option(self::SEEDED_OPTION, true);
        return true;
    }

    /**
     * מחזיר את slug טקסונומיית ההתמחויות.
     *
     * @return string
     */
    public static function get_specialties_taxonomy() {
        return self::TAXONOMY_SPECIALTIES;
    }

    /**
     * מחזיר את slug טקסונומיית סוגי הטיפולים.
     *
     * @return string
     */
    public static function get_treatments_taxonomy() {
        return self::TAXONOMY_TREATMENTS;
    }

    /**
     * שליפת טיפולים לפי התמחות.
     *
     * @param int $specialty_term_id ID של term ההתמחות.
     * @return array מערך של treatment terms.
     */
    public static function get_treatments_by_specialty($specialty_term_id) {
        $all_treatments = get_terms(array(
            'taxonomy'   => self::TAXONOMY_TREATMENTS,
            'hide_empty' => false,
        ));

        if (is_wp_error($all_treatments) || empty($all_treatments)) {
            return array();
        }

        $filtered = array();
        foreach ($all_treatments as $term) {
            if (self::get_specialty_id_of_treatment($term->term_id) === (int) $specialty_term_id) {
                $filtered[] = $term;
            }
        }

        return $filtered;
    }

    /**
     * מחזיר את מזהי ההתמחות של טיפול (תמיד מערך עם פריט אחד – טיפול משויך להתמחות אחת).
     *
     * @param int $treatment_term_id ID של term הטיפול.
     * @return array מערך עם specialty term ID אחד או ריק.
     */
    public static function get_specialties_of_treatment($treatment_term_id) {
        $id = self::get_specialty_id_of_treatment($treatment_term_id);
        return $id > 0 ? array($id) : array();
    }

    /**
     * מחזיר את מזהה ההתמחות היחידה שאליה משויך הטיפול.
     *
     * @param int $treatment_term_id ID של term הטיפול.
     * @return int 0 אם אין שיוך.
     */
    public static function get_specialty_id_of_treatment($treatment_term_id) {
        $id = (int) get_term_meta($treatment_term_id, 'specialty_id', true);
        if ($id > 0) {
            return $id;
        }
        $ids = get_term_meta($treatment_term_id, 'specialty_ids', true);
        if (is_array($ids) && !empty($ids)) {
            return (int) $ids[0];
        }
        return 0;
    }
}
