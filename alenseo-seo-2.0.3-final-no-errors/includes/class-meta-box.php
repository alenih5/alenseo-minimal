<?php
/**
 * Meta-Box-Klasse für Alenseo SEO
 *
 * Diese Klasse handhabt alle Meta-Box-Funktionen des Plugins
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
 * Die Meta-Box-Klasse
 */
class Alenseo_Meta_Box {
    
    /**
     * Initialisierung der Klasse
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Meta-Boxen hinzufügen
     */
    public function add_meta_boxes() {
        // Einstellungen laden
        $settings = get_option('alenseo_settings', array());
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        
        // Meta-Box für jede ausgewählte Post-Type hinzufügen
        foreach ($post_types as $post_type) {
            add_meta_box(
                'alenseo_seo_meta_box',
                __('Alenseo SEO Optimierung', 'alenseo'),
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Scripts und Styles für die Meta-Box laden
     */
    public function enqueue_scripts($hook) {
        global $post;
        
        // Nur auf der Post-Bearbeitungsseite laden
        if (!($hook == 'post.php' || $hook == 'post-new.php') || !is_object($post)) {
            return;
        }
        
        // Einstellungen laden
        $settings = get_option('alenseo_settings', array());
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        
        // Nur für ausgewählte Post-Types laden
        if (!in_array($post->post_type, $post_types)) {
            return;
        }
        
        // CSS laden
        wp_enqueue_style(
            'alenseo-meta-box-style',
            ALENSEO_MINIMAL_URL . 'assets/css/meta-box.css',
            array(),
            ALENSEO_MINIMAL_VERSION
        );
        
        // JS laden
        wp_enqueue_script(
            'alenseo-meta-box-script',
            ALENSEO_MINIMAL_URL . 'assets/js/meta-box.js',
            array('jquery'),
            ALENSEO_MINIMAL_VERSION,
            true
        );
        
        // Keyword-Generator-Scripts laden, wenn Claude API aktiv ist
        if (!empty($settings['claude_api_key'])) {
            wp_enqueue_script(
                'alenseo-keyword-generator',
                ALENSEO_MINIMAL_URL . 'assets/js/enhanced-keyword-generator.js',
                array('jquery'),
                ALENSEO_MINIMAL_VERSION,
                true
            );
            
            wp_enqueue_style(
                'alenseo-keyword-generator-style',
                ALENSEO_MINIMAL_URL . 'assets/css/enhanced-keyword-generator.css',
                array(),
                ALENSEO_MINIMAL_VERSION
            );
        }
        
        // Lokalisierung für JS
        wp_localize_script('alenseo-meta-box-script', 'alenseoData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
            'post_id' => $post->ID,
            'i18n' => array(
                'analyzing' => __('Analysiere...', 'alenseo'),
                'saving' => __('Speichern...', 'alenseo'),
                'success' => __('Erfolg!', 'alenseo'),
                'error' => __('Fehler!', 'alenseo')
            )
        ));
    }
    
    /**
     * Meta-Box rendern
     */
    public function render_meta_box($post) {
        $post_id = $post->ID;
        
        // Datenbank-Klasse für SEO-Daten verwenden, wenn verfügbar
        global $alenseo_database;
        
        // Keyword aus der Datenbank abrufen, falls verfügbar
        $keyword = get_post_meta($post_id, '_alenseo_keyword', true);
        if (empty($keyword) && isset($alenseo_database) && method_exists($alenseo_database, 'get_keywords')) {
            $keywords = $alenseo_database->get_keywords($post_id);
            if (!empty($keywords) && isset($keywords[0]['keyword'])) {
                $keyword = $keywords[0]['keyword'];
                // Sicherstellen, dass das Keyword auch im post_meta gespeichert wird für Abwärtskompatibilität
                update_post_meta($post_id, '_alenseo_keyword', $keyword);
            }
        }
        
        $meta_description = get_post_meta($post_id, '_alenseo_meta_description', true);
        
        // SEO-Score aus der Datenbank abrufen, falls verfügbar
        $seo_score = 0;
        $seo_status = 'unknown';
        
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_seo_score')) {
            $seo_data = $alenseo_database->get_seo_score($post_id);
            if ($seo_data && isset($seo_data['score'])) {
                $seo_score = $seo_data['score'];
                
                // SEO-Status basierend auf Score
                if ($seo_score >= 80) {
                    $seo_status = 'good';
                } elseif ($seo_score >= 50) {
                    $seo_status = 'ok';
                } else {
                    $seo_status = 'poor';
                }
                
                // Auch im post_meta für Abwärtskompatibilität speichern
                update_post_meta($post_id, '_alenseo_seo_score', $seo_score);
                update_post_meta($post_id, '_alenseo_seo_status', $seo_status);
            }
        } else {
            // Fallback zu post_meta, wenn Datenbank nicht verfügbar
            $seo_score = get_post_meta($post_id, '_alenseo_seo_score', true);
            $seo_status = get_post_meta($post_id, '_alenseo_seo_status', true);
        }
        
        // Claude API-Status prüfen
        $settings = get_option('alenseo_settings', array());
        $claude_api_active = !empty($settings['claude_api_key']);
        
        // Nonce für Sicherheit
        wp_nonce_field('alenseo_meta_box', 'alenseo_meta_box_nonce');
        
        // Template-Pfad
        $template_path = ALENSEO_MINIMAL_DIR . 'templates/alenseo-meta-box.php';
        
        // Falls vereinfachte Ansicht gewünscht ist
        if (apply_filters('alenseo_use_simplified_meta_box', false)) {
            $template_path = ALENSEO_MINIMAL_DIR . 'templates/alenseo-meta-box-simplified.php';
        }
        
        // Template laden und Variablen übergeben
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            _e('Meta-Box-Template konnte nicht geladen werden.', 'alenseo');
        }
    }
    
