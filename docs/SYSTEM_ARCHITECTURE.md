# ארכיטקטורת המערכת - מערכת ניהול תורים למרפאות

## תרשים ארכיטקטורה כללי

```mermaid
graph TB
    subgraph "WordPress Environment"
        WP[WordPress Core]
        EL[Elementor Plugin]
        DB[(WordPress Database)]
        CRON[WordPress Cron]
    end
    
    subgraph "Plugin Architecture"
        subgraph "Entry Layer"
            MAIN[clinic-queue-management.php]
            INIT[Plugin Initialization]
        end
        
        subgraph "Core Layer"
            CORE[Plugin Core]
            DB_MGR[Database Manager]
            APPT_MGR[Appointment Manager]
        end
        
        subgraph "API Layer"
            API[API Manager]
            MOCK[Mock Data]
            CACHE[Cache System]
        end
        
        subgraph "Admin Layer"
            DASH[Dashboard]
            CAL[Calendars]
            SYNC[Sync Status]
            CRON_ADMIN[Cron Jobs]
        end
        
        subgraph "Frontend Layer"
            WIDGET[Elementor Widget]
            SHORT[Shortcode]
            FIELDS[Fields Manager]
        end
        
        subgraph "Data Layer"
            TABLES[Custom Tables]
            CACHE_DB[Cache Database]
        end
    end
    
    subgraph "External Systems"
        EXT_API[External API]
        CRON_SYS[System Cron]
    end
    
    %% Connections
    WP --> MAIN
    EL --> WIDGET
    MAIN --> INIT
    INIT --> CORE
    
    CORE --> DB_MGR
    CORE --> APPT_MGR
    CORE --> API
    CORE --> DASH
    CORE --> CAL
    CORE --> SYNC
    CORE --> CRON_ADMIN
    CORE --> WIDGET
    CORE --> SHORT
    
    API --> MOCK
    API --> CACHE
    API --> EXT_API
    
    DB_MGR --> TABLES
    DB_MGR --> DB
    
    WIDGET --> FIELDS
    SHORT --> FIELDS
    
    CRON --> CRON_SYS
    CRON_ADMIN --> CRON
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
        varchar patient_name
        varchar patient_phone
        text notes
        datetime created_at
        datetime updated_at
    }
    
    CALENDARS ||--o{ DATES : "has many"
    DATES ||--o{ TIMES : "has many"
```

## תרשים זרימת נתונים

```mermaid
sequenceDiagram
    participant U as User
    participant W as Widget
    participant A as API Manager
    participant D as Database
    participant E as External API
    
    U->>W: Select Doctor & Clinic
    W->>A: Request Appointments
    A->>D: Check Cache
    alt Cache Hit
        D-->>A: Return Cached Data
    else Cache Miss
        A->>E: Fetch from External API
        E-->>A: Return Data
        A->>D: Store in Cache
    end
    A-->>W: Return Appointments
    W-->>U: Display Available Slots
    
    U->>W: Select Time Slot
    W->>A: Book Appointment
    A->>D: Update Database
    D-->>A: Confirm Booking
    A-->>W: Booking Confirmed
    W-->>U: Show Confirmation
```

## תרשים זרימת סנכרון

```mermaid
flowchart TD
    START[Start Sync Process]
    CHECK{Check if Sync Needed}
    FETCH[Fetch from External API]
    VALIDATE[Validate Data]
    UPDATE[Update Database]
    CACHE[Update Cache]
    LOG[Log Sync Status]
    END[End Process]
    
    START --> CHECK
    CHECK -->|Yes| FETCH
    CHECK -->|No| END
    FETCH --> VALIDATE
    VALIDATE -->|Valid| UPDATE
    VALIDATE -->|Invalid| LOG
    UPDATE --> CACHE
    CACHE --> LOG
    LOG --> END
```

## תרשים ממשק משתמש

```mermaid
graph TB
    subgraph "Admin Interface"
        MENU[Main Menu]
        DASHBOARD[Dashboard]
        CALENDARS[Calendars Management]
        SYNC[Sync Status]
        CRON[Cron Jobs]
    end
    
    subgraph "Frontend Interface"
        WIDGET[Elementor Widget]
        SHORTCODE[Shortcode]
        CALENDAR_UI[Calendar View]
        TIME_SLOTS[Time Slots]
        BOOKING[Booking Form]
    end
    
    subgraph "User Actions"
        SELECT_DOCTOR[Select Doctor]
        SELECT_CLINIC[Select Clinic]
        SELECT_DATE[Select Date]
        SELECT_TIME[Select Time]
        CONFIRM[Confirm Booking]
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
    SELECT_TIME --> CONFIRM
```

## תרשים ביצועים ואופטימיזציה

```mermaid
graph LR
    subgraph "Performance Optimization"
        CACHE[Cache System]
        LAZY[Lazy Loading]
        MINIFY[Asset Minification]
        CDN[CDN Support]
    end
    
    subgraph "Monitoring"
        LOGS[Error Logs]
        METRICS[Performance Metrics]
        ALERTS[System Alerts]
    end
    
    subgraph "Maintenance"
        CLEANUP[Data Cleanup]
        BACKUP[Backup System]
        UPDATE[Auto Updates]
    end
    
    CACHE --> LAZY
    LAZY --> MINIFY
    MINIFY --> CDN
    
    LOGS --> METRICS
    METRICS --> ALERTS
    
    CLEANUP --> BACKUP
    BACKUP --> UPDATE
```

