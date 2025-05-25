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

use Alenseo\Alenseo_Database;
use Alenseo\Alenseo_Claude_API;

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Die Dashboard-Klasse
 */
class Alenseo_Dashboard {

    /**
     * Enthält die Instanz der Datenbank-Klasse
     *
     * @since    1.0.0
     * @access   private
     * @var      Alenseo_Database    $db   Die Datenbank-Klasse
     */
    private $db;

    /**
     * Die ID des anzuzeigenden Artikels
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $post_id    Die ID des anzuzeigenden Artikels
     */
    private $post_id;

    /**
     * Instanziierung der Klasse und Registrierung von WordPress-Hooks
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Datenbank-Klasse instanziieren
        $this->db = new Alenseo_Database();

        // DISABLED: Menü wird jetzt zentral in der Hauptklasse verwaltet
        // add_action('admin_menu', array($this, 'add_dashboard_menu'));

        // AJAX-Endpunkte für das Dashboard registrieren
        add_action('wp_ajax_alenseo_get_post_data', array($this, 'get_post_data'));
        add_action('wp_ajax_alenseo_get_score_history', array($this, 'get_score_history'));
        add_action('wp_ajax_alenseo_get_stats', array($this, 'get_stats'));
        add_action('wp_ajax_alenseo_get_api_status', array($this, 'get_api_status'));
        add_action('wp_ajax_alenseo_analyze_post', array($this, 'analyze_post'));

        // Scripts und Styles für das Dashboard laden
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));

        // Hinzufügen des Dashboard-Widgets
        add_action('wp_dashboard_setup', function() {
            wp_add_dashboard_widget('alenseo_ki_widget', 'Alenseo KI SEO-Tools', 'Alenseo\\alenseo_render_ki_widget');
        });
    }

    /**
     * Scripts und Styles für das Dashboard laden
     *
     * @since    1.0.0
     * @param    string    $hook    Der aktuelle Admin-Hook
     */
    public function enqueue_dashboard_assets($hook) {
        // Prüfen, ob wir auf der Dashboard-Seite sind
        if (strpos($hook, 'page_alenseo-dashboard') === false && $hook !== 'toplevel_page_alenseo-dashboard') {
            return;
        }

        // Visual Dashboard CSS
        $css_path = plugin_dir_path(dirname(__FILE__)) . 'assets/css/dashboard-visual.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'alenseo-dashboard-visual-css',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/dashboard-visual.css',
                array(),
                filemtime($css_path)
            );
        } else {
            error_log('CSS-Datei nicht gefunden: ' . $css_path);
        }

        // Visual Dashboard JS
        $js_path = plugin_dir_path(dirname(__FILE__)) . 'assets/js/dashboard-visual.js';
        if (file_exists($js_path)) {
            // Chart.js laden (falls benötigt)
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js',
                array(),
                '3.9.1',
                true
            );

            // Dashboard JS laden
            wp_enqueue_script(
                'alenseo-dashboard-visual-js',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/dashboard-visual.js',
                array('jquery', 'chartjs'),
                filemtime($js_path),
                true
            );

            // AJAX-URL und Nonce für JavaScript
            wp_localize_script('alenseo-dashboard-visual-js', 'alenseoData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
                'messages' => array(
                    'selectAction' => __('Bitte wähle eine Aktion aus.', 'alenseo'),
                    'selectContent' => __('Bitte wähle mindestens einen Inhalt aus.', 'alenseo'),
                    'analyzing' => __('Wird analysiert...', 'alenseo'),
                    'error' => __('Es ist ein Fehler aufgetreten.', 'alenseo'),
                    'allDone' => __('Alle Inhalte wurden verarbeitet.', 'alenseo')
                )
            ));
        } else {
            error_log('JS-Datei nicht gefunden: ' . $js_path);
        }
    }

    /**
     * Fügt den Dashboard-Menüpunkt im Admin-Bereich hinzu
     *
     * @since    1.0.0
     */
    public function add_dashboard_menu() {
        add_menu_page(
            'Alenseo SEO',
            'Alenseo SEO',
            'manage_options',
            'alenseo-dashboard',
            array($this, 'display_dashboard_page'),
            'dashicons-chart-area',
            100
        );

        // Untermenü für das Dashboard hinzufügen
        add_submenu_page(
            'alenseo-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'alenseo-dashboard',
            array($this, 'display_dashboard_page')
        );

        // Untermenü für die Einstellungen hinzufügen
        add_submenu_page(
            'alenseo-dashboard',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'alenseo-settings',
            array($this, 'display_settings_page')
        );
        
        // Untermenü für Seitenoptimierung hinzufügen
        add_submenu_page(
            'alenseo-dashboard',
            'Seiten optimieren',
            'Seiten optimieren',
            'manage_options',
            'alenseo-optimizer',
            array($this, 'display_optimizer_page')
        );
    }

    /**
     * Zeigt die Hauptseite des Dashboards an
     *
     * @since    1.0.0
     */
    public function display_dashboard_page() {
        // URL-Parameter für die Detailansicht abfragen
        $this->post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        
        if ($this->post_id > 0) {
            // Detailansicht für einen Artikel anzeigen
            $this->display_detail_page();
        } else {
            // Dashboard-Übersicht anzeigen
            $this->display_overview_page();
        }
    }

    /**
     * Zeigt die Übersichtsseite des Dashboards an
     *
     * @since    1.0.0
     */
    public function display_overview_page() {
        // Pfad zum Template
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/dashboard-page-visual.php';
        // Prüfen, ob die Template-Datei existiert
        if (file_exists($template_path)) {
            // Template einbinden
            include $template_path;
        } else {
            echo '<div class="error"><p>Template nicht gefunden: ' . esc_html($template_path) . '</p></div>';
        }
    }

    /**
     * Zeigt die Detailansicht für einen Artikel an
     *
     * @since    1.0.0
     */
    public function display_detail_page() {
        // Pfad zum Template
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/page-detail.php';
        
        // Artikel-Daten aus der Datenbank abrufen
        $post_data = $this->db->get_post_data($this->post_id);
        
        // Prüfen, ob die Template-Datei existiert
        if (file_exists($template_path)) {
            // Template einbinden
            include $template_path;
        } else {
            echo '<div class="error"><p>Template nicht gefunden: ' . esc_html($template_path) . '</p></div>';
        }
    }

    /**
     * Zeigt die Einstellungsseite an
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        // Pfad zum Template
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/settings-page.php';
        
        // Prüfen, ob die Template-Datei existiert
        if (file_exists($template_path)) {
            // Template einbinden
            include $template_path;
        } else {
            echo '<div class="error"><p>Template nicht gefunden: ' . esc_html($template_path) . '</p></div>';
        }
    }

    /**
     * Zeigt die Optimierungsseite an
     */
    public function display_optimizer_page() {
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/optimizer-page.php';
        if (file_exists($template_path)) {
            // Benötigte Variablen bereitstellen
            $settings = get_option('alenseo_settings', array());
            $post_types = get_post_types(array('public' => true), 'objects');
            $api_configured = class_exists('Alenseo_Claude_API') ? (new \Alenseo_Claude_API())->is_api_configured() : false;
            $posts = $this->get_all_posts();
            include $template_path;
        } else {
            echo '<div class="error"><p>Template nicht gefunden: ' . esc_html($template_path) . '</p></div>';
        }
    }

    /**
     * AJAX-Callback für das Abrufen der Artikeldaten
     *
     * @since    1.0.0
     */
    public function get_post_data() {
        // Sicherheitscheck für AJAX-Anfrage
        check_ajax_referer('alenseo_nonce', 'security');

        // Eingabe validieren
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if ($post_id <= 0) {
            wp_send_json_error('Ungültige Artikel-ID');
            wp_die();
        }

        // Daten aus der Datenbank abrufen
        $post_data = $this->db->get_post_data($post_id);

        if ($post_data) {
            wp_send_json_success($post_data);
        } else {
            wp_send_json_error('Keine Daten für diesen Artikel gefunden');
        }

        wp_die();
    }

    /**
     * AJAX-Callback für das Abrufen des Score-Verlaufs
     *
     * @since    1.0.0
     */
    public function get_score_history() {
        // Sicherheitscheck für AJAX-Anfrage
        check_ajax_referer('alenseo_nonce', 'security');

        // Eingabe validieren
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if ($post_id <= 0) {
            wp_send_json_error('Ungültige Artikel-ID');
            wp_die();
        }

        // Daten aus der Datenbank abrufen
        $history_data = $this->db->get_score_history($post_id);

        if ($history_data) {
            wp_send_json_success($history_data);
        } else {
            wp_send_json_error('Keine Verlaufsdaten für diesen Artikel gefunden');
        }

        wp_die();
    }

    /**
     * AJAX-Callback für das Abrufen der Statistiken
     *
     * @since    1.0.0
     */
    public function get_stats() {
        // Sicherheitscheck für AJAX-Anfrage
        check_ajax_referer('alenseo_nonce', 'security');

        // Statistikdaten aus der Datenbank abrufen
        $stats = array(
            'total_posts' => $this->db->get_total_posts(),
            'analyzed_posts' => $this->db->get_analyzed_posts(),
            'average_score' => $this->db->get_average_score(),
            'recent_posts' => $this->db->get_recent_posts(5)
        );

        wp_send_json_success($stats);
        wp_die();
    }

    /**
     * Übersichtsdaten abrufen (für Dashboard-Header)
     * 
     * @return array Übersichtsdaten
     */
    public function get_overview_data() {
        // Echtzeitdaten abrufen
        $data = array(
            'total_count' => $this->db->get_total_posts(),
            'optimized_count' => $this->db->get_analyzed_posts(),
            'needs_improvement_count' => $this->db->get_needs_improvement_posts(),
            'no_keyword_count' => $this->db->get_no_keyword_posts(),
            'average_score' => $this->db->get_average_score(),
            'keywords_count' => $this->db->get_total_keywords(),
            'top_keywords' => $this->db->get_top_keywords(),
            'items' => $this->get_all_posts()
        );

        return $data;
    }

    /**
     * Aktualisiert den Score-Verlauf für einen Artikel
     *
     * @since    1.0.0
     * @param    int      $post_id    Die ID des Artikels
     * @param    int      $score      Der aktuelle Score
     */
    public function update_score_history($post_id, $score) {
        // Verlaufsdaten aus der Datenbank abrufen
        $history = $this->db->get_score_history($post_id);
        
        // Wenn keine Verlaufsdaten vorhanden sind, ein leeres Array erstellen
        if (empty($history)) {
            $history = array();
        }
        
        // Aktuelles Datum und Score hinzufügen
        $history[] = array(
            'date' => current_time('mysql'),
            'score' => $score
        );
        
        // Verlauf auf maximal 10 Einträge begrenzen
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }
        
        // Verlaufsdaten in der Datenbank speichern
        $this->db->update_score_history($post_id, $history);
    }

    /**
     * Gibt die neuesten analysierten Artikel zurück
     *
     * @since    1.0.0
     * @param    int      $count    Die Anzahl der zurückzugebenden Artikel
     * @return   array              Array mit Artikeldaten
     */
    public function get_recent_posts($count = 5) {
        return $this->db->get_recent_posts($count);
    }

    /**
     * Gibt die Anzahl aller Artikel zurück
     *
     * @since    1.0.0
     * @return   int      Anzahl der Artikel
     */
    public function get_total_posts() {
        return $this->db->get_total_posts();
    }

    /**
     * Gibt die Anzahl aller analysierten Artikel zurück
     *
     * @since    1.0.0
     * @return   int      Anzahl der analysierten Artikel
     */
    public function get_analyzed_posts() {
        return $this->db->get_analyzed_posts();
    }

    /**
     * Gibt den durchschnittlichen Score aller analysierten Artikel zurück
     *
     * @since    1.0.0
     * @return   float    Durchschnittlicher Score
     */
    public function get_average_score() {
        return $this->db->get_average_score();
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
        $settings = get_option('alenseo_settings', array());
        if (isset($settings['post_types']) && is_array($settings['post_types'])) {
            $default_args['post_type'] = $settings['post_types'];
        }
        $query_args = wp_parse_args($args, $default_args);
        if (isset($args['status'])) {
            $status = $args['status'];
            unset($query_args['status']);
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
        $query = new WP_Query($query_args);
        $posts = $query->posts;
        $result = array();
        foreach ($posts as $post) {
            $post_obj = new stdClass();
            $post_obj->ID = $post->ID;
            $post_obj->post_title = $post->post_title;
            $post_obj->post_type = $post->post_type;
            $seo_data = $this->get_post_seo_data($post->ID);
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
        global $alenseo_database;
        $data = array(
            'score' => 0,
            'status' => 'unknown',
            'status_text' => __('Nicht analysiert', 'alenseo'),
            'keyword' => get_post_meta($post_id, '_alenseo_keyword', true),
            'meta_description' => ''
        );
        $seo_score = 0;
        $seo_status = 'unknown';
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_seo_score')) {
            $seo_data = $alenseo_database->get_seo_score($post_id);
            if ($seo_data && isset($seo_data['score'])) {
                $seo_score = $seo_data['score'];
                if ($seo_score >= 80) {
                    $seo_status = 'good';
                } elseif ($seo_score >= 50) {
                    $seo_status = 'ok';
                } else {
                    $seo_status = 'poor';
                }
                update_post_meta($post_id, '_alenseo_seo_score', $seo_score);
                update_post_meta($post_id, '_alenseo_seo_status', $seo_status);
            }
        } else {
            $seo_score = get_post_meta($post_id, '_alenseo_seo_score', true);
            $seo_status = get_post_meta($post_id, '_alenseo_seo_status', true);
        }
        if ($seo_score !== '') {
            $data['score'] = intval($seo_score);
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
        $meta_description = get_post_meta($post_id, '_alenseo_meta_description', true);
        if (empty($meta_description)) {
            $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, '_aioseo_description', true);
            }
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, '_aioseop_description', true);
            }
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, 'rank_math_description', true);
            }
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, '_seopress_titles_desc', true);
            }
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, 'vc_description', true);
            }
        }
        $data['meta_description'] = $meta_description;
        return $data;
    }

    /**
     * AJAX-Handler für den API-Status
     */
    public function get_api_status() {
        // Sicherheitscheck
        if (!current_user_can('manage_options')) {
            check_ajax_referer('alenseo_ajax_nonce', 'security');
            // Berechtigungscheck
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Keine Berechtigung', 'alenseo')]);
                return;
            }
        }
        // API-Status abrufen
        $api = new Alenseo_Claude_API();
        $status = $api->get_api_status();
        wp_send_json_success($status);
    }

    /**
     * AJAX-Handler für die Post-Analyse
     */
    public function analyze_post() {
        // Sicherheitscheck
        if (!current_user_can('manage_options')) {
            check_ajax_referer('alenseo_ajax_nonce', 'security');
            // Berechtigungscheck
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => __('Keine Berechtigung', 'alenseo')]);
                return;
            }
        }
        // Post-ID validieren
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($post_id <= 0) {
            wp_send_json_error(['message' => __('Ungültige Post-ID', 'alenseo')]);
            return;
        }
        // API-Status prüfen (nur für Nicht-Admins)
        if (!current_user_can('manage_options')) {
            $api = new Alenseo_Claude_API();
            if (!$api->is_api_configured()) {
                wp_send_json_error(['message' => __('API nicht konfiguriert', 'alenseo')]);
                return;
            }
        }
        try {
            // Post analysieren
            $result = $this->analyze_post_content($post_id);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
                return;
            }
            // Erfolgreiche Analyse
            wp_send_json_success([
                'message' => __('Analyse erfolgreich abgeschlossen', 'alenseo'),
                'score' => $result['score'],
                'status' => $result['status'],
                'status_text' => $result['status_text'],
                'last_analysis' => current_time('mysql')
            ]);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Fehler bei der Analyse', 'alenseo'),
                'debug' => WP_DEBUG ? $e->getMessage() : null
            ]);
        }
    }

    /**
     * Post-Inhalt analysieren
     */
    private function analyze_post_content($post_id) {
        // Post-Daten abrufen
        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error('post_not_found', __('Post nicht gefunden', 'alenseo'));
        }

        // API-Instanz erstellen
        $api = new Alenseo_Claude_API();

        // Analyse-Prompt erstellen
        $prompt = sprintf(
            'Analysiere den folgenden Text auf SEO-Optimierung:\n\n%s\n\n%s',
            $post->post_title,
            wp_strip_all_tags($post->post_content)
        );

        // Analyse durchführen
        $result = $api->generate_text($prompt, [
            'max_tokens' => 1024,
            'temperature' => 0.3,
            'system_prompt' => 'Du bist ein SEO-Experte. Analysiere den Text und gib einen Score von 0-100 zurück.'
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        // Score extrahieren
        preg_match('/\b(\d{1,3})\b/', $result, $matches);
        $score = isset($matches[1]) ? intval($matches[1]) : 0;

        // Status bestimmen
        if ($score >= 80) {
            $status = 'good';
            $status_text = __('Gut optimiert', 'alenseo');
        } elseif ($score >= 50) {
            $status = 'ok';
            $status_text = __('Teilweise optimiert', 'alenseo');
        } else {
            $status = 'poor';
            $status_text = __('Optimierung nötig', 'alenseo');
        }

        // Ergebnisse speichern
        update_post_meta($post_id, '_alenseo_seo_score', $score);
        update_post_meta($post_id, '_alenseo_seo_status', $status);
        update_post_meta($post_id, '_alenseo_last_analysis', current_time('mysql'));

        return [
            'score' => $score,
            'status' => $status,
            'status_text' => $status_text
        ];
    }
}

