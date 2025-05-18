<?php
/**
 * Plugin Name: Alenseo SEO Minimal
 * Plugin URI: https://imponi.ch
 * Description: Ein schlankes SEO-Plugin mit Claude API-Integration für WordPress
 * Version: 1.0.0
 * Author: Alenseo
 * Author URI: https://imponi.ch
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
            wp_mkdir_p($directory);
        }
    }
    
    // Flush Rewrite Rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'alenseo_minimal_activate');

/**
 * Plugin deaktivieren
 */
function alenseo_minimal_deactivate() {
    // Hier könnten Aufräumaktionen stattfinden
    
    // Flush Rewrite Rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'alenseo_minimal_deactivate');

/**
 * Plugin initialisieren
 */
function alenseo_minimal_init() {
    // Sprachdateien laden
    load_plugin_textdomain('alenseo', false, basename(dirname(__FILE__)) . '/languages');
    
    // Admin-Klasse laden und initialisieren, wenn im Admin-Bereich
    if (is_admin()) {
        require_once ALENSEO_MINIMAL_DIR . 'includes/minimal-admin.php';
        new Alenseo_Minimal_Admin();
        
        // AJAX-Handler laden
        require_once ALENSEO_MINIMAL_DIR . 'includes/alenseo-ajax-handlers.php';
        
        // Claude API AJAX-Handler laden, wenn die Datei existiert
        if (file_exists(ALENSEO_MINIMAL_DIR . 'includes/alenseo-claude-ajax.php')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/alenseo-claude-ajax.php';
        }
    }
}
add_action('plugins_loaded', 'alenseo_minimal_init');

/**
 * Admin-Assets laden
 */
function alenseo_minimal_admin_enqueue_scripts() {
    // Nur im Admin-Bereich laden
    if (!is_admin()) {
        return;
    }
    
    // Admin CSS
    wp_enqueue_style(
        'alenseo-admin-css',
        ALENSEO_MINIMAL_URL . 'assets/css/admin.css',
        array(),
        ALENSEO_MINIMAL_VERSION
    );
    
    // Admin JS
    wp_enqueue_script(
        'alenseo-admin-js',
        ALENSEO_MINIMAL_URL . 'assets/js/admin.js',
        array('jquery'),
        ALENSEO_MINIMAL_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'alenseo_minimal_admin_enqueue_scripts');

/**
 * Plugin-Einstellungslink im Plugin-Menü hinzufügen
 */
function alenseo_minimal_add_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=alenseo-minimal-settings') . '">' . __('Einstellungen', 'alenseo') . '</a>'
    );
    
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'alenseo_minimal_add_action_links');
