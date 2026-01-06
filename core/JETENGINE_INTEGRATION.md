# 🔌 JetEngine Integration

## סקירה כללית

קובץ `class-jetengine-integration.php` מטפל באינטגרציה בין התוסף ל-JetEngine, בעיקר למשיכת תתי תחומים רפואיים מ-API חיצוני.

---

## 🎯 מה זה עושה?

### 1. משיכת תתי תחומים מ-API חיצוני

**API Endpoint:**
```
https://doctor-place.com/wp-json/clinics/sub-specialties/
```

**מחזיר:** 60+ תתי תחומים רפואיים (אודיולוגיה, קרדיולוגיה, וכו')

### 2. הזרקה לשדות JetEngine

המחלקה מזריקה את התתי תחומים לשני מקומות:

#### א. Meta Fields (בעריכת פוסט)
**Hook:** `jet-engine/meta-fields/config`

**איפה:** כשעורכים פוסט מסוג `clinics`

**שדה:** `treatment_type` בתוך repeater `treatments`

**תוצאה:** רשימת Select עם 60+ תתי תחומים במקום רשימה קבועה

#### ב. JetFormBuilder Forms
**Hook:** `jet-engine/forms/booking/field-value`

**איפה:** בטפסים שיש בהם שדה `treatment_type`

**תוצאה:** האופציות נטענות דינמית מה-API

---

## 📋 מבנה הקוד

### מתודות ציבוריות:

#### `get_instance()`
```php
$integration = Clinic_Queue_JetEngine_Integration::get_instance();
```
Singleton instance של המחלקה.

#### `get_treatment_types_simple()`
```php
$treatments = $integration->get_treatment_types_simple();
// Returns: ['רפואה כללית' => 'רפואה כללית', ...]
```
מחזיר תתי תחומים בפורמט פשוט (name => name).

### מתודות פרטיות:

#### `get_treatment_types_from_api()`
מושך נתונים מה-API ומעבד אותם לפורמט JetEngine:
```php
[
    ['value' => 'אודיולוגיה', 'label' => 'אודיולוגיה'],
    ['value' => 'קרדיולוגיה', 'label' => 'קרדיולוגיה'],
    ...
]
```

**תכונות:**
- ✅ Error handling מלא
- ✅ Fallback ל-5 תתי תחומים בסיסיים
- ✅ מיון אלפביתי
- ✅ Timeout של 10 שניות

---

## 🔄 תהליך העבודה

### 1. טעינה ראשונית
```php
// In class-plugin-core.php
require_once CLINIC_QUEUE_MANAGEMENT_PATH . 'core/class-jetengine-integration.php';
Clinic_Queue_JetEngine_Integration::get_instance();
```

### 2. Hook Registration
המחלקה רושמת את עצמה ל-hooks של JetEngine:
```php
add_filter('jet-engine/meta-fields/config', [...], 10, 2);
add_filter('jet-engine/forms/booking/field-value', [...], 10, 3);
```

### 3. עריכת Clinic Post
כשעורכים clinic → JetEngine קורא ל-filter שלנו → אנחנו מחזירים אופציות מה-API

### 4. טופס JetFormBuilder
כשמציגים טופס עם `treatment_type` → JetFormBuilder קורא ל-filter שלנו → אופציות מה-API

---

## 🧪 בדיקות

### בדיקה 1: עריכת Clinic
1. עבור ל-WordPress Admin
2. ערוך clinic post
3. בחן את השדה `treatment_type` ב-repeater `treatments`
4. ✅ אמור להציג 60+ אופציות

### בדיקה 2: טופס JetFormBuilder
1. צור טופס עם שדה `treatment_type`
2. הצג את הטופס בפרונט-אנד
3. ✅ אמור להציג 60+ אופציות

### בדיקה 3: API Failure
1. נתק אינטרנט זמנית
2. ערוך clinic post
3. ✅ אמור להציג 5 תתי תחומים בסיסיים (fallback)

---

## 🐛 Troubleshooting

### בעיה: לא רואה אופציות חדשות

**פתרון:**
```php
// Clear any JetEngine cache
delete_transient('jet_engine_meta_boxes');

// או דרך WP-CLI:
wp transient delete jet_engine_meta_boxes
```

### בעיה: API לא עונה

**בדוק:**
1. האם ה-endpoint זמין?
   ```bash
   curl https://doctor-place.com/wp-json/clinics/sub-specialties/
   ```
2. בדוק error log:
   ```bash
   tail -f wp-content/debug.log | grep "JetEngine Integration"
   ```

### בעיה: אופציות לא ממוינות

**פתרון:** המיון מתבצע ב-`get_treatment_types_from_api()` דרך `usort()`. אם זה לא עובד, בדוק שה-locale של השרת תומך בעברית.

---

## 🔮 עתיד

### אופציות לשיפור:

1. **Caching**
   ```php
   // הוסף בתחילת get_treatment_types_from_api():
   $cached = get_transient('clinic_treatment_types_from_api');
   if ($cached) return $cached;
   
   // הוסף לפני return:
   set_transient('clinic_treatment_types_from_api', $treatments, 5 * MINUTE_IN_SECONDS);
   ```

2. **Background Sync**
   - שימוש ב-WP Cron לעדכון תקופתי
   - שמירה ב-option במקום transient

3. **Admin UI**
   - כפתור "רענן תתי תחומים" ב-settings
   - הצגת מספר התתי תחומים הזמינים

---

## 📚 קישורים

- [JetEngine Filters Documentation](https://crocoblock.com/knowledge-base/jetengine/hooks/)
- [WordPress Transients API](https://developer.wordpress.org/apis/transients/)
- [API Endpoint](https://doctor-place.com/wp-json/clinics/sub-specialties/)

---

**נוצר:** ינואר 2026  
**גרסה:** 1.0  
**סטטוס:** ✅ פעיל

