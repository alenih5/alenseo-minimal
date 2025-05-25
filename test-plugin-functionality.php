<?php
/**
 * Simple test to verify Alenseo plugin functionality
 */

// Test if WordPress is loaded
if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

echo "<h2>Alenseo Plugin Test Results</h2>\n";

// Test 1: Check if main plugin file exists
$plugin_file = 'c:\Users\Alen_\OneDrive\Desktop\Plugin Imponi\alenseo-minimal\alenseo-minimal\alenseo-minimal.php';
echo "<p><strong>1. Plugin File Check:</strong> ";
if (file_exists($plugin_file)) {
    echo "✅ Main plugin file exists</p>\n";
} else {
    echo "❌ Main plugin file NOT found</p>\n";
}

// Test 2: Check if plugin class exists
echo "<p><strong>2. Plugin Class Check:</strong> ";
if (class_exists('Alenseo_SEO_Minimal')) {
    echo "✅ Main plugin class loaded</p>\n";
} else {
    echo "❌ Main plugin class NOT loaded</p>\n";
}

// Test 3: Check if constants are defined
echo "<p><strong>3. Constants Check:</strong> ";
$constants = ['ALENSEO_MINIMAL_DIR', 'ALENSEO_MINIMAL_URL', 'ALENSEO_MINIMAL_VERSION'];
$all_defined = true;
foreach ($constants as $const) {
    if (!defined($const)) {
        $all_defined = false;
        break;
    }
}
if ($all_defined) {
    echo "✅ All constants defined</p>\n";
} else {
    echo "❌ Some constants missing</p>\n";
}

// Test 4: Check asset files
echo "<p><strong>4. Asset Files Check:</strong><br>\n";
$assets = [
    'CSS' => 'assets/css/alenseo-admin.css',
    'JavaScript' => 'assets/js/alenseo-admin.js',
    'Dashboard JS' => 'assets/js/dashboard-visual.js'
];

foreach ($assets as $type => $path) {
    $full_path = ALENSEO_MINIMAL_DIR . $path;
    echo "&nbsp;&nbsp;{$type}: ";
    if (file_exists($full_path)) {
        echo "✅ Found<br>\n";
    } else {
        echo "❌ Missing: {$full_path}<br>\n";
    }
}
echo "</p>\n";

// Test 5: Check database connection
echo "<p><strong>5. Database Connection:</strong> ";
global $wpdb;
if ($wpdb && $wpdb->last_error === '') {
    echo "✅ WordPress database connected</p>\n";
} else {
    echo "❌ Database connection issue</p>\n";
}

// Test 6: Check if AJAX handlers are registered
echo "<p><strong>6. AJAX Handlers:</strong> ";
$ajax_actions = [
    'alenseo_test_claude_api',
    'alenseo_test_openai_api',
    'alenseo_load_posts',
    'alenseo_get_api_status'
];

$registered_actions = 0;
foreach ($ajax_actions as $action) {
    if (has_action("wp_ajax_{$action}")) {
        $registered_actions++;
    }
}

if ($registered_actions === count($ajax_actions)) {
    echo "✅ All AJAX handlers registered ({$registered_actions}/{count($ajax_actions)})</p>\n";
} else {
    echo "⚠️ Some AJAX handlers missing ({$registered_actions}/{count($ajax_actions)})</p>\n";
}

// Test 7: Check API settings
echo "<p><strong>7. API Settings:</strong> ";
$settings = get_option('alenseo_settings', []);
$claude_configured = !empty($settings['claude_api_key']);
$openai_configured = !empty($settings['openai_api_key']);

if ($claude_configured || $openai_configured) {
    echo "✅ At least one API configured<br>\n";
    echo "&nbsp;&nbsp;Claude: " . ($claude_configured ? "✅" : "❌") . "<br>\n";
    echo "&nbsp;&nbsp;OpenAI: " . ($openai_configured ? "✅" : "❌") . "</p>\n";
} else {
    echo "⚠️ No APIs configured</p>\n";
}

echo "<h3>Summary</h3>\n";
echo "<p>The plugin structure appears to be in place. Next steps:</p>\n";
echo "<ol>\n";
echo "<li>Configure API keys in the settings</li>\n";
echo "<li>Test API connections</li>\n";
echo "<li>Try analyzing some posts/pages</li>\n";
echo "</ol>\n";
?>
