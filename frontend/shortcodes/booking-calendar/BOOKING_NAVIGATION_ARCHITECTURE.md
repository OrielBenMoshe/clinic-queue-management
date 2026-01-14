# ארכיטקטורת מעבר להזמנת תור

## סקירה כללית

כאשר משתמש בוחר סלוט זמן בלוח התורים ולוחץ על הכפתור "הזמן תור", המערכת מעבירה אותו לעמוד 4366 (טופס הזמנת תור) עם כל הפרמטרים הדרושים להצגה ולמילוי הטופס.

---

## זרימת הנתונים

```
[Booking Calendar] 
    ↓ (בחירת סלוט)
[כפתור "הזמן תור" פעיל]
    ↓ (לחיצה)
[איסוף פרמטרים]
    ↓
[בניית URL עם query parameters]
    ↓
[מעבר לעמוד 4366]
    ↓
[טופס הזמנת תור קורא פרמטרים]
    ↓
[מילוי שדות הטופס]
    ↓
[הצגת פרטי התור]
```

---

## פרמטרים שמועברים

### פרמטרים חובה

| פרמטר | תיאור | דוגמה | שימוש |
|--------|-------|-------|------|
| `scheduler_id` | מזהה היומן ב-WordPress (post ID) | `123` | זיהוי היומן, שליחה ל-API |
| `proxy_schedule_id` | מזהה היומן בפרוקסי | `789` | שליחה ל-API של הפרוקסי |
| `treatment_type` | סוג הטיפול | `"בדיקה כללית"` | הצגה בטופס, שליחה ל-API |
| `date` | תאריך התור (YYYY-MM-DD) | `"2025-12-28"` | הצגה בטופס, בניית datetime |
| `time` | שעת התור (HH:MM) | `"16:00"` | הצגה בטופס, בניית datetime |
| `duration` | משך הטיפול בדקות | `30` | הצגה בטופס, שליחה ל-API |
| `from` | תאריך ושעה התחלה (ISO 8601 UTC) | `"2025-12-28T16:00:00Z"` | שליחה ל-API |
| `to` | תאריך ושעה סיום (ISO 8601 UTC) | `"2025-12-28T16:30:00Z"` | שליחה ל-API |

### פרמטרים אופציונליים

| פרמטר | תיאור | דוגמה | שימוש |
|--------|-------|-------|------|
| `clinic_id` | מזהה המרפאה | `1` | הצגה בטופס (אם רלוונטי) |
| `doctor_id` | מזהה הרופא | `5` | הצגה בטופס (אם רלוונטי) |
| `doctor_id_full` | מזהה הרופא המלא (מ-scheduler) | `5` | זיהוי הרופא |
| `clinic_name` | שם המרפאה | `"מרפאת דוגמה"` | הצגה בטופס |
| `doctor_name` | שם הרופא | `"ד\"ר ישראל ישראלי"` | הצגה בטופס |
| `doctor_specialty` | התמחות הרופא | `"מומחית לרפואת ילדים והתפתחות הילד"` | הצגה בטופס |
| `doctor_thumbnail` | תמונת הרופא (URL) | `"https://example.com/image.jpg"` | הצגה בטופס |
| `clinic_address` | כתובת המרפאה | `"אבן גבירול 15, תל אביב"` | הצגה בטופס |

---

## מימוש

### 1. Event Handler (booking-calendar-core.js)

```javascript
// ב-bindEvents():
this.element.on(`click${eventNamespace}`, '.ap-book-btn:not(.disabled)', (e) => {
    e.preventDefault();
    this.handleBookButtonClick();
});
```

### 2. Data Collection (booking-calendar-core.js)

```javascript
/**
 * אוסף את כל הפרמטרים הדרושים להזמנת תור
 * @returns {Object|null} אובייקט עם כל הפרמטרים, או null אם חסרים פרמטרים חובה
 */
collectBookingParameters() {
    // בדיקת פרמטרים חובה
    if (!this.selectedDate || !this.selectedTime) {
        window.BookingCalendarUtils.error('נא לבחור תאריך ושעה');
        return null;
    }
    
    // מציאת היומן הנבחר
    const scheduler = this.getSelectedScheduler();
    if (!scheduler) {
        window.BookingCalendarUtils.error('יומן לא נבחר');
        return null;
    }
    
    // בניית תאריך ושעה מלא
    const fromDateTime = this.buildDateTime(this.selectedDate, this.selectedTime);
    const duration = this.getTreatmentDuration();
    const toDateTime = new Date(fromDateTime.getTime() + duration * 60000);
    
    // איסוף פרמטרים
    const params = {
        scheduler_id: scheduler.id,
        proxy_schedule_id: scheduler.proxy_schedule_id || scheduler.proxy_scheduler_id,
        treatment_type: this.treatmentType,
        date: this.selectedDate,
        time: this.selectedTime,
        duration: duration,
        from: fromDateTime.toISOString(),
        to: toDateTime.toISOString(),
        clinic_id: this.currentClinicId || scheduler.clinic_id || '',
        doctor_id: this.currentDoctorId || scheduler.doctor_id || '',
        doctor_id_full: scheduler.doctor_id || '', // מזהה הרופא המלא
        clinic_name: scheduler.clinic_name || '',
        doctor_name: scheduler.doctor_name || scheduler.name || '',
        doctor_specialty: scheduler.doctor_specialty || '', // התמחות הרופא
        doctor_thumbnail: scheduler.doctor_thumbnail || '', // תמונת הרופא (אם יש)
        clinic_address: scheduler.clinic_address || '' // כתובת המרפאה (אם יש)
    };
    
    return params;
}
```

