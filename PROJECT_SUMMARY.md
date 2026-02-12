---
title: "מפרט סיכום - מערכת ניהול תורים למרפאות"
subtitle: "Clinic Queue Management System"
author: "Oriel Ben-Moshe"
date: "דצמבר 2025"
version: "0.2.37"
lang: he
dir: rtl
geometry: margin=2.5cm
fontsize: 11pt
documentclass: article
---

# 📋 מפרט סיכום - מערכת ניהול תורים למרפאות
## Clinic Queue Management System

**תאריך:** דצמבר 2025  
**גרסה:** 0.2.37  
**מפתח:** Oriel Ben-Moshe

---

## 📑 תוכן עניינים

1. [סקירה כללית](#סקירה-כללית)
2. [מה נבנה בתוסף](#מה-נבנה-בתוסף)
3. [מה נדרש ב-CMS (WordPress/JetEngine)](#מה-נדרש-ב-cms)
4. [ארכיטקטורה ומבנה](#ארכיטקטורה-ומבנה)
5. [תכונות עיקריות](#תכונות-עיקריות)
6. [אינטגרציות חיצוניות](#אינטגרציות-חיצוניות)
7. [סטטיסטיקות פרויקט](#סטטיסטיקות-פרויקט)
8. [תהליך פיתוח ושיפורים](#תהליך-פיתוח-ושיפורים)

---

## 🎯 סקירה כללית

מערכת ניהול תורים מתקדמת למרפאות רפואיות, בנויה כתוסף WordPress מקצועי. המערכת מספקת פתרון מלא לניהול תורים, אינטגרציה עם יומנים חיצוניים (Google Calendar, DRWeb), וממשק ניהול מתקדם.

### מטרת המערכת
- ניהול תורים בזמן אמת
- אינטגרציה עם מערכות יומנים חיצוניות
- ממשק משתמש נוח למטופלים
- ממשק ניהול למנהלי מרפאות
- API מלא לתקשורת עם מערכות חיצוניות

---

## 🏗️ מה נבנה בתוסף

### 1. ליבת המערכת (Core)

#### קבצים עיקריים:
- **`class-plugin-core.php`** - מנהל מרכזי של התוסף
  - בדיקת דרישות (WordPress, Elementor, JetFormBuilder)
  - טעינת תלויות
  - רישום ווידג'טים ו-Shortcodes
  - ניהול Feature Toggles

- **`class-helpers.php`** - פונקציות עזר גלובליות
  - פונקציות עזר לניהול נתונים
  - המרות תאריכים
  - פונקציות עזר ל-JetEngine

- **`class-jetengine-integration.php`** - אינטגרציה עם JetEngine
  - משיכת תתי תחומים רפואיים מ-API
  - הזרקת אופציות לשדות Meta Fields
  - תמיכה ב-JetFormBuilder Forms

- **`class-database-manager.php`** - מנהל מסד נתונים
- **`class-feature-toggle.php`** - ניהול תכונות (הפעלה/כיבוי)
- **`constants.php`** - קבועים גלובליים

### 2. ממשק ניהול (Admin)

#### מבנה מאורגן (לאחר Refactoring):
```
admin/
├── handlers/          # Business Logic
│   └── class-settings-handler.php
├── services/          # Shared Services
│   ├── class-encryption-service.php
│   └── class-relations-service.php
├── ajax/              # AJAX Handlers
│   └── class-ajax-handlers.php
├── views/             # HTML Templates (נקי)
│   ├── settings-html.php
│   ├── dashboard-html.php
│   └── help-html.php
└── assets/            # CSS/JS נפרדים
    ├── css/
    │   └── settings.css
    └── js/
        ├── settings.js
        └── dashboard.js
```

#### דפי ניהול:

**א. דשבורד (`class-dashboard.php`)**
- סקירה כללית של יומנים ותורים
- סטטיסטיקות
- קישורים מהירים

**ב. הגדרות (`class-settings-handler.php`)**
- הגדרת טוקן API (מוצפן)
- הגדרת כתובת API
- ניהול פרטי התחברות
- הצפנה/פענוח בטוח של טוקנים

**ג. עזרה (`class-help.php`)**
- תיעוד מפורט
- פתרון בעיות
- קישורים למסמכים

#### שיפורים שבוצעו (Refactoring):
- ✅ הפרדת Business Logic מ-Presentation
- ✅ הפרדת CSS/JS מ-HTML
- ✅ יצירת Services משותפים (DRY)
- ✅ ארגון AJAX Handlers
- ✅ Routing נקי
- ✅ תיקון שגיאות איות

### 3. REST API מלא

#### ארכיטקטורה בשכבות:
```
api/
├── models/              # Data Transfer Objects
│   ├── class-base-model.php
│   ├── class-appointment-model.php
│   ├── class-scheduler-model.php
│   └── class-response-model.php
├── services/            # Services Layer
│   ├── class-base-service.php
│   ├── class-appointment-service.php
│   ├── class-scheduler-service.php
│   ├── class-google-calendar-service.php
│   ├── class-doctoronline-api-service.php
│   └── class-jetengine-relations-service.php
├── validation/          # Validation Layer
│   └── class-validator.php
├── exceptions/          # Exception Classes
│   └── class-api-exception.php
└── handlers/            # Error Handlers
    └── class-error-handler.php
```

#### Endpoints זמינים:

**תורים (Appointments):**
- `POST /wp-json/clinic-queue/v1/appointment/create` - יצירת תור חדש

**יומנים (Schedulers):**
- `GET /wp-json/clinic-queue/v1/scheduler/source-calendars` - קבלת יומנים ממקור
- `GET /wp-json/clinic-queue/v1/scheduler/drweb-calendar-reasons` - סיבות תור מ-DRWeb
- `GET /wp-json/clinic-queue/v1/scheduler/free-time` - שעות זמינות
- `GET /wp-json/clinic-queue/v1/scheduler/check-slot-available` - בדיקת זמינות
- `GET /wp-json/clinic-queue/v1/scheduler/properties` - מאפיינים

**פרטי התחברות (Source Credentials):**
- `POST /wp-json/clinic-queue/v1/source-credentials/save` - שמירת פרטי התחברות

#### תכונות API:
- ✅ ולידציה אוטומטית של כל הנתונים
- ✅ טיפול מקצועי בשגיאות
- ✅ תמיכה ב-Cache Miss
- ✅ תמיכה ב-Legacy (backward compatibility)
- ✅ ארכיטקטורה מודולרית

### 4. Shortcodes (ממשק משתמש)

#### א. Booking Calendar (`[booking_calendar]`)
**קובץ:** `frontend/shortcodes/booking-calendar/`

**תכונות:**
- זיהוי אוטומטי של רופא/מרפאה
- מצבי תצוגה: רופא, מרפאה, אוטומטי
- סינון לפי סוג טיפול
- בחירת יומן/מרפאה
- הצגת תורים זמינים בזמן אמת
- ניווט חודשי
- תמיכה ב-RTL מלאה
- עיצוב רספונסיבי

**מבנה:**
```
booking-calendar/
├── class-booking-calendar-shortcode.php
├── managers/
│   ├── class-calendar-data-provider.php
│   └── class-calendar-filter-engine.php
├── js/
│   └── modules/
│       ├── booking-calendar-ui-manager.js
│       ├── booking-calendar-api-manager.js
│       └── ...
└── views/
    └── booking-calendar-html.php
```

#### ב. Booking Form (`[booking_form]`)
**קובץ:** `frontend/shortcodes/booking-form/`

**תכונות:**
- טופס יצירת תור מלא
- ולידציה בצד לקוח ושרת
- תמיכה בכל שדות המטופל
- אינטגרציה עם API

#### ג. Schedule Form (`[schedule_form]`)
**קובץ:** `frontend/shortcodes/schedule-form/`

**תכונות:**
- טופס רב-שלבי ליצירת יומן
- תמיכה ב-Google Calendar (OAuth)
- תמיכה ב-DRWeb
- הגדרת שעות פעילות
- ניהול יומנים מרובים

### 5. אינטגרציה עם Elementor

#### Elementor Widget
- ווידג'ט גרירה ושחרור
- הגדרות מלאות ב-Elementor
- תמיכה ב-Dynamic Tags
- תמיכה ב-RTL

### 6. אבטחה

#### Encryption Service
- הצפנת טוקנים ב-WordPress Options
- שימוש ב-WordPress Salt
- פענוח בטוח

#### Token Management
- תמיכה ב-`wp-config.php` (הכי בטוח)
- תמיכה ב-WordPress Options (מוצפן)
- Fallback ל-scheduler_id (legacy)

---

## 🔧 מה נדרש ב-CMS (WordPress/JetEngine)

### 1. Custom Post Types

המערכת משתמשת ב-JetEngine ליצירת Custom Post Types:

#### א. `clinics` - מרפאות
**Meta Fields נדרשים:**
- `treatments` (Repeater) - רשימת טיפולים
  - `treatment_type` (Select) - סוג טיפול
  - `duration` (Number) - משך הטיפול בדקות
  - `price` (Number) - מחיר (אופציונלי)
- `address` (Text) - כתובת
- `phone` (Text) - טלפון
- `email` (Email) - אימייל
- שדות נוספים לפי צורך

#### ב. `doctors` - רופאים
**Meta Fields נדרשים:**
- `specialty` (Select) - התמחות
- `phone` (Text) - טלפון
- `email` (Email) - אימייל
- שדות נוספים לפי צורך

#### ג. `appointments` - תורים (אופציונלי)
**Meta Fields נדרשים:**
- `patient_name` (Text) - שם מטופל
- `patient_phone` (Text) - טלפון
- `appointment_date` (Date) - תאריך תור
- `appointment_time` (Time) - שעת תור
- שדות נוספים לפי צורך

### 2. JetEngine Relations

#### קשרים נדרשים:

**א. רופא ↔ מרפאה**
- **Relation ID:** `doctor_to_clinic`
- **Type:** Many-to-Many
- **Parent:** `doctors`
- **Child:** `clinics`

**ב. תור ↔ רופא**
- **Relation ID:** `appointment_to_doctor`
- **Type:** Many-to-One
- **Parent:** `appointments`
- **Child:** `doctors`

**ג. תור ↔ מרפאה**
- **Relation ID:** `appointment_to_clinic`
- **Type:** Many-to-One
- **Parent:** `appointments`
- **Child:** `clinics`

**ד. תור ↔ מטופל**
- **Relation ID:** `appointment_to_patient`
- **Type:** Many-to-One
- **Parent:** `appointments`
- **Child:** `patients` (אם קיים)

### 3. JetFormBuilder Forms

#### טפסים נדרשים:

**א. טופס יצירת תור**
- שדות: שם, טלפון, אימייל, תאריך, שעה, סוג טיפול
- Action: יצירת תור דרך API

**ב. טופס יצירת יומן**
- שדות: סוג יומן (Google/DRWeb), פרטי התחברות
- Action: יצירת יומן דרך API

### 4. תבניות Elementor

#### תבניות נדרשים:

**א. תבנית דף רופא**
- שימוש ב-`[booking_calendar]` או Elementor Widget
- הצגת פרטי רופא
- רשימת מרפאות (דרך Relations)

**ב. תבנית דף מרפאה**
- שימוש ב-`[booking_calendar]` או Elementor Widget
- הצגת פרטי מרפאה
- רשימת רופאים (דרך Relations)

**ג. תבנית דף תור**
- הצגת פרטי תור
- קישור לרופא ומרפאה

### 5. הגדרות JetEngine

#### Meta Fields Configuration:
- שדות עם אופציות דינמיות (דרך `jet-engine/meta-fields/config` filter)
- Repeater Fields לנתונים מורכבים
- Select Fields עם אופציות מ-API

---

## 🏛️ ארכיטקטורה ומבנה

### עקרונות ארכיטקטוריים:

1. **Separation of Concerns**
   - Business Logic → Handlers
   - Presentation → Views
   - Styling → CSS Files
   - Behavior → JavaScript Files
   - Shared Logic → Services

2. **DRY (Don't Repeat Yourself)**
   - Services משותפים
   - פונקציות עזר גלובליות
   - Base Classes

3. **Single Responsibility**
   - כל מחלקה עושה דבר אחד
   - Handlers מטפלים ב-logic
   - Services מספקים פונקציונליות משותפת

4. **Clean Code**
   - שמות ברורים
   - תיעוד מלא (DocBlocks)
   - קוד מודולרי

### מבנה קבצים:

```
clinic-queue-management/
├── clinic-queue-management.php    # נקודת כניסה
├── core/                          # ליבת המערכת
├── admin/                         # ממשק ניהול
├── api/                           # REST API
├── frontend/                      # ממשק משתמש
│   ├── shortcodes/                # Shortcodes
│   └── oauth-callback.php         # Google OAuth
├── assets/                        # נכסים סטטיים
│   ├── css/                       # סגנונות
│   └── js/                        # JavaScript
└── docs/                          # תיעוד
```

---

## ⚡ תכונות עיקריות

### 1. ניהול תורים בזמן אמת
- שליפת תורים זמינים מ-API חיצוני
- בדיקת זמינות בזמן אמת
- יצירת תורים דרך API

### 2. אינטגרציה עם Google Calendar
- OAuth 2.0 מלא
- יצירת יומנים
- סנכרון תורים
- ניהול שעות פעילות

### 3. אינטגרציה עם DRWeb
- תמיכה במערכת DRWeb
- שליפת סיבות תור
- שליפת שעות פעילות
- יצירת תורים

### 4. אינטגרציה עם DoctorOnline Proxy API
- API מלא לניהול תורים
- תמיכה ב-Cache Miss
- טיפול בשגיאות מקצועי
- ולידציה מלאה

### 5. ממשק משתמש מתקדם
- Shortcodes גמישים
- Elementor Widget
- עיצוב רספונסיבי
- תמיכה ב-RTL מלאה
- נגישות

### 6. ממשק ניהול
- דשבורד מפורט
- הגדרות מתקדמות
- ניהול טוקנים מוצפן
- תיעוד מלא

### 7. אבטחה
- הצפנת טוקנים
- Nonce verification
- Sanitization ו-Validation
- הגנה מפני גישה ישירה

---

## 🔌 אינטגרציות חיצוניות

### 1. DoctorOnline Proxy API
- **Base URL:** `https://do-proxy-staging.doctor-clinix.com`
- **Endpoints:** 10+ endpoints
- **Authentication:** Token-based
- **תכונות:** Cache Miss handling, Error handling

### 2. Google Calendar API
- **OAuth 2.0:** מלא
- **תכונות:** יצירת יומנים, סנכרון תורים
- **Callback:** `/oauth-callback.php`

### 3. DRWeb API
- **תכונות:** שליפת סיבות תור, שעות פעילות
- **תמיכה:** יצירת תורים

### 4. JetEngine REST API
- **תכונות:** שליפת Custom Post Types, Meta Fields, Relations
- **Endpoints:** `/jet-engine/v1/`

---

## 📊 סטטיסטיקות פרויקט

### קבצים:
- **סה"כ קבצי PHP:** ~70 קבצים
- **קבצי JavaScript:** ~20 קבצים
- **קבצי CSS:** ~11 קבצים
- **קבצי תיעוד:** ~15 קבצים

### שורות קוד (משוער):
- **PHP:** ~15,000+ שורות
- **JavaScript:** ~5,000+ שורות
- **CSS:** ~2,000+ שורות
- **סה"כ:** ~22,000+ שורות קוד

### מבנה:
- **Core Classes:** 6
- **Admin Classes:** 8+
- **API Services:** 8+
- **API Models:** 5+
- **Shortcodes:** 3
- **Managers:** 5+

### תכונות:
- **REST API Endpoints:** 10+
- **Shortcodes:** 3
- **Elementor Widgets:** 1
- **Admin Pages:** 3
- **AJAX Handlers:** 5+

---

## 🚀 תהליך פיתוח ושיפורים

### Refactoring שבוצע:

#### 1. Refactoring תיקיית Admin (דצמבר 2025)
- **לפני:** קובץ אחד עם 519 שורות (הכל מעורב)
- **אחרי:** מבנה מאורגן עם הפרדת concerns
- **שיפור:** -90% שורות ב-class-settings.php
- **תוצאה:** קוד נקי, קל לתחזוקה, מוכן להרחבות

#### 2. ארכיטקטורת API
- **בנייה:** ארכיטקטורה בשכבות מקצועית
- **תכונות:** Models, Services, Validation, Error Handling
- **תוצאה:** API יציב, מאובטח, קל להרחבה

#### 3. אינטגרציה עם JetEngine
- **תכונות:** משיכת תתי תחומים מ-API
- **תוצאה:** שדות דינמיים, ללא צורך בעדכון ידני

### שיפורים טכניים:

1. **הפרדת CSS/JS מ-HTML**
   - כל הסגנונות בקבצים נפרדים
   - כל ה-JavaScript בקבצים נפרדים
   - HTML נקי בלבד

2. **Services משותפים**
   - Encryption Service
   - Relations Service
   - מניעת כפילויות

3. **תיעוד מלא**
   - DocBlocks בכל פונקציה
   - מסמכי README מפורטים
   - דיאגרמות זרימה

4. **אבטחה**
   - הצפנת טוקנים
   - Nonce verification
   - Sanitization ו-Validation

---

## 📝 סיכום

### מה נבנה:

✅ **תוסף WordPress מלא** - מערכת ניהול תורים מקצועית  
✅ **REST API מלא** - 10+ endpoints עם ארכיטקטורה בשכבות  
✅ **3 Shortcodes** - Booking Calendar, Booking Form, Schedule Form  
✅ **Elementor Widget** - ווידג'ט גרירה ושחרור  
✅ **ממשק ניהול** - Dashboard, Settings, Help  
✅ **אינטגרציות** - Google Calendar, DRWeb, DoctorOnline Proxy API  
✅ **אבטחה** - הצפנה, Validation, Sanitization  
✅ **תיעוד מלא** - 15+ קבצי תיעוד מפורטים  

### מה נדרש ב-CMS:

🔧 **Custom Post Types** - clinics, doctors, appointments  
🔧 **JetEngine Relations** - קשרים בין רופאים, מרפאות, תורים  
🔧 **Meta Fields** - שדות מותאמים אישית  
🔧 **JetFormBuilder Forms** - טפסים ליצירת תורים ויומנים  
🔧 **Elementor Templates** - תבניות לדפי רופאים ומרפאות  

### היקף העבודה:

📈 **~22,000+ שורות קוד**  
📈 **~70 קבצי PHP**  
📈 **~20 קבצי JavaScript**  
📈 **~11 קבצי CSS**  
📈 **~15 קבצי תיעוד**  

---

**תאריך:** דצמבר 2025  
**גרסה:** 0.2.37  
**סטטוס:** ✅ פעיל ומוכן לשימוש

---

*מסמך זה מסכם את כל העבודה שנעשתה בפיתוח מערכת ניהול התורים. המערכת בנויה בארכיטקטורה מקצועית, מאובטחת, ומוכנה להרחבות עתידיות.*
