# ארכיטקטורת טקסונומיות: התמחויות וסוגי טיפולים

## סקירה

מערכת שתי טקסונומיות נפרדות עם קישור **many-to-many** דרך term meta.

---

## הטקסונומיות

### 1. `specialties` – התמחויות (קיימת)

- **שימוש:** התמחויות רפואיות (רופא משפחה, רופא ילדים, גינקולוג...)
- **משויכת ל:** doctors, clinics, schedules (ברירת מחדל)
- **סוג:** flat (לא היררכית)
- **רישום:** על ידי JetEngine או התוסף (אם לא קיימת). אם קיימת – מוסיף שיוך ל-CPT דרך `register_taxonomy_for_object_type`.

### 2. `treatment_types` – סוגי טיפולים (חדשה)

- **שימוש:** סוגי טיפולים (בדיקה כללית, טיפול שורש, מעקב הריון...)
- **משויכת ל:** doctors, clinics, schedules (ברירת מחדל)
- **סוג:** flat (לא היררכית)
- **רישום:** על ידי התוסף

---

## קישור Many-to-Many

כל **term של טיפול** שומר ב-**term meta**:
- **מפתח:** `specialty_ids`
- **ערך:** מערך של term IDs מ-`specialties`

**דוגמה:**
```php
// טיפול "בדיקות עיניים כלליות" שייך ל-3 התמחויות:
update_term_meta($treatment_term_id, 'specialty_ids', [
    12, // רופא עיניים
    45, // רופא משפחה
    78  // אופטומטריסט
]);
```

---

## ייבוא מ-JSON

### זרימה

1. **הכנת JSON:**
   ```bash
   node scripts/csv-to-json.mjs
   ```
   פלט: `core/out-specialties.json` – מערך של `{ name, slug, treatments: [...] }`

2. **ייבוא ב-WordPress:**
   - **ממשק ניהול:** מערכת ניהול מרפאות → **ייבוא התמחויות** → כפתור "הרץ ייבוא"
   - **מקוד:**
     ```php
     Clinic_Queue_Specialty_Taxonomy::get_instance()->seed_from_json();
     ```

3. **תהליך הייבוא:**
   - לכל התמחות ב-JSON:
     - יצירת/שליפת term ב-`specialties`
     - לכל טיפול תחת ההתמחות:
       - יצירת/שליפת term ב-`treatment_types` (אם כבר קיים – לא יוצר כפילות)
       - הוספת `specialty_term_id` ל-`specialty_ids` meta (אם עדיין לא שם)

4. **תוצאה:**
   - אין כפילויות: כל טיפול = term אחד
   - טיפול שמופיע ב-3 עמודות (התמחויות) ב-CSV → term אחד עם 3 IDs ב-`specialty_ids`

---

## ממשק אדמין – שיוך טיפולים להתמחויות

**מיקום:** `admin/handlers/class-treatment-specialty-handler.php` + `admin/views/seed-specialties-html.php`

- **תצוגה:** ברשימת סוגי הטיפולים (מונחים → סוגי טיפולים) מופיעה עמודה "התמחויות משויכות".
- **הוספה:** בטופס הוספת סוג טיפול חדש – שדה בחירה מרובה (checkboxes) עם חיפוש.
- **עריכה:** בטופס עריכת סוג טיפול – אותו שדה עם הבחירות הנוכחיות.
- **כפתורים:** "בחר הכל", "בטל הכל", שדה חיפוש להתמחויות.
- **ייבוא:** דף "ייבוא התמחויות" תחת מערכת ניהול מרפאות.

---

## שימוש בקוד

### שליפת טיפולים לפי התמחות

```php
// כל הטיפולים של "רופא משפחה"
$specialty = get_term_by('slug', 'רופא-משפחה', 'specialties');
$treatments = Clinic_Queue_Specialty_Taxonomy::get_treatments_by_specialty($specialty->term_id);

foreach ($treatments as $treatment) {
    echo $treatment->name; // "בדיקה כללית", "מעקב חולים" וכו'
}
```

### שליפת התמחויות של טיפול

```php
// כל ההתמחויות שמציעות "טיפול שורש"
$treatment = get_term_by('slug', 'טיפול-שורש', 'treatment_types');
$specialty_ids = Clinic_Queue_Specialty_Taxonomy::get_specialties_of_treatment($treatment->term_id);

foreach ($specialty_ids as $spec_id) {
    $spec = get_term($spec_id, 'specialties');
    echo $spec->name; // "רופא שיניים", "אנדודונט" וכו'
}
```

### שיוך טיפולים ליומן

ביומן (schedule) יש repeater או רשימת term IDs מ-`treatment_types`:

```php
// שמירה ביומן
update_post_meta($schedule_id, 'treatment_type_ids', [15, 42, 67]); // term IDs מ-treatment_types

// שליפה
$treatment_ids = get_post_meta($schedule_id, 'treatment_type_ids', true);
foreach ($treatment_ids as $tid) {
    $term = get_term($tid, 'treatment_types');
    echo $term->name;
}
```

---

## שיוך לסוגי פוסט (CPT)

**ברירת מחדל:** שתי הטקסונומיות משויכות ל-`doctors`, `clinics`, `schedules`.

אם ה-CPT ב-JetEngine משתמשים ב-slugs אחרים, או שרוצים שיוך אחר:

```php
// בקובץ functions.php או בתוסף
add_filter('clinic_queue_specialty_taxonomy_object_types', function($types) {
    return array('doctors', 'clinics', 'schedules');
});

add_filter('clinic_queue_treatment_taxonomy_object_types', function($types) {
    return array('doctors', 'clinics', 'schedules');
});
```

---

## יתרונות הגישה

✅ **אין כפילויות** – כל טיפול נוצר פעם אחת  
✅ **many-to-many** – טיפול שייך לכמה התמחויות  
✅ **שומר מבנה קיים** – `specialties` נשאר flat  
✅ **גמיש** – קל להוסיף/להסיר קישורים  
✅ **שאילתות יעילות** – query ב-meta או traverse ב-loop קטן

---

## הערות

- **ריצה ראשונה:** אחרי עדכון הקוד, הרץ "ייבוא התמחויות" בממשק הניהול (פעם אחת).
- **עדכון JSON:** אחרי הרצת `csv-to-json.mjs` מחדש, תצטרך למחוק את האופציה `clinic_queue_specialties_seeded` (או ללחוץ "הרץ ייבוא" שוב – הוא יוסיף terms חדשים ללא כפילויות).
- **ניקוי לפני re-import:** אם רוצה לאפס הכל:
  ```php
  delete_option('clinic_queue_specialties_seeded');
  // אופציונלי: מחיקת terms קיימים מ-treatment_types אם צריך
  ```
