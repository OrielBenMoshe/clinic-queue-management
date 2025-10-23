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
    
    subgraph "Data Layer"
        TABLES[Custom Tables]
        CACHE[Cache System]
        MOCK[Mock Data JSON]
    end
    
    subgraph "External Systems"
        EXT_API[External API]
        CRON_SYS[WordPress Cron]
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
    API_MGR --> MOCK
    API_MGR --> EXT_API
    API_MGR --> CACHE
    DB_MGR --> TABLES
    DB_MGR --> DB
    
    %% Frontend Dependencies
    WIDGET --> FIELDS
    SHORT --> FIELDS
    
    %% External Connections
    CRON --> CRON_SYS
    API_MGR --> EXT_API
```

## תרשים זרימת הצגת שעות זמינות מפורטת

```mermaid
sequenceDiagram
    participant U as משתמש
    participant W as Widget/Shortcode
    participant F as Fields Manager
    participant A as API Manager
    participant D as Database Manager
    participant DB as Database
    participant M as Mock Data
    
    Note over U,M: תהליך הצגת שעות זמינות מפורט
    
    U->>W: בחירת רופא ומרפאה
    W->>F: בקשת נתוני תורים
    F->>A: בקשת נתונים
    A->>D: בדיקת Cache
    D->>DB: שאילתת נתונים
    DB-->>D: החזרת נתונים
    D-->>A: בדיקת תוקף Cache
    
    alt Cache פג תוקף
        A->>M: קבלת נתונים מ-Mock
        M-->>A: החזרת נתונים
        A->>D: עדכון Cache
        D->>DB: שמירת נתונים
    end
    
    A-->>F: החזרת נתוני תורים
    F-->>W: עיבוד נתונים
    W-->>U: הצגת שעות זמינות
    
    U->>W: בחירת תאריך ושעה
    W-->>U: הצגת שעות זמינות
```

## תרשים זרימת סנכרון מפורטת

```mermaid
flowchart TD
    START[התחלת סנכרון]
    CHECK_CALENDAR{בדיקת קיום לוח שנה}
    CREATE_CALENDAR[יצירת לוח שנה חדש]
    CHECK_SYNC{בדיקת צורך בסנכרון}
    FETCH_DATA[קבלת נתונים מ-API]
    VALIDATE_DATA[אימות נתונים]
    PROCESS_DATA[עיבוד נתונים]
    UPDATE_DB[עדכון בסיס נתונים]
    UPDATE_CACHE[עדכון Cache]
    LOG_SYNC[רישום סטטוס סנכרון]
    END[סיום סנכרון]
    
    START --> CHECK_CALENDAR
    CHECK_CALENDAR -->|לא קיים| CREATE_CALENDAR
    CHECK_CALENDAR -->|קיים| CHECK_SYNC
    CREATE_CALENDAR --> CHECK_SYNC
    CHECK_SYNC -->|צריך סנכרון| FETCH_DATA
    CHECK_SYNC -->|לא צריך| END
    FETCH_DATA --> VALIDATE_DATA
    VALIDATE_DATA -->|תקין| PROCESS_DATA
    VALIDATE_DATA -->|לא תקין| LOG_SYNC
    PROCESS_DATA --> UPDATE_DB
    UPDATE_DB --> UPDATE_CACHE
    UPDATE_CACHE --> LOG_SYNC
    LOG_SYNC --> END
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

## תרשים זרימת Cron Jobs מפורטת

```mermaid
graph TD
    subgraph "WordPress Cron System"
        WP_CRON[WordPress Cron]
        SCHEDULE[תזמון משימות]
    end
    
    subgraph "Plugin Cron Jobs"
        AUTO_SYNC[סנכרון אוטומטי]
        CLEANUP[ניקוי נתונים ישנים]
        EXTEND[הארכת לוחות שנה]
        HEALTH[בדיקת בריאות מערכת]
    end
    
    subgraph "תדירות משימות"
        HOURLY[כל שעה]
        DAILY[יומי]
        WEEKLY[שבועי]
        MONTHLY[חודשי]
    end
    
    subgraph "פעולות"
        SYNC_DATA[סנכרון נתונים]
        DELETE_OLD[מחיקת נתונים ישנים]
        ADD_DATES[הוספת תאריכים]
        CHECK_HEALTH[בדיקת בריאות]
    end
    
    WP_CRON --> SCHEDULE
    SCHEDULE --> HOURLY
    SCHEDULE --> DAILY
    SCHEDULE --> WEEKLY
    SCHEDULE --> MONTHLY
    
    HOURLY --> AUTO_SYNC
    DAILY --> CLEANUP
    WEEKLY --> EXTEND
    MONTHLY --> HEALTH
    
    AUTO_SYNC --> SYNC_DATA
    CLEANUP --> DELETE_OLD
    EXTEND --> ADD_DATES
    HEALTH --> CHECK_HEALTH
```

