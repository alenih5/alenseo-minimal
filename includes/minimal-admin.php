<?php
/**
 * Admin-Klasse mit verbesserter Dashboard-Integration
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
        // Fehlerprotokollierung für Debugging
        if (function_exists('alenseo_log')) {
            alenseo_log("Alenseo Admin: Konstruktor wird initialisiert");
        }
        
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
        
        if (function_exists('alenseo_log')) {
            alenseo_log("Alenseo Admin: Konstruktor abgeschlossen");
        }
    }

    /**
     * Admin-Menü initialisieren
     */
    public function init_admin_menu() {
        try {
            // Hauptmenüpunkt
            add_menu_page(
                __('Alenseo SEO', 'alenseo'),            // Seitentitel
                __('Alenseo SEO', 'alenseo'),            // Menütitel
                'manage_options',                         // Erforderliche Berechtigung
                'alenseo-minimal',                       // Menü-Slug
                array($this, 'render_plugin_dashboard'),  // Callback für die Hauptseite
                'dashicons-superhero-alt',               // Icon
                80                                       // Position
            );
            
            // Einstellungen
            add_submenu_page(
                'alenseo-minimal',                       // Eltern-Slug
                __('Einstellungen', 'alenseo'),           // Seitentitel
                __('Einstellungen', 'alenseo'),           // Menütitel
                'manage_options',                         // Erforderliche Berechtigung
                'alenseo-minimal-settings',              // Menü-Slug
                array($this, 'render_settings_page')      // Callback-Funktion
            );
            
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Admin-Menü erfolgreich initialisiert");
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Fehler beim Initialisieren des Admin-Menüs: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Hauptplugin-Dashboard rendern
     */
    public function render_plugin_dashboard() {
        // Einfache Weiterleitung zur Einstellungsseite als Fallback
        ?>
        <div class="wrap">
            <h1><?php _e('Alenseo SEO Plugin', 'alenseo'); ?></h1>
            <div class="card">
                <h2><?php _e('Willkommen bei Alenseo SEO', 'alenseo'); ?></h2>
                <p><?php _e('Optimiere deine WordPress-Inhalte mit KI-gestützter SEO-Analyse und -Optimierung.', 'alenseo'); ?></p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=alenseo-minimal-settings'); ?>" class="button button-primary"><?php _e('Einstellungen', 'alenseo'); ?></a>
                    <a href="<?php echo admin_url('admin.php?page=alenseo-optimizer'); ?>" class="button"><?php _e('SEO-Optimierung', 'alenseo'); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Admin-Scripts und -Styles registrieren
     */
    public function enqueue_admin_scripts($hook) {
        try {
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
                $css_file = ALENSEO_MINIMAL_DIR . 'assets/css/enhanced-keyword-generator.css';
                if (file_exists($css_file)) {
                    wp_enqueue_style(
                        'alenseo-keyword-generator',
                        ALENSEO_MINIMAL_URL . 'assets/css/enhanced-keyword-generator.css',
                        array(),
                        ALENSEO_MINIMAL_VERSION
                    );
                } else {
                    if (function_exists('alenseo_log')) {
                        alenseo_log("Alenseo Admin: CSS-Datei nicht gefunden: $css_file");
                    }
                }
                
                // Keyword-Generator-Script
                $js_file = ALENSEO_MINIMAL_DIR . 'assets/js/enhanced-keyword-generator.js';
                if (file_exists($js_file)) {
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
                } else {
                    if (function_exists('alenseo_log')) {
                        alenseo_log("Alenseo Admin: JS-Datei nicht gefunden: $js_file");
                    }
                }
            }
            
            // Einstellungsseite-spezifische Scripts und Styles
            if ('alenseo-minimal_page_alenseo-minimal-settings' === $hook) {
                // Einstellungsseite-Styles
                $settings_css_file = ALENSEO_MINIMAL_DIR . 'assets/css/settings.css';
                if (file_exists($settings_css_file)) {
                    wp_enqueue_style(
                        'alenseo-settings-style',
                        ALENSEO_MINIMAL_URL . 'assets/css/settings.css',
                        array(),
                        ALENSEO_MINIMAL_VERSION
                    );
                }
                
                // Einstellungsseite-Script
                $settings_js_file = ALENSEO_MINIMAL_DIR . 'assets/js/settings.js';
                if (file_exists($settings_js_file)) {
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
            
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Admin-Scripts erfolgreich geladen für Hook: $hook");
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Fehler beim Laden der Admin-Scripts: " . $e->getMessage());
            }
        }
    }

    /**
     * Meta-Box hinzufügen
     */
    public function add_meta_boxes() {
        try {
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
            
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Meta-Boxen erfolgreich hinzugefügt");
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Fehler beim Hinzufügen der Meta-Boxen: " . $e->getMessage());
            }
        }
    }

    /**
     * Meta-Box rendern
     */
    public function render_meta_box($post) {
        try {
            // Nonce für Sicherheit
            wp_nonce_field('alenseo_meta_box', 'alenseo_meta_box_nonce');
            
            // Aktuelle SEO-Daten abrufen
            $seo_data = $this->get_seo_data($post->ID);
            
            // Meta-Box-Template laden
            $template_file = ALENSEO_MINIMAL_DIR . 'templates/alenseo-meta-box-simplified.php';
            
            if (file_exists($template_file)) {
                include_once $template_file;
            } else {
                // Fallback, wenn das Template nicht gefunden wird
                echo '<div class="notice notice-error inline"><p>';
                echo esc_html__('Fehler: Meta-Box-Template konnte nicht geladen werden.', 'alenseo');
                echo '</p></div>';
                
                // Grundlegende Funktionalität bereitstellen
                echo '<div class="alenseo-meta-box">';
                echo '<div class="alenseo-field">';
                echo '<label for="alenseo_focus_keyword">' . esc_html__('Fokus-Keyword', 'alenseo') . '</label>';
                echo '<input type="text" id="alenseo_focus_keyword" name="alenseo_focus_keyword" value="';
                echo esc_attr(get_post_meta($post->ID, '_alenseo_keyword', true));
                echo '">';
                echo '</div>';
                echo '</div>';
                
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Admin: Verwende Fallback-Template, da Template nicht gefunden wurde: $template_file");
                }
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Fehler beim Rendern der Meta-Box: " . $e->getMessage());
            }
            
            // Fehlermeldung anzeigen
            echo '<div class="notice notice-error inline"><p>';
            echo esc_html__('Fehler beim Laden der Alenseo SEO Meta-Box: ', 'alenseo') . esc_html($e->getMessage());
            echo '</p></div>';
        }
    }

    /**
     * Meta-Box-Daten speichern
     */
    public function save_meta_box_data($post_id) {
        try {
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
                
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Admin: Keyword erfolgreich gespeichert für Post ID: $post_id");
                }
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Fehler beim Speichern der Meta-Box-Daten: " . $e->getMessage());
            }
        }
    }

    /**
     * SEO-Daten für einen Beitrag abrufen
     */
    public function get_seo_data($post_id) {
        try {
            // Fokus-Keyword abrufen
            $focus_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
            
            // SEO-Score abrufen
            $seo_score = get_post_meta($post_id, '_alenseo_seo_score', true);
            
            // Wenn kein Score vorhanden ist und ein Keyword gesetzt ist, analysieren
            if (empty($seo_score) && !empty($focus_keyword)) {
                // Analysis-Klasse laden
                $analysis_file = ALENSEO_MINIMAL_DIR . 'includes/class-minimal-analysis.php';
                
                if (file_exists($analysis_file)) {
                    require_once $analysis_file;
                    
                    if (class_exists('Alenseo_Minimal_Analysis')) {
                        $analysis = new Alenseo_Minimal_Analysis();
                        
                        // Analyse durchführen und SEO-Daten aktualisieren
                        $seo_data = $analysis->get_seo_data($post_id);
                        $seo_score = $seo_data['seo_score'];
                        
                        if (function_exists('alenseo_log')) {
                            alenseo_log("Alenseo Admin: Analyse erfolgreich durchgeführt für Post ID: $post_id");
                        }
                    } else {
                        if (function_exists('alenseo_log')) {
                            alenseo_log("Alenseo Admin: Analysis-Klasse nicht gefunden, obwohl die Datei geladen wurde: $analysis_file");
                        }
                    }
                } else {
                    if (function_exists('alenseo_log')) {
                        alenseo_log("Alenseo Admin: Analysis-Datei nicht gefunden: $analysis_file");
                    }
                }
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
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Fehler beim Abrufen der SEO-Daten: " . $e->getMessage());
            }
            
            // Standardwerte zurückgeben
            return array(
                'seo_score' => 0,
                'title_score' => 0,
                'title_message' => '',
                'content_score' => 0,
                'content_message' => '',
                'url_score' => 0,
                'url_message' => '',
                'meta_description_score' => 0,
                'meta_description_message' => ''
            );
        }
    }

    /**
     * Einstellungsseite rendern
     */
    public function render_settings_page() {
        try {
            // Einstellungsseiten-Template laden
            $template_file = ALENSEO_MINIMAL_DIR . 'templates/settings-page.php';
            
            if (file_exists($template_file)) {
                include_once $template_file;
                
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Admin: Einstellungsseite erfolgreich gerendert");
                }
            } else {
                // Fallback-Einstellungsseite anzeigen
                echo '<div class="wrap">';
                echo '<h1>' . esc_html__('Alenseo SEO Einstellungen', 'alenseo') . '</h1>';
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('Fehler: Einstellungsseiten-Template konnte nicht geladen werden.', 'alenseo');
                echo '</p></div>';
                
                // Einfaches Formular anzeigen
                echo '<form method="post" action="options.php">';
                settings_fields('alenseo_options');
                echo '<table class="form-table">';
                echo '<tr><th scope="row">' . esc_html__('Claude API-Schlüssel', 'alenseo') . '</th>';
                echo '<td><input type="password" name="alenseo_settings[claude_api_key]" value="';
                echo esc_attr(isset($this->settings['claude_api_key']) ? $this->settings['claude_api_key'] : '');
                echo '" class="regular-text"></td></tr>';
                echo '</table>';
                submit_button(__('Einstellungen speichern', 'alenseo'));
                echo '</form>';
                echo '</div>';
                
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Admin: Verwende Fallback-Einstellungsseite, da Template nicht gefunden wurde: $template_file");
                }
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Admin: Fehler beim Rendern der Einstellungsseite: " . $e->getMessage());
            }
            
            // Fehlermeldung anzeigen
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Alenseo SEO Einstellungen', 'alenseo') . '</h1>';
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Fehler beim Laden der Einstellungsseite: ', 'alenseo') . esc_html($e->getMessage());
            echo '</p></div>';
            echo '</div>';
        }
    }
}
