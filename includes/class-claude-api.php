<?php
namespace Alenseo;

/**
 * Claude API-Klasse für Alenseo SEO
 * Verantwortlich für die sichere Kommunikation mit der Claude AI API
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 * @since      2.1.0
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Alenseo_Claude_API {
    
    /**
     * API-Konfiguration
     */
    private $api_key;
    private $model;
    private $api_url = 'https://api.anthropic.com/v1/messages'; // Aktualisierte URL
    private $api_version = '2023-06-01';
    
    /**
     * Rate-Limiting
     */
    private $rate_limits = [
        'requests' => 0,
        'tokens' => 0,
        'reset_time' => 0
    ];
    
    /**
     * Cache-Einstellungen
     */
    private $cache_duration = 3600; // 1 Stunde
    private $cache_prefix = 'alenseo_claude_';
    
    /**
     * Fehler-Codes
     */
    const ERROR_NO_API_KEY = 'no_api_key';
    const ERROR_INVALID_KEY = 'invalid_api_key';
    const ERROR_RATE_LIMIT = 'rate_limit_exceeded';
    const ERROR_API_ERROR = 'api_error';
    const ERROR_NETWORK = 'network_error';
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->load_settings();
        $this->load_rate_limits();
    }
    
    /**
     * Einstellungen laden
     */
    private function load_settings() {
        $settings = get_option('alenseo_settings', []);
        
        $this->api_key = isset($settings['claude_api_key']) 
            ? trim($settings['claude_api_key']) 
            : '';
            
        $this->model = isset($settings['claude_model']) 
            ? $settings['claude_model'] 
            : 'claude-3-haiku-20240307';
            
        // Cache-Dauer aus Einstellungen
        if (isset($settings['advanced']['cache_duration'])) {
            $this->cache_duration = absint($settings['advanced']['cache_duration']);
        }
    }
    
    /**
     * Rate-Limits laden
     */
    private function load_rate_limits() {
        $limits = get_transient('alenseo_claude_rate_limits');
        
        if ($limits === false || !is_array($limits)) {
            $this->reset_rate_limits();
        } else {
            $this->rate_limits = $limits;
        }
        
        // Automatisches Reset wenn Zeit abgelaufen
        if (time() > $this->rate_limits['reset_time']) {
            $this->reset_rate_limits();
        }
    }
    
    /**
     * Rate-Limits zurücksetzen
     */
    private function reset_rate_limits() {
        $this->rate_limits = [
            'requests' => 0,
            'tokens' => 0,
            'reset_time' => time() + HOUR_IN_SECONDS
        ];
        
        set_transient('alenseo_claude_rate_limits', $this->rate_limits, HOUR_IN_SECONDS);
    }
    
    /**
     * API-Schlüssel validieren
     */
    public function validate_api_key($key = null) {
        $key_to_validate = $key ?: $this->api_key;
        
        // Grundlegende Validierung
        if (empty($key_to_validate)) {
            return new \WP_Error(self::ERROR_NO_API_KEY, 'Kein API-Schlüssel vorhanden.');
        }
        
        // Format-Validierung (Claude API-Schlüssel beginnen mit "sk-ant-")
        if (!preg_match('/^sk-ant-[a-zA-Z0-9\-_]+$/', $key_to_validate)) {
            return new \WP_Error(self::ERROR_INVALID_KEY, 'Ungültiges API-Schlüssel-Format.');
        }
        
        // Längenvalidierung
        if (strlen($key_to_validate) < 20 || strlen($key_to_validate) > 200) {
            return new \WP_Error(self::ERROR_INVALID_KEY, 'API-Schlüssel hat ungültige Länge.');
        }
        
        return true;
    }
    
    /**
     * API-Schlüssel testen
     */
    public function test_api_key() {
        // Validierung
        $validation = $this->validate_api_key();
        if (is_wp_error($validation)) {
            return [
                'success' => false,
                'message' => $validation->get_error_message()
            ];
        }
        
        // Test-Anfrage mit minimalem Prompt
        $result = $this->generate_text('Antworte nur mit: OK', [
            'max_tokens' => 10,
            'temperature' => 0
        ]);
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => $result->get_error_message(),
                'error_code' => $result->get_error_code()
            ];
        }
        
        return [
            'success' => true,
            'message' => 'API-Verbindung erfolgreich!',
            'model' => $this->model,
            'rate_limits' => $this->get_rate_limit_info()
        ];
    }
    
    /**
     * Text generieren
     */
    public function generate_text($prompt, $options = []) {
        // API-Schlüssel prüfen
        $validation = $this->validate_api_key();
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Rate-Limits prüfen
        $rate_check = $this->check_rate_limits();
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        // Optionen vorbereiten
        $defaults = [
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'system_prompt' => 'Du bist ein SEO-Experte, der bei der Optimierung von Website-Inhalten hilft.',
            'use_cache' => true
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        // Cache prüfen
        if ($options['use_cache']) {
            $cache_key = $this->get_cache_key($prompt, $options);
            $cached = get_transient($cache_key);
            
            if ($cached !== false) {
                $this->update_cache_stats('hit');
                return $cached;
            }
            
            $this->update_cache_stats('miss');
        }
        
        // API-Anfrage vorbereiten
        $request_body = [
            'model' => $this->model,
            'max_tokens' => absint($options['max_tokens']),
            'temperature' => floatval($options['temperature']),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        // System-Prompt hinzufügen wenn vorhanden
        if (!empty($options['system_prompt'])) {
            $request_body['system'] = $options['system_prompt'];
        }
        
        // API-Anfrage durchführen
        $response = $this->make_api_request($request_body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Antwort extrahieren
        $text = $this->extract_text_from_response($response);
        
        if (is_wp_error($text)) {
            return $text;
        }
        
        // Tokens zählen und Rate-Limits aktualisieren
        $tokens_used = $this->count_tokens_from_response($response);
        $this->update_rate_limits($tokens_used);
        
        // Ergebnis cachen
        if ($options['use_cache']) {
            set_transient($cache_key, $text, $this->cache_duration);
        }
        
        // Nutzungsstatistiken aktualisieren
        $this->update_usage_stats($tokens_used);
        
        return $text;
    }
    
    /**
     * API-Anfrage durchführen
     */
    private function make_api_request($body) {
        $headers = [
            'Content-Type' => 'application/json',
            'x-api-key' => $this->api_key,
            'anthropic-version' => $this->api_version
        ];
        
        $args = [
                'timeout' => 60,
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'sslverify' => true
        ];
        
        // Request durchführen
        $response = wp_remote_post($this->api_url, $args);
        
        // Netzwerkfehler prüfen
        if (is_wp_error($response)) {
            $this->log_error('Network error: ' . $response->get_error_message());
            return new \WP_Error(
                self::ERROR_NETWORK,
                'Netzwerkfehler: ' . $response->get_error_message()
            );
        }
        
        // HTTP-Status prüfen
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return $this->handle_api_error($status_code, $body);
        }
        
        // JSON dekodieren
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                self::ERROR_API_ERROR,
                'Ungültige API-Antwort (JSON-Fehler)'
            );
        }
        
        return $decoded;
    }
    
    /**
     * API-Fehler behandeln
     */
    private function handle_api_error($status_code, $body) {
        $decoded = json_decode($body, true);
        $error_message = 'Unbekannter API-Fehler';
        
        if (isset($decoded['error']['message'])) {
            $error_message = $decoded['error']['message'];
        }
        
        // Spezifische Fehlerbehandlung nach Status-Code
        switch ($status_code) {
            case 401:
                return new \WP_Error(
                    self::ERROR_INVALID_KEY,
                    'Ungültiger API-Schlüssel'
                );
                
            case 429:
                return new \WP_Error(
                    self::ERROR_RATE_LIMIT,
                    'Rate-Limit überschritten. Bitte später erneut versuchen.'
                );
                
            case 400:
                return new \WP_Error(
                    self::ERROR_API_ERROR,
                    'Ungültige Anfrage: ' . $error_message
                );
                
            default:
                return new \WP_Error(
                    self::ERROR_API_ERROR,
                    sprintf('API-Fehler (%d): %s', $status_code, $error_message)
                );
        }
    }
    
    /**
     * Text aus API-Antwort extrahieren
     */
    private function extract_text_from_response($response) {
        if (!isset($response['content']) || !is_array($response['content'])) {
            return new \WP_Error(
                self::ERROR_API_ERROR,
                'Unerwartetes Antwortformat'
            );
        }
        
        $text_parts = [];
        
        foreach ($response['content'] as $content) {
            if (isset($content['type']) && $content['type'] === 'text' && isset($content['text'])) {
                $text_parts[] = $content['text'];
            }
        }
        
        if (empty($text_parts)) {
            return new \WP_Error(
                self::ERROR_API_ERROR,
                'Keine Textantwort erhalten'
            );
        }
        
        return implode("\n", $text_parts);
    }
    
    /**
     * Tokens aus Antwort zählen
     */
    private function count_tokens_from_response($response) {
        if (isset($response['usage'])) {
            $input_tokens = isset($response['usage']['input_tokens']) 
                ? absint($response['usage']['input_tokens']) 
                : 0;
                
            $output_tokens = isset($response['usage']['output_tokens']) 
                ? absint($response['usage']['output_tokens']) 
                : 0;
                
            return $input_tokens + $output_tokens;
        }
        
        // Fallback: Schätzung basierend auf Textlänge
        return 100; // Konservative Schätzung
    }
    
    /**
     * Rate-Limits prüfen
     */
    private function check_rate_limits() {
        // Limits aus Einstellungen oder Defaults
        $settings = get_option('alenseo_settings', []);
        $max_requests = isset($settings['rate_limits']['max_requests']) 
            ? absint($settings['rate_limits']['max_requests']) 
            : 50;
        $max_tokens = isset($settings['rate_limits']['max_tokens']) 
            ? absint($settings['rate_limits']['max_tokens']) 
            : 100000;
        
        // Anfragen-Limit prüfen
        if ($this->rate_limits['requests'] >= $max_requests) {
            return new \WP_Error(
                self::ERROR_RATE_LIMIT,
                sprintf(
                    'Anfragen-Limit erreicht (%d/%d). Reset um %s.',
                    $this->rate_limits['requests'],
                    $max_requests,
                    date('H:i', $this->rate_limits['reset_time'])
                )
            );
        }
        
        // Token-Limit prüfen
        if ($this->rate_limits['tokens'] >= $max_tokens) {
            return new \WP_Error(
                self::ERROR_RATE_LIMIT,
                sprintf(
                    'Token-Limit erreicht (%d/%d). Reset um %s.',
                    $this->rate_limits['tokens'],
                    $max_tokens,
                    date('H:i', $this->rate_limits['reset_time'])
                )
            );
        }
        
        return true;
    }
    
    /**
     * Rate-Limits aktualisieren
     */
    private function update_rate_limits($tokens_used) {
        $this->rate_limits['requests']++;
        $this->rate_limits['tokens'] += $tokens_used;
        
        set_transient('alenseo_claude_rate_limits', $this->rate_limits, HOUR_IN_SECONDS);
        
        // Optional: In Datenbank loggen
        $this->log_api_usage($tokens_used);
    }
    
    /**
     * Cache-Key generieren
     */
    private function get_cache_key($prompt, $options) {
        $key_data = [
            'model' => $this->model,
            'prompt' => $prompt,
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'system_prompt' => $options['system_prompt']
        ];
        
        return $this->cache_prefix . md5(serialize($key_data));
    }
    
    /**
     * Cache-Statistiken aktualisieren
     */
    private function update_cache_stats($type) {
        $stats = get_option('alenseo_cache_stats', ['hits' => 0, 'misses' => 0]);
        $stats[$type . 's']++;
        update_option('alenseo_cache_stats', $stats);
    }
    
    /**
     * Nutzungsstatistiken aktualisieren
     */
    private function update_usage_stats($tokens) {
        // Tägliche Statistik
        $today = date('Y-m-d');
        $daily_usage = get_option('alenseo_api_usage', []);
        
        if (!isset($daily_usage[$today])) {
            $daily_usage[$today] = ['requests' => 0, 'tokens' => 0];
        }
        
        $daily_usage[$today]['requests']++;
        $daily_usage[$today]['tokens'] += $tokens;
        
        // Nur die letzten 30 Tage behalten
        $daily_usage = array_slice($daily_usage, -30, 30, true);
        
        update_option('alenseo_api_usage', $daily_usage);
        
        // Monatliche Token-Statistik
        $month = date('Y-m');
        $monthly_tokens = get_option('alenseo_monthly_tokens', []);
        $monthly_tokens[$month] = isset($monthly_tokens[$month]) 
            ? $monthly_tokens[$month] + $tokens 
            : $tokens;
            
        update_option('alenseo_monthly_tokens', $monthly_tokens);
    }
    
    /**
     * API-Nutzung loggen
     */
    private function log_api_usage($tokens) {
        global $alenseo_database;
        
        if ($alenseo_database && method_exists($alenseo_database, 'log_api_usage')) {
            $alenseo_database->log_api_usage(
                substr($this->api_key, -6), // Nur letzte 6 Zeichen für Datenschutz
                'text_generation',
                $tokens,
                true,
                null
            );
        }
    }
    
    /**
     * Fehler loggen
     */
    private function log_error($message) {
        if (WP_DEBUG) {
            error_log('Alenseo Claude API: ' . $message);
        }
    }
    
    /**
     * Rate-Limit-Informationen abrufen
     */
    public function get_rate_limit_info() {
        $settings = get_option('alenseo_settings', []);
        
        return [
            'requests_used' => $this->rate_limits['requests'],
            'requests_limit' => isset($settings['rate_limits']['max_requests']) 
                ? absint($settings['rate_limits']['max_requests']) 
                : 50,
            'tokens_used' => $this->rate_limits['tokens'],
            'tokens_limit' => isset($settings['rate_limits']['max_tokens']) 
                ? absint($settings['rate_limits']['max_tokens']) 
                : 100000,
            'reset_time' => $this->rate_limits['reset_time'],
            'reset_in' => max(0, $this->rate_limits['reset_time'] - time())
        ];
    }
    
    /**
     * Verfügbare Modelle mit Details
     */
    public function get_available_models() {
        return [
            'claude-3-opus-20240229' => [
                'name' => 'Claude 3 Opus',
                'description' => 'Höchste Leistung für komplexe Aufgaben',
                'max_tokens' => 4096,
                'cost_per_1k_tokens' => 0.015
            ],
            'claude-3-sonnet-20240229' => [
                'name' => 'Claude 3 Sonnet',
                'description' => 'Ausgewogene Leistung und Geschwindigkeit',
                'max_tokens' => 4096,
                'cost_per_1k_tokens' => 0.003
            ],
            'claude-3-haiku-20240307' => [
                'name' => 'Claude 3 Haiku',
                'description' => 'Schnell und kosteneffizient',
                'max_tokens' => 4096,
                'cost_per_1k_tokens' => 0.00025
            ]
        ];
    }
    
    /**
     * Cache löschen
     */
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                $wpdb->esc_like($this->cache_prefix) . '%'
            )
        );
        
        return true;
    }

    /**
     * AJAX-Handler für Textgenerierung
     */
    public static function ajax_generate_text() {
        // Sicherheitsprüfung
        if (!check_ajax_referer('alenseo_ajax_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen']);
            return;
        }
        
        // Berechtigungsprüfung
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
            return;
        }
        
        // Eingabedaten validieren
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        
        if (empty($prompt)) {
            wp_send_json_error(['message' => 'Kein Prompt angegeben']);
            return;
        }
        
        // Optionen verarbeiten
        $options = [];
        if (isset($_POST['options']) && is_string($_POST['options'])) {
            $decoded = json_decode(stripslashes($_POST['options']), true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }
        
        // API-Instanz erstellen und Text generieren
        try {
            $api = new self();
            $result = $api->generate_text($prompt, $options);

        if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code()
                ]);
        } else {
                wp_send_json_success([
                    'text' => $result,
                    'rate_limits' => $api->get_rate_limit_info()
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'Fehler bei der Textgenerierung',
                'debug' => WP_DEBUG ? $e->getMessage() : null
            ]);
        }
    }

    /**
     * AJAX-Handler registrieren
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_alenseo_generate_text', [__CLASS__, 'ajax_generate_text']);
        add_action('wp_ajax_alenseo_test_api_key', [__CLASS__, 'ajax_test_api_key']);
        add_action('wp_ajax_alenseo_clear_api_cache', [__CLASS__, 'ajax_clear_cache']);
    }
    
    /**
     * AJAX-Handler für API-Key-Test
     */
    public static function ajax_test_api_key() {
        check_ajax_referer('alenseo_ajax_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
            return;
        }
        
        $api = new self();
        $result = $api->test_api_key();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX-Handler für Cache-Löschung
     */
    public static function ajax_clear_cache() {
        check_ajax_referer('alenseo_ajax_nonce', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
            return;
        }
        
        $api = new self();
        $api->clear_cache();
        
        wp_send_json_success(['message' => 'Cache erfolgreich gelöscht']);
    }
    
    /**
     * Prüft, ob die API korrekt konfiguriert ist
     * 
     * @return bool True wenn die API konfiguriert ist, sonst false
     */
    public function is_api_configured() {
        // API-Schlüssel muss vorhanden und gültig sein
        if (empty($this->api_key)) {
            return false;
        }
        
        // Grundlegende Validierung des API-Schlüssels
        if (!preg_match('/^sk-ant-[a-zA-Z0-9\-_]+$/', $this->api_key)) {
            return false;
        }
        
        // Modell muss ausgewählt sein
        if (empty($this->model)) {
            return false;
        }
        
        return true;
    }

    /**
     * Gibt den aktuellen API-Status zurück
     * 
     * @return array Status-Informationen
     */
    public function get_api_status() {
        $status = [
            'configured' => false,
            'valid' => false,
            'message' => '',
            'model' => $this->model,
            'last_check' => get_option('alenseo_api_last_check', 0)
        ];

        // Prüfen ob API konfiguriert ist
        if (!$this->is_api_configured()) {
            $status['message'] = __('API nicht konfiguriert', 'alenseo');
            return $status;
        }

        $status['configured'] = true;

        // Prüfen ob letzter API-Test erfolgreich war
        $last_check = get_option('alenseo_api_last_check', 0);
        $last_status = get_option('alenseo_api_last_status', false);

        if ($last_check && $last_status && (time() - $last_check) < 3600) {
            $status['valid'] = true;
            $status['message'] = __('API-Verbindung aktiv', 'alenseo');
            return $status;
        }

        // API-Test durchführen
        $test_result = $this->test_api_key();
        
        if ($test_result['success']) {
            $status['valid'] = true;
            $status['message'] = __('API-Verbindung erfolgreich', 'alenseo');
            update_option('alenseo_api_last_check', time());
            update_option('alenseo_api_last_status', true);
        } else {
            $status['message'] = $test_result['message'];
            update_option('alenseo_api_last_check', time());
            update_option('alenseo_api_last_status', false);
        }

        return $status;
    }

    /**
     * API-Status für JavaScript bereitstellen
     */
    public function get_api_status_for_js() {
        $status = $this->get_api_status();
        
        return [
            'configured' => $status['configured'],
            'valid' => $status['valid'],
            'message' => $status['message'],
            'model' => $status['model'],
            'last_check' => $status['last_check'],
            'nonce' => wp_create_nonce('alenseo_api_status')
        ];
    }
}