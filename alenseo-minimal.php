<?php
/**
 * Plugin Name: Alenseo SEO Minimal
 * Plugin URI: https://www.imponi.ch
 * Description: Ein schlankes SEO-Plugin mit Claude AI-Integration für WordPress
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Alen
 * Author URI: https://www.imponi.ch
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Company: Imponi Bern-Worb GmbH
 * Text Domain: alenseo
 * Domain Path: /languages
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Fehlerbehandlung aktivieren
if (!function_exists('alenseo_error_handler')) {
    function alenseo_error_handler($errno, $errstr, $errfile, $errline) {
        error_log("Alenseo Error: {$errstr} in {$errfile} on line {$errline}");
        return false; // Standard-PHP-Fehlerbehandlung fortsetzen lassen
    }
}
set_error_handler('alenseo_error_handler', E_ALL & ~E_NOTICE & ~E_DEPRECATED);

define('ALENSEO_MINIMAL_DIR', plugin_dir_path(__FILE__));
define('ALENSEO_MINIMAL_URL', plugin_dir_url(__FILE__));
define('ALENSEO_MINIMAL_VERSION', '1.0.0');

// Sichere Datei-Einbindung
function alenseo_require_file($file) {
    $file_path = ALENSEO_MINIMAL_DIR . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
        return true;
    } else {
        error_log("Alenseo SEO: Datei nicht gefunden - {$file_path}");
        return false;
    }
}

// Kernkomponenten laden
$required_files = array(
    'includes/minimal-admin.php',
    'includes/class-minimal-analysis.php',
    'includes/class-dashboard.php',
    'includes/class-claude-api.php',
    'includes/class-content-optimizer.php',
    'includes/alenseo-ajax-handlers.php',
    'includes/alenseo-claude-ajax.php',
    'includes/alenseo-enhanced-ajax.php'
);

$missing_files = array();
foreach ($required_files as $file) {
    if (!alenseo_require_file($file)) {
        $missing_files[] = $file;
    }
}

// Wenn Dateien fehlen, Warnung anzeigen
if (!empty($missing_files)) {
    add_action('admin_notices', function() use ($missing_files) {
        echo '<div class="error"><p>';
        echo 'Alenseo SEO Minimal: Folgende Dateien fehlen: ' . implode(', ', $missing_files);
        echo '</p></div>';
    });
}

if (defined('WPB_VC_VERSION') || class_exists('WPBakeryVisualComposer')) {
    require_once ALENSEO_MINIMAL_DIR . 'includes/class-wpbakery-integration.php';
}

function alenseo_init() {
    try {
        // Prüfen ob die Klassen existieren, bevor sie instanziiert werden
        if (class_exists('Alenseo_Minimal_Admin')) {
            new Alenseo_Minimal_Admin();
        } else {
            error_log('Alenseo SEO Minimal: Klasse "Alenseo_Minimal_Admin" nicht gefunden.');
        }
        
        if (class_exists('Alenseo_Dashboard')) {
            new Alenseo_Dashboard();
        } else {
            error_log('Alenseo SEO Minimal: Klasse "Alenseo_Dashboard" nicht gefunden.');
        }
        
        if ((defined('WPB_VC_VERSION') || class_exists('WPBakeryVisualComposer')) && 
            class_exists('Alenseo_WPBakery_Integration')) {
            new Alenseo_WPBakery_Integration();
        }
    } catch (Exception $e) {
        error_log('Alenseo SEO Minimal - Initialisierungsfehler: ' . $e->getMessage());
        
        // Füge Admin-Benachrichtigung hinzu
        add_action('admin_notices', function() use ($e) {
            echo '<div class="error"><p>';
            echo 'Alenseo SEO Minimal: Initialisierungsfehler. Bitte überprüfen Sie die Fehlerprotokolle.';
            if (WP_DEBUG) {
                echo ' Fehler: ' . esc_html($e->getMessage());
            }
            echo '</p></div>';
        });
    }
}
add_action('plugins_loaded', 'alenseo_init');

function alenseo_minimal_activate() {
    if (!get_option('alenseo_settings')) {
        $default_settings = array(
            'claude_api_key' => '',
            'claude_model' => 'claude-3-haiku-20240307',
            'post_types' => array('post', 'page'),
            'seo_elements' => array(
                'meta_title' => true,
                'meta_description' => true,
                'headings' => true,
                'content' => true
            )
        );
        update_option('alenseo_settings', $default_settings);
    }
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'alenseo_minimal_activate');

function alenseo_minimal_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'alenseo_minimal_deactivate');

function alenseo_minimal_add_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=alenseo-minimal-settings') . '">' . __('Einstellungen', 'alenseo') . '</a>',
        '<a href="' . admin_url('admin.php?page=alenseo-optimizer') . '">' . __('SEO-Optimierung', 'alenseo') . '</a>'
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'alenseo_minimal_add_action_links');
