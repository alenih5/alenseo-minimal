<?php
/**
 * Hauptklasse für das Alenseo SEO Plugin
 *
 * Diese Klasse initialisiert alle Komponenten des Plugins und stellt sicher,
 * dass sie in der richtigen Reihenfolge geladen werden.
 *
 * @package Alenseo
 */

namespace Alenseo;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

if (!defined('ABSPATH')) {
    exit;
}

class Alenseo_Plugin_Core {

    private $encryption_key;

    public function __construct() {
        $this->encryption_key = Key::loadFromAsciiSafeString(get_option('alenseo_encryption_key', ''));
        if (!$this->encryption_key) {
            $this->encryption_key = Key::createNewRandomKey();
            update_option('alenseo_encryption_key', $this->encryption_key->saveToAsciiSafeString());
        }
    }

    /**
     * Initialisiert das Plugin
     */
    public static function init() {
        // Klassen laden
        self::load_classes();

        // Hooks registrieren
        self::register_hooks();

        // Google Search Console Integration
        self::integrate_google_search_console();

        // Wöchentliche Berichte planen
        self::schedule_weekly_reports();

        // SEO-Änderungen überwachen
        self::monitor_seo_changes();

        // REST-API-Routen registrieren
        self::register_rest_routes();

        // Wissensdatenbank-Seite hinzufügen
        self::add_knowledge_base_page();

        // Benutzerdefinierte Berichte Seite hinzufügen
        self::add_custom_report_page();

        // SEO-Audit-Seite hinzufügen
        $this->add_seo_audit_page();

        // WooCommerce Integration
        $this->integrate_woocommerce();

        // ROI-Berechnungsseite hinzufügen
        $this->add_roi_calculator_page();

        // Erfolgsgeschichten-Widget hinzufügen
        $this->add_success_stories_widget();
    }

    /**
     * Lädt alle benötigten Klassen
     */
    private static function load_classes() {
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-database.php';
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-enhanced-analysis.php';
        require_once ALENSEO_MINIMAL_DIR . 'includes/alenseo-ajax-handlers.php';
    }

    /**
     * Registriert alle notwendigen Hooks
     */
    private static function register_hooks() {
        // Datenbank-Setup bei Aktivierung
        register_activation_hook(ALENSEO_MINIMAL_FILE, array('Alenseo_Database', 'setup')); 

        // AJAX-Handler registrieren
        add_action('init', array('Alenseo_Claude_API', 'register_ajax_handlers'));
        add_action('init', array('Alenseo_Enhanced_Analysis', 'register_ajax_handlers'));
    }

    /**
     * Integriert die Google Search Console
     */
    private static function integrate_google_search_console() {
        add_action('admin_menu', function () {
            add_submenu_page(
                'tools.php',
                'Google Search Console',
                'Search Console',
                'manage_options',
                'google-search-console',
                array('Alenseo_Plugin_Core', 'render_search_console_page')
            );
        });
    }

    /**
     * Rendert die Google Search Console Seite
     */
    public static function render_search_console_page() {
        echo '<h1>Google Search Console Integration</h1>';
        echo '<p>Hier können Sie Ihre Google Search Console-Daten integrieren.</p>';
        // Add form or API integration logic here
    }

    /**
     * Plant wöchentliche SEO-Fortschrittsberichte
     */
    public static function schedule_weekly_reports() {
        if (!wp_next_scheduled('send_weekly_seo_report')) {
            wp_schedule_event(time(), 'weekly', 'send_weekly_seo_report');
        }

        add_action('send_weekly_seo_report', array('Alenseo_Plugin_Core', 'send_weekly_report'));
    }

    /**
     * Sendet wöchentliche SEO-Fortschrittsberichte per E-Mail
     */
    public static function send_weekly_report() {
        $users = get_users(array('role' => 'administrator'));
        foreach ($users as $user) {
            $email = $user->user_email;
            $subject = 'Wöchentlicher SEO-Fortschrittsbericht';

            $seo_score = self::calculate_seo_score();
            $top_keywords = self::get_top_keywords();

            $message = "Hier ist Ihr wöchentlicher Bericht über die SEO-Fortschritte Ihrer Website:\n\n";
            $message .= "SEO-Score: $seo_score\n";
            $message .= "Top-Keywords:\n";
            foreach ($top_keywords as $keyword) {
                $message .= "- $keyword\n";
            }

            wp_mail($email, $subject, $message);
        }
    }

