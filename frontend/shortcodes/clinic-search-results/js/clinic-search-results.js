/**
 * Clinic Search Results — [clinic_search_results] shortcode JS
 *
 * Handles AJAX-powered clinic card loading, pagination, and search integration.
 *
 * Architecture note:
 *  Each card returned by the server already contains the full booking-calendar
 *  widget HTML with inline scheduler data. The MutationObserver registered in
 *  booking-calendar-init.js automatically calls new BookingCalendarCore(el) for
 *  every .booking-calendar-shortcode that enters the DOM — no calendar-specific
 *  code is needed here.
 *
 * Config is injected via wp_localize_script as window.csrListingData:
 *  { ajaxUrl, search, perPage, i18n: { loading, loadMore, loadingMore,
 *    noResults, noResultsHint, errorLoad } }
 */
(function ($) {
    'use strict';

    // -------------------------------------------------------------------------
    // Config
    // -------------------------------------------------------------------------

    var cfg     = window.csrListingData || {};
    var ajaxUrl = cfg.ajaxUrl || '';
    var i18n    = cfg.i18n   || {};

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    var $wrap;
    var $list;
    var $count;
    var $loadMoreWrap;
    var $loadMoreBtn;

    var search    = $.trim(cfg.search || '');
    var offset    = 0;
    var isLoading = false;

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    function boot() {
        $wrap        = $('.csr-wrap').last();
        $list        = $wrap.find('#csr-list');
        $count       = $wrap.find('#csr-count');
        $loadMoreWrap = $wrap.find('#csr-load-more-wrap');
        $loadMoreBtn = $wrap.find('#csr-load-more-btn');

        if (!$wrap.length) {
            return;
        }

        bindEvents();

        // Sync search from URL in case a query-string parameter exists on page load
        // (e.g. user navigated here from a search results page).
        syncSearchFromUrl();

        loadResults(false);
    }

    // -------------------------------------------------------------------------
    // Event bindings
    // -------------------------------------------------------------------------

    function bindEvents() {
        $loadMoreBtn.on('click', function () {
            loadResults(true);
        });

        // Intercept standard and Jet/Elementor search form submissions.
        $(document).on(
            'submit',
            'form[role="search"], form.search-form, form.jet-search-filter__form, form.elementor-search-form',
            handleSearchFormSubmit
        );

        // Live search (debounced): update listing while the user types.
        var liveTimer = null;
        $(document).on('input', 'input[name="s"], input[type="search"]', function () {
            var $input = $(this);

            if (!$input.closest(
                'form[role="search"], form.search-form, form.jet-search-filter__form, form.elementor-search-form'
            ).length) {
                return;
            }

            clearTimeout(liveTimer);
            liveTimer = setTimeout(function () {
                var next = $.trim($input.val());
                if (next !== search) {
                    reloadListing(next);
                }
            }, 500);
        });

        // Sync when the user navigates back/forward without a full page reload.
        window.addEventListener('popstate', function () {
            if (syncSearchFromUrl()) {
                reloadListing(search);
            }
        });
    }

    function handleSearchFormSubmit(e) {
        var $form  = $(this);
        var $input = $form.find('input[name="s"], input[type="search"]').first();

        if (!$input.length) {
            return;
        }

        var next = $.trim($input.val());
        if (next === search) {
            return;
        }

        e.preventDefault();
        reloadListing(next);

        // Reflect the new search term in the URL without a hard reload.
        if (window.history && window.history.pushState) {
            var url = new URL(window.location.href);
            url.searchParams.set('s', next);
            window.history.pushState({}, '', url.toString());
        }
    }

    // -------------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------------

    /**
     * Fetch a page of clinic cards from the server.
     *
     * @param {boolean} append  true = load-more, false = fresh search / first load.
     */
    function loadResults(append) {
        if (isLoading) {
            return;
        }

        isLoading = true;

        if (!append) {
            offset = 0;
            $count.prop('hidden', true).html('');
            $list.html(buildMainLoader()).attr('aria-busy', 'true');
            $loadMoreWrap.prop('hidden', true);
        } else {
            $loadMoreBtn
                .prop('disabled', true)
                .html(
                    '<span class="csr-btn-spinner" aria-hidden="true"></span>'
                    + '<span>' + escapeHtml(i18n.loadingMore || 'טוען...') + '</span>'
                );
        }

        $.ajax({
            url:      ajaxUrl,
            type:     'POST',
            dataType: 'json',
            data: {
                action: 'csr_load_results',
                search: search,
                offset: offset,
            },
        })
        .done(function (res) {
            handleLoadDone(res, append);
        })
        .fail(function () {
            handleLoadFail(append);
        })
        .always(function () {
            isLoading = false;
            $list.attr('aria-busy', 'false');
        });
    }

    function handleLoadDone(res, append) {
        if (!res || !res.success) {
            if (!append) {
                $list.html(buildErrorState());
            }
            return;
        }

        var data  = res.data || {};
        var html  = data.html  || '';
        var total = parseInt(data.total || 0, 10);

        // Echo back the search term echoed by the server (keeps client/server in sync).
        if (typeof data.search === 'string') {
            search = $.trim(data.search);
        }

        renderCount(total);

        if (!append) {
            $list.html(html || buildNoResults(search));
        } else if (html) {
            $list.append(html);
        }

        offset = parseInt(data.nextOffset || (offset + 10), 10);

        if (data.has_more) {
            $loadMoreWrap.prop('hidden', false);
            $loadMoreBtn.prop('disabled', false).text(i18n.loadMore || 'טען עוד תוצאות');
        } else {
            $loadMoreWrap.prop('hidden', true);
        }
    }

    function handleLoadFail(append) {
        if (!append) {
            $count.prop('hidden', true);
            $list.html(buildErrorState());
        } else {
            $loadMoreBtn.prop('disabled', false).text(i18n.loadMore || 'טען עוד תוצאות');
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Restart the listing from the first page with a new search term.
     *
     * @param {string} nextSearch
     */
    function reloadListing(nextSearch) {
        search = $.trim(nextSearch || '');
        loadResults(false);
    }

    /**
     * Sync the internal search state from common URL query-string parameters.
     *
     * @returns {boolean} true if the search value changed.
     */
    function syncSearchFromUrl() {
        var params    = new URLSearchParams(window.location.search);
        var urlSearch = $.trim(
            params.get('s')             ||
            params.get('search')        ||
            params.get('q')             ||
            params.get('keyword')       ||
            params.get('clinic_search') ||
            params.get('mad_search')    ||
            ''
        );

        if (urlSearch && urlSearch !== search) {
            search = urlSearch;
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // UI builders
    // -------------------------------------------------------------------------

    /**
     * Update the results-count element with an accessible string.
     *
     * @param {number} total
     */
    function renderCount(total) {
        var html;

        if (search) {
            html = 'נמצאו <strong>' + total + '</strong> תוצאות עבור "'
                + escapeHtml(search) + '"';
        } else {
            html = '<strong>' + total + '</strong> מרפאות';
        }

        $count.html(html).prop('hidden', false);
    }

    function buildMainLoader() {
        return '<div class="csr-main-loader">'
            + '<div class="csr-spinner" aria-hidden="true"></div>'
            + '<span class="csr-main-loader__text">' + escapeHtml(i18n.loading || 'טוען תוצאות') + '</span>'
            + '</div>';
    }

    function buildNoResults(query) {
        var msg = escapeHtml(i18n.noResults || 'לא נמצאו תוצאות');
        if (query) {
            msg += ' עבור "' + escapeHtml(query) + '"';
        }

        return '<div class="csr-no-results">'
            + '<div class="csr-no-results__icon" aria-hidden="true">🔍</div>'
            + '<p class="csr-no-results__message">' + msg + '</p>'
            + '<p class="csr-no-results__hint">'
            +     escapeHtml(i18n.noResultsHint || 'נסה לחפש מילות מפתח אחרות או תחום רפואי שונה')
            + '</p>'
            + '</div>';
    }

    function buildErrorState() {
        return '<div class="csr-no-results">'
            + '<p class="csr-no-results__message">'
            +     escapeHtml(i18n.errorLoad || 'אירעה שגיאה בטעינת התוצאות')
            + '</p>'
            + '</div>';
    }

    /** Safe HTML-escape using jQuery's text-node trick. */
    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Exposed on window so external scripts (search widgets, filters, etc.)
     * can trigger a listing reload without knowing the internal implementation.
     *
     * Usage:
     *   window.CsrClinicListing.reload('cardiology');
     *   window.CsrClinicListing.getSearch();
     */
    window.CsrClinicListing = {
        reload:    reloadListing,
        getSearch: function () { return search; },
    };

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    $(boot);

})(jQuery);