## תרשים אבטחה

```mermaid
graph TD
    subgraph "Security Layers"
        AUTH[Authentication]
        AUTHZ[Authorization]
        VALIDATION[Input Validation]
        SANITIZATION[Data Sanitization]
    end
    
    subgraph "Protection Mechanisms"
        NONCE[Nonce Verification]
        CSRF[CSRF Protection]
        SQL_INJECTION[SQL Injection Prevention]
        XSS[XSS Protection]
    end
    
    subgraph "Access Control"
        USER_ROLES[User Roles]
        PERMISSIONS[Permissions]
        CAPABILITIES[Capabilities]
    end
    
    AUTH --> AUTHZ
    AUTHZ --> VALIDATION
    VALIDATION --> SANITIZATION
    
    NONCE --> CSRF
    CSRF --> SQL_INJECTION
    SQL_INJECTION --> XSS
    
    USER_ROLES --> PERMISSIONS
    PERMISSIONS --> CAPABILITIES
```

## תרשים ניטור ולוגים

```mermaid
graph TD
    subgraph "Logging System"
        ERROR_LOG[Error Logs]
        DEBUG_LOG[Debug Logs]
        ACCESS_LOG[Access Logs]
        PERFORMANCE_LOG[Performance Logs]
    end
    
    subgraph "Monitoring"
        HEALTH_CHECK[Health Checks]
        METRICS[Performance Metrics]
        ALERTS[Alert System]
    end
    
    subgraph "Analytics"
        USAGE_STATS[Usage Statistics]
        ERROR_RATES[Error Rates]
        PERFORMANCE_STATS[Performance Statistics]
    end
    
    ERROR_LOG --> HEALTH_CHECK
    DEBUG_LOG --> METRICS
    ACCESS_LOG --> USAGE_STATS
    PERFORMANCE_LOG --> PERFORMANCE_STATS
    
    HEALTH_CHECK --> ALERTS
    METRICS --> ALERTS
    USAGE_STATS --> ALERTS
    PERFORMANCE_STATS --> ALERTS
```

## תרשים תחזוקה ועדכונים

```mermaid
graph TD
    subgraph "Maintenance Tasks"
        CLEANUP[Data Cleanup]
        BACKUP[Data Backup]
        OPTIMIZATION[Database Optimization]
        CACHE_CLEAR[Cache Clearing]
    end
    
    subgraph "Update Process"
        VERSION_CHECK[Version Check]
        DEPLOYMENT[Deployment]
        ROLLBACK[Rollback]
        TESTING[Testing]
    end
    
    subgraph "Health Monitoring"
        SYSTEM_HEALTH[System Health]
        PERFORMANCE[Performance Monitoring]
        ERROR_TRACKING[Error Tracking]
    end
    
    CLEANUP --> BACKUP
    BACKUP --> OPTIMIZATION
    OPTIMIZATION --> CACHE_CLEAR
    
    VERSION_CHECK --> TESTING
    TESTING --> DEPLOYMENT
    DEPLOYMENT --> ROLLBACK
    
    SYSTEM_HEALTH --> PERFORMANCE
    PERFORMANCE --> ERROR_TRACKING
```

## סיכום ארכיטקטורה

### 1. שכבות המערכת
- **שכבת כניסה**: נקודת כניסה ראשית וטעינת התוסף
- **שכבת ליבה**: מנהלים מרכזיים של המערכת
- **שכבת API**: ניהול תקשורת עם מערכות חיצוניות
- **שכבת ניהול**: ממשקי ניהול למנהלי המערכת
- **שכבת משתמש**: ממשקי משתמש לזמינות
- **שכבת נתונים**: ניהול בסיס נתונים ו-Cache

### 2. עקרונות ארכיטקטורה
- **מודולריות**: הפרדת אחריות בין רכיבים
- **גמישות**: תמיכה ב-Elementor ו-Shortcode
- **ביצועים**: מערכת Cache ואופטימיזציה
- **אבטחה**: הגנות רב-שכבתיות
- **ניטור**: מעקב אחר ביצועים ושגיאות
- **תחזוקה**: ניקוי אוטומטי ועדכונים

### 3. תכונות מתקדמות
- **Cache חכם**: Cache של 30 דקות עם ניקוי אוטומטי
- **סנכרון אוטומטי**: Cron Jobs לסנכרון תקופתי
- **ניטור ביצועים**: מעקב אחר מדדי ביצועים
- **תמיכה ב-RTL**: תמיכה מלאה בעברית
- **אינטגרציה**: תמיכה ב-Elementor ו-WordPress
- **הרחבה**: אפשרויות הרחבה עתידיות

המערכת מספקת ארכיטקטורה מודולרית וגמישה לניהול תורים במרפאות עם כל התכונות הנדרשות לניהול מקצועי ויעיל.


