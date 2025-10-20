# מפת פרויקט - מערכת ניהול תורים למרפאות

## סקירה כללית

פרויקט WordPress Plugin לניהול תורים במרפאות רפואיות עם תמיכה מלאה בעברית ואינטגרציה ל-Elementor.

## מבנה הקבצים המפורט

### 📁 Root Directory
```
clinic-queue-management/
├── 📄 clinic-queue-management.php     # נקודת כניסה ראשית
├── 📄 README.md                      # תיעוד בסיסי
├── 📄 SPECIFICATION.md               # מסמך איפיון מפורט
├── 📄 ARCHITECTURE_DIAGRAM.md        # תרשימי ארכיטקטורה
├── 📄 PROJECT_MAP.md                 # מפה זו
└── 🖼️ Screenshot 2025-09-25 at 13.54.33.png
```

### 📁 Core Layer (ליבת המערכת)
```
core/
├── 📄 class-plugin-core.php          # מנהל מרכזי
│   ├── 🔧 init()                     # אתחול הפלאגין
│   ├── 🔧 load_dependencies()        # טעינת תלויות
│   ├── 🔧 init_database()            # אתחול בסיס נתונים
│   ├── 🔧 init_cron_jobs()           # אתחול משימות
│   ├── 🔧 register_ajax_handlers()   # רישום AJAX
│   ├── 🔧 register_rest_routes()     # רישום REST API
│   ├── 🔧 add_admin_menu()           # הוספת תפריט ניהול
│   └── 🔧 register_widgets()         # רישום ווידג'טים
│
├── 📄 class-database-manager.php     # מנהל בסיס נתונים
│   ├── 🔧 create_tables()            # יצירת טבלאות
│   ├── 🔧 tables_exist()             # בדיקת קיום טבלאות
│   ├── 🔧 get_calendar()             # קבלת לוח שנה
│   ├── 🔧 create_calendar()          # יצירת לוח שנה
│   ├── 🔧 update_calendar()          # עדכון לוח שנה
│   └── 🔧 delete_calendar()          # מחיקת לוח שנה
│
└── 📄 class-appointment-manager.php  # מנהל תורים
    ├── 🔧 update_calendar_from_data() # עדכון מנתונים
    ├── 🔧 get_appointments_for_widget() # קבלת תורים לווידג'ט
    ├── 🔧 book_appointment()         # הזמנת תור
    ├── 🔧 cancel_appointment()       # ביטול תור
    └── 🔧 generate_future_appointments() # יצירת תורים עתידיים
```

### 📁 API Layer (שכבת API)
```
api/
└── 📄 class-api-manager.php          # מנהל API
    ├── 🔧 sync_from_api()            # סנכרון מ-API
    ├── 🔧 get_mock_data()            # קבלת נתוני דמו
    ├── 🔧 needs_sync()               # בדיקת צורך בסנכרון
    ├── 🔧 get_appointments_data()    # קבלת נתוני תורים
    ├── 🔧 schedule_auto_sync()       # תזמון סנכרון אוטומטי
    ├── 🔧 manual_sync()              # סנכרון ידני
    ├── 🔧 get_sync_status()          # קבלת סטטוס סנכרון
    ├── 🔧 clear_cache()              # ניקוי Cache
    ├── 🔧 cleanup_old_data()         # ניקוי נתונים ישנים
    └── 🔧 get_api_stats()            # סטטיסטיקות API
```

