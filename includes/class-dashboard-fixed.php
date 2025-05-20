<?php
/**
 * Dashboard-Klasse für Alenseo
 * Diese Klasse enthält die Funktionalität für das SEO-Dashboard
 * und die Detailansicht
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
 * Die Dashboard-Klasse
 */
class Alenseo_Dashboard {
    
    /**
     * Dashboard-Seite registrieren
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
    }
    
    /**
     * Admin-Menü registrieren
     */
    public function register_admin_menu() {
        try {
            // Benutzerrolle mit Zugriffsrechten auf das Plugin
            $capability = 'manage_options';
            $parent_slug = 'alenseo-seo';
            
            // Hauptmenü
            add_menu_page(
                __('Alenseo SEO', 'alenseo'),
                __('Alenseo SEO', 'alenseo'),
                $capability,
                $parent_slug,
                array($this, 'render_dashboard_page'),
                'dashicons-chart-bar'
            );
            
            // Untermenü: Dashboard (um es als ersten Eintrag zu haben)
            add_submenu_page(
                $parent_slug,
                __('Dashboard', 'alenseo'),
                __('Dashboard', 'alenseo'),
                $capability,
                $parent_slug
            );
            
            // Untermenü: SEO-Optimierung
            add_submenu_page(
                $parent_slug,
                __('SEO-Optimierung', 'alenseo'),
                __('SEO-Optimierung', 'alenseo'),
                $capability,
                'alenseo-optimizer',
                array($this, 'render_optimizer_page')
            );
            
            // Untermenü: Einstellungen
            add_submenu_page(
                $parent_slug,
                __('Einstellungen', 'alenseo'),
                __('Einstellungen', 'alenseo'),
                $capability,
                'alenseo-settings',
                array($this, 'render_settings_page')
            );
            
        } catch (Exception $e) {
            error_log('Alenseo SEO - Fehler beim Registrieren des Admin-Menüs: ' . $e->getMessage());
        }
    }
    
