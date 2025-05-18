<?php
/**
 * AJAX-Handler für Alenseo SEO
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
 * AJAX-Handler für das Generieren von Keywords
 */
function alenseo_generate_keywords() {
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
    
    // Prüfen, ob Claude API konfiguriert ist und verwenden, wenn ja
    $settings = get_option('alenseo_settings', array());
    $api_key = isset($settings['claude_api_key']) ? $settings['claude_api_key'] : '';
    
    if (!empty($api_key) && file_exists(ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php')) {
        // Claude API verwenden (wird in alenseo-claude-ajax.php implementiert)
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
        $claude_api = new Alenseo_Claude_API();
        $result = $claude_api->generate_keywords($post_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'keywords' => $result['keywords']
            ));
            return;
        }
    }
    
    // Fallback: Lokale Keyword-Generierung
    require_once ALENSEO_MINIMAL_DIR . 'includes/class-minimal-analysis.php';
    $analysis = new Alenseo_Minimal_Analysis();
    $keywords = $analysis->generate_keywords($post_id);
    
    if (empty($keywords)) {
        wp_send_json_error(array('message' => __('Keine Keywords gefunden.', 'alenseo')));
        return;
    }
    
    wp_send_json_success(array(
        'keywords' => $keywords
    ));
}
add_action('wp_ajax_alenseo_generate_keywords', 'alenseo_generate_keywords');