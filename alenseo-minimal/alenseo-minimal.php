<?php
/**
 * Minimal Analysis-Klasse für Alenseo SEO
 *
 * Diese Klasse führt die grundlegende SEO-Analyse durch
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
 * Die Minimal Analysis-Klasse
 */
class Alenseo_Minimal_Analysis {
    
    /**
     * Initialisierung der Klasse
     */
    public function __construct() {
        // Keine besonderen Initialisierungen erforderlich
    }
    
    /**
     * Post analysieren
     * 
     * @param int $post_id Die Post-ID
     * @return bool|WP_Error true bei Erfolg, sonst ein Fehler
     */
    public function analyze_post($post_id) {
        // Post-Daten abrufen
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Beitrag nicht gefunden.');
        }
        
        // Keyword abrufen
        $keyword = get_post_meta($post_id, '_alenseo_keyword', true);
        
        // Detaillierte Analyse durchführen
        $title_result = $this->analyze_title($post, $keyword);
        $content_result = $this->analyze_content($post, $keyword);
        $url_result = $this->analyze_url($post, $keyword);
        $meta_description_result = $this->analyze_meta_description($post_id, $keyword);
        
        // Gesamtscore berechnen
        $total_score = 0;
        $factor_count = 0;
        
        if ($title_result['score'] > 0) {
            $total_score += $title_result['score'];
            $factor_count++;
        }
        
        if ($content_result['score'] > 0) {
            $total_score += $content_result['score'];
            $factor_count++;
        }
        
        if ($url_result['score'] > 0) {
            $total_score += $url_result['score'];
            $factor_count++;
        }
        
        if ($meta_description_result['score'] > 0) {
            $total_score += $meta_description_result['score'];
            $factor_count++;
        }
        
        // Durchschnittlichen Score berechnen
        $average_score = ($factor_count > 0) ? round($total_score / $factor_count) : 0;
        
        // Status basierend auf Score bestimmen
        $status = 'unknown';
        if ($average_score >= 80) {
            $status = 'good';
        } elseif ($average_score >= 50) {
            $status = 'ok';
        } elseif ($average_score > 0) {
            $status = 'poor';
        }
        
        // Ergebnisse speichern
        update_post_meta($post_id, '_alenseo_seo_score', $average_score);
        update_post_meta($post_id, '_alenseo_seo_status', $status);
        update_post_meta($post_id, '_alenseo_last_analysis', current_time('mysql'));
        
        // Detaillierte Analyseergebnisse speichern
        update_post_meta($post_id, '_alenseo_title_score', $title_result['score']);
        update_post_meta($post_id, '_alenseo_title_message', $title_result['message']);
        
        update_post_meta($post_id, '_alenseo_content_score', $content_result['score']);
        update_post_meta($post_id, '_alenseo_content_message', $content_result['message']);
        
        update_post_meta($post_id, '_alenseo_url_score', $url_result['score']);
        update_post_meta($post_id, '_alenseo_url_message', $url_result['message']);
        
        update_post_meta($post_id, '_alenseo_meta_description_score', $meta_description_result['score']);
        update_post_meta($post_id, '_alenseo_meta_description_message', $meta_description_result['message']);
        
