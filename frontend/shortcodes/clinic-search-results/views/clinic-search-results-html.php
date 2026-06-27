<?php
/**
 * Clinic Search Results — Main wrapper template.
 *
 * Renders the static skeleton; JavaScript (clinic-search-results.js) populates the
 * list via AJAX as soon as the page is ready. No dynamic PHP variables required.
 *
 * @package Clinic_Queue_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="csr-wrap" dir="rtl">

    <p class="csr-count" id="csr-count" aria-live="polite" hidden></p>

    <div id="csr-list" class="csr-list" aria-live="polite" aria-busy="true">
        <div class="csr-main-loader">
            <div class="csr-spinner" aria-hidden="true"></div>
            <span class="csr-main-loader__text"><?php esc_html_e('טוען תוצאות', 'clinic-queue-management'); ?></span>
        </div>
    </div>

    <div class="csr-load-more-wrap" id="csr-load-more-wrap" hidden>
        <button class="csr-load-more" id="csr-load-more-btn" type="button">
            <?php esc_html_e('טען עוד תוצאות', 'clinic-queue-management'); ?>
        </button>
    </div>

</div>
