# תרשים זרימה - מערכת הצגת שעות זמינות למרפאות

## תרשים זרימה כללי של המערכת

```mermaid
graph TB
    subgraph "משתמש"
        USER[משתמש]
        ADMIN[מנהל מערכת]
    end
    
    subgraph "WordPress"
        WP[WordPress Core]
        EL[Elementor]
        DB[(WordPress Database)]
    end
    
    subgraph "התוסף - Clinic Queue Management"
        subgraph "נקודת כניסה"
            MAIN[clinic-queue-management.php]
        end
        
        subgraph "ליבת המערכת"
            CORE[Plugin Core]
            DB_MGR[Database Manager]
            APPT_MGR[Appointment Manager]
        end
        
        subgraph "ממשק API"
            API[API Manager]
            MOCK[Mock Data JSON]
        end
        
        subgraph "ממשק ניהול"
            DASH[Dashboard]
            CAL[Calendars]
            SYNC[Sync Status]
            CRON[Cron Jobs]
        end
        
        subgraph "ממשק משתמש"
            WIDGET[Elementor Widget]
            SHORT[Shortcode]
            JS[JavaScript]
            CSS[CSS]
        end
        
        subgraph "API חיצוני"
            EXT_API[API חיצוני]
        end
    end
    
    subgraph "מערכות חיצוניות"
        EXT_API[API חיצוני]
    end
    
    %% חיבורים
    USER --> WIDGET
    USER --> SHORT
    ADMIN --> DASH
    ADMIN --> CAL
    ADMIN --> SYNC
    ADMIN --> CRON
    
    WP --> MAIN
    EL --> WIDGET
    MAIN --> CORE
    
    CORE --> DB_MGR
    CORE --> APPT_MGR
    CORE --> API
    CORE --> DASH
    CORE --> CAL
    CORE --> SYNC
    CORE --> CRON
    CORE --> WIDGET
    CORE --> SHORT
    
    API --> EXT_API
    
    WIDGET --> JS
    WIDGET --> CSS
    SHORT --> JS
    SHORT --> CSS
    
    WIDGET --> API
    SHORT --> API
```

## תרשים זרימת נתונים - תהליך הזמנת תור

```mermaid
sequenceDiagram
    participant U as משתמש
    participant W as Widget/Shortcode
    participant A as API Manager
    participant E as External API
    
    Note over U,E: תהליך הצגת שעות זמינות
    
    U->>W: ווידג'ט נטען בעמוד
    W->>A: בקשת נתוני תורים (עם מזהה יומן/רופא/מרפאה)
    A->>E: פנייה ישירה ל-API חיצוני
    E-->>A: החזרת נתוני תורים בזמן אמת
    A-->>W: החזרת נתוני תורים
    W-->>U: הצגת שעות זמינות
    
    Note over U,E: אין שמירה מקומית - כל נתונים מגיעים ישירות מה-API
```

## תרשים זרימת קבלת תורים (זרימה חדשה)

```mermaid
flowchart TD
    START[ווידג'ט נטען בעמוד]
    GET_ID[קבלת מזהה יומן/רופא/מרפאה]
    REQUEST[בקשת AJAX ל-API Manager]
    FETCH[פנייה ל-API חיצוני]
    VALIDATE[אימות תגובת API]
    DISPLAY[הצגת שעות זמינות]
    ERROR[טיפול בשגיאה]
    END[סיום]
    
    START --> GET_ID
    GET_ID --> REQUEST
    REQUEST --> FETCH
    FETCH --> VALIDATE
    VALIDATE -->|תקין| DISPLAY
    VALIDATE -->|לא תקין| ERROR
    DISPLAY --> END
    ERROR --> END
    
    Note1[אין שמירה מקומית]
    Note2[אין Cache]
    Note3[כל נתונים בזמן אמת]
```

## תרשים מבנה נתונים (API Response)

```mermaid
erDiagram
    API_RESPONSE {
        string calendar_id
        string doctor_id
        string clinic_id
    }
    
    AVAILABLE_SLOTS {
        date date
        array time_slots
    }
    
    TIME_SLOT {
        string time
        boolean available
    }
    
    API_RESPONSE ||--o{ AVAILABLE_SLOTS : "מכיל"
    AVAILABLE_SLOTS ||--o{ TIME_SLOT : "מכיל"
    
    Note1[אין מסד נתונים מקומי]
    Note2[כל נתונים מגיעים מה-API]
```

## תרשים ממשק משתמש - זרימת משתמש

