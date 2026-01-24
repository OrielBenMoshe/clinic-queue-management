# מערכת ניהול תורים למרפאות - Clinic Queue Management

![Version](https://img.shields.io/badge/version-0.3.0-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-5.8+-brightgreen.svg)
![PHP](https://img.shields.io/badge/php-7.4+-purple.svg)

פלאגין WordPress מתקדם לניהול תורים במרפאות רפואיות עם תמיכה מלאה בעברית ואינטגרציה ל-Elementor.

## 🎉 גירסה 0.3.0 - API Architecture v2.0 (ינואר 2026)

**Refactoring משמעותי:** ארכיטקטורת API מודולרית חדשה!
- ✅ פיצול `class-rest-handlers.php` מ-1537 שורות ל-6 handlers מודולריים
- ✅ Registry Pattern לניהול נקי
- ✅ 100% Backward Compatible - אין צורך בשינויים ב-frontend
- 📚 ראה [CHANGELOG.md](CHANGELOG.md) לפרטים מלאים

## תכונות עיקריות

- **3 Shortcodes**: `[booking_calendar]`, `[booking_form]`, `[schedule_form]` - פתרונות גמישים לכל צורך
- **אינטגרציה עם Elementor**: ווידג'ט גרירה ושחרור לניהול תורים (אופציונלי)
- **REST API מלא**: 15+ endpoints לניהול תורים, יומנים, ואינטגרציות
- **JavaScript מודרני**: קוד נקי עם ES6+, מודולרי ומאורגן
- **תמיכה מלאה ב-RTL**: עיצוב מותאם לעברית וערבית
- **עיצוב רספונסיבי**: עובד על כל הגדלי מסך
- **נגישות**: ניווט מקלדת ותמיכה בקוראי מסך
- **מספר מופעים**: מותאם למספר shortcodes באותו דף
- **ביצועים מותאמים**: נכסים נטענים פעם אחת, Cache משותף
- **ממשק ניהול מתקדם**: דשבורד, הגדרות, ועזרה

## Installation

1. Copy the `clinic-queue-management` folder to your WordPress `wp-content/plugins/` directory
2. Activate the plugin in your WordPress admin panel
3. Configure your API token in **ניהול תורים > הגדרות**
4. (Optional) If using Elementor, the "Clinic Queue" widget will be accessible in Elementor's General widgets category
5. Use shortcodes `[booking_calendar]`, `[booking_form]`, or `[schedule_form]` in your content

## Usage

### 1. Using the Booking Calendar Shortcode

Add the booking calendar shortcode anywhere in your WordPress content:

```
[booking_calendar]
```

**Shortcode Parameters:**
- `mode` (optional): `auto`, `doctor`, or `clinic` (default: `auto`)
- `doctor_id` (optional): Doctor ID (auto-detected on doctor pages)
- `clinic_id` (optional): Clinic ID (auto-detected on clinic pages)
- `treatment_type` (optional): Pre-selected treatment type

For more details, see [Booking Calendar Documentation](frontend/shortcodes/booking-calendar/README.md).

### 2. Using the Booking Form Shortcode

Add the booking form shortcode to create appointments:

```
[booking_form]
```

**Shortcode Parameters:**
- `scheduler_id` (optional): Scheduler ID for the appointment
- `doctor_id` (optional): Doctor ID (auto-detected on doctor pages)
- `clinic_id` (optional): Clinic ID (auto-detected on clinic pages)
- `treatment_type` (optional): Pre-selected treatment type

This shortcode provides a complete form for creating appointments with customer details.

### 3. Using the Schedule Form Shortcode

Add the schedule form shortcode to create new schedules:

```
[schedule_form]
```

This shortcode provides a multi-step form for creating Google Calendar or DRWeb schedules.

### 4. Admin Interface

Access the admin interface through **ניהול תורים** in WordPress admin:

- **Dashboard**: Overview of schedules and appointments
- **Settings**: Configure API token and endpoint
- **Help**: Documentation and troubleshooting

### 5. API Integration

The plugin integrates with the DoctorOnline Proxy API for real-time appointment data. Configure your API token in the admin settings page.

**Available REST API Endpoints:**
- `POST /wp-json/clinic-queue/v1/appointment/create` - Create new appointment
- `GET /wp-json/clinic-queue/v1/scheduler/free-time` - Get available time slots
- `GET /wp-json/clinic-queue/v1/scheduler/check-slot-available` - Check if slot is available
- `GET /wp-json/clinic-queue/v1/scheduler/source-calendars` - Get calendars from source
- `GET /wp-json/clinic-queue/v1/scheduler/drweb-calendar-reasons` - Get DRWeb calendar reasons
- `GET /wp-json/clinic-queue/v1/scheduler/drweb-calendar-active-hours` - Get DRWeb active hours
- `GET /wp-json/clinic-queue/v1/scheduler/properties` - Get scheduler properties
- `POST /wp-json/clinic-queue/v1/scheduler/create-schedule-in-proxy` - Create scheduler in proxy
- `POST /wp-json/clinic-queue/v1/source-credentials/save` - Save source credentials
- `GET /wp-json/clinic-queue/v1/google/connect` - Google Calendar OAuth
- `GET /wp-json/clinic-queue/v1/google/calendars` - Get Google calendars
- Legacy endpoints: `/appointments`, `/all-appointments`, `/scheduler/create-proxy`

For detailed API documentation, see [API README](api/README.md) and [API Architecture](api/ARCHITECTURE.md).

### 6. Multiple Instances

You can use multiple shortcodes on the same page with different configurations:

```
[booking_calendar doctor_id="1" clinic_id="1"]
[booking_calendar doctor_id="1" clinic_id="2"]
[booking_calendar doctor_id="2" clinic_id="3"]
```

The plugin automatically optimizes performance by:
- Loading CSS/JS assets only once per page
- Sharing data cache between similar instances
- Providing unique identification for each instance

## Features

### Real-time Appointment Data
The plugin fetches appointment availability in real-time from the DoctorOnline Proxy API. No local data storage required.

### Google Calendar Integration
Create and manage schedules connected to Google Calendar through the schedule form shortcode.

### DRWeb Integration
Support for DRWeb calendar integration for clinics using the DRWeb system.

### JetEngine Integration
Full integration with JetEngine for Custom Post Types, Meta Fields, and Relations. The plugin includes:
- Dynamic treatment types from API (60+ medical sub-specialties)
- Automatic injection into Meta Fields and JetFormBuilder forms
- Relations management service for doctor-clinic-appointment relationships
- REST API fields for doctors and clinics post types

### REST API
Complete REST API with 15+ endpoints for external integrations. Full architecture with Models, Services, Validation, and Error Handling layers. See [API Documentation](api/README.md) and [API Architecture](api/ARCHITECTURE.md) for details.

## מסמכים מפורטים

כל המסמכים המפורטים נמצאים בתיקיות המתאימות:

### 📚 תיעוד כללי
- **[אינדקס מהיר](docs/INDEX.md)** - מדריך לפי תפקיד
- **[תיקיית תיעוד](docs/README.md)** - מדריך לשימוש במסמכים

### 🔌 API
- **[API README](api/README.md)** - תיעוד מלא של ה-API
- **[API Architecture](api/ARCHITECTURE.md)** - ארכיטקטורת ה-API
- **[API Flow Diagram](API_FLOW_DIAGRAM.md)** - דיאגרמת זרימת API
- **[Token Flow](api/TOKEN_FLOW.md)** - זרימת טוקן API
- **[Security](api/SECURITY.md)** - אבטחת טוקן API

### ⚙️ Admin
- **[Refactor Summary](admin/REFACTOR_SUMMARY.md)** - סיכום Refactor של תיקיית Admin
- **[Relations Fix](admin/RELATIONS_FIX.md)** - תיקון בעיית Relations

### 🎨 Frontend
- **[Booking Calendar](frontend/shortcodes/booking-calendar/README.md)** - תיעוד שורטקוד יומן תורים
- **[Treatments Update](frontend/TREATMENTS_UPDATE.md)** - עדכון אזור הגדרת טיפולים

### 🔧 Core
- **[JetEngine Integration](core/JETENGINE_INTEGRATION.md)** - אינטגרציה עם JetEngine

### 🐛 Debug
- **[Debug Instructions](DEBUG_INSTRUCTIONS.md)** - הוראות דיבאג

## Development

### מבנה קבצים מפורט
```
clinic-queue-management/
├── clinic-queue-management.php          # נקודת כניסה ראשית
├── README.md                           # תיעוד בסיסי
├── DEBUG_INSTRUCTIONS.md               # הוראות דיבאג
├── API_FLOW_DIAGRAM.md                 # דיאגרמת זרימת API
│
├── core/                               # ליבת המערכת
│   ├── class-plugin-core.php          # מנהל מרכזי
│   ├── class-helpers.php              # פונקציות עזר
│   ├── class-jetengine-integration.php # אינטגרציה עם JetEngine
│   ├── class-database-manager.php      # מנהל מסד נתונים
│   ├── class-feature-toggle.php        # ניהול תכונות
│   └── constants.php                   # קבועים
│
├── api/                                # ממשקי API
│   ├── class-api-manager.php          # מנהל API (legacy)
│   ├── class-rest-handlers.php        # REST API handlers
│   ├── services/                      # Services Layer
│   │   ├── class-base-service.php
│   │   ├── class-appointment-service.php
│   │   ├── class-scheduler-service.php
│   │   └── ...
│   ├── models/                        # Data Transfer Objects
│   ├── validation/                    # Validation Layer
│   └── handlers/                      # Error Handlers
│
├── admin/                              # ממשק ניהול
│   ├── class-admin-menu.php           # תפריט ניהול (routing)
│   ├── class-settings.php             # Legacy wrapper
│   ├── class-dashboard.php            # דשבורד ראשי
│   ├── class-help.php                 # עזרה
│   ├── handlers/                      # Business Logic
│   │   └── class-settings-handler.php
│   ├── services/                      # Shared Services
│   │   ├── class-encryption-service.php
│   │   └── class-relations-service.php
│   ├── ajax/                          # AJAX Handlers
│   │   └── class-ajax-handlers.php
│   ├── views/                         # HTML Templates
│   └── assets/                        # CSS/JS
│
├── frontend/                           # ממשק משתמש
│   ├── shortcodes/                    # Shortcodes
│   │   ├── booking-calendar/          # שורטקוד יומן תורים
│   │   ├── booking-form/              # שורטקוד טופס יצירת תור
│   │   └── schedule-form/              # טופס יצירת יומן
│   └── oauth-callback.php            # Google OAuth callback
│
├── assets/                             # נכסים סטטיים
│   ├── css/                           # סגנונות
│   └── js/                            # JavaScript
│
└── docs/                               # תיעוד מפורט
    ├── README.md                      # מדריך תיעוד
    └── INDEX.md                       # אינדקס מהיר
```

### Architecture

- **PHP Backend**: Handles shortcode rendering, AJAX endpoints, REST API, and data processing
- **JavaScript Frontend**: Modular ES6+ code managing UI interactions, instance coordination, and caching
- **CSS Styling**: Responsive design with RTL support, organized in shared components
- **Layered API Architecture**: Models → Services → Validation → Error Handling
- **Admin Architecture**: Separation of concerns (Handlers → Services → Views → Assets)
- **No Build Process**: Direct file serving, no compilation needed

### Dependencies
- WordPress 5.0+
- JetEngine (for Custom Post Types and Relations)
- JetFormBuilder (optional, for form building)
- jQuery (included with WordPress)
- Modern browser with ES6 support

### Browser Support
- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+

## Customization

### Styling
Override styles by targeting plugin classes in your theme:

```css
/* Booking Calendar */
.clinic-booking-calendar {
    /* Your custom styles */
}

/* Booking Form */
.clinic-booking-form {
    /* Your custom styles */
}

/* Schedule Form */
.clinic-schedule-form {
    /* Your custom styles */
}
```

### RTL Support
All shortcodes and widgets automatically detect RTL languages and adjust the layout accordingly. The plugin includes full RTL support for Hebrew and Arabic.

## Troubleshooting

### Shortcode Not Appearing
- Check that the shortcode is correctly formatted
- Clear any caching plugins
- Check browser console for JavaScript errors
- See [Debug Instructions](DEBUG_INSTRUCTIONS.md) for detailed troubleshooting

### API Connection Issues
- Verify API token is configured in admin settings
- Check API endpoint URL is correct
- Review [API Documentation](api/README.md) for API requirements
- Check WordPress error logs for API errors

### Schedule Creation Issues
- Ensure Google Calendar is properly connected
- Verify clinic and doctor IDs are correct
- Check [Relations Fix Documentation](admin/RELATIONS_FIX.md) for relation issues

## License

This plugin is released under the GPL v2 license.