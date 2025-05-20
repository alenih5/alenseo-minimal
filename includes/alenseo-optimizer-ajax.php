<?php
/**
 * AJAX-Handler für Optimierungsfunktionen in Alenseo SEO
 *
 * Diese Datei enthält AJAX-Handler für die Optimizer-Seite
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
 * AJAX-Handler für die Optimierung eines einzelnen Inhalts
 */
function alenseo_optimize_single_content_ajax() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Unzureichende Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Erforderliche Parameter prüfen
    if (!isset($_POST['post_id'])) {
        wp_send_json_error(array('message' => __('Fehlende Post-ID.', 'alenseo')));
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    $optimize_type = isset($_POST['optimize_type']) ? sanitize_text_field($_POST['optimize_type']) : 'all';
    
    try {
        // Content Optimizer erstellen
        if (!class_exists('Alenseo_Content_Optimizer')) {
            wp_send_json_error(array('message' => __('Content Optimizer-Klasse nicht gefunden.', 'alenseo')));
            return;
        }
        
        $optimizer = new Alenseo_Content_Optimizer();
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(array('message' => __('Inhalt nicht gefunden.', 'alenseo')));
            return;
        }
        
        // Keywords holen
        $keywords = get_post_meta($post_id, 'alenseo_focus_keywords', true);
        
        if (empty($keywords)) {
            // Wenn keine Keywords vorhanden sind, automatisch generieren
            $generated_keywords = $optimizer->generate_keywords($post_id);
            
            if (!is_wp_error($generated_keywords)) {
                $keywords = implode(', ', array_slice($generated_keywords, 0, 3));
                update_post_meta($post_id, 'alenseo_focus_keywords', $keywords);
            } else {
                // Fallback: Titel als Keyword verwenden
                $keywords = $post->post_title;
                update_post_meta($post_id, 'alenseo_focus_keywords', $keywords);
            }
        }
        
        $result = array();
        $updates = array();
        
        // Optimierung basierend auf Typ durchführen
        switch ($optimize_type) {
            case 'title':
                $new_title = $optimizer->optimize_title($post_id, $keywords);
                if (!is_wp_error($new_title)) {
                    update_post_meta($post_id, '_alenseo_seo_title', $new_title);
                    $updates[] = 'title';
                }
                break;
                
            case 'meta_description':
                $new_desc = $optimizer->optimize_meta_description($post_id, $keywords);
                if (!is_wp_error($new_desc)) {
                    update_post_meta($post_id, '_alenseo_seo_description', $new_desc);
                    $updates[] = 'meta_description';
                }
                break;
                
            case 'content':
                // Für Inhaltsoptimierung nur Empfehlungen zurückgeben
                $recommendations = $optimizer->get_content_recommendations($post_id, $keywords);
                if (!is_wp_error($recommendations)) {
                    $result['recommendations'] = $recommendations;
                    $updates[] = 'recommendations';
                }
                break;
                
            case 'all':
            default:
                // Titel optimieren
                $new_title = $optimizer->optimize_title($post_id, $keywords);
                if (!is_wp_error($new_title)) {
                    update_post_meta($post_id, '_alenseo_seo_title', $new_title);
                    $updates[] = 'title';
                }
                
                // Meta-Description optimieren
                $new_desc = $optimizer->optimize_meta_description($post_id, $keywords);
                if (!is_wp_error($new_desc)) {
                    update_post_meta($post_id, '_alenseo_seo_description', $new_desc);
                    $updates[] = 'meta_description';
                }
                
                // Inhaltsempfehlungen holen
                $recommendations = $optimizer->get_content_recommendations($post_id, $keywords);
                if (!is_wp_error($recommendations)) {
                    $result['recommendations'] = $recommendations;
                    $updates[] = 'recommendations';
                }
                break;
        }
        
        // Post erneut analysieren, um den SEO-Score zu aktualisieren
        if (!class_exists('Alenseo_Minimal_Analysis')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-minimal-analysis.php';
        }
        
        $analyzer = new Alenseo_Minimal_Analysis();
        $analyzer->analyze_post($post_id, $keywords);
        
        // Analyseergebnisse abrufen
        $result['score'] = get_post_meta($post_id, '_alenseo_seo_score', true);
        $result['status'] = get_post_meta($post_id, '_alenseo_seo_status', true);
        $result['updated'] = $updates;
        $result['message'] = __('Optimierung erfolgreich durchgeführt.', 'alenseo');
        
        wp_send_json_success($result);
        
    } catch (Exception $e) {
        // Fehlerbehandlung
        error_log('Alenseo SEO - AJAX-Fehler: ' . $e->getMessage());
        wp_send_json_error(array('message' => __('Fehler bei der Optimierung: ', 'alenseo') . $e->getMessage()));
    }
}
add_action('wp_ajax_alenseo_optimize_single_content', 'alenseo_optimize_single_content_ajax');

/**
 * AJAX-Handler für die Keyword-Generierung
 */
function alenseo_generate_keywords_ajax() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Unzureichende Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Erforderliche Parameter prüfen
    if (!isset($_POST['post_id'])) {
        wp_send_json_error(array('message' => __('Fehlende Post-ID.', 'alenseo')));
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    
    try {
        // Content Optimizer erstellen
        if (!class_exists('Alenseo_Content_Optimizer')) {
            wp_send_json_error(array('message' => __('Content Optimizer-Klasse nicht gefunden.', 'alenseo')));
            return;
        }
        
        $optimizer = new Alenseo_Content_Optimizer();
        
        // Keywords generieren
        $keywords = $optimizer->generate_keywords($post_id);
        
        if (is_wp_error($keywords)) {
            wp_send_json_error(array('message' => $keywords->get_error_message()));
            return;
        }
        
        // Erfolgreich
        wp_send_json_success(array(
            'message' => __('Keywords erfolgreich generiert.', 'alenseo'),
            'keywords' => $keywords
        ));
        
    } catch (Exception $e) {
        // Fehlerbehandlung
        error_log('Alenseo SEO - AJAX-Fehler: ' . $e->getMessage());
        wp_send_json_error(array('message' => __('Fehler bei der Keyword-Generierung: ', 'alenseo') . $e->getMessage()));
    }
}
add_action('wp_ajax_alenseo_generate_keywords', 'alenseo_generate_keywords_ajax');