function alenseo_render_ki_widget() {
    ?>
    <div id="alenseo-ki-widget">
        <h4>KI-SEO-Tools</h4>
        <div style="margin-bottom:10px;">
            <label>Thema/Text:</label><br>
            <textarea id="alenseo_ki_input" rows="3" style="width:100%"></textarea>
        </div>
        <div style="margin-bottom:10px;">
            <button class="button" onclick="alenseoKI('meta_title')">Meta-Title</button>
            <button class="button" onclick="alenseoKI('meta_description')">Meta-Description</button>
            <button class="button" onclick="alenseoKI('seo_text')">SEO-Text</button>
            <button class="button" onclick="alenseoKI('optimization')">Optimierung</button>
            <button class="button" onclick="alenseoKI('keywords')">Keywords</button>
        </div>
        <div id="alenseo_ki_result" style="background:#f9f9f9;padding:10px;border-radius:4px;min-height:40px;"></div>
        <script>
        function alenseoKI(type) {
            var val = document.getElementById('alenseo_ki_input').value;
            var result = document.getElementById('alenseo_ki_result');
            result.innerHTML = '⏳ Bitte warten...';
            var data = {
                action: '',
                nonce: '<?php echo wp_create_nonce('alenseo_ajax_nonce'); ?>',
                provider: 'openai',
            };
            if(type==='meta_title') { data.action='alenseo_generate_meta_title'; data.content=val; }
            if(type==='meta_description') { data.action='alenseo_generate_meta_description'; data.content=val; }
            if(type==='seo_text') { data.action='alenseo_generate_seo_text'; data.topic=val; }
            if(type==='optimization') { data.action='alenseo_optimization_suggestions'; data.content=val; }
            if(type==='keywords') { data.action='alenseo_keyword_analysis'; data.content=val; }
            jQuery.post(ajaxurl, data, function(resp) {
                if(resp.success) {
                    if(type==='meta_title') result.innerHTML = '<b>Meta-Title:</b><br>'+resp.data.meta_title;
                    if(type==='meta_description') result.innerHTML = '<b>Meta-Description:</b><br>'+resp.data.meta_description;
                    if(type==='seo_text') result.innerHTML = '<b>SEO-Text:</b><br>'+resp.data.seo_text;
                    if(type==='optimization') result.innerHTML = '<b>Optimierungsvorschläge:</b><br>'+resp.data.suggestions.replace(/\n/g,'<br>');
                    if(type==='keywords') result.innerHTML = '<b>Keywords:</b><br>'+resp.data.keywords;
                } else {
                    result.innerHTML = 'Fehler: '+(resp.data && resp.data.message ? resp.data.message : 'Unbekannter Fehler');
                }
            });
        }
        </script>
    </div>
    <?php
}

