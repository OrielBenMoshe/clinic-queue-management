# תרשים ארכיטקטורה - מערכת הצגת שעות זמינות למרפאות

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
        
        subgraph "External API"
            EXT_API[External API]
        end
    end
    
    subgraph "External Systems"
        EXT_API[External API]
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
    
    API --> EXT_API
    WIDGET --> API
    SHORT --> API
    
    WIDGET --> JS
    WIDGET --> CSS
    SHORT --> JS
    SHORT --> CSS
    
```

## תרשים זרימת נתונים

```mermaid
sequenceDiagram
    participant U as User
    participant W as Widget/Shortcode
    participant A as API Manager
    participant E as External API
    
    U->>W: Widget Loaded on Page
    W->>A: Request Appointments (with calendar_id/doctor_id/clinic_id)
    A->>E: Direct Request to External API
    E-->>A: Return Real-time Data
    A-->>W: Return Appointments
    W-->>U: Display Available Slots
    
    Note over U,E: No local storage - all data comes directly from API
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
    
    API_RESPONSE ||--o{ AVAILABLE_SLOTS : "contains"
    AVAILABLE_SLOTS ||--o{ TIME_SLOT : "contains"
    
    Note1[No local database]
    Note2[All data from API]
```

## תרשים זרימת קבלת תורים (זרימה חדשה)

```mermaid
flowchart TD
    START[Widget Loaded on Page]
    GET_ID[Get calendar_id/doctor_id/clinic_id]
    REQUEST[AJAX Request to API Manager]
    FETCH[Request to External API]
    VALIDATE[Validate API Response]
    DISPLAY[Display Available Slots]
    ERROR[Handle Error]
    END[End]
    
    START --> GET_ID
    GET_ID --> REQUEST
    REQUEST --> FETCH
    FETCH --> VALIDATE
    VALIDATE -->|Valid| DISPLAY
    VALIDATE -->|Invalid| ERROR
    DISPLAY --> END
    ERROR --> END
    
    Note1[No local storage]
    Note2[No Cache]
    Note3[All data in real-time]
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