```mermaid
graph TB
    subgraph "ממשק ניהול"
        MENU[תפריט ראשי]
        DASHBOARD[דשבורד]
        CALENDARS[ניהול לוחות שנה]
        SYNC[סטטוס סנכרון]
        CRON[משימות אוטומטיות]
    end
    
    subgraph "ממשק משתמש"
        WIDGET[ווידג'ט Elementor]
        SHORTCODE[Shortcode]
        CALENDAR_UI[תצוגת לוח שנה]
        TIME_SLOTS[בחירת שעות]
        BOOKING[טופס הזמנה]
    end
    
    subgraph "פעולות משתמש"
        SELECT_DOCTOR[בחירת רופא]
        SELECT_CLINIC[בחירת מרפאה]
        SELECT_DATE[בחירת תאריך]
        SELECT_TIME[בחירת שעה]
    end
    
    MENU --> DASHBOARD
    MENU --> CALENDARS
    MENU --> SYNC
    MENU --> CRON
    
    WIDGET --> CALENDAR_UI
    SHORTCODE --> CALENDAR_UI
    CALENDAR_UI --> TIME_SLOTS
    TIME_SLOTS --> BOOKING
    
    SELECT_DOCTOR --> SELECT_CLINIC
    SELECT_CLINIC --> SELECT_DATE
    SELECT_DATE --> SELECT_TIME
```

## תרשים זרימת AJAX (זרימה חדשה)

```mermaid
graph LR
    subgraph "Frontend"
        WIDGET[ווידג'ט/Shortcode]
        JS[JavaScript]
    end
    
    subgraph "Backend"
        AJAX[AJAX Handler]
        API[API Manager]
    end
    
    subgraph "External"
        EXT_API[API חיצוני]
    end
    
    WIDGET --> JS
    JS --> AJAX
    AJAX --> API
    API --> EXT_API
    EXT_API --> API
    API --> AJAX
    AJAX --> JS
    JS --> WIDGET
    
    Note1[כל קריאה היא בזמן אמת]
    Note2[אין שמירה מקומית]
```

## תרשים זרימת AJAX

```mermaid
sequenceDiagram
    participant F as Frontend
    participant A as AJAX Handler
    participant M as Manager Classes
    participant D as Database
    participant R as Response
    
    Note over F,R: תהליך AJAX
    
    F->>A: שליחת בקשת AJAX
    A->>A: בדיקת Nonce
    A->>A: בדיקת הרשאות
    A->>M: קריאה למנהל המתאים
    M->>D: שאילתת/עדכון בסיס נתונים
    D-->>M: החזרת תוצאות
    M-->>A: עיבוד תוצאות
    A->>R: הכנת תגובה JSON
    R-->>F: החזרת תגובה ל-Frontend
```

## תרשים זרימת נתונים (זרימה חדשה - ללא Cache)

```mermaid
graph TD
    subgraph "Frontend"
        WIDGET[ווידג'ט]
        REQUEST[בקשה]
    end
    
    subgraph "Backend"
        HANDLER[AJAX Handler]
        API_MGR[API Manager]
    end
    
    subgraph "External"
        EXT_API[API חיצוני]
    end
    
    WIDGET --> REQUEST
    REQUEST --> HANDLER
    HANDLER --> API_MGR
    API_MGR --> EXT_API
    EXT_API --> API_MGR
    API_MGR --> HANDLER
    HANDLER --> WIDGET
    
    Note1[אין Cache]
    Note2[אין שמירה מקומית]
    Note3[כל נתונים בזמן אמת]
```

## סיכום זרימת המערכת

### 1. אתחול המערכת
- טעינת התוסף ב-WordPress
- רישום AJAX handlers
- רישום REST API endpoints
- רישום ווידג'טים

### 2. זרימת משתמש (זרימה חדשה)
- משתמש נכנס לאתר
- ווידג'ט/Shortcode נטען בעמוד
- JavaScript פונה ל-API Manager עם מזהה יומן/רופא/מרפאה
- API Manager פונה ישירות ל-API החיצוני
- נתונים מוחזרים ומוצגים למשתמש
- אין שמירה מקומית - כל נתונים בזמן אמת

### 3. זרימת ניהול
- מנהל נכנס לממשק הניהול
- רואה דשבורד עם מידע כללי

### 4. זרימת ביצועים
- פנייה ישירה ל-API בכל טעינת ווידג'ט
- אין Cache - כל נתונים בזמן אמת
- ניטור ביצועים
- לוגים ושגיאות

המערכת מספקת פתרון מקיף לניהול תורים במרפאות עם ארכיטקטורה מודולרית, ממשק משתמש אינטואיטיבי, ותמיכה מלאה בעברית.