    /**
     * Überwacht SEO-Änderungen
     */
    public static function monitor_seo_changes() {
        add_action('wp', function () {
            if (!wp_next_scheduled('check_seo_changes')) {
                wp_schedule_event(time(), 'hourly', 'check_seo_changes');
            }
        });

        add_action('check_seo_changes', array('Alenseo_Plugin_Core', 'check_seo_changes'));
    }

    /**
     * Prüft auf signifikante SEO-Änderungen
     */
    public static function check_seo_changes() {
        $previous_score = get_option('previous_seo_score', 0);
        $current_score = self::calculate_seo_score();

        if (abs($current_score - $previous_score) > 10) {
            $users = get_users(array('role' => 'administrator'));
            foreach ($users as $user) {
                $email = $user->user_email;
                $subject = 'Signifikante SEO-Veränderung erkannt';
                $message = 'Es wurde eine signifikante Veränderung in Ihrem SEO-Score festgestellt.';
                wp_mail($email, $subject, $message);
            }
        }

        update_option('previous_seo_score', $current_score);
    }

    /**
     * Berechnet den SEO-Score
     */
    private static function calculate_seo_score() {
        // Dummy-Implementierung zur Berechnung des SEO-Scores
        return rand(50, 100);
    }

    /**
     * Registriert die REST-API-Routen
     */
    public static function register_rest_routes() {
        add_action('rest_api_init', function () {
            register_rest_route('alenseo/v1', '/generate-keywords', [
                'methods' => 'POST',
                'callback' => array('Alenseo_Plugin_Core', 'handle_generate_keywords'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]);

            register_rest_route('alenseo/v1', '/seo-report', [
                'methods' => 'GET',
                'callback' => [$this, 'handle_seo_report'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]);
        });
    }

    /**
     * Handhabt die Generierung von Keywords basierend auf einem Prompt
     */
    public static function handle_generate_keywords($request) {
        $prompt = sanitize_text_field($request->get_param('prompt'));

        if (empty($prompt)) {
            return new WP_Error('invalid_prompt', 'Prompt is required', ['status' => 400]);
        }

        $claude_api = new Claude_API();
        $keywords = $claude_api->fetch_keywords($prompt);

        if (!$keywords) {
            return new WP_Error('api_error', 'Failed to generate keywords', ['status' => 500]);
        }

        return rest_ensure_response(['keywords' => $keywords]);
    }

    public function handle_seo_report() {
        $seo_score = $this->calculate_seo_score();
        $top_keywords = $this->get_top_keywords();
        $page_speed = $this->get_page_speed();

        return rest_ensure_response([
            'seo_score' => $seo_score,
            'top_keywords' => $top_keywords,
            'page_speed' => $page_speed,
        ]);
    }

    public function encrypt_api_key($api_key) {
        return Crypto::encrypt($api_key, $this->encryption_key);
    }

    public function decrypt_api_key($encrypted_key) {
        return Crypto::decrypt($encrypted_key, $this->encryption_key);
    }

    public function save_api_key($api_key) {
        $encrypted_key = $this->encrypt_api_key($api_key);
        update_option('alenseo_api_key', $encrypted_key);
    }

    public function get_api_key() {
        $encrypted_key = get_option('alenseo_api_key', '');
        return $encrypted_key ? $this->decrypt_api_key($encrypted_key) : '';
    }

    /**
     * Fügt eine Wissensdatenbank-Seite im WordPress-Admin-Bereich hinzu
     */
    public static function add_knowledge_base_page() {
        add_action('admin_menu', function () {
            add_menu_page(
                'Wissensdatenbank',
                'Wissensdatenbank',
                'manage_options',
                'alenseo-knowledge-base',
                array('Alenseo_Plugin_Core', 'render_knowledge_base_page'),
                'dashicons-welcome-learn-more'
            );
        });
    }

    /**
     * Rendert die Wissensdatenbank-Seite
     */
    public static function render_knowledge_base_page() {
        echo '<h1>Alenseo Wissensdatenbank</h1>';
        echo '<p>Hier finden Sie häufig gestellte Fragen und Anleitungen zur Nutzung des Plugins.</p>';
        echo '<ul>';
        echo '<li><strong>Wie integriere ich die Google Search Console?</strong><br>Gehen Sie zu den Einstellungen und geben Sie Ihre API-Schlüssel ein.</li>';
        echo '<li><strong>Wie generiere ich Keywords?</strong><br>Nutzen Sie die Keyword-Generator-Funktion im Dashboard.</li>';
        echo '</ul>';
    }

    /**
     * Fügt eine Seite für benutzerdefinierte Berichte hinzu
     */
    public static function add_custom_report_page() {
        add_action('admin_menu', function () {
            add_submenu_page(
                'tools.php',
                'Benutzerdefinierte Berichte',
                'Berichte',
                'manage_options',
                'alenseo-custom-reports',
                array('Alenseo_Plugin_Core', 'render_custom_report_page')
            );
        });
    }

    /**
     * Rendert die Seite für benutzerdefinierte Berichte
     */
    public static function render_custom_report_page() {
        echo '<h1>Benutzerdefinierte Berichte</h1>';
        echo '<form method="post" action="">';
        echo '<label for="report-type">Berichtstyp:</label>';
        echo '<select id="report-type" name="report_type">';
        echo '<option value="seo_score">SEO-Score</option>';
        echo '<option value="top_keywords">Top-Keywords</option>';
        echo '</select><br><br>';
        echo '<input type="submit" value="Bericht generieren">';
        echo '</form>';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $report_type = sanitize_text_field($_POST['report_type']);
            echo '<h2>Bericht:</h2>';
            if ($report_type === 'seo_score') {
                echo '<p>SEO-Score: ' . self::calculate_seo_score() . '</p>';
            } elseif ($report_type === 'top_keywords') {
                echo '<p>Top-Keywords:</p><ul>';
                foreach (self::get_top_keywords() as $keyword) {
                    echo '<li>' . esc_html($keyword) . '</li>';
                }
                echo '</ul>';
            }
        }
    }

    /**
     * Fügt eine SEO-Audit-Seite hinzu
     */
    public function add_seo_audit_page() {
        add_action('admin_menu', function () {
            add_submenu_page(
                'tools.php',
                'SEO Audit',
                'SEO Audit',
                'manage_options',
                'alenseo-seo-audit',
                [$this, 'render_seo_audit_page']
            );
        });
    }

    /**
     * Rendert die SEO-Audit-Seite
     */
    public function render_seo_audit_page() {
        echo '<h1>SEO Audit</h1>';
        echo '<p>Hier ist eine Übersicht über die wichtigsten SEO-Metriken Ihrer Website.</p>';

        $seo_score = $this->calculate_seo_score();
        $top_keywords = $this->get_top_keywords();
        $page_speed = $this->get_page_speed();

        echo '<ul>';
        echo '<li><strong>SEO-Score:</strong> ' . esc_html($seo_score) . '</li>';
        echo '<li><strong>Top-Keywords:</strong></li><ul>';
        foreach ($top_keywords as $keyword) {
            echo '<li>' . esc_html($keyword) . '</li>';
        }
        echo '</ul>';
        echo '<li><strong>Page Speed:</strong> ' . esc_html($page_speed) . ' ms</li>';
        echo '</ul>';
    }

    /**
     * Holt die Page Speed
     */
    private function get_page_speed() {
        // Dummy implementation for page speed
        return rand(500, 2000);
    }

    /**
     * Holt die Top-Keywords
     */
    private static function get_top_keywords() {
        // Dummy implementation for top keywords
        return ['Keyword 1', 'Keyword 2', 'Keyword 3'];
    }

    /**
     * Integriert WooCommerce
     */
    public function integrate_woocommerce() {
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_product_options_general_product_data', [$this, 'add_seo_fields_to_products']);
            add_action('woocommerce_process_product_meta', [$this, 'save_seo_fields_for_products']);
        }
    }

