<?php
namespace Alenseo;

/**
 * Claude API-Klasse für Alenseo SEO
 * Diese Klasse ist verantwortlich für die Kommunikation mit der Claude AI API
 * 
 * @link        https://imponi.ch
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
 * Die Claude API-Klasse
 */
class Alenseo_Claude_API {
    
    /**
     * Der API-Schlüssel
     * 
     * @var string
     */
    private $api_key;
    
    /**
     * Das zu verwendende Modell
     * 
     * @var string
     */
    private $model;
    
    /**
     * Die API-Basis-URL
     * 
     * @var string
     */
    private $api_url = 'https://api.anthropic.com/v1/complete';
    private $api_headers = array(
        'Content-Type' => 'application/json',
        'anthropic-version' => '2023-06-01',
        'x-api-key' => '' // API-Schlüssel wird dynamisch hinzugefügt
    );
    
    /**
     * Rate-Limit-Tracking
     * 
     * @var array
     */
    private $rate_limits = array(
        'requests' => 0,
        'tokens' => 0,
        'reset_time' => 0
    );
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Einstellungen laden
        $settings = get_option('alenseo_settings', array());
        
        // API-Schlüssel und Modell aus den Einstellungen abrufen
        $this->api_key = isset($settings['claude_api_key']) ? $settings['claude_api_key'] : '';
        $this->model = isset($settings['claude_model']) ? $settings['claude_model'] : 'claude-3-haiku-20240307';
        
