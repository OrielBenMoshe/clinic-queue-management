<?php
/**
 * סקריפט בדיקה לendpoint של Relations
 * הרץ: php test-relations-endpoint.php
 */

// Load WordPress
require_once('../../../../../wp-load.php');

echo "=== בדיקת Relations Endpoint ===\n\n";

$clinic_id = 1249;
$url = rest_url("clinic-queue/v1/relations/clinic/{$clinic_id}/doctors");

echo "1. URL: {$url}\n\n";

// Make request
$response = wp_remote_get($url, array(
    'headers' => array(
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ),
    'cookies' => $_COOKIE
));

echo "2. Response Code: " . wp_remote_retrieve_response_code($response) . "\n\n";

if (is_wp_error($response)) {
    echo "❌ שגיאה: " . $response->get_error_message() . "\n";
    exit;
}

$body = wp_remote_retrieve_body($response);
echo "3. Response Body:\n";
echo $body . "\n\n";

$data = json_decode($body, true);
echo "4. Parsed Data:\n";
print_r($data);

if (is_array($data) && count($data) > 0) {
    echo "\n✅ הצלחה! נמצאו " . count($data) . " רופאים\n";
} else {
    echo "\n⚠️ לא נמצאו רופאים\n";
}

// Now test JetEngine directly
echo "\n\n=== בדיקת JetEngine ישירות ===\n\n";
$jet_url = rest_url("jet-rel/201/");
echo "URL: {$jet_url}\n\n";

$jet_response = wp_remote_get($jet_url, array(
    'headers' => array(
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ),
    'cookies' => $_COOKIE
));

echo "Response Code: " . wp_remote_retrieve_response_code($jet_response) . "\n";
$jet_body = wp_remote_retrieve_body($jet_response);
echo "Response:\n";
echo $jet_body . "\n";
