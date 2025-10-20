# מסמך איפיון - מערכת ניהול תורים למרפאות

## סקירה כללית

**שם הפרויקט:** Clinic Queue Management  
**גרסה:** 1.0.0  
**סוג:** WordPress Plugin  
**תיאור:** מערכת ניהול תורים למרפאות עם אינטגרציה ל-Elementor ו-Shortcode

## מטרות הפרויקט

1. **ניהול תורים דיגיטלי** - מערכת לניהול תורים למרפאות רפואיות
2. **אינטגרציה עם WordPress** - פלאגין מלא עם ממשק ניהול
3. **תמיכה ב-Elementor** - ווידג'ט גרירה ושחרור
4. **תמיכה ב-Shortcode** - שימוש בכל מקום ב-WordPress
5. **ממשק ניהול מתקדם** - דשבורד, סנכרון, וניהול Cron Jobs
6. **תמיכה ב-RTL** - תמיכה מלאה בעברית וערבית

## ארכיטקטורה כללית

### מבנה הקבצים

```
clinic-queue-management/
├── clinic-queue-management.php          # נקודת כניסה ראשית
├── README.md                           # תיעוד בסיסי
├── SPECIFICATION.md                    # מסמך איפיון זה
│
├── core/                               # ליבת המערכת
│   ├── class-plugin-core.php          # מנהל מרכזי
│   ├── class-database-manager.php     # ניהול בסיס נתונים
│   └── class-appointment-manager.php  # ניהול תורים
│
├── api/                                # ממשקי API
│   └── class-api-manager.php          # מנהל API חיצוני
│
├── admin/                              # ממשק ניהול
│   ├── class-dashboard.php            # דשבורד ראשי
│   ├── class-calendars.php            # ניהול לוחות שנה
│   ├── class-sync-status.php          # סטטוס סנכרון
│   ├── class-cron-jobs.php            # ניהול משימות
│   ├── class-cron-manager.php         # מנהל Cron Jobs
│   ├── assets/                        # נכסי ממשק ניהול
│   │   ├── css/                       # עיצוב
│   │   └── js/                        # JavaScript
│   └── views/                         # תבניות HTML
│
├── frontend/                           # ממשק משתמש
│   ├── widgets/                       # ווידג'טים
│   │   ├── class-clinic-queue-widget.php      # ווידג'ט Elementor
│   │   └── class-widget-fields-manager.php   # מנהל שדות
│   ├── shortcodes/                    # Shortcodes
│   │   └── class-shortcode-handler.php       # מנהל Shortcode
│   └── assets/                        # נכסי Frontend
│       ├── css/                       # עיצוב
│       └── js/                        # JavaScript
│
├── data/                               # נתונים
│   └── mock-data.json                 # נתוני דמו
│
└── includes/                          # קבצים משותפים
    ├── class-api-manager.php          # מנהל API (עותק)
    └── class-cron-manager.php         # מנהל Cron (עותק)
```

## רכיבי המערכת

### 1. ליבת המערכת (Core)

#### Clinic_Queue_Plugin_Core
- **תפקיד:** מנהל מרכזי של הפלאגין
- **אחריות:**
  - טעינת תלויות
  - אתחול בסיס נתונים
  - רישום AJAX handlers
  - רישום REST API endpoints
  - הוספת תפריט ניהול

#### Clinic_Queue_Database_Manager
- **תפקיד:** ניהול בסיס הנתונים
- **טבלאות:**
  - `clinic_queue_calendars` - לוחות שנה
  - `clinic_queue_dates` - תאריכי תורים
  - `clinic_queue_times` - שעות תורים
- **פונקציות:**
  - יצירת טבלאות
  - בדיקת קיום טבלאות
  - ניהול גרסאות

#### Clinic_Queue_Appointment_Manager
- **תפקיד:** ניהול תורים
- **פונקציות:**
  - יצירת תורים
  - עדכון תורים
  - מחיקת תורים
  - סנכרון עם API חיצוני

### 2. ממשק API

#### Clinic_Queue_API_Manager
- **תפקיד:** ניהול API חיצוני
- **פונקציות:**
  - סנכרון נתונים
  - ניהול Cache
  - בדיקת סטטוס סנכרון
  - ניקוי נתונים ישנים

### 3. ממשק ניהול (Admin)

#### Dashboard
- **תפקיד:** דשבורד ראשי
- **תכונות:**
  - סטטיסטיקות כלליות
  - כפתורי פעולה מהירה
  - מידע על סנכרון

#### Calendars Management
- **תפקיד:** ניהול לוחות שנה
- **תכונות:**
  - הצגת לוחות שנה
  - הוספת לוחות שנה
  - עריכת לוחות שנה
  - מחיקת לוחות שנה

#### Sync Status
- **תפקיד:** מעקב סנכרון
- **תכונות:**
  - הצגת סטטוס סנכרון
  - כפתורי סנכרון ידני
  - היסטוריית סנכרון

