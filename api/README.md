# API Integration - DoctorOnline Proxy

## Overview
This plugin integrates with the DoctorOnline Proxy API to fetch appointment availability in real-time.

**Last Updated:** December 2025 - Updated based on latest Swagger documentation

## API Endpoint
**Base URL:** `https://do-proxy-staging.doctor-clinix.com`

## Endpoints Used

### GET /Scheduler/GetFreeTime
Retrieves free time slots for scheduling appointments.

**⚠️ IMPORTANT CHANGE (Dec 2025):** This endpoint changed from POST to GET.

**Headers:**
- `Accept: application/json` (required)
- `DoctorOnlineProxyAuthToken: {token}` (required) - API authentication token
  - If `DOCTOR_ONLINE_PROXY_AUTH_TOKEN` constant is defined, uses that token
  - Otherwise, falls back to scheduler_id (legacy behavior)

**Query Parameters:**
- `schedulerIDsStr` (required, string) - Comma-separated string of scheduler IDs (e.g., "123" or "123,456")
- `duration` (required, integer) - Slot duration in minutes (e.g., 30)
- `fromDateUTC` (required, string) - Start date in UTC, ISO 8601 format (e.g., "2025-11-25T00:00:00Z")
- `toDateUTC` (required, string) - End date in UTC, ISO 8601 format (e.g., "2025-11-27T00:00:00Z")

**Example Request:**
```
GET /Scheduler/GetFreeTime?schedulerIDsStr=123&duration=30&fromDateUTC=2025-11-25T00:00:00Z&toDateUTC=2025-11-27T00:00:00Z
Headers:
  Accept: application/json
  DoctorOnlineProxyAuthToken: your-token-here
```

**Response (ListResultBaseResponse<FreeTimeSlotModel>):**
```json
{
  "code": "Success" | "Undefined" | "CacheMiss" | ...,
  "error": "string" | null,
  "result": [
    {
      "from": "2025-12-28T16:00:55.185Z",
      "schedulerID": 0
    }
  ]
}
```

**⚠️ IMPORTANT CHANGE (Dec 2025):** The `to` field was removed from `FreeTimeSlotModel`. Only `from` is returned. Calculate the end time by adding the `duration` to `from`.

**Note about CacheMiss:**
If you receive a `CacheMiss` error code, wait a few seconds and try again. This means the data is not yet in the cache.

### POST /Appointment/Create
Creates a new appointment.

**Headers:**
- `Content-Type: application/json`
- `Accept: application/json`
- `DoctorOnlineProxyAuthToken: {token}` (required)

**Request Body (AppointmentModel):**
```json
{
  "schedulerID": 0,
  "customer": {
    "firstName": "string",
    "lastName": "string",
    "identityType": "Undefined" | "IsraeliID" | "Passport" | "Other",
    "identity": "string",
    "email": "string",
    "mobilePhone": "string",
    "gender": "NotSet" | "Male" | "Female" | "Other",
    "birthDate": "2025-12-28T16:00:43.624Z"
  },
  "startAtUTC": "2025-12-28T16:00:43.624Z",
  "drWebReasonID": 0,
  "remark": "string",
  "duration": 0
}
```

**Response (BaseResponse):**
```json
{
  "code": "Success" | "Error" | ...,
  "error": "string" | null
}
```

### GET /Scheduler/CheckIsSlotAvailable
Force check if a slot is free (real-time, not cached). Also checks if the slot is within active hours.

**Headers:**
- `Accept: application/json`
- `DoctorOnlineProxyAuthToken: {token}` (required)

**Query Parameters:**
- `schedulerID` (required, integer) - The scheduler ID
- `fromUTC` (required, string) - Start date/time in UTC (ISO 8601, e.g., "2025-12-28T10:00:00Z")
- `duration` (required, integer) - Slot duration in minutes

**Response (ResultBaseResponse<Boolean>):**
```json
{
  "code": "Success" | "Error" | ...,
  "error": "string" | null,
  "result": true
}
```

### GET /Scheduler/GetAllSourceCalendars
Get all available calendars from a source (e.g., Google Calendar, DRWeb).

**Headers:**
- `Accept: application/json`
- `DoctorOnlineProxyAuthToken: {token}` (required)

**Query Parameters:**
- `sourceCredsID` (required, integer) - The source credentials ID

**Response (ListResultBaseResponse<SourceSchedulerModel>):**
```json
{
  "code": "Success" | "Error" | ...,
  "error": "string" | null,
  "result": [
    {
      "name": "string",
      "sourceSchedulerID": "string",
      "description": "string"
    }
  ]
}
```

### GET /Scheduler/GetDRWebCalendarReasons
Get DRWeb calendar reasons (appointment types/reasons).

**Headers:**
- `Accept: application/json`
- `DoctorOnlineProxyAuthToken: {token}` (required)

