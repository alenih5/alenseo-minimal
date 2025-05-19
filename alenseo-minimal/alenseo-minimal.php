<?php
/**
 * Plugin Name: Alenseo SEO Minimal
 * Plugin URI: https://www.imponi.ch
 * Description: Ein schlankes SEO-Plugin mit Claude AI-Integration für WordPress
 * Version: 1.0.0
 * Author: Alen
 * Author URI: https://www.imponi.ch
 * Company: Imponi Bern-Worb GmbH
 * Text Domain: alenseo
 * Domain Path: /languages
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Verzeichnis und URL definieren
define('ALENSEO_MINIMAL_DIR', plugin_dir_path(__FILE__));
define('ALENSEO_MINIMAL_URL', plugin_dir_url(__FILE__));
define('ALENSEO_MINIMAL_VERSION', '1.0.0');

/**
 * Plugin aktivieren
 */
function alenseo_minimal_activate() {
    // Fehlerprotokollierung für Aktivierungsprobleme
    error_log("Alenseo SEO Minimal: Aktivierung gestartet");
    
    // Standardeinstellungen setzen, wenn noch keine vorhanden sind
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
    
    // Verzeichnisse erstellen, falls sie nicht existieren
    $directories = array(
        ALENSEO_MINIMAL_DIR . 'assets',
        ALENSEO_MINIMAL_DIR . 'assets/css',
        ALENSEO_MINIMAL_DIR . 'assets/js',
        ALENSEO_MINIMAL_DIR . 'includes',
        ALENSEO_MINIMAL_DIR . 'templates'
    );
    
    foreach ($directories as $directory) {
        if (!file_exists($directory)) {
            if (!wp_mkdir_p($directory)) {
                error_log("Alenseo SEO Minimal: Konnte Verzeichnis nicht erstellen: $directory");
            }
        }
    }
    
    // Flush Rewrite Rules
    flush_rewrite_rules();
    
    error_log("Alenseo SEO Minimal: Aktivierung abgeschlossen");
}
register_activation_hook(__FILE__, 'alenseo_minimal_activate');

/**
 * Plugin deaktivieren
 */
function alenseo_minimal_deactivate() {
    // Hier könnten Aufräumaktionen stattfinden
    
    // Flush Rewrite Rules
    flush_rewrite_rules();
    
    error_log("Alenseo SEO Minimal: Deaktivierung abgeschlossen");
}
register_deactivation_hook(__FILE__, 'alenseo_minimal_deactivate');

/**
 * Fehlerprotokollierung
 */
function alenseo_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

/**
 * Plugin initialisieren
 */