### 📁 Admin Layer (שכבת ניהול)
```
admin/
├── 📄 class-dashboard.php            # דשבורד ראשי
│   ├── 🔧 render_page()              # הצגת דף
│   ├── 🔧 enqueue_assets()           # טעינת נכסים
│   ├── 🔧 get_dashboard_data()       # קבלת נתוני דשבורד
│   └── 🔧 get_statistics()           # סטטיסטיקות
│
├── 📄 class-calendars.php            # ניהול לוחות שנה
│   ├── 🔧 render_page()              # הצגת דף
│   ├── 🔧 get_calendars()            # קבלת לוחות שנה
│   ├── 🔧 add_calendar()             # הוספת לוח שנה
│   ├── 🔧 edit_calendar()            # עריכת לוח שנה
│   └── 🔧 delete_calendar()          # מחיקת לוח שנה
│
├── 📄 class-sync-status.php          # סטטוס סנכרון
│   ├── 🔧 render_page()              # הצגת דף
│   ├── 🔧 get_sync_status()          # קבלת סטטוס
│   ├── 🔧 sync_calendar()            # סנכרון לוח שנה
│   └── 🔧 clear_cache()              # ניקוי Cache
│
├── 📄 class-cron-jobs.php            # ניהול משימות
│   ├── 🔧 render_page()              # הצגת דף
│   ├── 🔧 get_cron_jobs()            # קבלת משימות
│   ├── 🔧 run_cron_job()             # הרצת משימה
│   └── 🔧 reset_cron_jobs()          # איפוס משימות
│
├── 📄 class-cron-manager.php         # מנהל Cron Jobs
│   ├── 🔧 init_cron_jobs()           # אתחול משימות
│   ├── 🔧 run_auto_sync_task()       # הרצת סנכרון אוטומטי
│   ├── 🔧 run_cleanup_task()         # הרצת ניקוי
│   ├── 🔧 run_extend_calendars_task() # הרחבת לוחות שנה
│   ├── 🔧 reset_all_cron_jobs()      # איפוס כל המשימות
│   └── 🔧 cleanup_on_deactivation()  # ניקוי בהשבתה
│
├── 📁 assets/                        # נכסי ממשק ניהול
│   ├── 📁 css/
│   │   ├── 📄 dashboard.css          # עיצוב דשבורד
│   │   ├── 📄 calendars.css          # עיצוב לוחות שנה
│   │   ├── 📄 sync-status.css        # עיצוב סטטוס סנכרון
│   │   └── 📄 cron-jobs.css          # עיצוב משימות
│   └── 📁 js/
│       ├── 📄 dashboard.js           # JavaScript דשבורד
│       ├── 📄 calendars.js           # JavaScript לוחות שנה
│       ├── 📄 sync-status.js         # JavaScript סטטוס
│       └── 📄 cron-jobs.js           # JavaScript משימות
│
└── 📁 views/                         # תבניות HTML
    ├── 📄 dashboard-html.php         # תבנית דשבורד
    ├── 📄 calendars-html.php         # תבנית לוחות שנה
    ├── 📄 sync-status-html.php       # תבנית סטטוס סנכרון
    └── 📄 cron-jobs-html.php         # תבנית משימות
```

### 📁 Frontend Layer (שכבת משתמש)
```
frontend/
├── 📁 widgets/                       # ווידג'טים
│   ├── 📄 class-clinic-queue-widget.php      # ווידג'ט Elementor
│   │   ├── 🔧 get_name()             # שם הווידג'ט
│   │   ├── 🔧 get_title()            # כותרת הווידג'ט
│   │   ├── 🔧 get_icon()             # אייקון הווידג'ט
│   │   ├── 🔧 get_categories()       # קטגוריות
│   │   ├── 🔧 get_script_depends()   # תלויות JavaScript
│   │   ├── 🔧 get_style_depends()    # תלויות CSS
│   │   ├── 🔧 register_controls()    # רישום בקרות
│   │   ├── 🔧 render()               # הצגת הווידג'ט
│   │   └── 🔧 get_appointments_data() # קבלת נתוני תורים
│   │
│   └── 📄 class-widget-fields-manager.php   # מנהל שדות
│       ├── 🔧 handle_ajax_request()  # טיפול ב-AJAX
│       ├── 🔧 handle_booking_request() # טיפול בהזמנה
│       └── 🔧 get_appointments()     # קבלת תורים
│
├── 📁 shortcodes/                    # Shortcodes
│   └── 📄 class-shortcode-handler.php       # מנהל Shortcode
│       ├── 🔧 register_shortcode()   # רישום Shortcode
│       ├── 🔧 render_shortcode()     # הצגת Shortcode
│       └── 🔧 get_shortcode_data()   # קבלת נתוני Shortcode
│
└── 📁 assets/                        # נכסי Frontend
    ├── 📁 css/
    │   └── 📄 clinic-queue.css       # עיצוב ראשי
    └── 📁 js/
        └── 📄 clinic-queue.js        # JavaScript ראשי
            ├── 🔧 ClinicQueueWidget  # מחלקת ווידג'ט
            ├── 🔧 ClinicQueueManager # מנהל כללי
            ├── 🔧 loadAppointmentData() # טעינת נתוני תורים
            ├── 🔧 bindEvents()       # קישור אירועים
            ├── 🔧 renderCalendar()   # הצגת לוח שנה
            ├── 🔧 renderTimeSlots()  # הצגת שעות
            └── 🔧 bookAppointment()  # הזמנת תור
```