**Query Parameters:**
- `sourceCredsID` (required, integer) - The source credentials ID
- `drwebCalendarID` (required, string) - The DRWeb calendar ID

**Response (ListResultBaseResponse<DRWebSchedulerEventReason>):**
```json
{
  "code": "Success" | "Error" | ...,
  "error": "string" | null,
  "result": [
    {
      "id": 0,
      "name": "string",
      "duration": 0
    }
  ]
}
```

### POST /Scheduler/Create
Create a new scheduler.

**Headers:**
- `Content-Type: application/json`
- `Accept: application/json`
- `DoctorOnlineProxyAuthToken: {token}` (required)

**Request Body (CreateSchedulerModel):**
```json
{
  "sourceCredentialsID": 0,
  "sourceSchedulerID": "string",
  "activeHours": [
    {
      "weekDay": "Sunday" | "Monday" | "Tuesday" | "Wednesday" | "Thursday" | "Friday" | "Saturday",
      "fromUTC": {
        "ticks": 0
      },
      "toUTC": {
        "ticks": 0
      }
    }
  ],
  "maxOverlappingMeeting": 0,
  "overlappingDurationInMinutes": 0
}
```

**Response (ResultBaseResponse<Int32>):**
```json
{
  "code": "Success" | "Error" | ...,
  "error": "string" | null,
  "result": 123
}
```

### POST /SourceCredentials/Save
Save source credentials (e.g., Google Calendar OAuth tokens, DRWeb credentials).

**Headers:**
- `Content-Type: application/json`
- `Accept: application/json`
- `DoctorOnlineProxyAuthToken: {token}` (required)

**Request Body (SourceCredentialsModel):**
```json
{
  "sourceType": "Unknown" | "GoogleCalendar" | "DRWeb" | ...,
  "accessToken": "string",
  "accessTokenExpiresIn": "2025-12-28T16:01:32.063Z",
  "refreshToken": "string"
}
```

**Response (ResultBaseResponse<Int32>):**
```json
{
  "code": "Success" | "Error" | ...,
  "error": "string" | null,
  "result": 456
}
```

## Configuration

### Constants
Define in `wp-config.php`:
```php
// API Base URL
define('DOCTOR_ONLINE_PROXY_BASE_URL', 'https://do-proxy-staging.doctor-clinix.com');

// API Authentication Token (RECOMMENDED - Most Secure)
// This token will be used for all API requests
// If not defined, the plugin will fall back to scheduler_id (legacy behavior)
define('DOCTOR_ONLINE_PROXY_AUTH_TOKEN', 'your-api-token-here');
```

**Security Best Practices:**
1. **RECOMMENDED:** Store the token in `wp-config.php` as a constant (most secure - not in database)
2. **Alternative:** Store encrypted in WordPress options (see below)
3. **Fallback:** Use scheduler_id as token (legacy behavior, less secure)

### Storing Token Securely

#### Option 1: Admin Settings Page (EASIEST)
1. Go to **ניהול תורים > הגדרות** in WordPress admin
2. Enter your API token in the "טוקן API" field
3. Click "שמור הגדרות"
4. The token will be encrypted and stored in WordPress options

**Note:** If a token is defined in `wp-config.php` as a constant, it will take priority over the admin setting.

#### Option 2: wp-config.php (RECOMMENDED - Most Secure)
Add to `wp-config.php` (outside web root, not in version control):
```php
define('DOCTOR_ONLINE_PROXY_AUTH_TOKEN', 'pMtGAAMhbpLg21nFPaUhEr6UJaeUcrrHhTvmzewMkEc7gwTGv2EpGm8Xp7C6wHRutncWp78ceV30Qp3XroYoM9mzQCqvJ3NGnEpp');
```

**Why this is secure:**
- `wp-config.php` is outside the web root (not accessible via HTTP)
- Not stored in database
- Not exposed in WordPress admin
- Can be excluded from version control (add to `.gitignore`)

**Note:** Tokens defined in `wp-config.php` take priority over admin settings.

#### Option 3: Filter (Programmatic)
```php
// Override token programmatically
add_filter('clinic_queue_api_token', function($token, $scheduler_id) {
    // Return your token from secure storage
    return 'your-token-here';
}, 10, 2);
```

### Filters
```php
// Change API endpoint
add_filter('clinic_queue_api_endpoint', function($endpoint) {
    return 'https://your-custom-endpoint.com';
});

// Override API token programmatically
add_filter('clinic_queue_api_token', function($token, $scheduler_id) {
    // Return token from your secure storage
    return get_option('my_custom_token');
}, 10, 2);
```

## Response Codes
- `Success` - Request successful
- `CacheMiss` - Data not in cache (for GetFreeTime - wait and retry)
- `CustomerNotFound` - Customer not found
- `InvalidCredential` - Invalid credentials
- `InnerServerError` - Server error
- `Error` - General error
- `Undefined` - Undefined error

