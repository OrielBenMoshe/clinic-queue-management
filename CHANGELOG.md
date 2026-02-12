# Changelog - מערכת ניהול מרפאות

All notable changes to this project will be documented in this file.

## [Unreleased] - 2026-02-04

### 🔧 API Architecture Alignment (Handlers / Services / Models)

#### Changed
- **get_free_time**: הבקשות עם `schedulerIDsStr` עוברות כעת דרך `Scheduler_Proxy_Service::get_free_time_by_scheduler_ids_str()` (במקום API_Manager ישירות). פורמט התגובה (שטוח) מטופל בתוך ה-Service.
- **create_scheduler_in_proxy**: איסוף והמרת שעות פעילות (מ-request או מ-post meta) הועבר ל-`Scheduler_Proxy_Service::get_active_hours_for_scheduler()` – ה-Handler רק בודק הרשאות ומבנה מודל.
- **Relations – get_doctors_by_clinic**: הלוגיקה הועברה ל-`JetEngine_Relations_Service::get_doctor_ids_by_clinic()` ו-`get_doctors_by_clinic()` – ה-Handler רק מחלץ `clinic_id` ומחזיר תגובה.
- **Google credentials**: כל מתודות ה-credentials (שמירה/קריאה/תוקף/עדכון/ניתוק) הועברו מ-`Scheduler_Proxy_Service` ל-`Google_Calendar_Service`. ה-Google Calendar Handler משתמש כעת ב-`google_service` למתודות אלה.

#### Improved
- **Separation of concerns**: Handlers מטפלים רק ב-REST (params, permissions, response); לוגיקה עסקית ו־API ב-Services.
- **Single entry point**: free-time דרך Scheduler Service בלבד; Relations דרך JetEngine Relations Service.

---

## [0.3.0] - 2026-01-21

### 🎉 Major Refactoring - API Architecture v2.0

#### Added
- ✨ **Modular Handler Architecture**: פיצול `class-rest-handlers.php` (1537 שורות) ל-6 handlers מודולריים
  - `class-base-handler.php` - Base Handler עם פונקציונליות משותפת
  - `class-appointment-handler.php` - Appointment endpoints
  - `class-scheduler-wp-rest-handler.php` - Scheduler – פניות ל-REST API של וורדפרס (7 endpoints)
  - `class-source-credentials-handler.php` - Source Credentials endpoints
  - `class-google-calendar-handler.php` - Google Calendar integration
  - `class-relations-jet-api-handler.php` - Relations – פניות ל-API של Jet (JetEngine)

#### Changed
- 🔄 **Registry Pattern**: `class-rest-handlers.php` עכשיו משמש כ-Registry בלבד (307 שורות)
- 📚 **תיעוד מעודכן**: 
  - `ARCHITECTURE.md` - תיעוד ארכיטקטורה מלא עם דיאגרמות
  - `README.md` - מבנה מודולרי חדש

#### Improved
- ⚡ **Maintainability**: קוד מסודר יותר, קל לתחזוקה
- 🧪 **Testability**: כל handler ניתן לבדיקה בנפרד
- 📈 **Scalability**: קל להוסיף handlers חדשים
- 🔒 **Backward Compatibility**: תמיכה מלאה לאחור - כל ה-endpoints נשארו זהים

#### Technical Details
- **לפני**: 1 קובץ מונוליטי - 1,537 שורות
- **אחרי**: 6 handlers מודולריים - 2,323 שורות סה"כ (כולל Base Handler)
- **תאימות**: 100% backward compatible - אין צורך בשינויים ב-frontend

---

## [0.2.37] - קודם

### Changed
- שיפורים כלליים ותיקוני באגים