function alenseo_minimal_init() {
    try {
        // Sprachdateien laden
        load_plugin_textdomain('alenseo', false, basename(dirname(__FILE__)) . '/languages');
        
        // Admin-Klasse laden und initialisieren, wenn im Admin-Bereich
        if (is_admin()) {
            $admin_file = ALENSEO_MINIMAL_DIR . 'includes/minimal-admin.php';
            
            if (file_exists($admin_file)) {
                require_once $admin_file;
                $admin = new Alenseo_Minimal_Admin();
                
                // Claude API-Klasse laden, wenn die Datei existiert
                $claude_api_file = ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
                if (file_exists($claude_api_file)) {
                    require_once $claude_api_file;
                } else {
                    alenseo_log("Alenseo SEO Minimal: Claude API-Datei nicht gefunden: $claude_api_file");
                }
                
                // Content Optimizer-Klasse laden, wenn die Datei existiert
                $content_optimizer_file = ALENSEO_MINIMAL_DIR . 'includes/class-content-optimizer.php';
                if (file_exists($content_optimizer_file)) {
                    require_once $content_optimizer_file;
                } else {
                    alenseo_log("Alenseo SEO Minimal: Content Optimizer-Datei nicht gefunden: $content_optimizer_file");
                }
                
                // WPBakery-Integration laden, wenn die Datei existiert
                $wpbakery_file = ALENSEO_MINIMAL_DIR . 'includes/class-wpbakery-integration.php';
                if (file_exists($wpbakery_file)) {
                    require_once $wpbakery_file;
                    
                    // WPBakery-Integration initialisieren, wenn WPBakery aktiv ist
                    if (defined('WPB_VC_VERSION') || class_exists('WPBakeryVisualComposer')) {
                        $wpbakery_integration = new Alenseo_WPBakery_Integration();
                    }
                }
                
                // AJAX-Handler laden
                $ajax_handlers_file = ALENSEO_MINIMAL_DIR . 'includes/alenseo-ajax-handlers.php';
                if (file_exists($ajax_handlers_file)) {
                    require_once $ajax_handlers_file;
                } else {
                    alenseo_log("Alenseo SEO Minimal: AJAX-Handler-Datei nicht gefunden: $ajax_handlers_file");
                }
                
                // Claude API AJAX-Handler laden, wenn die Datei existiert
                $claude_ajax_file = ALENSEO_MINIMAL_DIR . 'includes/alenseo-claude-ajax.php';
                if (file_exists($claude_ajax_file)) {
                    require_once $claude_ajax_file;
                }
                
                // Erweiterte AJAX-Handler laden, wenn die Datei existiert
                $enhanced_ajax_file = ALENSEO_MINIMAL_DIR . 'includes/alenseo-enhanced-ajax.php';
                if (file_exists($enhanced_ajax_file)) {
                    require_once $enhanced_ajax_file;
                }
                
                // Dashboard-Klasse beim Init-Hook registrieren
                add_action('init', 'alenseo_register_dashboard');
            } else {
                alenseo_log("Alenseo SEO Minimal: Admin-Datei nicht gefunden: $admin_file");
            }
        }
    } catch (Exception $e) {
        alenseo_log("Alenseo SEO Minimal Fehler bei der Initialisierung: " . $e->getMessage());
    }
}
add_action('plugins_loaded', 'alenseo_minimal_init');

/**
 * Dashboard-Klasse registrieren
 */
function alenseo_register_dashboard() {
    // Prüfen, ob wir im Admin-Bereich sind
    if (!is_admin()) {
        return;
    }
    
    try {
        // Nur registrieren, wenn die Datei existiert
        $dashboard_file = ALENSEO_MINIMAL_DIR . 'includes/class-dashboard.php';
        if (file_exists($dashboard_file)) {
            require_once $dashboard_file;
            
            // Prüfen, ob die Klasse existiert, bevor eine Instanz erstellt wird
            if (class_exists('Alenseo_Dashboard')) {
                $dashboard = new Alenseo_Dashboard();
                alenseo_log("Alenseo SEO Minimal: Dashboard erfolgreich initialisiert");
            } else {
                alenseo_log("Alenseo SEO Minimal: Dashboard-Klasse existiert nicht, obwohl die Datei geladen wurde: $dashboard_file");
            }
        } else {
            alenseo_log("Alenseo SEO Minimal: Dashboard-Datei nicht gefunden: $dashboard_file");
        }
    } catch (Exception $e) {
        alenseo_log("Alenseo SEO Minimal Fehler bei der Dashboard-Registrierung: " . $e->getMessage());
    }
}

/**
 * Admin-Assets laden
 */
