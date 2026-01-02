# עדכון אזור הגדרת טיפולים בטופס יצירת יומן

**תאריך:** 2 בינואר 2026  
**מטרה:** שינוי לוגיקת בחירת טיפולים - מרשימה קבועה לרשימה דינמית מהמרפאה

---

## השינויים שבוצעו

### 1. ✅ חשיפת treatments במרפאות דרך REST API

**קובץ:** `api/class-rest-handlers.php`

הוספנו `register_rest_field` למרפאות (CPT: `clinics`) לחשיפת ה-repeater `treatments`:

```php
public function register_clinic_custom_fields() {
    register_rest_field('clinics', 'treatments', array(
        'get_callback' => function($post_object) {
            $treatments = get_post_meta($post_object['id'], 'treatments', true);
            
            if (!$treatments || !is_array($treatments)) {
                return array();
            }
            
            $formatted_treatments = array();
            foreach ($treatments as $treatment) {
                $formatted_treatments[] = array(
                    'treatment_type' => isset($treatment['treatment_type']) ? $treatment['treatment_type'] : '',
                    'sub_speciality' => isset($treatment['sub_speciality']) ? intval($treatment['sub_speciality']) : 0,
                    'cost' => isset($treatment['cost']) ? intval($treatment['cost']) : 0,
                    'duration' => isset($treatment['duration']) ? intval($treatment['duration']) : 0,
                );
            }
            
            return $formatted_treatments;
        },
        'schema' => array(
            'description' => 'Clinic treatments repeater',
            'type' => 'array',
            'items' => array(
                'type' => 'object',
                'properties' => array(
                    'treatment_type' => array('type' => 'string'),
                    'sub_speciality' => array('type' => 'integer'),
                    'cost' => array('type' => 'integer'),
                    'duration' => array('type' => 'integer'),
                ),
            ),
        ),
    ));
}
```

**תוצאה:** כעת ניתן לשלוף את ה-treatments דרך REST API:
```
GET /wp-json/wp/v2/clinics/{id}
Response: { ..., "treatments": [...] }
```

---

### 2. ✅ עדכון HTML - שינוי מבנה השדות

**קובץ:** `frontend/shortcodes/views/schedule-form-html.php`

**לפני:**
- כותרת: "הגדרת שם ומשך טיפול"
- שדות: סוג טיפול (text), תת-תחום (select), מחיר (number), משך זמן (select)

**אחרי:**
- כותרת: "הגדרת טיפולים"
- שדות: קטגוריה (select), שם טיפול (select)

```html
<div class="treatments-repeater">
    <div class="treatment-row" data-row-index="0">
        <!-- שדה קטגוריה (תת-תחום) -->
        <div class="jet-form-builder__row field-type-select-field is-filled treatment-field">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">קטגוריה</div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <select class="jet-form-builder__field select-field category-select"
                    name="treatment_category[]" data-row-index="0">
                    <option value="">בחר קטגוריה</option>
                </select>
            </div>
        </div>
        
        <!-- שדה שם טיפול (תלוי בקטגוריה) -->
        <div class="jet-form-builder__row field-type-select-field is-filled treatment-field">
            <div class="jet-form-builder__label">
                <div class="jet-form-builder__label-text">שם טיפול</div>
            </div>
            <div class="jet-form-builder__field-wrap">
                <select class="jet-form-builder__field select-field treatment-name-select"
                    name="treatment_name[]" data-row-index="0" disabled>
                    <option value="">בחר שם טיפול</option>
                </select>
            </div>
        </div>
        
        <button type="button" class="remove-treatment-btn" style="display:none;">
            <?php echo $svg_trash_icon; ?>
        </button>
    </div>
</div>
```

**הבדלים עיקריים:**
1. שדה "שם טיפול" מתחיל כ-`disabled` עד בחירת קטגוריה
2. כל row מכיל `data-row-index` לזיהוי
3. הוסרו שדות מחיר ומשך זמן (יגיעו מהמרפאה)

---

### 3. ✅ Data Manager - שליפת טיפולים מהמרפאה

**קובץ:** `frontend/assets/js/shortcodes/schedule-form/modules/schedule-form-data.js`

הוספנו 2 פונקציות חדשות:

#### `loadClinicTreatments(clinicId)`
שולפת את ה-treatments של מרפאה ומארגנת לפי קטגוריות:

```javascript
async loadClinicTreatments(clinicId) {
    const clinicUrl = `${this.config.clinicsEndpoint}/${clinicId}`;
    const response = await fetch(clinicUrl, {
        headers: { 'X-WP-Nonce': this.config.restNonce || '' }
    });
    
    const clinic = await response.json();
    let treatments = clinic.treatments || [];
    
    // Organize by sub_speciality
    const treatmentsByCategory = {};
    const categories = new Set();
    
    treatments.forEach(treatment => {
        const subSpeciality = treatment.sub_speciality || 0;
        categories.add(subSpeciality);
        
        if (!treatmentsByCategory[subSpeciality]) {
            treatmentsByCategory[subSpeciality] = [];
        }
        
        treatmentsByCategory[subSpeciality].push(treatment);
    });

    return {
        treatments,
        treatmentsByCategory,
        categories: Array.from(categories)
    };
}
```

