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

// Neue AJAX-Handler für KI-Integration
add_action('wp_ajax_alenseo_connect_claude', 'alenseo_connect_claude');

function alenseo_connect_claude() {
    check_ajax_referer('alenseo_ajax_nonce', 'security');

    $api_key = get_option('alenseo_claude_api_key');
    if (!$api_key) {
        wp_send_json_error(array('message' => __('API-Schlüssel für Claude fehlt.', 'alenseo')));
        wp_die();
    }

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    if (empty($query)) {
        wp_send_json_error(array('message' => __('Keine Anfrage übermittelt.', 'alenseo')));
        wp_die();
    }

    // Anfrage an Claude senden (Beispiel-API-Aufruf)
    $response = wp_remote_post('https://api.claude.ai/v1/query', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array('query' => $query))
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => __('Fehler bei der Verbindung zu Claude: ', 'alenseo') . $response->get_error_message()));
        wp_die();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['error'])) {
        wp_send_json_error(array('message' => __('Claude-Fehler: ', 'alenseo') . $data['error']));
        wp_die();
    }

    if (!isset($data['result'])) {
        wp_send_json_error(array('message' => __('Unerwartete Antwort von Claude.', 'alenseo')));
        wp_die();
    }

    wp_send_json_success(array('result' => $data['result']));
    wp_die();
}

// Neue AJAX-Handler für ChatGPT-Integration
add_action('wp_ajax_alenseo_connect_chatgpt', 'alenseo_connect_chatgpt');

function alenseo_connect_chatgpt() {
    check_ajax_referer('alenseo_ajax_nonce', 'security');

    $api_key = get_option('alenseo_chatgpt_api_key');
    if (!$api_key) {
        wp_send_json_error(array('message' => __('API-Schlüssel für ChatGPT fehlt.', 'alenseo')));
        wp_die();
    }

    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    if (empty($query)) {
        wp_send_json_error(array('message' => __('Keine Anfrage übermittelt.', 'alenseo')));
        wp_die();
    }

    // Anfrage an ChatGPT senden (Beispiel-API-Aufruf)
    $response = wp_remote_post('https://api.openai.com/v1/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'model' => 'text-davinci-003',
            'prompt' => $query,
            'max_tokens' => 100
        ))
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => __('Fehler bei der Verbindung zu ChatGPT: ', 'alenseo') . $response->get_error_message()));
        wp_die();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['error'])) {
        wp_send_json_error(array('message' => __('ChatGPT-Fehler: ', 'alenseo') . $data['error']['message']));
        wp_die();
    }

    if (!isset($data['choices'][0]['text'])) {
        wp_send_json_error(array('message' => __('Unerwartete Antwort von ChatGPT.', 'alenseo')));
        wp_die();
    }

    wp_send_json_success(array('result' => trim($data['choices'][0]['text'])));
    wp_die();
}

// Registrierung der Einstellungen für die Optionen-Seite
add_action('admin_init', function() {
    register_setting('alenseo_settings_group', 'alenseo_claude_api_key');
    register_setting('alenseo_settings_group', 'alenseo_claude_model');
});
