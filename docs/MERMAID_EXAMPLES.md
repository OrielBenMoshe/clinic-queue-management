# דוגמאות קוד Mermaid להצגה גרפית

## 1. תרשים זרימה פשוט - הזמנת תור

```mermaid
graph TD
    A[משתמש נכנס לאתר] --> B[רואה ווידג'ט תורים]
    B --> C[בוחר רופא ומרפאה]
    C --> D[רואה תאריכים זמינים]
    D --> E[בוחר תאריך ושעה]
    E --> F[מזמין תור]
    F --> G[מקבל אישור הזמנה]
    
    style A fill:#e1f5fe
    style G fill:#e8f5e8
    style F fill:#fff3e0
```

## 2. תרשים זרימת נתונים - תהליך AJAX

```mermaid
sequenceDiagram
    participant U as משתמש
    participant W as ווידג'ט
    participant S as שרת
    participant D as בסיס נתונים
    
    U->>W: בוחר רופא
    W->>S: מבקש תורים
    S->>D: שולף נתונים
    D-->>S: מחזיר תורים
    S-->>W: שולח תורים
    W-->>U: מציג תורים
    
    U->>W: בוחר תור
    W->>S: שולח הזמנה
    S->>D: שומר הזמנה
    D-->>S: מאשר שמירה
    S-->>W: מאשר הזמנה
    W-->>U: מציג אישור
```

## 3. תרשים ארכיטקטורה - מבנה המערכת

```mermaid
graph TB
    subgraph "משתמשים"
        USER[משתמש רגיל]
        ADMIN[מנהל מערכת]
    end
    
    subgraph "WordPress"
        WP[WordPress Core]
        EL[Elementor]
        DB[(WordPress Database)]
    end
    
    subgraph "התוסף"
        WIDGET[ווידג'ט Elementor]
        SHORTCODE[Shortcode]
        DASHBOARD[דשבורד ניהול]
        DATABASE[בסיס נתונים]
    end
    
    USER --> WIDGET
    USER --> SHORTCODE
    ADMIN --> DASHBOARD
    
    WP --> WIDGET
    EL --> WIDGET
    
    WIDGET --> DATABASE
    SHORTCODE --> DATABASE
    DASHBOARD --> DATABASE
    
    DATABASE --> DB
    
    classDef userClass fill:#e1f5fe
    classDef systemClass fill:#f3e5f5
    classDef dataClass fill:#e8f5e8
    
    class USER,ADMIN userClass
    class WIDGET,SHORTCODE,DASHBOARD systemClass
    class DATABASE,DB dataClass
```

## 4. תרשים זרימת סנכרון

```mermaid
flowchart TD
    START[התחלת סנכרון]
    CHECK{בדיקת צורך בסנכרון}
    FETCH[קבלת נתונים מ-API]
    VALIDATE[אימות נתונים]
    UPDATE[עדכון בסיס נתונים]
    CACHE[עדכון Cache]
    LOG[רישום סטטוס סנכרון]
    END[סיום סנכרון]
    
    START --> CHECK
    CHECK -->|כן| FETCH
    CHECK -->|לא| END
    FETCH --> VALIDATE
    VALIDATE -->|תקין| UPDATE
    VALIDATE -->|לא תקין| LOG
    UPDATE --> CACHE
    CACHE --> LOG
    LOG --> END
    
    style START fill:#e1f5fe
    style END fill:#e8f5e8
    style CHECK fill:#fff3e0
    style LOG fill:#ffebee
```

## 5. תרשים מבנה בסיס נתונים

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
        varchar patient_name
        varchar patient_phone
        text notes
        datetime created_at
        datetime updated_at
    }
    
    CALENDARS ||--o{ DATES : "has many"
    DATES ||--o{ TIMES : "has many"
```

## 6. תרשים זרימת Cron Jobs

```mermaid
graph TD
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
    
    style AUTO_SYNC fill:#e1f5fe
    style CLEANUP fill:#fff3e0
    style EXTEND fill:#e8f5e8
```

## 7. תרשים זרימת Cache

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
    
    style HIT fill:#e8f5e8
    style MISS fill:#ffebee
    style RETURN fill:#e1f5fe
```

## 8. תרשים זרימת AJAX מפורטת

```mermaid
sequenceDiagram
    participant F as Frontend
    participant A as AJAX Handler
    participant V as Validation
    participant M as Manager Classes
    participant D as Database
    participant R as JSON Response
    
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

## 9. תרשים זרימת שגיאות

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
    
    style ERROR fill:#ffebee
    style SUCCESS fill:#e8f5e8
    style RETRY fill:#fff3e0
```

## 10. תרשים זרימת ביצועים

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
    
    style CACHE fill:#e1f5fe
    style METRICS fill:#f3e5f5
    style CLEANUP fill:#e8f5e8
```

## איך להשתמש בדוגמאות

### 1. העתק את הקוד
העתק את הקוד Mermaid (החלק בין ```mermaid ו-```)

### 2. הצג ב-Mermaid Live Editor
1. לך לאתר: https://mermaid.live/
2. הדבק את הקוד
3. התרשים יוצג אוטומטית

### 3. ייצא כתמונה
1. לחץ על "Actions" → "Download PNG"
2. או "Download SVG"

### 4. השתמש בתיעוד
העתק את התמונה לתיעוד, מצגות, או כל מטרה אחרת

## טיפים לעיצוב טוב

### 1. השתמש בצבעים
```mermaid
graph TD
    A[משתמש] --> B[ווידג'ט]
    B --> C[בסיס נתונים]
    
    classDef userClass fill:#e1f5fe
    classDef systemClass fill:#f3e5f5
    classDef dataClass fill:#e8f5e8
    
    class A userClass
    class B systemClass
    class C dataClass
```

### 2. השתמש בחצים שונים
```mermaid
graph TD
    A --> B
    A -.-> C
    A ==> D
    A -->|נתונים| E
```

### 3. השתמש בצורות שונות
```mermaid
graph TD
    A[מלבן]
    B(עיגול)
    C{יהלום}
    D((עיגול כפול))
    E>חץ]
    F{{עיגול עם קווים}}
```

## סיכום

הדוגמאות האלה נותנות לך תרשימים מקצועיים ויפים שתוכל להשתמש בהם:
- **תיעוד** - הסבר איך המערכת עובדת
- **מצגות** - הצגת המערכת ללקוחות
- **הדרכה** - הסבר למפתחים חדשים
- **תכנון** - תכנון פיתוח עתידי

כל התרשימים כתובים בפורמט Mermaid וניתן להציג אותם בקלות בכל פלטפורמה שתומכת ב-Mermaid.


