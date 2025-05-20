<?php
/**
 * Admin-Klasse für Alenseo SEO
 *
 * Diese Datei enthält die Admin-Funktionalitäten des Plugins
 * 
 * @link       https://www.imponi.ch
 * @since      1.0.0
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Die Admin-Klasse
 */
class Alenseo_Minimal_Admin {
    
    /**
     * Initialisierung der Klasse
     */
    public function __construct() {
        // Meta-Box hinzufügen
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Meta-Box-Daten speichern
        add_action('save_post', array($this, 'save_meta_box_data'));
        
        // Admin-Assets laden
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Meta-Box hinzufügen 
     * Deaktiviert, um Dopplung mit class-meta-box.php zu vermeiden
     */
    public function add_meta_boxes() {
        // Deaktiviert wegen Dopplung mit class-meta-box.php
        return;
        
        // Einstellungen abrufen
        $settings = get_option('alenseo_settings', array());
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        
        // Meta-Box zu allen ausgewählten Post-Typen hinzufügen
        foreach ($post_types as $post_type) {
            // Deaktiviert
            /*
            add_meta_box(
                'alenseo_meta_box',
                __('Alenseo SEO', 'alenseo'),
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
            */
        }
    }
    
    /**
     * Meta-Box rendern
     * 
     * @param WP_Post $post Das aktuelle Post-Objekt
     */
    public function render_meta_box($post) {
        // Nonce für Sicherheitsüberprüfung erstellen
        wp_nonce_field('alenseo_meta_box', 'alenseo_meta_box_nonce');
        
        // Meta-Box-Template laden
        $template_file = ALENSEO_MINIMAL_DIR . 'templates/alenseo-meta-box.php';
        
        if (file_exists($template_file)) {
            // Daten für das Template vorbereiten
            $post_id = $post->ID;
            $keyword = get_post_meta($post->ID, '_alenseo_keyword', true);
            $meta_description = get_post_meta($post->ID, '_alenseo_meta_description', true);
            $seo_score = get_post_meta($post->ID, '_alenseo_seo_score', true);
            $seo_status = get_post_meta($post->ID, '_alenseo_seo_status', true);
            
            // Claude API-Status prüfen
            $settings = get_option('alenseo_settings', array());
            $claude_api_active = !empty($settings['claude_api_key']);
            
            // Template einbinden
            include $template_file;
        } else {
            // Fallback, wenn Template nicht gefunden wird
            echo '<p>' . __('Meta-Box-Template konnte nicht geladen werden.', 'alenseo') . '</p>';
        }
    }
    
    /**
     * Meta-Box-Daten speichern
     * 
     * @param int $post_id Die Post-ID
     */
    public function save_meta_box_data($post_id) {
        // Autosave prüfen
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Nonce prüfen
        if (!isset($_POST['alenseo_meta_box_nonce']) || !wp_verify_nonce($_POST['alenseo_meta_box_nonce'], 'alenseo_meta_box')) {
            return;
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Keyword speichern
        if (isset($_POST['alenseo_keyword'])) {
            update_post_meta($post_id, '_alenseo_keyword', sanitize_text_field($_POST['alenseo_keyword']));
        }
        
        // Meta-Description speichern
        if (isset($_POST['alenseo_meta_description'])) {
            update_post_meta($post_id, '_alenseo_meta_description', sanitize_textarea_field($_POST['alenseo_meta_description']));
        }
        
        // SEO-Analyse durchführen, wenn ein Keyword gesetzt ist
        $keyword = isset($_POST['alenseo_keyword']) ? sanitize_text_field($_POST['alenseo_keyword']) : '';
        if (!empty($keyword) && class_exists('Alenseo_Minimal_Analysis')) {
            $analyzer = new Alenseo_Minimal_Analysis();
            $analyzer->analyze_post($post_id);
        }
    }
    
    /**
     * Admin-Menü hinzufügen
     * Hinweis: Diese Funktion wird nur ausgeführt, wenn die Dashboard-Klasse nicht geladen ist
     */
    public function add_admin_menu() {
        // Prüfen, ob bereits ein Menüpunkt existiert (z.B. durch Dashboard-Klasse)
        global $submenu;
        if (isset($submenu['alenseo-optimizer'])) {
            return;
        }
        
        // Admin-Menüpunkt hinzufügen
        add_menu_page(
            __('Alenseo SEO', 'alenseo'),
            __('Alenseo SEO', 'alenseo'),
            'manage_options',
            'alenseo-minimal-settings',
            array($this, 'render_settings_page'),
            'dashicons-chart-bar',
            80
        );
    }
    
    /**
     * Einstellungsseite rendern
     */
    public function render_settings_page() {
        // Einstellungs-Template laden
        $template_file = ALENSEO_MINIMAL_DIR . 'templates/settings-page.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // Fallback, wenn Template nicht gefunden wird
            echo '<div class="wrap"><h1>' . __('Alenseo SEO Einstellungen', 'alenseo') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Einstellungen-Template konnte nicht geladen werden.', 'alenseo') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Admin-Assets laden
     * 
     * @param string $hook Die aktuelle Admin-Seite
     */
    public function enqueue_admin_assets($hook) {
        // Meta-Box-Assets laden, wenn wir auf einer Post-Bearbeitungsseite sind
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            // Meta-Box CSS
            if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/meta-box.css')) {
                wp_enqueue_style(
                    'alenseo-meta-box-css',
                    ALENSEO_MINIMAL_URL . 'assets/css/meta-box.css',
                    array(),
                    ALENSEO_MINIMAL_VERSION
                );
            }
            
            // Meta-Box JS
            if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/meta-box.js')) {
                wp_enqueue_script(
                    'alenseo-meta-box-js',
                    ALENSEO_MINIMAL_URL . 'assets/js/meta-box.js',
                    array('jquery'),
                    ALENSEO_MINIMAL_VERSION,
                    true
                );
                
                // AJAX-URL und Nonce für JavaScript
                wp_localize_script('alenseo-meta-box-js', 'alenseoData', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('alenseo_ajax_nonce')
                ));
            }
        }
        
        // Plugin-Einstellungsseite-Assets laden
        if ('toplevel_page_alenseo-minimal-settings' === $hook || strpos($hook, 'alenseo-settings') !== false) {
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
                
                // AJAX-URL und Nonce für JavaScript
                wp_localize_script('alenseo-admin-js', 'alenseoAdminData', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
                    'messages' => array(
                        'apiTestSuccess' => __('API-Test erfolgreich!', 'alenseo'),
                        'apiTestFailed' => __('API-Test fehlgeschlagen: ', 'alenseo'),
                        'apiTesting' => __('API wird getestet...', 'alenseo'),
                    )
                ));
            }
        }
    }
}
