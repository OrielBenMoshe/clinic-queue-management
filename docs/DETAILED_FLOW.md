# תרשים זרימה מפורט - מערכת הצגת שעות זמינות למרפאות

## תרשים זרימת נתונים מפורטת

```mermaid
graph TB
    subgraph "משתמש"
        USER[משתמש]
        ADMIN[מנהל]
    end
    
    subgraph "WordPress Environment"
        WP[WordPress Core]
        EL[Elementor]
        DB[(WordPress DB)]
    end
    
    subgraph "Plugin Entry Point"
        MAIN[clinic-queue-management.php]
        INIT[Plugin Initialization]
    end
    
    subgraph "Core System"
        CORE[Plugin Core]
        DB_MGR[Database Manager]
        APPT_MGR[Appointment Manager]
        API_MGR[API Manager]
    end
    
    subgraph "Admin Interface"
        DASH[Dashboard Admin]
        CAL[Calendars Admin]
        SYNC[Sync Status Admin]
        CRON[Cron Jobs Admin]
    end
    
    subgraph "Frontend Interface"
        WIDGET[Elementor Widget]
        SHORT[Shortcode Handler]
        FIELDS[Widget Fields Manager]
    end
    
    subgraph "External API"
        EXT_API[External API]
    end
    
    subgraph "External Systems"
        EXT_API[External API]
    end
    
    %% User Flows
    USER --> WIDGET
    USER --> SHORT
    ADMIN --> DASH
    ADMIN --> CAL
    ADMIN --> SYNC
    ADMIN --> CRON
    
    %% System Initialization
    WP --> MAIN
    MAIN --> INIT
    INIT --> CORE
    
    %% Core Dependencies
    CORE --> DB_MGR
    CORE --> APPT_MGR
    CORE --> API_MGR
    CORE --> DASH
    CORE --> CAL
    CORE --> SYNC
    CORE --> CRON
    CORE --> WIDGET
    CORE --> SHORT
    
    %% Data Flows
    API_MGR --> EXT_API
    WIDGET --> API_MGR
    SHORT --> API_MGR
    
    %% Frontend Dependencies
    WIDGET --> FIELDS
    SHORT --> FIELDS
    
    %% External Connections
    API_MGR --> EXT_API
```

## תרשים זרימת הצגת שעות זמינות מפורטת

```mermaid
sequenceDiagram
    participant U as משתמש
    participant W as Widget/Shortcode
    participant A as API Manager
    participant E as External API
    
    Note over U,E: תהליך הצגת שעות זמינות מפורט (זרימה חדשה)
    
    U->>W: ווידג'ט נטען בעמוד
    W->>A: בקשת נתוני תורים (עם מזהה יומן/רופא/מרפאה)
    A->>E: פנייה ישירה ל-API חיצוני
    E-->>A: החזרת נתוני תורים בזמן אמת
    A-->>W: החזרת נתוני תורים
    W-->>U: הצגת שעות זמינות
    
    Note over U,E: אין שמירה מקומית - כל נתונים מגיעים ישירות מה-API
```

## תרשים זרימת קבלת תורים מפורטת (זרימה חדשה)

```mermaid
flowchart TD
    START[ווידג'ט נטען בעמוד]
    GET_ID[קבלת מזהה יומן/רופא/מרפאה]
    AJAX_REQUEST[בקשת AJAX ל-API Manager]
    VALIDATE_REQUEST[אימות בקשה]
    FETCH_API[פנייה ל-API חיצוני]
    VALIDATE_RESPONSE[אימות תגובת API]
    PROCESS_DATA[עיבוד נתונים]
    DISPLAY[הצגת שעות זמינות]
    ERROR[טיפול בשגיאה]
    END[סיום]
    
    START --> GET_ID
    GET_ID --> AJAX_REQUEST
    AJAX_REQUEST --> VALIDATE_REQUEST
    VALIDATE_REQUEST -->|תקין| FETCH_API
    VALIDATE_REQUEST -->|לא תקין| ERROR
    FETCH_API --> VALIDATE_RESPONSE
    VALIDATE_RESPONSE -->|תקין| PROCESS_DATA
    VALIDATE_RESPONSE -->|לא תקין| ERROR
    PROCESS_DATA --> DISPLAY
    DISPLAY --> END
    ERROR --> END
    
    Note1[אין שמירה מקומית]
    Note2[אין Cache]
    Note3[כל נתונים בזמן אמת]
```

## תרשים זרימת AJAX מפורטת

```mermaid
sequenceDiagram
    participant F as Frontend JavaScript
    participant A as AJAX Handler
    participant V as Validation
    participant M as Manager Classes
    participant D as Database
    participant R as JSON Response
    
    Note over F,R: תהליך AJAX מפורט
    
    F->>A: שליחת בקשת AJAX
    A->>V: בדיקת Nonce
    V-->>A: אישור Nonce
    A->>V: בדיקת הרשאות משתמש
    V-->>A: אישור הרשאות
    A->>M: זיהוי מנהל מתאים
    M->>D: ביצוע פעולה בבסיס נתונים
    D-->>M: החזרת תוצאות
    M-->>A: עיבוד תוצאות
    A->>R: הכנת תגובה JSON
    R-->>F: החזרת תגובה
    F->>F: עדכון ממשק משתמש
```

## תרשים זרימת AJAX מפורטת (זרימה חדשה)

