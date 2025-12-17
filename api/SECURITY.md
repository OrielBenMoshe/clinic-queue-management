# אבטחת טוקן API

## הגדרת טוקן API

הפלאגין תומך בכמה דרכים לאבטחת טוקן ה-API. הדרך המומלצת ביותר היא שמירה ב-`wp-config.php`.

**דרך קלה: הגדרה דרך ממשק הניהול**
1. עבור ל-`ניהול תורים > הגדרות` בממשק הניהול של WordPress
2. הזן את הטוקן בשדה "טוקן API"
3. לחץ על "שמור הגדרות"
4. הטוקן יישמר מוצפן במסד הנתונים

**דרך מאובטחת יותר: הגדרה ב-wp-config.php**

### שיטה מומלצת: wp-config.php

1. פתח את קובץ `wp-config.php` (בשורש האתר, מחוץ לתיקיית `wp-content`)
2. הוסף את השורה הבאה לפני השורה `/* That's all, stop editing! Happy publishing. */`:

```php
// Clinic Queue API Token
define('CLINIC_QUEUE_API_TOKEN', 'pMtGAAMhbpLg21nFPaUhEr6UJaeUcrrHhTvmzewMkEc7gwTGv2EpGm8Xp7C6wHRutncWp78ceV30Qp3XroYoM9mzQCqvJ3NGnEpp');
```

3. שמור את הקובץ

**יתרונות:**
- ✅ הטוקן לא נשמר במסד הנתונים
- ✅ לא נגיש דרך HTTP
- ✅ לא מוצג בממשק הניהול של WordPress
- ✅ ניתן להוסיף ל-`.gitignore` (לא יועלה ל-Git)

### אבטחה נוספת

1. **ודא ש-`wp-config.php` מחוץ לתיקיית `wp-content`**
   - הקובץ צריך להיות ברמת השורש של האתר
   - לא נגיש דרך דפדפן

2. **הוסף ל-`.gitignore`** (אם משתמשים ב-Git):
   ```
   wp-config.php
   ```

3. **הרשאות קבצים:**
   - `wp-config.php` צריך להיות עם הרשאות `600` או `644`
   - רק הבעלים יכול לקרוא/לכתוב

4. **סיבוב טוקנים:**
   - החלף את הטוקן ב-`wp-config.php` באופן קבוע
   - עדכן את הטוקן אם יש חשד לפריצה

### בדיקת הגדרה

לאחר הגדרת הטוקן, בדוק שהפלאגין משתמש בו:

1. פתח את לוג השגיאות של WordPress
2. בצע בקשה ל-API
3. ודא שהבקשה מצליחה (קוד 200)

### פתרון בעיות

**הטוקן לא עובד:**
- ודא שהטוקן מוגדר נכון ב-`wp-config.php`
- בדוק שאין שגיאות תחביר ב-PHP
- ודא שהטוקן לא מכיל גרשיים או תווים מיוחדים (אם כן, השתמש ב-`'` במקום `"`)

**הפלאגין עדיין משתמש ב-scheduler_id:**
- זה תקין - אם אין טוקן מוגדר, הפלאגין יפול חזרה ל-scheduler_id (התנהגות legacy)
- ודא שהטוקן מוגדר נכון ב-`wp-config.php`

## דרכים חלופיות

### דרך WordPress Options (מוצפן)

הפלאגין יכול לשמור את הטוקן מוצפן ב-WordPress options. זה פחות מאובטח מ-`wp-config.php` כי:
- נשמר במסד הנתונים
- נגיש דרך ממשק הניהול (אם יש גישה)
- אבל עדיין מוצפן

### דרך Filter (תכנותי)

```php
add_filter('clinic_queue_api_token', function($token, $scheduler_id) {
    // החזר טוקן מהמקור המאובטח שלך
    return 'your-token-here';
}, 10, 2);
```

## סדר עדיפויות

הפלאגין בודק את הטוקן בסדר הבא:

1. **`CLINIC_QUEUE_API_TOKEN` constant** (מ-`wp-config.php`) - הכי מאובטח
2. **WordPress option מוצפן** (`clinic_queue_api_token_encrypted`)
3. **Filter** (`clinic_queue_api_token`)
4. **Fallback ל-scheduler_id** (התנהגות legacy)

## תמיכה

אם יש בעיות עם הגדרת הטוקן, בדוק:
- לוג השגיאות של WordPress
- תגובות ה-API (קודי שגיאה)
- הגדרות `wp-config.php`

