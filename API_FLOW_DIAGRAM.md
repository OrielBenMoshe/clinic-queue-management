# דיאגרמת זרימת API - Clinic Queue Management

## סקירה כללית

מסמך זה מתאר את כל השלבים של פניות ל-API, מה מגיע ומה חוזר, מה נשמר ב-WordPress ומה לא.

**המסמך מפריד בין 3 סוגי פניות:**
1. **Frontend → Backend** - פניות מהפרונט ל-WordPress REST API
2. **Backend → Proxy API** - פניות מהבאק ל-API של הפרוקסי
3. **Backend → Google API** - פניות מהבאק ל-API של גוגל

---

## 0. יצירת פוסט מסוג יומן (`POST /admin-ajax.php` - `save_clinic_schedule`)

### שלב 1: Frontend → Backend (WordPress AJAX)

**בקשה:**
```
POST /wp-admin/admin-ajax.php
Headers:
  Content-Type: application/x-www-form-urlencoded
Body (form-data):
  action: save_clinic_schedule
  nonce: {saveNonce}
  schedule_data: {
    "clinic_id": 123,
    "doctor_id": 456,
    "manual_calendar_name": "יומן ידני",
    "action_type": "google",  // או "clinix"
    "days": {
      "sunday": [{"start_time": "09:00", "end_time": "17:00"}],
      "monday": [{"start_time": "09:00", "end_time": "17:00"}]
    },
    "treatments": [
      {
        "treatment_type": "רפואה כללית",
        "sub_speciality": 0,
        "cost": 200,
        "duration": 30
      }
    ]
  }
}
```

**תגובה:**
```json
{
  "success": true,
  "data": {
    "message": "Schedule saved successfully",
    "wordpress_scheduler_id": 789,  // WordPress post ID (post_type = schedules)
    "post_id": 789,  // Legacy alias
    "post_title": "יומן 🏥 מרפאה X | 👨‍⚕️ רופא Y"
  }
}
```

**⚠️ חשוב**: 
- `wordpress_scheduler_id` = **WordPress post ID** (post_type = schedules)
- זה **לא** proxy scheduler ID - ה-proxy scheduler ID יגיע רק אחרי `POST /scheduler/create-schedule-in-proxy`
- זה **לא** Google Calendar ID או DRWeb Calendar ID - אלה יגיעו מ-`getAllSourceCalendars`

---

### שלב 2: יצירת הפוסט ב-WordPress

**פעולה פנימית (לא API call):**
- **נוצר פוסט מסוג `schedules`**:
  - `post_type` = `'schedules'`
  - `post_title` = "יומן 🏥 [clinic_name] | [icon] [doctor_name/manual_name]"
  - `post_status` = `'publish'`
  - `post_author` = `get_current_user_id()`

- **נשמר ב-Post Meta**:
  - `schedule_type` = `'google'` או `'clinix'`
  - `clinic_id` = מזהה המרפאה
  - `doctor_id` = מזהה הרופא (אופציונלי)
  - `manual_calendar_name` = שם יומן ידני (אופציונלי)
  - `sunday`, `monday`, `tuesday`, `wednesday`, `thursday`, `friday`, `saturday` = שעות פעילות
  - `treatments` = רשימת טיפולים

**⚠️ חשוב**: 
- הפוסט נוצר **לפני** חיבור לפרוקסי
- בשלב זה עדיין **אין** `proxy_schedule_id` (proxy scheduler ID) ב-meta
- ה-proxy scheduler ID (`proxy_schedule_id` ב-meta) יגיע רק אחרי `POST /scheduler/create-schedule-in-proxy`
- בשלב זה יש רק `wordpress_scheduler_id` (WordPress post ID) - זה מה שמוחזר בתגובה

---

## 1. חיבור Google Calendar (`POST /google/connect`)

### שלב 1: Frontend → Backend (WordPress REST API)

**בקשה:**
```
POST /wp-json/clinic-queue/v1/google/connect
Headers:
  Content-Type: application/json
  X-WP-Nonce: {nonce}
Body:
{
  "code": "4/0AeanS...",  // Authorization code מ-Google OAuth
  "wordpress_scheduler_id": 123  // WordPress post ID של scheduler (post_type = schedules)
  // או "scheduler_id": 123 (legacy support)
}
```

**תגובה:**
```json
{
  "success": true,
  "message": "Successfully connected to Google Calendar",
  "data": {
    "wordpress_scheduler_id": 123,
    "scheduler_id": 123,  // Legacy alias
    "source_credentials_id": 456,
    "google_user_email": "user@example.com"
  },
  "debug": [...]
}
```

---

### שלב 2: Backend → Google API (Exchange Code for Tokens)

**בקשה:**
```
POST https://oauth2.googleapis.com/token
Headers:
  Content-Type: application/x-www-form-urlencoded
Body (form-data):
  code: {authorization_code}
  client_id: {GOOGLE_CLIENT_ID}
  client_secret: {GOOGLE_CLIENT_SECRET}
  redirect_uri: postmessage
  grant_type: authorization_code
```