```mermaid
graph TD
    subgraph "Frontend"
        WIDGET[ווידג'ט/Shortcode]
        JS[JavaScript]
    end
    
    subgraph "Backend"
        AJAX[AJAX Handler]
        API_MGR[API Manager]
    end
    
    subgraph "External"
        EXT_API[API חיצוני]
    end
    
    WIDGET --> JS
    JS --> AJAX
    AJAX --> API_MGR
    API_MGR --> EXT_API
    EXT_API --> API_MGR
    API_MGR --> AJAX
    AJAX --> JS
    JS --> WIDGET
    
    Note1[כל קריאה היא בזמן אמת]
    Note2[אין שמירה מקומית]
    Note3[אין Cron Jobs]
```

## תרשים זרימת נתונים מפורטת (זרימה חדשה - ללא Cache)

```mermaid
graph TD
    subgraph "Frontend"
        WIDGET[ווידג'ט]
        JS[JavaScript]
        REQUEST[בקשה]
    end
    
    subgraph "Backend"
        AJAX[AJAX Handler]
        API_MGR[API Manager]
    end
    
    subgraph "External"
        EXT_API[API חיצוני]
    end
    
    WIDGET --> JS
    JS --> REQUEST
    REQUEST --> AJAX
    AJAX --> API_MGR
    API_MGR --> EXT_API
    EXT_API --> API_MGR
    API_MGR --> AJAX
    AJAX --> JS
    JS --> WIDGET
    
    Note1[אין Cache]
    Note2[אין שמירה מקומית]
    Note3[כל נתונים בזמן אמת]
```

## תרשים זרימת שגיאות

```mermaid
graph TD
    subgraph "זיהוי שגיאות"
        ERROR[זיהוי שגיאה]
        LOG[רישום שגיאה]
        NOTIFY[התראה]
    end
    
    subgraph "סוגי שגיאות"
        API_ERROR[שגיאת API]
        DB_ERROR[שגיאת בסיס נתונים]
        VALIDATION_ERROR[שגיאת אימות]
        PERMISSION_ERROR[שגיאת הרשאות]
    end
    
    subgraph "טיפול בשגיאות"
        RETRY[ניסיון חוזר]
        FALLBACK[גיבוי]
        USER_MESSAGE[הודעת משתמש]
        ADMIN_ALERT[התראת מנהל]
    end
    
    ERROR --> LOG
    LOG --> NOTIFY
    
    API_ERROR --> RETRY
    DB_ERROR --> FALLBACK
    VALIDATION_ERROR --> USER_MESSAGE
    PERMISSION_ERROR --> ADMIN_ALERT
    
    RETRY --> SUCCESS[הצלחה]
    FALLBACK --> SUCCESS
    USER_MESSAGE --> SUCCESS
    ADMIN_ALERT --> SUCCESS
```

## תרשים זרימת ביצועים

```mermaid
graph LR
    subgraph "אופטימיזציה"
        CACHE[מערכת Cache]
        LAZY[טעינה איטית]
        MINIFY[דחיסת קבצים]
        CDN[CDN]
    end
    
    subgraph "ניטור"
        METRICS[מדדי ביצועים]
        LOGS[לוגים]
        ALERTS[התראות]
    end
    
    subgraph "תחזוקה"
        CLEANUP[ניקוי נתונים]
        BACKUP[גיבוי]
        UPDATE[עדכונים]
    end
    
    CACHE --> LAZY
    LAZY --> MINIFY
    MINIFY --> CDN
    
    METRICS --> LOGS
    LOGS --> ALERTS
    
    CLEANUP --> BACKUP
    BACKUP --> UPDATE
```

## סיכום זרימת המערכת המפורטת

### 1. אתחול המערכת
- **טעינת התוסף**: WordPress טוען את הקובץ הראשי
- **אתחול Core**: יצירת instance של Plugin Core
- **טעינת תלויות**: טעינת כל המחלקות הנדרשות
- **רישום Handlers**: רישום AJAX ו-REST API endpoints
- **רישום ווידג'טים**: רישום ווידג'ט Elementor ו-Shortcode

### 2. זרימת משתמש מפורטת (זרימה חדשה)
- **כניסה לאתר**: משתמש נכנס לאתר WordPress
- **זיהוי ווידג'ט**: Elementor מזהה את הווידג'ט
- **טעינת נכסים**: טעינת CSS ו-JavaScript
- **טעינת ווידג'ט**: JavaScript מזהה את הווידג'ט בעמוד
- **קבלת מזהה**: JavaScript מקבל מזהה יומן/רופא/מרפאה מהווידג'ט
- **בקשת נתונים**: AJAX request ל-API Manager עם המזהה
- **פנייה ל-API**: API Manager פונה ישירות ל-API החיצוני
- **קבלת נתונים**: קבלת נתוני תורים בזמן אמת מה-API
- **עיבוד נתונים**: עיבוד והצגת נתונים
- **הצגת שעות זמינות**: הצגת השעות הזמינות למשתמש

### 3. זרימת ניהול מפורטת
- **כניסה לממשק ניהול**: מנהל נכנס לממשק הניהול
- **דשבורד**: הצגת מידע כללי על התוסף

### 4. זרימת ביצועים מפורטת (זרימה חדשה)
- **פנייה ישירה ל-API**: כל קריאה היא ישירה ל-API החיצוני
- **אין Cache**: אין שמירה מקומית של נתונים
- **טעינה איטית**: טעינה איטית של נכסים
- **דחיסת קבצים**: דחיסת CSS ו-JavaScript
- **ניטור ביצועים**: מעקב אחר מדדי ביצועים
- **התראות**: התראות על בעיות ביצועים

המערכת מספקת פתרון מקיף ומפורט להצגת שעות זמינות במרפאות עם כל התכונות הנדרשות להצגה מקצועית ויעילה.