#### Cron Jobs
- **תפקיד:** ניהול משימות
- **תכונות:**
  - הצגת משימות
  - הרצה ידנית
  - הגדרת תדירות

### 4. ממשק משתמש (Frontend)

#### Elementor Widget
- **תפקיד:** ווידג'ט Elementor
- **תכונות:**
  - בחירת רופא
  - בחירת מרפאה
  - בחירת תאריך ושעה
  - הזמנת תור

#### Shortcode Handler
- **תפקיד:** מנהל Shortcode
- **תכונות:**
  - `[clinic_queue]` - הצגת מערכת תורים
  - פרמטרים: `doctor_id`, `clinic_id`, `cta_label`

## מבנה בסיס הנתונים

### טבלת Calendars
```sql
CREATE TABLE wp_clinic_queue_calendars (
    id int(11) NOT NULL AUTO_INCREMENT,
    doctor_id varchar(50) NOT NULL,
    clinic_id varchar(50) NOT NULL,
    treatment_type varchar(100) NOT NULL,
    calendar_name varchar(255) NOT NULL,
    last_updated datetime DEFAULT CURRENT_TIMESTAMP,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_calendar (doctor_id, clinic_id, treatment_type)
);
```

### טבלת Dates
```sql
CREATE TABLE wp_clinic_queue_dates (
    id int(11) NOT NULL AUTO_INCREMENT,
    calendar_id int(11) NOT NULL,
    appointment_date date NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_date (calendar_id, appointment_date),
    FOREIGN KEY (calendar_id) REFERENCES wp_clinic_queue_calendars(id)
);
```

### טבלת Times
```sql
CREATE TABLE wp_clinic_queue_times (
    id int(11) NOT NULL AUTO_INCREMENT,
    date_id int(11) NOT NULL,
    time_slot time NOT NULL,
    is_booked tinyint(1) DEFAULT 0,
    patient_name varchar(255) DEFAULT NULL,
    patient_phone varchar(20) DEFAULT NULL,
    notes text DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_time (date_id, time_slot),
    FOREIGN KEY (date_id) REFERENCES wp_clinic_queue_dates(id)
);
```

## API Endpoints

### REST API
- `GET /wp-json/clinic-queue/v1/appointments`
  - פרמטרים: `doctor_id`, `clinic_id`, `treatment_type`
  - החזרה: נתוני תורים

### AJAX Endpoints
- `wp_ajax_clinic_queue_get_appointments` - קבלת תורים
- `wp_ajax_clinic_queue_book_appointment` - הזמנת תור
- `wp_ajax_clinic_queue_sync_all` - סנכרון כל הלוחות
- `wp_ajax_clinic_queue_clear_cache` - ניקוי Cache

## תכונות מתקדמות

### 1. ניהול Cache
- Cache של 30 דקות לנתוני API
- ניקוי אוטומטי של נתונים ישנים
- ניהול Cache משותף בין ווידג'טים

### 2. סנכרון אוטומטי
- Cron Jobs לסנכרון תקופתי
- סנכרון ידני דרך ממשק הניהול
- מעקב אחר סטטוס סנכרון

### 3. תמיכה ב-RTL
- עיצוב מותאם לעברית
- תמיכה מלאה בכיוון ימין-שמאל
- פונטים עבריים

### 4. ביצועים
- טעינה איטית של נכסים
- ניהול זיכרון יעיל
- אופטימיזציה למספר ווידג'טים

## דרישות מערכת

### דרישות מינימליות
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+

### דרישות מומלצות
- WordPress 6.0+
- PHP 8.0+
- MySQL 8.0+
- Elementor 3.0+ (אופציונלי)

## אבטחה

### הגנות
- בדיקת `ABSPATH` בכל קובץ
- Sanitization של קלט משתמש
- Nonce verification ל-AJAX
- בדיקת הרשאות משתמש

### הרשאות
- `manage_options` לפעולות ניהול
- גישה ציבורית ל-API endpoints
- בדיקת הרשאות לכל פעולה

## תחזוקה ופיתוח

### לוגים
- לוגים של סנכרון
- לוגים של שגיאות
- מעקב אחר ביצועים

### עדכונים
- מערכת גרסאות
- עדכון אוטומטי של טבלאות
- גיבוי נתונים

### בדיקות
- בדיקות יחידה
- בדיקות אינטגרציה
- בדיקות ביצועים

## תכונות עתידיות

### שלב 2
- אינטגרציה עם מערכות CRM
- התראות SMS/Email
- תשלומים אונליין

### שלב 3
- אפליקציה ניידת
- AI לניהול תורים
- אנליטיקס מתקדם

## מסקנות

המערכת מספקת פתרון מקיף לניהול תורים במרפאות עם:
- ארכיטקטורה מודולרית וגמישה
- ממשק משתמש אינטואיטיבי
- תמיכה מלאה בעברית
- אינטגרציה מלאה עם WordPress
- אפשרויות הרחבה עתידיות

המערכת מוכנה לשימוש בסביבת ייצור עם כל התכונות הנדרשות לניהול תורים מקצועי.
