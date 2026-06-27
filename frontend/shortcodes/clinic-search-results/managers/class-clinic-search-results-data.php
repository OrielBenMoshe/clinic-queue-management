<?php
/**
 * Clinic Search Results — Data Manager
 *
 * Handles WP_Query for clinic IDs, card rendering, and inline booking-calendar
 * HTML generation (bypasses do_shortcode for performance).
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data services for the [clinic_search_results] shortcode.
 *
 * Responsibilities:
 *  - Querying clinic post IDs (with optional external search fallback)
 *  - Building full card HTML, including the embedded booking-calendar widget
 *  - Resolving specialty labels from taxonomy or meta fallback
 */
class Clinic_Search_Results_Data {

    /** @var self|null */
    private static $instance = null;

    /** @var Booking_Calendar_Data_Provider|null Lazy-loaded on first calendar render. */
    private $calendar_provider = null;

    /** Maximum plain-text characters shown in the "about" section of a card. */
    private const ABOUT_MAX_LENGTH = 120;

    /** Maximum specialty tags rendered before a "+N more" badge. */
    private const MAX_SPEC_TAGS = 4;

    /**
     * @return self
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Return a paginated set of published clinic post IDs.
     *
     * Falls back to a custom external-search function (mad_find_matching_clinics)
     * when available and a search term is provided.
     *
     * @param array{search?: string, offset?: int, per_page?: int} $params
     * @return array{ids: int[], total: int}
     */
    public function get_clinic_ids(array $params = []): array {
        $search   = sanitize_text_field($params['search'] ?? '');
        $offset   = max(0, intval($params['offset'] ?? 0));
        $per_page = max(1, intval($params['per_page'] ?? 10));

        $base_args = [
            'post_type'              => 'clinics',
            'post_status'            => 'publish',
            'posts_per_page'         => $per_page,
            'offset'                 => $offset,
            'fields'                 => 'ids',
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        if (!empty($search) && function_exists('mad_find_matching_clinics')) {
            return $this->query_by_external_search($search, $offset, $per_page, $base_args);
        }

        if (!empty($search)) {
            $base_args['s'] = $search;
        }

        $query = new WP_Query($base_args);

        return [
            'ids'   => array_map('intval', $query->posts),
            'total' => intval($query->found_posts),
        ];
    }

    /**
     * Build and return the full HTML for a single clinic result card.
     *
     * The booking-calendar widget is embedded inline — no secondary AJAX call is
     * needed from the browser. The MutationObserver in booking-calendar-init.js
     * automatically initializes each widget when it lands in the DOM.
     *
     * @param int $clinic_id
     * @return string
     */
    public function render_card(int $clinic_id): string {
        $clinic_id = absint($clinic_id);

        $name      = get_the_title($clinic_id);
        $permalink = get_permalink($clinic_id);
        $img_id    = get_post_meta($clinic_id, 'clinc_img', true);
        $img_url   = $img_id ? (string) wp_get_attachment_image_url((int) $img_id, 'medium') : '';
        $about     = (string) get_post_meta($clinic_id, 'clinic_more_info', true);
        $address   = (string) get_post_meta($clinic_id, 'clinic_address', true);
        $spec_terms = $this->resolve_specialties($clinic_id);

        $about_clean = wp_strip_all_tags($about);
        if (mb_strlen($about_clean) > self::ABOUT_MAX_LENGTH) {
            $about_clean = mb_substr($about_clean, 0, self::ABOUT_MAX_LENGTH) . '...';
        }

        $max_tags   = self::MAX_SPEC_TAGS;
        $shown_tags = array_slice($spec_terms, 0, $max_tags);
        $extra_tags = count($spec_terms) - count($shown_tags);

        $calendar_html = $this->build_calendar_html($clinic_id);

        ob_start();
        include __DIR__ . '/../views/clinic-search-results-card.php';

        return ob_get_clean();
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Query via external matching function (e.g. Elasticsearch / mad_find_matching_clinics).
     *
     * Caches the raw match result for 10 minutes to avoid repeated external calls.
     * Falls back to raw IDs if the published-clinic filter eliminates everything
     * (guards against mismatched post_type slugs on some sites).
     *
     * @param string $search
     * @param int    $offset
     * @param int    $per_page
     * @param array  $base_args Base WP_Query args.
     * @return array{ids: int[], total: int}
     */
    private function query_by_external_search(
        string $search,
        int $offset,
        int $per_page,
        array $base_args
    ): array {
        $cache_key    = 'csr_matching_' . md5($search);
        $matching_ids = get_transient($cache_key);

        if ($matching_ids === false) {
            $raw          = mad_find_matching_clinics($search);
            $matching_ids = is_array($raw)
                ? array_values(array_unique(array_map('intval', $raw)))
                : [];
            set_transient($cache_key, $matching_ids, 10 * MINUTE_IN_SECONDS);
        } else {
            $matching_ids = array_values(array_unique(array_map('intval', (array) $matching_ids)));
        }

        $filtered_ids = $this->filter_published_clinic_ids($matching_ids);

        // Graceful fallback when the filter eliminates all results.
        if (empty($filtered_ids) && !empty($matching_ids)) {
            $filtered_ids = $matching_ids;
        }

        if (empty($filtered_ids)) {
            return ['ids' => [], 'total' => 0];
        }

        $total         = count($filtered_ids);
        $paginated_ids = array_slice($filtered_ids, $offset, $per_page);

        $args                  = $base_args;
        $args['post__in']      = $paginated_ids;
        $args['orderby']       = 'post__in';
        $args['offset']        = 0;
        $args['posts_per_page'] = count($paginated_ids);

        $query = new WP_Query($args);

        return [
            'ids'   => array_map('intval', $query->posts),
            'total' => $total,
        ];
    }

    /**
     * Keep only IDs that belong to published clinic posts.
     *
     * @param int[] $ids
     * @return int[]
     */
    private function filter_published_clinic_ids(array $ids): array {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return array_values(array_filter($ids, static function (int $id): bool {
            return $id > 0
                && get_post_type($id) === 'clinics'
                && get_post_status($id) === 'publish';
        }));
    }

    /**
     * Generate the booking-calendar widget HTML for a clinic card.
     *
     * Key design decision: this method directly includes the booking-calendar view
     * template instead of calling do_shortcode(). Benefits:
     *  - No shortcode parsing overhead
     *  - No redundant enqueue_assets() calls in AJAX context
     *  - Scheduler data is embedded inline so BookingCalendarCore can initialise
     *    immediately without a secondary AJAX round-trip
     *
     * @param int $clinic_id
     * @return string
     */
    private function build_calendar_html(int $clinic_id): string {
        $provider        = $this->get_calendar_provider();
        $widget_id       = 'booking-calendar-' . wp_unique_id();
        $all_schedulers  = $provider ? $provider->get_schedulers_by_clinic($clinic_id) : [];

        $schedulers_array = [];
        if (is_array($all_schedulers)) {
            foreach ($all_schedulers as $scheduler_id => $scheduler_data) {
                if (is_array($scheduler_data)) {
                    $scheduler_data['id'] = $scheduler_id;
                    $schedulers_array[]   = $scheduler_data;
                }
            }
        }

        // Settings mirror the booking-calendar shortcode in clinic mode with 2 slot rows.
        $settings = [
            'mode'           => 'clinic',
            'clinic_id'      => $clinic_id,
            'doctor_id'      => null,
            'treatment_type' => '',
            'slot_rows'      => 2,
        ];

        // Calendar icon for the empty-state card and the slot-loading placeholder.
        // The SVG uses hardcoded pink strokes/fills; we replace the colour with gray
        // so it reads as a neutral placeholder in the listing context.
        // --color-gray-300 (#d1d5db) is used to match the shared CSS variable from base.css.
        $calendar_icon = class_exists('Clinic_Schedule_Form_Manager')
            ? str_replace('#D82466', '#d1d5db', Clinic_Schedule_Form_Manager::load_icon_from_assets('calendar-pink-icon.svg', 32, 32))
            : '';

        // Variables consumed by booking-calendar-html.php
        $empty_calendars            = empty($schedulers_array);
        $empty_state_message        = $empty_calendars
            ? __('לא קיימים יומנים פעילים למרפאה זו.', 'clinic-queue-management')
            : '';
        $empty_state_icon           = $calendar_icon;
        $loading_placeholder_icon   = $calendar_icon;
        $enable_mobile_cta          = true;  // Listing context always uses mobile CTA.
        $show_elementor_editor_hint = false;
        $treatments                 = [];

        ob_start();

        // Inline data script replaces wp_add_inline_script, which is a no-op in AJAX context.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode is safe
        echo '<script>window.bookingCalendarInitialDataByWidget=window.bookingCalendarInitialDataByWidget||{};'
            . 'window.bookingCalendarInitialDataByWidget[' . wp_json_encode($widget_id) . ']='
            . wp_json_encode([
                'schedulers' => $schedulers_array,
                'settings'   => $settings,
            ]) . ';</script>';

        include CLINIC_QUEUE_MANAGEMENT_PATH . 'frontend/shortcodes/booking-calendar/views/booking-calendar-html.php';

        return ob_get_clean();
    }

    /**
     * Resolve specialty labels from taxonomy first, then serialized meta as fallback.
     *
     * @param int $clinic_id
     * @return string[]
     */
    private function resolve_specialties(int $clinic_id): array {
        $terms = wp_get_post_terms($clinic_id, 'specialties', ['fields' => 'names']);

        if (!is_wp_error($terms) && !empty($terms)) {
            return array_unique($terms);
        }

        // Meta fallback: serialized array of key => 'true'/'false' or indexed string values.
        $raw  = get_post_meta($clinic_id, 'clinic_specialization', true);
        $data = maybe_unserialize($raw);

        if (!is_array($data)) {
            return [];
        }

        $specs = [];
        foreach ($data as $key => $value) {
            if ($value === 'true' || $value === true) {
                $specs[] = (string) $key;
            } elseif (is_int($key) && is_string($value) && $value !== 'false' && $value !== 'true') {
                $specs[] = trim($value);
            }
        }

        return array_unique($specs);
    }

    /**
     * Lazy-load and cache the calendar data provider.
     *
     * Handles the case where this method is called from admin-ajax.php before
     * the booking-calendar shortcode has been fully bootstrapped.
     *
     * @return Booking_Calendar_Data_Provider|null
     */
    private function get_calendar_provider(): ?Booking_Calendar_Data_Provider {
        if ($this->calendar_provider !== null) {
            return $this->calendar_provider;
        }

        if (!class_exists('Booking_Calendar_Data_Provider')) {
            $path = CLINIC_QUEUE_MANAGEMENT_PATH
                . 'frontend/shortcodes/booking-calendar/managers/class-calendar-data-provider.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }

        if (class_exists('Booking_Calendar_Data_Provider')) {
            $this->calendar_provider = Booking_Calendar_Data_Provider::get_instance();
        }

        return $this->calendar_provider;
    }
}
