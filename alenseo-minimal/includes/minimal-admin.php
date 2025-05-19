<?php
/**
 * Aktualisierte Admin-Klasse mit zentralem Dashboard und Integration
 *
 * @link       https://imponi.ch
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
 * Die Admin-Klasse für das Alenseo SEO Plugin
 */
class Alenseo_Minimal_Admin {

    /**
     * Plugin-Einstellungen
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Die Plugin-Einstellungen.
     */
    private $settings;

    /**
     * Konstruktor
     */
    public function __construct() {
        // Einstellungen laden
        $this->settings = get_option('alenseo_settings', array());
        
        // Admin-Menü initialisieren
        add_action('admin_menu', array($this, 'init_admin_menu'));
        
        // Admin-Scripts und -Styles registrieren
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Meta-Box hinzufügen
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Post speichern
        add_action('save_post', array($this, 'save_meta_box_data'));
        
        // Dashboard Tabelle initialisieren
        add_action('current_screen', array($this, 'init_dashboard_table'));
    }

    /**
     * Admin-Menü initialisieren
     */
    public function init_admin_menu() {
        // Hauptmenüpunkt
        add_menu_page(
            __('Alenseo SEO', 'alenseo'),            // Seitentitel
            __('Alenseo SEO', 'alenseo'),            // Menütitel
            'manage_options',                         // Erforderliche Berechtigung
            'alenseo-minimal',                       // Menü-Slug
            null,                                    // Callback (null, da wir Untermenüs verwenden)
            'dashicons-superhero-alt',               // Icon
            80                                       // Position
        );
        
        // Dashboard (wird von der Dashboard-Klasse hinzugefügt)
        
        // Einstellungen
        add_submenu_page(
            'alenseo-minimal',                       // Eltern-Slug
            __('Einstellungen', 'alenseo'),           // Seitentitel
            __('Einstellungen', 'alenseo'),           // Menütitel
            'manage_options',                         // Erforderliche Berechtigung
            'alenseo-minimal-settings',              // Menü-Slug
            array($this, 'render_settings_page')      // Callback-Funktion
        );
    }

