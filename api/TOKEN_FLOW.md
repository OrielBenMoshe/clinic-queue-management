# זרימת טוקן API והגדרות - מדריך מפורט

## סקירה כללית

כאשר משתמש מזין טוכן API בדף ההגדרות, המערכת שומרת אותו **מוצפן** במסד הנתונים.
לאחר מכן, **כל קריאת API** בתוסף אוטומטית משתמשת בטוכן הזה.

---

## 🔐 שמירת הטוכן וכתובת השרת

### 1. משתמש מזין בדף ההגדרות

**מיקום**: `wp-admin` → `Clinic Queue` → `הגדרות`

```
משתמש מזין:
├── טוכן API: "abc123xyz789"
└── כתובת שרת: "https://do-proxy-staging.doctor-clinix.com"
```

### 2. שמירה במסד הנתונים

**קובץ**: `admin/class-settings.php` → `handle_form_submission()`

```php
// הטוכן מוצפן באמצעות AES-256-CBC
$encrypted = $this->encrypt_token($token);
update_option('clinic_queue_api_token_encrypted', $encrypted);

// כתובת השרת נשמרת כפי שהיא
update_option('clinic_queue_api_endpoint', $endpoint);
```

**תוצאה במסד הנתונים** (`wp_options`):

| option_name | option_value |
|-------------|--------------|
| `clinic_queue_api_token_encrypted` | `base64(IV + encrypted_token)` |
| `clinic_queue_api_endpoint` | `https://do-proxy-staging.doctor-clinix.com` |

---

## 📡 שימוש בטוכן לקריאות API

### זרימה כללית

```
Widget/Shortcode נטען
    ↓
קריאה ל-API Manager
    ↓
API Manager שולף טוכן וכתובת
    ↓
שליחת HTTP Request עם הטוכן
    ↓
קבלת תגובה מהשרת
```

### 1. נקודות כניסה לקריאות API

#### א. Widget: `Clinic Queue Widget`
**קובץ**: `frontend/widgets/class-clinic-queue-widget.php`

```php
// הווידג'ט קורא ל-API Manager
$api_manager = Clinic_Queue_API_Manager::get_instance();
$data = $api_manager->get_appointments_data(
    $calendar_id, 
    $doctor_id, 
    $clinic_id, 
    $treatment_type
);
```

#### ב. Shortcode: `[clinic_schedule_form]`
**קובץ**: `frontend/shortcodes/class-schedule-form-shortcode.php`

```php
// הטופס שולח AJAX לשמירת לוח זמנים
// שמשתמש ב-Scheduler Service
$scheduler_service = new Clinic_Queue_Scheduler_Service();
$result = $scheduler_service->create_scheduler($data);
```

#### ג. REST API: `/wp-json/clinic-queue/v1/...`
**קובץ**: `api/class-rest-handlers.php`

```php
// כל endpoint משתמש ב-Services
$appointment_service = new Clinic_Queue_Appointment_Service();
$appointments = $appointment_service->get_appointments($params);
```

### 2. שליפת הטוכן - שכבת Service

**קובץ**: `api/services/class-base-service.php`

כל השירותים יורשים מ-`Clinic_Queue_Base_Service` שמספק:

```php
protected function get_auth_token($scheduler_id = null) {
    // Priority 1: קבוע מ-wp-config.php (הכי מאובטח)
    if (defined('CLINIC_QUEUE_API_TOKEN')) {
        return CLINIC_QUEUE_API_TOKEN;
    }
    
    // Priority 2: Option מוצפן (מדף ההגדרות) ⭐ זה מה שאנחנו משתמשים בו
    $encrypted_token = get_option('clinic_queue_api_token_encrypted');
    if ($encrypted_token) {
        return $this->decrypt_token($encrypted_token); // פענוח אוטומטי
    }
    
    // Priority 3: Filter (לשימוש פרוגרמטי)
    $token = apply_filters('clinic_queue_api_token', null, $scheduler_id);
    if ($token) {
        return $token;
    }
    
    // Priority 4: Fallback ל-scheduler_id (legacy)
    return $scheduler_id ? (string)$scheduler_id : null;
}
```

