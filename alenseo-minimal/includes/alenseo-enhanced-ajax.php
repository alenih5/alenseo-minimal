<?php
/**
 * AJAX-Handler für die erweiterten Optimierungsfunktionen
 *
 * @link       https://imponi.ch
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
 * AJAX-Handler für das Speichern eines Keywords
 */
function alenseo_save_keyword() {
    // Sicherheitsüberprüfung
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Berechtigungen prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Parameter abrufen
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    
    if (!$post_id) {
        wp_send_json_error(array('message' => __('Ungültige Post-ID.', 'alenseo')));
        return;
    }
    
    if (empty($keyword)) {
        wp_send_json_error(array('message' => __('Kein Keyword angegeben.', 'alenseo')));
        return;
    }
    
    // Keyword speichern
    update_post_meta($post_id, '_alenseo_keyword', $keyword);
    
    // Für Yoast SEO
    update_post_meta($post_id, '_yoast_wpseo_focuskw', $keyword);
    
    // Für All in One SEO
    update_post_meta($post_id, '_aioseop_keywords', $keyword);
    
    // Für Rank Math
    update_post_meta($post_id, 'rank_math_focus_keyword', $keyword);
    
    // WPBakery (falls vorhanden)
    update_post_meta($post_id, 'vc_seo_keyword', $keyword);
    
    // SEO-Score zurücksetzen, damit neu analysiert wird
    delete_post_meta($post_id, '_alenseo_seo_score');
    delete_post_meta($post_id, '_alenseo_seo_status');
    
    wp_send_json_success(array(
        'message' => __('Keyword erfolgreich gespeichert.', 'alenseo')
    ));
}
add_action('wp_ajax_alenseo_save_keyword', 'alenseo_save_keyword');

/**
 * AJAX-Handler für das Anwenden von Optimierungsvorschlägen
 */
function alenseo_apply_suggestion() {
    // Sicherheitsüberprüfung
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Berechtigungen prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Parameter abrufen
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
    
    if (!$post_id) {
        wp_send_json_error(array('message' => __('Ungültige Post-ID.', 'alenseo')));
        return;
    }
    
    if (empty($type) || empty($content)) {
        wp_send_json_error(array('message' => __('Unvollständige Parameter.', 'alenseo')));
        return;
    }
    
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error(array('message' => __('Beitrag nicht gefunden.', 'alenseo')));
        return;
    }
    
    $update_data = array();
    
    // Je nach Typ den entsprechenden Inhalt aktualisieren
    switch ($type) {
        case 'title':
            // Titel aktualisieren
            $update_data['ID'] = $post_id;
            $update_data['post_title'] = $content;
            
            // Für Yoast SEO
            update_post_meta($post_id, '_yoast_wpseo_title', $content);
            
            // Für All in One SEO
            update_post_meta($post_id, '_aioseop_title', $content);
            
            // Für Rank Math
            update_post_meta($post_id, 'rank_math_title', $content);
            break;
            
        case 'meta_description':
            // Meta-Description aktualisieren
            
            // Für Alenseo
            update_post_meta($post_id, '_alenseo_meta_description', $content);
            
            // Für Yoast SEO
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $content);
            
            // Für All in One SEO
            update_post_meta($post_id, '_aioseo_description', $content);
            update_post_meta($post_id, '_aioseop_description', $content);
            
            // Für Rank Math
            update_post_meta($post_id, 'rank_math_description', $content);
            
            // Für SEOPress
            update_post_meta($post_id, '_seopress_titles_desc', $content);
            
            // WPBakery (falls vorhanden)
            update_post_meta($post_id, 'vc_description', $content);
            break;
            
        case 'content':
            // Content-Vorschläge speichern
            update_post_meta($post_id, '_alenseo_content_suggestions', $content);
            break;
            
        default:
            wp_send_json_error(array('message' => __('Ungültiger Typ.', 'alenseo')));
            return;
    }
    
    // Wenn Titel aktualisiert werden soll, wp_update_post ausführen
    if (!empty($update_data)) {
        $result = wp_update_post($update_data);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
    }
    
    // SEO-Score zurücksetzen, damit neu analysiert wird
    delete_post_meta($post_id, '_alenseo_seo_score');
    delete_post_meta($post_id, '_alenseo_seo_status');
    
    wp_send_json_success(array(
        'message' => __('Optimierungsvorschlag erfolgreich angewendet.', 'alenseo'),
        'type' => $type
    ));
}
add_action('wp_ajax_alenseo_apply_suggestion', 'alenseo_apply_suggestion');