        return true;
    }
    
    /**
     * Titel analysieren
     * 
     * @param object $post     Das Post-Objekt
     * @param string $keyword  Das Fokus-Keyword
     * @return array Array mit Score und Nachricht
     */
    protected function analyze_title($post, $keyword) {
        $result = array(
            'score' => 0,
            'message' => ''
        );
        
        // Wenn kein Keyword, dann kann der Titel nicht optimiert sein
        if (empty($keyword)) {
            $result['message'] = __('Kein Fokus-Keyword gesetzt. Setze ein Keyword für eine vollständige Analyse.', 'alenseo');
            return $result;
        }
        
        $title = $post->post_title;
        $title_length = mb_strlen($title);
        
        // Liste der Probleme
        $issues = array();
        
        // Titel-Länge prüfen (ideale Länge: 30-60 Zeichen)
        if ($title_length < 30) {
            $issues[] = __('Der Titel ist zu kurz (weniger als 30 Zeichen). Ideale Länge: 30-60 Zeichen.', 'alenseo');
        } elseif ($title_length > 60) {
            $issues[] = __('Der Titel ist zu lang (mehr als 60 Zeichen). Ideale Länge: 30-60 Zeichen.', 'alenseo');
        }
        
        // Keyword im Titel prüfen
        $keyword_in_title = stripos($title, $keyword) !== false;
        if (!$keyword_in_title) {
            $issues[] = __('Das Fokus-Keyword kommt nicht im Titel vor.', 'alenseo');
        }
        
        // Keyword-Position im Titel prüfen (am Anfang ist besser)
        $keyword_position = stripos($title, $keyword);
        if ($keyword_position !== false && $keyword_position > 20) {
            $issues[] = __('Das Fokus-Keyword steht nicht am Anfang des Titels.', 'alenseo');
        }
        
        // Score basierend auf Anzahl der Probleme berechnen
        if (empty($issues)) {
            $result['score'] = 100;
            $result['message'] = __('Der Titel ist optimal für SEO.', 'alenseo');
        } else {
            // Jedes Problem reduziert den Score
            $result['score'] = max(0, 100 - (count($issues) * 25));
            $result['message'] = implode(' ', $issues);
        }
        
        return $result;
    }
    
    /**
     * Inhalt analysieren
     * 
     * @param object $post     Das Post-Objekt
     * @param string $keyword  Das Fokus-Keyword
     * @return array Array mit Score und Nachricht
     */
    protected function analyze_content($post, $keyword) {
        $result = array(
            'score' => 0,
            'message' => ''
        );
        
        // Wenn kein Keyword, dann kann der Inhalt nicht optimiert sein
        if (empty($keyword)) {
            $result['message'] = __('Kein Fokus-Keyword gesetzt. Setze ein Keyword für eine vollständige Analyse.', 'alenseo');
            return $result;
        }
        
        $content = wp_strip_all_tags($post->post_content);
        $word_count = str_word_count($content);
        
        // Liste der Probleme
        $issues = array();
        
        // Wortanzahl prüfen (Minimum: 300 Wörter)
        if ($word_count < 300) {
            $issues[] = __('Der Inhalt hat weniger als 300 Wörter. Für eine gute SEO-Wirkung werden mindestens 300 Wörter empfohlen.', 'alenseo');
        }
        
        // Keyword im Inhalt prüfen
        $keyword_in_content = stripos($content, $keyword) !== false;
        if (!$keyword_in_content) {
            $issues[] = __('Das Fokus-Keyword kommt nicht im Inhalt vor.', 'alenseo');
        } else {
            // Keyword-Dichte berechnen
            $keyword_count = substr_count(strtolower($content), strtolower($keyword));
            $keyword_density = ($keyword_count / $word_count) * 100;
            
            // Ideale Keyword-Dichte: 0,5% - 2,5%
            if ($keyword_density < 0.5) {
                $issues[] = __('Die Keyword-Dichte ist zu niedrig (unter 0,5%). Ideale Dichte: 0,5% - 2,5%.', 'alenseo');
            } elseif ($keyword_density > 2.5) {
                $issues[] = __('Die Keyword-Dichte ist zu hoch (über 2,5%, Keyword-Stuffing). Ideale Dichte: 0,5% - 2,5%.', 'alenseo');
            }
        }
        
        // Überschriften im Inhalt prüfen
        $has_h2 = preg_match('/<h2[^>]*>/i', $post->post_content);
        if (!$has_h2) {
            $issues[] = __('Der Inhalt enthält keine H2-Überschriften. Verwende Überschriften zur besseren Strukturierung.', 'alenseo');
        }
        
        // Keyword in Überschriften prüfen
        $keyword_in_headings = false;
        preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $post->post_content, $headings);
        if (!empty($headings[1])) {
            foreach ($headings[1] as $heading) {
                if (stripos($heading, $keyword) !== false) {
                    $keyword_in_headings = true;
                    break;
                }
            }
        }
        
        if (!$keyword_in_headings) {
            $issues[] = __('Das Fokus-Keyword kommt in keiner Überschrift vor.', 'alenseo');
        }
        
        // Bilder im Inhalt prüfen
        $has_images = preg_match('/<img[^>]*>/i', $post->post_content);
        if (!$has_images) {
            $issues[] = __('Der Inhalt enthält keine Bilder. Bilder verbessern die Nutzerfreundlichkeit und SEO.', 'alenseo');
        }
        
        // Score basierend auf Anzahl der Probleme berechnen
        if (empty($issues)) {
            $result['score'] = 100;
            $result['message'] = __('Der Inhalt ist optimal für SEO.', 'alenseo');
        } else {
            // Jedes Problem reduziert den Score
            $result['score'] = max(0, 100 - (count($issues) * 20));
            $result['message'] = implode(' ', $issues);
        }
        
        return $result;
    }
    
    /**
     * URL analysieren
     * 
     * @param object $post     Das Post-Objekt
     * @param string $keyword  Das Fokus-Keyword
     * @return array Array mit Score und Nachricht
     */
    protected function analyze_url($post, $keyword) {
        $result = array(
            'score' => 0,
            'message' => ''
        );
        
        // Wenn kein Keyword, dann kann die URL nicht optimiert sein
        if (empty($keyword)) {
            $result['message'] = __('Kein Fokus-Keyword gesetzt. Setze ein Keyword für eine vollständige Analyse.', 'alenseo');
            return $result;
        }
        
        $slug = $post->post_name;
        $slug_length = mb_strlen($slug);
        
        // Liste der Probleme
        $issues = array();
        
        // Slug-Länge prüfen (ideale Länge: 3-75 Zeichen)
        if ($slug_length < 3) {
            $issues[] = __('Der Slug ist zu kurz (weniger als 3 Zeichen).', 'alenseo');
        } elseif ($slug_length > 75) {
            $issues[] = __('Der Slug ist zu lang (mehr als 75 Zeichen). Kürze den Slug für bessere Lesbarkeit.', 'alenseo');
        }
        
        // Keyword im Slug prüfen
        $keyword_slug = sanitize_title($keyword);
        $keyword_in_slug = strpos($slug, $keyword_slug) !== false;
        if (!$keyword_in_slug) {
            $issues[] = __('Das Fokus-Keyword kommt nicht im Slug vor.', 'alenseo');
        }
        
        // Score basierend auf Anzahl der Probleme berechnen
        if (empty($issues)) {
            $result['score'] = 100;
            $result['message'] = __('Die URL ist optimal für SEO.', 'alenseo');
        } else {
            // Jedes Problem reduziert den Score
            $result['score'] = max(0, 100 - (count($issues) * 25));
            $result['message'] = implode(' ', $issues);
        }
        
        return $result;
    }
    
    /**
     * Meta-Description analysieren
     * 
     * @param int    $post_id  Die Post-ID
     * @param string $keyword  Das Fokus-Keyword
     * @return array Array mit Score und Nachricht
     */
    protected function analyze_meta_description($post_id, $keyword) {
        $result = array(
            'score' => 0,
            'message' => ''
        );
        
        // Wenn kein Keyword, dann kann die Meta-Description nicht optimiert sein
        if (empty($keyword)) {
            $result['message'] = __('Kein Fokus-Keyword gesetzt. Setze ein Keyword für eine vollständige Analyse.', 'alenseo');
            return $result;
        }
        
        // Meta-Description abrufen
        $meta_description = get_post_meta($post_id, '_alenseo_meta_description', true);
        
        // Wenn keine Meta-Description von Alenseo, dann nach anderen SEO-Plugins suchen
        if (empty($meta_description)) {
            // Yoast SEO
            $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            
            // All in One SEO
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, '_aioseo_description', true);
            }
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, '_aioseop_description', true);
            }
            
            // Rank Math
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, 'rank_math_description', true);
            }
            
            // SEOPress
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, '_seopress_titles_desc', true);
            }
            
            // WPBakery
            if (empty($meta_description)) {
                $meta_description = get_post_meta($post_id, 'vc_description', true);
            }
        }
        
        // Liste der Probleme
        $issues = array();
        
        // Prüfen, ob eine Meta-Description vorhanden ist
        if (empty($meta_description)) {
            $issues[] = __('Keine Meta-Description vorhanden. Erstelle eine Meta-Description für bessere SEO.', 'alenseo');
            $result['score'] = 0;
            $result['message'] = implode(' ', $issues);
            return $result;
        }
        
        $description_length = mb_strlen($meta_description);
        
        // Meta-Description-Länge prüfen (ideale Länge: 120-160 Zeichen)
        if ($description_length < 120) {
            $issues[] = __('Die Meta-Description ist zu kurz (weniger als 120 Zeichen). Ideale Länge: 120-160 Zeichen.', 'alenseo');
        } elseif ($description_length > 160) {
            $issues[] = __('Die Meta-Description ist zu lang (mehr als 160 Zeichen). Ideale Länge: 120-160 Zeichen.', 'alenseo');
        }
        
        // Keyword in Meta-Description prüfen
        $keyword_in_description = stripos($meta_description, $keyword) !== false;
        if (!$keyword_in_description) {
            $issues[] = __('Das Fokus-Keyword kommt nicht in der Meta-Description vor.', 'alenseo');
        }
        
        // Score basierend auf Anzahl der Probleme berechnen
        if (empty($issues)) {
            $result['score'] = 100;
            $result['message'] = __('Die Meta-Description ist optimal für SEO.', 'alenseo');
        } else {
            // Jedes Problem reduziert den Score
            $result['score'] = max(0, 100 - (count($issues) * 25));
            $result['message'] = implode(' ', $issues);
        }
        
        return $result;
    }
}
