<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-model.php';

/**
 * Scheduler Models
 * אובייקטי העברת נתונים עבור יומנים
 */

/**
 * Create Scheduler Model
 */
class Clinic_Queue_Create_Scheduler_Model extends Clinic_Queue_Base_Model {
    public $sourceCredentialsID;
    public $sourceSchedulerID;
    public $activeHours = null; // null for Google Calendar (nullable), array for DRWeb
    public $maxOverlappingMeeting = 1;
    public $overlappingDurationInMinutes = 0;
    
    public function validate() {
        $errors = array();
        
        if (empty($this->sourceCredentialsID) || !is_numeric($this->sourceCredentialsID)) {
            $errors[] = 'Source Credentials ID is required';
        }
        
        if (empty($this->sourceSchedulerID)) {
            $errors[] = 'Source Scheduler ID is required';
        }
        
        // activeHours can be null (for Google Calendar) or non-empty array (for DRWeb)
        // According to API schema: nullable: true, "Required - unless its a DRWeb scheduler"
        if ($this->activeHours !== null) {
            if (!is_array($this->activeHours) || empty($this->activeHours)) {
                $errors[] = 'Active hours must be null or a non-empty array';
            } else {
                // Validate each entry - expecting HH:mm:ss strings
                foreach ($this->activeHours as $index => $hour) {
                    if (!isset($hour['weekDay'])) {
                        $errors[] = "Active hour #{$index}: weekDay is required";
                    }
                    // Expecting HH:mm:ss strings, not objects with ticks
                    if (!isset($hour['fromUTC']) || !is_string($hour['fromUTC']) || !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hour['fromUTC'])) {
                        $errors[] = "Active hour #{$index}: fromUTC must be a valid HH:mm:ss string";
                    }
                    if (!isset($hour['toUTC']) || !is_string($hour['toUTC']) || !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hour['toUTC'])) {
                        $errors[] = "Active hour #{$index}: toUTC must be a valid HH:mm:ss string";
                    }
                }
            }
        }
        // Note: null is allowed (for Google Calendar, as per schema: nullable: true)
        
        return empty($errors) ? true : $errors;
    }
    
    public function to_array() {
        $result = array(
            'sourceCredentialsID' => intval($this->sourceCredentialsID),
            'sourceSchedulerID' => (string)$this->sourceSchedulerID,
            'maxOverlappingMeeting' => intval($this->maxOverlappingMeeting),
            'overlappingDurationInMinutes' => intval($this->overlappingDurationInMinutes)
        );
        
        // According to API schema: activeHours is nullable: true
        // For Google Calendar: send null (nullable field)
        // For DRWeb: send array with active hours (HH:mm:ss strings)
        if ($this->activeHours === null) {
            // Send null for Google Calendar (as per schema: nullable: true)
            $result['activeHours'] = null;
        } elseif (!empty($this->activeHours) && is_array($this->activeHours)) {
            // Send array with HH:mm:ss strings (as expected by the proxy API)
            $active_hours = array();
            foreach ($this->activeHours as $hour) {
                // Ensure we have the required fields
                if (!isset($hour['weekDay']) || !isset($hour['fromUTC']) || !isset($hour['toUTC'])) {
                    continue; // Skip invalid entries
                }
                
                // Data should already be in HH:mm:ss string format from convert_days_to_active_hours
                $active_hours[] = array(
                    'weekDay' => (string)$hour['weekDay'],
                    'fromUTC' => (string)$hour['fromUTC'],  // String: "HH:mm:ss"
                    'toUTC' => (string)$hour['toUTC']        // String: "HH:mm:ss"
                );
            }
            
            // Only set activeHours if we have valid entries
            if (!empty($active_hours)) {
                $result['activeHours'] = $active_hours;
            } else {
                $result['activeHours'] = null;
            }
        }
        
        return $result;
    }
}

/**
 * Update Scheduler Model – גוף בקשה ל-POST Scheduler/Update (Proxy)
 *
 * שדות לפי Swagger: schedulerID, isActive (חובה); maxOverlappingMeeting,
 * overlappingDurationInMinutes – אופציונליים (null / השמטה).
 *
 * @package Clinic_Queue_Management
 * @subpackage API\Models
 */
