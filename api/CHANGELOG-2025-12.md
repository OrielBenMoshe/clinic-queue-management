# API Changes - December 2025

## תקציר השינויים

עדכון מקיף של אינטגרציית ה-API עם doctor-clinix על בסיס תיעוד Swagger המעודכן.

## שינויים עיקריים

### 1. **GetFreeTime Endpoint - שינוי משמעותי**

#### לפני:
- **Method:** POST
- **Parameters:** Request body עם schedulers array, drWebBranchID, fromTime/toTime ticks
- **Response:** FreeTimeSlotModel עם `from` ו-`to`

#### אחרי:
- **Method:** GET ✨
- **Parameters:** Query parameters:
  - `schedulerIDsStr` - מחרוזת מופרדת בפסיקים (לא array)
  - `duration` - משך הזמן בדקות
  - `fromDateUTC` - תאריך התחלה ב-UTC (ISO 8601)
  - `toDateUTC` - תאריך סיום ב-UTC (ISO 8601)
- **Response:** FreeTimeSlotModel עם `from` בלבד (ללא `to`)

**קבצים שעודכנו:**
- `/api/class-api-manager.php` - פונקציית `fetch_from_real_api()` 
- `/api/services/class-scheduler-service.php` - פונקציית `get_free_time()`

### 2. **FreeTimeSlotModel - שדה הוסר**

**השדה `to` הוסר מהמודל!**

עכשיו המודל מכיל רק:
```json
{
  "from": "2025-12-28T16:00:55.185Z",
  "schedulerID": 0
}
```

**פתרון:** חישוב זמן סיום = `from` + `duration` דקות

**קבצים שעודכנו:**
- `/api/class-api-manager.php` - פונקציית `validate_doctoronline_api_response()`

### 3. **Authentication Token - שיפור אבטחה**

הוספת תמיכה בטוקן אימות ייעודי (לא רק scheduler_id).

**סדר עדיפויות:**
1. `DOCTOR_ONLINE_PROXY_AUTH_TOKEN` constant (מ-wp-config.php - הכי מאובטח)
2. WordPress option (מוצפן)
3. Filter `clinic_queue_api_token`
4. Fallback ל-scheduler_id (התנהגות legacy)

**פונקציה חדשה:**
- `get_auth_token($scheduler_id)` ב-`class-api-manager.php`

### 4. **Endpoints חדשים**

הוספת תמיכה ב-endpoints נוספים מה-API:

#### POST /Scheduler/Create
יצירת scheduler חדש

#### POST /Scheduler/Update
עדכון scheduler קיים

#### POST /Scheduler/SetActiveHours
הגדרת שעות פעילות ל-scheduler

**קבצים שעודכנו:**
- `/api/class-rest-handlers.php` - הוספת routes ו-handlers חדשים

### 5. **תיעוד מקיף**

עדכון מלא של `/api/README.md` עם:
- כל ה-endpoints המעודכנים
- דוגמאות request/response
- הסבר על Response Codes
- טיפים לטיפול ב-CacheMiss errors
- מדריך אבטחה מלא
- הסבר על TimeSpan ticks
- סוגי Identity, Gender, WeekDay

## קבצים שעודכנו

### קבצי PHP:
1. `/api/class-api-manager.php`
   - עדכון `fetch_from_real_api()` - GET במקום POST
   - עדכון `validate_doctoronline_api_response()` - חישוב `to` time
   - הוספת `get_auth_token()` - ניהול טוקן אימות

2. `/api/services/class-scheduler-service.php`
   - עדכון `get_free_time()` - שימוש ב-`schedulerIDsStr`

3. `/api/class-rest-handlers.php`
   - הוספת `create_scheduler()` handler
   - הוספת `update_scheduler()` handler
   - הוספת `set_active_hours()` handler
   - רישום routes חדשים

### קבצי תיעוד:
1. `/api/README.md` - עדכון מקיף
2. `/api/CHANGELOG-2025-12.md` - קובץ זה

## Breaking Changes ⚠️

### שינויים שעלולים לשבור קוד קיים:

1. **GetFreeTime Response Structure**
   - השדה `to` אינו קיים יותר
   - יש לחשב את זמן הסיום: `from + duration`

2. **GetFreeTime Request Method**
   - שונה מ-POST ל-GET
   - פרמטרים עברו מ-body ל-query string

3. **schedulerIDsStr vs schedulers**
   - במקום `schedulers: [123]` (array)
   - עכשיו `schedulerIDsStr: "123"` (string)

## Migration Guide

### אם משתמשים ב-REST API ישירות:

**לפני:**
```javascript
fetch('/clinic-queue/v1/scheduler/free-time', {
  method: 'POST',
  body: JSON.stringify({
    schedulers: [123],
    duration: 30,
    // ...
  })
})
```

**אחרי:**
```javascript
const params = new URLSearchParams({
  schedulerIDsStr: '123',
  duration: 30,
  fromDateUTC: '2025-11-25T00:00:00Z',
  toDateUTC: '2025-11-27T00:00:00Z'
});
fetch(`/clinic-queue/v1/scheduler/free-time?${params}`)
```

### אם משתמשים ב-Response Data:

**לפני:**
```javascript
slots.forEach(slot => {
  console.log(`From: ${slot.from}, To: ${slot.to}`);
});
```

**אחרי:**
```javascript
slots.forEach(slot => {
  const fromDate = new Date(slot.from);
  const toDate = new Date(fromDate.getTime() + duration * 60000);
  console.log(`From: ${slot.from}, To: ${toDate.toISOString()}`);
});
```

## הגדרת טוקן API (מומלץ)

הוסף ל-`wp-config.php`:
```php
// Doctor-Clinix API Configuration
define('DOCTOR_ONLINE_PROXY_BASE_URL', 'https://do-proxy-staging.doctor-clinix.com');
define('DOCTOR_ONLINE_PROXY_AUTH_TOKEN', 'your-secure-token-here');
```

## בדיקות שמומלץ לבצע

1. ✅ בדוק שקריאות ל-GetFreeTime עובדות
2. ✅ בדוק שזמן הסיום מחושב נכון
3. ✅ בדוק שטוקן האימות עובד
4. ✅ בדוק טיפול ב-CacheMiss errors
5. ✅ בדוק יצירת appointments
6. ✅ בדוק את ה-widgets והשדות

## תאימות אחורה

הקוד נשמר עם תאימות אחורה מקסימלית:
- Mock data ממשיך לעבוד
- Fallback ל-scheduler_id כטוקן
- ה-DTOs הקיימים ממשיכים לעבוד
- Legacy endpoints עדיין זמינים

## תאריך עדכון

**28 דצמבר 2025**

מבוסס על: [Swagger Documentation](https://do-proxy-staging.doctor-clinix.com/swagger/index.html)

