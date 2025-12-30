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
    
    <form method="post" action="" id="clinic-queue-settings-form" class="clinic-queue-settings-form">
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
            
        </div>
    </form>
</div>
