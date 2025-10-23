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
        
        subgraph "בסיס נתונים"
            TABLES[טבלאות מותאמות אישית]
            CACHE[מערכת Cache]
        end
    end
    
    subgraph "מערכות חיצוניות"
        EXT_API[API חיצוני]
        CRON_SYS[מערכת Cron]
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
    
    API --> MOCK
    API --> EXT_API
    API --> CACHE
    
    DB_MGR --> TABLES
    DB_MGR --> DB
    
    WIDGET --> JS
    WIDGET --> CSS
    SHORT --> JS
    SHORT --> CSS
    
    CRON --> CRON_SYS
    CRON --> API
```

## תרשים זרימת נתונים - תהליך הזמנת תור

```mermaid
sequenceDiagram
    participant U as משתמש
    participant W as Widget/Shortcode
    participant A as API Manager
    participant D as Database Manager
    participant DB as Database
    participant E as External API/Mock Data
    
    Note over U,E: תהליך הזמנת תור
    
    U->>W: בחירת רופא ומרפאה
    W->>A: בקשת נתוני תורים
    A->>D: בדיקת Cache
    alt Cache Hit
        D-->>A: החזרת נתונים מ-Cache
    else Cache Miss
        A->>E: קבלת נתונים מ-API חיצוני
        E-->>A: החזרת נתונים
        A->>D: שמירה ב-Cache
    end
    A-->>W: החזרת נתוני תורים
    W-->>U: הצגת שעות זמינות
    
    U->>W: בחירת תאריך ושעה
    W-->>U: הצגת שעות זמינות
```

## תרשים זרימת סנכרון נתונים

```mermaid
flowchart TD
    START[התחלת תהליך סנכרון]
    CHECK{בדיקת צורך בסנכרון}
    FETCH[קבלת נתונים מ-API חיצוני]
    VALIDATE[אימות נתונים]
    UPDATE[עדכון בסיס נתונים]
    CACHE[עדכון Cache]
    LOG[רישום סטטוס סנכרון]
    END[סיום תהליך]
    
    START --> CHECK
    CHECK -->|כן| FETCH
    CHECK -->|לא| END
    FETCH --> VALIDATE
    VALIDATE -->|תקין| UPDATE
    VALIDATE -->|לא תקין| LOG
    UPDATE --> CACHE
    CACHE --> LOG
    LOG --> END
```

## תרשים מבנה בסיס הנתונים

```mermaid
erDiagram
    CALENDARS {
        int id PK
        varchar doctor_id
        varchar clinic_id
        varchar treatment_type
        varchar calendar_name
        datetime last_updated
        datetime created_at
    }
    
    DATES {
        int id PK
        int calendar_id FK
        date appointment_date
        datetime created_at
    }
    
    TIMES {
        int id PK
        int date_id FK
        time time_slot
        tinyint is_booked
        datetime created_at
        datetime updated_at
    }
    
    CALENDARS ||--o{ DATES : "יש לו"
    DATES ||--o{ TIMES : "יש לו"
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

## תרשים זרימת Cron Jobs

```mermaid
graph LR
    subgraph "משימות אוטומטיות"
        AUTO_SYNC[סנכרון אוטומטי]
        CLEANUP[ניקוי נתונים ישנים]
        EXTEND[הארכת לוחות שנה]
    end
    
    subgraph "תדירות"
        HOURLY[כל שעה]
        DAILY[יומי]
        WEEKLY[שבועי]
    end
    
    subgraph "פעולות"
        SYNC_DATA[סנכרון נתונים]
        DELETE_OLD[מחיקת נתונים ישנים]
        ADD_DATES[הוספת תאריכים חדשים]
    end
    
    HOURLY --> AUTO_SYNC
    DAILY --> CLEANUP
    WEEKLY --> EXTEND
    
    AUTO_SYNC --> SYNC_DATA
    CLEANUP --> DELETE_OLD
    EXTEND --> ADD_DATES
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

## תרשים זרימת Cache

```mermaid
graph TD
    subgraph "מערכת Cache"
        CHECK[בדיקת Cache]
        HIT[Cache Hit]
        MISS[Cache Miss]
        UPDATE[עדכון Cache]
        EXPIRE[פג תוקף Cache]
    end
    
    subgraph "מקורות נתונים"
        DB[בסיס נתונים]
        API[API חיצוני]
        MOCK[Mock Data]
    end
    
    CHECK --> HIT
    CHECK --> MISS
    HIT --> RETURN[החזרת נתונים]
    MISS --> API
    API --> UPDATE
    UPDATE --> RETURN
    EXPIRE --> MISS
```

## סיכום זרימת המערכת

### 1. אתחול המערכת
- טעינת התוסף ב-WordPress
- יצירת טבלאות בסיס נתונים
- טעינת נתוני Mock
- רישום AJAX handlers
- רישום REST API endpoints

### 2. זרימת משתמש
- משתמש נכנס לאתר
- רואה ווידג'ט או Shortcode
- בוחר רופא ומרפאה
- רואה תאריכים ושעות זמינות
- בוחר תאריך ושעה

### 3. זרימת ניהול
- מנהל נכנס לממשק הניהול
- רואה דשבורד עם סטטיסטיקות
- מנהל לוחות שנה
- עוקב אחר סטטוס סנכרון
- מגדיר משימות אוטומטיות

### 4. זרימת סנכרון
- בדיקת צורך בסנכרון
- קבלת נתונים מ-API חיצוני
- אימות ועיבוד נתונים
- עדכון בסיס נתונים
- עדכון Cache
- רישום סטטוס

### 5. זרימת ביצועים
- Cache של 30 דקות
- ניקוי אוטומטי של נתונים ישנים
- סנכרון תקופתי
- ניטור ביצועים
- לוגים ושגיאות

המערכת מספקת פתרון מקיף לניהול תורים במרפאות עם ארכיטקטורה מודולרית, ממשק משתמש אינטואיטיבי, ותמיכה מלאה בעברית.


