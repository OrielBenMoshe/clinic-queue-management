<?php
/**
 * Schedule Form Manager
 * Helper functions and validation for schedule form shortcode
 * 
 * @package Clinic_Queue_Management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Clinic_Schedule_Form_Manager
 * Provides helper functions and validation for the schedule form
 */
class Clinic_Schedule_Form_Manager {
    
    /**
     * Generate time options for select dropdown
     * 
     * @param string $default_time Default selected time
     * @return string HTML options
     */
    public static function generate_time_options($default_time = '08:00') {
        $options = '';
        for($h = 0; $h < 24; $h++) { 
            for($m = 0; $m < 60; $m+=30) {
                $time = sprintf('%02d:%02d', $h, $m);
                $selected = ($time === $default_time) ? ' selected' : '';
                $options .= "<option value=\"{$time}\"{$selected}>{$time}</option>";
            }
        }
        return $options;
    }
    
    /**
     * Generate a single day time range HTML
     * 
     * @param string $day_key Day key (e.g., 'sunday')
     * @param string $day_label Day label in Hebrew
     * @param string $default_end Default end time (default: '18:00', except Friday: '16:00')
     * @param string $trash_icon SVG icon for delete button
     * @return string HTML for day time range
     */
    public static function generate_day_time_range($day_key, $day_label, $default_end = '18:00', $trash_icon = '') {
        ob_start();
        ?>
        <div class="day-time-range" data-day="<?php echo esc_attr($day_key); ?>" style="display:none;">
            <div class="time-ranges-list" data-day="<?php echo esc_attr($day_key); ?>">
                <div class="time-range-row">
                    <div class="jet-form-builder__row field-type-select-field is-filled">
                        <div class="jet-form-builder__label">
                            <div class="jet-form-builder__label-text time-label">מ-:</div>
                        </div>
                        <div class="jet-form-builder__field-wrap">
                            <select class="jet-form-builder__field select-field time-select from-time" name="<?php echo esc_attr($day_key); ?>_from_time[]">
                                <?php echo self::generate_time_options('08:00'); ?>
                            </select>
                        </div>
                    </div>
                    <div class="jet-form-builder__row field-type-select-field is-filled">
                        <div class="jet-form-builder__label">
                            <div class="jet-form-builder__label-text time-label">עד-:</div>
                        </div>
                        <div class="jet-form-builder__field-wrap">
                            <select class="jet-form-builder__field select-field time-select to-time" name="<?php echo esc_attr($day_key); ?>_to_time[]">
                                <?php echo self::generate_time_options($default_end); ?>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="remove-time-split-btn" style="display:none;"><?php echo $trash_icon; ?></button>
                </div>
            </div>
            <button type="link" class="add-time-split-btn" data-day="<?php echo esc_attr($day_key); ?>">
                <span>+</span> הוספת פיצול
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate treatment duration options
     * 
     * @param int $default_minutes Default selected duration in minutes
     * @return string HTML options
     */
    public static function generate_duration_options($default_minutes = 45) {
        $options = '';
        // 5 minutes to 6 hours in 5-minute increments
        for($minutes = 5; $minutes <= 360; $minutes += 5) {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            if ($hours > 0 && $mins > 0) {
                $label = $hours . ' שעות ו-' . $mins . ' דקות';
            } elseif ($hours > 0) {
                $label = $hours . ' שע' . ($hours == 1 ? 'ה' : 'ות');
            } else {
                $label = $mins . ' דקות';
            }
            $selected = ($minutes == $default_minutes) ? ' selected' : '';
            $options .= "<option value=\"{$minutes}\"{$selected}>{$label}</option>";
        }
        return $options;
    }
    
    /**
     * Load SVG icon from assets/images/icons (single global source).
     *
     * @param string $filename Filename e.g. 'trash.svg'
     * @param int    $width   Optional width for inline use (preserves aspect if only width set)
     * @param int    $height  Optional height for inline use
     * @return string SVG markup or empty string if file missing
     */
    public static function load_icon_from_assets($filename, $width = null, $height = null) {
        if (!defined('CLINIC_QUEUE_MANAGEMENT_PATH')) {
            return '';
        }
        $path = CLINIC_QUEUE_MANAGEMENT_PATH . 'assets/images/icons/' . $filename;
        if (!is_readable($path)) {
            return '';
        }
        $svg = file_get_contents($path);
        if ($svg === false || $svg === '') {
            return '';
        }
        $svg = trim($svg);
        if ($width !== null || $height !== null) {
            $w = $width !== null ? (int) $width : '';
            $h = $height !== null ? (int) $height : '';
            $svg = preg_replace('/\s*width="[^"]*"/', ' width="' . $w . '"', $svg, 1);
            $svg = preg_replace('/\s*height="[^"]*"/', ' height="' . $h . '"', $svg, 1);
        }
        return $svg;
    }

    /**
     * Get SVG icons array.
     * All icons are loaded from assets/images/icons (single global source).
     * Returns empty string for a key if the file is missing.
     *
     * @return array SVG icons (markup string per key)
     */
    public static function get_svg_icons() {
        return array(
            'google_calendar'   => self::load_icon_from_assets('google-calendar.svg', 73, 24),
            'clinix_logo'       => self::load_icon_from_assets('clinix-logo.svg', 78, 24),
            'calendar_icon'     => self::load_icon_from_assets('calendar-icon.svg', 120, 120),
            'trash_icon'        => self::load_icon_from_assets('trash.svg', 32, 32),
            'checkbox_checked'  => self::load_icon_from_assets('checkbox-checked.svg', 20, 20),
            'checkbox_unchecked' => self::load_icon_from_assets('checkbox-unchecked.svg', 22, 22),
        );
    }
    
    /**
     * Get days of week array
     *
     * @return array Days of week
     */
    public static function get_days_of_week() {
        return array(
            'sunday'    => 'יום ראשון',
            'monday'    => 'יום שני',
            'tuesday'   => 'יום שלישי',
            'wednesday' => 'יום רביעי',
            'thursday'  => 'יום חמישי',
            'friday'    => 'יום שישי',
            'saturday'  => 'יום שבת'
        );
    }

    /**
     * Mapping from form day key to working_days meta value (JetEngine field values::labels).
     * Used when saving schedule post meta so working_days reflects selected working days.
     *
     * @return array [ day_key => meta_value ] e.g. 'sunday' => 'sun'
     */
    public static function get_working_days_meta_mapping() {
        return array(
            'sunday'    => 'sun',
            'monday'    => 'mon',
            'tuesday'   => 'tue',
            'wednesday' => 'wed',
            'thursday'  => 'thu',
            'friday'    => 'fri',
            'saturday'  => 'sat'
        );
    }

    /**
     * Build working_days meta value array from schedule days data.
     * Only days that have at least one time range are included.
     *
     * @param array $days_data Same structure as schedule_data['days']: [ day_key => [ ['start_time'=>..., 'end_time'=>...], ... ] ]
     * @return array List of working_days meta values (e.g. ['sun', 'mon', 'wed']) for saving to post meta
     */
    public static function get_working_days_meta_values($days_data) {
        if (!is_array($days_data)) {
            return array();
        }
        $mapping = self::get_working_days_meta_mapping();
        $values = array();
        foreach ($days_data as $day_key => $time_ranges) {
            if (!is_array($time_ranges)) {
                continue;
            }
            $has_range = false;
            foreach ($time_ranges as $range) {
                if (!empty($range['start_time']) && !empty($range['end_time'])) {
                    $has_range = true;
                    break;
                }
            }
            if ($has_range && isset($mapping[ $day_key ])) {
                $values[] = $mapping[ $day_key ];
            }
        }
        return $values;
    }
    
    /**
     * Validate schedule data
     * 
     * @param array $data Schedule data
     * @return bool|WP_Error True if valid, WP_Error if not
     */
    public static function validate_schedule_data($data) {
        if (empty($data['days']) || !is_array($data['days'])) {
            return new WP_Error('no_days', 'No working days provided');
        }
        
        if (empty($data['treatments']) || !is_array($data['treatments'])) {
            return new WP_Error('no_treatments', 'No treatments provided');
        }
        
        return true;
    }
}

