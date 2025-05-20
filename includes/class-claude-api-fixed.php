<?php
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
    private $api_url = 'https://api.anthropic.com/v1/messages';
    
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
     */
    private function update_rate_limits($tokens) {
        // Wenn die Reset-Zeit erreicht ist, Limits zurücksetzen
        if (time() > $this->rate_limits['reset_time']) {
            $this->reset_rate_limits();
        }
        
        $this->rate_limits['requests']++;
        $this->rate_limits['tokens'] += $tokens;
        
        set_transient('alenseo_claude_rate_limits', $this->rate_limits, 60 * 60);
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
        
        // Statistikzähler inkrementieren
        $total_requests = get_option('alenseo_total_api_requests', 0);
        update_option('alenseo_total_api_requests', $total_requests + 1);
        
        // Gecachte Antwort verwenden, falls vorhanden
        if ($cached_response !== false) {
            // Cache-Treffer zählen
            $cache_hits = get_option('alenseo_cache_hits', 0);
            update_option('alenseo_cache_hits', $cache_hits + 1);
            
            return $cached_response;
        }
        
        // Request an API senden
        $response = wp_remote_post(
            $this->api_url,
            array(
                'timeout' => 60,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->api_key,
                    'anthropic-version' => '2023-06-01'
                ),
                'body' => json_encode($request_data)
            )
        );
        
        // Fehlerbehandlung
        $error = $this->handle_api_error($response);
        if (is_wp_error($error)) {
            return $error;
        }
        
        // Erfolgreiche Antwort verarbeiten
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['content'][0]['text'])) {
            error_log('Alenseo Claude API: Unerwartetes Antwortformat');
            return new WP_Error('unexpected_response', __('Unerwartetes Antwortformat von der API.', 'alenseo'));
        }
        
        $result = $body['content'][0]['text'];
        
        // Rate-Limits aktualisieren (geschätzte Token-Anzahl)
        $estimated_tokens = ceil(mb_strlen($prompt) / 4) + ceil(mb_strlen($result) / 4);
        $this->update_rate_limits($estimated_tokens);
        
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
        // Wenn bereits ein WP_Error, direkt zurückgeben
        if (is_wp_error($response)) {
            error_log('Alenseo Claude API Error: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        // Wenn kein 200er Code, Fehler zurückgeben
        if ($response_code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            
            // Detaillierte Fehlermeldung aus dem Body extrahieren, falls vorhanden
            if (!empty($error_data['error']) && !empty($error_data['error']['message'])) {
                $error_message = $error_data['error']['message'];
            }
            
            error_log("Alenseo Claude API Error ({$response_code}): {$error_message}");
            
            // Häufige API-Fehler mit benutzerfreundlichen Meldungen abfangen
            switch ($response_code) {
                case 400:
                    return new WP_Error(
                        'bad_request',
                        __('Ungültige Anfrage an die API. Möglicherweise ist der Prompt zu lang.', 'alenseo')
                    );
                    
                case 401:
                    return new WP_Error(
                        'unauthorized',
                        __('Ungültiger API-Schlüssel oder Authentifizierungsfehler.', 'alenseo')
                    );
                    
                case 403:
                    return new WP_Error(
                        'forbidden',
                        __('Keine Berechtigung für diese API-Anfrage.', 'alenseo')
                    );
                    
                case 404:
                    return new WP_Error(
                        'not_found',
                        __('API-Endpunkt nicht gefunden.', 'alenseo')
                    );
                    
                case 429:
                    return new WP_Error(
                        'rate_limited',
                        __('API-Limit erreicht. Bitte versuche es später erneut.', 'alenseo')
                    );
                    
                case 500:
                case 502:
                case 503:
                case 504:
                    return new WP_Error(
                        'server_error',
                        __('API-Serverfehler. Bitte versuche es später erneut.', 'alenseo')
                    );
                    
                default:
                    return new WP_Error(
                        'api_error',
                        sprintf(__('API-Fehler: %s (Code: %s)', 'alenseo'), $error_message, $response_code)
                    );
            }
        }
        
        return false; // Kein Fehler
    }
}