    /**
     * SEO-Score-Historie aktualisieren
     * 
     * @param int $score Der aktuelle durchschnittliche Score
     * @return bool Erfolg der Aktualisierung
     */
    public function update_score_history($score) {
        $today = date('Y-m-d');
        
        // Wenn die Datenbank-Klasse verfügbar ist, dort den Score speichern
        global $alenseo_database;
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_score_history')) {
            // Score wird automatisch in der Datenbank gespeichert, wenn SEO-Scores aktualisiert werden
            // Dies ist nur ein Fallback für manuelle Updates
            return true;
        } else {
            // Fallback zur alten Methode über Optionen
            $history = get_option('alenseo_score_history', array());
            
            // Heutigen Score speichern/aktualisieren
            $history[$today] = $score;
            
            // Historie auf 30 Tage begrenzen
            if (count($history) > 30) {
                // Nach Datum sortieren
                ksort($history);
                
                // Älteste Einträge entfernen
                $history = array_slice($history, -30, 30, true);
            }
            
            return update_option('alenseo_score_history', $history);
        }
    }

    /**
     * Dashboard-Assets laden
     */
    public function enqueue_dashboard_assets($hook) {
        try {
            // Dashboard-Seite
            if (strpos($hook, 'alenseo-optimizer') !== false || strpos($hook, 'alenseo-seo') !== false) {
                // Dashboard CSS - Visuelle Version bevorzugt laden wenn vorhanden
                if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/dashboard-visual.css')) {
                    wp_enqueue_style(
                        'alenseo-dashboard-visual-css',
                        ALENSEO_MINIMAL_URL . 'assets/css/dashboard-visual.css',
                        array(),
                        ALENSEO_MINIMAL_VERSION
                    );
                } else if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/dashboard.css')) {
                    wp_enqueue_style(
                        'alenseo-dashboard-css',
                        ALENSEO_MINIMAL_URL . 'assets/css/dashboard.css',
                        array(),
                        ALENSEO_MINIMAL_VERSION
                    );
                } else {
                    // Fallback zu minimal CSS
                    if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/dashboard-minimal.css')) {
                        wp_enqueue_style(
                            'alenseo-dashboard-minimal-css',
                            ALENSEO_MINIMAL_URL . 'assets/css/dashboard-minimal.css',
                            array(),
                            ALENSEO_MINIMAL_VERSION
                        );
                    }
                }
                
                // Dashboard JS - Visuelle Version bevorzugt laden wenn vorhanden
                // Für das visuelle Dashboard Chart.js einbinden
                wp_enqueue_script(
                    'chartjs',
                    'https://cdn.jsdelivr.net/npm/chart.js',
                    array('jquery'),
                    '4.3.0', // Chart.js Version
                    true
                );
                
                if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/dashboard-visual.js')) {
                    wp_enqueue_script(
                        'alenseo-dashboard-visual-js',
                        ALENSEO_MINIMAL_URL . 'assets/js/dashboard-visual.js',
                        array('jquery', 'chartjs'),
                        ALENSEO_MINIMAL_VERSION,
                        true
                    );
                } else if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/dashboard-minimal.js')) {
                    wp_enqueue_script(
                        'alenseo-dashboard-js',
                        ALENSEO_MINIMAL_URL . 'assets/js/dashboard-minimal.js',
                        array('jquery'),
                        ALENSEO_MINIMAL_VERSION,
                        true
                    );
                }
                
                // Enhanced bulk optimizer JS
                if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/bulk-optimizer.js')) {
                    wp_enqueue_script(
                        'alenseo-bulk-optimizer-js',
                        ALENSEO_MINIMAL_URL . 'assets/js/bulk-optimizer.js',
                        array('jquery'),
                        ALENSEO_MINIMAL_VERSION,
                        true
                    );
                    
                    // Localize bulk optimizer script
                    wp_localize_script('alenseo-bulk-optimizer-js', 'alenseoBulkOptimizerParams', array(
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
                        'analyze' => __('Analysieren', 'alenseo'),
                        'analyzing' => __('Analysiere...', 'alenseo'),
                        'optimize' => __('Optimieren', 'alenseo'),
                        'optimizing' => __('Optimiere...', 'alenseo'),
                        'analyzeSuccess' => __('Analyse erfolgreich!', 'alenseo'),
                        'analyzeError' => __('Fehler bei der Analyse. Bitte versuche es erneut.', 'alenseo'),
                        'optimizeSuccess' => __('Optimierung erfolgreich!', 'alenseo'),
                        'optimizeError' => __('Fehler bei der Optimierung. Bitte versuche es erneut.', 'alenseo'),
                        'noActionSelected' => __('Bitte wähle eine Aktion aus.', 'alenseo'),
                        'noContentSelected' => __('Bitte wähle mindestens einen Inhalt aus.', 'alenseo'),
                        'allDone' => __('Alle Optimierungen wurden erfolgreich abgeschlossen!', 'alenseo'),
                        'ajaxError' => __('Ein Fehler ist aufgetreten. Bitte versuche es erneut.', 'alenseo'),
                        'bulkConfirmation' => __('Möchtest du die ausgewählte Aktion für %count% Inhalte durchführen? Dies kann eine Weile dauern.', 'alenseo'),
                        'statusGood' => __('Gut', 'alenseo'),
                        'statusNeedsImprovement' => __('Verbesserungswürdig', 'alenseo'),
                        'statusPoor' => __('Schlecht', 'alenseo'),
                        'recommendationsTitle' => __('Optimierungsempfehlungen', 'alenseo'),
                        'recommendationsAvailable' => __('Es wurden Empfehlungen zur Optimierung generiert. Öffne den Beitrag im Editor für Details.', 'alenseo'),
                        'keywordUsage' => __('Keyword-Nutzung', 'alenseo'),
                        'headings' => __('Überschriften', 'alenseo'),
                        'contentStructure' => __('Inhaltsstruktur', 'alenseo'),
                        'readability' => __('Lesbarkeit', 'alenseo'),
                        'generalRecommendations' => __('Allgemeine Empfehlungen', 'alenseo')
                    ));
                }
                
                // Bulk optimizer CSS
                if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/bulk-optimizer.css')) {
                    wp_enqueue_style(
                        'alenseo-bulk-optimizer-css',
                        ALENSEO_MINIMAL_URL . 'assets/css/bulk-optimizer.css',
                        array(),
                        ALENSEO_MINIMAL_VERSION
                    );
                }
                
                // Dashboard CSS
                if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/dashboard.css')) {
                    wp_enqueue_style(
                        'alenseo-dashboard-css',
                        ALENSEO_MINIMAL_URL . 'assets/css/dashboard.css',
                        array(),
                        ALENSEO_MINIMAL_VERSION
                    );
                }
                
                // AJAX-URL und Nonce für JavaScript
                wp_localize_script('alenseo-dashboard-js', 'alenseoData', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
                    'detailPageUrl' => admin_url('admin.php?page=alenseo-page-detail'),
                    'messages' => array(
                        'analyzing' => __('Analysiere...', 'alenseo'),
                        'success' => __('Analyse erfolgreich!', 'alenseo'),
                        'error' => __('Fehler bei der Analyse. Bitte versuche es erneut.', 'alenseo'),
                        'selectAction' => __('Bitte wähle eine Aktion aus.', 'alenseo'),
                        'selectContent' => __('Bitte wähle mindestens einen Inhalt aus.', 'alenseo'),
                        'allDone' => __('Alle Optimierungen wurden erfolgreich abgeschlossen!', 'alenseo')
                    )
                ));
            }
            
            // Detailansicht-Seite
            if ($hook === 'admin_page_alenseo-page-detail') {
                // Page Detail CSS
                if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/page-detail.css')) {
                    wp_enqueue_style(
                        'alenseo-page-detail-css',
                        ALENSEO_MINIMAL_URL . 'assets/css/page-detail.css',
                        array(),
                        ALENSEO_MINIMAL_VERSION
                    );
                }
                
                // Page Detail JS
                if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/page-detail.js')) {
                    wp_enqueue_script(
                        'alenseo-page-detail-js',
                        ALENSEO_MINIMAL_URL . 'assets/js/page-detail.js',
                        array('jquery'),
                        ALENSEO_MINIMAL_VERSION,
                        true
                    );
                    
                    // AJAX-URL und Nonce für JavaScript
                    wp_localize_script('alenseo-page-detail-js', 'alenseoData', array(
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
                        'messages' => array(
                            'analyzing' => __('Analysiere...', 'alenseo'),
                            'success' => __('Analyse erfolgreich!', 'alenseo'),
                            'error' => __('Fehler bei der Analyse. Bitte versuche es erneut.', 'alenseo')
                        )
                    ));
                }
            }
            
            // Einstellungsseite
            if (strpos($hook, 'alenseo-settings') !== false) {
                // Settings CSS
                if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/settings-page.css')) {
                    wp_enqueue_style(
                        'alenseo-settings-css',
                        ALENSEO_MINIMAL_URL . 'assets/css/settings-page.css',
                        array(),
                        ALENSEO_MINIMAL_VERSION
                    );
                }
                
                // Settings JS
                if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/settings-page.js')) {
                    wp_enqueue_script(
                        'alenseo-settings-js',
                        ALENSEO_MINIMAL_URL . 'assets/js/settings-page.js',
                        array('jquery'),
                        ALENSEO_MINIMAL_VERSION,
                        true
                    );
                    
                    // AJAX-URL und Nonce für JavaScript
                    wp_localize_script('alenseo-settings-js', 'alenseoSettingsParams', array(
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('alenseo_settings_nonce'),
                        'saving' => __('Speichern...', 'alenseo'),
                        'saved' => __('Gespeichert!', 'alenseo'),
                        'saveError' => __('Fehler beim Speichern. Bitte versuche es erneut.', 'alenseo'),
                        'testingApi' => __('API-Test läuft...', 'alenseo'),
                        'testSuccess' => __('API-Schlüssel funktioniert!', 'alenseo'),
                        'testError' => __('API-Test fehlgeschlagen: ', 'alenseo'),
                        'confirmReset' => __('Möchtest du wirklich alle Einstellungen zurücksetzen? Dies kann nicht rückgängig gemacht werden.', 'alenseo')
                    ));
                }
            }
            
        } catch (Exception $e) {
            error_log('Alenseo Dashboard - Fehler beim Laden der Assets: ' . $e->getMessage());
        }
    }
    
    /**
     * Dashboard-Seite rendern
     */
    public function render_dashboard_page() {
        try {
            // Filter-Parameter abrufen
            $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
            $filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
            $filter_search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
            
            // Template laden und rendern
            $template_file = ALENSEO_MINIMAL_DIR . 'templates/dashboard-page.php';
            
            // Visual Template bevorzugen, wenn vorhanden
            if (file_exists(ALENSEO_MINIMAL_DIR . 'templates/dashboard-page-visual.php')) {
                $template_file = ALENSEO_MINIMAL_DIR . 'templates/dashboard-page-visual.php';
            }
            
            if (file_exists($template_file)) {
                // Übersichtsdaten abrufen
                $overview_data = $this->get_overview_data();
                
                // Chart-Daten generieren
                $chart_data = $this->get_chart_data();
                
                // Query-Parameter für Filterung
                $query_args = array();
                
                // Statusfilter anwenden
                if (!empty($filter_status)) {
                    $query_args['status'] = $filter_status;
                }
                
                // Typfilter anwenden
                if (!empty($filter_type)) {
                    $query_args['post_type'] = $filter_type;
                }
                
                // Suchfilter anwenden
                if (!empty($filter_search)) {
                    $query_args['s'] = $filter_search;
                }
                
                // Alle Beiträge und Seiten abrufen
                $posts = $this->get_all_posts($query_args);
                
                // Post-Typen für Filter abrufen
                $post_types = get_post_types(array(
                    'public' => true
                ), 'objects');
                
                // Template einbinden
                include_once $template_file;
            } else {
                // Fallback, wenn Template nicht gefunden wird
                echo '<div class="wrap"><h1>' . __('Alenseo SEO Dashboard', 'alenseo') . '</h1>';
                echo '<div class="notice notice-error"><p>' . __('Dashboard-Template konnte nicht geladen werden.', 'alenseo') . '</p></div>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            error_log('Alenseo Dashboard - Fehler beim Rendern der Dashboard-Seite: ' . $e->getMessage());
            
            // Fehlerausgabe für Administratoren
            echo '<div class="wrap"><h1>' . __('Alenseo SEO Dashboard', 'alenseo') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Fehler beim Laden des Dashboards: ', 'alenseo') . esc_html($e->getMessage()) . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Optimizer-Seite rendern
     */
    public function render_optimizer_page() {
        try {
            // Template laden
            $template_file = ALENSEO_MINIMAL_DIR . 'templates/optimizer-page.php';
            
            if (file_exists($template_file)) {
                // Übersichtsdaten abrufen
                $overview_data = $this->get_overview_data();
                
                // Alle Beiträge und Seiten abrufen
                $posts = $this->get_all_posts();
                
                // Post-Typen für Filter abrufen
                $post_types = get_post_types(array(
                    'public' => true
                ), 'objects');
                
                // Template einbinden
                include_once $template_file;
            } else {
                // Fallback, wenn Template nicht gefunden wird
                echo '<div class="wrap"><h1>' . __('Alenseo SEO Optimierung', 'alenseo') . '</h1>';
                echo '<div class="notice notice-error"><p>' . __('Optimizer-Template konnte nicht geladen werden.', 'alenseo') . '</p></div>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            error_log('Alenseo Dashboard - Fehler beim Rendern der Optimizer-Seite: ' . $e->getMessage());
            
            // Fehlerausgabe für Administratoren
            echo '<div class="wrap"><h1>' . __('Alenseo SEO Optimierung', 'alenseo') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Fehler beim Laden der Optimierungsseite: ', 'alenseo') . esc_html($e->getMessage()) . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Detailansicht einer Seite rendern
     */
    public function render_page_detail() {
        try {
            // Post-ID aus der URL holen
            $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
            
            if (!$post_id) {
                wp_die(__('Keine Seite ausgewählt.', 'alenseo'));
            }
            
            // Prüfen, ob der Beitrag existiert
            $post = get_post($post_id);
            if (!$post) {
                wp_die(__('Die ausgewählte Seite existiert nicht.', 'alenseo'));
            }
            
            // Benutzerrechte prüfen - erlaubt edit_posts für alle Seiten/Posts
            if (!current_user_can('edit_posts')) {
                wp_die(__('Du hast keine Berechtigung, diese Seite anzusehen.', 'alenseo'));
            }
            
            // Detailansicht-Template laden und rendern
            $template_file = ALENSEO_MINIMAL_DIR . 'templates/page-detail.php';
            
            if (file_exists($template_file)) {
                // Globale Dashboard-Instanz für Helper-Funktionen
                global $alenseo_dashboard;
                $alenseo_dashboard = $this;
                
                // Template einbinden
                include_once $template_file;
            } else {
                // Fallback, wenn Template nicht gefunden wird
                echo '<div class="wrap"><h1>' . __('Seiten-Details', 'alenseo') . ': ' . esc_html($post->post_title) . '</h1>';
                echo '<div class="notice notice-error"><p>' . __('Detailansicht-Template konnte nicht geladen werden.', 'alenseo') . '</p></div>';
                echo '<p><a href="' . admin_url('admin.php?page=alenseo-optimizer') . '" class="button">' . __('Zurück zur Übersicht', 'alenseo') . '</a></p>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            error_log('Alenseo Dashboard - Fehler beim Rendern der Detailansicht: ' . $e->getMessage());
            
            // Fehlerausgabe für Administratoren
            echo '<div class="wrap"><h1>' . __('Seiten-Details', 'alenseo') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Fehler beim Laden der Detailansicht: ', 'alenseo') . esc_html($e->getMessage()) . '</p></div>';
            echo '<p><a href="' . admin_url('admin.php?page=alenseo-optimizer') . '" class="button">' . __('Zurück zur Übersicht', 'alenseo') . '</a></p>';
            echo '</div>';
        }
    }
    
    /**
     * Einstellungsseite rendern
     */
    public function render_settings_page() {
        try {
            // Einstellungen-Template laden und rendern
            $template_file = ALENSEO_MINIMAL_DIR . 'templates/settings-page.php';
            
            if (file_exists($template_file)) {
                include_once $template_file;
            } else {
                // Fallback, wenn Template nicht gefunden wird
                echo '<div class="wrap"><h1>' . __('Alenseo SEO Einstellungen', 'alenseo') . '</h1>';
                echo '<div class="notice notice-error"><p>' . __('Einstellungen-Template konnte nicht geladen werden.', 'alenseo') . '</p></div>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            error_log('Alenseo Dashboard - Fehler beim Rendern der Einstellungsseite: ' . $e->getMessage());
            
            // Fehlerausgabe für Administratoren
            echo '<div class="wrap"><h1>' . __('Alenseo SEO Einstellungen', 'alenseo') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Fehler beim Laden der Einstellungsseite: ', 'alenseo') . esc_html($e->getMessage()) . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Alle Beiträge und Seiten abrufen
     * 
     * @param array $args Zusätzliche Query-Parameter
     * @return array Array mit SEO-Daten pro Post
     */
    public function get_all_posts($args = array()) {
        // Standard-Abfrageparameter
        $default_args = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        // Benutzerdefinierte Einstellungen laden
        $settings = get_option('alenseo_settings', array());
        if (isset($settings['post_types']) && is_array($settings['post_types'])) {
            $default_args['post_type'] = $settings['post_types'];
        }
        
        // Parameter zusammenführen
        $query_args = wp_parse_args($args, $default_args);
        
        // Status-Filterung
        if (isset($args['status'])) {
            $status = $args['status'];
            unset($query_args['status']); // Entfernen, da kein WP_Query-Parameter
            
            // Meta-Query für Status-Filterung
            $query_args['meta_query'] = array();
            
            switch ($status) {
                case 'good':
                    $query_args['meta_query'][] = array(
                        'key' => '_alenseo_seo_score',
                        'value' => 80,
                        'compare' => '>=',
                        'type' => 'NUMERIC'
                    );
                    break;
                    
                case 'needs_improvement':
                    $query_args['meta_query'][] = array(
                        'key' => '_alenseo_seo_score',
                        'value' => array(50, 79),
                        'compare' => 'BETWEEN',
                        'type' => 'NUMERIC'
                    );
                    break;
                    
                case 'poor':
                    $query_args['meta_query'][] = array(
                        'key' => '_alenseo_seo_score',
                        'value' => 50,
                        'compare' => '<',
                        'type' => 'NUMERIC'
                    );
                    break;
                    
                case 'no_keyword':
                    $query_args['meta_query'][] = array(
                        'relation' => 'OR',
                        array(
                            'key' => '_alenseo_keyword',
                            'value' => '',
                            'compare' => '='
                        ),
                        array(
                            'key' => '_alenseo_keyword',
                            'compare' => 'NOT EXISTS'
                        )
                    );
                    break;
            }
        }
        
        // Abfrage ausführen
        $query = new WP_Query($query_args);
        $posts = $query->posts;
        
        // Ergebnisarray vorbereiten
        $result = array();
        
        // SEO-Daten für jeden Beitrag abrufen
        foreach ($posts as $post) {
            $post_obj = new stdClass();
            $post_obj->ID = $post->ID;
            $post_obj->post_title = $post->post_title;
            $post_obj->post_type = $post->post_type;
            
            // SEO-Daten abrufen
            $seo_data = $this->get_post_seo_data($post->ID);
            
            // Daten hinzufügen
            $post_obj->seo_score = $seo_data['score'];
            $post_obj->seo_status = $seo_data['status'];
            $post_obj->seo_status_label = $seo_data['status_text'];
            $post_obj->keyword = $seo_data['keyword'];
            $post_obj->meta_description = $seo_data['meta_description'];
            $post_obj->permalink = get_permalink($post->ID);
            
            $result[] = $post_obj;
        }
        
        return $result;
    }
    
    /**
     * SEO-Score und Status für einen Beitrag abrufen
     * 
     * @param int $post_id Die Post-ID
     * @return array Array mit Score und Status
     */
    public function get_post_seo_data($post_id) {
        // Datenbank-Klasse für SEO-Daten verwenden, wenn verfügbar
        global $alenseo_database;
        
        $data = array(
            'score' => 0,
            'status' => 'unknown',
            'status_text' => __('Nicht analysiert', 'alenseo'),
            'keyword' => get_post_meta($post_id, '_alenseo_keyword', true),
            'meta_description' => ''
        );
        
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
        
        // SEO-Score abrufen
        if ($seo_score !== '') {
            $data['score'] = intval($seo_score);
            
            // SEO-Status basierend auf Score
            if ($data['score'] >= 80) {
                $data['status'] = 'good';
                $data['status_text'] = __('Gut optimiert', 'alenseo');
            } elseif ($data['score'] >= 50) {
                $data['status'] = 'ok';
                $data['status_text'] = __('Teilweise optimiert', 'alenseo');
            } else {
                $data['status'] = 'poor';
                $data['status_text'] = __('Optimierung nötig', 'alenseo');
            }
        }
        
        // Meta-Description abrufen
        $meta_description = get_post_meta($post_id, '_alenseo_meta_description', true);
        if (empty($meta_description)) {
            // Yoast SEO
            $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            
            // All in One SEO
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, '_aioseo_description', true);
            }
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, '_aioseop_description', true);
            }
            
            // Rank Math
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, 'rank_math_description', true);
            }
            
            // SEOPress
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, '_seopress_titles_desc', true);
            }
            
            // WPBakery
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, 'vc_description', true);
            }
        }
        
        $data['meta_description'] = $meta_description;
        
        return $data;
    }
    
    /**
     * Übersichtsdaten abrufen (für Dashboard-Header)
     * 
     * @return array Übersichtsdaten
     */
    public function get_overview_data() {
        // Verwende Transient für bessere Performance
        $cache_key = 'alenseo_overview_data';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $data = array(
            'total_count' => 0,
            'optimized_count' => 0,
            'needs_improvement_count' => 0,
            'no_keyword_count' => 0,
            'average_score' => 0,
            'keywords_count' => 0,
            'top_keywords' => array(),
            'items' => array()
        );
        
        // Daten über Datenbank-Klasse abrufen, falls verfügbar
        global $alenseo_database;
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_average_scores')) {
            $average_scores = $alenseo_database->get_average_scores();
            if ($average_scores && isset($average_scores['avg_score'])) {
                $data['average_score'] = round($average_scores['avg_score']);
            }
        }
        
        // Posts abrufen
        $posts = $this->get_all_posts();
        $data['items'] = $posts;
        
        // Zählen
        $total_score = 0;
        $score_count = 0;
        
        foreach ($posts as $post) {
            $data['total_count']++;
            
            if (isset($post->seo_score) && $post->seo_score > 0) {
                $total_score += $post->seo_score;
                $score_count++;
                
                if ($post->seo_score >= 80) {
                    $data['optimized_count']++;
                } elseif ($post->seo_score >= 50) {
                    $data['needs_improvement_count']++;
                } else {
                    $data['needs_improvement_count']++;
                }
            }
            
            if (empty($post->keyword)) {
                $data['no_keyword_count']++;
            }
        }
        
        // Durchschnittliche Punktzahl berechnen, falls nicht aus der Datenbank verfügbar
        if ($data['average_score'] === 0 && $score_count > 0) {
            $data['average_score'] = round($total_score / $score_count);
        }
        
        // In Transient speichern (15 Minuten gültig)
        set_transient($cache_key, $data, 15 * MINUTE_IN_SECONDS);
        
        return $data;
    }
    
    /**
     * Einen Label für einen Post-Type abrufen
     * 
     * @param string $post_type Der Post-Type
     * @return string Das Label für den Post-Type
     */
    public function get_post_type_label($post_type) {
        $post_type_obj = get_post_type_object($post_type);
        
        if ($post_type_obj && isset($post_type_obj->labels->singular_name)) {
            return $post_type_obj->labels->singular_name;
        }
        
        return ucfirst($post_type);
    }
    
    /**
     * HTML für ein Status-Badge generieren
     * 
     * @param string $status Der Status (good, ok, poor, unknown)
     * @param string $status_text Der Statustext
     * @return string Das HTML für das Status-Badge
     */
    public function get_status_badge_html($status, $status_text) {
        return '<div class="alenseo-status alenseo-status-' . esc_attr($status) . '">' . esc_html($status_text) . '</div>';
    }

    /**
     * API-Nutzungsstatistiken abrufen
     * 
     * @return array API-Nutzungsstatistiken
     */
    public function get_api_usage_stats() {
        $stats = array(
            'requests_today' => 0,
            'daily_limit' => 50, // Standard-Tageslimit
            'tokens_used' => 0,
            'monthly_limit' => 100000, // Standard-Monatslimit
            'cache_hit_percentage' => 0,
            'status' => 'ok',
            'status_message' => __('Betriebsbereit', 'alenseo'),
            'success_rate' => 100,
            'error_rate' => 0
        );
        
        // Einstellungen laden
        $settings = get_option('alenseo_settings', array());
        
        // Tageslimits aus den Einstellungen übernehmen
        if (isset($settings['api_daily_limit'])) {
            $stats['daily_limit'] = intval($settings['api_daily_limit']);
        }
        
        if (isset($settings['api_monthly_limit'])) {
            $stats['monthly_limit'] = intval($settings['api_monthly_limit']);
        }
        
        // Statistiken aus der Datenbank-Klasse abrufen, wenn verfügbar
        global $alenseo_database;
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_api_usage')) {
            $api_stats = $alenseo_database->get_api_usage('today');
            
            if ($api_stats) {
                $stats['requests_today'] = $api_stats['total_requests'];
                $stats['tokens_used'] = $api_stats['total_tokens'];
                $stats['error_rate'] = $api_stats['error_rate'];
                $stats['success_rate'] = 100 - $api_stats['error_rate'];
            }
        } else {
            // Fallback zu altem System
            $rate_limits = get_transient('alenseo_claude_rate_limits');
            if ($rate_limits !== false) {
                // Anfragen von heute zählen
                $today_start = strtotime('today midnight');
                if ($rate_limits['reset_time'] > $today_start) {
                    $stats['requests_today'] = $rate_limits['requests'];
                }
                
                // Token-Nutzung
                $stats['tokens_used'] = $rate_limits['tokens'];
            }
        }
        
        // Cache-Treffer berechnen
        $total_requests = get_option('alenseo_total_api_requests', 0);
        $cache_hits = get_option('alenseo_cache_hits', 0);
        
        if ($total_requests > 0) {
            $stats['cache_hit_percentage'] = round(($cache_hits / $total_requests) * 100);
        }
        
        // API-Status überprüfen
        if ($stats['requests_today'] >= $stats['daily_limit']) {
            $stats['status'] = 'limit';
            $stats['status_message'] = __('Tageslimit erreicht', 'alenseo');
        } elseif ($stats['tokens_used'] >= $stats['monthly_limit']) {
            $stats['status'] = 'limit';
            $stats['status_message'] = __('Monatslimit erreicht', 'alenseo');
        } elseif ($stats['requests_today'] >= ($stats['daily_limit'] * 0.9)) {
            $stats['status'] = 'warning';
            $stats['status_message'] = __('Tageslimit fast erreicht', 'alenseo');
        }
        
        return $stats;
    }

    /**
     * Chartdaten für das Dashboard generieren
     * 
     * @return array Chart-Daten
     */
    public function get_chart_data() {
        // Verwende Transient für bessere Performance
        $cache_key = 'alenseo_chart_data';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $data = array(
            'post_types_data' => array(),
            'time_series' => array(),
            'keyword_stats' => array()
        );
        
        // Post-Typen-Daten
        $posts = $this->get_all_posts();
        $post_type_counts = array();
        
        foreach ($posts as $post) {
            if (!isset($post_type_counts[$post->post_type])) {
                $post_type_counts[$post->post_type] = 0;
            }
            $post_type_counts[$post->post_type]++;
        }
        
        foreach ($post_type_counts as $type => $count) {
            $data['post_types_data'][] = array(
                'label' => $this->get_post_type_label($type),
                'count' => $count
            );
        }
        
        // SEO-Score-Entwicklung über Zeit aus der Datenbank
        global $alenseo_database;
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_score_history')) {
            $score_history = $alenseo_database->get_score_history(30);
            if (!empty($score_history)) {
                $data['time_series'] = $score_history;
            } else {
                // Fallback zu leeren Standardwerten
                $data['time_series'] = array();
                // Letzte 7 Tage
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $data['time_series'][$date] = 50 + rand(0, 30); // Zufällige Score-Werte
                }
            }
        } else {
            // Fallback zum alten System
            $score_history = get_option('alenseo_score_history', array());
            $data['time_series'] = $score_history;
            
            // Wenn keine Historie vorhanden, Beispieldaten erstellen
            if (empty($score_history)) {
                // Letzte 7 Tage
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $data['time_series'][$date] = 50 + rand(0, 30); // Zufällige Score-Werte
                }
            }
        }
        
        // Top Keywords aus der Datenbank abrufen
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_top_keywords')) {
            $top_keywords = $alenseo_database->get_top_keywords(10);
            $keyword_counts = array();
            
            foreach ($top_keywords as $keyword_data) {
                $keyword_counts[$keyword_data['keyword']] = intval($keyword_data['count']);
            }
            
            // Wenn keine Keywords in der Datenbank, Fallback zu post meta
            if (empty($keyword_counts)) {
                foreach ($posts as $post) {
                    $keywords = get_post_meta($post->ID, 'alenseo_focus_keywords', true);
                    if (!empty($keywords)) {
                        $keyword_array = array_map('trim', explode(',', $keywords));
                        foreach ($keyword_array as $keyword) {
                            if (!empty($keyword)) {
                                if (!isset($keyword_counts[$keyword])) {
                                    $keyword_counts[$keyword] = 0;
                                }
                                $keyword_counts[$keyword]++;
                            }
                        }
                    }
                }
            }
        } else {
            // Fallback zu altem System für Keywords
            $keyword_counts = array();
            foreach ($posts as $post) {
                $keywords = get_post_meta($post->ID, 'alenseo_focus_keywords', true);
                if (!empty($keywords)) {
                    $keyword_array = array_map('trim', explode(',', $keywords));
                    foreach ($keyword_array as $keyword) {
                        if (!empty($keyword)) {
                            if (!isset($keyword_counts[$keyword])) {
                                $keyword_counts[$keyword] = 0;
                            }
                            $keyword_counts[$keyword]++;
                        }
                    }
                }
            }
        }
        
        // Top 10 Keywords
        arsort($keyword_counts);
        $data['keyword_stats'] = array_slice($keyword_counts, 0, 10, true);
        
        // In Transient speichern (1 Stunde gültig)
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        
        return $data;
    }

    /**
     * SEO-Score-Historie aktualisieren
     * 
     * @param int $score Der aktuelle durchschnittliche Score
     * @return bool Erfolg der Aktualisierung
     */
    public function update_score_history($score) {
        $today = date('Y-m-d');
        
        // Wenn die Datenbank-Klasse verfügbar ist, dort den Score speichern
        global $alenseo_database;
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_score_history')) {
            // Score wird automatisch in der Datenbank gespeichert, wenn SEO-Scores aktualisiert werden
            // Dies ist nur ein Fallback für manuelle Updates
            return true;
        } else {
            // Fallback zur alten Methode über Optionen
            $history = get_option('alenseo_score_history', array());
            
            // Heutigen Score speichern/aktualisieren
            $history[$today] = $score;
            
            // Historie auf 30 Tage begrenzen
            if (count($history) > 30) {
                // Nach Datum sortieren
                ksort($history);
                
                // Älteste Einträge entfernen
                $history = array_slice($history, -30, 30, true);
            }
            
            return update_option('alenseo_score_history', $history);
        }
    }
}
