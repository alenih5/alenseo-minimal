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

        // Menü im Admin-Bereich registrieren
        add_action('admin_menu', array($this, 'add_dashboard_menu'));

        // AJAX-Endpunkte für das Dashboard registrieren
        add_action('wp_ajax_alenseo_get_post_data', array($this, 'get_post_data'));
        add_action('wp_ajax_alenseo_get_score_history', array($this, 'get_score_history'));
        add_action('wp_ajax_alenseo_get_stats', array($this, 'get_stats'));
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
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/dashboard-page.php';
        
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
}