### 3. שליפת כתובת השרת

**אותו קובץ**: `api/services/class-base-service.php`

```php
public function __construct() {
    // Priority 1: קבוע מ-wp-config.php
    if (defined('CLINIC_QUEUE_API_ENDPOINT')) {
        $this->api_endpoint = CLINIC_QUEUE_API_ENDPOINT;
    } 
    // Priority 2: Option (מדף ההגדרות) ⭐
    else {
        $this->api_endpoint = get_option('clinic_queue_api_endpoint');
    }
}
```

### 4. שליחת הבקשה

**אותו קובץ**: `api/services/class-base-service.php` → `make_request()`

```php
protected function make_request($method, $endpoint, $data = null, $scheduler_id = null) {
    // בניית URL מלא
    $url = rtrim($this->api_endpoint, '/') . $endpoint;
    // דוגמה: https://do-proxy-staging.doctor-clinix.com/api/v1/appointments
    
    // הוספת הטוכן ל-Headers
    $auth_token = $this->get_auth_token($scheduler_id);
    $headers = [
        'Content-Type' => 'application/json',
        'DoctorOnlineProxyAuthToken' => $auth_token // ⭐ הטוכן נשלח כאן
    ];
    
    // שליחת הבקשה
    if ($method === 'GET') {
        $response = wp_remote_get($url, ['headers' => $headers]);
    } else {
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => json_encode($data)
        ]);
    }
    
    return $response;
}
```

---

## 🔄 דוגמה מלאה: Widget טוען תורים

### צד לקוח (Frontend)

```javascript
// frontend/assets/js/widgets/clinic-queue/clinic-queue.js
class ClinicQueueWidget {
    async loadAppointments() {
        // שליחת AJAX ל-WordPress
        const response = await fetch(
            '/wp-json/clinic-queue/v1/appointments',
            {
                method: 'POST',
                body: JSON.stringify({
                    clinic_id: this.clinicId,
                    doctor_id: this.doctorId
                })
            }
        );
    }
}
```

### צד שרת (Backend)

```php
// 1. REST Handler מקבל את הבקשה
// api/class-rest-handlers.php
public function get_appointments($request) {
    $clinic_id = $request->get_param('clinic_id');
    $doctor_id = $request->get_param('doctor_id');
    
    // 2. קריאה ל-Service
    $service = new Clinic_Queue_Appointment_Service();
    $appointments = $service->get_appointments($clinic_id, $doctor_id);
    
    return rest_ensure_response($appointments);
}

// 3. Service מבצע את הקריאה החיצונית
// api/services/class-appointment-service.php
public function get_appointments($clinic_id, $doctor_id) {
    // 4. make_request אוטומטית משתמש בטוכן!
    return $this->make_request(
        'POST',
        '/api/v1/appointments',
        [
            'clinic_id' => $clinic_id,
            'doctor_id' => $doctor_id
        ]
    );
}
```

### HTTP Request שנשלח לשרת החיצוני

```http
POST https://do-proxy-staging.doctor-clinix.com/api/v1/appointments
Content-Type: application/json
DoctorOnlineProxyAuthToken: abc123xyz789

{
    "clinic_id": "123",
    "doctor_id": "456"
}
```

---

## 🎯 סיכום: איך זה עובד אוטומטית?

### 1. **הגדרה חד-פעמית**
```
משתמש → דף הגדרות → שמירת טוכן וכתובת
    ↓
wp_options table:
├── clinic_queue_api_token_encrypted = [encrypted]
└── clinic_queue_api_endpoint = https://...
```

### 2. **שימוש אוטומטי בכל קריאה**
```
כל Service יורש מ-Base_Service
    ↓
Base_Service.get_auth_token() שולף מ-wp_options
    ↓
Base_Service.make_request() מוסיף ל-Headers
    ↓
wp_remote_post() שולח עם הטוכן
```

### 3. **אין צורך בקוד נוסף!**

