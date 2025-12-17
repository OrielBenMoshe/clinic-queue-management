# API Integration - DoctorOnline Proxy

## Overview
This plugin integrates with the DoctorOnline Proxy API to fetch appointment availability in real-time.

## API Endpoint
**Base URL:** `https://do-proxy-staging.doctor-clinix.com`

## Endpoints Used

### POST /Scheduler/GetFreeTime
Retrieves free time slots for scheduling appointments.

**Headers:**
- `Content-Type: application/json`
- `Accept: application/json`
- `DoctorOnlineProxyAuthToken: {token}` (required) - API authentication token
  - If `CLINIC_QUEUE_API_TOKEN` constant is defined, uses that token
  - Otherwise, falls back to scheduler_id (legacy behavior)

**Request Body (GetFreeTimeRequest):**
```json
{
  "schedulers": [0],                  // Array of scheduler IDs (required) - usually contains one ID
  "drWebBranchID": 0,                 // Optional: Branch/clinic ID (default: 0)
  "duration": 0,                     // Slot duration in minutes (default: 30)
  "fromDate": "2025-11-17T12:29:33.668Z",  // Start date (required, ISO 8601 with milliseconds)
  "toDate": "2025-11-17T12:29:33.668Z",    // End date (required, ISO 8601 with milliseconds)
  "fromTime": {                       // Start time (TimeSpan)
    "ticks": 0                        // Ticks for time (e.g., 288000000000 for 8:00 AM)
  },
  "toTime": {                         // End time (TimeSpan)
    "ticks": 0                        // Ticks for time (e.g., 720000000000 for 8:00 PM)
  }
}
```

**Note:** 
- `DoctorOnlineProxyAuthToken` in header = API token (from constant, option, or scheduler_id as fallback)
- Date format: ISO 8601 with milliseconds (e.g., `2025-11-17T12:29:33.668Z`)
- Ticks calculation: `(hours * 60 * 60 + minutes * 60) * 10,000,000`

**Response (BaseListResponse<FreeTimeSlotModel>):**
```json
{
  "code": "Success" | "Undefined" | ...,
  "error": "string" | null,
  "result": [
    {
      "schedulerID": 0,
      "drWebBranchID": 0,
      "from": "2025-11-17T12:29:33.675Z",
      "to": "2025-11-17T12:29:33.675Z"
    }
  ],
  "serverTime": "string",
  "nextPageToken": "string" | null
}
```

## Configuration

### Constants
Define in `wp-config.php`:
```php
// API Endpoint
define('CLINIC_QUEUE_API_ENDPOINT', 'https://do-proxy-staging.doctor-clinix.com');

// API Authentication Token (RECOMMENDED - Most Secure)
// This token will be used for all API requests
// If not defined, the plugin will fall back to scheduler_id (legacy behavior)
define('CLINIC_QUEUE_API_TOKEN', 'your-api-token-here');
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
define('CLINIC_QUEUE_API_TOKEN', 'pMtGAAMhbpLg21nFPaUhEr6UJaeUcrrHhTvmzewMkEc7gwTGv2EpGm8Xp7C6wHRutncWp78ceV30Qp3XroYoM9mzQCqvJ3NGnEpp');
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
- `CustomerNotFound` - Customer not found
- `InvalidCredential` - Invalid credentials
- `InnerServerError` - Server error
- `Error` - General error

## How It Works

1. **Widget Configuration**: Each widget has settings for "מזהה רופא קבוע" (doctor ID) and "מזהה מרפאה קבוע" (clinic ID)
2. **API Request**: 
   - The doctor/calendar ID is used as the scheduler ID
   - The same ID is sent as `DoctorOnlineProxyAuthToken` in the header
   - The clinic ID (if provided) is sent as `drWebBranchID` in the request body
3. **Response**: Only free/available slots are returned (no booked slots)

## Notes
- All dates/times are in UTC format (ISO 8601 with milliseconds)
- The plugin converts UTC to Asia/Jerusalem timezone for display
- Scheduler IDs = calendar/doctor IDs from widget settings
- `DoctorOnlineProxyAuthToken` = API token (from constant, option, or scheduler_id as fallback)
- If no scheduler ID is provided, the plugin falls back to mock data

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
1. **`CLINIC_QUEUE_API_TOKEN` constant** (from `wp-config.php`) - Most secure
2. **WordPress option** (`clinic_queue_api_token_encrypted`) - Can be set via admin settings page
3. **Filter** (`clinic_queue_api_token`)
4. **Fallback to scheduler_id** (legacy behavior)

### API Endpoint Priority:
1. **`CLINIC_QUEUE_API_ENDPOINT` constant** (from `wp-config.php`)
2. **WordPress option** (`clinic_queue_api_endpoint`) - Can be set via admin settings page
3. **Filter** (`clinic_queue_api_endpoint`)

## Admin Settings Page

You can configure both the API token and endpoint through the WordPress admin:

1. Go to **ניהול תורים > הגדרות** (Clinic Queue > Settings)
2. Enter your API token in the "טוקן API" field
3. Enter your API endpoint in the "כתובת API" field
4. Click "שמור הגדרות" (Save Settings)

**Note:** If constants are defined in `wp-config.php`, they will take priority over admin settings.

