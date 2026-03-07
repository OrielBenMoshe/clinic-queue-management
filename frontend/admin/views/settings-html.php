<?php
/**
 * Settings Page Template
 * 
 * @package ClinicQueue
 * @subpackage Admin\Views
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract variables from handler
$updated = isset($updated) ? $updated : false;
$has_token = isset($has_token) ? $has_token : false;
$api_endpoint = isset($api_endpoint) ? $api_endpoint : 'https://do-proxy-staging.doctor-clinix.com';
$encryption_available = isset($encryption_available) ? $encryption_available : false;
?>

<div class="wrap clinic-settings-wrap">
    <div class="clinic-settings-header">
        <h1>
            <span class="dashicons dashicons-admin-settings"></span>
            הגדרות מערכת ניהול מרפאות
        </h1>
        <p class="description">ניהול הגדרות API, אבטחה ותצורות המערכת</p>
    </div>
    
    <?php if ($updated): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✓ ההגדרות נשמרו בהצלחה!</strong></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>✗ שגיאה בשמירת ההגדרות</strong></p>
            <?php if (isset($_GET['error_message'])): ?>
                <p><?php echo esc_html(urldecode($_GET['error_message'])); ?></p>
            <?php else: ?>
                <p>אירעה שגיאה בעת שמירת ההגדרות. בדוק את לוג השגיאות לפרטים נוספים.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php 
    // Check for transient error messages
    $token_error = get_transient('clinic_queue_token_save_error');
    if ($token_error): ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>✗ שגיאה בשמירת הטוקן:</strong></p>
            <p><?php echo esc_html($token_error); ?></p>
        </div>
        <?php delete_transient('clinic_queue_token_save_error'); ?>
    <?php endif; ?>
    
    <?php 
    // Debug: Check if form was submitted and handler was called
    $handler_called = get_transient('clinic_queue_handler_called');
    $exit_reason = get_transient('clinic_queue_handler_exit_reason');
    
    // Check if form was submitted (check for 'action' field, not 'clinic_queue_save_settings')
    $form_submitted = (isset($_POST['action']) && $_POST['action'] === 'clinic_queue_save_settings') || 
                      (isset($_REQUEST['action']) && $_REQUEST['action'] === 'clinic_queue_save_settings');
    
    if ($form_submitted) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>🔍 דיבוג:</strong> הטופס נשלח! POST keys: ' . implode(', ', array_keys($_POST)) . '</p>';
        echo '<p>action field: ' . (isset($_POST['action']) ? esc_html($_POST['action']) : 'לא קיים') . '</p>';
        echo '<p>clinic_queue_api_token קיים: ' . (isset($_POST['clinic_queue_api_token']) ? '✓ כן (אורך: ' . strlen($_POST['clinic_queue_api_token']) . ')' : '✗ לא') . '</p>';
        echo '<p>clinic_queue_settings_nonce קיים: ' . (isset($_POST['clinic_queue_settings_nonce']) ? '✓ כן' : '✗ לא') . '</p>';
        echo '<p>handle_form_submission() נקרא: ' . ($handler_called === 'yes' ? '✓ כן' : '✗ לא') . '</p>';
        if ($exit_reason) {
            echo '<p>סיבת יציאה: ' . esc_html($exit_reason) . '</p>';
        }
        echo '</div>';
        
        // Clear transients
        delete_transient('clinic_queue_handler_called');
        delete_transient('clinic_queue_handler_exit_reason');
    }
    ?>
    
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="clinic-queue-settings-form" class="clinic-queue-settings-form">
        <input type="hidden" name="action" value="clinic_queue_save_settings" />
        <?php wp_nonce_field('clinic_queue_save_settings', 'clinic_queue_settings_nonce'); ?>
        
        <div class="clinic-queue-settings-container">
            
            <!-- API Configuration Card -->
            <div class="clinic-settings-card">
                <div class="card-header">
                    <span class="dashicons dashicons-admin-network"></span>
                    <h2>הגדרות API</h2>
                </div>
                
                <div class="card-body">
                    <!-- API Token -->
                    <div class="settings-group">
                        <label class="settings-label">
                            <span class="dashicons dashicons-lock"></span>
                            טוקן אימות API
                        </label>
                        
                        <?php $handler->render_token_field(); ?>
                    </div>
                    
                    <div class="settings-divider"></div>
                    
                    <!-- API Endpoint -->
                    <div class="settings-group">
                        <label class="settings-label">
                            <span class="dashicons dashicons-cloud"></span>
                            כתובת שרת API DoctorOnline Proxy
                        </label>
                        
                        <?php $handler->render_endpoint_field(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Security Guidelines Card -->
            <div class="clinic-settings-card security-card">
                <div class="card-header">
                    <span class="dashicons dashicons-shield"></span>
                    <h2>הנחיות אבטחה</h2>
                </div>
                
                <div class="card-body">
                    <div class="security-guidelines">
                        <div class="guideline-item">
                            <span class="guideline-icon">🔒</span>
                            <div>
                                <strong>הצפנה:</strong>
                                <p>הטוקן נשמר מוצפן במסד הנתונים באמצעות AES-256-CBC</p>
                            </div>
                        </div>
                
                        <div class="guideline-item">
                            <span class="guideline-icon">⚠️</span>
                            <div>
                                <strong>שיתוף:</strong>
                                <p>אל תשתף את הטוקן עם אחרים או תשמור אותו בקבצים שנגישים לציבור</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Information Card -->
            <div class="clinic-settings-card info-card">
                <div class="card-header">
                    <span class="dashicons dashicons-info"></span>
                    <h2>מידע מערכת</h2>
                </div>
                
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">גרסת תוסף:</span>
                            <span class="info-value"><?php echo esc_html(CLINIC_QUEUE_MANAGEMENT_VERSION); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">סטטוס טוקן:</span>
                            <span class="info-value <?php echo $has_token ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $has_token ? '✓ מוגדר ומוצפן' : '⚠ לא מוגדר'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">שרת API:</span>
                            <span class="info-value"><?php echo esc_html(parse_url($api_endpoint, PHP_URL_HOST)); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">הצפנה:</span>
                            <span class="info-value status-active">
                                <?php echo $encryption_available ? '✓ AES-256-CBC' : '⚠ Base64 Only'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Token Debug Information Card (Testing Only) -->
            <?php 
            // Make sure token_debug_info is available
            $token_debug_info = isset($token_debug_info) && is_array($token_debug_info) ? $token_debug_info : array();
            // Debug: Check if variable exists
            $debug_var_exists = isset($token_debug_info);
            $debug_is_array = is_array($token_debug_info);
            $debug_not_empty = !empty($token_debug_info);
            // Always show debug card for testing
            if (true): 
            ?>
            <div class="clinic-settings-card debug-card" style="border-left: 4px solid #f0b849; background: #fff8e5; margin-top: 20px;">
                <div class="card-header">
                    <span class="dashicons dashicons-search" style="color: #f0b849;"></span>
                    <h2 style="color:rgb(255, 255, 255);">מידע בדיקה - טוקן API (לבדיקות בלבד)</h2>
                </div>
                
                <div class="card-body">
                    <!-- Debug Info -->
                    <div style="background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #e0e0e0; margin-bottom: 16px;">
                        <strong>דיבוג משתנים:</strong>
                        <ul style="margin: 8px 0 0 20px; padding: 0;">
                            <li>משתנה קיים: <?php echo $debug_var_exists ? '✓ כן' : '✗ לא'; ?></li>
                            <li>זה מערך: <?php echo $debug_is_array ? '✓ כן' : '✗ לא'; ?></li>
                            <li>לא ריק: <?php echo $debug_not_empty ? '✓ כן' : '✗ לא'; ?></li>
                        </ul>
                        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0;">
                            <strong>מידע נוסף:</strong>
                            <ul style="margin: 8px 0 0 20px; padding: 0;">
                                <li>ערך ישיר מ-get_option: <?php 
                                    $direct_check = get_option('clinic_queue_api_token_encrypted', null);
                                    echo !empty($direct_check) ? '✓ קיים (אורך: ' . strlen($direct_check) . ')' : '✗ לא קיים';
                                ?></li>
                                <li>WP_DEBUG מופעל: <?php echo defined('WP_DEBUG') && WP_DEBUG ? '✓ כן' : '✗ לא'; ?></li>
                                <?php 
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'options';
                                $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
                                ?>
                                <li>Prefix של מסד הנתונים: 
                                    <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; margin-right: 4px;">
                                        <?php echo esc_html($wpdb->prefix); ?>
                                    </code>
                                </li>
                                <li>שם טבלת Options: 
                                    <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; margin-right: 4px;">
                                        <?php echo esc_html($table_name); ?>
                                    </code>
                                    <span style="color: <?php echo $table_exists ? '#00a32a' : '#d63638'; ?>; font-weight: bold; margin-right: 8px;">
                                        <?php echo $table_exists ? '✓ קיימת' : '✗ לא קיימת'; ?>
                                    </span>
                                </li>
                                <?php if (!$table_exists): ?>
                                    <li style="color: #d63638; margin-top: 8px;">
                                        <strong>⚠️ בעיה קריטית:</strong> טבלת Options לא נמצאה! זה יכול להסביר למה הטוקן לא נשמר.
                                        <br>WordPress לא יכול לעבוד בלי טבלה זו. בדוק את מסד הנתונים.
                                    </li>
                                <?php else: ?>
                                    <?php 
                                    // Try to query the table directly
                                    $test_query = $wpdb->get_var($wpdb->prepare(
                                        "SELECT option_value FROM {$table_name} WHERE option_name = %s LIMIT 1",
                                        'clinic_queue_api_token_encrypted'
                                    ));
                                    
                                    // Check if option exists (even if value is empty)
                                    $option_exists = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(*) FROM {$table_name} WHERE option_name = %s",
                                        'clinic_queue_api_token_encrypted'
                                    )) > 0;
                                    ?>
                                    <li>בדיקת שאילתה ישירה: 
                                        <?php if ($test_query !== null): ?>
                                            <span style="color: #00a32a;">✓ הטבלה נגישה, הערך קיים (אורך: <?php echo strlen($test_query); ?>)</span>
                                        <?php elseif ($option_exists): ?>
                                            <span style="color: #f0b849;">⚠ הטבלה נגישה, הרשומה קיימת אבל הערך ריק או NULL</span>
                                        <?php else: ?>
                                            <span style="color: #d63638;">✗ הטבלה נגישה, אבל הערך לא נמצא</span>
                                        <?php endif; ?>
                                    </li>
                                    <?php if ($table_exists): ?>
                                        <?php 
                                        // Test if we can write to the table
                                        $test_key = 'clinic_queue_test_write_' . time();
                                        $test_write = $wpdb->query($wpdb->prepare(
                                            "INSERT INTO {$table_name} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = %s",
                                            $test_key,
                                            'test_value',
                                            'test_value'
                                        ));
                                        $test_read = $wpdb->get_var($wpdb->prepare(
                                            "SELECT option_value FROM {$table_name} WHERE option_name = %s",
                                            $test_key
                                        ));
                                        // Clean up test
                                        if ($test_read === 'test_value') {
                                            $wpdb->delete($table_name, array('option_name' => $test_key));
                                        }
                                        ?>
                                        <li>בדיקת כתיבה לטבלה: 
                                            <?php if ($test_read === 'test_value'): ?>
                                                <span style="color: #00a32a;">✓ כתיבה וקריאה עובדים תקין</span>
                                            <?php else: ?>
                                                <span style="color: #d63638;">✗ בעיה בכתיבה/קריאה מהטבלה</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (isset($_GET['settings-updated'])): ?>
                                    <li style="color: #00a32a;">✓ הטופס נשלח לאחרונה (settings-updated=<?php echo esc_html($_GET['settings-updated']); ?>)</li>
                                <?php endif; ?>
                                <?php if (isset($_POST['clinic_queue_api_token'])): ?>
                                    <li style="color: #2271b1;">✓ שדה טוקן נשלח בטופס (אורך: <?php echo strlen($_POST['clinic_queue_api_token']); ?>)</li>
                                <?php elseif (isset($_POST['clinic_queue_save_settings'])): ?>
                                    <li style="color: #d63638;">✗ שדה טוקן לא נשלח בטופס (אבל הטופס נשלח)</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="debug-info-grid" style="display: grid; grid-template-columns: 1fr; gap: 16px;">
                        
                        <!-- Option Storage Status -->
                        <div class="debug-section">
                            <h3 style="margin-top: 0; color: #8b6914; font-size: 14px; font-weight: 600;">
                                <span class="dashicons dashicons-database" style="font-size: 16px; vertical-align: text-bottom;"></span>
                                אחסון ב-WordPress Options
                            </h3>
                            <div style="background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #e0e0e0;">
                                <div style="margin-bottom: 8px;">
                                    <strong>קיים במסד הנתונים:</strong>
                                    <span style="color: <?php echo !empty($token_debug_info['option_exists']) ? '#00a32a' : '#d63638'; ?>; font-weight: bold;">
                                        <?php echo !empty($token_debug_info['option_exists']) ? '✓ כן' : '✗ לא'; ?>
                                    </span>
                                </div>
                                <?php if (!empty($token_debug_info['option_exists'])): ?>
                                    <div style="margin-bottom: 8px;">
                                        <strong>אורך ערך מוצפן:</strong>
                                        <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">
                                            <?php echo esc_html($token_debug_info['option_value_length'] ?? 0); ?> תווים
                                        </code>
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <strong>תצוגה מקדימה (50 תווים ראשונים):</strong>
                                        <div style="background: #f9f9f9; padding: 8px; border-radius: 3px; font-family: monospace; font-size: 11px; word-break: break-all; direction: ltr; text-align: left; margin-top: 4px;">
                                            <?php echo esc_html($token_debug_info['option_value_preview'] ?? ''); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Decryption Status -->
                        <?php if (!empty($token_debug_info['option_exists'])): ?>
                        <div class="debug-section">
                            <h3 style="margin-top: 0; color: #8b6914; font-size: 14px; font-weight: 600;">
                                <span class="dashicons dashicons-unlock" style="font-size: 16px; vertical-align: text-bottom;"></span>
                                פענוח טוקן
                            </h3>
                            <div style="background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #e0e0e0;">
                                <div style="margin-bottom: 8px;">
                                    <strong>פענוח הצליח:</strong>
                                    <span style="color: <?php echo !empty($token_debug_info['decryption_success']) ? '#00a32a' : '#d63638'; ?>; font-weight: bold;">
                                        <?php echo !empty($token_debug_info['decryption_success']) ? '✓ כן' : '✗ לא'; ?>
                                    </span>
                                </div>
                                <?php if (!empty($token_debug_info['decryption_success'])): ?>
                                    <div style="margin-bottom: 8px;">
                                        <strong>אורך טוקן מפוענח:</strong>
                                        <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">
                                            <?php echo esc_html($token_debug_info['decrypted_token_length'] ?? 0); ?> תווים
                                        </code>
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <strong>תצוגה מקדימה (20 תווים ראשונים):</strong>
                                        <div style="background: #f9f9f9; padding: 8px; border-radius: 3px; font-family: monospace; font-size: 11px; word-break: break-all; direction: ltr; text-align: left; margin-top: 4px;">
                                            <?php echo esc_html($token_debug_info['decrypted_token_preview'] ?? ''); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #d63638; font-size: 12px; margin-top: 8px;">
                                        ⚠ לא ניתן לפענח את הטוקן. ייתכן שהמפתחות השתנו או שהטוקן נשמר בפורמט ישן.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Token Source -->
                        <div class="debug-section">
                            <h3 style="margin-top: 0; color: #8b6914; font-size: 14px; font-weight: 600;">
                                <span class="dashicons dashicons-admin-settings" style="font-size: 16px; vertical-align: text-bottom;"></span>
                                מקור הטוקן
                            </h3>
                            <div style="background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #e0e0e0;">
                                <div style="margin-bottom: 8px;">
                                    <strong>מקור פעיל:</strong>
                                    <span style="color: #2271b1; font-weight: bold;">
                                        <?php
                                        $source = $token_debug_info['token_source'] ?? 'none';
                                        if ($source === 'wordpress_option') {
                                            echo 'WordPress Option (clinic_queue_api_token_encrypted)';
                                        } else {
                                            echo 'לא נמצא - יש להזין טוקן בדף ההגדרות';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <?php if (!empty($token_debug_info['token_source_details']) && is_array($token_debug_info['token_source_details'])): ?>
                                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0;">
                                        <strong>פרטים נוספים:</strong>
                                        <ul style="margin: 8px 0 0 20px; padding: 0;">
                                            <?php foreach ($token_debug_info['token_source_details'] as $key => $value): ?>
                                                <li style="margin-bottom: 4px;">
                                                    <strong><?php echo esc_html($key); ?>:</strong>
                                                    <?php if (is_bool($value)): ?>
                                                        <span style="color: <?php echo $value ? '#00a32a' : '#d63638'; ?>;">
                                                            <?php echo $value ? '✓ כן' : '✗ לא'; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">
                                                            <?php echo esc_html($value); ?>
                                                        </code>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0; color: #8b6914; font-size: 12px;">
                                    <strong>הערה:</strong> הטוקן חייב להיות מוגדר דרך דף ההגדרות של התוסף בלבד.
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </form>
</div>
