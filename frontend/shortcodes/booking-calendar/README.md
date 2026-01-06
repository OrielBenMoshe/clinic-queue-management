# 📅 Booking Calendar Shortcode

שורטקוד עצמאי להצגת יומן קביעת תורים - חלופה לווידג'ט Elementor.

## 🎯 סקירה

השורטקוד `[booking_calendar]` מאפשר להציג יומן תורים בכל מקום בוורדפרס:
- בפוסטים ודפים
- בטפסים
- ב-widgets
- ללא תלות ב-Elementor

## 💡 שימוש בסיסי

### זיהוי אוטומטי (מומלץ)

```php
[booking_calendar]
```

השורטקוד מזהה אוטומטית:
- **בדף רופא** (`post_type: doctors`) → מצב "יומן רופא"
- **בדף מרפאה** (`post_type: clinics`) → מצב "יומן מרפאה"

### דריסה ידנית

```php
// כפיית מצב רופא עם מרפאה ספציפית
[booking_calendar mode="doctor" doctor_id="5" clinic_id="3"]

// כפיית מצב מרפאה
[booking_calendar mode="clinic" clinic_id="3"]

// עם סוג טיפול מוגדר מראש
[booking_calendar treatment_type="קרדיולוגיה"]
```

## ⚙️ פרמטרים

| פרמטר | ערכים אפשריים | ברירת מחדל | תיאור |
|-------|---------------|-------------|--------|
| `mode` | `auto`, `doctor`, `clinic` | `auto` | מצב תצוגה |
| `doctor_id` | מספר (ID) | זיהוי אוטומטי | מזהה רופא |
| `clinic_id` | מספר (ID) | זיהוי אוטומטי | מזהה מרפאה |
| `treatment_type` | טקסט | ריק | סוג טיפול מוגדר מראש |

## 🔄 מצבי תצוגה

### מצב רופא (Doctor Mode)
```
┌─────────────────────────┐
│ יומן - ד"ר יוסי כהן    │  ← קבוע
│                         │
│ סוג טיפול: [▼]         │
│ מרפאה:     [▼]         │  ← נבחר
│                         │
│ [תורים זמינים...]      │
└─────────────────────────┘
```

### מצב מרפאה (Clinic Mode)
```
┌─────────────────────────┐
│ יומן - מרפאה תל אביב    │  ← קבוע
│                         │
│ סוג טיפול: [▼]         │
│ יומן:       [▼]         │  ← נבחר
│                         │
│ [תורים זמינים...]      │
└─────────────────────────┘
```

## 📋 דוגמאות שימוש

### דוגמה 1: דף רופא אישי
```php
<!-- בתבנית של דף רופא יחיד -->
<?php 
// הדף מזהה אוטומטית שזה רופא
echo do_shortcode('[booking_calendar]'); 
?>
```

### דוגמה 2: דף מרפאה
```php
<!-- בתבנית של דף מרפאה -->
<?php 
echo do_shortcode('[booking_calendar]'); 
?>
```

### דוגמה 3: דף סטטי עם רופא מוגדר
```php
[booking_calendar mode="doctor" doctor_id="5"]
```

### דוגמה 4: דף השוואה - מספר רופאים
```html
<div class="doctors-comparison">
  <div class="doctor-calendar">
    <h3>ד"ר יוסי כהן</h3>
    [booking_calendar doctor_id="1"]
  </div>
  
  <div class="doctor-calendar">
    <h3>ד"ר שרה לוי</h3>
    [booking_calendar doctor_id="2"]
  </div>
</div>
```

## 🏗️ מבנה טכני

```
booking-calendar/
├── class-booking-calendar-shortcode.php    # מחלקה ראשית
├── managers/                                # Business Logic
│   ├── class-calendar-data-provider.php    # שליפת נתונים
│   └── class-calendar-filter-engine.php    # פילטור
├── views/                                   # HTML Templates
│   └── booking-calendar-html.php
└── js/                                      # JavaScript
    ├── booking-calendar.js
    └── modules/
        ├── booking-calendar-widget.js
        ├── booking-calendar-data-manager.js
        ├── booking-calendar-ui-manager.js
        ├── booking-calendar-utils.js
        └── booking-calendar-init.js
```

## 🔧 טעינת Assets

