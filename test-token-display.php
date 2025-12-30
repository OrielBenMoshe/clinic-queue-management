<?php
/**
 * Test file to check token display
 * Navigate to: /wp-content/plugins/clinic-queue-management/test-token-display.php
 */

// Load WordPress
require_once('../../../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('You need to be an admin to view this page');
}

echo '<h1>Token Display Test</h1>';
echo '<hr>';

// Check if token exists
$encrypted_token = get_option('clinic_queue_api_token_encrypted', null);
echo '<h2>Token Status:</h2>';
echo '<p><strong>Encrypted token exists:</strong> ' . ($encrypted_token ? 'YES' : 'NO') . '</p>';

if ($encrypted_token) {
    echo '<p><strong>Encrypted token length:</strong> ' . strlen($encrypted_token) . '</p>';
    echo '<p><strong>Encrypted token (first 50 chars):</strong> ' . esc_html(substr($encrypted_token, 0, 50)) . '...</p>';
}

echo '<hr>';
echo '<h2>Rendering Token Field:</h2>';

// Load the settings class
require_once(plugin_dir_path(__FILE__) . 'admin/class-settings.php');
$settings = Clinic_Queue_Settings_Admin::get_instance();
$settings->render_token_field();

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=clinic-queue-settings') . '">Go to Settings Page</a></p>';

