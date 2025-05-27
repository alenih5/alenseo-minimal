<?php
/**
 * Plugin Name: SEO AI Master
 * Plugin URI: https://imponi.ch
 * Description: KI-gestützte SEO-Optimierung für WordPress mit Claude & ChatGPT Integration.
 * Version: 1.0.2
 * Author: AlenSEO
 * Author URI: https://imponi.ch
 * Text Domain: seo-ai-master
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Plugin-Konstanten definieren
define('SEO_AI_MASTER_VERSION', '1.0.2');
define('SEO_AI_MASTER_PATH', plugin_dir_path(__FILE__));
define('SEO_AI_MASTER_URL', plugin_dir_url(__FILE__));
define('SEO_AI_MASTER_BASENAME', plugin_basename(__FILE__));
define('SEO_AI_MASTER_FILE', __FILE__);

// Mindestanforderungen prüfen
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>SEO AI Master:</strong> ';
        echo sprintf(__('Benötigt PHP 8.0 oder höher. Aktuelle Version: %s', 'seo-ai-master'), PHP_VERSION);
        echo '</p></div>';
    });
    return;
}

if (version_compare(get_bloginfo('version'), '6.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>SEO AI Master:</strong> ';
        echo sprintf(__('Benötigt WordPress 6.0 oder höher. Aktuelle Version: %s', 'seo-ai-master'), get_bloginfo('version'));
        echo '</p></div>';
    });
    return;
}

/**
 * PSR-4 Klassen-Autoloader
 */
spl_autoload_register(function ($class) {
    $prefix = 'SEOAI\\';
    $base_dir = SEO_AI_MASTER_PATH . 'includes/';
    
    // Nur Klassen im eigenen Namespace laden
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    
    // Relativen Klassennamen ermitteln
    $relative_class = substr($class, strlen($prefix));
    
    // Namespace-Teile für Pfad extrahieren
    $parts = explode('\\', $relative_class);
    $className = array_pop($parts);
    
    // Pfad zusammensetzen
    $path = $base_dir;
    if (!empty($parts)) {
        $path .= strtolower(implode('/', $parts)) . '/';
    }
    $path .= 'class-' . strtolower(str_replace('_', '-', $className)) . '.php';
    
    // Datei laden falls vorhanden
    if (file_exists($path)) {
        require_once $path;
    }
});

/**
 * Plugin-Hooks registrieren
 */
register_activation_hook(__FILE__, ['SEOAI\\Core', 'activate']);
register_deactivation_hook(__FILE__, ['SEOAI\\Core', 'deactivate']);
register_uninstall_hook(__FILE__, ['SEOAI\\Core', 'uninstall']);

/**
 * Plugin initialisieren
 * 
 * @return \SEOAI\Core
 */
function seo_ai_master() {
    return \SEOAI\Core::get_instance();
}

// Plugin nach dem Laden aller Plugins initialisieren
add_action('plugins_loaded', 'seo_ai_master', 10);

/**
 * Textdomain für Übersetzungen laden
 */
add_action('init', function() {
    load_plugin_textdomain(
        'seo-ai-master', 
        false, 
        dirname(SEO_AI_MASTER_BASENAME) . '/languages'
    );
});

/**
 * Admin Body Class für bessere CSS-Isolation hinzufügen
 */
add_filter('admin_body_class', function($classes) {
    $screen = get_current_screen();
    
    if ($screen && (
        strpos($screen->id, 'seo-ai-master') !== false || 
        (isset($_GET['page']) && strpos($_GET['page'], 'seo-ai-') === 0)
    )) {
        $classes .= ' seo-ai-master-admin-page';
    }
    
    return $classes;
});

/**
 * Admin-spezifische Styles und Scripts (vereinfacht)
 * Das Haupt-Asset-Loading erfolgt im Admin\Manager
 */
add_action('admin_enqueue_scripts', function($hook) {
    // Nur auf Plugin-Seiten
    $is_plugin_page = (
        strpos($hook, 'seo-ai-master') !== false ||
        (isset($_GET['page']) && strpos($_GET['page'], 'seo-ai-') === 0)
    );
    
    if ($is_plugin_page) {
        // Zusätzliche WordPress-Admin-Styles falls benötigt
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Media Uploader für eventuelle Bild-Uploads
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }
        
        // WordPress REST API für erweiterte AJAX-Funktionen
        wp_enqueue_script('wp-api');
    }
}, 5); // Frühe Priorität, damit Manager darauf aufbauen kann

/**
 * Plugin-Links in der Plugin-Liste erweitern
 */
add_filter('plugin_action_links_' . SEO_AI_MASTER_BASENAME, function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=seo-ai-settings'),
        __('Einstellungen', 'seo-ai-master')
    );
    
    $dashboard_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=seo-ai-master'),
        __('Dashboard', 'seo-ai-master')
    );
    
    array_unshift($links, $settings_link, $dashboard_link);
    
    return $links;
});

/**
 * Plugin Meta-Links hinzufügen
 */
add_filter('plugin_row_meta', function($links, $file) {
    if ($file === SEO_AI_MASTER_BASENAME) {
        $meta_links = [
            sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://imponi.ch/seo-ai-master-docs',
                __('Dokumentation', 'seo-ai-master')
            ),
            sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://imponi.ch/support',
                __('Support', 'seo-ai-master')
            )
        ];
        
        $links = array_merge($links, $meta_links);
    }
    
    return $links;
}, 10, 2);

/**
 * Debug-Informationen (nur bei WP_DEBUG und für Administratoren)
 */
