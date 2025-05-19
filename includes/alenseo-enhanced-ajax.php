<?php
/**
 * Erweiterte AJAX-Handler für Alenseo SEO
 *
 * Diese Datei enthält alle AJAX-Handler für die erweiterten Funktionen
 * wie Content-Optimierung und Detailansicht
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
 * AJAX-Handler für erweiterte Optimierungsvorschläge von Claude
 */
function alenseo_claude_get_enhanced_optimization_suggestions() {
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
    $optimize_type = isset($_POST['optimize_type']) ? sanitize_text_field($_POST['optimize_type']) : '';
    
    // Optimierungseinstellungen
    $options = array(
        'optimize_title' => isset($_POST['optimize_title']) ? (bool)$_POST['optimize_title'] : false,
        'optimize_meta_description' => isset($_POST['optimize_meta_description']) ? (bool)$_POST['optimize_meta_description'] : false,
        'optimize_content' => isset($_POST['optimize_content']) ? (bool)$_POST['optimize_content'] : false,
        'tone' => isset($_POST['tone']) ? sanitize_text_field($_POST['tone']) : 'professional',
        'level' => isset($_POST['level']) ? sanitize_text_field($_POST['level']) : 'moderate'
    );
    
    try {
        // Optimizer-Instanz erstellen
        if (!class_exists('Alenseo_Content_Optimizer')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-content-optimizer.php';
        }
        
        $optimizer = new Alenseo_Content_Optimizer();
        
        // Einzeloptimierung oder Massenoptimierung
        $suggestions = array();
        
        if (!empty($optimize_type)) {
            // Einzelne Optimierung (Titel, Meta oder Inhalt)
            switch ($optimize_type) {
                case 'title':
                    $result = $optimizer->optimize_title($post_id, $keyword, $options);
                    if (!is_wp_error($result)) {
                        $suggestions['title'] = $result;
                    } else {
                        wp_send_json_error(array('message' => $result->get_error_message()));
                        return;
                    }
                    break;
                    
                case 'meta_description':
                    $result = $optimizer->optimize_meta_description($post_id, $keyword, $options);
                    if (!is_wp_error($result)) {
                        $suggestions['meta_description'] = $result;
                    } else {
                        wp_send_json_error(array('message' => $result->get_error_message()));
                        return;
                    }
                    break;
                    
                case 'content':
                    $result = $optimizer->generate_content_suggestions($post_id, $keyword, $options);
                    if (!is_wp_error($result)) {
                        $suggestions['content'] = $result;
                    } else {
                        wp_send_json_error(array('message' => $result->get_error_message()));
                        return;
                    }
                    break;
                
                default:
                    wp_send_json_error(array('message' => 'Ungültiger Optimierungstyp.'));
                    return;
            }
        } else {
            // Massenoptimierung (alle ausgewählten Elemente)
            $result = $optimizer->generate_optimization_suggestions($post_id, $keyword, $options);
            if (!empty($result)) {
                $suggestions = $result;
            } else {
                wp_send_json_error(array('message' => 'Keine Optimierungsvorschläge generiert.'));
                return;
            }
        }
        
        // Erfolgreich
        wp_send_json_success(array(
            'message' => 'Optimierungsvorschläge erfolgreich generiert.',
            'suggestions' => $suggestions
        ));
        
    } catch (Exception $e) {
        // Fehlerbehandlung
        error_log('Alenseo SEO - AJAX-Fehler: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Fehler bei der Generierung von Optimierungsvorschlägen: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_alenseo_claude_get_enhanced_optimization_suggestions', 'alenseo_claude_get_enhanced_optimization_suggestions');

/**
 * AJAX-Handler für erweiterte Keyword-Generierung von Claude
 */
function alenseo_claude_generate_enhanced_keywords() {
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
        // Claude API-Instanz erstellen
        if (!class_exists('Alenseo_Claude_API')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
        }
        
        $claude_api = new Alenseo_Claude_API();
        
        // Post-Daten abrufen
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => 'Beitrag nicht gefunden.'));
            return;
        }
        
        // Inhalt extrahieren
        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        
        // Maximal 2000 Zeichen des Inhalts verwenden
        if (mb_strlen($content) > 2000) {
            $content = mb_substr($content, 0, 1997) . '...';
        }
        
        // Prompt für Keyword-Generierung erstellen
        $prompt = "Du bist ein SEO-Experte mit jahrelanger Erfahrung in der Keyword-Recherche. Deine Aufgabe ist es, relevante Keywords für eine Webseite vorzuschlagen.

