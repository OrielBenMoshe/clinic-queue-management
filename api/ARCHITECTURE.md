# ארכיטקטורת API - מערכת ניהול תורים

## סקירה כללית

הארכיטקטורה בנויה בשכבות מקצועיות ומסודרות, בהתבסס על דוקומנטציית Swagger של DoctorOnline Proxy API.

## מבנה התיקיות

```
api/
├── model/                          # Data Transfer Objects
│   ├── class-base-model.php        # Base Model class
│   ├── class-appointment-model.php # Appointment Models
│   ├── class-scheduler-model.php   # Scheduler Models
│   └── class-response-model.php    # Response Models
│
├── services/                      # Services Layer
│   ├── class-base-service.php              # Base service class
│   ├── class-appointment-service.php       # Appointment service
│   ├── class-scheduler-service.php         # Scheduler service
│   └── class-source-credentials-service.php # Source credentials service
│
├── validation/                    # Validation Layer
│   └── class-validator.php        # Validator utilities
│
├── exceptions/                    # Exception Classes
│   └── class-api-exception.php    # API exceptions
│
├── handlers/                      # Handlers
│   ├── class-error-handler.php    # Error handling
│   └── class-rest-handlers.php    # REST API handlers (main file)
│
├── class-api-manager.php          # API Manager (legacy support)
└── class-rest-handlers.php        # REST API endpoints registration
```

## שכבות הארכיטקטורה

### 1. Model Layer (Data Transfer Objects)

**תפקיד:** אובייקטי העברת נתונים מובנים ומוגדרים היטב.

**קבצים:**
- `class-base-model.php` - Base class לכל ה-Models
- `class-appointment-model.php` - Models עבור תורים
- `class-scheduler-model.php` - Models עבור יומנים
- `class-response-model.php` - Models עבור תגובות API

**דוגמה:**
```php
$customer_model = new Clinic_Queue_Customer_Model();
$customer_model->firstName = 'יוסי';
$customer_model->email = 'yossi@example.com';
// ... וכו'

$appointment_model = new Clinic_Queue_Appointment_Model();
$appointment_model->customer = $customer_model;
$appointment_model->schedulerID = 123;
```

### 2. Services Layer

**תפקיד:** שירותים מקצועיים המטפלים בכל פעולת API.

**קבצים:**
- `class-base-service.php` - Base service עם פונקציות משותפות
- `class-appointment-service.php` - שירות לניהול תורים
- `class-scheduler-service.php` - שירות לניהול יומנים
- `class-source-credentials-service.php` - שירות לניהול פרטי התחברות

**דוגמה:**
```php
$appointment_service = new Clinic_Queue_Appointment_Service();
$result = $appointment_service->create_appointment($appointment_model, $scheduler_id);
```

### 3. Validation Layer

**תפקיד:** ולידציה מקצועית של נתונים.

**קובץ:**
- `class-validator.php` - פונקציות ולידציה

**דוגמה:**
```php
if (!Clinic_Queue_Validator::validate_email($email)) {
    // שגיאה
}
```

### 4. Error Handling Layer

**תפקיד:** טיפול מקצועי בשגיאות.

**קבצים:**
- `class-api-exception.php` - מחלקות יוצאות דופן
- `class-error-handler.php` - טיפול בשגיאות

**דוגמה:**
```php
$result = Clinic_Queue_Error_Handler::handle_api_response($response_data);
```

### 5. REST API Handlers

**תפקיד:** נקודות קצה REST API מקצועיות.

**קובץ:**
- `class-rest-handlers.php` - רישום וטיפול בכל נקודות הקצה

## נקודות קצה זמינות

### Appointment Endpoints

#### POST `/wp-json/clinic-queue/v1/appointment/create`
יצירת תור חדש

**Request Body:**
```json
{
  "schedulerID": 123,
  "customer": {
    "firstName": "יוסי",
    "lastName": "כהן",
    "identityType": "TZ",
    "identity": "123456789",
    "email": "yossi@example.com",
    "mobilePhone": "0501234567",
    "gender": "Male",
    "birthDate": "1990-01-01T00:00:00Z"
  },
  "startAtUTC": "2025-12-01T10:00:00Z",
  "duration": 30,
  "drWebReasonID": 1,
  "remark": "הערה"
}
```

