<?php
/**
 * Enhanced Analysis-Klasse für Alenseo SEO
 *
 * Diese Klasse erweitert die grundlegende SEO-Analyse mit zusätzlichen Funktionen
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
 * Die Enhanced Analysis-Klasse
 */
class Alenseo_Enhanced_Analysis {
    
    /**
     * Analyse-Cache-Dauer in Sekunden
     * 
     * @var int
     */
    private $cache_expiry = 3600; // 1 Stunde
    
    /**
     * Initialisierung der Klasse
     */
    public function __construct() {
        // Cache-Dauer aus Einstellungen laden, falls vorhanden
        $settings = get_option('alenseo_settings', array());
        if (isset($settings['analysis_cache_expiry'])) {
            $this->cache_expiry = intval($settings['analysis_cache_expiry']);
        }
    }
    
    /**
     * Fortgeschrittene Inhaltsanalyse
     * 
     * @param WP_Post $post   Der Post
     * @param string  $keyword Das Fokus-Keyword
     * @return array Das Analyseergebnis
     */
    public function analyze_content($post, $keyword) {
        $result = array(
            'score' => 0,
            'message' => '',
            'details' => array()
        );
        
        if (empty($keyword)) {
            $result['message'] = __('Kein Fokus-Keyword festgelegt.', 'alenseo');
            return $result;
        }
        
        // HTML-Code für die Analyse extrahieren
        $content = $post->post_content;
        $plain_content = wp_strip_all_tags($content);
        $content_length = mb_strlen($plain_content);
        $word_count = str_word_count($plain_content);
        
        if ($content_length < 300) {
            $result['score'] = 20;
            $result['message'] = __('Der Inhalt ist sehr kurz (weniger als 300 Zeichen). Erweitern Sie den Inhalt für bessere SEO.', 'alenseo');
            $result['details']['content_length'] = array(
                'value' => $content_length,
                'status' => 'poor',
                'message' => __('Inhaltslänge ist zu kurz', 'alenseo')
            );
            return $result;
        }
        
        // Keyword und Variationen für die Analyse vorbereiten
        $keyword_lower = mb_strtolower(trim($keyword));
        $keyword_words = explode(' ', $keyword_lower);
        
        // 1. Exakte Keyword-Übereinstimmung zählen
        $exact_match_count = substr_count(mb_strtolower($plain_content), $keyword_lower);
        
        // 2. Teilübereinstimmungen der Keyword-Bestandteile zählen
        $partial_matches = 0;
        if (count($keyword_words) > 1) {
            foreach ($keyword_words as $word) {
                if (mb_strlen($word) > 3) { // Nur bedeutsame Wörter zählen
                    $partial_matches += substr_count(mb_strtolower($plain_content), $word);
                }
            }
            // Exakte Übereinstimmungen herausrechnen
            $partial_matches -= ($exact_match_count * count($keyword_words));
        }
        
        // 3. Überschriften überprüfen (h1, h2, h3)
        $keyword_in_headings = $this->check_keyword_in_headings($content, $keyword_lower);
        
        // 4. Keyword-Dichte berechnen
        $keyword_density = 0;
        $effective_keyword_count = $exact_match_count + ($partial_matches * 0.3); // Partial Matches gewichtet einbeziehen
        
        if ($content_length > 0) {
            $keyword_density = ($effective_keyword_count * mb_strlen($keyword_lower) / $content_length) * 100;
        }
        
        // 5. Ersten Absatz überprüfen
        $first_paragraph = $this->extract_first_paragraph($content);
        $keyword_in_first_paragraph = (strpos(mb_strtolower($first_paragraph), $keyword_lower) !== false);
        
        // 6. Bilder Alt-Tags überprüfen
        $images_with_alt = $this->check_images_alt_tags($content, $keyword_lower);
        
        // 7. Linkanalyse durchführen
        $link_analysis = $this->analyze_links($content);
        
        // 8. Readability Score berechnen
        $readability_score = $this->calculate_readability_score($plain_content);
        
        // 9. Bewertung basierend auf allen Faktoren
        $detail_scores = array();
        
        // Bewertung der Keyword-Dichte
        if ($exact_match_count === 0) {
            $detail_scores['keyword_density'] = 30;
            $density_message = __('Das Fokus-Keyword kommt im Inhalt nicht vor.', 'alenseo');
        } elseif ($keyword_density < 0.5) {
            $detail_scores['keyword_density'] = 50;
            $density_message = __('Das Fokus-Keyword kommt zu selten im Inhalt vor (weniger als 0,5%).', 'alenseo');
        } elseif ($keyword_density > 2.5) {
            $detail_scores['keyword_density'] = 60;
            $density_message = __('Das Fokus-Keyword kommt zu häufig im Inhalt vor (mehr als 2,5%). Das könnte als Keyword-Stuffing gewertet werden.', 'alenseo');
        } else {
            $detail_scores['keyword_density'] = 100;
            $density_message = __('Die Keyword-Dichte im Inhalt ist optimal.', 'alenseo');
        }
        
        // Bewertung für Überschriften
        if ($keyword_in_headings) {
            $detail_scores['headings'] = 100;
            $headings_message = __('Das Fokus-Keyword wird in Überschriften verwendet. Sehr gut!', 'alenseo');
        } else {
            $detail_scores['headings'] = 40;
            $headings_message = __('Das Fokus-Keyword kommt in keiner Überschrift vor.', 'alenseo');
        }
        
        // Bewertung für ersten Absatz
        if ($keyword_in_first_paragraph) {
            $detail_scores['first_paragraph'] = 100;
            $first_paragraph_message = __('Das Fokus-Keyword erscheint im ersten Absatz. Sehr gut!', 'alenseo');
        } else {
            $detail_scores['first_paragraph'] = 50;
            $first_paragraph_message = __('Das Fokus-Keyword erscheint nicht im ersten Absatz.', 'alenseo');
        }
        
        // Bewertung für Bilder Alt-Tags
        $detail_scores['image_alt_tags'] = $images_with_alt['score'];
        $image_alt_message = $images_with_alt['message'];
        
        // Bewertung für Links
        $detail_scores['links'] = $link_analysis['score'];
        $links_message = $link_analysis['message'];
        
        // Bewertung für Lesbarkeit
        $detail_scores['readability'] = $readability_score['score'];
        $readability_message = $readability_score['message'];
        
        // Bewertung für Inhaltslänge
        if ($word_count > 1500) {
            $detail_scores['content_length'] = 100;
            $length_message = __('Der Inhalt hat eine hervorragende Länge für SEO.', 'alenseo');
        } elseif ($word_count > 900) {
            $detail_scores['content_length'] = 80;
            $length_message = __('Der Inhalt hat eine gute Länge für SEO.', 'alenseo');
        } elseif ($word_count > 600) {
            $detail_scores['content_length'] = 60;
            $length_message = __('Der Inhalt hat eine akzeptable Länge, könnte aber noch erweitert werden.', 'alenseo');
        } elseif ($word_count > 300) {
            $detail_scores['content_length'] = 40;
            $length_message = __('Der Inhalt ist etwas kurz. Es wird empfohlen, mindestens 600 Wörter zu verwenden.', 'alenseo');
        } else {
            $detail_scores['content_length'] = 20;
            $length_message = __('Der Inhalt ist zu kurz für gute SEO-Ergebnisse.', 'alenseo');
        }
        
        // Gesamtscore berechnen mit unterschiedlicher Gewichtung
        $weights = array(
            'keyword_density'   => 1.5,
            'headings'          => 1.0,
            'first_paragraph'   => 1.0,
            'image_alt_tags'    => 0.8,
            'links'             => 0.7,
            'readability'       => 1.0,
            'content_length'    => 1.2
        );
        
        $weighted_score = 0;
        $total_weight = 0;
        
        foreach ($detail_scores as $key => $score) {
            $weight = isset($weights[$key]) ? $weights[$key] : 1.0;
            $weighted_score += $score * $weight;
            $total_weight += $weight;
        }
        
        $result['score'] = round($weighted_score / $total_weight);
        
        // Detaillierte Ergebnisse speichern
        $result['details'] = array(
            'keyword_density' => array(
                'value' => round($keyword_density, 2) . '%',
                'count' => $exact_match_count,
                'score' => $detail_scores['keyword_density'],
                'message' => $density_message
            ),
            'headings' => array(
                'has_keyword' => $keyword_in_headings,
                'score' => $detail_scores['headings'],
                'message' => $headings_message
            ),
            'first_paragraph' => array(
                'has_keyword' => $keyword_in_first_paragraph,
                'score' => $detail_scores['first_paragraph'],
                'message' => $first_paragraph_message
            ),
            'image_alt_tags' => $images_with_alt,
            'links' => $link_analysis,
            'readability' => $readability_score,
            'content_length' => array(
                'words' => $word_count,
                'characters' => $content_length,
                'score' => $detail_scores['content_length'],
                'message' => $length_message
            )
        );
        
        // Zusammenfassung der Meldungen
        $result['message'] = $density_message . ' ' . $headings_message . ' ' . $first_paragraph_message . ' ' . $length_message;
        
        return $result;
    }
    