function alenseo_minimal_admin_enqueue_scripts($hook) {
    // Nur im Admin-Bereich laden
    if (!is_admin()) {
        return;
    }
    
    try {
        // Admin CSS
        if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/admin.css')) {
            wp_enqueue_style(
                'alenseo-admin-css',
                ALENSEO_MINIMAL_URL . 'assets/css/admin.css',
                array(),
                ALENSEO_MINIMAL_VERSION
            );
        }
        
        // Admin JS
        if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/admin.js')) {
            wp_enqueue_script(
                'alenseo-admin-js',
                ALENSEO_MINIMAL_URL . 'assets/js/admin.js',
                array('jquery'),
                ALENSEO_MINIMAL_VERSION,
                true
            );
        }
        
        // Page Detail CSS - nur auf der Optimizer-Seite laden
        if (strpos($hook, 'alenseo-optimizer') !== false && file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/page-detail.css')) {
            wp_enqueue_style(
                'alenseo-page-detail-css',
                ALENSEO_MINIMAL_URL . 'assets/css/page-detail.css',
                array(),
                ALENSEO_MINIMAL_VERSION
            );
        }
        
        // Page Detail JS - nur auf der Optimizer-Seite laden
        if (strpos($hook, 'alenseo-optimizer') !== false && file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/page-detail.js')) {
            wp_enqueue_script(
                'alenseo-page-detail-js',
                ALENSEO_MINIMAL_URL . 'assets/js/page-detail.js',
                array('jquery'),
                ALENSEO_MINIMAL_VERSION,
                true
            );
            
            // AJAX-Daten für JavaScript
            wp_localize_script('alenseo-page-detail-js', 'alenseoData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
                'messages' => array(
                    'optimizing' => __('Optimiere Inhalt...', 'alenseo'),
                    'success' => __('Optimierung erfolgreich!', 'alenseo'),
                    'error' => __('Fehler bei der Optimierung. Bitte versuche es erneut.', 'alenseo')
                )
            ));
        }
    } catch (Exception $e) {
        alenseo_log("Alenseo SEO Minimal Fehler beim Laden der Admin-Assets: " . $e->getMessage());
    }
}
add_action('admin_enqueue_scripts', 'alenseo_minimal_admin_enqueue_scripts');

/**
 * Plugin-Einstellungslink im Plugin-Menü hinzufügen
 */
function alenseo_minimal_add_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=alenseo-minimal-settings') . '">' . __('Einstellungen', 'alenseo') . '</a>',
        '<a href="' . admin_url('admin.php?page=alenseo-optimizer') . '">' . __('SEO-Optimierung', 'alenseo') . '</a>'
    );
    
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'alenseo_minimal_add_action_links');

/**
 * Meta-Tag-Ausgabe im Frontend
 */
function alenseo_output_meta_tags() {
    // Nur im Frontend
    if (is_admin()) {
        return;
    }
    
    // Nur für Einzelansichten
    if (!is_singular()) {
        return;
    }
    
    global $post;
    
    // Meta-Description abrufen
    $meta_description = get_post_meta($post->ID, '_alenseo_meta_description', true);
    
    // Wenn keine Meta-Description von Alenseo, dann nach anderen SEO-Plugins suchen
    if (empty($meta_description)) {
        // Yoast SEO
        $meta_description = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        
        // All in One SEO
        if (empty($meta_description)) {
            $meta_description = get_post_meta($post->ID, '_aioseo_description', true);
        }
        if (empty($meta_description)) {
            $meta_description = get_post_meta($post->ID, '_aioseop_description', true);
        }
        
        // Rank Math
        if (empty($meta_description)) {
            $meta_description = get_post_meta($post->ID, 'rank_math_description', true);
        }
        
        // SEOPress
        if (empty($meta_description)) {
            $meta_description = get_post_meta($post->ID, '_seopress_titles_desc', true);
        }
        
        // WPBakery
        if (empty($meta_description)) {
            $meta_description = get_post_meta($post->ID, 'vc_description', true);
        }
    }
    
    // Fallback: Excerpt verwenden
    if (empty($meta_description) && has_excerpt($post->ID)) {
        $meta_description = wp_strip_all_tags(get_the_excerpt($post->ID));
    }
    
    // Meta-Description ausgeben, wenn vorhanden
    if (!empty($meta_description)) {
        echo '<meta name="description" content="' . esc_attr($meta_description) . '" />' . "\n";
    }
    
    // Fokus-Keyword als Meta-Keywords ausgeben (optional)
    $focus_keyword = get_post_meta($post->ID, '_alenseo_keyword', true);
    if (!empty($focus_keyword)) {
        echo '<meta name="keywords" content="' . esc_attr($focus_keyword) . '" />' . "\n";
    }
}
add_action('wp_head', 'alenseo_output_meta_tags', 1);
