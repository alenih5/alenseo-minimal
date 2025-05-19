<?php
/**
 * AJAX-Handler für Alenseo SEO
 *
 * Diese Datei enthält alle AJAX-Handler für die Grundfunktionen
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
 * AJAX-Handler für die Analyse eines Posts
 */
function alenseo_analyze_post_ajax() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => 'Sicherheitsüberprüfung fehlgeschlagen.'));
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Unzureichende Berechtigungen.'));
        return;
    }
    
    // Erforderliche Parameter prüfen
    if (!isset($_POST['post_id'])) {
        wp_send_json_error(array('message' => 'Fehlende Post-ID.'));
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    
    try {
        // Analyzer erstellen
        if (!class_exists('Alenseo_Minimal_Analysis')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-minimal-analysis.php';
        }
        
        $analyzer = new Alenseo_Minimal_Analysis();
        
        // Analyse durchführen
        $result = $analyzer->analyze_post($post_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        // Erfolgreich
        wp_send_json_success(array(
            'message' => 'Analyse erfolgreich durchgeführt.',
            'score' => get_post_meta($post_id, '_alenseo_seo_score', true),
            'status' => get_post_meta($post_id, '_alenseo_seo_status', true)
        ));
        
    } catch (Exception $e) {
        // Fehlerbehandlung
        error_log('Alenseo SEO - AJAX-Fehler: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Fehler bei der Analyse: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_alenseo_analyze_post', 'alenseo_analyze_post_ajax');

/**
 * AJAX-Handler zum Speichern eines Keywords
 */
function alenseo_save_keyword_ajax() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => 'Sicherheitsüberprüfung fehlgeschlagen.'));
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Unzureichende Berechtigungen.'));
        return;
    }
    
    // Erforderliche Parameter prüfen
    if (!isset($_POST['post_id']) || !isset($_POST['keyword'])) {
        wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    $keyword = sanitize_text_field($_POST['keyword']);
    
    // Post-Existenz prüfen
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error(array('message' => 'Beitrag nicht gefunden.'));
        return;
    }
    
    // Keyword speichern
    $result = update_post_meta($post_id, '_alenseo_keyword', $keyword);
    
    if (false !== $result) {
        // Nach dem Speichern eine neue Analyse durchführen
        if (class_exists('Alenseo_Minimal_Analysis')) {
            $analyzer = new Alenseo_Minimal_Analysis();
            $analyzer->analyze_post($post_id);
        }
        
        wp_send_json_success(array('message' => 'Keyword erfolgreich gespeichert.'));
    } else {
        wp_send_json_error(array('message' => 'Fehler beim Speichern des Keywords.'));
    }
}
add_action('wp_ajax_alenseo_save_keyword', 'alenseo_save_keyword_ajax');