### 📁 Data Layer (שכבת נתונים)
```
data/
└── 📄 mock-data.json                 # נתוני דמו
    ├── 📊 calendars[]                # מערך לוחות שנה
    │   ├── 🔑 id                     # מזהה ייחודי
    │   ├── 🔑 doctor_id              # מזהה רופא
    │   ├── 🔑 doctor_name            # שם רופא
    │   ├── 🔑 clinic_id              # מזהה מרפאה
    │   ├── 🔑 clinic_name            # שם מרפאה
    │   ├── 🔑 clinic_address         # כתובת מרפאה
    │   ├── 🔑 treatment_type         # סוג טיפול
    │   └── 📊 appointments{}         # תורים
    │       └── 📅 date[]             # תורים לפי תאריך
    │           └── ⏰ time_slot      # שעות תור
    │               ├── 🔑 time       # שעה
    │               └── 🔑 booked     # תפוס
```

### 📁 Includes (קבצים משותפים)
```
includes/
├── 📄 class-api-manager.php          # עותק של מנהל API
└── 📄 class-cron-manager.php         # עותק של מנהל Cron
```

## זרימת נתונים

### 1. אתחול המערכת
```
WordPress → clinic-queue-management.php → Plugin Core → Database Manager → Create Tables
```

### 2. הצגת תורים
```
User → Widget/Shortcode → API Manager → Database → Cache → Display
```

### 3. הזמנת תור
```
User → Select Time → AJAX → Appointment Manager → Database → Confirmation
```

### 4. סנכרון נתונים
```
Cron Job → API Manager → External API → Database → Cache Update
```

## נקודות אינטגרציה

### 1. WordPress
- **Hooks:** `plugins_loaded`, `admin_menu`, `rest_api_init`
- **AJAX:** `wp_ajax_*`, `wp_ajax_nopriv_*`
- **Database:** `$wpdb` global
- **Security:** `check_ajax_referer`, `current_user_can`

### 2. Elementor
- **Widget Registration:** `elementor/widgets/register`
- **Controls:** `add_control()`, `add_group_control()`
- **Rendering:** `render()` method
- **Dependencies:** `get_script_depends()`, `get_style_depends()`

### 3. External API
- **Mock Data:** JSON file simulation
- **Real API:** Future integration point
- **Cache:** 30-minute cache duration
- **Sync:** Automatic and manual sync

## תכונות מרכזיות

### ✅ מומש
- [x] מבנה בסיס נתונים מלא
- [x] ממשק ניהול מתקדם
- [x] ווידג'ט Elementor
- [x] Shortcode support
- [x] תמיכה ב-RTL
- [x] Cache system
- [x] Cron Jobs
- [x] AJAX handlers
- [x] REST API endpoints

### 🔄 בתהליך
- [ ] אינטגרציה עם API חיצוני
- [ ] מערכת התראות
- [ ] דוחות מתקדמים

### 📋 עתידי
- [ ] אפליקציה ניידת
- [ ] תשלומים אונליין
- [ ] AI לניהול תורים
- [ ] אנליטיקס מתקדם

## מסקנות

הפרויקט בנוי בארכיטקטורה מודולרית וברורה עם:
- **הפרדת אחריות** ברורה בין השכבות
- **קוד נקי ומתועד** עם תגובות בעברית
- **תמיכה מלאה** בעברית ו-RTL
- **אינטגרציה מלאה** עם WordPress ו-Elementor
- **אפשרויות הרחבה** עתידיות
- **ביצועים מותאמים** לסביבת ייצור
