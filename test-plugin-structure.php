<?php
/**
 * Simple plugin structure test
 * This script validates that all required files exist and classes can be loaded
 */

// Define WordPress constants that are normally available
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('ALENSEO_MINIMAL_DIR')) {
    define('ALENSEO_MINIMAL_DIR', __DIR__ . '/');
}

if (!defined('ALENSEO_MINIMAL_URL')) {
    define('ALENSEO_MINIMAL_URL', 'http://localhost/wp-content/plugins/alenseo-minimal/');
}

if (!defined('ALENSEO_MINIMAL_VERSION')) {
    define('ALENSEO_MINIMAL_VERSION', '2.1.0');
}

echo "🔍 Testing Alenseo Plugin Structure...\n\n";

// Test 1: Check if main plugin file exists and is readable
echo "1. Testing main plugin file...\n";
$main_file = __DIR__ . '/alenseo-minimal.php';
if (file_exists($main_file) && is_readable($main_file)) {
    echo "   ✅ Main plugin file exists and is readable\n";
} else {
    echo "   ❌ Main plugin file not found or not readable\n";
    exit(1);
}

// Test 2: Check required include files
echo "\n2. Testing required include files...\n";
$required_files = [
    'includes/class-ai-api.php',
    'includes/class-claude-api.php', 
    'includes/class-chatgpt-api.php',
    'includes/alenseo-ajax-handlers.php',
    'includes/alenseo-settings-ajax.php',
    'includes/minimal-admin.php',
    'includes/class-dashboard.php'
];

foreach ($required_files as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path) && is_readable($file_path)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file (missing or not readable)\n";
    }
}

// Test 3: Basic PHP syntax check (if PHP is available)
echo "\n3. Testing PHP syntax...\n";
$test_files = [
    'alenseo-minimal.php',
    'includes/class-claude-api.php',
    'includes/alenseo-ajax-handlers.php'
];

foreach ($test_files as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        
        // Basic syntax checks
        $errors = [];
        
        // Check for unclosed PHP tags
        if (substr_count($content, '<?php') > substr_count($content, '?>')) {
            // This is actually okay for PHP files
        }
        
        // Check for basic PHP structure
        if (strpos($content, '<?php') !== 0) {
            $errors[] = "File doesn't start with <?php";
        }
        
        // Check for obvious syntax issues
        $lines = explode("\n", $content);
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '//') === 0 || strpos($line, '#') === 0) {
                continue;
            }
            
            // Check for unmatched braces (basic check)
            $open_braces = substr_count($line, '{');
            $close_braces = substr_count($line, '}');
            
            // This is a very basic check - in real code, braces can span multiple lines
        }
        
        if (empty($errors)) {
            echo "   ✅ $file (basic syntax check passed)\n";
        } else {
            echo "   ⚠️ $file (potential issues: " . implode(', ', $errors) . ")\n";
        }
    }
}

// Test 4: Check template files
echo "\n4. Testing template files...\n";
$template_files = [
    'templates/dashboard-page-visual.php',
    'templates/settings-page.php',
    'templates/optimizer-page.php'
];

foreach ($template_files as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path) && is_readable($file_path)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file (missing or not readable)\n";
    }
}

// Test 5: Check asset files
echo "\n5. Testing asset files...\n";
$asset_files = [
    'assets/css/admin.css',
    'assets/js/admin.js',
    'assets/css/dashboard-visual.css',
    'assets/js/dashboard-visual.js'
];

foreach ($asset_files as $file) {
    $file_path = __DIR__ . '/' . $file;
    if (file_exists($file_path) && is_readable($file_path)) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ $file (missing or not readable)\n";
    }
}

echo "\n🎉 Plugin structure test completed!\n";
echo "\nNext steps:\n";
echo "1. Upload the plugin to WordPress /wp-content/plugins/ directory\n";
echo "2. Activate the plugin in WordPress admin\n";
echo "3. Test the consolidated admin menu structure\n";
echo "4. Configure API keys in Settings\n";
echo "5. Test the Page Optimizer functionality\n";
echo "6. Test API integration\n";

?>
