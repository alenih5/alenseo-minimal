<?php
/**
 * Plugin Name: Alenseo SEO Minimal
 * Plugin URI: https://www.imponi.ch
 * Description: Ein schlankes SEO-Plugin mit Claude AI-Integration für WordPress
 * Version: 2.0.14
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

// Prüfen, ob bereits eine Version des Plugins aktiv ist
if (defined('ALENSEO_MINIMAL_VERSION')) {
    // Warnung ausgeben, wenn wir uns nicht im Aktivierungsprozess befinden
    if (!in_array(basename($_SERVER['PHP_SELF']), array('plugins.php'))) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            echo 'Eine andere Version von Alenseo SEO ist bereits aktiv. Bitte deaktivieren Sie alle anderen Versionen des Plugins, bevor Sie diese Version aktivieren.';
            echo '</p></div>';
        });
    }
    
    // Plugin nicht weiter laden
    return;
}

define('ALENSEO_MINIMAL_DIR', plugin_dir_path(__FILE__));
define('ALENSEO_MINIMAL_URL', plugin_dir_url(__FILE__));
define('ALENSEO_MINIMAL_VERSION', '2.0.13');  // Version auf 2.0.13 aktualisiert
define('ALENSEO_PLUGIN_FILE', __FILE__);
// Updated plugin version to 2.0.17
define('ALENSEO_VERSION', '2.0.17');

// Sichere Datei-Einbindung
function alenseo_require_file_v2($file) {
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
    'includes/debug.php', // Debug-Funktionen zuerst laden
    'includes/minimal-admin.php',
    'includes/class-minimal-analysis.php',
    'includes/class-enhanced-analysis.php',
    'includes/class-dashboard.php',
    'includes/class-meta-box.php',
    'includes/class-claude-api.php',
    'includes/class-content-optimizer.php',
    'includes/class-database.php',
    'includes/alenseo-ajax-handlers.php',
    'includes/alenseo-claude-ajax.php',
    'includes/alenseo-enhanced-ajax.php',
    'includes/alenseo-settings-ajax.php',
    'includes/alenseo-optimizer-ajax.php',
    'includes/alenseo-test-functions.php'
);

$missing_files = array();
foreach ($required_files as $file) {
    if (!alenseo_require_file_v2($file)) {
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
        if (class_exists('Alenseo_Database')) {
            // Die Datenbankklasse zuerst initialisieren, damit sie für andere Klassen verfügbar ist
            global $alenseo_database;
            $alenseo_database = new Alenseo_Database();
        } else {
            error_log('Alenseo SEO Minimal: Klasse "Alenseo_Database" nicht gefunden.');
        }
        
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
        
        // Meta Box Unterstützung hinzufügen
        if (class_exists('Alenseo_Meta_Box')) {
            new Alenseo_Meta_Box();
        } else {
            error_log('Alenseo SEO Minimal: Klasse "Alenseo_Meta_Box" nicht gefunden.');
        }
        
        if ((defined('WPB_VC_VERSION') || class_exists('WPBakeryVisualComposer')) && 
            class_exists('Alenseo_WPBakery_Integration')) {
            new Alenseo_WPBakery_Integration();
        }
        
        // Enhanced Analysis aktivieren, wenn verfügbar
        if (class_exists('Alenseo_Enhanced_Analysis')) {
            // Diese Klasse wird bei Bedarf instanziiert
            add_filter('alenseo_use_enhanced_analysis', '__return_true');
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

// Ensure Multisite compatibility
if (is_multisite()) {
    add_action('network_admin_menu', function () {
        add_menu_page(
            __('Alenseo Netzwerkeinstellungen', 'alenseo'),
            __('Alenseo SEO', 'alenseo'),
            'manage_network_options',
            'alenseo-network-settings',
            function () {
                echo '<h1>' . __('Alenseo Netzwerkeinstellungen', 'alenseo') . '</h1>';
                echo '<form method="post" action="">';
                echo '<label for="global-seo-setting">' . __('Globale SEO-Einstellung:', 'alenseo') . '</label>';
                echo '<input type="text" id="global-seo-setting" name="global_seo_setting" value="' . esc_attr(get_site_option('alenseo_global_seo_setting', '')) . '"><br><br>';
                echo '<input type="submit" value="' . __('Speichern', 'alenseo') . '">';
                echo '</form>';

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $global_seo_setting = sanitize_text_field($_POST['global_seo_setting']);
                    update_site_option('alenseo_global_seo_setting', $global_seo_setting);
                    echo '<p>' . __('Einstellungen gespeichert.', 'alenseo') . '</p>';
                }
            }
        );
    });
}

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
            ),
            'advanced' => array(
                'cache_duration' => 3600,
                'track_api_usage' => true,
                'store_seo_history' => true
            )
        );
        update_option('alenseo_settings', $default_settings);
    }
    
    // Initialisiere die Datenbank bei Aktivierung
    if (class_exists('Alenseo_Database')) {
        $db = new Alenseo_Database();
        $db->install();
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
