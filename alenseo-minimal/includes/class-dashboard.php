<?php
/**
 * Aktualisierte Dashboard-Klasse mit verbesserten Fehlerchecks
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
 * Die Dashboard-Klasse für das Alenseo SEO Plugin
 */
class Alenseo_Dashboard {

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
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Fehlerprotokollierung für Debugging
        if (function_exists('alenseo_log')) {
            alenseo_log("Alenseo Dashboard: Konstruktor wird initialisiert");
        }
        
        // Einstellungen laden
        $this->settings = get_option('alenseo_settings', array());
        
        // Admin-Hooks für das Dashboard registrieren
        $this->init_hooks();
        
        if (function_exists('alenseo_log')) {
            alenseo_log("Alenseo Dashboard: Konstruktor abgeschlossen");
        }
    }

    /**
     * Admin-Hooks registrieren
     */
    private function init_hooks() {
        try {
            // Hauptmenüpunkt für das Dashboard hinzufügen
            add_action('admin_menu', array($this, 'add_dashboard_menu'));
            
            // AJAX-Hooks für Bulk-Aktionen
            add_action('wp_ajax_alenseo_bulk_generate_keywords', array($this, 'ajax_bulk_generate_keywords'));
            add_action('wp_ajax_alenseo_bulk_optimize_titles', array($this, 'ajax_bulk_optimize_titles'));
            add_action('wp_ajax_alenseo_bulk_optimize_meta_descriptions', array($this, 'ajax_bulk_optimize_meta_descriptions'));
            add_action('wp_ajax_alenseo_bulk_optimize_content', array($this, 'ajax_bulk_optimize_content'));
            add_action('wp_ajax_alenseo_bulk_optimize_all', array($this, 'ajax_bulk_optimize_all'));
            add_action('wp_ajax_alenseo_get_content_stats', array($this, 'ajax_get_content_stats'));
            
            // Assets für das Dashboard laden
            add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
            
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Admin-Hooks erfolgreich registriert");
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler beim Registrieren der Admin-Hooks: " . $e->getMessage());
            }
        }
    }

    /**
     * Dashboard-Menü hinzufügen
     */
    public function add_dashboard_menu() {
        try {
            // Prüfen, ob der Admin-Bereich geladen ist
            if (!is_admin()) {
                return;
            }
            
            // Dashboard als Hauptseite des Plugins registrieren
            add_submenu_page(
                'alenseo-minimal',                // Eltern-Slug
                __('SEO-Optimierung', 'alenseo'), // Seitentitel
                __('SEO-Optimierung', 'alenseo'), // Menütitel
                'edit_posts',                     // Erforderliche Berechtigung
                'alenseo-optimizer',              // Slug dieser Seite
                array($this, 'render_dashboard_page') // Callback-Funktion
            );
            
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Dashboard-Menü erfolgreich hinzugefügt");
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler beim Hinzufügen des Dashboard-Menüs: " . $e->getMessage());
            }
        }
    }

    /**
     * Dashboard-Assets laden
     *
     * @param string $hook Der aktuelle Admin-Hook
     */
    public function enqueue_dashboard_assets($hook) {
        try {
            // Nur auf der Dashboard-Seite laden
            if ('alenseo-minimal_page_alenseo-optimizer' !== $hook) {
                return;
            }
            
            // CSS für das Dashboard laden
            if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/dashboard.css')) {
                wp_enqueue_style(
                    'alenseo-dashboard-css',
                    ALENSEO_MINIMAL_URL . 'assets/css/dashboard.css',
                    array(),
                    ALENSEO_MINIMAL_VERSION
                );
                
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Dashboard: Dashboard CSS erfolgreich geladen");
                }
            } else {
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Dashboard: Dashboard CSS-Datei nicht gefunden: " . ALENSEO_MINIMAL_DIR . 'assets/css/dashboard.css');
                }
            }
            
            // JS für die Bulk-Optimierung laden
            if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/bulk-optimizer.js')) {
                wp_enqueue_script(
                    'alenseo-bulk-optimizer',
                    ALENSEO_MINIMAL_URL . 'assets/js/bulk-optimizer.js',
                    array('jquery'),
                    ALENSEO_MINIMAL_VERSION,
                    true
                );
                
                // AJAX-Daten für JavaScript
                wp_localize_script('alenseo-bulk-optimizer', 'alenseoData', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
                    'messages' => array(
                        'selectContent' => __('Bitte wähle mindestens einen Inhalt aus.', 'alenseo'),
                        'processingItems' => __('Verarbeite Elemente...', 'alenseo'),
                        'allDone' => __('Alle Optimierungen abgeschlossen!', 'alenseo'),
                        'error' => __('Fehler bei der Verarbeitung. Bitte versuche es erneut.', 'alenseo')
                    )
                ));
                
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Dashboard: Bulk Optimizer JS erfolgreich geladen");
                }
            } else {
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Dashboard: Bulk Optimizer JS-Datei nicht gefunden: " . ALENSEO_MINIMAL_DIR . 'assets/js/bulk-optimizer.js');
                }
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler beim Laden der Dashboard-Assets: " . $e->getMessage());
            }
        }
    }

    /**
     * Dashboard-Seite rendern
     */
    public function render_dashboard_page() {
        try {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Rendere Dashboard-Seite");
            }
            
            // Template für die Dashboard-Seite laden
            $template_file = ALENSEO_MINIMAL_DIR . 'templates/dashboard-page.php';
            
            if (file_exists($template_file)) {
                include_once $template_file;
                
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Dashboard: Dashboard-Template erfolgreich geladen");
                }
            } else {
                // Fallback, wenn das Template nicht gefunden wurde
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Dashboard: Dashboard-Template nicht gefunden: " . $template_file);
                }
                
                // Einfaches Fallback-Template erstellen
                echo '<div class="wrap">';
                echo '<h1>' . esc_html__('Alenseo SEO Optimierung', 'alenseo') . '</h1>';
                echo '<div class="notice notice-error">';
                echo '<p>' . esc_html__('Fehler: Dashboard-Template konnte nicht geladen werden.', 'alenseo') . '</p>';
                echo '</div>';
                echo '<p>' . esc_html__('Bitte überprüfe, ob alle Dateien des Plugins korrekt installiert sind.', 'alenseo') . '</p>';
                echo '</div>';
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler beim Rendern der Dashboard-Seite: " . $e->getMessage());
            }
            
            // Fehler anzeigen
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Alenseo SEO Optimierung', 'alenseo') . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p>' . esc_html__('Fehler: Beim Laden des Dashboards ist ein Problem aufgetreten.', 'alenseo') . '</p>';
            echo '<p>' . esc_html__('Fehlermeldung:', 'alenseo') . ' ' . esc_html($e->getMessage()) . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * AJAX-Handler für Bulk-Keyword-Generierung
     */
    public function ajax_bulk_generate_keywords() {
        try {
            // Sicherheits-Check
            check_ajax_referer('alenseo_ajax_nonce', 'nonce');
            
            // Berechtigungen prüfen
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
                return;
            }
            
            // Post-IDs holen
            $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
            
            if (empty($post_ids)) {
                wp_send_json_error(array('message' => __('Keine Inhalte ausgewählt.', 'alenseo')));
                return;
            }
            
            // Prüfen, ob Claude API-Klasse existiert
            $claude_api_file = ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
            if (!file_exists($claude_api_file) || !class_exists('Alenseo_Claude_API')) {
                if (function_exists('alenseo_log')) {
                    alenseo_log("Alenseo Dashboard: Claude API-Klasse nicht gefunden");
                }
                
                wp_send_json_error(array(
                    'message' => __('Claude API-Klasse nicht gefunden. Bitte überprüfe die Plugin-Installation.', 'alenseo')
                ));
                return;
            }
            
            // Claude API-Klasse instanziieren
            $claude_api = new Alenseo_Claude_API();
            
            // Prüfen, ob API aktiv ist
            if (!$claude_api->is_active()) {
                wp_send_json_error(array(
                    'message' => __('Claude API ist nicht aktiv. Bitte konfiguriere den API-Schlüssel in den Einstellungen.', 'alenseo')
                ));
                return;
            }
            
            // Ergebnisse für jede Post-ID
            $results = array();
            
            // Jede Post-ID verarbeiten
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if (!$post) {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => __('Inhalt nicht gefunden.', 'alenseo')
                    );
                    continue;
                }
                
                // Keywords generieren
                $result = $claude_api->generate_keywords($post_id);
                
                if ($result['success'] && !empty($result['keywords'])) {
                    // Bestes Keyword auswählen (erstes in der Liste)
                    $best_keyword = '';
                    if (is_array($result['keywords'][0])) {
                        $best_keyword = $result['keywords'][0]['keyword'];
                    } else {
                        $best_keyword = $result['keywords'][0];
                    }
                    
                    // Keyword speichern
                    update_post_meta($post_id, '_alenseo_keyword', $best_keyword);
                    
                    $results[$post_id] = array(
                        'success' => true,
                        'keyword' => $best_keyword,
                        'message' => sprintf(__('Keyword "%s" gesetzt.', 'alenseo'), $best_keyword)
                    );
                } else {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => isset($result['message']) ? $result['message'] : __('Keine Keywords gefunden.', 'alenseo')
                    );
                }
            }
            
            wp_send_json_success(array(
                'results' => $results,
                'message' => __('Keyword-Generierung abgeschlossen.', 'alenseo')
            ));
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler bei Bulk-Keyword-Generierung: " . $e->getMessage());
            }
            
            wp_send_json_error(array(
                'message' => __('Fehler bei der Keyword-Generierung: ', 'alenseo') . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX-Handler für Bulk-Titel-Optimierung
     */
    public function ajax_bulk_optimize_titles() {
        try {
            // Sicherheits-Check
            check_ajax_referer('alenseo_ajax_nonce', 'nonce');
            
            // Berechtigungen prüfen
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
                return;
            }
            
            // Post-IDs holen
            $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
            
            if (empty($post_ids)) {
                wp_send_json_error(array('message' => __('Keine Inhalte ausgewählt.', 'alenseo')));
                return;
            }
            
            // Prüfen, ob Claude API-Klasse existiert
            if (!class_exists('Alenseo_Claude_API')) {
                // Versuche, die Claude API-Klasse zu laden
                $claude_api_file = ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
                if (file_exists($claude_api_file)) {
                    require_once $claude_api_file;
                }
                
                if (!class_exists('Alenseo_Claude_API')) {
                    wp_send_json_error(array(
                        'message' => __('Claude API-Klasse nicht gefunden. Bitte überprüfe die Plugin-Installation.', 'alenseo')
                    ));
                    return;
                }
            }
            
            $claude_api = new Alenseo_Claude_API();
            
            // Prüfen, ob API aktiv ist
            if (!$claude_api->is_active()) {
                wp_send_json_error(array(
                    'message' => __('Claude API ist nicht aktiv. Bitte konfiguriere den API-Schlüssel in den Einstellungen.', 'alenseo')
                ));
                return;
            }
            
            // Ergebnisse für jede Post-ID
            $results = array();
            
            // Jede Post-ID verarbeiten
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if (!$post) {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => __('Inhalt nicht gefunden.', 'alenseo')
                    );
                    continue;
                }
                
                // Fokus-Keyword abrufen
                $focus_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
                
                if (empty($focus_keyword)) {
                    // Versuche, ein Keyword zu generieren, wenn keines vorhanden ist
                    $keyword_result = $claude_api->generate_keywords($post_id);
                    if ($keyword_result['success'] && !empty($keyword_result['keywords'])) {
                        if (is_array($keyword_result['keywords'][0])) {
                            $focus_keyword = $keyword_result['keywords'][0]['keyword'];
                        } else {
                            $focus_keyword = $keyword_result['keywords'][0];
                        }
                        update_post_meta($post_id, '_alenseo_keyword', $focus_keyword);
                    } else {
                        $results[$post_id] = array(
                            'success' => false,
                            'message' => __('Kein Fokus-Keyword vorhanden und konnte keines generieren.', 'alenseo')
                        );
                        continue;
                    }
                }
                
                // Optimierungsvorschläge holen
                $suggestions = $claude_api->get_optimization_suggestions($post_id, $focus_keyword);
                
                if ($suggestions['success'] && isset($suggestions['suggestions']['title'])) {
                    // Titel aktualisieren
                    $optimized_title = $suggestions['suggestions']['title'];
                    
                    // Aktualisiere den Beitragstitel
                    $update_result = wp_update_post(array(
                        'ID' => $post_id,
                        'post_title' => $optimized_title
                    ), true);
                    
                    if (is_wp_error($update_result)) {
                        $results[$post_id] = array(
                            'success' => false,
                            'message' => $update_result->get_error_message()
                        );
                        continue;
                    }
                    
                    // Für Yoast SEO
                    update_post_meta($post_id, '_yoast_wpseo_title', $optimized_title);
                    
                    // Für All in One SEO
                    update_post_meta($post_id, '_aioseop_title', $optimized_title);
                    
                    // Für Rank Math
                    update_post_meta($post_id, 'rank_math_title', $optimized_title);
                    
                    $results[$post_id] = array(
                        'success' => true,
                        'title' => $optimized_title,
                        'message' => __('Titel erfolgreich optimiert.', 'alenseo')
                    );
                } else {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => isset($suggestions['message']) ? $suggestions['message'] : __('Kein Titelvorschlag gefunden.', 'alenseo')
                    );
                }
            }
            
            wp_send_json_success(array(
                'results' => $results,
                'message' => __('Titel-Optimierung abgeschlossen.', 'alenseo')
            ));
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler bei Bulk-Titel-Optimierung: " . $e->getMessage());
            }
            
            wp_send_json_error(array(
                'message' => __('Fehler bei der Titel-Optimierung: ', 'alenseo') . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX-Handler für Bulk-Meta-Description-Optimierung
     */
    public function ajax_bulk_optimize_meta_descriptions() {
        try {
            // Sicherheits-Check
            check_ajax_referer('alenseo_ajax_nonce', 'nonce');
            
            // Berechtigungen prüfen
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
                return;
            }
            
            // Post-IDs holen
            $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
            
            if (empty($post_ids)) {
                wp_send_json_error(array('message' => __('Keine Inhalte ausgewählt.', 'alenseo')));
                return;
            }
            
            // Prüfen, ob Claude API-Klasse existiert
            if (!class_exists('Alenseo_Claude_API')) {
                // Versuche, die Claude API-Klasse zu laden
                $claude_api_file = ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
                if (file_exists($claude_api_file)) {
                    require_once $claude_api_file;
                }
                
                if (!class_exists('Alenseo_Claude_API')) {
                    wp_send_json_error(array(
                        'message' => __('Claude API-Klasse nicht gefunden. Bitte überprüfe die Plugin-Installation.', 'alenseo')
                    ));
                    return;
                }
            }
            
            $claude_api = new Alenseo_Claude_API();
            
            // Prüfen, ob API aktiv ist
            if (!$claude_api->is_active()) {
                wp_send_json_error(array(
                    'message' => __('Claude API ist nicht aktiv. Bitte konfiguriere den API-Schlüssel in den Einstellungen.', 'alenseo')
                ));
                return;
            }
            
            // Ergebnisse für jede Post-ID
            $results = array();
            
            // Jede Post-ID verarbeiten
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if (!$post) {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => __('Inhalt nicht gefunden.', 'alenseo')
                    );
                    continue;
                }
                
                // Fokus-Keyword abrufen
                $focus_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
                
                if (empty($focus_keyword)) {
                    // Versuche, ein Keyword zu generieren, wenn keines vorhanden ist
                    $keyword_result = $claude_api->generate_keywords($post_id);
                    if ($keyword_result['success'] && !empty($keyword_result['keywords'])) {
                        if (is_array($keyword_result['keywords'][0])) {
                            $focus_keyword = $keyword_result['keywords'][0]['keyword'];
                        } else {
                            $focus_keyword = $keyword_result['keywords'][0];
                        }
                        update_post_meta($post_id, '_alenseo_keyword', $focus_keyword);
                    } else {
                        $results[$post_id] = array(
                            'success' => false,
                            'message' => __('Kein Fokus-Keyword vorhanden und konnte keines generieren.', 'alenseo')
                        );
                        continue;
                    }
                }
                
                // Optimierungsvorschläge holen
                $suggestions = $claude_api->get_optimization_suggestions($post_id, $focus_keyword);
                
                if ($suggestions['success'] && isset($suggestions['suggestions']['meta_description'])) {
                    // Meta-Description aktualisieren
                    $meta_description = $suggestions['suggestions']['meta_description'];
                    
                    // Für Alenseo
                    update_post_meta($post_id, '_alenseo_meta_description', $meta_description);
                    
                    // Für Yoast SEO
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
                    
                    // Für All in One SEO
                    update_post_meta($post_id, '_aioseo_description', $meta_description);
                    update_post_meta($post_id, '_aioseop_description', $meta_description);
                    
                    // Für Rank Math
                    update_post_meta($post_id, 'rank_math_description', $meta_description);
                    
                    // Für SEOPress
                    update_post_meta($post_id, '_seopress_titles_desc', $meta_description);
                    
                    // WPBakery (falls vorhanden)
                    update_post_meta($post_id, 'vc_description', $meta_description);
                    
                    $results[$post_id] = array(
                        'success' => true,
                        'meta_description' => $meta_description,
                        'message' => __('Meta-Description erfolgreich optimiert.', 'alenseo')
                    );
                } else {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => isset($suggestions['message']) ? $suggestions['message'] : __('Kein Meta-Description-Vorschlag gefunden.', 'alenseo')
                    );
                }
            }
            
            wp_send_json_success(array(
                'results' => $results,
                'message' => __('Meta-Description-Optimierung abgeschlossen.', 'alenseo')
            ));
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler bei Bulk-Meta-Description-Optimierung: " . $e->getMessage());
            }
            
            wp_send_json_error(array(
                'message' => __('Fehler bei der Meta-Description-Optimierung: ', 'alenseo') . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX-Handler für Bulk-Content-Optimierung
     * (Diese Funktion gibt nur Empfehlungen, ändert den Inhalt nicht direkt)
     */
    public function ajax_bulk_optimize_content() {
        try {
            // Sicherheits-Check
            check_ajax_referer('alenseo_ajax_nonce', 'nonce');
            
            // Berechtigungen prüfen
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
                return;
            }
            
            // Post-IDs holen
            $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
            
            if (empty($post_ids)) {
                wp_send_json_error(array('message' => __('Keine Inhalte ausgewählt.', 'alenseo')));
                return;
            }
            
            // Prüfen, ob Claude API-Klasse existiert
            if (!class_exists('Alenseo_Claude_API')) {
                // Versuche, die Claude API-Klasse zu laden
                $claude_api_file = ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
                if (file_exists($claude_api_file)) {
                    require_once $claude_api_file;
                }
                
                if (!class_exists('Alenseo_Claude_API')) {
                    wp_send_json_error(array(
                        'message' => __('Claude API-Klasse nicht gefunden. Bitte überprüfe die Plugin-Installation.', 'alenseo')
                    ));
                    return;
                }
            }
            
            $claude_api = new Alenseo_Claude_API();
            
            // Prüfen, ob API aktiv ist
            if (!$claude_api->is_active()) {
                wp_send_json_error(array(
                    'message' => __('Claude API ist nicht aktiv. Bitte konfiguriere den API-Schlüssel in den Einstellungen.', 'alenseo')
                ));
                return;
            }
            
            // Ergebnisse für jede Post-ID
            $results = array();
            
            // Jede Post-ID verarbeiten
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if (!$post) {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => __('Inhalt nicht gefunden.', 'alenseo')
                    );
                    continue;
                }
                
                // Fokus-Keyword abrufen
                $focus_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
                
                if (empty($focus_keyword)) {
                    // Versuche, ein Keyword zu generieren, wenn keines vorhanden ist
                    $keyword_result = $claude_api->generate_keywords($post_id);
                    if ($keyword_result['success'] && !empty($keyword_result['keywords'])) {
                        if (is_array($keyword_result['keywords'][0])) {
                            $focus_keyword = $keyword_result['keywords'][0]['keyword'];
                        } else {
                            $focus_keyword = $keyword_result['keywords'][0];
                        }
                        update_post_meta($post_id, '_alenseo_keyword', $focus_keyword);
                    } else {
                        $results[$post_id] = array(
                            'success' => false,
                            'message' => __('Kein Fokus-Keyword vorhanden und konnte keines generieren.', 'alenseo')
                        );
                        continue;
                    }
                }
                
                // Optimierungsvorschläge holen
                $suggestions = $claude_api->get_optimization_suggestions($post_id, $focus_keyword);
                
                if ($suggestions['success'] && isset($suggestions['suggestions']['content'])) {
                    // Content-Vorschläge speichern
                    $content_suggestions = $suggestions['suggestions']['content'];
                    update_post_meta($post_id, '_alenseo_content_suggestions', $content_suggestions);
                    
                    $results[$post_id] = array(
                        'success' => true,
                        'content_suggestions' => $content_suggestions,
                        'message' => sprintf(__('%d Inhaltsvorschläge generiert.', 'alenseo'), count($content_suggestions))
                    );
                } else {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => isset($suggestions['message']) ? $suggestions['message'] : __('Keine Inhaltsvorschläge gefunden.', 'alenseo')
                    );
                }
            }
            
            wp_send_json_success(array(
                'results' => $results,
                'message' => __('Inhaltsoptimierung abgeschlossen.', 'alenseo')
            ));
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler bei Bulk-Content-Optimierung: " . $e->getMessage());
            }
            
            wp_send_json_error(array(
                'message' => __('Fehler bei der Inhaltsoptimierung: ', 'alenseo') . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX-Handler für Bulk-Komplettoptimierung (Keywords, Titel, Meta, Content-Vorschläge)
     */
    public function ajax_bulk_optimize_all() {
        try {
            // Sicherheits-Check
            check_ajax_referer('alenseo_ajax_nonce', 'nonce');
            
            // Berechtigungen prüfen
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
                return;
            }
            
            // Post-IDs holen
            $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
            
            if (empty($post_ids)) {
                wp_send_json_error(array('message' => __('Keine Inhalte ausgewählt.', 'alenseo')));
                return;
            }
            
            // Prüfen, ob Claude API-Klasse existiert
            if (!class_exists('Alenseo_Claude_API')) {
                // Versuche, die Claude API-Klasse zu laden
                $claude_api_file = ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
                if (file_exists($claude_api_file)) {
                    require_once $claude_api_file;
                }
                
                if (!class_exists('Alenseo_Claude_API')) {
                    wp_send_json_error(array(
                        'message' => __('Claude API-Klasse nicht gefunden. Bitte überprüfe die Plugin-Installation.', 'alenseo')
                    ));
                    return;
                }
            }
            
            $claude_api = new Alenseo_Claude_API();
            
            // Prüfen, ob API aktiv ist
            if (!$claude_api->is_active()) {
                wp_send_json_error(array(
                    'message' => __('Claude API ist nicht aktiv. Bitte konfiguriere den API-Schlüssel in den Einstellungen.', 'alenseo')
                ));
                return;
            }
            
            // Ergebnisse für jede Post-ID
            $results = array();
            
            // Jede Post-ID verarbeiten
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if (!$post) {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => __('Inhalt nicht gefunden.', 'alenseo')
                    );
                    continue;
                }
                
                // 1. Keywords generieren
                $keyword_result = $claude_api->generate_keywords($post_id);
                $focus_keyword = '';
                
                if ($keyword_result['success'] && !empty($keyword_result['keywords'])) {
                    if (is_array($keyword_result['keywords'][0])) {
                        $focus_keyword = $keyword_result['keywords'][0]['keyword'];
                    } else {
                        $focus_keyword = $keyword_result['keywords'][0];
                    }
                    update_post_meta($post_id, '_alenseo_keyword', $focus_keyword);
                } else {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => __('Fehler bei der Keyword-Generierung.', 'alenseo')
                    );
                    continue;
                }
                
                // 2. Optimierungsvorschläge holen
                $suggestions = $claude_api->get_optimization_suggestions($post_id, $focus_keyword);
                
                if (!$suggestions['success']) {
                    $results[$post_id] = array(
                        'success' => false,
                        'message' => __('Fehler bei der Optimierungsvorschläge-Generierung.', 'alenseo')
                    );
                    continue;
                }
                
                $updated = array();
                
                // 3. Titel aktualisieren
                if (isset($suggestions['suggestions']['title'])) {
                    $optimized_title = $suggestions['suggestions']['title'];
                    
                    // Aktualisiere den Beitragstitel
                    $update_result = wp_update_post(array(
                        'ID' => $post_id,
                        'post_title' => $optimized_title
                    ), true);
                    
                    if (!is_wp_error($update_result)) {
                        // Für Yoast SEO
                        update_post_meta($post_id, '_yoast_wpseo_title', $optimized_title);
                        
                        // Für All in One SEO
                        update_post_meta($post_id, '_aioseop_title', $optimized_title);
                        
                        // Für Rank Math
                        update_post_meta($post_id, 'rank_math_title', $optimized_title);
                        
                        $updated[] = 'title';
                    }
                }
                
                // 4. Meta-Description aktualisieren
                if (isset($suggestions['suggestions']['meta_description'])) {
                    $meta_description = $suggestions['suggestions']['meta_description'];
                    
                    // Für Alenseo
                    update_post_meta($post_id, '_alenseo_meta_description', $meta_description);
                    
                    // Für Yoast SEO
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
                    
                    // Für All in One SEO
                    update_post_meta($post_id, '_aioseo_description', $meta_description);
                    update_post_meta($post_id, '_aioseop_description', $meta_description);
                    
                    // Für Rank Math
                    update_post_meta($post_id, 'rank_math_description', $meta_description);
                    
                    // Für SEOPress
                    update_post_meta($post_id, '_seopress_titles_desc', $meta_description);
                    
                    // WPBakery (falls vorhanden)
                    update_post_meta($post_id, 'vc_description', $meta_description);
                    
                    $updated[] = 'meta_description';
                }
                
                // 5. Content-Vorschläge speichern
                if (isset($suggestions['suggestions']['content'])) {
                    $content_suggestions = $suggestions['suggestions']['content'];
                    update_post_meta($post_id, '_alenseo_content_suggestions', $content_suggestions);
                    
                    $updated[] = 'content_suggestions';
                }
                
                // Alles erfolgreich aktualisiert
                $results[$post_id] = array(
                    'success' => true,
                    'keyword' => $focus_keyword,
                    'updated' => $updated,
                    'message' => sprintf(__('Optimierung abgeschlossen: %s', 'alenseo'), implode(', ', $updated))
                );
            }
            
            wp_send_json_success(array(
                'results' => $results,
                'message' => __('Komplettoptimierung abgeschlossen.', 'alenseo')
            ));
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler bei Bulk-Komplettoptimierung: " . $e->getMessage());
            }
            
            wp_send_json_error(array(
                'message' => __('Fehler bei der Komplettoptimierung: ', 'alenseo') . $e->getMessage()
            ));
        }
    }

    /**
     * AJAX-Handler für Abrufen von Inhaltsstatistiken
     */
    public function ajax_get_content_stats() {
        try {
            // Sicherheits-Check
            check_ajax_referer('alenseo_ajax_nonce', 'nonce');
            
            // Berechtigungen prüfen
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
                return;
            }
            
            // Statistiken berechnen
            $stats = $this->get_content_statistics();
            
            wp_send_json_success(array(
                'stats' => $stats
            ));
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler beim Abrufen von Inhaltsstatistiken: " . $e->getMessage());
            }
            
            wp_send_json_error(array(
                'message' => __('Fehler beim Abrufen der Statistiken: ', 'alenseo') . $e->getMessage()
            ));
        }
    }

    /**
     * Inhaltsstatistiken berechnen
     */
    public function get_content_statistics() {
        try {
            // Post-Typen für die Analyse
            $post_types = isset($this->settings['post_types']) ? $this->settings['post_types'] : array('post', 'page');
            
            // WP_Query für alle Beiträge
            $args = array(
                'post_type' => $post_types,
                'post_status' => 'publish',
                'posts_per_page' => -1
            );
            
            $query = new WP_Query($args);
            
            // Statistiken initialisieren
            $stats = array(
                'total' => 0,
                'with_keyword' => 0,
                'optimized' => 0,
                'partially_optimized' => 0,
                'needs_optimization' => 0,
                'avg_score' => 0,
                'total_score' => 0
            );
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    
                    // Keyword prüfen
                    $keyword = get_post_meta($post_id, '_alenseo_keyword', true);
                    
                    if (!empty($keyword)) {
                        $stats['with_keyword']++;
                    }
                    
                    // SEO-Score prüfen
                    $seo_score = (int) get_post_meta($post_id, '_alenseo_seo_score', true);
                    
                    if ($seo_score > 0) {
                        $stats['total_score'] += $seo_score;
                        
                        if ($seo_score >= 80) {
                            $stats['optimized']++;
                        } elseif ($seo_score >= 50) {
                            $stats['partially_optimized']++;
                        } else {
                            $stats['needs_optimization']++;
                        }
                    } else {
                        $stats['needs_optimization']++;
                    }
                    
                    $stats['total']++;
                }
                
                // Durchschnittsscore berechnen
                if ($stats['total'] > 0) {
                    $stats['avg_score'] = round($stats['total_score'] / $stats['total']);
                }
            }
            
            wp_reset_postdata();
            
            return $stats;
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler beim Berechnen der Inhaltsstatistiken: " . $e->getMessage());
            }
            
            // Leere Standardstatistiken zurückgeben
            return array(
                'total' => 0,
                'with_keyword' => 0,
                'optimized' => 0,
                'partially_optimized' => 0,
                'needs_optimization' => 0,
                'avg_score' => 0,
                'total_score' => 0
            );
        }
    }

    /**
     * Inhalte für das Dashboard abrufen
     */
    public function get_contents_for_dashboard($args = array()) {
        try {
            // Standardargumente
            $defaults = array(
                'post_type' => isset($this->settings['post_types']) ? $this->settings['post_types'] : array('post', 'page'),
                'post_status' => 'publish',
                'posts_per_page' => 50,
                'paged' => 1,
                'orderby' => 'date',
                'order' => 'DESC'
            );
            
            // Argumente zusammenführen
            $args = wp_parse_args($args, $defaults);
            
            // WP_Query ausführen
            $query = new WP_Query($args);
            
            $contents = array();
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    
                    // Fokus-Keyword abrufen
                    $focus_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
                    
                    // SEO-Daten abrufen
                    $seo_score = (int) get_post_meta($post_id, '_alenseo_seo_score', true);
                    $seo_status = get_post_meta($post_id, '_alenseo_seo_status', true);
                    
                    if (empty($seo_status)) {
                        if ($seo_score >= 80) {
                            $seo_status = 'optimized';
                        } elseif ($seo_score >= 50) {
                            $seo_status = 'partially_optimized';
                        } else {
                            $seo_status = 'needs_optimization';
                        }
                    }
                    
                    // Letztes Analysedatum
                    $last_analysis = get_post_meta($post_id, '_alenseo_last_analysis', true);
                    
                    $contents[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'type' => get_post_type_object(get_post_type())->labels->singular_name,
                        'date' => get_the_date(),
                        'focus_keyword' => $focus_keyword,
                        'seo_score' => $seo_score,
                        'seo_status' => $seo_status,
                        'last_analysis' => $last_analysis,
                        'edit_url' => get_edit_post_link($post_id),
                        'view_url' => get_permalink($post_id)
                    );
                }
            }
            
            wp_reset_postdata();
            
            return array(
                'contents' => $contents,
                'total' => $query->found_posts,
                'max_pages' => $query->max_num_pages
            );
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Dashboard: Fehler beim Abrufen der Inhalte für das Dashboard: " . $e->getMessage());
            }
            
            // Leere Standardergebnisse zurückgeben
            return array(
                'contents' => array(),
                'total' => 0,
                'max_pages' => 0
            );
        }
    }
    
    /**
     * Hilfsfunktion, um die CSS-Klasse für einen Score zu bestimmen
     * 
     * @param int $score Der SEO-Score
     * @return string Die CSS-Klasse
     */
    public function get_score_class($score) {
        if ($score >= 80) {
            return 'score-good';
        } elseif ($score >= 50) {
            return 'score-ok';
        } else {
            return 'score-poor';
        }
    }
    
    /**
     * Hilfsfunktion, um das HTML für einen Status zu generieren
     * 
     * @param string $status Der SEO-Status
     * @return string Das HTML für den Status
     */
    public function get_status_html($status) {
        switch ($status) {
            case 'optimized':
                return '<span class="alenseo-status alenseo-status-good">' . __('Gut optimiert', 'alenseo') . '</span>';
            case 'partially_optimized':
                return '<span class="alenseo-status alenseo-status-ok">' . __('Teilweise optimiert', 'alenseo') . '</span>';
            case 'needs_optimization':
            default:
                return '<span class="alenseo-status alenseo-status-poor">' . __('Optimierung nötig', 'alenseo') . '</span>';
        }
    }
}