    /**
     * Admin-Scripts und -Styles registrieren
     */
    public function enqueue_admin_scripts($hook) {
        // Hauptstile für alle Admin-Seiten
        wp_enqueue_style(
            'alenseo-admin-style',
            ALENSEO_MINIMAL_URL . 'assets/css/admin.css',
            array(),
            ALENSEO_MINIMAL_VERSION
        );
        
        // Post-Editor-spezifische Scripts und Styles
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            // Keyword-Generator-Stil
            wp_enqueue_style(
                'alenseo-keyword-generator',
                ALENSEO_MINIMAL_URL . 'assets/css/enhanced-keyword-generator.css',
                array(),
                ALENSEO_MINIMAL_VERSION
            );
            
            // Keyword-Generator-Script
            wp_enqueue_script(
                'alenseo-keyword-generator',
                ALENSEO_MINIMAL_URL . 'assets/js/enhanced-keyword-generator.js',
                array('jquery'),
                ALENSEO_MINIMAL_VERSION,
                true
            );
            
            // AJAX-Daten
            wp_localize_script('alenseo-keyword-generator', 'alenseoData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alenseo_ajax_nonce')
            ));
        }
        
        // Einstellungsseite-spezifische Scripts und Styles
        if ('alenseo-minimal_page_alenseo-minimal-settings' === $hook) {
            // Einstellungsseite-Styles
            wp_enqueue_style(
                'alenseo-settings-style',
                ALENSEO_MINIMAL_URL . 'assets/css/settings.css',
                array(),
                ALENSEO_MINIMAL_VERSION
            );
            
            // Einstellungsseite-Script
            wp_enqueue_script(
                'alenseo-settings-script',
                ALENSEO_MINIMAL_URL . 'assets/js/settings.js',
                array('jquery'),
                ALENSEO_MINIMAL_VERSION,
                true
            );
            
            // AJAX-Daten
            wp_localize_script('alenseo-settings-script', 'alenseoData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alenseo_ajax_nonce')
            ));
        }
    }

    /**
     * Meta-Box hinzufügen
     */
    public function add_meta_boxes() {
        // Relevante Post-Typen abrufen
        $post_types = isset($this->settings['post_types']) ? $this->settings['post_types'] : array('post', 'page');
        
        // Meta-Box für jeden relevanten Post-Typ hinzufügen
        foreach ($post_types as $post_type) {
            add_meta_box(
                'alenseo_meta_box',                  // Meta-Box-ID
                __('Alenseo SEO', 'alenseo'),         // Titel
                array($this, 'render_meta_box'),      // Callback-Funktion
                $post_type,                          // Post-Typ
                'normal',                            // Kontext (normal, side, advanced)
                'high'                               // Priorität
            );
        }
    }

    /**
     * Meta-Box rendern
     */
    public function render_meta_box($post) {
        // Nonce für Sicherheit
        wp_nonce_field('alenseo_meta_box', 'alenseo_meta_box_nonce');
        
        // Aktuelle SEO-Daten abrufen
        $seo_data = $this->get_seo_data($post->ID);
        
        // Meta-Box-Template laden
        include_once ALENSEO_MINIMAL_DIR . 'templates/alenseo-meta-box-simplified.php';
    }

    /**
     * Meta-Box-Daten speichern
     */
    public function save_meta_box_data($post_id) {
        // Sicherheitsüberprüfungen
        
        // Nonce prüfen
        if (!isset($_POST['alenseo_meta_box_nonce']) || !wp_verify_nonce($_POST['alenseo_meta_box_nonce'], 'alenseo_meta_box')) {
            return;
        }
        
        // Autosave überspringen
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Fokus-Keyword speichern
        if (isset($_POST['alenseo_focus_keyword'])) {
            $keyword = sanitize_text_field($_POST['alenseo_focus_keyword']);
            update_post_meta($post_id, '_alenseo_keyword', $keyword);
            
            // Auch in WPBakery speichern, falls vorhanden
            update_post_meta($post_id, 'vc_seo_keyword', $keyword);
        }
    }

    /**
     * SEO-Daten für einen Beitrag abrufen
     */
    public function get_seo_data($post_id) {
        // Fokus-Keyword abrufen
        $focus_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
        
        // SEO-Score abrufen
        $seo_score = get_post_meta($post_id, '_alenseo_seo_score', true);
        
        // Wenn kein Score vorhanden ist und ein Keyword gesetzt ist, analysieren
        if (empty($seo_score) && !empty($focus_keyword)) {
            // Analysis-Klasse laden
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-minimal-analysis.php';
            $analysis = new Alenseo_Minimal_Analysis($post_id, $focus_keyword);
            
            // Analyse durchführen und SEO-Daten aktualisieren
            $analysis_result = $analysis->analyze();
            $seo_score = $analysis_result['seo_score'];
        }
        
        // Detaillierte SEO-Daten abrufen
        $title_score = get_post_meta($post_id, '_alenseo_title_score', true);
        $title_message = get_post_meta($post_id, '_alenseo_title_message', true);
        $content_score = get_post_meta($post_id, '_alenseo_content_score', true);
        $content_message = get_post_meta($post_id, '_alenseo_content_message', true);
        $url_score = get_post_meta($post_id, '_alenseo_url_score', true);
        $url_message = get_post_meta($post_id, '_alenseo_url_message', true);
        $meta_description_score = get_post_meta($post_id, '_alenseo_meta_description_score', true);
        $meta_description_message = get_post_meta($post_id, '_alenseo_meta_description_message', true);
        
        // SEO-Daten zusammenstellen
        $seo_data = array(
            'seo_score' => $seo_score ? $seo_score : 0,
            'title_score' => $title_score ? $title_score : 0,
            'title_message' => $title_message ? $title_message : '',
            'content_score' => $content_score ? $content_score : 0,
            'content_message' => $content_message ? $content_message : '',
            'url_score' => $url_score ? $url_score : 0,
            'url_message' => $url_message ? $url_message : '',
            'meta_description_score' => $meta_description_score ? $meta_description_score : 0,
            'meta_description_message' => $meta_description_message ? $meta_description_message : ''
        );
        
        return $seo_data;
    }

    /**
     * Einstellungsseite rendern
     */
    public function render_settings_page() {
        // Einstellungsseiten-Template laden
        include_once ALENSEO_MINIMAL_DIR . 'templates/settings-page.php';
    }

    /**
     * Dashboard-Tabelle initialisieren
     */
    public function init_dashboard_table($current_screen) {
        // Nur auf der Dashboard-Seite laden
        if ('alenseo-minimal_page_alenseo-optimizer' !== $current_screen->id) {
            return;
        }
        
        // Dashboard-Klasse laden
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-dashboard.php';
        
        // Dashboard-Instanz erstellen
        $dashboard = new Alenseo_Dashboard();
    }
}