## תרשים זרימת Cache מפורטת

```mermaid
graph TD
    subgraph "מערכת Cache"
        CHECK[בדיקת Cache]
        HIT[Cache Hit]
        MISS[Cache Miss]
        UPDATE[עדכון Cache]
        EXPIRE[פג תוקף]
        CLEAR[ניקוי Cache]
    end
    
    subgraph "מקורות נתונים"
        DB[בסיס נתונים]
        API[API חיצוני]
        MOCK[Mock Data]
        MEMORY[זיכרון]
    end
    
    subgraph "תדירות Cache"
        SHORT[30 דקות]
        MEDIUM[שעה]
        LONG[יום]
    end
    
    CHECK --> HIT
    CHECK --> MISS
    HIT --> RETURN[החזרת נתונים]
    MISS --> API
    API --> UPDATE
    UPDATE --> RETURN
    EXPIRE --> MISS
    CLEAR --> MISS
    
    SHORT --> EXPIRE
    MEDIUM --> EXPIRE
    LONG --> EXPIRE
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
- **יצירת טבלאות**: בדיקה ויצירת טבלאות בסיס נתונים
- **טעינת נתוני Mock**: אתחול נתונים מדמה
- **רישום Handlers**: רישום AJAX ו-REST API endpoints

### 2. זרימת משתמש מפורטת
- **כניסה לאתר**: משתמש נכנס לאתר WordPress
- **זיהוי ווידג'ט**: Elementor מזהה את הווידג'ט
- **טעינת נכסים**: טעינת CSS ו-JavaScript
- **בחירת פרמטרים**: משתמש בוחר רופא ומרפאה
- **בקשת נתונים**: AJAX request לנתוני תורים
- **עיבוד נתונים**: עיבוד והצגת נתונים
- **בחירת תור**: משתמש בוחר תאריך ושעה
- **הצגת שעות זמינות**: הצגת השעות הזמינות

### 3. זרימת ניהול מפורטת
- **כניסה לממשק ניהול**: מנהל נכנס לממשק הניהול
- **דשבורד**: הצגת סטטיסטיקות כלליות
- **ניהול לוחות שנה**: הוספה, עריכה, מחיקה של לוחות שנה
- **סטטוס סנכרון**: מעקב אחר סטטוס סנכרון
- **משימות אוטומטיות**: הגדרת Cron Jobs
- **ניטור ביצועים**: מעקב אחר ביצועי המערכת

### 4. זרימת סנכרון מפורטת
- **זיהוי צורך בסנכרון**: בדיקת תאריך עדכון אחרון
- **קבלת נתונים**: קריאה ל-API חיצוני או Mock Data
- **אימות נתונים**: בדיקת תקינות הנתונים
- **עיבוד נתונים**: המרת פורמט הנתונים
- **עדכון בסיס נתונים**: שמירת נתונים בטבלאות
- **עדכון Cache**: עדכון מערכת ה-Cache
- **רישום סטטוס**: רישום סטטוס הסנכרון

### 5. זרימת ביצועים מפורטת
- **מערכת Cache**: Cache של 30 דקות לנתונים
- **טעינה איטית**: טעינה איטית של נכסים
- **דחיסת קבצים**: דחיסת CSS ו-JavaScript
- **ניקוי נתונים**: ניקוי אוטומטי של נתונים ישנים
- **ניטור ביצועים**: מעקב אחר מדדי ביצועים
- **התראות**: התראות על בעיות ביצועים

המערכת מספקת פתרון מקיף ומפורט להצגת שעות זמינות במרפאות עם כל התכונות הנדרשות להצגה מקצועית ויעילה.


