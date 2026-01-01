<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-base-model.php';

/**
 * Appointment Models
 * אובייקטי העברת נתונים עבור תורים
 */

/**
 * Customer Model
 */
class Clinic_Queue_Customer_Model extends Clinic_Queue_Base_Model {
    public $firstName;
    public $lastName = null;
    public $identityType; // 'TZ' | 'Passport' | 'Undefined'
    public $identity;
    public $email;
    public $mobilePhone;
    public $gender; // 'Male' | 'Female' | 'NotSet'
    public $birthDate; // ISO 8601 date-time string
    
    public function validate() {
        $errors = array();
        
        if (empty($this->firstName) || strlen($this->firstName) < 1) {
            $errors[] = 'שם פרטי הוא חובה';
        }
        
        if (empty($this->identity) || strlen($this->identity) < 1) {
            $errors[] = 'מספר זהות הוא חובה';
        }
        
        if (!in_array($this->identityType, array('TZ', 'Passport', 'Undefined'))) {
            $errors[] = 'סוג זהות לא תקין';
        }
        
        if (empty($this->email) || !is_email($this->email)) {
            $errors[] = 'אימייל לא תקין';
        }
        
        if (empty($this->mobilePhone) || strlen($this->mobilePhone) < 1) {
            $errors[] = 'מספר טלפון נייד הוא חובה';
        }
        
        if (!in_array($this->gender, array('Male', 'Female', 'NotSet'))) {
            $errors[] = 'מין לא תקין';
        }
        
        if (empty($this->birthDate)) {
            $errors[] = 'תאריך לידה הוא חובה';
        }
        
        return empty($errors) ? true : $errors;
    }
}

/**
 * Appointment Model
 */
class Clinic_Queue_Appointment_Model extends Clinic_Queue_Base_Model {
    public $schedulerID;
    public $customer; // Clinic_Queue_Customer_Model
    public $startAtUTC; // ISO 8601 date-time string
    public $drWebReasonID = null;
    public $remark = null;
    public $duration = null; // in minutes
    
    public function validate() {
        $errors = array();
        
        if (empty($this->schedulerID) || !is_numeric($this->schedulerID)) {
            $errors[] = 'מזהה יומן הוא חובה';
        }
        
        if (!$this->customer instanceof Clinic_Queue_Customer_Model) {
            $errors[] = 'נתוני לקוח לא תקינים';
        } else {
            $customer_errors = $this->customer->validate();
            if ($customer_errors !== true) {
                $errors = array_merge($errors, $customer_errors);
            }
        }
        
        if (empty($this->startAtUTC)) {
            $errors[] = 'תאריך ושעה של התור הם חובה';
        }
        
        if ($this->duration !== null && (!is_numeric($this->duration) || $this->duration <= 0)) {
            $errors[] = 'משך התור חייב להיות מספר חיובי';
        }
        
        return empty($errors) ? true : $errors;
    }
}

