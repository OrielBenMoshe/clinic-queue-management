# תרשים ארכיטקטורה - מערכת ניהול תורים למרפאות

## תרשים כללי של המערכת

```mermaid
graph TB
    subgraph "WordPress Environment"
        WP[WordPress Core]
        EL[Elementor Plugin]
        DB[(WordPress Database)]
    end
    
    subgraph "Clinic Queue Plugin"
        subgraph "Entry Point"
            MAIN[clinic-queue-management.php]
        end
        
        subgraph "Core Layer"
            CORE[Plugin Core]
            DB_MGR[Database Manager]
            APPT_MGR[Appointment Manager]
        end
        
        subgraph "API Layer"
            API[API Manager]
            MOCK[Mock Data JSON]
        end
        
        subgraph "Admin Layer"
            DASH[Dashboard]
            CAL[Calendars]
            SYNC[Sync Status]
            CRON[Cron Jobs]
        end
        
        subgraph "Frontend Layer"
            WIDGET[Elementor Widget]
            SHORT[Shortcode Handler]
            JS[JavaScript Manager]
            CSS[CSS Styles]
        end
        
        subgraph "Data Layer"
            TABLES[Custom Tables]
            CACHE[Cache System]
        end
    end
    
    subgraph "External Systems"
        EXT_API[External API]
        CRON_SYS[Cron System]
    end
    
    %% Connections
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

## תרשים זרימת נתונים

```mermaid
sequenceDiagram
    participant U as User
    participant W as Widget/Shortcode
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

## מסקנות מהתרשימים

1. **ארכיטקטורה מודולרית** - המערכת בנויה בשכבות ברורות עם הפרדת אחריות
2. **זרימת נתונים יעילה** - Cache system מונע קריאות מיותרות ל-API
3. **ממשק משתמש אינטואיטיבי** - זרימה לוגית מהבחירה עד להזמנה
4. **ניטור וביצועים** - מערכת מעקב אחר ביצועים ותחזוקה
5. **גמישות** - תמיכה ב-Elementor ו-Shortcode
6. **אבטחה** - בדיקות הרשאות וסניטיזציה בכל שכבה
