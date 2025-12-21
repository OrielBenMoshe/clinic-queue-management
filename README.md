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

### 1. Using the Shortcode

Add the shortcode anywhere in your WordPress content:

```
[clinic_queue doctor_id="1" clinic_id="1" cta_label="הזמן תור"]
```

**Shortcode Parameters:**
- `doctor_id` (required): The doctor's ID
- `clinic_id` (optional): Specific clinic ID  
- `cta_label` (optional): Custom booking button text
- `rtl` (optional): Force RTL/LTR direction

### 2. Using the Elementor Widget

1. Edit a page with Elementor
2. Search for "Clinic Queue" in the widget panel
3. Drag the widget to your desired location
4. Configure the widget settings

### 3. Configuring Data

The plugin loads data from a JSON file in the `data/` directory in this format:

```json
{
  "timezone": "Asia/Jerusalem",
  "days": [
    {
      "date": "2025-08-15",
      "slots": [
        { "time": "09:15", "id": "2025-08-15T09:15", "booked": false },
        { "time": "11:20", "id": "2025-08-15T11:20", "booked": false },
        { "time": "14:30", "id": "2025-08-15T14:30", "booked": false }
      ]
    },
    {
      "date": "2025-08-16",
      "slots": [
        { "time": "10:00", "id": "2025-08-16T10:00", "booked": false },
        { "time": "15:30", "id": "2025-08-16T15:30", "booked": false }
      ]
    }
  ]
}
```

### 4. Multiple Instances

You can use multiple widgets on the same page with different configurations:

```
[clinic_queue doctor_id="1" clinic_id="1" cta_label="מרפאה תל אביב"]
[clinic_queue doctor_id="1" clinic_id="2" cta_label="מרפאה ירושלים"]
[clinic_queue doctor_id="2" clinic_id="3" cta_label="רופא עור"]
```

The plugin automatically optimizes performance by:
- Loading CSS/JS assets only once per page
- Sharing data cache between similar instances
- Providing unique event identification for each widget

## Event Handling

The widget dispatches a custom event when a user selects a time slot:

```javascript
window.addEventListener('clinic_queue:selected', function(event) {
    const selection = event.detail;
    console.log('Selected:', {
        widgetId: selection.widgetId,   // "clinic-queue-123" 
        date: selection.date,           // "2025-08-15"
        time: selection.slot.time,      // "09:15"  
        slotId: selection.slot.id,      // "2025-08-15T09:15"
        timezone: selection.tz,         // "Asia/Jerusalem"
        doctor: selection.doctor,       // Doctor info
        clinic: selection.clinic        // Clinic info
    });
    
    // Integrate with your clinic system here
});
```

### Multiple Instance Management

```javascript
// Get specific widget instance
const widget = ClinicQueueManager.utils.getInstance('clinic-queue-123');

// Get all widget instances
const allWidgets = ClinicQueueManager.utils.getAllInstances();

// Clear shared cache
ClinicQueueManager.utils.clearCache();

// Reinitialize all widgets (useful after dynamic content changes)
ClinicQueueManager.utils.reinitialize();
```

## JSON Schema

### Root Object
- `timezone` (string, optional): Timezone identifier (default: "Asia/Jerusalem")
- `days` (array): Array of day objects

### Day Object
- `date` (string, required): Date in YYYY-MM-DD format
- `slots` (array): Array of time slot objects

### Slot Object
- `time` (string, required): Time in HH:MM format (24-hour)
- `id` (string, required): Unique identifier for the slot
- `booked` (boolean, optional): Whether the slot is booked (default: false)

## מסמכים מפורטים

כל המסמכים המפורטים נמצאים בתיקיית **[docs/](docs/)**:

- **[מסמך איפיון מפורט](docs/SPECIFICATION.md)** - תיאור מלא של המערכת, ארכיטקטורה, ותכונות
- **[תרשימי ארכיטקטורה](docs/ARCHITECTURE_DIAGRAM.md)** - תרשימי Mermaid של מבנה המערכת
- **[מפת פרויקט](docs/PROJECT_MAP.md)** - מפה מפורטת של כל הקבצים והפונקציונליות
- **[סיכום קצר](docs/SUMMARY.md)** - סיכום מהיר ויעיל של הפרויקט

📁 **[תיקיית תיעוד מלאה](docs/README.md)** - מדריך לשימוש במסמכים  
📋 **[אינדקס מהיר](docs/INDEX.md)** - מדריך לפי תפקיד

## Development

### מבנה קבצים מפורט
```
clinic-queue-management/
├── clinic-queue-management.php          # נקודת כניסה ראשית
├── README.md                           # תיעוד בסיסי
├── SPECIFICATION.md                    # מסמך איפיון מפורט
├── ARCHITECTURE_DIAGRAM.md             # תרשימי ארכיטקטורה
├── PROJECT_MAP.md                      # מפת פרויקט
│
├── core/                               # ליבת המערכת
│   ├── class-plugin-core.php          # מנהל מרכזי
│   ├── class-helpers.php              # פונקציות עזר
│   └── constants.php                   # קבועים
│
├── api/                                # ממשקי API
│   └── class-api-manager.php          # מנהל API חיצוני
│
├── admin/                              # ממשק ניהול
│   ├── class-dashboard.php            # דשבורד ראשי
│   ├── class-help.php                # עזרה
│   ├── class-settings.php            # הגדרות
│   ├── class-ajax-handlers.php       # מטפלי AJAX
│   ├── class-admin-menu.php          # תפריט ניהול
│   ├── assets/                        # נכסי ממשק ניהול
│   └── views/                         # תבניות HTML
│
├── frontend/                           # ממשק משתמש
│   ├── widgets/                       # ווידג'טים
│   ├── shortcodes/                    # Shortcodes
│   └── assets/                        # נכסי Frontend
│
├── data/                               # נתונים
│   └── mock-data.json                 # נתוני דמו
│
└── includes/                          # קבצים משותפים
```

### Architecture

- **PHP Backend**: Handles shortcode rendering, AJAX endpoints, and data processing
- **JavaScript Frontend**: Manages UI interactions, instance coordination, and caching
- **CSS Styling**: Responsive design with RTL support
- **No Build Process**: Direct file serving, no compilation needed

### Dependencies
- WordPress 5.0+
- Elementor 3.0+ (optional, only needed for widget functionality)
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

### Widget Not Appearing
- Ensure Elementor is installed and activated
- Clear any caching plugins
- Check browser console for JavaScript errors

### JSON Validation Errors
- Validate your JSON using an online JSON validator
- Ensure dates are in YYYY-MM-DD format
- Ensure times are in HH:MM format (24-hour)
- Check that all required fields are present

### Multiple Instances Issues
- Each widget instance maintains independent state
- If issues persist, check for JavaScript errors in browser console

## License

This plugin is released under the GPL v2 license.