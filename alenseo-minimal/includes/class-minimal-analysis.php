<?php
/**
 * Minimal Analysis Klasse für Alenseo SEO
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
        // Debug-Log
        error_log("Alenseo Analysis: Konstruktor aufgerufen");
        
        // Einstellungen laden
        $this->settings = get_option('alenseo_settings', array());
        
        error_log("Alenseo Analysis: Konstruktor abgeschlossen");
    }

    /**
     * SEO-Daten für einen Beitrag abrufen
     * 
     * @since    1.0.0
     * @param    int    $post_id    ID des Beitrags
     * @return   array   SEO-Daten (Scores, Meldungen, etc.)
     */
    public function get_seo_data($post_id) {
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
            'score' => $score,
            'message' => $message
        );
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
        
        // Überschriften prüfen
        $matches = array();
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $content, $matches);
        
        $headings = array();
        if (!empty($matches[0])) {
            foreach ($matches[0] as $index => $full_match) {
                $level = $matches[1][$index];
                $text = wp_strip_all_tags($matches[2][$index]);
                $headings[] = array(
                    'level' => $level,
                    'text' => $text
                );
            }
        }
        
        // Prüfe, ob Überschriften vorhanden sind
        if (empty($headings)) {
            $message .= ' ' . __('Der Inhalt enthält keine Überschriften. Verwende H2-H3 Überschriften zur besseren Strukturierung.', 'alenseo');
        } else {
            // Prüfe, ob das Keyword in einer Überschrift vorkommt
            $keyword_in_heading = false;
            foreach ($headings as $heading) {
                if (stripos($heading['text'], $focus_keyword) !== false) {
                    $keyword_in_heading = true;
                    break;
                }
            }
            
            if ($keyword_in_heading) {
                $score += 20;
                $message .= ' ' . __('Gut! Das Fokus-Keyword ist in mindestens einer Überschrift enthalten.', 'alenseo');
            } else {
                $message .= ' ' . __('Verwende das Fokus-Keyword in mindestens einer Überschrift (H2-H3).', 'alenseo');
            }
        }
        
        return array(
            'score' => min(100, $score),
            'message' => $message
        );
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
            'score' => $score,
            'message' => $message
        );
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
        $score = 0;
        $message = '';
        
        // Meta-Beschreibung aus verschiedenen SEO-Plugins abrufen
        $meta_description = '';
        
        // Yoast SEO
        $yoast_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (!empty($yoast_desc)) {
            $meta_description = $yoast_desc;
        }
        
        // All in One SEO
        if (empty($meta_description)) {
            $aioseo_desc = get_post_meta($post_id, '_aioseo_description', true);
            if (!empty($aioseo_desc)) {
                $meta_description = $aioseo_desc;
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
            'score' => $score,
            'message' => $message
        );
    }

    /**
     * Keywords aus dem Inhalt generieren
     * 
     * @since    1.0.0
     * @param    int       $post_id    Post-ID
     * @return   array     Array mit generierten Keywords
     */
    public function generate_keywords($post_id) {
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
            'der', 'die', 'das', 'ein', 'eine', 'einer', 'einem', 'einen', 'dem', 'den',
            'des', 'und', 'oder', 'aber', 'wenn', 'als', 'wie', 'für', 'mit', 'durch',
            'von', 'zu', 'auf', 'bei', 'nach', 'vor', 'an', 'in', 'um', 'aus', 'über',
            'unter', 'neben', 'zwischen', 'dass', 'weil', 'obwohl', 'während', 'seit',
            'bis', 'damit', 'dafür', 'dazu', 'dabei', 'darin', 'darauf', 'darüber',
            'darunter', 'daneben', 'davon', 'davor', 'danach', 'daran', 'dann', 'dort',
            'hier', 'da', 'wo', 'wer', 'was', 'wann', 'wie', 'warum', 'wieso', 'weshalb',
            'welche', 'welcher', 'welches', 'welchem', 'welchen', 'welches', 'welcher',
            'the', 'a', 'an', 'and', 'or', 'but', 'if', 'as', 'how', 'for', 'with',
            'through', 'from', 'to', 'on', 'at', 'after', 'before', 'in', 'around',
            'out', 'over', 'under', 'beside', 'between', 'that', 'because', 'although',
            'while', 'since', 'until', 'so', 'too', 'very', 'quite', 'rather', 'just',
            'now', 'then', 'there', 'here', 'where', 'who', 'what', 'when', 'why', 'how',
            'which', 'whose', 'whom'
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
        
        // Top-Wörter auswählen
        $keywords = array_slice(array_keys($word_count), 0, 5);
        
        // Kombinationen für Mehrwort-Keywords
        $combinations = array();
        $original_text = $title . ' ' . $content;
        
        // Einfache 2-Wort-Kombinationen finden
        preg_match_all('/\b(\p{L}{3,})\s+(\p{L}{3,})\b/u', $original_text, $matches);
        if (!empty($matches[0])) {
            $phrase_count = array();
            foreach ($matches[0] as $phrase) {
                $phrase = strtolower($phrase);
                if (isset($phrase_count[$phrase])) {
                    $phrase_count[$phrase]++;
                } else {
                    $phrase_count[$phrase] = 1;
                }
            }
            
            // Nach Häufigkeit sortieren
            arsort($phrase_count);
            
            // Top-Phrasen auswählen
            $combinations = array_slice(array_keys($phrase_count), 0, 2);
        }
        
        // Keywords und Kombinationen zusammenführen
        $result = array_merge($combinations, $keywords);
        
        // Auf 5 Keywords begrenzen
        return array_slice($result, 0, 5);
    }
}