## Customer Identity Types
- `Undefined` - Not specified
- `IsraeliID` - Israeli ID number (תעודת זהות)
- `Passport` - Passport number
- `Other` - Other identity type

## Gender Types
- `NotSet` - Not specified
- `Male` - Male (זכר)
- `Female` - Female (נקבה)
- `Other` - Other

## Week Days (for Active Hours)
- `Sunday` - ראשון
- `Monday` - שני
- `Tuesday` - שלישי
- `Wednesday` - רביעי
- `Thursday` - חמישי
- `Friday` - שישי
- `Saturday` - שבת

## TimeSpan Ticks
For active hours, time is represented in .NET TimeSpan ticks:
- **Calculation:** `(hours * 60 * 60 + minutes * 60 + seconds) * 10,000,000`
- **Example:** 8:00 AM = `8 * 60 * 60 * 10,000,000` = `288,000,000,000` ticks
- **Example:** 5:30 PM = `(17 * 60 * 60 + 30 * 60) * 10,000,000` = `630,000,000,000` ticks

## How It Works

1. **Widget Configuration**: Each widget has settings for "מזהה רופא קבוע" (doctor ID) and "מזהה מרפאה קבוע" (clinic ID)
2. **API Request (Updated Dec 2025)**: 
   - The doctor/calendar ID is used as the scheduler ID
   - Multiple scheduler IDs can be sent as a comma-separated string in `schedulerIDsStr`
   - Authentication is done via `DoctorOnlineProxyAuthToken` header (API token, not scheduler ID)
   - All dates are in UTC format (ISO 8601, e.g., "2025-11-25T00:00:00Z")
3. **Response**: 
   - Only free/available slots are returned (no booked slots)
   - Each slot contains only `from` time - calculate `to` by adding `duration`
   - CacheMiss errors should be handled by waiting and retrying

## Major Changes (December 2025)

Based on the latest Swagger documentation:

### 1. GetFreeTime Endpoint
- **Changed from POST to GET**
- **Parameters moved to query string** instead of request body
- **schedulerIDsStr** - Now a comma-separated string (was array `schedulers`)
- **Removed:** `drWebBranchID`, `fromTime`/`toTime` (ticks)
- **Simplified date parameters:** Only `fromDateUTC` and `toDateUTC` (full datetime in UTC)

### 2. FreeTimeSlotModel
- **Removed `to` field** - Only `from` is returned
- **Removed `drWebBranchID` field**
- Calculate end time: `from` + `duration` minutes

### 3. Authentication
- **DoctorOnlineProxyAuthToken** is now a proper API token
- Priority: constant > option > filter > fallback to scheduler_id
- Store token securely in `wp-config.php` as `DOCTOR_ONLINE_PROXY_AUTH_TOKEN`

## Notes
- All dates/times are in UTC format (ISO 8601, e.g., "2025-11-25T00:00:00Z")
- The plugin converts UTC to Asia/Jerusalem timezone for display
- Scheduler IDs = calendar/doctor IDs from widget settings
- `DoctorOnlineProxyAuthToken` = API token (from constant, option, or scheduler_id as fallback)
- If no scheduler ID is provided, the plugin falls back to mock data
- **CacheMiss:** If you get this error code, wait a few seconds and retry the request

## Security Recommendations

1. **Never commit tokens to version control**
   - Add `wp-config.php` to `.gitignore`
   - Use environment variables in production

2. **Use wp-config.php for tokens** (most secure)
   - Tokens in constants are not stored in database
   - Not accessible via HTTP requests
   - Not visible in WordPress admin

3. **Rotate tokens regularly**
   - Update the constant in `wp-config.php` when rotating

4. **Monitor API usage**
   - Check WordPress error logs for authentication failures
   - Monitor API response codes

5. **Use HTTPS only**
   - Ensure API endpoint uses HTTPS
   - Never send tokens over unencrypted connections

## Priority Order

### API Token Priority:
1. **`DOCTOR_ONLINE_PROXY_AUTH_TOKEN` constant** (from `wp-config.php`) - Most secure
2. **WordPress option** (`clinic_queue_api_token_encrypted`) - Can be set via admin settings page
3. **Filter** (`clinic_queue_api_token`)
4. **Fallback to scheduler_id** (legacy behavior)

### API Base URL Priority:
1. **`DOCTOR_ONLINE_PROXY_BASE_URL` constant** (from `wp-config.php`)
2. **WordPress option** (`clinic_queue_api_endpoint`) - Can be set via admin settings page
3. **Filter** (`clinic_queue_api_endpoint`)

## Admin Settings Page

You can configure both the API token and endpoint through the WordPress admin:

1. Go to **ניהול תורים > הגדרות** (Clinic Queue > Settings)
2. Enter your API token in the "טוקן API" field
3. Enter your API endpoint in the "כתובת API" field
4. Click "שמור הגדרות" (Save Settings)

**Note:** If constants are defined in `wp-config.php`, they will take priority over admin settings.

