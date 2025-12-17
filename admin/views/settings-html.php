<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data from the controller
$updated = isset($updated) ? $updated : false;
$current_token = isset($current_token) ? $current_token : '';
$has_constant_token = isset($has_constant_token) ? $has_constant_token : false;
$token_from_constant = isset($token_from_constant) ? $token_from_constant : null;
$api_endpoint = isset($api_endpoint) ? $api_endpoint : 'https://do-proxy-staging.doctor-clinix.com';
$has_constant_endpoint = isset($has_constant_endpoint) ? $has_constant_endpoint : false;
$endpoint_from_constant = isset($endpoint_from_constant) ? $endpoint_from_constant : null;
?>

<div class="wrap">
    <h1 class="wp-heading-inline">הגדרות - ניהול תורי מרפאה</h1>
    <hr class="wp-header-end">
    
    <?php if ($updated): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>ההגדרות נשמרו בהצלחה!</strong></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" class="clinic-queue-settings-form">
        <?php wp_nonce_field('clinic_queue_save_settings', 'clinic_queue_settings_nonce'); ?>
        
        <div class="clinic-queue-settings-container">
            <!-- API Token Section -->
            <div class="clinic-queue-settings-section">
                <h2>טוקן API</h2>
                
                <?php if ($has_constant_token): ?>
                    <div class="notice notice-info">
                        <p>
                            <strong>טוקן מוגדר ב-wp-config.php</strong><br>
                            הטוקן מוגדר כקבוע ב-<code>wp-config.php</code> ולכן לא ניתן לערוך אותו כאן.
                            זהו המתודה המאובטחת ביותר. אם תרצה לשנות את הטוקן, ערוך את הקובץ <code>wp-config.php</code>.
                        </p>
                        <p>
                            <strong>טוקן נוכחי:</strong> 
                            <code><?php echo esc_html(substr($token_from_constant, 0, 20)) . '...'; ?></code>
                        </p>
                    </div>
                <?php else: ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="clinic_queue_api_token">טוקן API</label>
                                </th>
                                <td>
                                    <?php
                                    // Render token field manually
                                    $settings = Clinic_Queue_Settings_Admin::get_instance();
                                    $settings->render_token_field();
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- API Endpoint Section -->
            <div class="clinic-queue-settings-section">
                <h2>כתובת API</h2>
                
                <?php if ($has_constant_endpoint): ?>
                    <div class="notice notice-info">
                        <p>
                            <strong>כתובת API מוגדרת ב-wp-config.php</strong><br>
                            כתובת ה-API מוגדרת כקבוע ב-<code>wp-config.php</code> ולכן לא ניתן לערוך אותה כאן.
                        </p>
                        <p>
                            <strong>כתובת נוכחית:</strong> 
                            <code><?php echo esc_html($endpoint_from_constant); ?></code>
                        </p>
                    </div>
                <?php else: ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="clinic_queue_api_endpoint">כתובת API</label>
                                </th>
                                <td>
                                    <?php 
                                    // Render endpoint field if not from constant
                                    $settings = Clinic_Queue_Settings_Admin::get_instance();
                                    $settings->render_endpoint_field();
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Security Information -->
            <div class="clinic-queue-settings-section">
                <h2>מידע אבטחה</h2>
                <div class="notice notice-warning">
                    <h3>המלצות אבטחה:</h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><strong>השיטה המאובטחת ביותר:</strong> הגדרת הטוקן ב-<code>wp-config.php</code> כקבוע <code>CLINIC_QUEUE_API_TOKEN</code></li>
                        <li>הטוקן שמוגדר כאן נשמר מוצפן במסד הנתונים</li>
                        <li>אל תשתף את הטוקן עם אחרים</li>
                        <li>החלף את הטוקן באופן קבוע</li>
                        <li>ודא ש-<code>wp-config.php</code> לא נגיש דרך HTTP</li>
                    </ul>
                    <p>
                        <strong>למידע נוסף:</strong> 
                        <a href="<?php echo esc_url(CLINIC_QUEUE_MANAGEMENT_URL . 'api/SECURITY.md'); ?>" target="_blank">
                            קרא את המדריך המלא לאבטחה
                        </a>
                    </p>
                </div>
            </div>
            
            <!-- Submit Button -->
            <?php if (!$has_constant_token || !$has_constant_endpoint): ?>
                <p class="submit">
                    <input type="submit" name="clinic_queue_save_settings" id="submit" class="button button-primary" value="שמור הגדרות">
                </p>
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
.clinic-queue-settings-container {
    max-width: 800px;
    margin-top: 20px;
}

.clinic-queue-settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}

.clinic-queue-settings-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.clinic-queue-settings-form .form-table th {
    width: 200px;
}

.clinic-queue-settings-form .form-table td input[type="password"],
.clinic-queue-settings-form .form-table td input[type="url"] {
    width: 100%;
    max-width: 500px;
}

.clinic-queue-settings-form code {
    background: #f0f0f1;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 13px;
}
</style>