class Clinic_Queue_Update_Scheduler_Model extends Clinic_Queue_Base_Model {

    /**
     * גוף ה-JSON שיישלח לפרוקסי (מפתחות camelCase כנדרש upstream).
     *
     * @var array
     */
    private $body = array();

    /**
     * @param array $body מערך לפי to_array() לאחר בנייה.
     */
    public function __construct(array $body = array()) {
        $this->body = $body;
    }

    /**
     * @return true|array שגיאות ולידציה
     */
    public function validate() {
        $errors = array();

        if (empty($this->body['schedulerID']) || !is_numeric($this->body['schedulerID']) || intval($this->body['schedulerID']) <= 0) {
            $errors[] = 'schedulerID is required and must be a positive integer';
        }

        if (!array_key_exists('isActive', $this->body)) {
            $errors[] = 'isActive is required';
        } elseif (!is_bool($this->body['isActive'])) {
            $errors[] = 'isActive must be a boolean';
        }

        if (array_key_exists('maxOverlappingMeeting', $this->body) && $this->body['maxOverlappingMeeting'] !== null) {
            $m = (int) $this->body['maxOverlappingMeeting'];
            if ($m !== 0 && ($m < 2 || $m > 30)) {
                $errors[] = 'maxOverlappingMeeting must be null, 0 (disabled), or between 2 and 30';
            }
        }

        if (array_key_exists('overlappingDurationInMinutes', $this->body) && $this->body['overlappingDurationInMinutes'] !== null) {
            $o = (int) $this->body['overlappingDurationInMinutes'];
            if ($o !== 0 && ($o < 5 || $o > 120 || ($o % 5) !== 0)) {
                $errors[] = 'overlappingDurationInMinutes must be null, 0 (disabled), or 5–120 in steps of 5';
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * @return array גוף ל-post JSON לפרוקסי
     */
    public function to_array() {
        $out = array(
            'schedulerID' => (int) $this->body['schedulerID'],
            'isActive' => (bool) $this->body['isActive'],
        );

        if (array_key_exists('maxOverlappingMeeting', $this->body)) {
            $v = $this->body['maxOverlappingMeeting'];
            $out['maxOverlappingMeeting'] = ($v === null) ? null : (int) $v;
        }

        if (array_key_exists('overlappingDurationInMinutes', $this->body)) {
            $v = $this->body['overlappingDurationInMinutes'];
            $out['overlappingDurationInMinutes'] = ($v === null) ? null : (int) $v;
        }

        return $out;
    }
}

/**
 * Set Active Hours Model – גוף בקשה ל-POST /Scheduler/SetActiveHours
 *
 * פורמט activeHours (זהה ל-Scheduler/Create):
 * [{ "weekDay": "Sunday", "fromUTC": "06:00:00", "toUTC": "15:00:00" }]
 *
 * weekDay ערכים חוקיים: Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, Saturday.
 *
 * ה-input הפנימי (מה-form/JS) הוא מערך days:
 * [ 'day_key' => [ ['start_time' => 'HH:mm', 'end_time' => 'HH:mm'], ... ], ... ]
 *
 * @package Clinic_Queue_Management
 * @subpackage API\Models
 */
class Clinic_Queue_Update_Active_Hours_Model extends Clinic_Queue_Base_Model {

    /**
     * מזהה היומן בפרוקסי (proxy_schedule_id).
     *
     * @var int
     */
    public $schedulerID;

    /**
     * מערך שעות פעילות בפורמט proxy (HH:mm:ss UTC).
     *
     * @var array
     */
    public $activeHours = array();

    /**
     * בנייה מנתוני days של הטופס.
     *
     * @param int   $scheduler_id מזהה יומן פרוקסי.
     * @param array $days_data    [ day_key => [ ['start_time'=>'HH:mm','end_time'=>'HH:mm'], … ] ]
     * @return self
     */
    public static function from_days_data( $scheduler_id, array $days_data ) {
        if ( ! class_exists( 'Clinic_Queue_Scheduler_Proxy_Service' ) ) {
            require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'api/services/class-scheduler-proxy-service.php';
        }

        $proxy_service = new Clinic_Queue_Scheduler_Proxy_Service();

        $model              = new self();
        $model->schedulerID = absint( $scheduler_id );
        $model->activeHours = $proxy_service->convert_days_to_active_hours( $days_data );
        return $model;
    }

    /**
     * ולידציה.
     *
     * @return true|array
     */
    public function validate() {
        $errors = array();

        if ( empty( $this->schedulerID ) || ! is_numeric( $this->schedulerID ) || intval( $this->schedulerID ) <= 0 ) {
            $errors[] = 'schedulerID הוא חובה וחייב להיות מספר חיובי';
        }

        if ( ! is_array( $this->activeHours ) || empty( $this->activeHours ) ) {
            $errors[] = 'activeHours חייב להיות מערך לא ריק';
        } else {
            foreach ( $this->activeHours as $index => $hour ) {
                if ( ! isset( $hour['weekDay'] ) ) {
                    $errors[] = "Active hour #{$index}: weekDay is required";
                }
                if ( ! isset( $hour['fromUTC'] ) || ! is_string( $hour['fromUTC'] ) || ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hour['fromUTC'] ) ) {
                    $errors[] = "Active hour #{$index}: fromUTC must be a valid HH:mm:ss string";
                }
                if ( ! isset( $hour['toUTC'] ) || ! is_string( $hour['toUTC'] ) || ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hour['toUTC'] ) ) {
                    $errors[] = "Active hour #{$index}: toUTC must be a valid HH:mm:ss string";
                }
            }
        }

        return empty( $errors ) ? true : $errors;
    }

    /**
     * המרה למבנה JSON לפרוקסי.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'schedulerID' => (int) $this->schedulerID,
            'activeHours' => $this->activeHours,
        );
    }
}

/**
 * Get Free Time Request Model
 */
class Clinic_Queue_Get_Free_Time_Model extends Clinic_Queue_Base_Model {
    public $schedulerID;
    public $duration; // in minutes
    public $fromDateUTC; // ISO 8601 date-time string
    public $toDateUTC; // ISO 8601 date-time string
    
    public function validate() {
        $errors = array();
        
        if (empty($this->schedulerID) || !is_numeric($this->schedulerID)) {
            $errors[] = 'מזהה יומן הוא חובה';
        }
        
        if (empty($this->duration) || !is_numeric($this->duration) || $this->duration <= 0) {
            $errors[] = 'משך התור הוא חובה וחייב להיות מספר חיובי';
        }
        
        if (empty($this->fromDateUTC)) {
            $errors[] = 'תאריך התחלה הוא חובה';
        }
        
        if (empty($this->toDateUTC)) {
            $errors[] = 'תאריך סיום הוא חובה';
        }
        
        if (!empty($this->fromDateUTC) && !empty($this->toDateUTC)) {
            $from = new DateTime($this->fromDateUTC);
            $to = new DateTime($this->toDateUTC);
            if ($from > $to) {
                $errors[] = 'תאריך התחלה חייב להיות לפני תאריך סיום';
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}

/**
 * Check Slot Available Request Model
 */
class Clinic_Queue_Check_Slot_Available_Model extends Clinic_Queue_Base_Model {
    public $schedulerID;
    public $fromUTC; // ISO 8601 date-time string
    public $duration; // in minutes
    
    public function validate() {
        $errors = array();
        
        if (empty($this->schedulerID) || !is_numeric($this->schedulerID)) {
            $errors[] = 'מזהה יומן הוא חובה';
        }
        
        if (empty($this->fromUTC)) {
            $errors[] = 'תאריך ושעה הם חובה';
        }
        
        if (empty($this->duration) || !is_numeric($this->duration) || $this->duration <= 0) {
            $errors[] = 'משך התור הוא חובה וחייב להיות מספר חיובי';
        }
        
        return empty($errors) ? true : $errors;
    }
}

