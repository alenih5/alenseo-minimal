<?php
/**
 * AJAX-Handler für Claude API
 *
 * @link       https://imponi.ch
 * @since      1.0.0
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Sicherheitsüberprüfung
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AJAX-Handler für das Testen des Claude API-Schlüssels
 */
function alenseo_test_claude_api() {
    // Überprüfe den Nonce-Wert für Sicherheit
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Überprüfe Berechtigungen
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
        return;
    }
    
    // API-Schlüssel aus der Anfrage holen
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    
    if (empty($api_key)) {
        wp_send_json_error(array('message' => __('Kein API-Schlüssel angegeben.', 'alenseo')));
        return;
    }
    
    // API-Schlüssel temporär in den Einstellungen speichern für den Test
    $settings = get_option('alenseo_settings', array());
    $settings['claude_api_key'] = $api_key;
    update_option('alenseo_settings', $settings);
    
    // Claude API-Klasse laden
    require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
    $claude_api = new Alenseo_Claude_API();
    
    // API-Test durchführen
    $test_result = $claude_api->test_api_key();
    
    if ($test_result['success']) {
        wp_send_json_success($test_result);
    } else {
        wp_send_json_error($test_result);
    }
}
add_action('wp_ajax_alenseo_test_claude_api', 'alenseo_test_claude_api');

/**
 * AJAX-Handler für das Generieren von Keywords über die Claude API
 */
function alenseo_claude_generate_keywords() {
    // Überprüfe den Nonce-Wert für Sicherheit
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Überprüfe Berechtigungen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Post-ID aus der Anfrage holen
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    if (!$post_id) {
        wp_send_json_error(array('message' => __('Ungültige Post-ID.', 'alenseo')));
        return;
    }
    
    // Claude API-Klasse laden
    require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
    $claude_api = new Alenseo_Claude_API();
    
    // Prüfen, ob API aktiv ist
    if (!$claude_api->is_active()) {
        wp_send_json_error(array(
            'message' => __('Claude API ist nicht aktiv. Bitte konfiguriere den API-Schlüssel in den Einstellungen.', 'alenseo')
        ));
        return;
    }
    
    // Keywords generieren
    $result = $claude_api->generate_keywords($post_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_alenseo_claude_generate_keywords', 'alenseo_claude_generate_keywords');

/**
 * AJAX-Handler für das Generieren von Optimierungsvorschlägen über die Claude API
 */
function alenseo_claude_get_basic_optimization_suggestions() {
    // Überprüfe den Nonce-Wert für Sicherheit
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Überprüfe Berechtigungen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Parameter aus der Anfrage holen
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    
    if (!$post_id) {
        wp_send_json_error(array('message' => __('Ungültige Post-ID.', 'alenseo')));
        return;
    }
    
    if (empty($keyword)) {
        wp_send_json_error(array('message' => __('Kein Fokus-Keyword angegeben.', 'alenseo')));
        return;
    }
    
    // Claude API-Klasse laden
    require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
    $claude_api = new Alenseo_Claude_API();
    
    // Prüfen, ob API aktiv ist
    if (!$claude_api->is_active()) {
        wp_send_json_error(array(
            'message' => __('Claude API ist nicht aktiv. Bitte konfiguriere den API-Schlüssel in den Einstellungen.', 'alenseo')
        ));
        return;
    }
    
    // Optimierungsvorschläge generieren
    $result = $claude_api->get_optimization_suggestions($post_id, $keyword);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_alenseo_claude_get_basic_optimization_suggestions', 'alenseo_claude_get_basic_optimization_suggestions');