### 3. URL Building (booking-calendar-core.js)

```javascript
/**
 * בונה URL עם query parameters
 * @param {number} pageId מזהה העמוד (4366)
 * @param {Object} params פרמטרים להעברה
 * @returns {string} URL מלא עם query parameters
 */
buildBookingUrl(pageId, params) {
    // קבלת URL בסיס של העמוד
    const baseUrl = this.getPageUrl(pageId);
    
    // בניית query string
    const queryParams = new URLSearchParams();
    Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
            queryParams.append(key, params[key]);
        }
    });
    
    // החזרת URL מלא
    const separator = baseUrl.includes('?') ? '&' : '?';
    return `${baseUrl}${separator}${queryParams.toString()}`;
}

/**
 * מקבל URL של עמוד לפי ID
 * @param {number} pageId מזהה העמוד
 * @returns {string} URL של העמוד
 */
getPageUrl(pageId) {
    // אם יש localized data עם permalink, השתמש בו
    if (window.bookingCalendarData && window.bookingCalendarData.pageUrls && 
        window.bookingCalendarData.pageUrls[pageId]) {
        return window.bookingCalendarData.pageUrls[pageId];
    }
    
    // אחרת, בנה URL ידנית
    return `${window.location.origin}/wp-admin/post.php?post=${pageId}&action=edit`;
    
    // או אם זה frontend page:
    // return `${window.location.origin}/?p=${pageId}`;
}
```

### 4. Navigation (booking-calendar-core.js)

```javascript
/**
 * מטפל בלחיצה על כפתור "הזמן תור"
 */
handleBookButtonClick() {
    // איסוף פרמטרים
    const params = this.collectBookingParameters();
    if (!params) {
        return; // שגיאה כבר הוצגה ב-collectBookingParameters
    }
    
    // בניית URL
    const bookingPageId = 4366; // מזהה העמוד עם טופס הזמנת התור
    const url = this.buildBookingUrl(bookingPageId, params);
    
    // מעבר לעמוד
    window.location.href = url;
}
```

---

## קריאת פרמטרים בטופס הזמנת התור

בעמוד 4366, הטופס צריך לקרוא את הפרמטרים מ-URL:

```javascript
// בטופס הזמנת התור (בעמוד 4366):
function getUrlParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

// קריאת פרמטרים
const schedulerId = getUrlParameter('scheduler_id');
const proxyScheduleId = getUrlParameter('proxy_schedule_id');
const treatmentType = getUrlParameter('treatment_type');
const date = getUrlParameter('date');
const time = getUrlParameter('time');
const duration = getUrlParameter('duration');
const from = getUrlParameter('from');
const to = getUrlParameter('to');
const clinicId = getUrlParameter('clinic_id');
const doctorId = getUrlParameter('doctor_id');
const doctorIdFull = getUrlParameter('doctor_id_full');
const doctorName = getUrlParameter('doctor_name');
const doctorSpecialty = getUrlParameter('doctor_specialty');
const doctorThumbnail = getUrlParameter('doctor_thumbnail');
const clinicName = getUrlParameter('clinic_name');
const clinicAddress = getUrlParameter('clinic_address');

// מילוי שדות הטופס
if (schedulerId) {
    document.querySelector('[name="scheduler_id"]').value = schedulerId;
}
if (treatmentType) {
    document.querySelector('[name="treatment_type"]').value = treatmentType;
}
// ... וכו'
```

---

## דוגמת URL סופי

```
https://example.com/?p=4366&scheduler_id=123&proxy_schedule_id=789&treatment_type=בדיקה%20כללית&date=2025-12-28&time=16:00&duration=30&from=2025-12-28T16:00:00Z&to=2025-12-28T16:30:00Z&clinic_id=1&doctor_id=5&doctor_id_full=5&clinic_name=מרפאת%20דוגמה&doctor_name=ד"ר%20ישראל%20ישראלי&doctor_specialty=מומחית%20לרפואת%20ילדים&doctor_thumbnail=https://example.com/image.jpg&clinic_address=אבן%20גבירול%2015%20תל%20אביב
```

---

## הערות חשובות

1. **Encoding**: כל הפרמטרים עוברים URL encoding אוטומטי דרך `URLSearchParams`
2. **Validation**: יש לוודא שכל הפרמטרים החובה קיימים לפני מעבר לעמוד
3. **Error Handling**: אם חסרים פרמטרים, יש להציג הודעה למשתמש ולא לעבור לעמוד
4. **Backward Compatibility**: אם הטופס לא קורא פרמטרים מ-URL, הוא צריך לעבוד גם בלי פרמטרים (fallback)
5. **Security**: יש לוודא שהפרמטרים מאומתים בטופס לפני שימוש (sanitization, validation)

---

## בדיקות

1. ✅ בחירת סלוט מפעילה את הכפתור
2. ✅ לחיצה על הכפתור אוספת את כל הפרמטרים
3. ✅ URL נבנה נכון עם כל הפרמטרים
4. ✅ מעבר לעמוד 4366 מצליח
5. ✅ הטופס קורא את הפרמטרים נכון
6. ✅ שדות הטופס מתמלאים נכון
7. ✅ הזמנת התור עובדת עם הפרמטרים