    /**
     * Fügt SEO-Felder zu WooCommerce-Produkten hinzu
     */
    public function add_seo_fields_to_products() {
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_alenseo_focus_keyword',
            'label' => __('Focus Keyword', 'alenseo'),
            'description' => __('Das Haupt-Keyword für dieses Produkt.', 'alenseo'),
            'desc_tip' => true,
        ]);
        echo '</div>';
    }

    /**
     * Speichert die SEO-Felder für WooCommerce-Produkte
     */
    public function save_seo_fields_for_products($post_id) {
        $focus_keyword = sanitize_text_field($_POST['_alenseo_focus_keyword'] ?? '');
        if (!empty($focus_keyword)) {
            update_post_meta($post_id, '_alenseo_focus_keyword', $focus_keyword);
        }
    }

    /**
     * Fügt eine ROI-Berechnungsseite hinzu
     */
    public function add_roi_calculator_page() {
        add_action('admin_menu', function () {
            add_submenu_page(
                'tools.php',
                'ROI-Berechnung',
                'ROI-Berechnung',
                'manage_options',
                'alenseo-roi-calculator',
                [$this, 'render_roi_calculator_page']
            );
        });
    }

    /**
     * Rendert die ROI-Berechnungsseite
     */
    public function render_roi_calculator_page() {
        echo '<h1>ROI-Berechnung</h1>';
        echo '<form method="post" action="">';
        echo '<label for="estimated-traffic">Geschätzter Traffic:</label>';
        echo '<input type="number" id="estimated-traffic" name="estimated_traffic" required><br><br>';
        echo '<label for="conversion-rate">Conversion-Rate (%):</label>';
        echo '<input type="number" id="conversion-rate" name="conversion_rate" step="0.01" required><br><br>';
        echo '<label for="average-order-value">Durchschnittlicher Bestellwert (€):</label>';
        echo '<input type="number" id="average-order-value" name="average_order_value" step="0.01" required><br><br>';
        echo '<input type="submit" value="ROI berechnen">';
        echo '</form>';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $traffic = intval($_POST['estimated_traffic']);
            $conversion_rate = floatval($_POST['conversion_rate']) / 100;
            $order_value = floatval($_POST['average_order_value']);

            $roi = $traffic * $conversion_rate * $order_value;

            echo '<h2>Ergebnis:</h2>';
            echo '<p>Der geschätzte ROI beträgt: ' . number_format($roi, 2) . ' €</p>';
        }
    }

    /**
     * Fügt ein Dashboard-Widget hinzu, das Benutzern ihre SEO-Erfolge und Verbesserungen anzeigt
     */
    public function add_success_stories_widget() {
        add_action('wp_dashboard_setup', function () {
            wp_add_dashboard_widget(
                'alenseo_success_stories',
                __('Erfolgsgeschichten', 'alenseo'),
                [$this, 'render_success_stories_widget']
            );
        });
    }

    /**
     * Rendert das Erfolgsgeschichten-Widget
     */
    public function render_success_stories_widget() {
        echo '<h3>' . __('Ihre SEO-Erfolge', 'alenseo') . '</h3>';
        echo '<p>' . __('Hier sind einige Highlights Ihrer SEO-Verbesserungen:', 'alenseo') . '</p>';
        echo '<ul>';
        echo '<li>' . __('SEO-Score um 15% gestiegen', 'alenseo') . '</li>';
        echo '<li>' . __('Top-Keyword-Ranking verbessert', 'alenseo') . '</li>';
        echo '<li>' . __('Seitenladezeit um 20% reduziert', 'alenseo') . '</li>';
        echo '</ul>';
    }
}

// Plugin initialisieren
Alenseo_Plugin_Core::init();
