# Changelog - מערכת ניהול מרפאות

All notable changes to this project will be documented in this file.

## [0.3.0] - 2026-01-21

### 🎉 Major Refactoring - API Architecture v2.0

#### Added
- ✨ **Modular Handler Architecture**: פיצול `class-rest-handlers.php` (1537 שורות) ל-6 handlers מודולריים
  - `class-base-handler.php` - Base Handler עם פונקציונליות משותפת
  - `class-appointment-handler.php` - Appointment endpoints
  - `class-scheduler-handler.php` - Scheduler endpoints (7 endpoints)
  - `class-source-credentials-handler.php` - Source Credentials endpoints
  - `class-google-calendar-handler.php` - Google Calendar integration
  - `class-relations-handler.php` - JetEngine Relations endpoints

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
