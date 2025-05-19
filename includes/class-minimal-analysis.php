<?php
/**
 * Einfache Analysis-Klasse für Alenseo SEO
 * Fallback für den Fall, dass die Hauptklasse nicht geladen werden kann
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
 * Die Analysis-Klasse für das Alenseo SEO Plugin
 */
class Alenseo_Minimal_Analysis {

    /**
     * Plugin-Einstellungen
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Die Plugin-Einstellungen.
     */
    private $settings;

    /**
     * Konstruktor
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Fehlerprotokollierung für Debugging
        if (function_exists('alenseo_log')) {
            alenseo_log("Alenseo Minimal Analysis: Konstruktor wird initialisiert");
        }
        
        // Einstellungen laden
        $this->settings = get_option('alenseo_settings', array());
        
        if (function_exists('alenseo_log')) {
            alenseo_log("Alenseo Minimal Analysis: Konstruktor abgeschlossen");
        }
    }

    /**
     * SEO-Daten für einen Beitrag abrufen
     * 
     * @since    1.0.0
     * @param    int    $post_id    ID des Beitrags
     * @return   array   SEO-Daten (Scores, Meldungen, etc.)
     */
    public function get_seo_data($post_id) {
        try {
            // Post-Daten abrufen
            $post = get_post($post_id);
            
            if (!$post) {
                return array(
                    'seo_score' => 0,
                    'focus_keyword' => '',
                    'status' => 'needs_optimization'
                );
            }
            
            // Fokus-Keyword abrufen
            $focus_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
            
            // Wenn kein Fokus-Keyword gesetzt ist, Grunddaten zurückgeben
            if (empty($focus_keyword)) {
                return array(
                    'seo_score' => 0,
                    'focus_keyword' => '',
                    'status' => 'needs_optimization',
                    'title_score' => 0,
                    'title_message' => __('Kein Fokus-Keyword gesetzt.', 'alenseo'),
                    'content_score' => 0,
                    'content_message' => __('Kein Fokus-Keyword gesetzt.', 'alenseo'),
                    'url_score' => 0,
                    'url_message' => __('Kein Fokus-Keyword gesetzt.', 'alenseo'),
                    'meta_description_score' => 0,
                    'meta_description_message' => __('Kein Fokus-Keyword gesetzt.', 'alenseo')
                );
            }
            
            // Titel analysieren
            $title_analysis = $this->analyze_title($post, $focus_keyword);
            
            // Inhalt analysieren
            $content_analysis = $this->analyze_content($post, $focus_keyword);
            
            // URL analysieren
            $url_analysis = $this->analyze_url($post, $focus_keyword);
            
            // Meta-Beschreibung analysieren
            $meta_description_analysis = $this->analyze_meta_description($post_id, $focus_keyword);
            
            // Gesamtscore berechnen (gewichteter Durchschnitt)
            $total_score = ($title_analysis['score'] * 2 + 
                           $content_analysis['score'] * 3 + 
                           $url_analysis['score'] * 1 + 
                           $meta_description_analysis['score'] * 2) / 8;
            
            // Auf ganze Zahl runden
            $total_score = round($total_score);
            
            // Status basierend auf Score
            $status = 'needs_optimization';
            if ($total_score >= 80) {
                $status = 'optimized';
            } elseif ($total_score >= 50) {
                $status = 'partially_optimized';
            }
            
            // SEO-Scores in die Datenbank speichern
            update_post_meta($post_id, '_alenseo_seo_score', $total_score);
            update_post_meta($post_id, '_alenseo_seo_status', $status);
            update_post_meta($post_id, '_alenseo_title_score', $title_analysis['score']);
            update_post_meta($post_id, '_alenseo_title_message', $title_analysis['message']);
            update_post_meta($post_id, '_alenseo_content_score', $content_analysis['score']);
            update_post_meta($post_id, '_alenseo_content_message', $content_analysis['message']);
            update_post_meta($post_id, '_alenseo_url_score', $url_analysis['score']);
            update_post_meta($post_id, '_alenseo_url_message', $url_analysis['message']);
            update_post_meta($post_id, '_alenseo_meta_description_score', $meta_description_analysis['score']);
            update_post_meta($post_id, '_alenseo_meta_description_message', $meta_description_analysis['message']);
            update_post_meta($post_id, '_alenseo_last_analysis', current_time('mysql'));
            
            // SEO-Daten zusammenführen
            $seo_data = array(
                'seo_score' => $total_score,
                'focus_keyword' => $focus_keyword,
                'status' => $status,
                'title_score' => $title_analysis['score'],
                'title_message' => $title_analysis['message'],
                'content_score' => $content_analysis['score'],
                'content_message' => $content_analysis['message'],
                'url_score' => $url_analysis['score'],
                'url_message' => $url_analysis['message'],
                'meta_description_score' => $meta_description_analysis['score'],
                'meta_description_message' => $meta_description_analysis['message']
            );
            
            return $seo_data;
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Minimal Analysis: Fehler bei der Analyse: " . $e->getMessage());
            }
            
            // Standardwerte zurückgeben
            return array(
                'seo_score' => 0,
                'focus_keyword' => '',
                'status' => 'needs_optimization',
                'title_score' => 0,
                'title_message' => __('Fehler bei der Analyse. Bitte versuche es erneut.', 'alenseo'),
                'content_score' => 0,
                'content_message' => __('Fehler bei der Analyse. Bitte versuche es erneut.', 'alenseo'),
                'url_score' => 0,
                'url_message' => __('Fehler bei der Analyse. Bitte versuche es erneut.', 'alenseo'),
                'meta_description_score' => 0,
                'meta_description_message' => __('Fehler bei der Analyse. Bitte versuche es erneut.', 'alenseo')
            );
        }
    }

    /**
     * Einen Beitrag vollständig analysieren
     *
     * @since    1.0.0
     * @param    int       $post_id    ID des Beitrags
     * @return   array     Ergebnis der Analyse
     */
    public function analyze($post_id = null) {
        try {
            if ($post_id) {
                return $this->get_seo_data($post_id);
            } else {
                return array(
                    'seo_score' => 0,
                    'status' => 'needs_optimization',
                    'message' => __('Keine Beitrags-ID angegeben.', 'alenseo')
                );
            }
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Minimal Analysis: Fehler bei der Analyse: " . $e->getMessage());
            }
            
            return array(
                'seo_score' => 0,
                'status' => 'needs_optimization',
                'message' => __('Fehler bei der Analyse. Bitte versuche es erneut.', 'alenseo')
            );
        }
    }

    /**
     * Titel analysieren
     * 
     * @since    1.0.0
     * @param    object    $post           Post-Objekt
     * @param    string    $focus_keyword  Fokus-Keyword
     * @return   array     Analyse-Ergebnis (Score, Meldung)
     */
    private function analyze_title($post, $focus_keyword) {
        try {
            $title = $post->post_title;
            $score = 0;
            $message = '';
            
            // Länge prüfen
            $title_length = strlen($title);
            
            if ($title_length < 10) {
                $score = 20;
                $message = __('Der Titel ist zu kurz. Verwende mindestens 10 Zeichen.', 'alenseo');
            } elseif ($title_length > 70) {
                $score = 40;
                $message = __('Der Titel ist zu lang. Halte ihn unter 70 Zeichen für eine optimale Darstellung in Suchergebnissen.', 'alenseo');
            } else {
                // Gute Länge
                $score += 40;
            }
            
            // Keyword im Titel prüfen
            if (stripos($title, $focus_keyword) !== false) {
                $score += 40;
                
                // Position des Keywords prüfen
                if (stripos($title, $focus_keyword) === 0) {
                    // Keyword am Anfang
                    $score += 20;
                    $message = __('Sehr gut! Der Titel enthält das Fokus-Keyword am Anfang.', 'alenseo');
                } else {
                    $message = __('Gut! Der Titel enthält das Fokus-Keyword.', 'alenseo');
                }
            } else {
                $message = __('Der Titel enthält das Fokus-Keyword nicht. Füge es hinzu, um die Sichtbarkeit in Suchergebnissen zu verbessern.', 'alenseo');
            }
            
            return array(
                'score' => min(100, $score), // Maximaler Score ist 100
                'message' => $message
            );
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Minimal Analysis: Fehler bei der Titelanalyse: " . $e->getMessage());
            }
            
            return array(
                'score' => 0,
                'message' => __('Fehler bei der Titelanalyse. Bitte versuche es erneut.', 'alenseo')
            );
        }
    }

    /**
     * Inhalt analysieren
     * 
     * @since    1.0.0
     * @param    object    $post           Post-Objekt
     * @param    string    $focus_keyword  Fokus-Keyword
     * @return   array     Analyse-Ergebnis (Score, Meldung)
     */
    private function analyze_content($post, $focus_keyword) {
        try {
            $content = $post->post_content;
            $score = 0;
            $message = '';
            
            // HTML-Tags entfernen
            $content_text = wp_strip_all_tags($content);
            
            // Länge prüfen
            $content_length = str_word_count($content_text);
            
            if ($content_length < 300) {
                $score = 20;
                $message = __('Der Inhalt ist zu kurz. Verwende mindestens 300 Wörter für eine gute SEO-Optimierung.', 'alenseo');
            } elseif ($content_length >= 300 && $content_length < 600) {
                $score = 50;
                $message = __('Die Inhaltslänge ist ok, aber mehr Inhalt (mindestens 600 Wörter) würde die SEO verbessern.', 'alenseo');
            } else {
                $score = 70;
                $message = __('Gut! Der Inhalt hat eine gute Länge.', 'alenseo');
            }
            
            // Keyword-Dichte prüfen
            $keyword_count = substr_count(strtolower($content_text), strtolower($focus_keyword));
            $keyword_density = ($content_length > 0) ? ($keyword_count / $content_length) * 100 : 0;
            
            if ($keyword_count === 0) {
                $message = __('Das Fokus-Keyword kommt im Inhalt nicht vor. Verwende es mindestens einmal.', 'alenseo');
            } elseif ($keyword_density < 0.5) {
                $score += 10;
                $message = __('Das Fokus-Keyword kommt im Inhalt zu selten vor. Eine empfohlene Dichte liegt bei 0,5% bis 2,5%.', 'alenseo');
            } elseif ($keyword_density >= 0.5 && $keyword_density <= 2.5) {
                $score += 30;
                $message = __('Gut! Das Fokus-Keyword hat eine optimale Dichte im Inhalt.', 'alenseo');
            } else {
                $score += 5;
                $message = __('Das Fokus-Keyword kommt zu häufig vor (Keyword-Stuffing). Reduziere die Häufigkeit.', 'alenseo');
            }
            
            return array(
                'score' => min(100, $score), // Maximaler Score ist 100
                'message' => $message
            );
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Minimal Analysis: Fehler bei der Inhaltsanalyse: " . $e->getMessage());
            }
            
            return array(
                'score' => 0,
                'message' => __('Fehler bei der Inhaltsanalyse. Bitte versuche es erneut.', 'alenseo')
            );
        }
    }

    /**
     * URL analysieren
     * 
     * @since    1.0.0
     * @param    object    $post           Post-Objekt
     * @param    string    $focus_keyword  Fokus-Keyword
     * @return   array     Analyse-Ergebnis (Score, Meldung)
     */
    private function analyze_url($post, $focus_keyword) {
        try {
            $slug = $post->post_name;
            $score = 0;
            $message = '';
            
            // Slug-Länge prüfen
            $slug_length = strlen($slug);
            
            if ($slug_length < 3) {
                $score = 20;
                $message = __('Die URL ist zu kurz. Verwende eine aussagekräftigere URL.', 'alenseo');
            } elseif ($slug_length > 75) {
                $score = 40;
                $message = __('Die URL ist zu lang. Halte sie kürzer für eine bessere Lesbarkeit.', 'alenseo');
            } else {
                // Gute Länge
                $score += 40;
            }
            
            // Keyword im Slug prüfen
            $keyword_slug = sanitize_title($focus_keyword);
            
            if (strpos($slug, $keyword_slug) !== false) {
                $score += 60;
                $message = __('Gut! Die URL enthält das Fokus-Keyword.', 'alenseo');
            } else {
                $message = __('Die URL enthält das Fokus-Keyword nicht. Erwäge eine Änderung für bessere SEO.', 'alenseo');
            }
            
            return array(
                'score' => min(100, $score), // Maximaler Score ist 100
                'message' => $message
            );
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Minimal Analysis: Fehler bei der URL-Analyse: " . $e->getMessage());
            }
            
            return array(
                'score' => 0,
                'message' => __('Fehler bei der URL-Analyse. Bitte versuche es erneut.', 'alenseo')
            );
        }
    }

    /**
     * Meta-Beschreibung analysieren
     * 
     * @since    1.0.0
     * @param    int       $post_id        Post-ID
     * @param    string    $focus_keyword  Fokus-Keyword
     * @return   array     Analyse-Ergebnis (Score, Meldung)
     */
    private function analyze_meta_description($post_id, $focus_keyword) {
        try {
            $score = 0;
            $message = '';
            
            // Meta-Beschreibung aus verschiedenen SEO-Plugins abrufen
            $meta_description = '';
            
            // Zuerst eigene Meta-Description
            $meta_description = get_post_meta($post_id, '_alenseo_meta_description', true);
            
            // Yoast SEO
            if (empty($meta_description)) {
                $yoast_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                if (!empty($yoast_desc)) {
                    $meta_description = $yoast_desc;
                }
            }
            
            // All in One SEO
            if (empty($meta_description)) {
                $aioseo_desc = get_post_meta($post_id, '_aioseo_description', true);
                if (!empty($aioseo_desc)) {
                    $meta_description = $aioseo_desc;
                }
            }
            if (empty($meta_description)) {
                $aioseop_desc = get_post_meta($post_id, '_aioseop_description', true);
                if (!empty($aioseop_desc)) {
                    $meta_description = $aioseop_desc;
                }
            }
            
            // RankMath
            if (empty($meta_description)) {
                $rankmath_desc = get_post_meta($post_id, 'rank_math_description', true);
                if (!empty($rankmath_desc)) {
                    $meta_description = $rankmath_desc;
                }
            }
            
            // SEOPress
            if (empty($meta_description)) {
                $seopress_desc = get_post_meta($post_id, '_seopress_titles_desc', true);
                if (!empty($seopress_desc)) {
                    $meta_description = $seopress_desc;
                }
            }
            
            // WPBakery
            if (empty($meta_description)) {
                $vc_desc = get_post_meta($post_id, 'vc_description', true);
                if (!empty($vc_desc)) {
                    $meta_description = $vc_desc;
                }
            }
            
            // Fallback auf Excerpt
            if (empty($meta_description)) {
                $post = get_post($post_id);
                $meta_description = $post->post_excerpt;
            }
            
            // Wenn keine Meta-Beschreibung gefunden wurde
            if (empty($meta_description)) {
                return array(
                    'score' => 0,
                    'message' => __('Keine Meta-Beschreibung gefunden. Erstelle eine Meta-Beschreibung mit dem Fokus-Keyword.', 'alenseo')
                );
            }
            
            // Länge prüfen
            $description_length = strlen($meta_description);
            
            if ($description_length < 120) {
                $score = 20;
                $message = __('Die Meta-Beschreibung ist zu kurz. Verwende 120-160 Zeichen für eine optimale Darstellung in Suchergebnissen.', 'alenseo');
            } elseif ($description_length > 160) {
                $score = 40;
                $message = __('Die Meta-Beschreibung ist zu lang. Halte sie unter 160 Zeichen für eine optimale Darstellung in Suchergebnissen.', 'alenseo');
            } else {
                // Gute Länge
                $score += 40;
                $message = __('Die Meta-Beschreibung hat eine gute Länge.', 'alenseo');
            }
            
            // Keyword in Meta-Beschreibung prüfen
            if (stripos($meta_description, $focus_keyword) !== false) {
                $score += 60;
                $message = __('Gut! Die Meta-Beschreibung enthält das Fokus-Keyword.', 'alenseo');
            } else {
                $message = __('Die Meta-Beschreibung enthält das Fokus-Keyword nicht. Füge es hinzu, um die Relevanz in Suchergebnissen zu erhöhen.', 'alenseo');
            }
            
            return array(
                'score' => min(100, $score), // Maximaler Score ist 100
                'message' => $message
            );
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Minimal Analysis: Fehler bei der Meta-Description-Analyse: " . $e->getMessage());
            }
            
            return array(
                'score' => 0,
                'message' => __('Fehler bei der Meta-Description-Analyse. Bitte versuche es erneut.', 'alenseo')
            );
        }
    }

    /**
     * Generiere einfache Keywords basierend auf dem Inhalt
     * 
     * @since    1.0.0
     * @param    int       $post_id    Post-ID
     * @return   array     Array mit keyword-Vorschlägen
     */
    public function generate_keywords($post_id) {
        try {
            $post = get_post($post_id);
            
            if (!$post) {
                return array();
            }
            
            // Titel und Inhalt kombinieren
            $title = $post->post_title;
            $content = wp_strip_all_tags($post->post_content);
            
            // Auf sinnvolle Länge begrenzen
            if (strlen($content) > 1000) {
                $content = substr($content, 0, 1000);
            }
            
            // Stopwörter-Liste (kann erweitert werden)
            $stopwords = array(
                'der', 'die', 'das', 'ein', 'eine', 'und', 'ist', 'von', 'zu', 'in',
                'im', 'mit', 'für', 'auf', 'the', 'and', 'or', 'of', 'to', 'a', 'an',
                'in', 'is', 'it', 'he', 'she', 'but', 'as', 'at', 'by', 'for', 'be'
            );
            
            // Text kombinieren und in Wörter aufteilen
            $text = $title . ' ' . $content;
            $text = strtolower($text);
            
            // Sonderzeichen entfernen
            $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
            
            // In Wörter aufteilen
            $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
            
            // Wörter zählen und filtern
            $word_count = array();
            foreach ($words as $word) {
                // Leere Wörter und Stopwörter überspringen
                if (empty($word) || in_array($word, $stopwords) || strlen($word) < 3) {
                    continue;
                }
                
                if (isset($word_count[$word])) {
                    $word_count[$word]++;
                } else {
                    $word_count[$word] = 1;
                }
            }
            
            // Nach Häufigkeit sortieren
            arsort($word_count);
            
            // Top-Wörter auswählen und Keywordobjekte erstellen
            $keywords = array();
            $i = 0;
            foreach ($word_count as $word => $count) {
                if ($i >= 5) break; // Maximal 5 Keywords
                
                $keywords[] = array(
                    'keyword' => $word,
                    'score' => min(95, 50 + ($count * 5)) // Score basierend auf Häufigkeit
                );
                
                $i++;
            }
            
            // Zusätzlich den Titel als Keyword hinzufügen
            if (!empty($title) && strlen($title) > 3) {
                array_unshift($keywords, array(
                    'keyword' => $title,
                    'score' => 95
                ));
            }
            
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Minimal Analysis: " . count($keywords) . " Keywords generiert für Post ID: $post_id");
            }
            
            return $keywords;
        } catch (Exception $e) {
            if (function_exists('alenseo_log')) {
                alenseo_log("Alenseo Minimal Analysis: Fehler bei der Keyword-Generierung: " . $e->getMessage());
            }
            
            // Standardwerte zurückgeben
            return array(
                array('keyword' => 'seo', 'score' => 80),
                array('keyword' => 'wordpress', 'score' => 75),
                array('keyword' => 'optimierung', 'score' => 70)
            );
        }
    }
}
