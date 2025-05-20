<?php
/**
 * AJAX-Handler für Einstellungen in Alenseo SEO
 *
 * Diese Datei enthält AJAX-Handler für die Einstellungsseite
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
 * AJAX-Handler zum Testen der Claude API
 */
function alenseo_test_api_ajax() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_test_api_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unzureichende Berechtigungen.', 'alenseo')));
        return;
    }
    
    // API-Schlüssel prüfen
    if (!isset($_POST['api_key']) || empty($_POST['api_key'])) {
        wp_send_json_error(array('message' => __('Kein API-Schlüssel angegeben.', 'alenseo')));
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'claude-3-haiku-20240307';
    
    // Claude API-Klasse prüfen
    if (!class_exists('Alenseo_Claude_API')) {
        wp_send_json_error(array('message' => __('Claude API-Klasse nicht gefunden.', 'alenseo')));
        return;
    }
    
    try {
        // Temporäre API-Instanz mit dem zu testenden API-Schlüssel erstellen
        $claude_api = new Alenseo_Claude_API($api_key, $model);
        
        // API testen
        $test_result = $claude_api->test_api_key();
        
        if ($test_result === true || (is_array($test_result) && isset($test_result['success']) && $test_result['success'])) {
            wp_send_json_success(array(
                'message' => __('API-Verbindung erfolgreich. Die Claude API ist korrekt konfiguriert.', 'alenseo')
            ));
        } else {
            $error_message = is_wp_error($test_result) ? $test_result->get_error_message() : __('Unbekannter Fehler bei der API-Verbindung.', 'alenseo');
            
            if (is_array($test_result) && isset($test_result['message'])) {
                $error_message = $test_result['message'];
            }
            
            wp_send_json_error(array('message' => $error_message));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}
add_action('wp_ajax_alenseo_test_api', 'alenseo_test_api_ajax');
