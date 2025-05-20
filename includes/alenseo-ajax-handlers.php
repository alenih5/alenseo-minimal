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

/**
 * AJAX-Handler für die Analyse eines Inhalts (für Bulk-Aktionen)
 */
function alenseo_analyze_content_ajax() {
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
        
        // Keywords automatisch erstellen wenn keine vorhanden sind
        $keywords = get_post_meta($post_id, 'alenseo_focus_keywords', true);
        if (empty($keywords)) {
            $post = get_post($post_id);
            $keywords = $post->post_title;
            update_post_meta($post_id, 'alenseo_focus_keywords', $keywords);
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
add_action('wp_ajax_alenseo_analyze_content', 'alenseo_analyze_content_ajax');

/**
 * AJAX-Handler für den Claude API-Schlüssel-Test
 */
function alenseo_test_api_key_ajax() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => 'Sicherheitsüberprüfung fehlgeschlagen.'));
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unzureichende Berechtigungen.'));
        return;
    }
    
    // Erforderliche Parameter prüfen
    if (!isset($_POST['api_key']) || !isset($_POST['model'])) {
        wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    $model = sanitize_text_field($_POST['model']);
    
    try {
        // Claude API-Klasse initialisieren
        if (!class_exists('Alenseo_Claude_API')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
        }
        
        $claude_api = new Alenseo_Claude_API($api_key, $model);
        
        // API-Schlüssel testen
        $test_result = $claude_api->test_api_key();
        
        if (isset($test_result['success']) && $test_result['success']) {
            // Bei Erfolg JSON-Antwort senden
            wp_send_json_success($test_result);
        } else {
            // Bei Fehler entsprechende Nachricht senden
            wp_send_json_error($test_result);
        }
        
    } catch (Exception $e) {
        // Fehlerbehandlung
        error_log('Alenseo SEO - API-Test Fehler: ' . $e->getMessage());
        wp_send_json_error(array(
            'success' => false,
            'message' => 'Fehler beim API-Test: ' . $e->getMessage()
        ));
    }
}
add_action('wp_ajax_alenseo_test_api_key', 'alenseo_test_api_key_ajax');

/**
 * AJAX-Handler für die Bulk-Analyse mehrerer Beiträge
 */
function alenseo_bulk_analyze_ajax() {
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
    if (!isset($_POST['post_ids']) || !is_array($_POST['post_ids'])) {
        wp_send_json_error(array('message' => 'Fehlende oder ungültige Post-IDs.'));
        return;
    }
    
    $post_ids = array_map('intval', $_POST['post_ids']);
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 5;
    $batch_index = isset($_POST['batch_index']) ? intval($_POST['batch_index']) : 0;
    
    // Aktuelle Batch berechnen
    $start = $batch_index * $batch_size;
    $batch_post_ids = array_slice($post_ids, $start, $batch_size);
    
    $processed = 0;
    $successful = 0;
    $failed = 0;
    $results = array();
    
    // Analyzer erstellen
    if (!class_exists('Alenseo_Minimal_Analysis')) {
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-minimal-analysis.php';
    }
    
    $analyzer = new Alenseo_Minimal_Analysis();
    
    // Jeden Beitrag im aktuellen Batch analysieren
    foreach ($batch_post_ids as $post_id) {
        $processed++;
        
        try {
            // Analyse durchführen
            $result = $analyzer->analyze_post($post_id, true); // true = erzwingt Neuanalyse
            
            if (is_wp_error($result)) {
                $failed++;
                $results[$post_id] = array(
                    'success' => false,
                    'message' => $result->get_error_message()
                );
                continue;
            }
            
            // Keywords automatisch erstellen wenn keine vorhanden sind
            $keywords = get_post_meta($post_id, '_alenseo_keyword', true);
            if (empty($keywords)) {
                $post = get_post($post_id);
                $keywords = $post->post_title;
                update_post_meta($post_id, '_alenseo_keyword', $keywords);
                
                // Nach dem Setzen des Keywords erneut analysieren
                $analyzer->analyze_post($post_id, true);
            }
            
            $successful++;
            $results[$post_id] = array(
                'success' => true,
                'score' => get_post_meta($post_id, '_alenseo_seo_score', true),
                'status' => get_post_meta($post_id, '_alenseo_seo_status', true)
            );
            
        } catch (Exception $e) {
            $failed++;
            $results[$post_id] = array(
                'success' => false,
                'message' => $e->getMessage()
            );
            error_log('Alenseo SEO - Bulk-Analyse Fehler für Post ID ' . $post_id . ': ' . $e->getMessage());
        }
    }
    
    // Prüfen, ob es noch mehr zu verarbeiten gibt
    $total_processed = $start + $processed;
    $completed = $total_processed >= count($post_ids);
    $next_batch = $completed ? 0 : $batch_index + 1;
    
    // Ergebnis zurückgeben
    wp_send_json_success(array(
        'message' => sprintf(
            'Batch %d verarbeitet: %d erfolgreich, %d fehlgeschlagen',
            $batch_index + 1,
            $successful,
            $failed
        ),
        'stats' => array(
            'processed' => $total_processed,
            'total' => count($post_ids),
            'successful' => $successful,
            'failed' => $failed
        ),
        'results' => $results,
        'completed' => $completed,
        'next_batch' => $next_batch
    ));
}
add_action('wp_ajax_alenseo_bulk_analyze', 'alenseo_bulk_analyze_ajax');
