<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">מדריך שימוש - מערכת ניהול מרפאות</h1>
    <hr class="wp-header-end">
    
    <div class="clinic-queue-help-content" style="max-width: 1200px; margin: 20px 0;">
        
        <!-- Introduction -->
        <div class="help-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2>📋 מבוא</h2>
            <p>הווידג'ט <strong>יומן קביעת תורים</strong> מאפשר למשתמשים לבחור רופא, מרפאה וסוג טיפול, ולצפות בשעות זמינות לקביעת תור.</p>
            <p>הווידג'ט פועל עם <strong>פנייה ישירה ל-API חיצוני</strong> - כל הנתונים מגיעים בזמן אמת ללא שמירה מקומית.</p>
        </div>
        
        <!-- Adding Widget to Elementor -->
        <div class="help-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2>➕ הוספת הווידג'ט בעמוד</h2>
            <ol style="line-height: 2;">
                <li>ערוך עמוד ב-<strong>Elementor</strong></li>
                <li>גרור את הווידג'ט <strong>"יומן קביעת תורים"</strong> מהפאנל השמאלי</li>
                <li>שחרר את הווידג'ט באזור הרצוי בעמוד</li>
                <li>הגדר את ההגדרות ב-<strong>Content</strong> ו-<strong>Style</strong></li>
            </ol>
        </div>
        
        <!-- Widget Settings -->
        <div class="help-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2>⚙️ הגדרות הווידג'ט</h2>
            
            <h3>1. מצב בחירה (Selection Mode)</h3>
            <ul style="line-height: 2;">
                <li><strong>בחירת רופא (Doctor Mode):</strong> המשתמש בוחר רופא, המרפאה קבועה מראש</li>
                <li><strong>בחירת מרפאה (Clinic Mode):</strong> המשתמש בוחר מרפאה, הרופא קבוע מראש</li>
            </ul>
            
            <h3>2. הגדרת רופא ספציפי (Specific Doctor)</h3>
            <p>כאשר <strong>Selection Mode = Doctor</strong>:</p>
            <ul style="line-height: 2;">
                <li>בחר את הרופא הספציפי מהרשימה</li>
                <li>המרפאה תהיה קבועה לפי ההגדרה ב-<strong>Specific Clinic</strong></li>
            </ul>
            
            <h3>3. הגדרת מרפאה ספציפית (Specific Clinic)</h3>
            <p>כאשר <strong>Selection Mode = Clinic</strong>:</p>
            <ul style="line-height: 2;">
                <li>בחר את המרפאה הספציפית מהרשימה</li>
                <li>הרופא יהיה קבוע לפי ההגדרה ב-<strong>Specific Doctor</strong></li>
            </ul>
            
            <h3>4. הגדרת סוג טיפול (Treatment Type)</h3>
            <ul style="line-height: 2;">
                <li><strong>Use Specific Treatment = No:</strong> המשתמש יכול לבחור סוג טיפול</li>
                <li><strong>Use Specific Treatment = Yes:</strong> סוג הטיפול קבוע מראש</li>
                <li>בחר את סוג הטיפול מהרשימה (רפואה כללית, קרדיולוגיה, וכו')</li>
            </ul>
        </div>
        
        <!-- Widget Features -->
        <div class="help-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2>✨ תכונות הווידג'ט</h2>
            
            <h3>📅 בחירת תאריך</h3>
            <ul style="line-height: 2;">
                <li>הווידג'ט מציג את 6 הימים הקרובים עם תורים זמינים</li>
                <li>לחץ על תאריך כדי לראות את השעות הזמינות</li>
                <li>תאריכים עם תורים זמינים מסומנים בצבע</li>
            </ul>
            
            <h3>⏰ בחירת שעה</h3>
            <ul style="line-height: 2;">
                <li>לאחר בחירת תאריך, מוצגות השעות הזמינות</li>
                <li>שעות תפוסות מוצגות באפור ולא ניתן לבחור בהן</li>
                <li>שעות זמינות מוצגות בכחול וניתן לבחור בהן</li>
            </ul>
            
            <h3>🔄 עדכון דינמי</h3>
            <ul style="line-height: 2;">
                <li>כאשר המשתמש משנה את הבחירות (רופא/מרפאה/טיפול), הנתונים מתעדכנים אוטומטית</li>
                <li>הנתונים נטענים ישירות מה-API החיצוני</li>
                <li>אין צורך ברענון העמוד</li>
            </ul>
        </div>
        
        <!-- Using Shortcode -->
        <div class="help-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2>📝 שימוש ב-Shortcode</h2>
            <p>ניתן להשתמש בווידג'ט גם באמצעות Shortcode:</p>
            
            <h3>דוגמה בסיסית:</h3>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>[clinic_queue_widget]</code></pre>
            
            <h3>עם פרמטרים:</h3>
            <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>[clinic_queue_widget selection_mode="doctor" specific_doctor_id="1" specific_clinic_id="1" use_specific_treatment="no"]</code></pre>
            
            <h3>פרמטרים זמינים:</h3>
            <ul style="line-height: 2;">
                <li><strong>selection_mode:</strong> "doctor" או "clinic"</li>
                <li><strong>specific_doctor_id:</strong> מזהה הרופא</li>
                <li><strong>specific_clinic_id:</strong> מזהה המרפאה</li>
                <li><strong>use_specific_treatment:</strong> "yes" או "no"</li>
                <li><strong>specific_treatment_type:</strong> סוג הטיפול (כאשר use_specific_treatment="yes")</li>
            </ul>
        </div>
        
        <!-- API Integration -->
        <div class="help-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2>🔌 אינטגרציה עם API</h2>
            <p>הווידג'ט פועל עם <strong>פנייה ישירה ל-API חיצוני</strong>:</p>
            
            <h3>איך זה עובד:</h3>
            <ol style="line-height: 2;">
                <li>כאשר הווידג'ט נטען בעמוד, הוא שולח בקשה ל-API עם המזהה של היומן/רופא/מרפאה</li>
                <li>ה-API מחזיר את התורים הזמינים בזמן אמת</li>
                <li>הנתונים מוצגים למשתמש ללא שמירה מקומית</li>
                <li>כל שינוי בבחירות (רופא/מרפאה/טיפול) גורם לבקשה חדשה ל-API</li>
            </ol>
            
            <h3>הגדרת API Endpoint:</h3>
            <p>ניתן להגדיר את כתובת ה-API באמצעות:</p>
            <ul style="line-height: 2;">
                <li><strong>Constant:</strong> <code>define('CLINIC_QUEUE_API_ENDPOINT', 'https://your-api.com/endpoint');</code></li>
                <li><strong>Filter:</strong> <code>apply_filters('clinic_queue_api_endpoint', null);</code></li>
            </ul>
            
            <p><strong>הערה:</strong> אם לא הוגדר API endpoint, הווידג'ט ישתמש בנתוני Mock (לפיתוח).</p>
        </div>
        
        <!-- Styling -->
        <div class="help-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2>🎨 עיצוב הווידג'ט</h2>
            <p>ניתן להתאים את העיצוב של הווידג'ט ב-<strong>Style</strong> ב-Elementor:</p>
            
            <h3>אפשרויות עיצוב:</h3>
            <ul style="line-height: 2;">
                <li><strong>Typography:</strong> גופן, גודל, צבע של הטקסטים</li>
                <li><strong>Colors:</strong> צבעי רקע, גבולות, כפתורים</li>
                <li><strong>Spacing:</strong> ריווחים, מרווחים פנימיים וחיצוניים</li>
                <li><strong>Border:</strong> גבולות, פינות מעוגלות</li>
            </ul>
        </div>
        
        <!-- Troubleshooting -->
        <div class="help-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2>🔧 פתרון בעיות</h2>
            
            <h3>הווידג'ט לא מציג תורים:</h3>
            <ul style="line-height: 2;">
                <li>ודא שה-API endpoint מוגדר נכון</li>
                <li>בדוק את הקונסול בדפדפן לשגיאות JavaScript</li>
                <li>ודא שהמזהה של הרופא/מרפאה תקין</li>
                <li>בדוק את הרשת (Network) בדפדפן אם הבקשות ל-API מגיעות</li>
            </ul>
            
            <h3>השדות לא מתעדכנים:</h3>
            <ul style="line-height: 2;">
                <li>ודא שה-AJAX handlers רשומים נכון</li>
                <li>בדוק את ה-Nonce ב-AJAX requests</li>
                <li>ודא שה-JavaScript נטען כראוי</li>
            </ul>
            
            <h3>העיצוב לא נראה נכון:</h3>
            <ul style="line-height: 2;">
                <li>ודא שה-CSS נטען</li>
                <li>בדוק אם יש קונפליקטים עם עיצובים אחרים</li>
                <li>נסה לנקות את ה-Cache</li>
            </ul>
        </div>
        
        <!-- Support -->
        <div class="help-section" style="background: #f0f8ff; padding: 20px; margin-bottom: 20px; border: 2px solid #4a90e2; border-radius: 8px;">
            <h2>💬 תמיכה</h2>
            <p>אם נתקלת בבעיה או יש לך שאלה, אנא פנה לתמיכה הטכנית.</p>
            <p><strong>גרסת התוסף:</strong> <?php echo CLINIC_QUEUE_MANAGEMENT_VERSION; ?></p>
        </div>
        
    </div>
</div>

<style>
.clinic-queue-help-content h2 {
    color: #23282d;
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #0073aa;
}

.clinic-queue-help-content h3 {
    color: #0073aa;
    margin-top: 20px;
}

.clinic-queue-help-content pre {
    font-family: 'Courier New', monospace;
    font-size: 14px;
}

.clinic-queue-help-content code {
    background: #f5f5f5;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}

.clinic-queue-help-content ul,
.clinic-queue-help-content ol {
    margin-left: 20px;
}
</style>