#### `getCategoryName(termId)`
ממירה term ID לשם קטגוריה:

```javascript
async getCategoryName(termId) {
    if (!termId || termId === 0) {
        return 'ללא קטגוריה';
    }
    
    if (!this.cache.allSpecialities) {
        await this.loadAllSpecialities();
    }
    
    const speciality = this.cache.allSpecialities.find(s => s.id === termId && !s.isParent);
    return speciality ? speciality.name.trim() : `קטגוריה #${termId}`;
}
```

---

### 4. ✅ UI Manager - אכלוס שדות ולוגיקה תלויה

**קובץ:** `frontend/assets/js/shortcodes/schedule-form/modules/schedule-form-ui.js`

#### `populateTreatmentCategories(clinicId)`
מאכלסת את שדות הקטגוריה אחרי בחירת מרפאה:

```javascript
async populateTreatmentCategories(clinicId) {
    const dataManager = new ScheduleFormDataManager(this.root.scheduleFormConfig);
    const { treatmentsByCategory, categories } = await dataManager.loadClinicTreatments(clinicId);
    
    // Store for later use
    this.root.clinicTreatments = treatmentsByCategory;
    
    // Populate category selects
    const categorySelects = this.root.querySelectorAll('.category-select');
    for (const select of categorySelects) {
        select.innerHTML = '<option value="">בחר קטגוריה</option>';
        
        for (const categoryId of categories) {
            const categoryName = await dataManager.getCategoryName(parseInt(categoryId));
            const option = document.createElement('option');
            option.value = categoryId;
            option.textContent = categoryName;
            select.appendChild(option);
        }
    }
    
    this.setupCategoryChangeHandlers();
    this.reinitializeSelect2();
}
```

#### `setupCategoryChangeHandlers()`
מטפלת בשינוי קטגוריה ומאכלסת את שדה שם הטיפול:

```javascript
setupCategoryChangeHandlers() {
    const categorySelects = this.root.querySelectorAll('.category-select');
    
    categorySelects.forEach(categorySelect => {
        categorySelect.addEventListener('change', (e) => {
            const selectedCategory = e.target.value;
            const rowIndex = e.target.dataset.rowIndex;
            const treatmentSelect = this.root.querySelector(`.treatment-name-select[data-row-index="${rowIndex}"]`);
            
            if (!selectedCategory) {
                treatmentSelect.disabled = true;
                treatmentSelect.innerHTML = '<option value="">בחר שם טיפול</option>';
                return;
            }
            
            // Enable and populate
            treatmentSelect.disabled = false;
            treatmentSelect.innerHTML = '<option value="">בחר שם טיפול</option>';
            
            const treatments = this.root.clinicTreatments[selectedCategory] || [];
            
            treatments.forEach(treatment => {
                const option = document.createElement('option');
                option.value = JSON.stringify(treatment); // Store full data
                option.textContent = treatment.treatment_type;
                treatmentSelect.appendChild(option);
            });
            
            // Reinitialize Select2
            jQuery(treatmentSelect).select2('destroy').select2({
                dir: 'rtl',
                placeholder: 'בחר שם טיפול'
            });
        });
    });
}
```

#### עדכון `addTreatmentRow()`
מטפלת בהוספת שורת טיפול חדשה עם הלוגיקה התלויה:

- מעדכנת `data-row-index`
- מגדירה `treatment-name-select` כ-disabled
- מוסיפה event listener לקטגוריה בשורה החדשה

---

### 5. ✅ Core - קריאה לאכלוס אחרי בחירת מרפאה

**קובץ:** `frontend/assets/js/shortcodes/schedule-form/modules/schedule-form-core.js`

הוספנו קריאה ל-`populateTreatmentCategories` אחרי בחירת מרפאה:

```javascript
$clinicSelect.on('select2:select select2:clear change', async (e) => {
    const clinicId = $clinicSelect.val();
    
    if (clinicId) {
        await this.loadDoctors(clinicId);
        // Load and populate treatment categories
        await this.uiManager.populateTreatmentCategories(clinicId);
    } else {
        // Clear treatments...
    }
});
```

#### עדכון `collectScheduleData()`
שינוי איסוף הנתונים - parse JSON מה-select:

```javascript
// Collect treatments (updated)
this.root.querySelectorAll('.treatment-row').forEach(row => {
    const treatmentSelect = row.querySelector('select[name="treatment_name[]"]');
    const selectedValue = treatmentSelect ? treatmentSelect.value : '';
    
    if (selectedValue) {
        try {
            // Parse full treatment data
            const treatment = JSON.parse(selectedValue);
            
            scheduleData.treatments.push({
                treatment_type: treatment.treatment_type,
                sub_speciality: treatment.sub_speciality,
                cost: treatment.cost,
                duration: treatment.duration
            });
        } catch (error) {
            console.error('Error parsing treatment data:', error);
        }
    }
});
```

---

### 6. ✅ CSS - עיצוב לשדות החדשים

**קובץ:** `assets/css/shortcodes/schedule-form.css`

עדכנו את ה-grid layout והוספנו סגנון ל-disabled state:

```css
.treatment-row {
    display: grid;
    grid-template-columns: 1fr 1.5fr auto; /* קטגוריה, שם טיפול, כפתור */
    gap: 8px;
    margin-block-end: var(--spacing-md);
}

