# הוראות דיבאג - ארון חשמל 🔌

## איך להשתמש

### שלב 1: פתח את `debug-config.php`
פתח את הקובץ `debug-config.php` בתיקיית התוסף וערוך אותו:

```php
// שלב 1: כבה הכל חוץ מ-CSS
// שנה את הערכים מ-false ל-true

define('CLINIC_QUEUE_DISABLE_SHORTCODE', true);
define('CLINIC_QUEUE_DISABLE_AJAX', true);
define('CLINIC_QUEUE_DISABLE_REST_API', true);
define('CLINIC_QUEUE_DISABLE_ADMIN_MENU', true);
define('CLINIC_QUEUE_DISABLE_WIDGET', true);
define('CLINIC_QUEUE_DISABLE_VERSION_CHECK', true);
define('CLINIC_QUEUE_DISABLE_JS', true);
// CSS נשאר פעיל (CLINIC_QUEUE_DISABLE_CSS נשאר false)
```

### שלב 2: בדוק אם הטפסים חזרו
- אם הטפסים חזרו → הבעיה היא באחד החלקים שכיבית
- אם הטפסים עדיין נעלמים → הבעיה היא ב-CSS

### שלב 3: הדלק חלק אחד בכל פעם

**אם הטפסים חזרו כשכיבית הכל:**
1. פתח את `debug-config.php`
2. שנה את `CLINIC_QUEUE_DISABLE_SHORTCODE` מ-`true` ל-`false` (הדלק)
3. שמור את הקובץ
4. בדוק אם הטפסים נעלמו שוב
5. אם כן → הבעיה היא ב-SHORTCODE
6. אם לא → המשך לחלק הבא (הדלק חלק אחר)

**אם הטפסים עדיין נעלמים כשכיבית הכל:**
1. פתח את `debug-config.php`
2. שנה את `CLINIC_QUEUE_DISABLE_CSS` מ-`false` ל-`true` (כבה)
3. שמור את הקובץ
4. בדוק אם הטפסים חזרו
5. אם כן → הבעיה היא ב-CSS שלנו

## רשימת כל החלקים שאפשר לכבות:

בקובץ `debug-config.php`, שנה את הערכים מ-`false` ל-`true`:

```php
// CSS - קבצי עיצוב
define('CLINIC_QUEUE_DISABLE_CSS', true);  // שנה מ-false ל-true

// JS - קבצי JavaScript
define('CLINIC_QUEUE_DISABLE_JS', true);  // שנה מ-false ל-true

// SHORTCODE - טופס הוספת יומן
define('CLINIC_QUEUE_DISABLE_SHORTCODE', true);  // שנה מ-false ל-true

// AJAX - מטפלי AJAX
define('CLINIC_QUEUE_DISABLE_AJAX', true);  // שנה מ-false ל-true

// REST_API - REST API endpoints
define('CLINIC_QUEUE_DISABLE_REST_API', true);  // שנה מ-false ל-true

// ADMIN_MENU - תפריט ניהול
define('CLINIC_QUEUE_DISABLE_ADMIN_MENU', true);  // שנה מ-false ל-true

// WIDGET - ווידג'ט Elementor
define('CLINIC_QUEUE_DISABLE_WIDGET', true);  // שנה מ-false ל-true

// VERSION_CHECK - בדיקות גרסאות
define('CLINIC_QUEUE_DISABLE_VERSION_CHECK', true);  // שנה מ-false ל-true
```

## תהליך מומלץ:

### ניסוי 1: כבה הכל חוץ מ-CSS
פתח את `debug-config.php` ושנה:
```php
define('CLINIC_QUEUE_DISABLE_SHORTCODE', true);  // false → true
define('CLINIC_QUEUE_DISABLE_AJAX', true);  // false → true
define('CLINIC_QUEUE_DISABLE_REST_API', true);  // false → true
define('CLINIC_QUEUE_DISABLE_ADMIN_MENU', true);  // false → true
define('CLINIC_QUEUE_DISABLE_WIDGET', true);  // false → true
define('CLINIC_QUEUE_DISABLE_VERSION_CHECK', true);  // false → true
define('CLINIC_QUEUE_DISABLE_JS', true);  // false → true
// CLINIC_QUEUE_DISABLE_CSS נשאר false
```
**תוצאה:** אם הטפסים חזרו → הבעיה באחד החלקים. אם לא → הבעיה ב-CSS.

### ניסוי 2: כבה גם CSS
בקובץ `debug-config.php`, שנה גם:
```php
define('CLINIC_QUEUE_DISABLE_CSS', true);  // false → true
// + כל השאר מהניסוי הקודם נשאר true
```
**תוצאה:** אם הטפסים חזרו → הבעיה ב-CSS שלנו.

### ניסוי 3: הדלק חלק אחד בכל פעם
שנה חלק אחד בכל פעם מ-`true` חזרה ל-`false` ובדוק מתי הטפסים נעלמים שוב.

## איך לראות מה כבוי

כשתכנס לדף התוספים (Plugins), תראה הודעה צהובה שמראה אילו חלקים כבויים.

## טיפים:

1. **תמיד שמור גיבוי** של `debug-config.php` לפני שינויים
2. **נסה חלק אחד בכל פעם** - זה יעזור לזהות בדיוק איפה הבעיה
3. **אחרי כל שינוי** - שמור את הקובץ ורענן את הדף
4. **תעד את התוצאות** - כתוב מה עבד ומה לא
5. **הקובץ נטען אוטומטית** - אין צורך לערוך `wp-config.php`

## דוגמה לתהליך מלא:

```
1. כבה הכל חוץ מ-CSS → הטפסים עדיין נעלמים
2. כבה גם CSS → הטפסים חזרו! ✅
   → הבעיה היא ב-CSS שלנו
3. בדוק איזה קובץ CSS גורם לבעיה
```

או:

```
1. כבה הכל חוץ מ-CSS → הטפסים חזרו! ✅
2. הדלק SHORTCODE → הטפסים נעלמו שוב ❌
   → הבעיה היא ב-SHORTCODE
3. בדוק מה ב-SHORTCODE גורם לבעיה
```

