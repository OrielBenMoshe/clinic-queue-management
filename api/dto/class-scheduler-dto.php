<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-dto.php';

/**
 * Scheduler DTOs
 * אובייקטי העברת נתונים עבור יומנים
 */

/**
 * Create Scheduler Model DTO
 */
class Clinic_Queue_Create_Scheduler_DTO extends Clinic_Queue_Base_DTO {
    public $sourceCredentialsID;
    public $sourceSchedulerID;
    public $activeHours = array(); // Array of active hours objects
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
        
        // activeHours must be an array (can be empty for Google Calendar)
        if (!is_array($this->activeHours)) {
            $errors[] = 'Active hours must be an array';
        } elseif (!empty($this->activeHours)) {
            // If activeHours is not empty, validate each entry (required for DRWeb)
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
        // Note: Empty activeHours array is allowed (for Google Calendar)
        
        return empty($errors) ? true : $errors;
    }
    
    public function to_array() {
        // Ensure ticks are integers (not floats) for API compatibility
        // For Google Calendar, activeHours will be empty array
        $active_hours = array();
        if (!empty($this->activeHours)) {
            foreach ($this->activeHours as $hour) {
                $active_hours[] = array(
                    'weekDay' => $hour['weekDay'],
                    'fromUTC' => array(
                        'ticks' => intval($hour['fromUTC']['ticks'])
                    ),
                    'toUTC' => array(
                        'ticks' => intval($hour['toUTC']['ticks'])
                    )
                );
            }
        }
        
        return array(
            'sourceCredentialsID' => intval($this->sourceCredentialsID),
            'sourceSchedulerID' => (string)$this->sourceSchedulerID,
            'activeHours' => $active_hours, // Empty array for Google, populated for DRWeb
            'maxOverlappingMeeting' => intval($this->maxOverlappingMeeting),
            'overlappingDurationInMinutes' => intval($this->overlappingDurationInMinutes)
        );
    }
}

/**
 * Update Scheduler Model DTO
 */
class Clinic_Queue_Update_Scheduler_DTO extends Clinic_Queue_Base_DTO {
    // Schema details from Swagger will be added here
    // This is a placeholder - actual properties depend on UpdateSchedulerModel schema
    
    public function validate() {
        $errors = array();
        // Add validation logic based on actual schema
        return empty($errors) ? true : $errors;
    }
}

/**
 * Update Active Hours Model DTO
 */
class Clinic_Queue_Update_Active_Hours_DTO extends Clinic_Queue_Base_DTO {
    // Schema details from Swagger will be added here
    // This is a placeholder - actual properties depend on UpdateActiveHoursModel schema
    
    public function validate() {
        $errors = array();
        // Add validation logic based on actual schema
        return empty($errors) ? true : $errors;
    }
}

/**
 * Get Free Time Request DTO
 */
class Clinic_Queue_Get_Free_Time_DTO extends Clinic_Queue_Base_DTO {
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
 * Check Slot Available Request DTO
 */
class Clinic_Queue_Check_Slot_Available_DTO extends Clinic_Queue_Base_DTO {
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