### Scheduler Endpoints

#### GET `/wp-json/clinic-queue/v1/scheduler/source-calendars`
קבלת כל היומנים ממקור

**Parameters:**
- `source_creds_id` (required) - מזהה פרטי התחברות
- `scheduler_id` (required) - מזהה יומן לאימות

#### GET `/wp-json/clinic-queue/v1/scheduler/drweb-calendar-reasons`
קבלת סיבות תור מ-DRWeb

**Parameters:**
- `source_creds_id` (required)
- `drweb_calendar_id` (required)
- `scheduler_id` (required)

#### GET `/wp-json/clinic-queue/v1/scheduler/drweb-calendar-active-hours`
קבלת שעות פעילות מ-DRWeb

**Parameters:**
- `source_creds_id` (required)
- `drweb_calendar_id` (required)
- `scheduler_id` (required)

#### GET `/wp-json/clinic-queue/v1/scheduler/free-time`
קבלת שעות זמינות

**Parameters:**
- `scheduler_id` (required)
- `duration` (required) - משך התור בדקות
- `from_date_utc` (required) - תאריך התחלה (ISO 8601)
- `to_date_utc` (required) - תאריך סיום (ISO 8601)

#### GET `/wp-json/clinic-queue/v1/scheduler/check-slot-available`
בדיקה אם משבצת זמינה

**Parameters:**
- `scheduler_id` (required)
- `from_utc` (required) - תאריך ושעה (ISO 8601)
- `duration` (required) - משך התור בדקות

#### GET `/wp-json/clinic-queue/v1/scheduler/properties`
קבלת מאפיינים של יומן

**Parameters:**
- `scheduler_id` (required)

### Source Credentials Endpoints

#### POST `/wp-json/clinic-queue/v1/source-credentials/save`
שמירת פרטי התחברות למקור

## תכונות מרכזיות

### ✅ ולידציה אוטומטית
כל Model כולל ולידציה מובנית

### ✅ טיפול בשגיאות מקצועי
טיפול בכל סוגי השגיאות מ-API

### ✅ תמיכה ב-Cache Miss
טיפול מיוחד בשגיאות Cache Miss עם המלצה לנסות שוב

### ✅ תמיכה ב-Legacy
נקודות קצה ישנות עדיין עובדות (backward compatibility)

### ✅ ארכיטקטורה מודולרית
קל להוסיף endpoints חדשים או לשנות קיימים

## דוגמת שימוש

```php
// יצירת תור
$customer_model = Clinic_Queue_Customer_Model::from_array([
    'firstName' => 'יוסי',
    'lastName' => 'כהן',
    'identityType' => 'TZ',
    'identity' => '123456789',
    'email' => 'yossi@example.com',
    'mobilePhone' => '0501234567',
    'gender' => 'Male',
    'birthDate' => '1990-01-01T00:00:00Z'
]);

$appointment_model = Clinic_Queue_Appointment_Model::from_array([
    'schedulerID' => 123,
    'startAtUTC' => '2025-12-01T10:00:00Z',
    'duration' => 30
]);
$appointment_model->customer = $customer_model;

$appointment_service = new Clinic_Queue_Appointment_Service();
$result = $appointment_service->create_appointment($appointment_model, 123);

if (is_wp_error($result)) {
    // טיפול בשגיאה
    error_log($result->get_error_message());
} else {
    // הצלחה
    if ($result->is_success()) {
        // התור נוצר בהצלחה
    }
}
```

## הערות חשובות

1. **Authentication:** כל בקשה דורשת `scheduler_id` שמועבר כ-`DoctorOnlineProxyAuthToken` ב-header
2. **Date Format:** כל התאריכים בפורמט ISO 8601 (UTC)
3. **Error Handling:** כל שגיאה מחזירה `WP_Error` עם פרטים מלאים
4. **Validation:** כל Model כולל ולידציה אוטומטית לפני שליחה ל-API

## קישורים

- [Swagger Documentation](https://do-proxy-staging.doctor-clinix.com/swagger/index.html)
- [API README](./README.md)

