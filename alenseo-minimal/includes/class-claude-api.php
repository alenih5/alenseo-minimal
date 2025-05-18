<?php
/**
 * Claude API Klasse für Alenseo SEO
 *
 * @link       https://imponi.ch
 * @since      1.0.0
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

class Alenseo_Claude_API {

    /**
     * API-Schlüssel für Claude API
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_key    Der API-Schlüssel für die Claude API.
     */
    private $api_key;

    /**
     * API-Endpunkt für Claude API
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_endpoint    Der API-Endpunkt für die Claude API.
     */
    private $api_endpoint = 'https://api.anthropic.com/v1/messages';

    /**
     * API-Version für Claude API
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_version    Die API-Version für die Claude API.
     */
    private $api_version = '2023-06-01';

    /**
     * Claude-Modell
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $model    Das verwendete Claude-Modell.
     */
    private $model = 'claude-3-haiku-20240307';

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
        // Debug-Log
        error_log("Alenseo Claude API: Konstruktor aufgerufen");
        
        // Einstellungen laden
        $this->settings = get_option('alenseo_settings', array());
        
        // API-Schlüssel aus den Einstellungen holen
        $this->api_key = isset($this->settings['claude_api_key']) ? $this->settings['claude_api_key'] : '';
        
        // Modell aus den Einstellungen holen (falls gesetzt)
        if (isset($this->settings['claude_model']) && !empty($this->settings['claude_model'])) {
            $this->model = $this->settings['claude_model'];
        }
        
        error_log("Alenseo Claude API: Konstruktor abgeschlossen");
    }

    /**
     * API-Status prüfen
     * 
     * @since    1.0.0
     * @return   bool     True wenn API aktiv ist, False wenn nicht
     */
    public function is_active() {
        return !empty($this->api_key);
    }

    /**
     * API-Schlüssel prüfen
     * 
     * @since    1.0.0
     * @return   array    Ergebnis der API-Test-Anfrage
     */
    public function test_api_key() {
        error_log("Alenseo Claude API: Test API-Schlüssel");
        
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API-Schlüssel ist leer.', 'alenseo')
            );
        }
        
        $prompt = "Dies ist ein Test der Claude API. Bitte antworte nur mit 'API-Test erfolgreich'.";
        
        $response = $this->send_request($prompt);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        // Antwort auf Erfolg prüfen
        if (isset($response['content'][0]['text']) && strpos($response['content'][0]['text'], 'API-Test erfolgreich') !== false) {
            return array(
                'success' => true,
                'message' => __('API-Verbindung erfolgreich hergestellt.', 'alenseo'),
                'model' => $response['model'] ?? $this->model
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Unerwartete Antwort von der API.', 'alenseo'),
                'response' => $response
            );
        }
    }

    /**
     * Anfrage an die Claude API senden
     * 
     * @since    1.0.0
     * @param    string    $prompt     Der Eingabetext für die Anfrage
     * @param    float     $temperature Optional. Temperatur für das Modell (0.0-1.0)
     * @param    int       $max_tokens  Optional. Maximale Anzahl an Tokens in der Antwort
     * @return   array|WP_Error        Antwort der API oder WP_Error bei Fehler
     */
    public function send_request($prompt, $temperature = 0.7, $max_tokens = 1000) {
        if (empty($this->api_key)) {
            return new WP_Error('api_error', __('API-Schlüssel ist nicht konfiguriert.', 'alenseo'));
        }
        
        $headers = array(
            'Content-Type'  => 'application/json',
            'x-api-key'     => $this->api_key,
            'anthropic-version' => $this->api_version
        );
        
        $body = array(
            'model'       => $this->model,
            'messages'    => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens
        );
        
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 30 // Längeres Timeout für API-Anfragen
        ));
        
        if (is_wp_error($response)) {
            error_log("Alenseo Claude API Fehler: " . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log("Alenseo Claude API HTTP-Fehler: " . $response_code . " - " . $response_body);
            return new WP_Error(
                'api_error',
                sprintf(__('API-Fehler: %s - %s', 'alenseo'), $response_code, $response_body)
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Alenseo Claude API JSON-Fehler: " . json_last_error_msg());
            return new WP_Error('json_error', __('Fehler beim Parsen der API-Antwort.', 'alenseo'));
        }
        
        return $data;
    }

    /**
     * Generiere Keywords für einen Beitrag
     * 
     * @since    1.0.0
     * @param    int       $post_id    ID des Beitrags
     * @return   array     Array mit generierten Keywords oder Fehler
     */
    public function generate_keywords($post_id) {
        error_log("Alenseo Claude API: Generiere Keywords für Post ID " . $post_id);
        
        $post = get_post($post_id);
        
        if (!$post) {
            return array(
                'success' => false,
                'message' => __('Beitrag nicht gefunden.', 'alenseo')
            );
        }
        
        // Inhalt vorbereiten
        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        
        // Auf maximale Länge begrenzen (Claude hat Tokenlimits)
        if (strlen($content) > 4000) {
            $content = substr($content, 0, 4000) . '...';
        }
        
        // Prompt für die API erstellen
        $prompt = "Analysiere den folgenden Text und extrahiere die 5 wichtigsten Keywords oder Schlüsselbegriffe, die für SEO relevant sind. 
        Gib nur die Keywords zurück, jeweils in einer eigenen Zeile.
        
        Titel: $title
        
        Inhalt: $content";
        
        $response = $this->send_request($prompt, 0.3, 500); // Niedrige Temperatur für konsistentere Ergebnisse
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        if (isset($response['content'][0]['text'])) {
            // Antwort verarbeiten und als Array zurückgeben
            $keywords_text = trim($response['content'][0]['text']);
            $keywords = explode("\n", $keywords_text);
            
            // Keywords bereinigen
            $keywords = array_map('trim', $keywords);
            $keywords = array_filter($keywords);
            
            // Auf die ersten 5 beschränken
            $keywords = array_slice($keywords, 0, 5);
            
            return array(
                'success' => true,
                'keywords' => $keywords
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Keine Keywords gefunden.', 'alenseo')
            );
        }
    }

    /**
     * Erstelle SEO-Optimierungsvorschläge für einen Beitrag
     * 
     * @since    1.0.0
     * @param    int       $post_id       ID des Beitrags
     * @param    string    $focus_keyword Fokus-Keyword für die Optimierung
     * @return   array     Array mit Optimierungsvorschlägen oder Fehler
     */
    public function get_optimization_suggestions($post_id, $focus_keyword) {
        error_log("Alenseo Claude API: Erstelle Optimierungsvorschläge für Post ID " . $post_id);
        
        $post = get_post($post_id);
        
        if (!$post) {
            return array(
                'success' => false,
                'message' => __('Beitrag nicht gefunden.', 'alenseo')
            );
        }
        
        if (empty($focus_keyword)) {
            return array(
                'success' => false,
                'message' => __('Kein Fokus-Keyword angegeben.', 'alenseo')
            );
        }
        
        // Inhalt vorbereiten
        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = $post->post_excerpt;
        
        // Auf maximale Länge begrenzen
        if (strlen($content) > 4000) {
            $content = substr($content, 0, 4000) . '...';
        }
        
        // Meta-Beschreibung abrufen
        $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (empty($meta_description)) {
            $meta_description = get_post_meta($post_id, '_aioseo_description', true); // Alternative SEO Plugin
        }
        if (empty($meta_description)) {
            $meta_description = $excerpt;
        }
        
        // Prompt für die API erstellen
        $prompt = "Du bist ein SEO-Experte. Analysiere den folgenden Beitrag und erstelle spezifische Optimierungsvorschläge, um ihn für das Fokus-Keyword zu optimieren.
        
        Fokus-Keyword: $focus_keyword
        
        Titel: $title
        
        Meta-Beschreibung: $meta_description
        
        Inhalt: $content
        
        Gib deine Analyse in folgendem Format zurück:
        
        TITEL: [Vorschlag für optimierten Titel]
        
        META-BESCHREIBUNG: [Vorschlag für optimierte Meta-Beschreibung]
        
        INHALT-OPTIMIERUNGEN:
        1. [Erster Vorschlag]
        2. [Zweiter Vorschlag]
        3. [Dritter Vorschlag]
        
        Die Vorschläge sollten konkret und umsetzbar sein.";
        
        $response = $this->send_request($prompt, 0.7, 1500);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        if (isset($response['content'][0]['text'])) {
            $suggestions_text = $response['content'][0]['text'];
            
            // Extrahiere die vorgeschlagenen Änderungen
            $title_match = preg_match('/TITEL:\s*(.+?)(?=META-BESCHREIBUNG:|$)/s', $suggestions_text, $title_matches);
            $meta_match = preg_match('/META-BESCHREIBUNG:\s*(.+?)(?=INHALT-OPTIMIERUNGEN:|$)/s', $suggestions_text, $meta_matches);
            $content_match = preg_match('/INHALT-OPTIMIERUNGEN:\s*(.+?)$/s', $suggestions_text, $content_matches);
            
            $suggestions = array();
            
            if ($title_match && !empty(trim($title_matches[1]))) {
                $suggestions['title'] = trim($title_matches[1]);
            }
            
            if ($meta_match && !empty(trim($meta_matches[1]))) {
                $suggestions['meta_description'] = trim($meta_matches[1]);
            }
            
            if ($content_match && !empty(trim($content_matches[1]))) {
                // Einzelne Punkte extrahieren
                $content_suggestions = array();
                $lines = explode("\n", trim($content_matches[1]));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (preg_match('/^\d+\.\s+(.+)$/', $line, $point_match)) {
                        $content_suggestions[] = $point_match[1];
                    } elseif (!empty($line)) {
                        $content_suggestions[] = $line;
                    }
                }
                $suggestions['content'] = $content_suggestions;
            }
            
            return array(
                'success' => true,
                'suggestions' => $suggestions
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Keine Optimierungsvorschläge gefunden.', 'alenseo')
            );
        }
    }
}