/**
 * AJAX-Handler für die Analyse eines einzelnen Beitrags
 */
function alenseo_analyze_post() {
    // Sicherheitsüberprüfung
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Berechtigungen prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Post-ID abrufen
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    if (!$post_id) {
        wp_send_json_error(array('message' => __('Ungültige Post-ID.', 'alenseo')));
        return;
    }
    
    // Post laden
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error(array('message' => __('Beitrag nicht gefunden.', 'alenseo')));
        return;
    }
    
    // Fokus-Keyword abrufen
    $focus_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
    
    // Analysis-Klasse laden
    require_once ALENSEO_MINIMAL_DIR . 'includes/class-minimal-analysis.php';
    $analysis = new Alenseo_Minimal_Analysis($post_id, $focus_keyword);
    
    // Analyse durchführen
    $analysis_result = $analysis->analyze();
    
    // Analysedatum speichern
    update_post_meta($post_id, '_alenseo_last_analysis', current_time('mysql'));
    
    wp_send_json_success(array(
        'message' => __('Analyse erfolgreich durchgeführt.', 'alenseo'),
        'analysis' => $analysis_result
    ));
}
add_action('wp_ajax_alenseo_analyze_post', 'alenseo_analyze_post');

/**
 * AJAX-Handler für das Abrufen von Content-Statistiken
 */
function alenseo_get_content_stats() {
    // Sicherheitsüberprüfung
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Berechtigungen prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Dashboard-Klasse laden
    require_once ALENSEO_MINIMAL_DIR . 'includes/class-dashboard.php';
    $dashboard = new Alenseo_Dashboard();
    
    // Statistiken abrufen
    $stats = $dashboard->get_content_statistics();
    
    wp_send_json_success(array(
        'stats' => $stats
    ));
}
add_action('wp_ajax_alenseo_get_content_stats', 'alenseo_get_content_stats');

/**
 * AJAX-Handler für den optimierten Keyword-Generator
 * Diese Funktion dient als Schnittstelle zwischen der Meta-Box im Editor
 * und dem zentralen Dashboard-System
 */
function alenseo_enhanced_generate_keywords() {
    // Sicherheitsüberprüfung
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Berechtigungen prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Post-ID abrufen
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
        // Wenn nicht, Fallback zum lokalen Generator
        if (function_exists('alenseo_generate_keywords')) {
            // Originalen AJAX-Handler aufrufen
            return alenseo_generate_keywords();
        } else {
            wp_send_json_error(array(
                'message' => __('Claude API ist nicht aktiv und der lokale Generator ist nicht verfügbar.', 'alenseo')
            ));
            return;
        }
    }
    
    // Keywords generieren
    $result = $claude_api->generate_keywords($post_id);
    
    if ($result['success']) {
        wp_send_json_success(array(
            'keywords' => $result['keywords']
        ));
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_alenseo_enhanced_generate_keywords', 'alenseo_enhanced_generate_keywords');

/**
 * AJAX-Handler für das Speichern des ausgewählten Fokus-Keywords
 */
function alenseo_save_focus_keyword() {
    // Sicherheitsüberprüfung
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Berechtigungen prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Parameter abrufen
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    
    if (!$post_id) {
        wp_send_json_error(array('message' => __('Ungültige Post-ID.', 'alenseo')));
        return;
    }
    
    // Auch leere Keywords erlauben (zum Zurücksetzen)
    update_post_meta($post_id, '_alenseo_keyword', $keyword);
    
    // WPBakery (falls vorhanden)
    update_post_meta($post_id, 'vc_seo_keyword', $keyword);
    
    // SEO-Score zurücksetzen
    delete_post_meta($post_id, '_alenseo_seo_score');
    delete_post_meta($post_id, '_alenseo_seo_status');
    
    wp_send_json_success(array(
        'message' => __('Fokus-Keyword gespeichert.', 'alenseo')
    ));
}
add_action('wp_ajax_alenseo_save_focus_keyword', 'alenseo_save_focus_keyword');
