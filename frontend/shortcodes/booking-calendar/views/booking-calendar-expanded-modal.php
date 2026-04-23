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
                <img src="<?php echo esc_url( CLINIC_QUEUE_MANAGEMENT_URL . 'assets/images/icons/close-icon.svg' ); ?>"
                     width="16"
                     height="16"
                     alt=""
                     aria-hidden="true">
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
            <div class="bcm-filters-row bcm-filters-row--dates"
                 data-range-hint="<?php esc_attr_e( '*ניתן לבחור טווח תאריכים של עד 3 שבועות', 'clinic-queue' ); ?>">
                <div class="bcm-field bcm-field--native">
                    <label class="bcm-label bcm-label--sr" for="bcm-from-date">
                        <?php esc_html_e( 'מתאריך', 'clinic-queue' ); ?>
                    </label>
                    <input type="date" id="bcm-from-date"
                           class="bcm-input bcm-input--native bcm-filter" data-filter="fromDate">
                    <span class="bcm-native-shell bcm-native-shell--date" aria-hidden="true">
                        <span class="bcm-native-text is-placeholder"
                              data-display-for="fromDate"
                              data-empty-text="<?php esc_attr_e( 'מתאריך', 'clinic-queue' ); ?>">
                            <?php esc_html_e( 'מתאריך', 'clinic-queue' ); ?>
                        </span>
                    </span>
                </div>

                <div class="bcm-field bcm-field--native">
                    <label class="bcm-label bcm-label--sr" for="bcm-to-date">
                        <?php esc_html_e( 'עד תאריך', 'clinic-queue' ); ?>
                    </label>
                    <input type="date" id="bcm-to-date"
                           class="bcm-input bcm-input--native bcm-filter" data-filter="toDate">
                    <span class="bcm-native-shell bcm-native-shell--date" aria-hidden="true">
                        <span class="bcm-native-text is-placeholder"
                              data-display-for="toDate"
                              data-empty-text="<?php esc_attr_e( 'עד תאריך', 'clinic-queue' ); ?>">
                            <?php esc_html_e( 'עד תאריך', 'clinic-queue' ); ?>
                        </span>
                    </span>
                </div>

                <div class="bcm-field bcm-field--native">
                    <label class="bcm-label bcm-label--sr" for="bcm-from-time">
                        <?php esc_html_e( 'מהשעה', 'clinic-queue' ); ?>
                    </label>
                    <input type="time" id="bcm-from-time"
                           class="bcm-input bcm-input--native bcm-input--time bcm-filter" data-filter="fromTime"
                           placeholder="">
                    <span class="bcm-native-shell bcm-native-shell--time" aria-hidden="true">
                        <span class="bcm-native-text is-placeholder"
                              data-display-for="fromTime"
                              data-empty-text="<?php esc_attr_e( 'מהשעה', 'clinic-queue' ); ?>">
                            <?php esc_html_e( 'מהשעה', 'clinic-queue' ); ?>
                        </span>
                    </span>
                    <button type="button"
                            class="bcm-time-clear-btn"
                            data-clear-filter="fromTime"
                            aria-label="<?php esc_attr_e( 'נקה שעת התחלה', 'clinic-queue' ); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="bcm-field bcm-field--native">
                    <label class="bcm-label bcm-label--sr" for="bcm-to-time">
                        <?php esc_html_e( 'עד השעה', 'clinic-queue' ); ?>
                    </label>
                    <input type="time" id="bcm-to-time"
                           class="bcm-input bcm-input--native bcm-input--time bcm-filter" data-filter="toTime"
                           placeholder="">
                    <span class="bcm-native-shell bcm-native-shell--time" aria-hidden="true">
                        <span class="bcm-native-text is-placeholder"
                              data-display-for="toTime"
                              data-empty-text="<?php esc_attr_e( 'עד השעה', 'clinic-queue' ); ?>">
                            <?php esc_html_e( 'עד השעה', 'clinic-queue' ); ?>
                        </span>
                    </span>
                    <button type="button"
                            class="bcm-time-clear-btn"
                            data-clear-filter="toTime"
                            aria-label="<?php esc_attr_e( 'נקה שעת סיום', 'clinic-queue' ); ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <!-- Days of week + update button -->
            <div class="bcm-filters-row bcm-filters-row--days">
                <?php
                $days_of_week = [
                    '0' => "יום א׳",
                    '1' => "יום ב׳",
                    '2' => "יום ג׳",
                    '3' => "יום ד׳",
                    '4' => "יום ה׳",
                    '5' => "יום ו׳",
                    '6' => "יום ש׳",
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
        </div>

        <!-- Footer -->
        <div class="bcm-footer">
            <button type="button" class="btn btn-primary bcm-book-btn" disabled aria-disabled="true">
                <?php esc_html_e( 'הזמן תור', 'clinic-queue' ); ?>
            </button>
        </div>

    </div><!-- /.bcm-dialog -->

</div><!-- /#bcm-expanded-modal -->
