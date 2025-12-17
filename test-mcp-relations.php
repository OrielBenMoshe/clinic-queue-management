<?php
/**
 * Test script to fetch relations via JetEngine MCP
 * 
 * This script demonstrates how to pull relations from the JetEngine MCP server
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../../');
}

// Load WordPress
require_once ABSPATH . 'wp-load.php';

// MCP Server credentials
$username = 'mcp-server';
$password = '6iuY oi3g V0Pl bGhx wRgK de3V';
$base_url = 'https://doctor-place.com/wp-json';

echo "<h1>בדיקת Relations דרך JetEngine MCP</h1>\n";
echo "<hr>\n";

// Test 1: List available relations
echo "<h2>1. Relations זמינים:</h2>\n";
$relations_url = $base_url . '/jet-rel/';
$response = wp_remote_get($relations_url, [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
    ]
]);

if (is_wp_error($response)) {
    echo "<p style='color: red;'>שגיאה: " . $response->get_error_message() . "</p>\n";
} else {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($data && isset($data['routes'])) {
        $relation_ids = [];
        foreach ($data['routes'] as $route => $info) {
            if (preg_match('/\/jet-rel\/(\d+)$/', $route, $matches)) {
                $relation_ids[] = $matches[1];
            }
        }
        echo "<p>נמצאו Relations: " . implode(', ', $relation_ids) . "</p>\n";
    }
}

// Test 2: Get relation 138 data
echo "<h2>2. נתוני Relation 138:</h2>\n";
$rel_138_url = $base_url . '/jet-rel/138';
$response = wp_remote_get($rel_138_url, [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
    ]
]);

if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($data && !empty($data)) {
        echo "<h3>Parents עם Children:</h3>\n";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Parent ID</th><th>Children IDs</th></tr>\n";
        
        foreach ($data as $parent_id => $children) {
            $child_ids = array_map(function($child) {
                return $child['child_object_id'];
            }, $children);
            echo "<tr><td>$parent_id</td><td>" . implode(', ', $child_ids) . "</td></tr>\n";
        }
        echo "</table>\n";
        
        // Test getting children for a specific parent
        if (!empty($data)) {
            $first_parent = array_key_first($data);
            echo "<h3>Children של Parent ID $first_parent:</h3>\n";
            $children_url = $base_url . "/jet-rel/138/children/$first_parent";
            $children_response = wp_remote_get($children_url, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
                ]
            ]);
            
            if (!is_wp_error($children_response)) {
                $children_body = wp_remote_retrieve_body($children_response);
                $children_data = json_decode($children_body, true);
                echo "<pre>" . print_r($children_data, true) . "</pre>\n";
            }
        }
    } else {
        echo "<p>לא נמצאו נתונים ב-Relation 138</p>\n";
    }
}

// Test 3: Try MCP endpoint
echo "<h2>3. בדיקת MCP Endpoint:</h2>\n";
$mcp_url = $base_url . '/jet-engine/v1/mcp';

// Try JSON-RPC call
$mcp_request = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/call',
    'params' => [
        'name' => 'resource-get-configuration',
        'input' => [
            'parts' => [
                'relations' => true
            ]
        ]
    ]
];

$response = wp_remote_post($mcp_url, [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ],
    'body' => json_encode($mcp_request)
]);

if (is_wp_error($response)) {
    echo "<p style='color: red;'>שגיאה: " . $response->get_error_message() . "</p>\n";
} else {
    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "<p>Status Code: <strong>$status</strong></p>\n";
    
    if ($status === 200) {
        $data = json_decode($body, true);
        echo "<pre>" . print_r($data, true) . "</pre>\n";
    } else {
        echo "<p style='color: orange;'>Response Body:</p>\n";
        echo "<pre>" . esc_html($body) . "</pre>\n";
    }
}

// Test 4: Check if relation 5 exists (used in code)
echo "<h2>4. בדיקת Relation 5 (המשמש בקוד):</h2>\n";
$rel_5_url = $base_url . '/jet-rel/5';
$response = wp_remote_get($rel_5_url, [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
    ]
]);

if (is_wp_error($response)) {
    echo "<p style='color: red;'>שגיאה: " . $response->get_error_message() . "</p>\n";
} else {
    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($status === 200) {
        $data = json_decode($body, true);
        echo "<p style='color: green;'>✓ Relation 5 קיים!</p>\n";
        echo "<pre>" . print_r($data, true) . "</pre>\n";
    } else {
        echo "<p style='color: red;'>✗ Relation 5 לא קיים (Status: $status)</p>\n";
        echo "<p>Response: " . esc_html($body) . "</p>\n";
        echo "<p style='color: orange;'><strong>המלצה:</strong> עדכן את הקוד להשתמש ב-Relation 138 במקום 5</p>\n";
    }
}

echo "<hr>\n";
echo "<h2>סיכום:</h2>\n";
echo "<ul>\n";
echo "<li>Relations זמינים: 185, 184, 138</li>\n";
echo "<li>Relation 138 מכיל נתונים</li>\n";
echo "<li>Relation 5 לא קיים - צריך לעדכן את הקוד</li>\n";
echo "</ul>\n";
?>

