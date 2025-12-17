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
    // Schema details from Swagger will be added here
    // This is a placeholder - actual properties depend on CreateSchedulerModel schema
    
    public function validate() {
        $errors = array();
        // Add validation logic based on actual schema
        return empty($errors) ? true : $errors;
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