if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
    add_action('admin_notices', function() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'seo-ai-master') === false) {
            return;
        }
        
        // Asset-Dateien prüfen
        $css_path = SEO_AI_MASTER_PATH . 'assets/css/settings.css';
        $js_path = SEO_AI_MASTER_PATH . 'assets/js/settings.js';
        
        $errors = [];
        
        if (!file_exists($css_path)) {
            $errors[] = sprintf(__('CSS-Datei nicht gefunden: %s', 'seo-ai-master'), $css_path);
        }
        
        if (!file_exists($js_path)) {
            $errors[] = sprintf(__('JS-Datei nicht gefunden: %s', 'seo-ai-master'), $js_path);
        }
        
        // Schreibrechte prüfen
        $upload_dir = wp_upload_dir();
        if (!is_writable($upload_dir['basedir'])) {
            $errors[] = __('Upload-Verzeichnis ist nicht beschreibbar', 'seo-ai-master');
        }
        
        // Fehler anzeigen
        foreach ($errors as $error) {
            printf(
                '<div class="notice notice-error"><p><strong>SEO AI Master Debug:</strong> %s</p></div>',
                esc_html($error)
            );
        }
        
        // Erfolgreiche Ladung bestätigen
        if (empty($errors)) {
            printf(
                '<div class="notice notice-info is-dismissible"><p><strong>SEO AI Master Debug:</strong> %s</p></div>',
                __('Alle Asset-Dateien erfolgreich gefunden.', 'seo-ai-master')
            );
        }
    });
}

/**
 * Kompatibilitätsprüfungen mit anderen Plugins
 */
add_action('admin_init', function() {
    // Prüfung auf konfliktträchtiche Plugins
    $conflicting_plugins = [
        'yoast-seo/wp-seo.php' => 'Yoast SEO',
        'wordpress-seo/wp-seo.php' => 'Yoast SEO',
        'seo-by-rank-math/rank-math.php' => 'Rank Math SEO',
        'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO'
    ];
    
    $active_conflicts = [];
    foreach ($conflicting_plugins as $plugin_file => $plugin_name) {
        if (is_plugin_active($plugin_file)) {
            $active_conflicts[] = $plugin_name;
        }
    }
    
    if (!empty($active_conflicts)) {
        add_action('admin_notices', function() use ($active_conflicts) {
            printf(
                '<div class="notice notice-warning"><p><strong>SEO AI Master:</strong> %s %s. %s</p></div>',
                __('Konflikt mit aktivem SEO-Plugin erkannt:', 'seo-ai-master'),
                implode(', ', $active_conflicts),
                __('Für beste Ergebnisse empfehlen wir, nur ein SEO-Plugin zu verwenden.', 'seo-ai-master')
            );
        });
    }
});

/**
 * Plugin-Upgrade-Handler
 */
add_action('upgrader_process_complete', function($upgrader_object, $options) {
    if ($options['action'] === 'update' && $options['type'] === 'plugin') {
        if (isset($options['plugins']) && in_array(SEO_AI_MASTER_BASENAME, $options['plugins'])) {
            // Plugin wurde aktualisiert - Cache leeren
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // Transients löschen
            delete_transient('seo_ai_master_version_check');
            delete_transient('seo_ai_dashboard_stats_' . get_current_user_id());
            
            // Update-Hook für Migrations
            do_action('seo_ai_master_updated', SEO_AI_MASTER_VERSION);
        }
    }
}, 10, 2);

/**
 * Cleanup bei Plugin-Deaktivierung
 */
add_action('seo_ai_master_deactivate', function() {
    // Temporäre Daten bereinigen
    global $wpdb;
    
    // Transients löschen
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_seo_ai_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_seo_ai_%'");
    
    // Cache leeren
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Cron-Jobs entfernen
    wp_clear_scheduled_hook('seo_ai_master_daily_cleanup');
    wp_clear_scheduled_hook('seo_ai_master_weekly_report');
});

/**
 * Performance-Optimierung: Prefetch für externe Ressourcen
 */
add_action('admin_head', function() {
    $screen = get_current_screen();
    
    if ($screen && strpos($screen->id, 'seo-ai-master') !== false) {
        echo '<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//api.anthropic.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//api.openai.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//generativelanguage.googleapis.com">' . "\n";
    }
});

/**
 * Plugin-Informationen für Updates und Debugging
 */
if (!function_exists('seo_ai_master_get_plugin_info')) {
    function seo_ai_master_get_plugin_info() {
        return [
            'version' => SEO_AI_MASTER_VERSION,
            'path' => SEO_AI_MASTER_PATH,
            'url' => SEO_AI_MASTER_URL,
            'basename' => SEO_AI_MASTER_BASENAME,
            'file' => SEO_AI_MASTER_FILE,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'active_theme' => get_template(),
            'active_plugins' => get_option('active_plugins', [])
        ];
    }
}

// Plugin erfolgreich geladen - Signal für andere Plugins/Themes
do_action('seo_ai_master_loaded', SEO_AI_MASTER_VERSION);

// (NEU) Setze die Header im init-Hook, aber nur wenn noch keine Header gesendet wurden
add_action('init', function() {
    if (is_admin()) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_seo_ai = false;
        if ($screen && strpos($screen->id, 'seo-ai-master') !== false) {
            $is_seo_ai = true;
        } elseif (isset($_GET['page']) && strpos($_GET['page'], 'seo-ai-') === 0) {
            $is_seo_ai = true;
        }
        if ($is_seo_ai && !headers_sent()) {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self' https://api.anthropic.com https://api.openai.com https://generativelanguage.googleapis.com;");
            header("X-Content-Type-Options: nosniff");
            header("X-Frame-Options: SAMEORIGIN");
            header("Referrer-Policy: strict-origin-when-cross-origin");
        }
    }
});