// Claude Multi-Modell-Admin-Interface
if (!class_exists('Claude_Model_Admin')) {
    class Claude_Model_Admin {
        public function __construct() {
            // DISABLED: Admin-Menü wird jetzt zentral in der Hauptklasse verwaltet
            // add_action('admin_menu', [$this, 'add_admin_page']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('wp_ajax_test_all_claude_models', [$this, 'ajax_test_all_models']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        }
        public function add_admin_page() {
            add_submenu_page(
                'alenseo-dashboard',
                'Claude Modelle',
                'Claude Modelle',
                'manage_options',
                'alenseo-claude-models',
                [$this, 'render_admin_page']
            );
        }
        public function register_settings() {
            register_setting('alenseo_claude_models', 'alenseo_claude_model_preferences');
            register_setting('alenseo_claude_models', 'alenseo_claude_default_model');
        }
        public function enqueue_scripts($hook) {
            if (strpos($hook, 'alenseo-claude-models') === false) {
                return;
            }
            wp_enqueue_script('jquery');
            wp_localize_script('jquery', 'alenseoClaudeAdmin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alenseo_claude_admin')
            ]);
        }
        public function render_admin_page() {
            echo '<div class="wrap"><h1>Claude Modelle</h1><p>Hier kannst du die Claude-Modelle verwalten und testen.</p></div>';
        }
        public function ajax_test_all_models() {
            check_ajax_referer('alenseo_claude_admin', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
                return;
            }
            try {
                $api = new \Alenseo\Alenseo_Claude_API();
                if (!$api->is_api_configured()) {
                    wp_send_json_error(['message' => 'Claude API nicht konfiguriert']);
                    return;
                }
                $results = $api->test_all_models();
                wp_send_json_success($results);
            } catch (\Exception $e) {
                wp_send_json_error(['message' => 'Fehler beim Testen: ' . $e->getMessage()]);
            }
        }
    }
    // Admin-Interface initialisieren
    new Claude_Model_Admin();
}