    /**
     * Meta-Box-Daten speichern
     */
    public function save_meta_box_data($post_id) {
        // Sicherheitscheck
        if (!isset($_POST['alenseo_meta_box_nonce']) || !wp_verify_nonce($_POST['alenseo_meta_box_nonce'], 'alenseo_meta_box')) {
            return;
        }
        
        // Benutzerberechtigungen prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Auto-Save überspringen
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Keyword speichern
        if (isset($_POST['alenseo_keyword'])) {
            $keyword = sanitize_text_field($_POST['alenseo_keyword']);
            update_post_meta($post_id, '_alenseo_keyword', $keyword);
            
            // Keyword auch in der Datenbank speichern, wenn verfügbar
            global $alenseo_database;
            if (isset($alenseo_database) && method_exists($alenseo_database, 'save_keyword') && !empty($keyword)) {
                $alenseo_database->save_keyword($post_id, $keyword);
            }
        }
        
        // Meta-Description speichern
        if (isset($_POST['alenseo_meta_description'])) {
            $meta_description = sanitize_textarea_field($_POST['alenseo_meta_description']);
            update_post_meta($post_id, '_alenseo_meta_description', $meta_description);
        }
        
        // Analyse durchführen, wenn Keyword gesetzt wurde
        $keyword = get_post_meta($post_id, '_alenseo_keyword', true);
        if (!empty($keyword)) {
            if (class_exists('Alenseo_Enhanced_Analysis') && apply_filters('alenseo_use_enhanced_analysis', false)) {
                $analyzer = new Alenseo_Enhanced_Analysis();
                $results = $analyzer->analyze_post($post_id);
                
                // SEO-Score in der Datenbank speichern, wenn Datenbank verfügbar
                if (isset($alenseo_database) && method_exists($alenseo_database, 'save_seo_score') && isset($results['score'])) {
                    $detailed_scores = array(
                        'meta_title' => isset($results['components']['title_score']) ? $results['components']['title_score'] : 0,
                        'meta_description' => isset($results['components']['description_score']) ? $results['components']['description_score'] : 0,
                        'content' => isset($results['components']['content_score']) ? $results['components']['content_score'] : 0,
                        'headings' => isset($results['components']['headings_score']) ? $results['components']['headings_score'] : 0
                    );
                    
                    $alenseo_database->save_seo_score($post_id, $results['score'], $detailed_scores, 'completed');
                }
            } elseif (class_exists('Alenseo_Minimal_Analysis')) {
                $analyzer = new Alenseo_Minimal_Analysis();
                $results = $analyzer->analyze_post($post_id);
                
                // Auch für minimale Analyse Ergebnisse in der Datenbank speichern
                if (isset($alenseo_database) && method_exists($alenseo_database, 'save_seo_score') && isset($results['score'])) {
                    $alenseo_database->save_seo_score($post_id, $results['score'], array(), 'completed');
                }
            }
        }
    }
}