**תגובה:**
```json
{
  "access_token": "ya29.a0AfH6SMC...",
  "refresh_token": "1//0g...",
  "expires_in": 3599,
  "token_type": "Bearer",
  "scope": "https://www.googleapis.com/auth/calendar"
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - רק עובר לשלב הבא

---

### שלב 3: Backend → Google API (Get User Info)

**בקשה:**
```
GET https://www.googleapis.com/oauth2/v1/userinfo
Headers:
  Authorization: Bearer {access_token}
```

**תגובה:**
```json
{
  "email": "user@example.com",
  "name": "User Name",
  "picture": "https://...",
  "verified_email": true
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - רק עובר לשלב הבא

---

### שלב 4: שמירת מידע חיבור Google ב-WordPress

**פעולה פנימית (לא API call):**
- **נשמר ב-Scheduler Post Meta** (`post_id = 123`):
  - `google_connected` = `true` (מציין שהיומן מחובר ל-Google)
  - `google_user_email` = `'user@example.com'` (אימייל המשתמש ב-Google)
  - `google_connected_at` = `'2025-12-28 16:01:32'` (תאריך ושעה של החיבור)

**⚠️ חשוב**: 
- **Tokens (access_token, refresh_token) לא נשמרים** ב-WordPress - רק נשלחים לפרוקסי
- רק מידע חיבור בסיסי נשמר למעקב

---

### שלב 5: Backend → Proxy API (Save Source Credentials)

**בקשה:**
```
POST https://do-proxy-staging.doctor-clinix.com/SourceCredentials/Save
Headers:
  Content-Type: application/json
  Accept: application/json
  DoctorOnlineProxyAuthToken: {API_TOKEN}
Body:
{
  "sourceType": "Google",
  "accessToken": "ya29.a0AfH6SMC...",
  "accessTokenExpiresIn": "2025-12-28T17:01:32.063Z",  // ISO 8601 format
  "refreshToken": "1//0g..."
}
```

**תגובה:**
```json
{
  "code": "Success",
  "error": null,
  "result": 456  // sourceCredentialsID
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - Google tokens לא נשמרים ב-WordPress, רק נשלחים לפרוקסי
- `source_credentials_id` מוחזר מהפרוקסי אבל לא נשמר ב-WordPress
- מידע החיבור (`google_connected`, `google_user_email`, `google_connected_at`) נשמר בשלב 4

---

## 2. קבלת רשימת יומנים (`GET /scheduler/source-calendars`)

### שלב 1: Frontend → Backend (WordPress REST API)

**בקשה:**
```
GET /wp-json/clinic-queue/v1/scheduler/source-calendars?source_creds_id=456&wordpress_scheduler_id=123
// או ?scheduler_id=123 (legacy support)
Headers:
  X-WP-Nonce: {nonce}
```

**תגובה:**
```json
{
  "code": "Success",
  "result": [
    {
      "sourceSchedulerID": "calendar_id_123",
      "name": "My Calendar",
      "description": "Calendar description"
    }
  ]
}
```

---

### שלב 2: Backend → Proxy API (Get All Source Calendars)

**מה קורה לפני:**
- **Backend מקבל**: `source_creds_id` מ-request parameter
- **⚠️ חשוב**: `source_credentials_id` נדרש רק בשלבים הראשוניים (עד קבלת `proxy_schedule_id`)
- אחרי שיש `proxy_schedule_id`, כל הפעולות משתמשות רק בו (עם הטוקן הראשי של הפרוקסי)

**בקשה:**
```
GET https://do-proxy-staging.doctor-clinix.com/Scheduler/GetAllSourceCalendars?sourceCredsID=456
Headers:
  Accept: application/json
  DoctorOnlineProxyAuthToken: {API_TOKEN}
```

**תגובה:**
```json
{
  "code": "Success",
  "error": null,
  "result": [
    {
      "sourceSchedulerID": "calendar_id_123",
      "name": "My Calendar",
      "description": "Calendar description"
    }
  ]
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - רק מוחזר לפרונט

**⚠️ חשוב**: `sourceSchedulerID` זה המזהה של היומן ב-Source (Google Calendar ID או DRWeb Calendar ID), לא המזהה של ה-scheduler בפרוקסי.

---

## 3. יצירת Scheduler בפרוקסי (`POST /scheduler/create-schedule-in-proxy`)

### שלב 1: Frontend → Backend (WordPress REST API)

**בקשה:**
```
POST /wp-json/clinic-queue/v1/scheduler/create-schedule-in-proxy
Headers:
  Content-Type: application/json
  X-WP-Nonce: {nonce}
Body:
{
  "scheduler_id": 123,  // WordPress post ID
  "source_credentials_id": 456,
  "source_scheduler_id": "calendar_id_123",  // sourceSchedulerID מ-getAllSourceCalendars
  "active_hours": {  // רק ל-Google Calendar (חובה)
    "sunday": [{"from": "09:00", "to": "17:00"}],
    "monday": [{"from": "09:00", "to": "17:00"}]
  }
}
```

**תגובה:**
```json
{
  "success": true,
  "message": "Scheduler created successfully in proxy",
  "data": {
    "proxy_schedule_id": 789,  // proxy scheduler ID
    "wordpress_scheduler_id": 123,  // WordPress post ID
    "wordpress_post_id": 123,  // Legacy alias
    "source_scheduler_id": "calendar_id_123"  // Source Calendar ID
  }
}
```

---

### שלב 2: עיבוד ב-Backend (לא API call)

**מה קורה:**
1. **Get sourceCredentialsID** - קבלת sourceCredentialsID
   - **מקור**: מקבל מ-request parameter
   - **⚠️ חשוב**: `source_credentials_id` לא נשמר ב-WordPress, צריך לקבל אותו מהפרונט בכל פעם

2. **Get activeHours** - קבלת שעות פעילות
   - **ל-Google Calendar**: מקבל מ-request body (חובה)
   - **ל-DRWeb**: מקבל מ-Scheduler Post Meta (אופציונלי) או מ-request body
   - **המרה**: מ-format של frontend ל-format של API (weekDay, fromUTC HH:mm:ss, toUTC HH:mm:ss)

**⚠️ חשוב**: `source_credentials_id` נדרש רק בשלבים הראשוניים:
- חיבור Google (`/google/connect`) - מקבלים `source_credentials_id` מהפרוקסי
- קבלת רשימת יומנים (`/scheduler/source-calendars`) - צריך `source_credentials_id` כדי לקבל את רשימת היומנים
- יצירת scheduler בפרוקסי (`/scheduler/create-schedule-in-proxy`) - צריך `source_credentials_id` + `sourceSchedulerID` כדי ליצור את ה-scheduler בפרוקסי

**אחרי שיש `proxy_schedule_id`**: כל הפעולות הבאות משתמשות רק ב-`proxy_schedule_id` (והטוקן הראשי של הפרוקסי), ולא צריך יותר `source_credentials_id`.

---

### שלב 3: Backend → Proxy API (Create Scheduler)

**בקשה:**
```
POST https://do-proxy-staging.doctor-clinix.com/Scheduler/Create
Headers:
  Content-Type: application/json
  Accept: application/json
  DoctorOnlineProxyAuthToken: {API_TOKEN}
Body:
{
  "sourceCredentialsID": 456,
  "sourceSchedulerID": "calendar_id_123",
  "activeHours": [
    {
      "weekDay": "Sunday",
      "fromUTC": "08:00:00",
      "toUTC": "16:00:00"
    }
  ],
  "maxOverlappingMeeting": 1,
  "overlappingDurationInMinutes": 0
}
```

**תגובה:**
```json
{
  "code": "Success",
  "error": null,
  "result": 789  // זה ה-proxy scheduler ID
}
```

---

### שלב 4: שמירת Proxy Scheduler ID ב-WordPress

**פעולה פנימית (לא API call):**
- **נשמר ב-Scheduler Post Meta** (`post_id = 123`):
  - `proxy_schedule_id` = `789` (proxy scheduler ID - משמש לכל פעולות הפרוקסי)
  - `proxy_connected` = `true` (מציין שהיומן מחובר לפרוקסי)
  - `proxy_connected_at` = `'2025-12-28 16:01:32'` (תאריך ושעה של החיבור)

**⚠️ חשוב**: 
- `proxy_schedule_id` (meta) = proxy scheduler ID (משמש לכל פעולות הפרוקסי)
- `wordpress_post_id` = WordPress post ID (מזהה הפוסט)
- `source_scheduler_id` = Source Calendar ID (Google Calendar ID או DRWeb Calendar ID)

---

## 4. קבלת זמנים פנויים (`GET /scheduler/free-time`)

### שלב 1: Frontend → Backend (WordPress REST API)

**בקשה:**
```
GET /wp-json/clinic-queue/v1/scheduler/free-time?wordpress_scheduler_id=123&duration=30&from_date_utc=2025-12-28T00:00:00Z&to_date_utc=2025-12-30T00:00:00Z
// או ?scheduler_id=123 (legacy support)
Headers:
  X-WP-Nonce: {nonce}
```

**תגובה:**
```json
{
  "code": "Success",
  "result": [
    {
      "from": "2025-12-28T16:00:00Z",
      "schedulerID": 789
    }
  ]
}
```

---

### שלב 2: עיבוד ב-Backend (לא API call)

**מה קורה:**
1. **Get proxy scheduler ID** - קבלת proxy scheduler ID
   - **מקור**: `get_post_meta($wordpress_scheduler_id, 'proxy_schedule_id', true)`
   - **אם לא נמצא**: שגיאה "יומן לא מחובר לפרוקסי"

---

### שלב 3: Backend → Proxy API (Get Free Time)

**בקשה:**
```
GET https://do-proxy-staging.doctor-clinix.com/Scheduler/GetFreeTime?schedulerIDsStr=789&duration=30&fromDateUTC=2025-12-28T00:00:00Z&toDateUTC=2025-12-30T00:00:00Z
Headers:
  Accept: application/json
  DoctorOnlineProxyAuthToken: {API_TOKEN}
```

**תגובה:**
```json
{
  "code": "Success",
  "error": null,
  "result": [
    {
      "from": "2025-12-28T16:00:00Z",
      "schedulerID": 789
    }
  ]
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - רק מוחזר לפרונט

**⚠️ חשוב**: ה-`wordpress_scheduler_id` ב-request הוא WordPress post ID, אבל לפרוקסי נשלח ה-proxy scheduler ID מה-meta.

---

## 5. יצירת תור (`POST /appointment/create`)

### שלב 1: Frontend → Backend (WordPress REST API)

**בקשה:**
```
POST /wp-json/clinic-queue/v1/appointment/create
Headers:
  Content-Type: application/json
  X-WP-Nonce: {nonce}
Body:
{
  "scheduler_id": 123,  // WordPress post ID
  "fromUTC": "2025-12-28T16:00:00Z",
  "duration": 30,
  "customer": {
    "name": "John Doe",
    "phone": "0501234567",
    "email": "john@example.com"
  }
}
```

**תגובה:**
```json
{
  "code": "Success",
  "result": {...}
}
```

---

### שלב 2: עיבוד ב-Backend (לא API call)

**מה קורה:**
1. **Get proxy scheduler ID** - קבלת proxy scheduler ID
   - **מקור**: `get_post_meta($wordpress_scheduler_id, 'proxy_schedule_id', true)`
   - **אם לא נמצא**: שגיאה

---

### שלב 3: Backend → Proxy API (Create Appointment)

**בקשה:**
```
POST https://do-proxy-staging.doctor-clinix.com/Appointment/Create
Headers:
  Content-Type: application/json
  Accept: application/json
  DoctorOnlineProxyAuthToken: {API_TOKEN}
Body:
{
  "schedulerID": 789,  // proxy scheduler ID
  "fromUTC": "2025-12-28T16:00:00Z",
  "duration": 30,
  "customer": {
    "name": "John Doe",
    "phone": "0501234567",
    "email": "john@example.com"
  }
}
```

**תגובה:**
```json
{
  "code": "Success",
  "error": null,
  "result": {...}
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - רק יצירת תור בפרוקסי

---

## 5.1 בדיקת זמינות Slot (`GET /scheduler/check-slot-available`)

### שלב 1: Frontend → Backend (WordPress REST API)

**בקשה:**
```
GET /wp-json/clinic-queue/v1/scheduler/check-slot-available?wordpress_scheduler_id=123&from_utc=2025-12-28T16:00:00Z&duration=30
// או ?scheduler_id=123 (legacy support)
Headers:
  X-WP-Nonce: {nonce}
```

**תגובה:**
```json
{
  "code": "Success",
  "result": {
    "isAvailable": true
  }
}
```

---

### שלב 2: עיבוד ב-Backend (לא API call)

**מה קורה:**
1. **Get proxy scheduler ID** - קבלת proxy scheduler ID
   - **מקור**: `get_post_meta($wordpress_scheduler_id, 'proxy_schedule_id', true)`
   - **אם לא נמצא**: שגיאה "יומן לא מחובר לפרוקסי"

---

### שלב 3: Backend → Proxy API (Check Slot Available)

**בקשה:**
```
GET https://do-proxy-staging.doctor-clinix.com/Scheduler/CheckIsSlotAvailable?schedulerID=789&fromUTC=2025-12-28T16:00:00Z&duration=30
Headers:
  Accept: application/json
  DoctorOnlineProxyAuthToken: {API_TOKEN}
```

**תגובה:**
```json
{
  "code": "Success",
  "error": null,
  "result": {
    "isAvailable": true
  }
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - רק מוחזר לפרונט

---

## 5.2 קבלת תכונות Scheduler (`GET /scheduler/properties`)

### שלב 1: Frontend → Backend (WordPress REST API)

**בקשה:**
```
GET /wp-json/clinic-queue/v1/scheduler/properties?scheduler_id=123
Headers:
  X-WP-Nonce: {nonce}
```

**תגובה:**
```json
{
  "code": "Success",
  "result": {...}
}
```

---

### שלב 2: עיבוד ב-Backend (לא API call)

**מה קורה:**
1. **Get proxy scheduler ID** - קבלת proxy scheduler ID
   - **מקור**: `get_post_meta($wordpress_scheduler_id, 'proxy_schedule_id', true)`
   - **אם לא נמצא**: שגיאה "יומן לא מחובר לפרוקסי"

---

### שלב 3: Backend → Proxy API (Get Scheduler Properties)

**בקשה:**
```
GET https://do-proxy-staging.doctor-clinix.com/Scheduler/GetSchedulersProperties?schedulerID=789
Headers:
  Accept: application/json
  DoctorOnlineProxyAuthToken: {API_TOKEN}
```

**תגובה:**
```json
{
  "code": "Success",
  "error": null,
  "result": {...}
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - רק מוחזר לפרונט

---

## 6. DRWeb Calendar Flow

### 6.1 קבלת סיבות DRWeb (`GET /scheduler/drweb-calendar-reasons`)

#### שלב 1: Frontend → Backend (WordPress REST API)

**בקשה:**
```
GET /wp-json/clinic-queue/v1/scheduler/drweb-calendar-reasons?source_creds_id=456&drweb_calendar_id=calendar_id_123&scheduler_id=123
Headers:
  X-WP-Nonce: {nonce}
```

**תגובה:**
```json
{
  "code": "Success",
  "result": [
    {
      "id": 1,
      "name": "Reason 1"
    }
  ]
}
```

#### שלב 2: Backend → Proxy API (Get DRWeb Calendar Reasons)

**מה קורה לפני:**
- **Backend מקבל**: `source_creds_id` מ-request parameter
- **⚠️ חשוב**: `source_credentials_id` לא נשמר ב-WordPress, צריך לקבל אותו מהפרונט בכל פעם

**בקשה:**
```
GET https://do-proxy-staging.doctor-clinix.com/Scheduler/GetDRWebCalendarReasons?sourceCredsID=456&drwebCalendarID=calendar_id_123
Headers:
  Accept: application/json
  DoctorOnlineProxyAuthToken: {API_TOKEN}
```

**תגובה:**
```json
{
  "code": "Success",
  "error": null,
  "result": [
    {
      "id": 1,
      "name": "Reason 1"
    }
  ]
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - רק מוחזר לפרונט

**⚠️ חשוב**: `drweb_calendar_id` = `sourceSchedulerID` מ-getAllSourceCalendars.

---

### 6.2 קבלת שעות פעילות DRWeb (`GET /scheduler/drweb-calendar-active-hours`)

#### שלב 1: Frontend → Backend (WordPress REST API)

**בקשה:**
```
GET /wp-json/clinic-queue/v1/scheduler/drweb-calendar-active-hours?source_creds_id=456&drweb_calendar_id=calendar_id_123&scheduler_id=123
Headers:
  X-WP-Nonce: {nonce}
```

**תגובה:**
```json
{
  "code": "Success",
  "result": [...]
}
```

#### שלב 2: Backend → Proxy API (Get DRWeb Calendar Active Hours)

**מה קורה לפני:**
- **Backend מקבל**: `source_creds_id` מ-request parameter
- **⚠️ חשוב**: `source_credentials_id` לא נשמר ב-WordPress, צריך לקבל אותו מהפרונט בכל פעם

**בקשה:**
```
GET https://do-proxy-staging.doctor-clinix.com/Scheduler/GetDRWebCalendarActiveHours?sourceCredsID=456&drwebCalendarID=calendar_id_123
Headers:
  Accept: application/json
  DoctorOnlineProxyAuthToken: {API_TOKEN}
```

**תגובה:**
```json
{
  "code": "Success",
  "error": null,
  "result": [...]
}
```

**מה נשמר ב-WordPress:**
- ❌ לא נשמר - רק מוחזר לפרונט

---

## סיכום - טבלת כל ה-API Calls

### Frontend → Backend (WordPress REST API)

| Endpoint | Method | Parameters | Response | מה נשמר |
|----------|--------|------------|----------|---------|
| `/google/connect` | POST | `code`, `wordpress_scheduler_id` | `source_credentials_id`, `user_email` | Google credentials ב-Post Meta (לא נשמר source_credentials_id) |
| `/scheduler/source-calendars` | GET | `source_creds_id`, `wordpress_scheduler_id` | רשימת יומנים | ❌ לא נשמר |
| `/scheduler/create-schedule-in-proxy` | POST | `wordpress_scheduler_id`, `source_creds_id`, `source_scheduler_id`, `active_hours` | `proxy_schedule_id` (proxy) | `proxy_schedule_id` ב-Post Meta |
| `/scheduler/free-time` | GET | `wordpress_scheduler_id`, `duration`, `from_date_utc`, `to_date_utc` | רשימת slots | ❌ לא נשמר |
| `/scheduler/check-slot-available` | GET | `wordpress_scheduler_id`, `from_utc`, `duration` | `isAvailable` | ❌ לא נשמר |
| `/scheduler/properties` | GET | `wordpress_scheduler_id` | תכונות scheduler | ❌ לא נשמר |
| `/appointment/create` | POST | `scheduler_id`, `fromUTC`, `duration`, `customer` | תוצאה | ❌ לא נשמר |
| `/scheduler/drweb-calendar-reasons` | GET | `source_creds_id`, `drweb_calendar_id`, `scheduler_id` | רשימת סיבות | ❌ לא נשמר |
| `/scheduler/drweb-calendar-active-hours` | GET | `source_creds_id`, `drweb_calendar_id`, `scheduler_id` | שעות פעילות | ❌ לא נשמר |

### Backend → Proxy API

| Endpoint | Method | Headers | Body/Query | Response | מה נשמר |
|----------|--------|---------|------------|----------|---------|
| `/SourceCredentials/Save` | POST | `DoctorOnlineProxyAuthToken` | `sourceType`, `accessToken`, `accessTokenExpiresIn`, `refreshToken` | `sourceCredentialsID` | ❌ לא נשמר ב-WordPress |
| `/Scheduler/GetAllSourceCalendars` | GET | `DoctorOnlineProxyAuthToken` | `sourceCredsID` | רשימת יומנים | ❌ לא נשמר |
| `/Scheduler/Create` | POST | `DoctorOnlineProxyAuthToken` | `sourceCredentialsID`, `sourceSchedulerID`, `activeHours` | `schedulerID` | `proxy_schedule_id` ב-Post Meta |
| `/Scheduler/GetFreeTime` | GET | `DoctorOnlineProxyAuthToken` | `schedulerIDsStr`, `duration`, `fromDateUTC`, `toDateUTC` | רשימת slots | ❌ לא נשמר |
| `/Scheduler/CheckIsSlotAvailable` | GET | `DoctorOnlineProxyAuthToken` | `schedulerID`, `fromUTC`, `duration` | `isAvailable` | ❌ לא נשמר |
| `/Scheduler/GetSchedulersProperties` | GET | `DoctorOnlineProxyAuthToken` | `schedulerID` | תכונות scheduler | ❌ לא נשמר |
| `/Appointment/Create` | POST | `DoctorOnlineProxyAuthToken` | `schedulerID`, `fromUTC`, `duration`, `customer` | תוצאה | ❌ לא נשמר |
| `/Scheduler/GetDRWebCalendarReasons` | GET | `DoctorOnlineProxyAuthToken` | `sourceCredsID`, `drwebCalendarID` | רשימת סיבות | ❌ לא נשמר |
| `/Scheduler/GetDRWebCalendarActiveHours` | GET | `DoctorOnlineProxyAuthToken` | `sourceCredsID`, `drwebCalendarID` | שעות פעילות | ❌ לא נשמר |

### Backend → Google API

| Endpoint | Method | Headers | Body/Query | Response | מה נשמר |
|----------|--------|---------|------------|----------|---------|
| `https://oauth2.googleapis.com/token` | POST | `Content-Type: application/x-www-form-urlencoded` | `code`, `client_id`, `client_secret`, `redirect_uri`, `grant_type` | `access_token`, `refresh_token`, `expires_in` | ❌ לא נשמר (רק עובר לשלב הבא) |
| `https://www.googleapis.com/oauth2/v1/userinfo` | GET | `Authorization: Bearer {access_token}` | - | `email`, `name`, `picture` | ❌ לא נשמר (רק עובר לשלב הבא) |

---

## סיכום - מה נשמר ב-WordPress

### Custom Post Type: `schedules`

**מה זה:**
- `schedules` הוא Custom Post Type ב-WordPress שמייצג יומן (scheduler)
- כל פוסט מסוג `schedules` מייצג יומן אחד (Google Calendar או DRWeb)
- הפוסט נוצר על ידי המשתמש דרך טופס יצירת יומן

**איך נוצר:**
- נוצר דרך AJAX handler (`save_clinic_schedule`)
- נוצר עם `post_status = 'publish'`
- `post_author` = המשתמש הנוכחי
- `post_title` = שם היומן (למשל: "👨‍⚕️ רופא #123" או "📅 שם יומן")

**קשרים:**
- `clinic_id` (meta) - מזהה המרפאה
- `doctor_id` (meta) - מזהה הרופא (אופציונלי)
- `manual_calendar_name` (meta) - שם יומן ידני (אופציונלי)

**שלבי חיים:**
1. **יצירה** - פוסט נוצר עם `schedule_type`, `clinic_id`, `doctor_id`
2. **חיבור Google/DRWeb** - tokens נשלחים לפרוקסי (לא נשמרים ב-WordPress), מידע חיבור בסיסי נשמר (`google_connected`, `google_user_email`, `google_connected_at`)
3. **יצירת Scheduler בפרוקסי** - שמירת `proxy_schedule_id` (proxy scheduler ID)
4. **שימוש** - שימוש ב-`proxy_schedule_id` לכל פעולות הפרוקסי

### Scheduler Post Meta (post_type = 'schedules')

| שדה | מקור | מתי נשמר | שימוש |
|-----|------|-----------|------|
| `schedule_type` | Frontend form | יצירת scheduler | 'google' או 'clinix' |
| `clinic_id` | Frontend form | יצירת scheduler | מזהה מרפאה |
| `doctor_id` | Frontend form | יצירת scheduler | מזהה רופא |
| `proxy_schedule_id` | Proxy API | אחרי יצירת scheduler בפרוקסי | proxy scheduler ID (משמש לכל פעולות הפרוקסי) |
| `proxy_connected` | WordPress | אחרי יצירת scheduler בפרוקסי | `true` (מציין שהיומן מחובר לפרוקסי) |
| `proxy_connected_at` | WordPress | אחרי יצירת scheduler בפרוקסי | תאריך ושעה של החיבור |
| `google_connected` | WordPress | אחרי חיבור Google | `true` (מציין שהיומן מחובר ל-Google) |
| `google_user_email` | Google API | אחרי חיבור Google | אימייל המשתמש ב-Google |
| `google_connected_at` | WordPress | אחרי חיבור Google | תאריך ושעה של החיבור |
| `sunday`, `monday`, ... | Frontend form | יצירת scheduler | שעות פעילות (DRWeb) |

### User Meta

**⚠️ הערה חשובה**: `source_credentials_id` **לא נשמר** ב-WordPress (לא ב-Post Meta ולא ב-User Meta). הוא מוחזר מהפרוקסי אחרי שמירת credentials, אבל לא נשמר. 
- **מתי נדרש**: רק בשלבים הראשוניים עד קבלת `proxy_schedule_id`:
  - חיבור Google (`/google/connect`) - מקבלים `source_credentials_id` מהפרוקסי
  - קבלת רשימת יומנים (`/scheduler/source-calendars`) - צריך `source_credentials_id`
  - יצירת scheduler בפרוקסי (`/scheduler/create-schedule-in-proxy`) - צריך `source_credentials_id` + `sourceSchedulerID`
- **אחרי שיש `proxy_schedule_id`**: כל הפעולות משתמשות רק ב-`proxy_schedule_id` (והטוקן הראשי של הפרוקסי), ולא צריך יותר `source_credentials_id`

---

## מזהים חשובים

### 1. WordPress Scheduler ID (`wordpress_scheduler_id` ב-request)
- **מה זה**: מזהה הפוסט מסוג `schedules` ב-WordPress (WordPress post ID)
- **איפה**: ב-request parameters (כל ה-REST API endpoints)
- **שימוש**: לזיהוי הפוסט, לאימות הרשאות, לשליפת meta
- **שם בקוד**: `wordpress_scheduler_id` (או `wordpressSchedulerId` ב-JavaScript)
- **Legacy**: `scheduler_id` עדיין נתמך לתאימות לאחור

### 2. Proxy Scheduler ID (`proxy_schedule_id` ב-meta)
- **מה זה**: מזהה ה-scheduler בפרוקסי (proxy scheduler ID)
- **איפה**: `get_post_meta($wordpress_scheduler_id, 'proxy_schedule_id', true)`
- **שימוש**: לכל פעולות הפרוקסי (GetFreeTime, CreateAppointment, וכו')
- **מתי נשמר**: אחרי יצירת scheduler בפרוקסי (`POST /Scheduler/Create`)
- **⚠️ חשוב**: זה **לא** WordPress post ID - זה מזהה שונה מהפרוקסי

### 3. Source Scheduler ID (`sourceSchedulerID` / `source_scheduler_id`)
- **מה זה**: מזהה היומן ב-Source (Google Calendar ID או DRWeb Calendar ID)
- **איפה**: ב-response של `getAllSourceCalendars`
- **שימוש**: ליצירת scheduler בפרוקסי (`POST /Scheduler/Create`)
- **שמות נוספים**: `drwebCalendarID` (ב-DRWeb endpoints), `selected_calendar_id` (ב-formData)

### 4. Source Credentials ID (`sourceCredentialsID` / `source_credentials_id`)
- **מה זה**: מזהה ה-credentials בפרוקסי
- **איפה**: **לא נשמר ב-WordPress** - מוחזר מהפרוקסי אחרי שמירת credentials, אבל לא נשמר
- **מתי נדרש**: רק בשלבים הראשוניים עד קבלת `proxy_schedule_id`:
  - חיבור Google (`/google/connect`) - מקבלים `source_credentials_id` מהפרוקסי
  - קבלת רשימת יומנים (`/scheduler/source-calendars`) - צריך `source_credentials_id`
  - יצירת scheduler בפרוקסי (`/scheduler/create-schedule-in-proxy`) - צריך `source_credentials_id` + `sourceSchedulerID`
- **⚠️ חשוב**: אחרי שיש `proxy_schedule_id`, כל הפעולות משתמשות רק ב-`proxy_schedule_id` (והטוקן הראשי של הפרוקסי), ולא צריך יותר `source_credentials_id`
- **שימוש**: לכל פעולות הפרוקסי שדורשות credentials (GetAllSourceCalendars, CreateScheduler, וכו')
- **איך מקבלים**: הפרונט צריך לשלוח אותו בכל פעם שצריך להשתמש בו (מקבל אותו אחרי `POST /SourceCredentials/Save`)
- **⚠️ חשוב**: **לא נשמר ב-WordPress** - הפרונט צריך לשמור אותו בזיכרון/סשן

---

## דיאגרמות זרימה

### דיאגרמה 1: סקירה כללית - כל ה-Endpoints (עודכן - דצמבר 2025)
[דיאגרמה ויזואלית - סקירה כללית](https://www.figma.com/online-whiteboard/create-diagram/abdaed88-a9d9-49b2-a807-05f0cb2eb3c6?utm_source=chatgpt&utm_content=edit_in_figjam&oai_id=&request_id=2c2bcfc7-0cb4-4c3e-8274-b71c89ce78fd)

דיאגרמה זו מציגה את כל ה-endpoints והקשרים ביניהם:
- Frontend → Backend (עם `wordpress_scheduler_id`)
- Backend → Proxy API
- Backend → Google API
- WordPress Storage (Post Meta - `proxy_schedule_id` = proxy scheduler ID)

**שינויים חשובים**:
- כל ה-endpoints משתמשים ב-`wordpress_scheduler_id` במקום `scheduler_id`
- `proxy_schedule_id` ב-meta = proxy scheduler ID (לא WordPress post ID)
- `source_credentials_id` לא נשמר ב-WordPress

### דיאגרמה 2: זרימה מפורטת - כל Endpoint בנפרד (עודכן - דצמבר 2025)
[דיאגרמה ויזואלית - זרימה מפורטת](https://www.figma.com/online-whiteboard/create-diagram/b05c0867-2cac-48d0-84bf-643af75c4798?utm_source=chatgpt&utm_content=edit_in_figjam&oai_id=&request_id=fe8e493f-c6a9-4ac7-9005-b38ebecf2f86)

דיאגרמה זו מציגה את הזרימה המפורטת של כל endpoint עם כל השלבים, כולל:
- יצירת פוסט (`wordpress_scheduler_id`)
- חיבור Google Calendar (`wordpress_scheduler_id`)
- שליפת יומנים (`wordpress_scheduler_id`)
- יצירת scheduler בפרוקסי (`wordpress_scheduler_id` → `proxy_schedule_id` proxy)
- שימוש ב-scheduler (`wordpress_scheduler_id` → `proxy_schedule_id` proxy)

**שינויים חשובים**:
- כל ה-requests משתמשים ב-`wordpress_scheduler_id` (WordPress post ID)
- ה-proxy scheduler ID נשמר ב-meta כ-`proxy_schedule_id`
- `source_credentials_id` לא נשמר ב-WordPress - נדרש רק בשלבים הראשוניים (עד קבלת `proxy_schedule_id`)
- אחרי שיש `proxy_schedule_id`, כל הפעולות משתמשות רק בו (והטוקן הראשי של הפרוקסי)

---

## הערות חשובות

1. **sourceCredentialsID לא נשמר ב-WordPress** - הוא מוחזר מהפרוקסי אחרי שמירת credentials, אבל לא נשמר ב-WordPress (לא ב-Post Meta ולא ב-User Meta). 
   - **מתי נדרש**: רק בשלבים הראשוניים עד קבלת `proxy_schedule_id`:
     - חיבור Google (`/google/connect`) - מקבלים `source_credentials_id` מהפרוקסי
     - קבלת רשימת יומנים (`/scheduler/source-calendars`) - צריך `source_credentials_id`
     - יצירת scheduler בפרוקסי (`/scheduler/create-schedule-in-proxy`) - צריך `source_credentials_id` + `sourceSchedulerID`
   - **אחרי שיש `proxy_schedule_id`**: כל הפעולות משתמשות רק ב-`proxy_schedule_id` (והטוקן הראשי של הפרוקסי), ולא צריך יותר `source_credentials_id`

2. **שלושה מזהים שונים**:
   - WordPress Post ID - מזהה הפוסט
   - Proxy Scheduler ID - מזהה ה-scheduler בפרוקסי (נשמר ב-meta)
   - Source Scheduler ID - מזהה היומן ב-Source (Google/DRWeb)

3. **activeHours**:
   - **Google Calendar**: חובה, נשלח מ-frontend ב-request body
   - **DRWeb**: אופציונלי, נשלח מ-Scheduler Post Meta או מ-request body

4. **לא נשמר ב-WordPress**:
   - תורים (appointments) - רק בפרוקסי
   - זמנים פנויים (free time slots) - רק בפרוקסי
   - רשימת יומנים (calendars) - רק בפרוקסי
   - **source_credentials_id** - לא נשמר ב-WordPress, מוחזר מהפרוקסי אבל לא נשמר. נדרש רק בשלבים הראשוניים עד קבלת `proxy_schedule_id`

---

**עודכן**: דצמבר 2025

