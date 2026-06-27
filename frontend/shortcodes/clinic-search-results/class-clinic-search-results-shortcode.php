<?php
/**
 * Clinic Search Results Shortcode
 *
 * Registers [clinic_search_results] — an AJAX-powered clinic listing grid.
 * Each result card embeds a booking-calendar widget inline, eliminating the
 * per-card AJAX round-trip that the previous snippet-based approach required.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main controller for the [clinic_search_results] shortcode.
 *
 * Responsibilities:
 *  - Shortcode registration and rendering
 *  - AJAX handler registration (load-results)
 *  - CSS / JS asset enqueueing (own styles + booking-calendar dependency)
 */
class Clinic_Search_Results_Shortcode {

    /** @var self|null */
    private static $instance = null;

    /** @var Clinic_Search_Results_Data */
    private $data_manager;

    /**
     * @return self
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->data_manager = Clinic_Search_Results_Data::get_instance();
        $this->register_shortcode();
        $this->register_ajax_handlers();
    }

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Register the [clinic_search_results] shortcode.
     */
    private function register_shortcode(): void {
        add_shortcode('clinic_search_results', [$this, 'render_shortcode']);
    }

    /**
     * Register WordPress AJAX hooks (logged-in and public).
     */
    private function register_ajax_handlers(): void {
        add_action('wp_ajax_csr_load_results',        [$this, 'handle_load_results']);
        add_action('wp_ajax_nopriv_csr_load_results', [$this, 'handle_load_results']);
    }

    // =========================================================================
    // Shortcode rendering
    // =========================================================================

    /**
     * Render [clinic_search_results].
     *
     * Enqueues assets then outputs the static HTML skeleton; the JS takes over
     * immediately and populates the list via AJAX.
     *
     * @param array  $atts    Shortcode attributes (reserved for future use).
     * @param string $content Enclosed content (unused).
     * @return string HTML output.
     */
    public function render_shortcode($atts = [], $content = ''): string {
        $this->enqueue_assets();

        // Ensure the expanded-modal singleton (#bcm-expanded-modal) is printed
        // in wp_footer. Calendars here are built inline (not via render_shortcode),
        // so we must signal the booking-calendar class explicitly.
        if (class_exists('Clinic_Booking_Calendar_Shortcode')) {
            Clinic_Booking_Calendar_Shortcode::get_instance()->ensure_expanded_modal_rendered();
        }

        ob_start();
        include __DIR__ . '/views/clinic-search-results-html.php';

        return ob_get_clean();
    }

    // =========================================================================
    // AJAX handlers
    // =========================================================================

    /**
     * AJAX: return a page of clinic cards with embedded booking-calendar widgets.
     *
     * Each card already contains the full calendar HTML and scheduler data, so the
     * browser does not need to make further requests to render the calendars.
     *
     * Response shape:
     *  { success: true, data: { html, total, has_more, nextOffset, search } }
     */
    public function handle_load_results(): void {
        $search   = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
        $offset   = max(0, intval($_POST['offset'] ?? 0));
        $per_page = 10;

        $result = $this->data_manager->get_clinic_ids([
            'search'   => $search,
            'offset'   => $offset,
            'per_page' => $per_page,
        ]);

        $html = '';
        foreach ($result['ids'] as $clinic_id) {
            $html .= $this->data_manager->render_card($clinic_id);
        }

        wp_send_json_success([
            'html'       => $html,
            'total'      => $result['total'],
            'has_more'   => ($offset + $per_page) < $result['total'],
            'nextOffset' => $offset + $per_page,
            'search'     => $search,
        ]);
    }

    // =========================================================================
    // Assets
    // =========================================================================

    /**
     * Enqueue CSS and JS for the listing and its embedded booking-calendar widgets.
     *
     * Script data is injected once via wp_localize_script so the JS module can
     * read ajaxUrl, the initial search term, and i18n strings without any inline
     * PHP in the view or the JS file.
     */
    private function enqueue_assets(): void {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        // Shared base styles: CSS variables, typography, keyframes.
        wp_enqueue_style(
            'clinic-queue-base',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/base.css',
            [],
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        // Booking-calendar shared styles: empty-state card, booking-calendar-spin keyframe,
        // slot grid, etc. — referenced by .csr-spinner and the embedded calendar widgets.
        wp_enqueue_style(
            'booking-calendar-style',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shared/appointments-calendar.css',
            ['clinic-queue-base'],
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        // Listing-specific styles: card layout, logo, tags, load-more button, responsive.
        wp_enqueue_style(
            'clinic-search-results-css',
            CLINIC_QUEUE_MANAGEMENT_URL . 'assets/css/shortcodes/clinic-search-results.css',
            ['clinic-queue-base', 'booking-calendar-style'],
            CLINIC_QUEUE_MANAGEMENT_VERSION
        );

        // Booking-calendar widget JS/CSS (select2, date-picker, all modules).
        // Called via the public bridge method to avoid duplicating the enqueue logic.
        Clinic_Booking_Calendar_Shortcode::get_instance()->ensure_assets_loaded();

        // Listing JS — must load after booking-calendar modules so BookingCalendarCore exists.
        wp_enqueue_script(
            'clinic-search-results-js',
            CLINIC_QUEUE_MANAGEMENT_URL . 'frontend/shortcodes/clinic-search-results/js/clinic-search-results.js',
            ['jquery', 'booking-calendar-main'],
            CLINIC_QUEUE_MANAGEMENT_VERSION,
            true
        );

        wp_localize_script('clinic-search-results-js', 'csrListingData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'search'  => $this->get_search_from_request(),
            'perPage' => 10,
            'i18n'    => [
                'loading'       => __('טוען תוצאות', 'clinic-queue-management'),
                'loadMore'      => __('טען עוד תוצאות', 'clinic-queue-management'),
                'loadingMore'   => __('טוען...', 'clinic-queue-management'),
                'noResults'     => __('לא נמצאו תוצאות', 'clinic-queue-management'),
                'noResultsHint' => __('נסה לחפש מילות מפתח אחרות או תחום רפואי שונה', 'clinic-queue-management'),
                'errorLoad'     => __('אירעה שגיאה בטעינת התוצאות', 'clinic-queue-management'),
            ],
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Extract the search query from common URL parameter names.
     *
     * Checks several query-string keys used by different search integrations on the site.
     *
     * @return string Sanitized, unslashed search string.
     */
    private function get_search_from_request(): string {
        $candidates = ['s', 'search', 'q', 'keyword', 'clinic_search', 'mad_search'];

        foreach ($candidates as $key) {
            if (!empty($_GET[$key])) {
                return sanitize_text_field(wp_unslash($_GET[$key]));
            }
        }

        return '';
    }
}