        // Rate-Limits aus der Datenbank laden oder initialisieren
        $rate_limits = get_transient('alenseo_claude_rate_limits');
        if ($rate_limits !== false) {
            $this->rate_limits = $rate_limits;
        } else {
            $this->reset_rate_limits();
        }
    }
    
    /**
     * Rate-Limits zurücksetzen
     */
    private function reset_rate_limits() {
        $this->rate_limits = array(
            'requests' => 0,
            'tokens' => 0,
            'reset_time' => time() + 60 * 60 // 1 Stunde von jetzt an
        );
        set_transient('alenseo_claude_rate_limits', $this->rate_limits, 60 * 60);
    }
    
    /**
     * Rate-Limits aktualisieren
     * 
     * @param int $tokens Verwendete Tokens
     * @param string $request_type Art der API-Anfrage
     * @param bool $success War die Anfrage erfolgreich?
     * @param string $error_message Optionale Fehlermeldung
     */
    private function update_rate_limits($tokens, $request_type = 'completion', $success = true, $error_message = null) {
        // Wenn die Reset-Zeit erreicht ist, Limits zurücksetzen
        if (time() > $this->rate_limits['reset_time']) {
            $this->reset_rate_limits();
        }
        
        $this->rate_limits['requests']++;
        $this->rate_limits['tokens'] += $tokens;
        
        set_transient('alenseo_claude_rate_limits', $this->rate_limits, 60 * 60);
        
        // API-Nutzung in der Datenbank protokollieren, wenn die Datenbank-Klasse verfügbar ist
        global $alenseo_database;
        if (isset($alenseo_database) && method_exists($alenseo_database, 'log_api_usage')) {
            $alenseo_database->log_api_usage(
                $this->api_key,
                $request_type,
                $tokens,
                $success,
                $error_message
            );
        }
    }
    
    /**
     * Prüfen, ob das Rate-Limit überschritten wurde
     * 
     * @return bool|WP_Error true wenn OK, WP_Error wenn Limit erreicht
     */
    private function check_rate_limits() {
        // Wenn die Reset-Zeit erreicht ist, Limits zurücksetzen
        if (time() > $this->rate_limits['reset_time']) {
            $this->reset_rate_limits();
            return true;
        }
        
        // Beispiel-Limits (anpassen nach tatsächlichen API-Limits)
        $max_requests = 50; // Max Anfragen pro Stunde
        $max_tokens = 100000; // Max Tokens pro Stunde
        
        if ($this->rate_limits['requests'] >= $max_requests) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('API-Anfragenlimit erreicht. Bitte warte bis %s.', 'alenseo'),
                    date('H:i', $this->rate_limits['reset_time'])
                )
            );
        }
        
        if ($this->rate_limits['tokens'] >= $max_tokens) {
            return new WP_Error(
                'token_limit_exceeded',
                sprintf(
                    __('API-Tokenlimit erreicht. Bitte warte bis %s.', 'alenseo'),
                    date('H:i', $this->rate_limits['reset_time'])
                )
            );
        }
        
        return true;
    }
    
    /**
     * API-Schlüssel testen
     * 
     * @return array Erfolgs-/Fehlerinformationen
     */
    public function test_key() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('Kein API-Schlüssel konfiguriert.', 'alenseo')
            );
        }
        
        // Überprüfen ob API-Schlüssel zu lang ist
        if (strlen($this->api_key) > 100) {
            return array(
                'success' => false,
                'message' => __('API-Schlüssel ist zu lang. Bitte überprüfen Sie den eingegebenen Schlüssel.', 'alenseo')
            );
        }
        
        // Minimalen Prompt für den Test erstellen
        $result = $this->generate_text("Bitte antworte mit dem Wort: Test");
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        return array(
            'success' => true,
            'message' => __('API-Verbindung erfolgreich getestet!', 'alenseo'),
            'model' => $this->model
        );
    }
    
    /**
     * Prüft, ob der API-Schlüssel konfiguriert ist
     * 
     * @return bool True wenn API-Schlüssel konfiguriert ist, sonst False
     */
    public function is_api_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * API-Schlüssel testen (Alias für test_key für bessere Lesbarkeit)
     * 
     * @return bool|WP_Error true bei Erfolg, WP_Error bei Fehler
     */
    public function test_api_key() {
        return $this->test_key();
    }
    
    /**
     * Verfügbare Modelle abrufen
     * 
     * @return array Liste der verfügbaren Claude-Modelle
     */
    public function get_available_models() {
        return array(
            'claude-3-opus-20240229' => 'Claude 3 Opus',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku',
            'claude-2.0' => 'Claude 2',
            'claude-instant-1.2' => 'Claude Instant'
        );
    }
    
    /**
     * Text mit Claude generieren
     * 
     * @param string $prompt Der Prompt für Claude
     * @param array $options Zusätzliche Optionen
     * @return string|WP_Error Die generierte Antwort oder Fehler
     */
    public function generate_text($prompt, $options = array()) {
        // Prüfen, ob API-Schlüssel vorhanden
        if (empty($this->api_key)) {
            return new WP_Error('empty_key', __('Kein API-Schlüssel konfiguriert.', 'alenseo'));
        }
        
        // Rate-Limits prüfen
        $rate_check = $this->check_rate_limits();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        // Standard-Optionen festlegen
        $defaults = array(
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'system_prompt' => 'Du bist ein SEO-Experte und hilfst, Website-Inhalte zu optimieren.'
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Request-Daten vorbereiten
        $request_data = array(
            'model' => $this->model,
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $options['system_prompt']
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        // Caching-Key erstellen
        $cache_key = 'alenseo_claude_' . md5($this->model . json_encode($request_data));
        $cached_response = get_transient($cache_key);
        
        // Gecachte Antwort verwenden, falls vorhanden
        if ($cached_response !== false) {
            // Cache-Statistiken aktualisieren
            $cache_stats = get_option('alenseo_cache_stats', array('hits' => 0, 'misses' => 0));
            $cache_stats['hits']++;
            update_option('alenseo_cache_stats', $cache_stats);
            
            return $cached_response;
        }
        
        // Cache-Miss zählen
        $cache_stats = get_option('alenseo_cache_stats', array('hits' => 0, 'misses' => 0));
        $cache_stats['misses']++;
        update_option('alenseo_cache_stats', $cache_stats);
        
        // API-Nutzung für heute zählen
        $today = date('Y-m-d');
        $api_usage = get_option('alenseo_api_usage', array());
        $api_usage[$today] = isset($api_usage[$today]) ? $api_usage[$today] + 1 : 1;
        update_option('alenseo_api_usage', $api_usage);
        
        // Request an API senden
        $this->api_headers['x-api-key'] = $this->api_key;
        $response = wp_remote_post(
            $this->api_url,
            array(
                'timeout' => 60,
                'headers' => $this->api_headers,
                'body' => json_encode($request_data)
            )
        );
        
        // Fehlerbehandlung
        $error = $this->handle_api_error($response);
        if (is_wp_error($error)) {
            return $error;
        }
        
        // Erfolgreiche Antwort verarbeiten
        $body = $this->parse_response($response);
        if (is_wp_error($body)) {
            return $body;
        }
        
        if (!isset($body['content'][0]['text'])) {
            error_log('Alenseo Claude API: Unerwartetes Antwortformat');
            return new WP_Error('unexpected_response', __('Unerwartetes Antwortformat von der API.', 'alenseo'));
        }
        
        $result = $body['content'][0]['text'];
        
        // Tatsächliche Token-Anzahl aus der API-Antwort auslesen
        $actual_input_tokens = isset($body['usage']['input_tokens']) ? intval($body['usage']['input_tokens']) : 0;
        $actual_output_tokens = isset($body['usage']['output_tokens']) ? intval($body['usage']['output_tokens']) : 0;
        $total_tokens = $actual_input_tokens + $actual_output_tokens;
        
        // Wenn keine Token-Informationen verfügbar sind, schätzen wir sie
        if ($total_tokens === 0) {
            $total_tokens = ceil(mb_strlen($prompt) / 4) + ceil(mb_strlen($result) / 4);
        }
        
        // Rate-Limits aktualisieren mit tatsächlicher Token-Anzahl
        $this->update_rate_limits($total_tokens, 'text_completion', true);
        
        // Tokens für diesen Monat zählen
        $current_month = date('Y-m');
        $token_usage = get_option('alenseo_token_usage', array());
        $token_usage[$current_month] = isset($token_usage[$current_month]) ? 
            $token_usage[$current_month] + $total_tokens : 
            $total_tokens;
        update_option('alenseo_token_usage', $token_usage);
        
        // Ergebnis cachen (1 Stunde)
        set_transient($cache_key, $result, 60 * 60);
        
        return $result;
    }
    
    /**
     * Erweiterte Fehlerbehandlung für API-Anfragen
     * 
     * @param WP_Error|array $response Die API-Antwort
     * @return WP_Error|false WP_Error bei Fehler, false bei Erfolg
     */
    private function handle_api_error($response) {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $message = wp_remote_retrieve_response_message($response);
            return new WP_Error('api_error', $message);
        }

        return false;
    }

    /**
     * JSON-Antworten parsen
     */
    private function parse_response($response) {
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return $body;
    }

    /**
     * AJAX-Handler für Textgenerierung
     */
    public static function ajax_generate_text() {
        check_ajax_referer('alenseo_settings_nonce', 'security');

        $prompt = isset($_POST['prompt']) ? sanitize_text_field($_POST['prompt']) : '';
        $options = isset($_POST['options']) ? json_decode(stripslashes($_POST['options']), true) : array();

        $instance = new self();
        $result = $instance->generate_text($prompt, $options);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('text' => $result));
        }
    }

    /**
     * AJAX-Handler registrieren
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_alenseo_generate_text', array(__CLASS__, 'ajax_generate_text'));
    }

    public function get_cached_response($key, $callback, $expiration = 3600) {
        $cached = get_transient($key);
        if ($cached !== false) {
            return $cached;
        }

        $response = $callback();
        if ($response) {
            set_transient($key, $response, $expiration);
        }

        return $response;
    }

    public function fetch_keywords($prompt) {
        $cache_key = 'claude_keywords_' . md5($prompt);
        return $this->get_cached_response($cache_key, function() use ($prompt) {
            // ...existing API request logic...
            $response = $this->send_request($prompt);
            return $response;
        });
    }
}