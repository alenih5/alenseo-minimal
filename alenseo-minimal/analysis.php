/**
 * Alenseo_Analysis Klasse für die SEO-Analyse
 * 
 * Eine vereinfachte Version der Analyse-Klasse
 */
class Alenseo_Analysis {
    /**
     * Konstruktor
     */
    public function __construct() {
        // Einstellungen laden
        $this->settings = get_option('alenseo_settings', array());
        
        // AJAX-Hooks registrieren
        add_action('wp_ajax_alenseo_analyze_post', array($this, 'ajax_analyze_post'));
    }
    
    /**
     * AJAX-Handler für die Analyse einer Seite/eines Beitrags
     */
    public function ajax_analyze_post() {
        // Nonce prüfen
        check_ajax_referer('alenseo_ajax_nonce', 'nonce');
        
        // Berechtigung prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Keine Berechtigung.', 'alenseo')));
            return;
        }
        
        // Post-ID prüfen
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Ungültige Post-ID.', 'alenseo')));
            return;
        }
        
        // Post abrufen
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post nicht gefunden.', 'alenseo')));
            return;
        }
        
        // Keyword abrufen
        $keyword = get_post_meta($post_id, '_alenseo_keyword', true);
        if (empty($keyword)) {
            wp_send_json_error(array('message' => __('Bitte geben Sie zuerst ein Fokus-Keyword ein.', 'alenseo')));
            return;
        }
        
        // Analyse durchführen
        $result = $this->analyze_post($post_id, $keyword);
        
        // Erfolg zurückgeben
        wp_send_json_success(array(
            'message' => __('Analyse erfolgreich durchgeführt.', 'alenseo'),
            'result' => $result
        ));
    }
    
    /**
     * Analysiert einen Beitrag auf SEO-Optimierung
     * 
     * @param int $post_id Die Beitrags-ID
     * @param string $keyword Das Fokus-Keyword
     * @return array Die Analyse-Ergebnisse
     */
    public function analyze_post($post_id, $keyword) {
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'score' => 0,
                'status' => 'needs_optimization'
            );
        }
        
        // Inhalt und Metadaten abrufen
        $content = $post->post_content;
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $excerpt = $post->post_excerpt;
        
        // Yoast SEO-Metadaten abrufen, falls vorhanden
        $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true) ?: $title;
        $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true) ?: $excerpt;
        
        // Punktestand initialisieren
        $score = 0;
        $max_score = 100;
        
        // 1. Keyword im Titel
        if (stripos($title, $keyword) !== false) {
            $score += 15;
        }
        
        // 2. Keyword in Meta-Title
        if (stripos($meta_title, $keyword) !== false) {
            $score += 10;
        }
        
        // 3. Keyword in Meta-Description
        if (stripos($meta_description, $keyword) !== false) {
            $score += 10;
        }
        
        // 4. Keyword in URL
        if (stripos($permalink, $keyword) !== false) {
            $score += 5;
        }
        
        // 5. Keyword-Dichte im Inhalt
        $content_clean = strip_tags($content);
        $word_count = str_word_count($content_clean);
        
        if ($word_count > 0) {
            // Mit regulärem Ausdruck alle Vorkommen des Keywords zählen
            $keyword_count = preg_match_all('/\b' . preg_quote($keyword, '/') . '\b/i', $content_clean);
            
            // Keyword-Dichte berechnen (in Prozent)
            $keyword_density = ($keyword_count / $word_count) * 100;
            
            // Ideale Keyword-Dichte: 1-3%
            if ($keyword_density >= 1 && $keyword_density <= 3) {
                $score += 15;
            } elseif ($keyword_density > 0 && $keyword_density < 1) {
                $score += 10;
            } elseif ($keyword_density > 3) {
                $score += 5; // Keyword-Stuffing = weniger Punkte
            }
        }
        
        // 6. Keyword in Überschriften
        if (preg_match('/<h1[^>]*>.*' . preg_quote($keyword, '/') . '.*<\/h1>/i', $content)) {
            $score += 10;
        }
        
        if (preg_match('/<h2[^>]*>.*' . preg_quote($keyword, '/') . '.*<\/h2>/i', $content)) {
            $score += 5;
        }
        
        // 7. Inhaltslänge
        if ($word_count >= 300) {
            $score += 10;
        } elseif ($word_count >= 100) {
            $score += 5;
        }
        
        // 8. Bilder mit Alt-Text, der das Keyword enthält
        if (preg_match('/<img[^>]*alt=["\'][^"\']*' . preg_quote($keyword, '/') . '[^"\']*["\'][^>]*>/i', $content)) {
            $score += 5;
        }
        
        // 9. Ausgehende Links
        if (preg_match('/<a[^>]*href=["\']https?:\/\/[^"\']*["\'][^>]*>/i', $content)) {
            $score += 5;
        }
        
        // 10. Interne Links
        if (preg_match('/<a[^>]*href=["\'][^"\']*' . preg_quote(site_url(), '/') . '[^"\']*["\'][^>]*>/i', $content)) {
            $score += 5;
        }
        
        // Gesamtpunktzahl begrenzen
        $score = min($score, $max_score);
        
        // Status basierend auf Score festlegen
        $status = 'needs_optimization';
        if ($score >= 80) {
            $status = 'optimized';
        } elseif ($score >= 60) {
            $status = 'partially_optimized';
        }
        
        // Analyse-Ergebnisse speichern
        $this->save_analysis_result($post_id, $score, $status);
        
        return array(
            'score' => $score,
            'status' => $status
        );
    }
    
    /**
     * Speichert die Analyse-Ergebnisse in den Postmeta-Daten
     * 
     * @param int $post_id Die Beitrags-ID
     * @param int $score Der SEO-Score
     * @param string $status Der SEO-Status
     */
    private function save_analysis_result($post_id, $score, $status) {
        update_post_meta($post_id, '_alenseo_seo_score', $score);
        update_post_meta($post_id, '_alenseo_seo_status', $status);
        
        // Zeitstempel der letzten Analyse
        update_post_meta($post_id, '_alenseo_last_analysis', current_time('mysql'));
    }
    
    /**
     * Ruft die SEO-Daten für einen Beitrag ab
     * 
     * @param int $post_id Die Beitrags-ID
     * @return array Die SEO-Daten
     */
    public function get_seo_data($post_id) {
        return array(
            'focus_keyword' => get_post_meta($post_id, '_alenseo_keyword', true),
            'seo_score' => get_post_meta($post_id, '_alenseo_seo_score', true) ?: 0,
            'status' => get_post_meta($post_id, '_alenseo_seo_status', true) ?: 'needs_optimization',
            'last_analysis' => get_post_meta($post_id, '_alenseo_last_analysis', true)
        );
    }
    
    /**
     * AJAX-Handler für die Generierung von Keyword-Vorschlägen
     * In der vollständigen Version würde dies die Claude API verwenden
     */
    public function generate_keywords($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }
        
        // In der vereinfachten Version: Statische Beispiel-Keywords zurückgeben
        $title = $post->post_title;
        $content = strip_tags($post->post_content);
        
        // Beispielhafte Schlüsselwörter basierend auf Titel
        $words = explode(' ', $title);
        $keywords = array();
        
        // Einfache Beispielkeywords
        $keywords[] = array(
            'keyword' => $title,
            'score' => 95,
            'type' => 'primary'
        );
        
        if (count($words) >= 2) {
            $keywords[] = array(
                'keyword' => $words[0] . ' ' . $words[1],
                'score' => 90,
                'type' => 'short-tail'
            );
        }
        
        if (count($words) >= 3) {
            $keywords[] = array(
                'keyword' => $words[0] . ' ' . $words[1] . ' ' . $words[2],
                'score' => 85,
                'type' => 'short-tail'
            );
        }
        
        // Lange Keywords
        $keywords[] = array(
            'keyword' => $title . ' Beispiele',
            'score' => 80,
            'type' => 'long-tail'
        );
        
        $keywords[] = array(
            'keyword' => 'Wie funktioniert ' . $title,
            'score' => 75,
            'type' => 'long-tail'
        );
        
        return $keywords;
    }
}
