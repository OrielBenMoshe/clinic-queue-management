# 🗓️ ווידג'ט יומן התורים - תיעוד מפורט

> תיעוד מקיף לווידג'ט Elementor לקביעת תורים במרפאות
> 
> **עודכן לאחרונה:** ינואר 2026  
> **גרסה:** 1.0  
> **מיקום:** `/frontend/widgets/`

---

## 📑 תוכן עניינים

1. [סקירה כללית](#-סקירה-כללית)
2. [ארכיטקטורה](#️-ארכיטקטורה)
3. [מבנה קבצים](#-מבנה-קבצים)
4. [איך הווידג'ט עובד](#-איך-הווידג'ט-עובד)
5. [מצבי תצוגה](#-מצבי-תצוגה)
6. [רכיבי ממשק](#-רכיבי-ממשק)
7. [Flow נתונים](#-flow-נתונים)
8. [אינטראקציות](#-אינטראקציות)
9. [אינטגרציות](#-אינטגרציות)
10. [טיפול בשגיאות](#️-טיפול-בשגיאות)
11. [עיצוב ו-CSS](#-עיצוב-ו-css)
12. [API Endpoints](#-api-endpoints)
13. [פתרון בעיות נפוצות](#-פתרון-בעיות-נפוצות)

---

## 🎯 סקירה כללית

### מה עושה הווידג'ט?

ווידג'ט **יומן קביעת תורים** הוא רכיב Elementor אינטראקטיבי המאפשר למשתמשים:

- 📅 **לצפות** בתורים פנויים בטווח של 6 ימים קדימה
- 🔍 **לסנן** לפי רופא, מרפאה, וסוג טיפול
- ⏰ **לבחור** תאריך ושעה ספציפיים
- 📝 **להזמין** תור (אינטגרציה עם JetFormBuilder)

### תכונות עיקריות

✅ **2 מצבי תצוגה:** יומן רופא / יומן מרפאה  
✅ **Dynamic Tags:** תמיכה ב-post_id, user_id, ועוד  
✅ **Real-time API:** שליפת נתונים מ-Google Calendar  
✅ **Smart Filtering:** עדכון אוטומטי של שדות תלויים  
✅ **Responsive Design:** תמיכה מלאה במובייל  
✅ **RTL Support:** תמיכה מלאה בעברית  
✅ **Error Handling:** טיפול מקיף בשגיאות  

---

## 🏗️ ארכיטקטורה

### שכבות המערכת

```
┌─────────────────────────────────────────────┐
│           ELEMENTOR WIDGET LAYER            │
│      (class-clinic-queue-widget.php)        │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│         FIELDS MANAGER LAYER                │
│    (class-widget-fields-manager.php)        │
│  • Elementor Controls                       │
│  • Dynamic Tags Processing                  │
│  • Delegation to Managers                   │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│           MANAGERS LAYER                    │
│  ┌──────────────────────────────────────┐  │
│  │ Calendar Data Provider               │  │
│  │ • Raw data retrieval from API        │  │
│  └──────────────────────────────────────┘  │
│  ┌──────────────────────────────────────┐  │
│  │ Calendar Filter Engine               │  │
│  │ • Filtering logic                    │  │
│  │ • Smart field updates                │  │
│  └──────────────────────────────────────┘  │
│  ┌──────────────────────────────────────┐  │
│  │ Widget AJAX Handlers                 │  │
│  │ • AJAX endpoints                     │  │
│  └──────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│         JAVASCRIPT LAYER                    │
│  • clinic-queue-widget.js (Core)           │
│  • clinic-queue-ui-manager.js (UI)         │
│  • clinic-queue-data-manager.js (Data)     │
│  • clinic-queue-utils.js (Utilities)       │
│  • clinic-queue-init.js (Bootstrap)        │
└─────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────┐
│              REST API LAYER                 │
│   /wp-json/clinic-queue/v1/                 │
│   • scheduler/free-time                     │
│   • appointments/book                       │
└─────────────────────────────────────────────┘
```

### עקרונות תכנון

#### 1. **Separation of Concerns**
כל שכבה אחראית על תפקיד ספציפי:
- **Widget:** רישום ב-Elementor ו-rendering HTML בסיסי
- **Fields Manager:** ניהול בקרות Elementor ועיבוד Dynamic Tags
- **Managers:** לוגיקת עסקית מנותקת (Data, Filter, AJAX)
- **JavaScript:** אינטראקציות UI וטעינת נתונים
- **API:** תקשורת עם Google Calendar ומסד נתונים

#### 2. **Single Responsibility**
כל מחלקה עושה דבר אחד:
- `Calendar_Data_Provider` - רק שליפת נתונים גולמיים
- `Calendar_Filter_Engine` - רק פילטור ולוגיקה
- `Widget_Ajax_Handlers` - רק טיפול ב-AJAX

#### 3. **Delegation Pattern**
Fields Manager מתפקד כ-orchestrator:
```php
public function get_doctors_options() {
    // מאציל ל-Data Provider
    return $this->data_provider->get_all_doctors();
}
```

---

## 📁 מבנה קבצים

```
frontend/widgets/
├── class-clinic-queue-widget.php          # Widget ראשי (533 שורות)
├── class-widget-fields-manager.php        # Fields Manager (724 שורות)
├── WIDGET_DOCUMENTATION.md                # התיעוד הזה
└── managers/                              # Managers נפרדים
    ├── class-calendar-data-provider.php   # שליפת נתונים
    ├── class-calendar-filter-engine.php   # פילטור
    ├── class-widget-ajax-handlers.php     # AJAX
    └── index.php                          # הגנת ספרייה

assets/
├── css/
│   └── shared/
│       ├── appointments-calendar.css      # עיצוב היומן (400+ שורות)
│       ├── base.css                       # משתני CSS
│       └── select.css                     # Select2 customization
└── js/
    └── widgets/
        └── clinic-queue/
            ├── clinic-queue.js            # Main entry point
            └── modules/
                ├── clinic-queue-widget.js     # Core class
                ├── clinic-queue-ui-manager.js # UI operations
                ├── clinic-queue-data-manager.js # Data & API
                ├── clinic-queue-utils.js      # Utilities
                └── clinic-queue-init.js       # Bootstrap
```

---

## ⚙️ איך הווידג'ט עובד

### תרחיש שימוש רגיל

```
1. משתמש נכנס לדף
   ↓
2. PHP מרנדר HTML בסיסי (מבנה ריק)
   ↓
3. JavaScript מתחיל להתבצע
   ↓
4. clinic-queue-init.js מאתחל את הווידג'ט
   ↓
5. ClinicQueueWidget נוצר
   ↓
6. initializeSelect2() - אתחול Select2 לשדות
   ↓
7. loadFreeSlots() - קריאה ל-API
   ↓
8. API מחזיר תורים פנויים מ-Google Calendar
   ↓
9. processApiData() - עיבוד לפורמט פנימי
   ↓
10. renderDays() - רינדור 6 ימים קדימה
   ↓
11. היום הפעיל הראשון נבחר אוטומטית
   ↓
12. renderTimeSlots() - הצגת שעות זמינות
   ↓
13. משתמש בוחר שעה
   ↓
14. כפתור "הזמן תור" מתאפשר
   ↓
15. לחיצה על הכפתור → טופס קביעת תור (JetFormBuilder)
```

### Flow Code מפורט

#### שלב א': אתחול (Initialization)

```javascript
// clinic-queue-init.js
jQuery(document).ready(function($) {
    $('.appointments-calendar').each(function() {
        new ClinicQueueWidget(this);
    });
});
```

#### שלב ב': יצירת Instance

```javascript
// clinic-queue-widget.js
class ClinicQueueWidget {
    constructor(element) {
        this.element = $(element);
        this.widgetId = this.element.attr('id');
        
        // Get configuration from data attributes
        this.selectionMode = this.element.data('selection-mode') || 'doctor';
        
        // Initialize state
        this.selectedDate = null;
        this.selectedTime = null;
        
        // Create managers
        this.dataManager = new ClinicQueueDataManager(this);
        this.uiManager = new ClinicQueueUIManager(this);
        
        // Start
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initializeSelect2();
        this.dataManager.loadAllAppointmentData();
    }
}
```

#### שלב ג': טעינת נתונים

```javascript
// clinic-queue-data-manager.js
async loadFreeSlots() {
    const endpoint = `${this.apiBaseUrl}/scheduler/free-time`;
    const params = {
        scheduler_id: this.core.effectiveDoctorId,
        duration: 15,
        from_date_utc: new Date().toISOString(),
        to_date_utc: futureDate.toISOString()
    };
    
    const response = await $.get(endpoint, params);
    this.core.appointmentData = this.processApiData(response.result);
    this.renderData();
}
```

#### שלב ד': רינדור

```javascript
// clinic-queue-ui-manager.js
renderDays() {
    for (let i = 0; i < 6; i++) {
        const currentDay = new Date(today);
        currentDay.setDate(today.getDate() + i);
        
        const dayTab = $('<div>')
            .addClass('day-tab')
            .attr('data-date', dateStr)
            .toggleClass('selected', isSelected)
            .toggleClass('disabled', !hasSlots);
        
        daysContainer.append(dayTab);
    }
    
    this.renderTimeSlots();
}
```

---

## 🎭 מצבי תצוגה

הווידג'ט תומך ב-**2 מצבי תצוגה** שונים:

### מצב א': יומן רופא (Doctor Mode)

```php
'selection_mode' => 'doctor'
```

**מאפיינים:**
- **רופא**: **קבוע** (מוגדר ב-`specific_doctor_id`)
- **מרפאה**: **ניתנת לבחירה** על ידי המשתמש ✅
- **סוג טיפול**: תלוי בהגדרות (`use_specific_treatment`)

**תרחיש שימוש:**  
דף רופא אישי שבו המשתמש בוחר באיזו מרפאה לקבוע תור.

**דוגמה:**
```
┌────────────────────────────────┐
│ יומן - ד"ר יוסי כהן           │  ← קבוע
│                                │
│ סוג טיפול: [רפואה כללית ▼]   │
│ מרפאה:     [תל אביב ▼]        │  ← נבחר
│                                │
│ [תורים זמינים...]             │
└────────────────────────────────┘
```

### מצב ב': יומן מרפאה (Clinic Mode)

```php
'selection_mode' => 'clinic'
```

**מאפיינים:**
- **מרפאה**: **קבועה** (מוגדרת ב-`specific_clinic_id`)
- **רופא**: **ניתן לבחירה** על ידי המשתמש ✅
- **סוג טיפול**: תלוי בהגדרות (`use_specific_treatment`)

**תרחיש שימוש:**  
דף מרפאה ספציפית שבה המשתמש בוחר עם איזה רופא לקבוע תור.

**דוגמה:**
```
┌────────────────────────────────┐
│ יומן - מרפאה תל אביב           │  ← קבוע
│                                │
│ סוג טיפול: [רפואה כללית ▼]   │
│ רופא:       [ד"ר יוסי כהן ▼]  │  ← נבחר
│                                │
│ [תורים זמינים...]             │
└────────────────────────────────┘
```

### השוואת מצבים

| תכונה | Doctor Mode | Clinic Mode |
|-------|-------------|-------------|
| **רופא** | קבוע (hidden) | ניתן לבחירה (select) |
| **מרפאה** | ניתנת לבחירה (select) | קבועה (hidden) |
| **סוג טיפול** | תלוי בהגדרות | תלוי בהגדרות |
| **שדות בטופס** | 1-2 שדות | 1-2 שדות |
| **Dynamic Tags** | `{post_id}` למרפאה | `{post_id}` לרופא |

### הגדרת מצב ב-Elementor

בעורך Elementor:

```
Widget Settings → Content → הגדרות
├── סוג יומן: [יומן רופא / יומן מרפאה]
├── מזהה רופא קבוע: [1] או {post_id}
└── מזהה מרפאה קבועה: [1] או {post_id}
```

---

## 🎨 רכיבי ממשק

### מבנה ויזואלי

```
┌─────────────────────────────────────────┐
│          TOP SECTION                    │
│  (background: light blue)               │
│ ┌─────────────────────────────────────┐ │
│ │  FORM: סוג טיפול + רופא/מרפאה      │ │
│ │  [רפואה כללית ▼] [תל אביב ▼]       │ │
│ └─────────────────────────────────────┘ │
│                                         │
│  דצמבר, 2025  ← כותרת חודש             │
│                                         │
│ ┌─── DAYS CAROUSEL (6 days) ─────────┐ │
│ │  א׳  ב׳  ג׳  ד׳  ה׳  ו׳            │ │
│ │  1   2   3   4   5   6              │ │
│ │ (3) (5) (2) (0) (1) (4) ← slots    │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│        BOTTOM SECTION                   │
│  (background: white)                    │
│                                         │
│ ┌─── TIME SLOTS GRID ────────────────┐ │
│ │ [09:00] [09:15] [09:30] [09:45]   │ │
│ │ [10:00] [10:15] [10:30] [10:45]   │ │
│ │ [11:00] [11:15] [11:30] [11:45]   │ │
│ └───────────────────────────────────┘ │
│                                         │
│ ┌─── ACTION BUTTONS ─────────────────┐ │
│ │ [צפה בכל התורים] [הזמן תור 🔒]   │ │
│ │                    ↑ disabled        │ │
│ └───────────────────────────────────┘ │
└─────────────────────────────────────────┘
```

### 1. טופס בחירה (Selection Form)

**מיקום:** `.widget-selection-form` בתוך `.top-section`

**שדות אפשריים:**
- `treatment_type` - סוג טיפול (תמיד ראשון!)
- `doctor_id` - רופא (תלוי במצב)
- `clinic_id` - מרפאה (תלוי במצב)

**דוגמת HTML:**
```html
<form class="widget-selection-form">
    <input type="hidden" name="selection_mode" value="doctor">
    
    <!-- Treatment Type - תמיד ראשון -->
    <select name="treatment_type" class="form-field-select">
        <option value="רפואה כללית">רפואה כללית</option>
        <option value="קרדיולוגיה">קרדיולוגיה</option>
    </select>
    
    <!-- Clinic - במצב doctor -->
    <select name="clinic_id" class="form-field-select">
        <option value="1">מרפאה תל אביב</option>
        <option value="2">מרפאה חיפה</option>
    </select>
</form>
```

### 2. כותרת חודש (Month Header)

**מיקום:** `.month-and-year` בתוך `.top-section`

**פורמט:** "חודש, שנה" (לדוגמה: "דצמבר, 2025")

**עדכון דינמי:**
```javascript
updateMonthTitle() {
    const monthTitle = this.core.currentMonth.toLocaleDateString('he-IL', { 
        month: 'long', 
        year: 'numeric' 
    });
    this.core.element.find('.month-and-year').text(monthTitle);
}
```

### 3. קרוסלת ימים (Days Carousel)

**מיקום:** `.days-carousel > .days-container`

**כמות ימים:** 6 ימים קדימה מהיום

**מבנה יום בודד:**
```html
<div class="day-tab" data-date="2025-01-02">
    <div class="day-abbrev">ב׳</div>
    <div class="day-content">
        <div class="day-number">2</div>
        <div class="day-slots-count">5</div>
    </div>
</div>
```

**מצבים:**
- `.selected` - נבחר (רקע כחול)
- `.disabled` - אין תורים (אפור, לא ניתן ללחיצה)
- רגיל - יש תורים (לבן, ניתן ללחיצה)

### 4. רשת זמנים (Time Slots Grid)

**מיקום:** `.time-slots-container > .time-slots-grid`

**מבנה slot בודד:**
```html
<div class="time-slot-badge free" data-time="09:00">
    09:00
</div>
```

**מצבים:**
- `.free` - פנוי (רקע לבן, גבול כחול)
- `.selected` - נבחר (רקע כחול, טקסט לבן)

**Layout:** Grid עם 4 עמודות, רווחים של 8px

### 5. כפתורי פעולה (Action Buttons)

**מיקום:** `.action-buttons-container` בתוך `.bottom-section`

**2 כפתורים:**

1. **צפה בכל התורים** (`.ap-view-all-btn`)
   - מצב: תמיד פעיל
   - עיצוב: secondary (אפור)
   - פעולה: כרגע מושבת

2. **הזמן תור** (`.ap-book-btn`)
   - מצב: מושבת עד בחירת תאריך + שעה
   - עיצוב: primary (ורוד)
   - פעולה: פתיחת טופס JetFormBuilder

```html
<div class="action-buttons-container">
    <button class="btn btn-secondary ap-view-all-btn">
        צפייה בכל התורים
    </button>
    <button class="btn btn-primary ap-book-btn disabled" disabled>
        הזמן תור
    </button>
</div>
```

---

## 🔄 Flow נתונים

### 1. שליפה מ-API

```javascript
// Endpoint
GET /wp-json/clinic-queue/v1/scheduler/free-time

// Parameters
{
    scheduler_id: "1",           // ID של רופא/מרפאה
    duration: 15,                // משך תור בדקות
    from_date_utc: "2025-01-02T00:00:00Z",
    to_date_utc: "2025-02-01T00:00:00Z"
}

// Response
{
    "result": [
        {
            "from": "2025-01-02T09:00:00Z",
            "duration": 15
        },
        {
            "from": "2025-01-02T09:15:00Z",
            "duration": 15
        }
        // ... more slots
    ]
}
```

### 2. עיבוד נתונים (Processing)

```javascript
processApiData(slots) {
    // קבץ לפי תאריך
    const slotsByDate = {};
    
    slots.forEach(slot => {
        const date = formatDate(slot.from);  // "2025-01-02"
        
        if (!slotsByDate[date]) {
            slotsByDate[date] = [];
        }
        
        slotsByDate[date].push({
            time_slot: "09:00",
            is_booked: 0,
            from: slot.from,
            to: calculateTo(slot.from, slot.duration)
        });
    });
    
    // המר למבנה פנימי
    return Object.keys(slotsByDate).map(date => ({
        date: { appointment_date: date },
        time_slots: slotsByDate[date]
    }));
}
```

**פורמט פנימי (Internal Format):**
```javascript
[
    {
        date: {
            appointment_date: "2025-01-02"
        },
        time_slots: [
            { time_slot: "09:00", is_booked: 0, from: "...", to: "..." },
            { time_slot: "09:15", is_booked: 0, from: "...", to: "..." },
            { time_slot: "09:30", is_booked: 0, from: "...", to: "..." }
        ]
    },
    {
        date: {
            appointment_date: "2025-01-03"
        },
        time_slots: [...]
    }
]
```

### 3. רינדור (Rendering)

```javascript
// Step 1: Render Days
renderDays() {
    // יצירת 6 day-tabs
    for (let i = 0; i < 6; i++) {
        const date = getDate(i);
        const appointment = appointmentsMap.get(date);
        const hasSlots = appointment?.time_slots?.length > 0;
        
        createDayTab(date, hasSlots, appointment.time_slots.length);
    }
    
    // בחירה אוטומטית של היום הפעיל הראשון
    this.core.selectedDate = firstActiveDate;
    this.renderTimeSlots();
}

// Step 2: Render Time Slots
renderTimeSlots() {
    const dayData = findDayData(this.core.selectedDate);
    
    dayData.time_slots.forEach(slot => {
        createTimeSlotBadge(slot.time_slot);
    });
    
    this.ensureActionButtons();
    this.updateBookButtonState();
}
```

### 4. זרימת State

```javascript
// Initial State
{
    selectedDate: null,
    selectedTime: null,
    appointmentData: null,
    isLoading: false
}

// After Loading Data
{
    selectedDate: "2025-01-02",  // Auto-selected
    selectedTime: null,
    appointmentData: [...],      // Processed data
    isLoading: false
}

// After User Selects Time
{
    selectedDate: "2025-01-02",
    selectedTime: "09:00",       // User selected
    appointmentData: [...],
    isLoading: false
}
```

---

## 🖱️ אינטראקציות

### 1. שינוי שדה בטופס

**טריגר:** `change` event על `.form-field-select`

**Flow:**
```
User changes select
    ↓
handleFormFieldChange(field, value)
    ↓
updateFieldsWithSmartFiltering()  // עדכון שדות תלויים
    ↓
updateEffectiveValues()           // חישוב ערכים אפקטיביים
    ↓
showLoadingState()                // הצגת טעינה
    ↓
resetSelections()                 // איפוס בחירות
    ↓
loadFreeSlots()                   // טעינה מחדש מ-API
    ↓
renderData()                      // רינדור מחדש
```

**קוד:**
```javascript
handleFormFieldChange(field, value) {
    console.log(`Field changed: ${field} = ${value}`);
    
    const formData = this.getFormData();
    const widgetSettings = this.getWidgetSettings();
    
    // עדכון שדות תלויים
    this.updateFieldsWithSmartFiltering(field, value, formData, widgetSettings);
    
    // עדכון ערכים
    this.updateEffectiveValues(formData);
    
    // Reinitialize Select2
    this.reinitializeSelect2();
    
    // טעינה מחדש
    this.showLoadingState();
    this.resetSelections();
    this.dataManager.filterAndRenderData();
}
```

### 2. בחירת יום

**טריגר:** `click` event על `.day-tab:not(.disabled)`

**Flow:**
```
User clicks day tab
    ↓
selectDate(date)
    ↓
Check if same date
├── Yes → Deselect (selectedDate = null)
└── No  → Select (selectedDate = date)
    ↓
Update UI classes (.selected)
    ↓
renderTimeSlots()
    ↓
updateBookButtonState()
```

**קוד:**
```javascript
selectDate(date) {
    if (this.core.selectedDate === date) {
        // Deselect
        this.core.selectedDate = null;
        this.core.selectedTime = null;
        $('.day-tab').removeClass('selected');
        $('.time-slots-container').empty();
    } else {
        // Select
        this.core.selectedDate = date;
        this.core.selectedTime = null;
        
        $('.day-tab').removeClass('selected');
        $(`.day-tab[data-date="${date}"]`).addClass('selected');
        
        this.renderTimeSlots();
    }
    
    this.updateBookButtonState();
}
```

### 3. בחירת שעה

**טריגר:** `click` event על `.time-slot-badge`

**Flow:**
```
User clicks time slot
    ↓
selectTimeSlot(time)
    ↓
Check if same time
├── Yes → Deselect (selectedTime = null)
└── No  → Select (selectedTime = time)
    ↓
Update UI classes (.selected)
    ↓
updateBookButtonState()
    ↓
Focus on book button (if enabled)
```

**קוד:**
```javascript
selectTimeSlot(time) {
    if (this.core.selectedTime === time) {
        // Deselect
        this.core.selectedTime = null;
        $('.time-slot-badge').removeClass('selected');
    } else {
        // Select
        this.core.selectedTime = time;
        
        $('.time-slot-badge').removeClass('selected');
        $(`.time-slot-badge[data-time="${time}"]`).addClass('selected');
    }
    
    this.updateBookButtonState();
    
    // Focus on book button
    if (this.core.selectedTime) {
        setTimeout(() => {
            $('.ap-book-btn').focus();
        }, 100);
    }
}
```

### 4. לחיצה על "הזמן תור"

**תנאי:** כפתור מאופשר רק אם `selectedDate && selectedTime`

**Flow:**
```
User clicks book button
    ↓
Prepare appointment data
    ↓
Open JetFormBuilder form (or redirect)
    ↓
Pre-fill form fields with:
    - Date: selectedDate
    - Time: selectedTime
    - Doctor: effectiveDoctorId
    - Clinic: effectiveClinicId
    - Treatment: effectiveTreatmentType
```

---

## 🔗 אינטגרציות

### 1. עם Elementor

**רישום הווידג'ט:**
```php
// In plugin core
add_action('elementor/widgets/register', function($widgets_manager) {
    require_once 'frontend/widgets/class-clinic-queue-widget.php';
    $widgets_manager->register(new Clinic_Queue_Widget());
});
```

**בקרות Elementor (Controls):**
```php
protected function register_controls() {
    $this->start_controls_section('content_section', [
        'label' => 'הגדרות',
        'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
    ]);
    
    $this->add_control('selection_mode', [
        'label' => 'סוג יומן',
        'type' => \Elementor\Controls_Manager::SELECT,
        'options' => [
            'doctor' => 'יומן רופא',
            'clinic' => 'יומן מרפאה',
        ],
        'default' => 'doctor',
    ]);
    
    // ... more controls
    
    $this->end_controls_section();
}
```

**Preview vs Frontend:**
- **Editor:** `content_template()` - HTML סטטי, בלי JavaScript
- **Frontend:** `render()` - HTML מלא + JavaScript פעיל

### 2. עם JetEngine

**שליפת נתונים:**
```php
// Calendar Data Provider
public function get_all_doctors() {
    if (!$this->api_manager) {
        return array();
    }
    return $this->api_manager->get_all_doctors();
}

// API Manager
public function get_all_doctors() {
    // JetEngine REST API call או WP_Query
    $args = array(
        'post_type' => 'doctor',
        'posts_per_page' => -1,
    );
    $doctors = get_posts($args);
    
    return $this->format_doctors($doctors);
}
```

**Jet Relations:**
```php
// Get clinics for doctor
$relations = jet_engine()->relations->get_related_posts([
    'relation_id' => 'doctor_to_clinic',
    'parent_id' => $doctor_id,
    'context' => 'parent_to_child'
]);
```

### 3. עם Google Calendar API

**שליפת תורים פנויים:**
```php
// Google Calendar Service
public function get_free_slots($calendar_id, $from_date, $to_date, $duration) {
    $free_busy = $this->service->freebusy->query([
        'timeMin' => $from_date,
        'timeMax' => $to_date,
        'items' => [['id' => $calendar_id]]
    ]);
    
    $busy_times = $free_busy->getCalendars()[$calendar_id]->getBusy();
    
    return $this->calculate_free_slots($busy_times, $duration);
}
```

**זרימת נתונים:**
```
Widget Request
    ↓
REST Handler: /scheduler/free-time
    ↓
Scheduler Service
    ↓
Google Calendar Service
    ↓
Google Calendar API
    ↓
Response: Busy times
    ↓
Calculate free slots
    ↓
Return to widget
```

### 4. עם Select2

**אתחול:**
```javascript
initializeSelect2() {
    this.element.find('.form-field-select').select2({
        theme: 'clinic-queue',
        dir: 'rtl',
        language: 'he',
        width: '100%',
        minimumResultsForSearch: -1,  // Disable search
        dropdownParent: this.element
    });
}
```

**עיצוב מותאם:**
```css
/* select.css */
.select2-container--clinic-queue {
    .select2-selection {
        border: 1px solid #d0d7e8;
        border-radius: 8px;
        padding: 8px 12px;
    }
    
    .select2-selection__arrow {
        background: url('../images/icons/select-arrow-down.svg');
    }
}
```

### 5. עם JetFormBuilder

**אינטגרציה עתידית:**
```javascript
// כשמשתמש לוחץ "הזמן תור"
handleBookAppointment() {
    const appointmentData = {
        date: this.selectedDate,
        time: this.selectedTime,
        doctor_id: this.effectiveDoctorId,
        clinic_id: this.effectiveClinicId,
        treatment_type: this.effectiveTreatmentType
    };
    
    // פתיחת popup עם JetFormBuilder
    // או העברה לדף קביעת תור עם פרמטרים
    window.location.href = `/book-appointment/?` + 
        new URLSearchParams(appointmentData).toString();
}
```

---

## 🛡️ טיפול בשגיאות

### שכבות הגנה

#### 1. PHP - Widget Render

```php
protected function render() {
    try {
        $this->enqueue_widget_assets();
        $settings = $this->get_settings_for_display();
        
        if (!is_array($settings)) {
            $settings = array();
        }
        
        $fields_manager = Clinic_Queue_Widget_Fields_Manager::get_instance();
        $widget_settings = $fields_manager->get_widget_data($settings);
        
        if ($widget_settings['error']) {
            echo '<div class="clinic-queue-error">';
            echo '<h3>שגיאה בטעינת נתונים</h3>';
            echo '<p>' . esc_html($widget_settings['message']) . '</p>';
            echo '</div>';
            return;
        }
        
        $this->render_widget_html($settings, null, $widget_settings['settings']);
        
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Clinic Queue Widget: Render error - ' . $e->getMessage());
        }
        echo '<div class="clinic-queue-error">';
        echo '<h3>שגיאה זמנית</h3>';
        echo '<p>אנחנו עובדים על תיקון הבעיה. אנא נסה שוב מאוחר יותר.</p>';
        echo '</div>';
    } catch (Error $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Clinic Queue Widget: Fatal render error - ' . $e->getMessage());
        }
        echo '<div class="clinic-queue-error">';
        echo '<h3>שגיאה זמנית</h3>';
        echo '<p>אנחנו עובדים על תיקון הבעיה. אנא נסה שוב מאוחר יותר.</p>';
        echo '</div>';
    }
}
```

#### 2. JavaScript - API Calls

```javascript
async loadFreeSlots() {
    try {
        this.core.isLoading = true;
        this.showLoading();
        
        const response = await $.get(endpoint, params);
        
        if (!response || !response.result) {
            console.warn('No data in API response');
            this.core.appointmentData = [];
            this.showNoAppointmentsMessage();
            return;
        }
        
        const processedData = this.processApiData(response.result);
        this.core.appointmentData = processedData;
        
        if (processedData.length === 0) {
            this.showNoAppointmentsMessage();
            return;
        }
        
        this.renderData();
        
    } catch (error) {
        console.error('Failed to load appointment data:', error);
        
        // Graceful degradation - show empty calendar
        this.core.appointmentData = [];
        this.showNoAppointmentsMessage();
        
    } finally {
        this.core.isLoading = false;
    }
}
```

### Graceful Degradation

**אם אין תורים זמינים:**
```javascript
showNoAppointmentsMessage() {
    // הצג מבנה יומן ריק
    this.core.element.find('.month-and-year').text('אין תורים זמינים');
    
    // הצג ימים disabled
    this.renderEmptyDays();
    
    // הצג הודעה ידידותית
    this.core.element.find('.time-slots-container').html(`
        <div style="text-align: center; padding: 40px;">
            <div style="font-size: 32px;">📅</div>
            <p><strong>אין תורים זמינים</strong></p>
            <p>לא נמצאו תורים פנויים כרגע. נסה שוב מאוחר יותר.</p>
        </div>
    `);
}
```

**אם API נכשל:**
```javascript
catch (error) {
    // לא להציג שגיאה מפחידה למשתמש
    // במקום זה, להציג יומן ריק עם הודעה נחמדה
    this.showNoAppointmentsMessage();
}
```

### Logging

**PHP:**
```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[ClinicQueue] Widget data - Raw settings: ' . print_r($settings, true));
    error_log('[ClinicQueue] Effective values: doctor=' . $doctor_id);
}
```

**JavaScript:**
```javascript
console.log('[ClinicQueue] Widget initialized with ID:', this.widgetId);
console.log('[ClinicQueue] Configuration:', {
    selectionMode: this.selectionMode,
    effectiveDoctorId: this.effectiveDoctorId,
    effectiveClinicId: this.effectiveClinicId
});
```

---

## 🎨 עיצוב ו-CSS

### ארכיטקטורת CSS

**3 קבצים עיקריים:**

1. **`base.css`** - משתני CSS גלובליים
2. **`appointments-calendar.css`** - עיצוב היומן
3. **`select.css`** - עיצוב Select2

### משתני CSS (CSS Variables)

```css
/* base.css */
:root {
    /* Colors */
    --color-primary: #e91e63;
    --color-secondary: #2196f3;
    --color-bg-light-blue: #e3f2fd;
    --color-border-light-blue: #90caf9;
    --color-white: #ffffff;
    --color-gray-900: #212529;
    
    /* Spacing */
    --space-1: 4px;
    --space-2: 8px;
    --space-3: 12px;
    --space-4: 16px;
    --space-5: 20px;
    
    /* Border Radius */
    --radius-base: 4px;
    --radius-lg: 8px;
    --radius-xl: 12px;
    
    /* Transitions */
    --transition-base: all 0.2s ease;
    --transition-slow: all 0.3s ease;
    
    /* Typography */
    --font-primary: 'Heebo', -apple-system, sans-serif;
}
```

### מבנה Nesting

```css
/* appointments-calendar.css */
.appointments-calendar {
    display: flex;
    flex-direction: column;
    border: 3px solid var(--color-border-light-blue);
    border-radius: var(--radius-xl);
    
    /* TOP SECTION */
    .top-section {
        background-color: var(--color-bg-light-blue);
        padding: 16px 12px;
        
        .widget-selection-form {
            display: flex;
            gap: 8px;
            
            .form-field-select {
                flex-grow: 1;
                
                &:focus {
                    border-color: #80bdff;
                }
            }
        }
        
        .month-and-year {
            font-size: 20px;
            font-weight: 700;
            color: var(--color-secondary);
        }
        
        .days-carousel {
            .days-container {
                display: flex;
                gap: 10px;
                
                .day-tab {
                    border-radius: var(--radius-lg);
                    cursor: pointer;
                    
                    &.selected {
                        background: var(--color-secondary);
                        color: white;
                    }
                    
                    &.disabled {
                        opacity: 0.5;
                        cursor: not-allowed;
                    }
                }
            }
        }
    }
    
    /* BOTTOM SECTION */
    .bottom-section {
        background: white;
        padding: 16px;
        
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            
            .time-slot-badge {
                padding: 8px;
                border: 1px solid var(--color-secondary);
                border-radius: var(--radius-base);
                text-align: center;
                cursor: pointer;
                
                &.selected {
                    background: var(--color-secondary);
                    color: white;
                }
            }
        }
        
        .action-buttons-container {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            
            .ap-book-btn {
                background: var(--color-primary);
                color: white;
                
                &.disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }
            }
        }
    }
}
```

### Responsive Design

```css
/* appointments-calendar.css */
.appointments-calendar {
    /* Desktop: 478px */
    width: 478px;
    height: 459px;
    
    /* Tablet */
    @media (max-width: 768px) {
        width: 100%;
        max-width: 478px;
        
        .days-container {
            overflow-x: auto;
            scrollbar-width: thin;
        }
        
        .time-slots-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    /* Mobile */
    @media (max-width: 480px) {
        .widget-selection-form {
            flex-direction: column;
        }
        
        .time-slots-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .action-buttons-container {
            flex-direction: column;
            gap: 8px;
        }
    }
}
```

### מצבי אינטראקציה

```css
/* Hover States */
.day-tab:hover:not(.disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.time-slot-badge:hover:not(.selected) {
    border-color: var(--color-primary);
    background: rgba(233, 30, 99, 0.1);
}

/* Focus States */
.form-field-select:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Active States */
.day-tab.selected {
    background: var(--color-secondary);
    
    .day-abbrev,
    .day-number {
        color: white;
    }
}

/* Disabled States */
.day-tab.disabled {
    opacity: 0.5;
    pointer-events: none;
    
    .day-slots-count {
        background: #999;
    }
}
```

### RTL Support

```css
/* appointments-calendar.css */
.appointments-calendar {
    direction: rtl;
    text-align: right;
    
    .widget-selection-form {
        .form-field-select {
            &:first-of-type {
                margin-left: 0;
                margin-right: auto;
            }
        }
    }
}

/* Select2 RTL */
.select2-container--clinic-queue {
    .select2-selection__arrow {
        left: 12px;
        right: auto;
    }
}
```

---

## 🔌 API Endpoints

### 1. שליפת תורים פנויים

**Endpoint:**
```
GET /wp-json/clinic-queue/v1/scheduler/free-time
```

**Parameters:**
```javascript
{
    scheduler_id: string,      // ID של רופא/מרפאה (required)
    duration: number,          // משך תור בדקות (default: 15)
    from_date_utc: string,     // תאריך התחלה (ISO 8601) (required)
    to_date_utc: string        // תאריך סיום (ISO 8601) (required)
}
```

**Response Success (200):**
```json
{
    "result": [
        {
            "from": "2025-01-02T09:00:00Z",
            "duration": 15
        },
        {
            "from": "2025-01-02T09:15:00Z",
            "duration": 15
        }
    ]
}
```

**Response Error (400/500):**
```json
{
    "code": "invalid_request",
    "message": "Missing required parameter: scheduler_id",
    "data": {
        "status": 400
    }
}
```

**דוגמת שימוש:**
```javascript
const response = await $.get(
    '/wp-json/clinic-queue/v1/scheduler/free-time',
    {
        scheduler_id: '1',
        duration: 15,
        from_date_utc: '2025-01-02T00:00:00Z',
        to_date_utc: '2025-02-01T23:59:59Z'
    }
);
```

### 2. קביעת תור (עתידי)

**Endpoint:**
```
POST /wp-json/clinic-queue/v1/appointments/book
```

**Request Body:**
```json
{
    "date": "2025-01-02",
    "time": "09:00",
    "doctor_id": "1",
    "clinic_id": "1",
    "treatment_type": "רפואה כללית",
    "patient_name": "ישראל ישראלי",
    "patient_phone": "050-1234567",
    "patient_email": "israel@example.com",
    "notes": ""
}
```

**Response Success (201):**
```json
{
    "success": true,
    "appointment_id": "123",
    "confirmation_code": "ABC123",
    "message": "התור נקבע בהצלחה"
}
```

### 3. רשימת רופאים

**Endpoint:**
```
GET /wp-json/clinic-queue/v1/doctors
```

**Response:**
```json
{
    "doctors": [
        {
            "id": "1",
            "name": "ד\"ר יוסי כהן",
            "specialty": "רפואה כללית",
            "clinics": ["1", "2"]
        }
    ]
}
```

### 4. רשימת מרפאות

**Endpoint:**
```
GET /wp-json/clinic-queue/v1/clinics
```

**Response:**
```json
{
    "clinics": [
        {
            "id": "1",
            "name": "מרפאה תל אביב",
            "address": "רחוב הרצל 1, תל אביב",
            "doctors": ["1", "2", "3"]
        }
    ]
}
```

---

## 🔧 פתרון בעיות נפוצות

### בעיה 1: הווידג'ט לא נטען

**תסמינים:**
- הווידג'ט לא מופיע ב-Elementor
- שגיאת PHP בלוג

**פתרון:**
```bash
# בדוק שכל הקבצים קיימים
ls -la frontend/widgets/
ls -la frontend/widgets/managers/

# בדוק שהווידג'ט רשום
grep "register.*Clinic_Queue_Widget" clinic-queue-management.php

# בדוק שגיאות PHP
tail -f wp-content/debug.log
```

### בעיה 2: נתונים לא נטענים

**תסמינים:**
- היומן מציג "טוען..." לנצח
- Console errors ב-JavaScript

**פתרון:**
```javascript
// בדוק Console ב-DevTools
// חפש שגיאות:
// - CORS errors
// - 404 errors (API endpoint)
// - JSON parsing errors

// בדוק שה-API עובד:
fetch('/wp-json/clinic-queue/v1/scheduler/free-time?scheduler_id=1&duration=15&from_date_utc=2025-01-01T00:00:00Z&to_date_utc=2025-02-01T00:00:00Z')
    .then(r => r.json())
    .then(d => console.log(d));
```

### בעיה 3: Select2 לא עובד

**תסמינים:**
- שדות בחירה נראים רגילים (לא מעוצבים)
- Console error: "select2 is not a function"

**פתרון:**
```javascript
// בדוק ש-Select2 נטען
console.log(typeof $.fn.select2);  // should be "function"

// אם לא:
// 1. בדוק שה-script נטען:
<script src=".../assets/js/vendor/select2/select2.min.js"></script>

// 2. בדוק dependencies:
wp_enqueue_script('clinic-queue-select2-js', ..., ['jquery'], ...);

// 3. בדוק timing:
jQuery(document).ready(function($) {
    $('.form-field-select').select2({...});
});
```

### בעיה 4: תורים לא מופיעים

**תסמינים:**
- API מחזיר נתונים
- היומן מציג "אין תורים זמינים"

**Debug:**
```javascript
// בדוק את הנתונים המעובדים
const widget = window.ClinicQueueManager.instances.values().next().value;
console.log('Raw API data:', widget.allAppointmentData);
console.log('Processed data:', widget.appointmentData);
console.log('Selected date:', widget.selectedDate);

// בדוק את הפורמט של התאריכים
widget.appointmentData.forEach(day => {
    console.log('Date:', day.date.appointment_date);
    console.log('Slots:', day.time_slots.length);
});
```

### בעיה 5: כפתור "הזמן תור" תמיד מושבת

**תסמינים:**
- בחרתי תאריך ושעה
- הכפתור עדיין disabled

**Debug:**
```javascript
const widget = window.ClinicQueueManager.instances.values().next().value;
console.log('Selected date:', widget.selectedDate);
console.log('Selected time:', widget.selectedTime);
console.log('Has both:', !!(widget.selectedDate && widget.selectedTime));

// בדוק את הכפתור
const btn = $('.ap-book-btn');
console.log('Button disabled:', btn.prop('disabled'));
console.log('Button classes:', btn.attr('class'));
```

**פתרון:**
```javascript
// עדכון ידני של מצב הכפתור
widget.uiManager.updateBookButtonState();
```

### בעיה 6: קונפליקט עם JetFormBuilder

**תסמינים:**
- טופס JetFormBuilder נשבר
- Select2 מתנהג מוזר

**פתרון:**
```php
// בדוק ש-assets לא נטענים ב-Editor
public function get_script_depends() {
    return [];  // חשוב! לא להחזיר handles
}

// טעינה רק ב-render():
private function enqueue_widget_assets() {
    if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
        // DON'T load JavaScript in editor
        return;
    }
    
    // Load only in frontend
    wp_enqueue_script(...);
}
```

### בעיה 7: Dynamic Tags לא עובדים

**תסמינים:**
- `{post_id}` מופיע כטקסט
- ווידג'ט לא מקבל את ה-ID הנכון

**Debug:**
```php
// בדוק עיבוד Dynamic Tags
private function process_dynamic_tag($value) {
    error_log('[ClinicQueue] Input: ' . $value);
    
    // Process {post_id}
    if (strpos($value, '{post_id}') !== false) {
        global $post;
        $post_id = $post ? $post->ID : get_the_ID();
        $value = str_replace('{post_id}', $post_id, $value);
    }
    
    error_log('[ClinicQueue] Output: ' . $value);
    return $value;
}
```

### בעיה 8: עיצוב נשבר במובייל

**תסמינים:**
- היומן חורג מהמסך
- כפתורים לא ממוקמים נכון

**פתרון:**
```css
/* הוסף media queries */
@media (max-width: 480px) {
    .appointments-calendar {
        width: 100% !important;
        max-width: 100vw;
        
        .time-slots-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
}
```

---

## 📝 Checklist לפיתוח

### לפני העלאה לפרודקשן

- [ ] כל ה-console.log מוסרו או מוסתרים מאחורי WP_DEBUG
- [ ] Error handling מקיף בכל השכבות
- [ ] Graceful degradation במקרה של כשל API
- [ ] CSS לא משפיע על אלמנטים אחרים
- [ ] JavaScript לא נטען ב-Elementor Editor
- [ ] Select2 לא יוצר קונפליקטים עם JetForms
- [ ] Dynamic Tags עובדים עם {post_id}
- [ ] RTL עובד נכון
- [ ] Responsive למובייל
- [ ] נבדק ב-Chrome, Firefox, Safari
- [ ] Nonces נוספו לכל AJAX requests
- [ ] Sanitization לכל קלט משתמש
- [ ] Escaping לכל פלט ל-HTML

### לבדיקת באגים

- [ ] בדוק Console errors
- [ ] בדוק Network tab - API calls מצליחים?
- [ ] בדוק debug.log של WordPress
- [ ] בדוק ש-Select2 נטען
- [ ] בדוק שנתונים מתקבלים מ-API
- [ ] בדוק שנתונים מעובדים נכון
- [ ] בדוק שימים מוצגים נכון
- [ ] בדוק ש-time slots מוצגים
- [ ] בדוק שבחירה עובדת
- [ ] בדוק שכפתור מתאפשר

---

## 🎓 למידה נוספת

### קבצים חשובים לקריאה

1. **`class-clinic-queue-widget.php`** - הווידג'ט הראשי
2. **`class-widget-fields-manager.php`** - ניהול שדות
3. **`managers/class-calendar-data-provider.php`** - שליפת נתונים
4. **`modules/clinic-queue-widget.js`** - JavaScript core
5. **`modules/clinic-queue-ui-manager.js`** - ניהול UI
6. **`appointments-calendar.css`** - עיצוב

### מסמכים נוספים

- **`/docs/`** - תיעוד כללי של התוסף
- **`/api/ARCHITECTURE.md`** - ארכיטקטורת ה-API
- **`/admin/REFACTOR_SUMMARY.md`** - Refactoring של Admin

### משאבים חיצוניים

- [Elementor Developer Docs](https://developers.elementor.com/)
- [JetEngine Documentation](https://crocoblock.com/knowledge-base/jetengine/)
- [Select2 Documentation](https://select2.org/)
- [WordPress REST API](https://developer.wordpress.org/rest-api/)

---

## 📞 תמיכה

נתקלת בבעיה? יש שאלה?

1. **בדוק את ה-Console** - רוב הבעיות מופיעות שם
2. **בדוק את debug.log** - שגיאות PHP נרשמות שם
3. **קרא את הקוד** - הקוד מתועד היטב
4. **חפש ב-GitHub Issues** - אולי מישהו כבר פתר את זה

---

**זכור:** הקוד הזה נכתב עם הרבה ❤️ ו-☕

**Last Updated:** ינואר 2026  
**Author:** Clinic Queue Management Team  
**Version:** 1.0

