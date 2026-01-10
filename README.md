# מערכת ניהול תורים למרפאות - Clinic Queue Management

פלאגין WordPress מתקדם לניהול תורים במרפאות רפואיות עם תמיכה מלאה בעברית ואינטגרציה ל-Elementor.

## תכונות עיקריות

- **אינטגרציה עם Elementor**: ווידג'ט גרירה ושחרור לניהול תורים
- **תמיכה ב-Shortcode**: שימוש ב-`[clinic_queue]` בכל מקום ב-WordPress
- **JavaScript מודרני**: קוד נקי עם jQuery ותמיכה ב-ES6
- **תמיכה מלאה ב-RTL**: עיצוב מותאם לעברית וערבית
- **עיצוב רספונסיבי**: עובד על כל הגדלי מסך
- **נגישות**: ניווט מקלדת ותמיכה בקוראי מסך
- **מספר מופעים**: מותאם למספר ווידג'טים באותו דף
- **אירועים מותאמים**: שליחת אירועי בחירה לאינטגרציה
- **ביצועים מותאמים**: נכסים נטענים פעם אחת, Cache משותף
- **ממשק ניהול מתקדם**: דשבורד והגדרות

## Installation

1. Copy the `clinic-queue-management` folder to your WordPress `wp-content/plugins/` directory
2. Activate the plugin in your WordPress admin panel
3. The "Clinic Queue" widget will be accessible in Elementor's General widgets category

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

### 2. Using the Schedule Form Shortcode

Add the schedule form shortcode to create new schedules:

```
[schedule_form]
```

This shortcode provides a multi-step form for creating Google Calendar or DRWeb schedules.

### 3. Admin Interface

Access the admin interface through **ניהול תורים** in WordPress admin:

- **Dashboard**: Overview of schedules and appointments
- **Settings**: Configure API token and endpoint
- **Help**: Documentation and troubleshooting

### 4. API Integration

The plugin integrates with the DoctorOnline Proxy API for real-time appointment data. Configure your API token in the admin settings page.

For detailed API documentation, see [API README](api/README.md).

### 5. Multiple Instances

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
Full integration with JetEngine for Custom Post Types, Meta Fields, and Relations.

### REST API
Complete REST API for external integrations. See [API Documentation](api/README.md) for details.

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

- **PHP Backend**: Handles shortcode rendering, AJAX endpoints, and data processing
- **JavaScript Frontend**: Manages UI interactions, instance coordination, and caching
- **CSS Styling**: Responsive design with RTL support
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
Override styles by targeting `.ap-widget` classes in your theme:

```css
.ap-widget {
    /* Your custom styles */
}

.ap-widget .ap-cta-button {
    /* Custom booking button styles */
}
```

### RTL Support
The widget automatically detects RTL languages and adjusts the layout accordingly.

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