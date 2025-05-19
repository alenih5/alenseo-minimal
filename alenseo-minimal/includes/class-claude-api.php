<?php
/**
 * Beispiel für eine Claude API-Klasse
 * Diese Klasse ist verantwortlich für die Kommunikation mit der Claude AI API
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
     * Basis-URL für die Claude API
     * 
     * @var string
     */
    private $api_url = 'https://api.anthropic.com/v1/messages';
    
    /**
     * Initialisierung der Klasse
     */
    public function __construct() {
        // Einstellungen aus der Datenbank abrufen
        $settings = get_option('alenseo_settings', array());
        
        // API-Schlüssel setzen (falls vorhanden)
        $this->api_key = isset($settings['claude_api_key']) ? $settings['claude_api_key'] : '';
        
        // Modell setzen
        $this->model = isset($settings['claude_model']) ? $settings['claude_model'] : 'claude-3-haiku-20240307';
    }
    
    /**
     * Text mit Claude AI generieren
     * 
     * @param string $prompt   Der Prompt für Claude
     * @param array  $options  Zusätzliche Optionen für die API
     * @return string|WP_Error Der generierte Text oder ein Fehler
     */
    public function generate_text($prompt, $options = array()) {
        // API-Schlüssel prüfen
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', 'Claude API-Schlüssel ist nicht konfiguriert.');
        }
        
        // Standardoptionen setzen
        $defaults = array(
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'system' => 'Du bist ein SEO-Experte, der bei der Optimierung von Webseiten hilft.'
        );
        
        // Optionen mit Default-Werten zusammenführen
        $options = wp_parse_args($options, $defaults);
        
        // API-Anfrage vorbereiten
        $request_data = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'system' => $options['system']
        );
        
        // API-Anfrage senden
        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode($request_data),
            'timeout' => 60 // Längeres Timeout für die API-Antwort
        ));
        
        // Fehler prüfen
        if (is_wp_error($response)) {
            return $response;
        }
        
        // HTTP-Statuscode prüfen
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unbekannter API-Fehler.';
            
            return new WP_Error('api_error', $error_message, array('status' => $status_code));
        }
        
        // Antwort verarbeiten
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Antworttext extrahieren
        if (isset($data['content'][0]['text'])) {
            return $data['content'][0]['text'];
        }
        
        return new WP_Error('parsing_error', 'Die API-Antwort konnte nicht verarbeitet werden.');
    }
    
    /**
     * API-Schlüssel testen
     * 
     * @return bool|WP_Error true bei Erfolg, sonst ein Fehler
     */
    public function test_api_key() {
        // API-Schlüssel prüfen
        if (empty($this->api_key)) {
            return new WP_Error('missing_api_key', 'Claude API-Schlüssel ist nicht konfiguriert.');
        }
        
        // Einfachen Test-Prompt senden
        $test_prompt = 'Bitte antworte mit dem Wort "erfolgreich", wenn du diese Nachricht erhältst.';
        
        // Optionen mit kleinem Token-Limit
        $options = array(
            'max_tokens' => 50,
            'temperature' => 0
        );
        
        // Text generieren
        $response = $this->generate_text($test_prompt, $options);
        
        // Fehler prüfen
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Erfolg prüfen
        if (stripos($response, 'erfolgreich') !== false) {
            return true;
        }
        
        return new WP_Error('unexpected_response', 'Die API-Antwort war unerwartet: ' . $response);
    }
    
    /**
     * API-Schlüssel und Modell aktualisieren
     * 
     * @param string $api_key Der neue API-Schlüssel
     * @param string $model   Das neue Modell
     * @return bool           true bei Erfolg, sonst false
     */
    public function update_api_settings($api_key, $model) {
        // Einstellungen aus der Datenbank abrufen
        $settings = get_option('alenseo_settings', array());
        
        // API-Schlüssel und Modell aktualisieren
        $settings['claude_api_key'] = $api_key;
        $settings['claude_model'] = $model;
        
        // Eigene Eigenschaften aktualisieren
        $this->api_key = $api_key;
        $this->model = $model;
        
        // Einstellungen speichern
        return update_option('alenseo_settings', $settings);
    }
    
    /**
     * Prüfen, ob die API konfiguriert ist
     * 
     * @return bool true wenn konfiguriert, sonst false
     */
    public function is_api_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Aktuelles Modell abrufen
     * 
     * @return string Das aktuelle Modell
     */
    public function get_model() {
        return $this->model;
    }
    
    /**
     * Verfügbare Modelle abrufen
     * 
     * @return array Liste der verfügbaren Modelle
     */
    public function get_available_models() {
        return array(
            'claude-3-opus-20240229' => 'Claude 3 Opus',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku'
        );
    }
}