כל מקום בתוסף שמשתמש ב-Services **אוטומטית מקבל**:
- ✅ את הטוכן המוצפן (מפוענח אוטומטית)
- ✅ את כתובת השרת
- ✅ Headers מוכנים
- ✅ Error handling

---

## 📋 רשימת Services שמשתמשים בטוכן

| Service | קובץ | תיאור |
|---------|------|-------|
| `Appointment_Service` | `api/services/class-appointment-service.php` | שליפת תורים |
| `Scheduler_Service` | `api/services/class-scheduler-service.php` | ניהול לוחות זמנים |
| `Google_Calendar_Service` | `api/services/class-google-calendar-service.php` | אינטגרציה עם Google |
| `Source_Credentials_Service` | `api/services/class-source-credentials-service.php` | ניהול credentials |

**כולם יורשים מ-`Base_Service`** ולכן **כולם משתמשים באותו טוכן אוטומטית**.

---

## 🔒 אבטחה

### הצפנה
- **אלגוריתם**: AES-256-CBC
- **מפתח**: נגזר מ-`AUTH_SALT` של WordPress
- **IV**: אקראי לכל הצפנה (16 bytes)

### פענוח
- מתבצע **רק בזמן שימוש** (לא נשמר מפוענח)
- מתבצע **בצד השרת בלבד** (לא נחשף לצד לקוח)

### Fallback
אם אין OpenSSL:
```php
// הצפנה פשוטה (base64)
$encrypted = base64_encode($token);
```

---

## 🛠️ Debugging

### לבדוק אם הטוכן נשמר:

```php
// בכל מקום בקוד WordPress
$encrypted = get_option('clinic_queue_api_token_encrypted');
echo $encrypted ? 'יש טוכן!' : 'אין טוכן';
```

### לבדוק איזה טוכן נשלח:

```php
// הוסף ל-class-base-service.php בתוך make_request()
error_log('Auth Token: ' . $auth_token);
error_log('API Endpoint: ' . $url);
error_log('Headers: ' . print_r($headers, true));
```

### לבדוק את התגובה מהשרת:

```php
// אחרי wp_remote_post()
$body = wp_remote_retrieve_body($response);
error_log('API Response: ' . $body);
```

---

## ⚙️ תצורות מתקדמות

### שימוש בקבוע (מומלץ לפרודקשן)

**קובץ**: `wp-config.php`

```php
// הטוכן לא יישמר במסד הנתונים
define('CLINIC_QUEUE_API_TOKEN', 'your-super-secret-token');
define('CLINIC_QUEUE_API_ENDPOINT', 'https://api.production.com');
```

**יתרונות**:
- 🔒 לא נשמר במסד הנתונים
- 🔒 לא נגיש דרך ממשק הניהול
- 🔒 לא ניתן לשינוי ללא גישה לשרת

### שימוש ב-Filter (לשימוש פרוגרמטי)

```php
// בקובץ functions.php של התבנית
add_filter('clinic_queue_api_token', function($token, $scheduler_id) {
    // לוגיקה מותאמת אישית
    if ($scheduler_id === 123) {
        return 'special-token-for-scheduler-123';
    }
    return $token;
}, 10, 2);
```

---

## 📝 סיכום

### כן, התוסף **תמיד** ידע למשוך את הטוכן!

1. ✅ **שמירה**: הטוכן נשמר מוצפן ב-`wp_options`
2. ✅ **שליפה**: כל Service אוטומטית שולף אותו
3. ✅ **שימוש**: כל קריאת API מקבלת אותו ב-Headers
4. ✅ **אבטחה**: מוצפן במנוחה, מפוענח רק בשימוש

### מאיפה הוא מושך?

```
wp_options table
├── clinic_queue_api_token_encrypted → הטוכן (מוצפן)
└── clinic_queue_api_endpoint → כתובת השרת
```

### איך זה עובד?

```
Base_Service (מחלקת אב)
├── get_auth_token() → שולף מ-wp_options
├── make_request() → מוסיף ל-Headers
└── כל Service יורש את זה אוטומטית!
```

---

**תאריך עדכון**: דצמבר 2025  
**גרסה**: 1.0.0

