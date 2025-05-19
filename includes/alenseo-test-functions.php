<?php
/**
 * Test-Funktionen für Alenseo SEO
 *
 * Diese Datei enthält Test- und Debugging-Funktionen
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
 * Test-Handler für die Batch-Optimierung
 */
function alenseo_test_batch_optimize() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unzureichende Berechtigungen.', 'alenseo')));
        return;
    }
    
    // Optimizer-Klasse laden
    if (!class_exists('Alenseo_Content_Optimizer')) {
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-content-optimizer.php';
    }
    
    // Post-IDs abrufen (z.B. die neuesten 3 Beiträge)
    $posts = get_posts(array(
        'post_type' => array('post', 'page'),
        'posts_per_page' => 3,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    if (empty($posts)) {
        wp_send_json_error(array('message' => __('Keine Beiträge für den Test gefunden.', 'alenseo')));
        return;
    }
    
    $post_ids = array_map(function($post) {
        return $post->ID;
    }, $posts);
    
    // Log erstellen
    $log = array();
    $log[] = 'Test gestartet: ' . date('Y-m-d H:i:s');
    $log[] = 'Ausgewählte Post-IDs: ' . implode(', ', $post_ids);
    
    try {
        // Optimizer-Instanz erstellen
        $optimizer = new Alenseo_Content_Optimizer();
        
        // Test-Optionen
        $options = array(
            'optimize_title' => true,
            'optimize_meta_description' => true,
            'optimize_content' => false,
            'auto_save' => false,
            'generate_keywords' => true,
            'tone' => 'professional',
            'level' => 'moderate'
        );
        
        $log[] = 'Optimizer-Instanz erstellt, Optionen gesetzt';
        
        // Batch-Optimierung testen
        $results = $optimizer->batch_optimize($post_ids, $options);
        
        $log[] = 'Batch-Optimierung durchgeführt, Ergebnisse:';
        
        // Ergebnisse protokollieren
        foreach ($results as $post_id => $result) {
            $log[] = "Post ID: $post_id - Status: {$result['status']}";
            
            if (isset($result['keyword_generated'])) {
                $log[] = " - Keyword generiert: {$result['keyword']}";
            }
            
            if (isset($result['optimizations']['title'])) {
                $log[] = " - Titel: " . (
                    $result['optimizations']['title']['status'] === 'success' 
                    ? 'Optimiert' 
                    : 'Fehler: ' . $result['optimizations']['title']['message']
                );
            }
            
            if (isset($result['optimizations']['meta_description'])) {
                $log[] = " - Meta: " . (
                    $result['optimizations']['meta_description']['status'] === 'success' 
                    ? 'Optimiert' 
                    : 'Fehler: ' . $result['optimizations']['meta_description']['message']
                );
            }
        }
        
        // Ergebnis zurückgeben
        wp_send_json_success(array(
            'message' => __('Test erfolgreich durchgeführt.', 'alenseo'),
            'log' => $log,
            'results' => $results
        ));
        
    } catch (Exception $e) {
        $log[] = 'Fehler: ' . $e->getMessage();
        
        wp_send_json_error(array(
            'message' => __('Fehler beim Test:', 'alenseo') . ' ' . $e->getMessage(),
            'log' => $log
        ));
    }
}
add_action('wp_ajax_alenseo_test_batch_optimize', 'alenseo_test_batch_optimize');

/**
 * Performance-Test für Datenbank-Anfragen
 */
function alenseo_test_database_performance() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
        wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')));
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unzureichende Berechtigungen.', 'alenseo')));
        return;
    }
    
    global $wpdb;
    $log = array();
    
    // Start-Zeit
    $start_time = microtime(true);
    
    // Test 1: Metadaten-Abfrage ohne Optimierung
    $test1_start = microtime(true);
    $results1 = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_type, 
               (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_alenseo_seo_score' LIMIT 1) as seo_score,
               (SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = p.ID AND meta_key = '_alenseo_keyword' LIMIT 1) as keyword
        FROM {$wpdb->posts} p
        WHERE p.post_type IN ('post', 'page')
        AND p.post_status = 'publish'
        LIMIT 50
    ");
    $test1_time = microtime(true) - $test1_start;
    $log[] = "Test 1: Nicht-optimierte Abfrage - {$test1_time} Sekunden";
    
    // Test 2: Optimierte Metadaten-Abfrage mit JOIN
    $test2_start = microtime(true);
    $results2 = $wpdb->get_results("
        SELECT p.ID, p.post_title, p.post_type, 
               MAX(CASE WHEN pm1.meta_key = '_alenseo_seo_score' THEN pm1.meta_value END) as seo_score,
               MAX(CASE WHEN pm1.meta_key = '_alenseo_keyword' THEN pm1.meta_value END) as keyword
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key IN ('_alenseo_seo_score', '_alenseo_keyword')
        WHERE p.post_type IN ('post', 'page')
        AND p.post_status = 'publish'
        GROUP BY p.ID
        LIMIT 50
    ");
    $test2_time = microtime(true) - $test2_start;
    $log[] = "Test 2: Optimierte Abfrage mit JOIN - {$test2_time} Sekunden";
    
    // Cache-Test
    if (wp_using_ext_object_cache()) {
        $cache_key = 'alenseo_perf_test_' . md5(serialize($_SERVER['REQUEST_TIME']));
        $test3_start = microtime(true);
        
        // Prüfen, ob Ergebnisse im Cache sind
        $cached_results = wp_cache_get($cache_key, 'alenseo');
        
        if (false === $cached_results) {
            // Ergebnisse nicht im Cache, neu abfragen
            $cached_results = $wpdb->get_results("
                SELECT p.ID, p.post_title, p.post_type, 
                       MAX(CASE WHEN pm1.meta_key = '_alenseo_seo_score' THEN pm1.meta_value END) as seo_score,
                       MAX(CASE WHEN pm1.meta_key = '_alenseo_keyword' THEN pm1.meta_value END) as keyword
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key IN ('_alenseo_seo_score', '_alenseo_keyword')
                WHERE p.post_type IN ('post', 'page')
                AND p.post_status = 'publish'
                GROUP BY p.ID
                LIMIT 50
            ");
            
            // Im Cache speichern (5 Minuten)
            wp_cache_set($cache_key, $cached_results, 'alenseo', 300);
            $log[] = "Test 3: Cache miss - Ergebnisse neu geladen und im Cache gespeichert";
        } else {
            $log[] = "Test 3: Cache hit - Ergebnisse aus dem Cache geladen";
        }
        
        $test3_time = microtime(true) - $test3_start;
        $log[] = "Test 3: Cache-Verwendung - {$test3_time} Sekunden";
    }
    
    // Gesamtzeit
    $total_time = microtime(true) - $start_time;
    $log[] = "Gesamtzeit: {$total_time} Sekunden";
    
    // Ergebnis zurückgeben
    wp_send_json_success(array(
        'message' => __('Performance-Test abgeschlossen.', 'alenseo'),
        'log' => $log,
        'times' => array(
            'test1' => $test1_time,
            'test2' => $test2_time,
            'test3' => isset($test3_time) ? $test3_time : null,
            'total' => $total_time
        )
    ));
}
add_action('wp_ajax_alenseo_test_database_performance', 'alenseo_test_database_performance');