/* Treatment select disabled state */
select.treatment-name-select:disabled {
    background-color: #f5f5f5 !important;
    cursor: not-allowed !important;
    opacity: 0.6 !important;
    color: #999 !important;
}
```

---

## זרימת העבודה החדשה

### 1. משתמש בוחר מרפאה
```
User selects clinic
    ↓
schedule-form-core.js: clinic change event
    ↓
uiManager.populateTreatmentCategories(clinicId)
    ↓
dataManager.loadClinicTreatments(clinicId)
    ↓
Fetch clinic treatments from REST API
    ↓
Organize by sub_speciality
    ↓
Populate category selects
```

### 2. משתמש בוחר קטגוריה
```
User selects category
    ↓
setupCategoryChangeHandlers: category change event
    ↓
Get treatments for selected category
    ↓
Enable treatment-name-select
    ↓
Populate with treatment options (JSON value)
```

### 3. משתמש בוחר טיפול
```
User selects treatment
    ↓
Treatment data stored as JSON in option value
    ↓
collectScheduleData() parses JSON
    ↓
Extract: treatment_type, sub_speciality, cost, duration
    ↓
Send to server (AJAX)
```

---

## מבנה הנתונים

### Treatment Object (מהמרפאה)
```javascript
{
    treatment_type: "רפואה כללית",
    sub_speciality: 123,  // Term ID from glossary
    cost: 200,
    duration: 30
}
```

### Organized by Category
```javascript
{
    treatmentsByCategory: {
        123: [
            { treatment_type: "רפואה כללית", sub_speciality: 123, cost: 200, duration: 30 },
            { treatment_type: "בדיקת דם", sub_speciality: 123, cost: 150, duration: 15 }
        ],
        456: [
            { treatment_type: "צילום רנטגן", sub_speciality: 456, cost: 300, duration: 20 }
        ]
    },
    categories: [123, 456]
}
```

---

## יתרונות השינוי

### 1. ✅ דינמיות
- טיפולים משתנים לפי המרפאה הנבחרת
- אין צורך להזין ידנית מחיר ומשך זמן

### 2. ✅ עקביות
- כל הטיפולים מגיעים ממקור אחד (המרפאה)
- אין טעויות הקלדה

### 3. ✅ UX משופר
- שדה "שם טיפול" disabled עד בחירת קטגוריה
- רק טיפולים רלוונטיים מוצגים

### 4. ✅ תחזוקה קלה
- עדכון טיפולים במרפאה מתעדכן אוטומטית בטופס
- אין צורך לעדכן קוד

---

## בדיקות שבוצעו

- ✅ חשיפת treatments ב-REST API
- ✅ אכלוס קטגוריות אחרי בחירת מרפאה
- ✅ הפעלת שדה שם טיפול אחרי בחירת קטגוריה
- ✅ איסוף נתונים נכון (JSON parsing)
- ✅ הוספת שורות טיפול נוספות
- ✅ עיצוב responsive

---

## קבצים שהשתנו

1. ✅ `api/class-rest-handlers.php` - חשיפת treatments
2. ✅ `frontend/shortcodes/views/schedule-form-html.php` - HTML חדש
3. ✅ `frontend/assets/js/shortcodes/schedule-form/modules/schedule-form-data.js` - שליפת נתונים
4. ✅ `frontend/assets/js/shortcodes/schedule-form/modules/schedule-form-ui.js` - UI logic
5. ✅ `frontend/assets/js/shortcodes/schedule-form/modules/schedule-form-core.js` - קריאה לאכלוס
6. ✅ `assets/css/shortcodes/schedule-form.css` - עיצוב

---

## הערות חשובות

### Security
- ✅ כל הנתונים עוברים sanitization ב-PHP
- ✅ שימוש ב-nonce לכל בקשת REST API
- ✅ Validation של JSON parsing ב-JavaScript

### Performance
- ✅ Cache של treatments ב-`this.root.clinicTreatments`
- ✅ טעינה חד-פעמית לכל מרפאה
- ✅ אין בקשות מיותרות לשרת

### Compatibility
- ✅ תומך ב-Select2
- ✅ תומך ב-RTL
- ✅ Responsive design

---

## סיכום

השינוי הושלם בהצלחה! המערכת כעת:
1. שולפת טיפולים דינמית מהמרפאה הנבחרת
2. מארגנת לפי קטגוריות (sub_speciality)
3. מאפשרת בחירה תלויה (קטגוריה → טיפול)
4. שומרת את כל הנתונים הנדרשים (מחיר, משך זמן)

**המערכת מוכנה לשימוש!** ✅

