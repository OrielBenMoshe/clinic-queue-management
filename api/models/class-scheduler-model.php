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
        // This means: required for Google (but can be null), not required for DRWeb
        // However, based on user feedback, for Google we send null, for DRWeb we send array
        if ($this->activeHours !== null) {
            if (!is_array($this->activeHours) || empty($this->activeHours)) {
                $errors[] = 'Active hours must be null or a non-empty array';
            } else {
                // If activeHours is not null and not empty, validate each entry (for DRWeb)
                foreach ($this->activeHours as $index => $hour) {
                    if (!isset($hour['weekDay'])) {
                        $errors[] = "Active hour #{$index}: weekDay is required";
                    }
                    if (!isset($hour['fromUTC']) || !is_array($hour['fromUTC']) || !isset($hour['fromUTC']['ticks'])) {
                        $errors[] = "Active hour #{$index}: fromUTC.ticks is required";
                    } elseif (!is_numeric($hour['fromUTC']['ticks'])) {
                        $errors[] = "Active hour #{$index}: fromUTC.ticks must be a number";
                    }
                    if (!isset($hour['toUTC']) || !is_array($hour['toUTC']) || !isset($hour['toUTC']['ticks'])) {
                        $errors[] = "Active hour #{$index}: toUTC.ticks is required";
                    } elseif (!is_numeric($hour['toUTC']['ticks'])) {
                        $errors[] = "Active hour #{$index}: toUTC.ticks must be a number";
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
        // For DRWeb: send array with active hours
        if ($this->activeHours === null) {
            // Send null for Google Calendar (as per schema: nullable: true)
            $result['activeHours'] = null;
        } elseif (!empty($this->activeHours) && is_array($this->activeHours)) {
            // Send array for DRWeb/Google - ensure ticks are integers (not floats) for API compatibility
            // According to API documentation: fromUTC and toUTC should be objects with "ticks" property
            // The data from convert_days_to_active_hours is already in the correct format
            $active_hours = array();
            foreach ($this->activeHours as $hour) {
                // Ensure we have the required fields
                if (!isset($hour['weekDay']) || !isset($hour['fromUTC']) || !isset($hour['toUTC'])) {
                    continue; // Skip invalid entries
                }
                
                // Extract ticks - should already be in format {ticks: number} from convert_days_to_active_hours
                $from_ticks = 0;
                $to_ticks = 0;
                
                if (is_array($hour['fromUTC']) && isset($hour['fromUTC']['ticks'])) {
                    $from_ticks = intval($hour['fromUTC']['ticks']);
                } elseif (is_numeric($hour['fromUTC'])) {
                    $from_ticks = intval($hour['fromUTC']);
                }
                
                if (is_array($hour['toUTC']) && isset($hour['toUTC']['ticks'])) {
                    $to_ticks = intval($hour['toUTC']['ticks']);
                } elseif (is_numeric($hour['toUTC'])) {
                    $to_ticks = intval($hour['toUTC']);
                }
                
                // Validate that ticks are valid positive numbers
                if ($from_ticks <= 0 || $to_ticks <= 0 || $from_ticks >= $to_ticks) {
                    continue; // Skip invalid time ranges
                }
                
                // Convert ticks to HH:mm:ss format for the API
                // .NET TimeSpan: 1 tick = 100 nanoseconds
                // 1 second = 10,000,000 ticks
                $from_total_seconds = floor($from_ticks / 10000000);
                $to_total_seconds = floor($to_ticks / 10000000);
                
                $from_hours = floor($from_total_seconds / 3600);
                $from_minutes = floor(($from_total_seconds % 3600) / 60);
                $from_seconds = $from_total_seconds % 60;
                
                $to_hours = floor($to_total_seconds / 3600);
                $to_minutes = floor(($to_total_seconds % 3600) / 60);
                $to_seconds = $to_total_seconds % 60;
                
                // Format as HH:mm:ss strings (as expected by the proxy API)
                $from_time_string = sprintf('%02d:%02d:%02d', $from_hours, $from_minutes, $from_seconds);
                $to_time_string = sprintf('%02d:%02d:%02d', $to_hours, $to_minutes, $to_seconds);
                
                // Send as strings, not objects with ticks
                $active_hours[] = array(
                    'weekDay' => (string)$hour['weekDay'],
                    'fromUTC' => $from_time_string,  // String: "HH:mm:ss"
                    'toUTC' => $to_time_string        // String: "HH:mm:ss"
                );
            }
            
            // Only set activeHours if we have valid entries
            if (!empty($active_hours)) {
                $result['activeHours'] = $active_hours;
            } else {
                // If no valid active hours, send null (for Google Calendar this might cause error, but better than invalid data)
                $result['activeHours'] = null;
            }
        }
        // Note: If activeHours is empty array, we still send null (shouldn't happen in practice)
        
        return $result;
    }
}

/**
 * Update Scheduler Model
 */
class Clinic_Queue_Update_Scheduler_Model extends Clinic_Queue_Base_Model {
    // Schema details from Swagger will be added here
    // This is a placeholder - actual properties depend on UpdateSchedulerModel schema
    
    public function validate() {
        $errors = array();
        // Add validation logic based on actual schema
        return empty($errors) ? true : $errors;
    }
}

/**
 * Update Active Hours Model
 */
class Clinic_Queue_Update_Active_Hours_Model extends Clinic_Queue_Base_Model {
    // Schema details from Swagger will be added here
    // This is a placeholder - actual properties depend on UpdateActiveHoursModel schema
    
    public function validate() {
        $errors = array();
        // Add validation logic based on actual schema
        return empty($errors) ? true : $errors;
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