השורטקוד טוען אוטומטית:

**CSS:**
- `base.css` - משתני CSS
- `appointments-calendar.css` - סגנונות היומן
- `select.css` - סגנונות Select2

**JavaScript:**
- 5 מודולים: utils, data-manager, ui-manager, widget, init
- `booking-calendar.js` - entry point
- Select2 (אם לא נטען כבר)

## ⚠️ הבדלים מהווידג'ט

| תכונה | Widget | Shortcode |
|-------|--------|-----------|
| תלות ב-Elementor | ✅ כן | ❌ לא |
| זיהוי אוטומטי | ✅ | ✅ |
| דריסה ידנית | ✅ | ✅ |
| קונפליקטים עם JetEngine | ⚠️ לעיתים | ❌ לא |
| מיקום | רק בדפי Elementor | בכל מקום |
| טעינת Assets | אוטומטי | מבוקר |

## 🐛 פתרון בעיות

### השורטקוד לא מוצג
```php
// בדוק ש-SHORTCODE לא מושבת
// ב-debug-config.php:
define('CLINIC_QUEUE_DISABLE_SHORTCODE', false);
```

### עיצוב לא נטען
```php
// בדוק ש-CSS לא מושבת
define('CLINIC_QUEUE_DISABLE_CSS', false);
```

### JavaScript לא עובד
```php
// בדוק ש-JS לא מושבת
define('CLINIC_QUEUE_DISABLE_JS', false);

// בדוק Console errors בדפדפן
```

### קונפליקטים עם JetForms
השורטקוד **לא אמור** ליצור קונפליקטים כי:
- הוא עצמאי לחלוטין
- טוען assets רק כשנדרש
- אין לו התנגשויות עם Elementor

## 📝 הערות פיתוח

### עצמאות מוחלטת
השורטקוד הוא **יחידה נפרדת לגמרי**:
- ✅ PHP managers משוכפלים
- ✅ JavaScript modules משוכפלים
- ✅ אין שיתוף קוד עם הווידג'ט
- ✅ ניתן למחוק את הווידג'ט בלי לשבור

### שינויים עתידיים
אם צריך לשנות משהו:
1. **רק בשורטקוד** → שנה ב-`shortcodes/booking-calendar/`
2. **רק בווידג'ט** → שנה ב-`widgets/clinic-queue/`
3. **בשניהם** → שנה בכל אחד בנפרד

## 🎨 עיצוב מותאם

```css
/* Override styles */
.booking-calendar-shortcode {
    max-width: 600px !important;
    border-color: #your-color !important;
}

/* Specific day styles */
.booking-calendar-shortcode .day-tab.selected {
    background: #your-brand-color !important;
}
```

## 🔌 אינטגרציה עם API חיצוני

### תתי תחומים רפואיים

השורטקוד מושך את רשימת תתי התחומים הרפואיים מ-API חיצוני:

**Endpoint:** `https://doctor-place.com/wp-json/clinics/sub-specialties/`

**תכונות:**
- ✅ משיכה דינמית מה-API
- ✅ Cache לשעה אחת (שיפור ביצועים)
- ✅ Fallback לרשימת ברירת מחדל במקרה תקלה
- ✅ מיון אלפביתי אוטומטי
- ✅ תמיכה ב-60+ תתי תחומים

**ניקוי Cache:**

אם הוספת תת תחום חדש ורוצה לראות אותו מיד:

```php
// בקוד PHP
$filter_engine = new Booking_Calendar_Calendar_Filter_Engine();
$filter_engine->clear_treatment_types_cache();

// או דרך WP-CLI
wp transient delete clinic_queue_treatment_types
```

**מבנה נתונים מה-API:**
```json
[
  {
    "term_id": 314,
    "name": "אודיולוגיה",
    "slug": "...",
    "taxonomy": "specialities",
    "parent": 312
  }
]
```

## 📚 תיעוד נוסף

- [Widget Documentation](../../widgets/clinic-queue/README.md)
- [API Documentation](../../../api/ARCHITECTURE.md)
- [Admin Documentation](../../../admin/REFACTOR_SUMMARY.md)

---

**נוצר:** ינואר 2026  
**גרסה:** 1.0  
**סטטוס:** ✅ פעיל ועצמאי