    /**
     * Prüft, ob das Keyword in Überschriften vorkommt
     * 
     * @param string $content Der HTML-Inhalt
     * @param string $keyword Das Fokus-Keyword (kleingeschrieben)
     * @return bool true wenn das Keyword in einer Überschrift vorkommt
     */
    private function check_keyword_in_headings($content, $keyword) {
        // Alle H1, H2 und H3 Tags extrahieren
        $pattern = '/<h[1-3][^>]*>(.*?)<\/h[1-3]>/si';
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $heading) {
                $heading_text = wp_strip_all_tags($heading);
                if (strpos(mb_strtolower($heading_text), $keyword) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Extrahiert den ersten Absatz aus dem Inhalt
     * 
     * @param string $content Der HTML-Inhalt
     * @return string Der extrahierte erste Absatz
     */
    private function extract_first_paragraph($content) {
        // Einfache Methode für den ersten Absatz
        $pattern = '/<p[^>]*>(.*?)<\/p>/si';
        if (preg_match($pattern, $content, $matches)) {
            return wp_strip_all_tags($matches[1]);
        }
        
        // Fallback: Ersten 200 Zeichen
        return substr(wp_strip_all_tags($content), 0, 200);
    }
    
    /**
     * Prüft Bilder auf Alt-Tags und Keyword-Verwendung
     * 
     * @param string $content Der HTML-Inhalt
     * @param string $keyword Das Fokus-Keyword (kleingeschrieben)
     * @return array Analyse-Ergebnisse für Bilder
     */
    private function check_images_alt_tags($content, $keyword) {
        $result = array(
            'total' => 0,
            'with_alt' => 0,
            'with_keyword' => 0,
            'score' => 0,
            'message' => ''
        );
        
        // Alle IMG-Tags extrahieren
        $pattern = '/<img[^>]*>/i';
        if (preg_match_all($pattern, $content, $matches)) {
            $result['total'] = count($matches[0]);
            
            foreach ($matches[0] as $img_tag) {
                // Alt-Attribut prüfen
                $alt_pattern = '/alt=["\']([^"\']*)["\'/i';
                if (preg_match($alt_pattern, $img_tag, $alt_match)) {
                    $result['with_alt']++;
                    
                    // Keyword im Alt-Text prüfen
                    if (strpos(mb_strtolower($alt_match[1]), $keyword) !== false) {
                        $result['with_keyword']++;
                    }
                }
            }
            
            // Score berechnen
            if ($result['total'] === 0) {
                $result['score'] = 0;
                $result['message'] = __('Keine Bilder im Inhalt gefunden.', 'alenseo');
            } else {
                $alt_percentage = ($result['with_alt'] / $result['total']) * 100;
                
                if ($alt_percentage === 100 && $result['with_keyword'] > 0) {
                    $result['score'] = 100;
                    $result['message'] = __('Alle Bilder haben Alt-Texte und mindestens eines enthält das Fokus-Keyword.', 'alenseo');
                } elseif ($alt_percentage === 100) {
                    $result['score'] = 80;
                    $result['message'] = __('Alle Bilder haben Alt-Texte, aber keiner enthält das Fokus-Keyword.', 'alenseo');
                } elseif ($alt_percentage > 50) {
                    $result['score'] = 60;
                    $result['message'] = __('Mehr als die Hälfte der Bilder haben Alt-Texte.', 'alenseo');
                } else {
                    $result['score'] = 40;
                    $result['message'] = __('Weniger als die Hälfte der Bilder haben Alt-Texte.', 'alenseo');
                }
            }
        } else {
            $result['score'] = 50;
            $result['message'] = __('Keine Bilder im Inhalt gefunden. Bilder mit passenden Alt-Texten verbessern die SEO.', 'alenseo');
        }
        
        return $result;
    }
    
    /**
     * Analysiert Links im Inhalt
     * 
     * @param string $content Der HTML-Inhalt
     * @return array Analyse-Ergebnisse für Links
     */
    private function analyze_links($content) {
        $result = array(
            'internal' => 0,
            'external' => 0,
            'total' => 0,
            'score' => 0,
            'message' => ''
        );
        
        // Alle A-Tags extrahieren
        $pattern = '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i';
        if (preg_match_all($pattern, $content, $matches)) {
            $result['total'] = count($matches[0]);
            $site_url = get_site_url();
            
            foreach ($matches[1] as $link) {
                if (strpos($link, $site_url) === 0 || strpos($link, '/') === 0) {
                    $result['internal']++;
                } else {
                    $result['external']++;
                }
            }
            
            // Score berechnen
            if ($result['total'] === 0) {
                $result['score'] = 40;
                $result['message'] = __('Keine Links im Inhalt gefunden. Links verbessern die SEO.', 'alenseo');
            } else {
                if ($result['internal'] > 0 && $result['external'] > 0) {
                    $result['score'] = 100;
                    $result['message'] = __('Der Inhalt enthält sowohl interne als auch externe Links. Ideal für SEO.', 'alenseo');
                } elseif ($result['internal'] > 0) {
                    $result['score'] = 80;
                    $result['message'] = __('Der Inhalt enthält interne Links, aber keine externen Links.', 'alenseo');
                } elseif ($result['external'] > 0) {
                    $result['score'] = 70;
                    $result['message'] = __('Der Inhalt enthält externe Links, aber keine internen Links.', 'alenseo');
                }
            }
        } else {
            $result['score'] = 40;
            $result['message'] = __('Keine Links im Inhalt gefunden. Links verbessern die SEO.', 'alenseo');
        }
        
        return $result;
    }
    
    /**
     * Berechnet einen einfachen Lesbarkeitsscore
     * 
     * @param string $content Der Text-Inhalt (ohne HTML)
     * @return array Ergebnisse der Lesbarkeitsanalyse
     */
    private function calculate_readability_score($content) {
        $result = array(
            'score' => 0,
            'message' => '',
            'details' => array()
        );
        
        // Durchschnittliche Satzlänge berechnen
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        if ($sentence_count === 0) {
            $result['score'] = 0;
            $result['message'] = __('Keine Sätze erkannt.', 'alenseo');
            return $result;
        }
        
        $word_count = 0;
        $long_sentences = 0;
        
        foreach ($sentences as $sentence) {
            $sentence_words = str_word_count(trim($sentence));
            $word_count += $sentence_words;
            
            if ($sentence_words > 20) {
                $long_sentences++;
            }
        }
        
        $avg_sentence_length = $word_count / $sentence_count;
        $long_sentence_percentage = ($sentence_count > 0) ? ($long_sentences / $sentence_count) * 100 : 0;
        
        $result['details']['avg_sentence_length'] = round($avg_sentence_length, 1);
        $result['details']['long_sentences'] = $long_sentences;
        $result['details']['long_sentence_percentage'] = round($long_sentence_percentage, 1);
        
        // Score basierend auf durchschnittlicher Satzlänge und Prozentsatz langer Sätze
        if ($avg_sentence_length < 12 && $long_sentence_percentage < 10) {
            $result['score'] = 100;
            $result['message'] = __('Der Text hat eine sehr gute Lesbarkeit mit kurzen, klaren Sätzen.', 'alenseo');
        } elseif ($avg_sentence_length < 15 && $long_sentence_percentage < 20) {
            $result['score'] = 80;
            $result['message'] = __('Der Text hat eine gute Lesbarkeit.', 'alenseo');
        } elseif ($avg_sentence_length < 20 && $long_sentence_percentage < 30) {
            $result['score'] = 60;
            $result['message'] = __('Der Text hat eine mittelmäßige Lesbarkeit. Versuchen Sie, einige Sätze zu verkürzen.', 'alenseo');
        } else {
            $result['score'] = 40;
            $result['message'] = __('Der Text hat eine niedrige Lesbarkeit. Viele Sätze sind zu lang.', 'alenseo');
        }
        
        return $result;
    }
    
    /**
     * Führt eine Meta-Tag-Analyse durch
     * 
     * @param int $post_id Die Post-ID
     * @return array Analyse-Ergebnisse für Meta-Tags
     */
    public function analyze_meta_tags($post_id) {
        $result = array(
            'score' => 0,
            'message' => '',
            'details' => array()
        );
        
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
        }
        
        // Open Graph-Tags prüfen
        $og_title = get_post_meta($post_id, '_alenseo_og_title', true);
        $og_description = get_post_meta($post_id, '_alenseo_og_description', true);
        $og_image = get_post_meta($post_id, '_alenseo_og_image', true);
        
        // Twitter Cards prüfen
        $twitter_card = get_post_meta($post_id, '_alenseo_twitter_card', true);
        $twitter_title = get_post_meta($post_id, '_alenseo_twitter_title', true);
        $twitter_description = get_post_meta($post_id, '_alenseo_twitter_description', true);
        $twitter_image = get_post_meta($post_id, '_alenseo_twitter_image', true);
        
        // Detaillierte Ergebnisse speichern
        $result['details'] = array(
            'meta_description' => array(
                'exists' => !empty($meta_description),
                'length' => mb_strlen($meta_description)
            ),
            'open_graph' => array(
                'title' => !empty($og_title),
                'description' => !empty($og_description),
                'image' => !empty($og_image)
            ),
            'twitter_cards' => array(
                'card' => !empty($twitter_card),
                'title' => !empty($twitter_title),
                'description' => !empty($twitter_description),
                'image' => !empty($twitter_image)
            )
        );
        
        // Score berechnen
        $score = 0;
        
        // Meta-Description
        if (!empty($meta_description)) {
            $description_length = mb_strlen($meta_description);
            
            if ($description_length >= 120 && $description_length <= 160) {
                $score += 30;
                $result['details']['meta_description']['status'] = 'good';
                $meta_description_message = __('Die Meta-Description hat eine optimale Länge.', 'alenseo');
            } elseif ($description_length >= 80 && $description_length < 120) {
                $score += 20;
                $result['details']['meta_description']['status'] = 'ok';
                $meta_description_message = __('Die Meta-Description ist etwas zu kurz.', 'alenseo');
            } elseif ($description_length > 160) {
                $score += 15;
                $result['details']['meta_description']['status'] = 'ok';
                $meta_description_message = __('Die Meta-Description ist zu lang und könnte in den Suchergebnissen abgeschnitten werden.', 'alenseo');
            } else {
                $score += 10;
                $result['details']['meta_description']['status'] = 'poor';
                $meta_description_message = __('Die Meta-Description ist zu kurz.', 'alenseo');
            }
        } else {
            $score += 0;
            $result['details']['meta_description']['status'] = 'missing';
            $meta_description_message = __('Keine Meta-Description vorhanden.', 'alenseo');
        }
        
        // Open Graph
        $og_score = 0;
        if (!empty($og_title)) $og_score += 5;
        if (!empty($og_description)) $og_score += 5;
        if (!empty($og_image)) $og_score += 10;
        
        $score += $og_score;
        
        if ($og_score >= 15) {
            $result['details']['open_graph']['status'] = 'good';
            $og_message = __('Open Graph-Tags sind gut konfiguriert.', 'alenseo');
        } elseif ($og_score >= 10) {
            $result['details']['open_graph']['status'] = 'ok';
            $og_message = __('Open Graph-Tags sind teilweise konfiguriert.', 'alenseo');
        } elseif ($og_score > 0) {
            $result['details']['open_graph']['status'] = 'poor';
            $og_message = __('Open Graph-Tags sind unvollständig konfiguriert.', 'alenseo');
        } else {
            $result['details']['open_graph']['status'] = 'missing';
            $og_message = __('Keine Open Graph-Tags vorhanden.', 'alenseo');
        }
        
        // Twitter Cards
        $twitter_score = 0;
        if (!empty($twitter_card)) $twitter_score += 5;
        if (!empty($twitter_title)) $twitter_score += 5;
        if (!empty($twitter_description)) $twitter_score += 5;
        if (!empty($twitter_image)) $twitter_score += 5;
        
        $score += $twitter_score;
        
        if ($twitter_score >= 15) {
            $result['details']['twitter_cards']['status'] = 'good';
            $twitter_message = __('Twitter Card-Tags sind gut konfiguriert.', 'alenseo');
        } elseif ($twitter_score >= 10) {
            $result['details']['twitter_cards']['status'] = 'ok';
            $twitter_message = __('Twitter Card-Tags sind teilweise konfiguriert.', 'alenseo');
        } elseif ($twitter_score > 0) {
            $result['details']['twitter_cards']['status'] = 'poor';
            $twitter_message = __('Twitter Card-Tags sind unvollständig konfiguriert.', 'alenseo');
        } else {
            $result['details']['twitter_cards']['status'] = 'missing';
            $twitter_message = __('Keine Twitter Card-Tags vorhanden.', 'alenseo');
        }
        
        // Gesamtscore berechnen (Meta-Description hat höhere Gewichtung)
        $result['score'] = min(100, $score * 2);
        $result['message'] = $meta_description_message . ' ' . $og_message . ' ' . $twitter_message;
        
        return $result;
    }
}
