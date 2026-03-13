<?php
/**
 * Booking Calendar – Expanded Modal View Template
 *
 * ה-skeleton הסטטי של מודל "כל התורים".
 * מוסתר כברירת מחדל; JS מציג, ממלא ומסתיר אותו.
 *
 * מוכלל מתוך views/booking-calendar-html.php.
 * כל תוכן דינמי (אפשרויות ב-selects, רשימת תורים) ממולא על-ידי
 * booking-calendar-expanded-modal.js.
 *
 * @package ClinicQueue\Frontend\Shortcodes
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="bcm-expanded-modal" class="bcm-overlay" role="dialog" aria-modal="true"
     dir="rtl" aria-label="<?php esc_attr_e( 'כל התורים הזמינים', 'clinic-queue' ); ?>"
     aria-hidden="true">

    <div class="bcm-dialog" role="document">

        <!-- Header -->
        <div class="bcm-header">
            <h2 class="bcm-title"><?php esc_html_e( 'כל התורים', 'clinic-queue' ); ?></h2>
            <button type="button" class="bcm-close-btn"
                    aria-label="<?php esc_attr_e( 'סגור חלון', 'clinic-queue' ); ?>">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                    <path d="M1 1l16 16M17 1L1 17" stroke="currentColor"
                          stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <!-- Filters -->
        <div class="bcm-filters">

            <!-- Selects row: treatment type + scheduler (גלובלי: form-field-select) -->
            <div class="bcm-filters-row bcm-filters-row--selects">
                <select class="form-field-select bcm-select bcm-filter" data-filter="treatmentType"
                        aria-label="<?php esc_attr_e( 'סוג טיפול', 'clinic-queue' ); ?>">
                    <option value=""><?php esc_html_e( 'סוג טיפול', 'clinic-queue' ); ?></option>
                    <!-- Options populated by booking-calendar-expanded-modal.js -->
                </select>

                <select class="form-field-select bcm-select bcm-filter" data-filter="schedulerId"
                        aria-label="<?php esc_attr_e( 'רופא / מטפל', 'clinic-queue' ); ?>">
                    <option value=""><?php esc_html_e( 'רופא / מטפל', 'clinic-queue' ); ?></option>
                    <!-- Options populated by booking-calendar-expanded-modal.js -->
                </select>
            </div>

            <!-- Date & time range row -->
            <div class="bcm-filters-row bcm-filters-row--dates">
                <div class="bcm-field">
                    <label class="bcm-label" for="bcm-from-date">
                        <?php esc_html_e( 'מתאריך', 'clinic-queue' ); ?>
                    </label>
                    <input type="date" id="bcm-from-date"
                           class="bcm-input bcm-filter" data-filter="fromDate">
                </div>

                <div class="bcm-field">
                    <label class="bcm-label" for="bcm-to-date">
                        <?php esc_html_e( 'עד תאריך', 'clinic-queue' ); ?>
                    </label>
                    <input type="date" id="bcm-to-date"
                           class="bcm-input bcm-filter" data-filter="toDate">
                </div>

                <div class="bcm-field">
                    <label class="bcm-label" for="bcm-from-time">
                        <?php esc_html_e( 'משעה', 'clinic-queue' ); ?>
                    </label>
                    <input type="time" id="bcm-from-time"
                           class="bcm-input bcm-filter" data-filter="fromTime">
                </div>

                <div class="bcm-field">
                    <label class="bcm-label" for="bcm-to-time">
                        <?php esc_html_e( 'עד שעה', 'clinic-queue' ); ?>
                    </label>
                    <input type="time" id="bcm-to-time"
                           class="bcm-input bcm-filter" data-filter="toTime">
                </div>
            </div>

            <!-- Days of week + update button -->
            <div class="bcm-filters-row bcm-filters-row--days">
                <?php
                $days_of_week = [
                    '0' => "א׳",
                    '1' => "ב׳",
                    '2' => "ג׳",
                    '3' => "ד׳",
                    '4' => "ה׳",
                    '5' => "ו׳",
                    '6' => "ש׳",
                ];
                foreach ( $days_of_week as $value => $label ) :
                ?>
                    <label class="bcm-day-label">
                        <input type="checkbox" class="bcm-day-cb"
                               value="<?php echo esc_attr( $value ); ?>" checked>
                        <span class="bcm-day-pill"><?php echo esc_html( $label ); ?></span>
                    </label>
                <?php endforeach; ?>

                <button type="button" class="btn btn-secondary bcm-update-btn">
                    <?php esc_html_e( 'עדכון תוצאות', 'clinic-queue' ); ?>
                </button>
            </div>

        </div><!-- /.bcm-filters -->

        <!-- Results -->
        <div class="bcm-results" role="region"
             aria-label="<?php esc_attr_e( 'תורים זמינים', 'clinic-queue' ); ?>">
            <div class="bcm-results-list">
                <!-- Populated by booking-calendar-expanded-modal.js -->
            </div>
            <div class="bcm-load-more-wrap" style="display:none;">
                <button type="button" class="btn btn-secondary bcm-load-more-btn">
                    <?php esc_html_e( 'טען עוד', 'clinic-queue' ); ?>
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="bcm-footer">
            <button type="button" class="btn btn-primary bcm-book-btn" disabled aria-disabled="true">
                <?php esc_html_e( 'הזמן תור', 'clinic-queue' ); ?>
            </button>
        </div>

    </div><!-- /.bcm-dialog -->

</div><!-- /#bcm-expanded-modal -->