Der Titel der Webseite lautet: \"{$title}\".

Hier ist ein Auszug aus dem Inhalt der Seite:
\"{$content}\"

Bitte schlage 5 Fokus-Keywords vor, die:
1. Relevant für den Inhalt der Seite sind
2. Ein gutes Suchvolumen haben könnten
3. Eine realistische Chance auf Rankings bieten (nicht zu wettbewerbsintensiv)
4. Eine klare Nutzerintention widerspiegeln

Formatiere deine Antwort als eine einfache, durch Kommas getrennte Liste der 5 Keywords, ohne Nummerierung oder zusätzliche Erklärungen.";

        // API-Anfrage
        $response = $claude_api->generate_text($prompt);
        
        // Fehler prüfen
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        // Keywords extrahieren
        $keywords = extract_keywords_from_response($response);
        
        // Erfolgreich
        wp_send_json_success(array(
            'message' => 'Keywords erfolgreich generiert.',
            'keywords' => $keywords
        ));
        
    } catch (Exception $e) {
        // Fehlerbehandlung
        error_log('Alenseo SEO - AJAX-Fehler: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Fehler bei der Generierung von Keywords: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_alenseo_claude_generate_enhanced_keywords', 'alenseo_claude_generate_enhanced_keywords');

/**
 * AJAX-Handler zum Speichern eines Keywords
 */
function alenseo_save_keyword() {
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
add_action('wp_ajax_alenseo_save_keyword', 'alenseo_save_keyword');

/**
 * AJAX-Handler zum Anwenden eines Optimierungsvorschlags
 */
function alenseo_apply_suggestion() {
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
    if (!isset($_POST['post_id']) || !isset($_POST['type']) || !isset($_POST['content'])) {
        wp_send_json_error(array('message' => 'Fehlende Parameter.'));
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    $type = sanitize_text_field($_POST['type']);
    $content = sanitize_textarea_field($_POST['content']);
    
    try {
        // Content Optimizer erstellen
        if (!class_exists('Alenseo_Content_Optimizer')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-content-optimizer.php';
        }
        
        $optimizer = new Alenseo_Content_Optimizer();
        
        // Vorschlag anwenden
        $result = $optimizer->apply_suggestion($post_id, $type, $content);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        // Nach dem Anwenden eine neue Analyse durchführen
        if (class_exists('Alenseo_Minimal_Analysis')) {
            $analyzer = new Alenseo_Minimal_Analysis();
            $analyzer->analyze_post($post_id);
        }
        
        // Erfolgreich
        wp_send_json_success(array('message' => 'Optimierungsvorschlag erfolgreich angewendet.'));
        
    } catch (Exception $e) {
        // Fehlerbehandlung
        error_log('Alenseo SEO - AJAX-Fehler: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Fehler beim Anwenden des Optimierungsvorschlags: ' . $e->getMessage()));
    }
}
add_action('wp_ajax_alenseo_apply_suggestion', 'alenseo_apply_suggestion');

/**
 * AJAX-Handler für die Durchführung einer Analyse
 */
function alenseo_analyze_post() {
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
add_action('wp_ajax_alenseo_analyze_post', 'alenseo_analyze_post');

/**
 * Helper-Funktion zum Extrahieren von Keywords aus der API-Antwort
 *
 * @param string $response Die API-Antwort
 * @return array Die extrahierten Keywords
 */
function extract_keywords_from_response($response) {
    // Bereinigungen durchführen
    $response = trim($response);
    
    // Anführungszeichen entfernen, falls vorhanden
    $response = trim($response, '"\'');
    
    // Keywords aufteilen (durch Komma oder Zeilenumbruch getrennt)
    $keywords = preg_split('/[,\n]+/', $response, -1, PREG_SPLIT_NO_EMPTY);
    
    // Bereinigen und Duplikate entfernen
    $cleaned_keywords = array();
    foreach ($keywords as $keyword) {
        $keyword = trim($keyword);
        $keyword = trim($keyword, '"\'');
        
        if (!empty($keyword) && !in_array($keyword, $cleaned_keywords)) {
            $cleaned_keywords[] = $keyword;
        }
    }
    
    return $cleaned_keywords;
}

/**
 * AJAX-Handler für Massenoptimierung von Inhalten
 */
function alenseo_bulk_optimize_content() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unzureichende Berechtigungen für Massenoptimierung.', 'alenseo')));
        return;
    }
    
    // Parameter prüfen
    $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
    $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 5;
    $batch_index = isset($_POST['batch_index']) ? intval($_POST['batch_index']) : 0;
    $optimize_settings = isset($_POST['settings']) ? $_POST['settings'] : array();
    
    // Standardeinstellungen festlegen, falls nicht angegeben
    $optimize_settings = wp_parse_args($optimize_settings, array(
        'optimize_title' => false,
        'optimize_meta_description' => false,
        'optimize_content' => false,
        'tone' => 'professional',
        'level' => 'moderate'
    ));
    
    // Wenn keine Post-IDs angegeben wurden
    if (empty($post_ids)) {
        wp_send_json_error(array('message' => __('Keine Beiträge zur Optimierung ausgewählt.', 'alenseo')));
        return;
    }
    
    // Aktuellen Batch berechnen
    $current_batch = array_slice($post_ids, $batch_index * $batch_size, $batch_size);
    
    // Wenn kein Batch mehr übrig ist
    if (empty($current_batch)) {
        wp_send_json_success(array(
            'message' => __('Massenoptimierung abgeschlossen.', 'alenseo'),
            'completed' => true,
            'stats' => array(
                'total' => count($post_ids),
                'processed' => count($post_ids)
            )
        ));
        return;
    }
    
    $results = array();
    $success_count = 0;
    $error_count = 0;
    
    // Optimizer-Klasse laden
    if (!class_exists('Alenseo_Content_Optimizer')) {
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-content-optimizer.php';
    }
    
    $optimizer = new Alenseo_Content_Optimizer();
    
    // Jeden Post im aktuellen Batch verarbeiten
    foreach ($current_batch as $post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            $results[$post_id] = array(
                'status' => 'error',
                'message' => __('Beitrag nicht gefunden.', 'alenseo')
            );
            $error_count++;
            continue;
        }
        
        // Keyword abrufen oder eines generieren
        $keyword = get_post_meta($post_id, '_alenseo_keyword', true);
        
        if (empty($keyword)) {
            // Automatisch ein Keyword generieren
            try {
                if (class_exists('Alenseo_Claude_API')) {
                    $claude_api = new Alenseo_Claude_API();
                    $title = $post->post_title;
                    $content = wp_strip_all_tags($post->post_content);
                    
                    if (mb_strlen($content) > 2000) {
                        $content = mb_substr($content, 0, 1997) . '...';
                    }
                    
                    // Einfachen Prompt erstellen
                    $prompt = "Extrahiere das wichtigste Keyword aus diesem Text. Titel: \"{$title}\". Inhalt: \"{$content}\". Antworte nur mit dem Keyword ohne weitere Erklärung.";
                    
                    // Keyword generieren
                    $generated_keyword = $claude_api->generate_text($prompt, array(
                        'max_tokens' => 100,
                        'temperature' => 0.1
                    ));
                    
                    if (!is_wp_error($generated_keyword)) {
                        $keyword = trim($generated_keyword);
                        update_post_meta($post_id, '_alenseo_keyword', $keyword);
                    } else {
                        $results[$post_id] = array(
                            'status' => 'error',
                            'message' => __('Fehler bei der Keyword-Generierung:', 'alenseo') . ' ' . $generated_keyword->get_error_message()
                        );
                        $error_count++;
                        continue;
                    }
                } else {
                    $results[$post_id] = array(
                        'status' => 'error',
                        'message' => __('Claude API nicht verfügbar für die Keyword-Generierung.', 'alenseo')
                    );
                    $error_count++;
                    continue;
                }
            } catch (Exception $e) {
                $results[$post_id] = array(
                    'status' => 'error',
                    'message' => __('Fehler bei der Keyword-Generierung:', 'alenseo') . ' ' . $e->getMessage()
                );
                $error_count++;
                continue;
            }
        }
        
        // Optimierungen durchführen
        $optimizations = array();
        
        try {
            // Titel optimieren
            if ($optimize_settings['optimize_title']) {
                $title_result = $optimizer->optimize_title($post_id, $keyword, $optimize_settings);
                
                if (!is_wp_error($title_result)) {
                    $optimizations['title'] = array(
                        'status' => 'success',
                        'original' => $post->post_title,
                        'optimized' => $title_result
                    );
                } else {
                    $optimizations['title'] = array(
                        'status' => 'error',
                        'message' => $title_result->get_error_message()
                    );
                }
            }
            
            // Meta-Description optimieren
            if ($optimize_settings['optimize_meta_description']) {
                $meta_result = $optimizer->optimize_meta_description($post_id, $keyword, $optimize_settings);
                
                if (!is_wp_error($meta_result)) {
                    $optimizations['meta_description'] = array(
                        'status' => 'success',
                        'original' => get_post_meta($post_id, '_alenseo_meta_description', true),
                        'optimized' => $meta_result
                    );
                } else {
                    $optimizations['meta_description'] = array(
                        'status' => 'error',
                        'message' => $meta_result->get_error_message()
                    );
                }
            }
            
            // Inhalt optimieren
            if ($optimize_settings['optimize_content']) {
                $content_result = $optimizer->generate_content_suggestions($post_id, $keyword, $optimize_settings);
                
                if (!is_wp_error($content_result)) {
                    $optimizations['content'] = array(
                        'status' => 'success',
                        'suggestions' => $content_result
                    );
                } else {
                    $optimizations['content'] = array(
                        'status' => 'error',
                        'message' => $content_result->get_error_message()
                    );
                }
            }
            
            // Analyse durchführen und Score aktualisieren
            if (class_exists('Alenseo_Minimal_Analysis')) {
                $analyzer = new Alenseo_Minimal_Analysis();
                $analyzer->analyze_post($post_id);
            }
            
            $success_count++;
            $results[$post_id] = array(
                'status' => 'success',
                'title' => $post->post_title,
                'keyword' => $keyword,
                'optimizations' => $optimizations
            );
            
        } catch (Exception $e) {
            $error_count++;
            $results[$post_id] = array(
                'status' => 'error',
                'message' => __('Fehler bei der Optimierung:', 'alenseo') . ' ' . $e->getMessage()
            );
        }
    }
    
    // Nächsten Batch berechnen
    $next_batch_index = $batch_index + 1;
    $total_batches = ceil(count($post_ids) / $batch_size);
    $is_complete = $next_batch_index >= $total_batches;
    
    // Ergebnis zurückgeben
    wp_send_json_success(array(
        'message' => sprintf(
            __('Batch %d von %d verarbeitet.', 'alenseo'),
            $batch_index + 1,
            $total_batches
        ),
        'results' => $results,
        'stats' => array(
            'success' => $success_count,
            'error' => $error_count,
            'total' => count($post_ids),
            'processed' => min(($next_batch_index * $batch_size), count($post_ids)),
            'batch' => $batch_index + 1,
            'batches' => $total_batches
        ),
        'next_batch' => $next_batch_index,
        'completed' => $is_complete
    ));
}
add_action('wp_ajax_alenseo_bulk_optimize_content', 'alenseo_bulk_optimize_content');
