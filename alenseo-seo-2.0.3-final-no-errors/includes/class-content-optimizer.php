<?php
/**
 * Content-Optimizer-Klasse für Alenseo SEO
 *
 * Diese Klasse ist verantwortlich für die Optimierung von Inhalten mit Hilfe der Claude AI
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
 * Die Content-Optimizer-Klasse
 */
class Alenseo_Content_Optimizer {
    
    /**
     * Claude API-Instanz
     * 
     * @var Alenseo_Claude_API
     */
    private $claude_api;
    
    /**
     * Enhanced Analysis-Instanz
     * 
     * @var Alenseo_Enhanced_Analysis
     */
    private $enhanced_analysis;
    
    /**
     * Ob die erweiterte Analyse verwendet werden soll
     * 
     * @var bool
     */
    private $use_enhanced_analysis = false;
    
    /**
     * Initialisierung der Klasse
     */
    public function __construct() {
        // Claude API-Klasse laden, wenn sie existiert
        if (class_exists('Alenseo_Claude_API')) {
            $this->claude_api = new Alenseo_Claude_API();
        } else {
            error_log('Alenseo_Content_Optimizer: Claude API-Klasse nicht gefunden.');
        }
        
        // Prüfen ob erweiterte Analyse verwendet werden soll
        $this->use_enhanced_analysis = apply_filters('alenseo_use_enhanced_analysis', false);
        
        // Enhanced Analysis-Klasse laden, wenn verfügbar und aktiviert
        if ($this->use_enhanced_analysis && class_exists('Alenseo_Enhanced_Analysis')) {
            $this->enhanced_analysis = new Alenseo_Enhanced_Analysis();
        }
    }
    
    /**
     * Optimierung eines Titels
     * 
     * @param int    $post_id   Die Post-ID
     * @param string $keyword   Das Fokus-Keyword
     * @param array  $options   Zusätzliche Optionen
     * @return string|WP_Error  Der optimierte Titel oder ein Fehler
     */
    public function optimize_title($post_id, $keyword, $options = array()) {
        if (!$this->claude_api) {
            return new WP_Error('claude_api_missing', 'Claude API ist nicht verfügbar.');
        }
        
        // Post-Daten abrufen
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Beitrag nicht gefunden.');
        }
        
        // Standardoptionen setzen
        $defaults = array(
            'tone' => 'professional',
            'level' => 'moderate'
        );
        $options = wp_parse_args($options, $defaults);
        
        // Prompt für die Titeloptimierung erstellen
        $prompt = $this->build_title_prompt($post, $keyword, $options);
        
        // API-Anfrage
        $response = $this->claude_api->generate_text($prompt);
        
        // Fehler prüfen
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Antwort verarbeiten und zurückgeben
        return $this->extract_title_from_response($response);
    }
    
    /**
     * Optimierung einer Meta-Description
     * 
     * @param int    $post_id   Die Post-ID
     * @param string $keyword   Das Fokus-Keyword
     * @param array  $options   Zusätzliche Optionen
     * @return string|WP_Error  Die optimierte Meta-Description oder ein Fehler
     */
    public function optimize_meta_description($post_id, $keyword, $options = array()) {
        if (!$this->claude_api) {
            return new WP_Error('claude_api_missing', 'Claude API ist nicht verfügbar.');
        }
        
        // Post-Daten abrufen
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Beitrag nicht gefunden.');
        }
        
        // Aktuelle Meta-Description abrufen
        $current_meta_description = get_post_meta($post_id, '_alenseo_meta_description', true);
        
        // Wenn keine Meta-Description von Alenseo, dann nach anderen SEO-Plugins suchen
        if (empty($current_meta_description)) {
            // Yoast SEO
            $current_meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            
            // All in One SEO
            if (empty($current_meta_description)) {
                $current_meta_description = get_post_meta($post_id, '_aioseo_description', true);
            }
            if (empty($current_meta_description)) {
                $current_meta_description = get_post_meta($post_id, '_aioseop_description', true);
            }
            
            // Rank Math
            if (empty($current_meta_description)) {
                $current_meta_description = get_post_meta($post_id, 'rank_math_description', true);
            }
            
            // SEOPress
            if (empty($current_meta_description)) {
                $current_meta_description = get_post_meta($post_id, '_seopress_titles_desc', true);
            }
            
            // WPBakery
            if (empty($current_meta_description)) {
                $current_meta_description = get_post_meta($post_id, 'vc_description', true);
            }
            
            // Fallback: Excerpt verwenden
            if (empty($current_meta_description) && !empty($post->post_excerpt)) {
                $current_meta_description = $post->post_excerpt;
            }
        }
        
        // Standardoptionen setzen
        $defaults = array(
            'tone' => 'professional',
            'level' => 'moderate'
        );
        $options = wp_parse_args($options, $defaults);
        
        // Prompt für die Meta-Description-Optimierung erstellen
        $prompt = $this->build_meta_description_prompt($post, $keyword, $current_meta_description, $options);
        
        // API-Anfrage
        $response = $this->claude_api->generate_text($prompt);
        
        // Fehler prüfen
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Antwort verarbeiten und zurückgeben
        return $this->extract_meta_description_from_response($response);
    }
    
    /**
     * Generierung von Inhaltsoptimierungsvorschlägen
     * 
     * @param int    $post_id   Die Post-ID
     * @param string $keyword   Das Fokus-Keyword
     * @param array  $options   Zusätzliche Optionen
     * @return array|WP_Error   Die Optimierungsvorschläge oder ein Fehler
     */
    public function generate_content_suggestions($post_id, $keyword, $options = array()) {
        if (!$this->claude_api) {
            return new WP_Error('claude_api_missing', 'Claude API ist nicht verfügbar.');
        }
        
        // Post-Daten abrufen
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Beitrag nicht gefunden.');
        }
        
        // Standardoptionen setzen
        $defaults = array(
            'tone' => 'professional',
            'level' => 'moderate',
            'max_suggestions' => 5
        );
        $options = wp_parse_args($options, $defaults);
        
        // Prompt für die Inhaltsoptimierung erstellen
        $prompt = $this->build_content_suggestions_prompt($post, $keyword, $options);
        
        // API-Anfrage
        $response = $this->claude_api->generate_text($prompt);
        
        // Fehler prüfen
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Antwort verarbeiten und zurückgeben
        return $this->extract_content_suggestions_from_response($response, $options['max_suggestions']);
    }
    
    /**
     * Generierung vollständiger Optimierungsvorschläge
     * 
     * @param int    $post_id   Die Post-ID
     * @param string $keyword   Das Fokus-Keyword
     * @param array  $options   Zusätzliche Optionen
     * @return array|WP_Error   Die Optimierungsvorschläge oder ein Fehler
     */
    public function generate_optimization_suggestions($post_id, $keyword, $options = array()) {
        $results = array();
        
        // Standardoptionen setzen
        $defaults = array(
            'optimize_title' => true,
            'optimize_meta_description' => true,
            'optimize_content' => true,
            'tone' => 'professional',
            'level' => 'moderate'
        );
        $options = wp_parse_args($options, $defaults);
        
        // Titeloptimierung
        if ($options['optimize_title']) {
            $title_result = $this->optimize_title($post_id, $keyword, $options);
            if (!is_wp_error($title_result)) {
                $results['title'] = $title_result;
            }
        }
        
        // Meta-Description-Optimierung
        if ($options['optimize_meta_description']) {
            $meta_result = $this->optimize_meta_description($post_id, $keyword, $options);
            if (!is_wp_error($meta_result)) {
                $results['meta_description'] = $meta_result;
            }
        }
        
        // Inhaltsoptimierung
        if ($options['optimize_content']) {
            $content_result = $this->generate_content_suggestions($post_id, $keyword, $options);
            if (!is_wp_error($content_result)) {
                $results['content'] = $content_result;
            }
        }
        
        return $results;
    }
    
    /**
     * Anwenden eines Optimierungsvorschlags
     * 
     * @param int    $post_id   Die Post-ID
     * @param string $type      Der Typ des Vorschlags (title, meta_description)
     * @param string $content   Der neue Inhalt
     * @return bool|WP_Error    true bei Erfolg, sonst ein Fehler
     */
    public function apply_suggestion($post_id, $type, $content) {
        // Post-Daten abrufen
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Beitrag nicht gefunden.');
        }
        
        switch ($type) {
            case 'title':
                // Titel aktualisieren
                $update_args = array(
                    'ID' => $post_id,
                    'post_title' => sanitize_text_field($content)
                );
                $result = wp_update_post($update_args);
                
                if (is_wp_error($result)) {
                    return $result;
                }
                
                return true;
                
            case 'meta_description':
                // Meta-Description aktualisieren
                $result = update_post_meta($post_id, '_alenseo_meta_description', sanitize_text_field($content));
                
                if (false === $result) {
                    return new WP_Error('update_failed', 'Meta-Description konnte nicht aktualisiert werden.');
                }
                
                return true;
                
            default:
                return new WP_Error('invalid_type', 'Ungültiger Vorschlagstyp.');
        }
    }
    
    /**
     * Batch-Optimierung für mehrere Posts
     * 
     * @param array $post_ids    Die Post-IDs für die Batch-Optimierung
     * @param array $options     Zusätzliche Optionen für die Optimierung
     * @return array             Ergebnisse der Batch-Optimierung
     */
    public function batch_optimize($post_ids, $options = array()) {
        // Standard-Optionen festlegen
        $default_options = array(
            'optimize_title' => true,
            'optimize_meta_description' => true,
            'optimize_content' => false,
            'auto_save' => false,
            'generate_keywords' => false,
            'tone' => 'professional',
            'level' => 'moderate'
        );
        
        $options = wp_parse_args($options, $default_options);
        
        $results = array();
        
        // Jeden Post optimieren
        foreach ($post_ids as $post_id) {
            $post_result = array(
                'post_id' => $post_id,
                'title' => '',
                'status' => 'pending',
                'optimizations' => array()
            );
            
            // Post abrufen
            $post = get_post($post_id);
            
            if (!$post) {
                $post_result['status'] = 'error';
                $post_result['message'] = __('Beitrag nicht gefunden.', 'alenseo');
                $results[$post_id] = $post_result;
                continue;
            }
            
            $post_result['title'] = $post->post_title;
            
            try {
                // Keyword abrufen oder generieren, falls erforderlich
                $keyword = get_post_meta($post_id, '_alenseo_keyword', true);
                
                if (empty($keyword) && $options['generate_keywords']) {
                    if ($this->claude_api) {
                        $generated_keywords = $this->generate_post_keywords($post_id);
                        
                        if (!is_wp_error($generated_keywords) && is_array($generated_keywords) && !empty($generated_keywords)) {
                            // Erstes Keyword verwenden
                            $keyword = $generated_keywords[0];
                            update_post_meta($post_id, '_alenseo_keyword', $keyword);
                            
                            $post_result['keyword_generated'] = true;
                            $post_result['keyword'] = $keyword;
                        } else {
                            $post_result['keyword_error'] = is_wp_error($generated_keywords) ? 
                                $generated_keywords->get_error_message() : 
                                __('Fehler bei der Keyword-Generierung.', 'alenseo');
                        }
                    }
                } else {
                    $post_result['keyword'] = $keyword;
                }
                
                // Wenn kein Keyword vorhanden oder generiert werden konnte, überspringen
                if (empty($keyword)) {
                    $post_result['status'] = 'skipped';
                    $post_result['message'] = __('Kein Keyword vorhanden und konnte nicht generiert werden.', 'alenseo');
                    $results[$post_id] = $post_result;
                    continue;
                }
                
                // Optimierungen durchführen
                if ($options['optimize_title']) {
                    $title_result = $this->optimize_title($post_id, $keyword, $options);
                    
                    if (!is_wp_error($title_result)) {
                        $post_result['optimizations']['title'] = array(
                            'status' => 'success',
                            'original' => $post->post_title,
                            'optimized' => $title_result
                        );
                        
                        // Automatisch speichern, wenn aktiviert
                        if ($options['auto_save']) {
                            wp_update_post(array(
                                'ID' => $post_id,
                                'post_title' => $title_result
                            ));
                        }
                    } else {
                        $post_result['optimizations']['title'] = array(
                            'status' => 'error',
                            'message' => $title_result->get_error_message()
                        );
                    }
                }
                
                if ($options['optimize_meta_description']) {
                    $meta_result = $this->optimize_meta_description($post_id, $keyword, $options);
                    
                    if (!is_wp_error($meta_result)) {
                        $current_meta = get_post_meta($post_id, '_alenseo_meta_description', true);
                        
                        $post_result['optimizations']['meta_description'] = array(
                            'status' => 'success',
                            'original' => $current_meta,
                            'optimized' => $meta_result
                        );
                        
                        // Automatisch speichern, wenn aktiviert
                        if ($options['auto_save']) {
                            update_post_meta($post_id, '_alenseo_meta_description', $meta_result);
                        }
                    } else {
                        $post_result['optimizations']['meta_description'] = array(
                            'status' => 'error',
                            'message' => $meta_result->get_error_message()
                        );
                    }
                }
                
                if ($options['optimize_content']) {
                    $content_result = $this->generate_content_suggestions($post_id, $keyword, $options);
                    
                    if (!is_wp_error($content_result)) {
                        $post_result['optimizations']['content'] = array(
                            'status' => 'success',
                            'suggestions' => $content_result
                        );
                        
                        // Content-Optimierung wird nicht automatisch gespeichert
                    } else {
                        $post_result['optimizations']['content'] = array(
                            'status' => 'error',
                            'message' => $content_result->get_error_message()
                        );
                    }
                }
                
                // Wenn mindestens eine Optimierung erfolgreich war
                if (
                    (isset($post_result['optimizations']['title']) && $post_result['optimizations']['title']['status'] === 'success') ||
                    (isset($post_result['optimizations']['meta_description']) && $post_result['optimizations']['meta_description']['status'] === 'success') ||
                    (isset($post_result['optimizations']['content']) && $post_result['optimizations']['content']['status'] === 'success')
                ) {
                    $post_result['status'] = 'success';
                    
                    // SEO-Analyse durchführen, wenn automatisches Speichern aktiviert ist
                    if ($options['auto_save'] && class_exists('Alenseo_Minimal_Analysis')) {
                        $analyzer = new Alenseo_Minimal_Analysis();
                        $analyzer->analyze_post($post_id);
                        
                        // Neuen Score abrufen
                        $new_score = get_post_meta($post_id, '_alenseo_seo_score', true);
                        $post_result['new_score'] = $new_score;
                    }
                } else {
                    $post_result['status'] = 'error';
                    $post_result['message'] = __('Keine der Optimierungen war erfolgreich.', 'alenseo');
                }
                
            } catch (Exception $e) {
                $post_result['status'] = 'error';
                $post_result['message'] = $e->getMessage();
            }
            
            $results[$post_id] = $post_result;
        }
        
        return $results;
    }
    
    /*
     * Private Helper-Methoden
     */
    
    /**
     * Prompt für die Titeloptimierung erstellen
     *
     * @param object $post     WordPress Post-Objekt
     * @param string $keyword  Das Fokus-Keyword
     * @param array  $options  Optionen für die Optimierung
     * @return string Der Prompt für die API
     */
    private function build_title_prompt($post, $keyword, $options) {
        $current_title = $post->post_title;
        $content_excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 150, '...');
        
        $tone_instruction = $this->get_tone_instruction($options['tone']);
        $level_instruction = $this->get_level_instruction($options['level']);
        
        $prompt = "Du bist ein SEO-Experte mit jahrelanger Erfahrung in der Optimierung von Website-Inhalten. Deine Aufgabe ist es, einen SEO-optimierten Titel für eine Webseite zu erstellen.

Der aktuelle Titel der Webseite lautet: \"{$current_title}\".

Das Fokus-Keyword für die Seite ist: \"{$keyword}\".

Hier ist ein Auszug aus dem Inhalt der Seite, um den Kontext zu verstehen:
\"{$content_excerpt}\"

{$tone_instruction}

{$level_instruction}

Bitte erstelle einen neuen, SEO-optimierten Titel, der:
1. Das Fokus-Keyword enthält (idealerweise am Anfang)
2. Zwischen 30 und 60 Zeichen lang ist
3. Die Aufmerksamkeit der Nutzer weckt und zum Klicken anregt
4. Den Inhalt der Seite akkurat widerspiegelt
5. Natürlich klingt (kein Keyword-Stuffing)

Antworte nur mit dem neuen Titel, ohne zusätzliche Erklärungen oder Anführungszeichen.";

        return $prompt;
    }
    
    /**
     * Prompt für die Meta-Description-Optimierung erstellen
     *
     * @param object $post                  WordPress Post-Objekt
     * @param string $keyword               Das Fokus-Keyword
     * @param string $current_meta_description  Die aktuelle Meta-Description
     * @param array  $options               Optionen für die Optimierung
     * @return string Der Prompt für die API
     */
    private function build_meta_description_prompt($post, $keyword, $current_meta_description, $options) {
        $content_excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 150, '...');
        
        $tone_instruction = $this->get_tone_instruction($options['tone']);
        $level_instruction = $this->get_level_instruction($options['level']);
        
        $current_meta_part = empty($current_meta_description) 
            ? "Die Seite hat derzeit keine Meta-Description." 
            : "Die aktuelle Meta-Description lautet: \"{$current_meta_description}\".";
        
        $prompt = "Du bist ein SEO-Experte mit jahrelanger Erfahrung in der Optimierung von Website-Inhalten. Deine Aufgabe ist es, eine SEO-optimierte Meta-Description für eine Webseite zu erstellen.

Der Titel der Webseite lautet: \"{$post->post_title}\".

Das Fokus-Keyword für die Seite ist: \"{$keyword}\".

{$current_meta_part}

Hier ist ein Auszug aus dem Inhalt der Seite, um den Kontext zu verstehen:
\"{$content_excerpt}\"

{$tone_instruction}

{$level_instruction}

Bitte erstelle eine neue, SEO-optimierte Meta-Description, die:
1. Das Fokus-Keyword enthält (am besten einmal)
2. Zwischen 120 und 155 Zeichen lang ist
3. Den Nutzer zum Klicken motiviert (mit einem klaren Nutzenversprechen)
4. Den Inhalt der Seite akkurat zusammenfasst
5. Einen natürlichen, informativen Sprachstil verwendet

Antworte nur mit der neuen Meta-Description, ohne zusätzliche Erklärungen oder Anführungszeichen.";

        return $prompt;
    }
    
    /**
     * Prompt für Inhaltsoptimierungsvorschläge erstellen
     *
     * @param object $post     WordPress Post-Objekt
     * @param string $keyword  Das Fokus-Keyword
     * @param array  $options  Optionen für die Optimierung
     * @return string Der Prompt für die API
     */
    private function build_content_suggestions_prompt($post, $keyword, $options) {
        $content = wp_strip_all_tags($post->post_content);
        $title = $post->post_title;
        
        // Maximal 2000 Zeichen des Inhalts verwenden, um den Prompt nicht zu groß zu machen
        if (mb_strlen($content) > 2000) {
            $content = mb_substr($content, 0, 1997) . '...';
        }
        
        $tone_instruction = $this->get_tone_instruction($options['tone']);
        $level_instruction = $this->get_level_instruction($options['level']);
        
        $prompt = "Du bist ein SEO-Experte mit jahrelanger Erfahrung in der Optimierung von Website-Inhalten. Deine Aufgabe ist es, konkrete Verbesserungsvorschläge für den Content einer Webseite zu machen, um ihn für Suchmaschinen zu optimieren.

Der Titel der Webseite lautet: \"{$title}\".

Das Fokus-Keyword für die Seite ist: \"{$keyword}\".

Hier ist der aktuelle Inhalt der Seite:
\"{$content}\"

{$tone_instruction}

{$level_instruction}

Bitte analysiere den Inhalt und gib konkrete, umsetzbare Optimierungsvorschläge in Listenform. Konzentriere dich auf folgende Aspekte:

1. Keyword-Verwendung (Dichte, Platzierung, Variationen)
2. Überschriftenstruktur (H1, H2, H3 Hierarchie)
3. Inhaltslänge und -qualität
4. Lesbarkeit und Benutzerfreundlichkeit
5. Interne und externe Verlinkungen
6. Verwendung von Bildern und Alt-Texten

Gib für jeden Aspekt maximal 1-2 konkrete Verbesserungsvorschläge, die direkt umgesetzt werden können. Formuliere alles als klare, präzise Handlungsanweisungen.

Formatiere deine Antwort als nummerierte Liste mit einzelnen, prägnanten Vorschlägen.";

        return $prompt;
    }
    
    /**
     * Tone-Instruktion basierend auf der ausgewählten Tonalität zurückgeben
     *
     * @param string $tone Die gewünschte Tonalität
     * @return string Die Anweisung für die API
     */
    private function get_tone_instruction($tone) {
        switch ($tone) {
            case 'friendly':
                return "Verwende einen freundlichen, zugänglichen Ton, der sich direkt an den Leser richtet.";
            
            case 'casual':
                return "Verwende einen lockeren, entspannten Sprachstil, der nahbar und unkompliziert klingt.";
            
            case 'formal':
                return "Verwende eine formelle, präzise Sprache, die professionell und autoritativ wirkt.";
            
            case 'professional':
            default:
                return "Verwende einen professionellen, sachlichen Ton, der kompetent und vertrauenswürdig wirkt.";
        }
    }
    
    /**
     * Level-Instruktion basierend auf dem ausgewählten Optimierungsgrad zurückgeben
     *
     * @param string $level Der gewünschte Optimierungsgrad
     * @return string Die Anweisung für die API
     */
    private function get_level_instruction($level) {
        switch ($level) {
            case 'light':
                return "Führe nur leichte Optimierungen durch. Bewahre den ursprünglichen Stil und die grundlegende Struktur.";
            
            case 'aggressive':
                return "Führe umfangreiche Optimierungen durch. Priorität hat die SEO-Leistung, auch wenn sich der Stil stark verändert.";
            
            case 'moderate':
            default:
                return "Führe moderate Optimierungen durch, die die SEO-Leistung verbessern, aber den grundlegenden Stil bewahren.";
        }
    }
    
    /**
     * Titel aus der API-Antwort extrahieren
     *
     * @param string $response Die API-Antwort
     * @return string Der extrahierte Titel
     */
    private function extract_title_from_response($response) {
        // Bereinigungen durchführen
        $title = trim($response);
        
        // Anführungszeichen entfernen, falls vorhanden
        $title = trim($title, '"\'');
        
        return $title;
    }
    
    /**
     * Meta-Description aus der API-Antwort extrahieren
     *
     * @param string $response Die API-Antwort
     * @return string Die extrahierte Meta-Description
     */
    private function extract_meta_description_from_response($response) {
        // Bereinigungen durchführen
        $meta_description = trim($response);
        
        // Anführungszeichen entfernen, falls vorhanden
        $meta_description = trim($meta_description, '"\'');
        
        return $meta_description;
    }
    
    /**
     * Inhaltsvorschläge aus der API-Antwort extrahieren
     *
     * @param string $response Die API-Antwort
     * @param int $max_suggestions Maximale Anzahl der Vorschläge
     * @return array Die extrahierten Vorschläge
     */
    private function extract_content_suggestions_from_response($response, $max_suggestions = 5) {
        // Antwort in Zeilen aufteilen
        $lines = explode("\n", $response);
        $suggestions = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Leere Zeilen überspringen
            if (empty($line)) {
                continue;
            }
            
            // Nummerierung und Listenpunkte entfernen
            $line = preg_replace('/^(\d+[\.\)]\s*|\-\s*|\*\s*)/', '', $line);
            $line = trim($line);
            
            if (!empty($line)) {
                $suggestions[] = $line;
                
                // Maximale Anzahl an Vorschlägen erreicht
                if (count($suggestions) >= $max_suggestions) {
                    break;
                }
            }
        }
        
        return $suggestions;
    }

    /**
     * Keywords für einen Post generieren
     * 
     * @param int $post_id Die Post-ID
     * @return array|WP_Error Array mit Keywords oder Fehler
     */
    public function generate_post_keywords($post_id) {
        if (!$this->claude_api) {
            return new WP_Error('claude_api_missing', __('Claude API ist nicht verfügbar.', 'alenseo'));
        }
        
        // Post abrufen
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Beitrag nicht gefunden.', 'alenseo'));
        }
        
        // Prüfen, ob bereits Keywords in der Datenbank existieren
        global $alenseo_database;
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_keywords')) {
            $existing_keywords = $alenseo_database->get_keywords($post_id);
            if (!empty($existing_keywords)) {
                $keyword_list = array();
                foreach ($existing_keywords as $keyword_data) {
                    $keyword_list[] = $keyword_data['keyword'];
                }
                
                if (!empty($keyword_list)) {
                    return $keyword_list;
                }
            }
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

        // API-Anfrage senden
        $response = $this->claude_api->generate_text($prompt, array(
            'temperature' => 0.1,
            'max_tokens' => 300
        ));
        
        // Fehler prüfen
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Keywords aus der Antwort extrahieren
        if (function_exists('extract_keywords_from_response')) {
            return extract_keywords_from_response($response);
        }
        
        // Eigene Extraktion, falls die Funktion nicht existiert
        $response = trim($response);
        $response = trim($response, '"\'');
        $keywords = preg_split('/[,\n]+/', $response, -1, PREG_SPLIT_NO_EMPTY);
        
        // Bereinigen
        $cleaned_keywords = array();
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            $keyword = trim($keyword, '"\'');
            
            if (!empty($keyword) && !in_array($keyword, $cleaned_keywords)) {
                $cleaned_keywords[] = $keyword;
                
                // Keyword in der Datenbank speichern, wenn die Datenbank-Klasse verfügbar ist
                global $alenseo_database;
                if (isset($alenseo_database) && method_exists($alenseo_database, 'save_keyword')) {
                    // Initial mit Score 0 speichern, wird später durch Analyse aktualisiert
                    $alenseo_database->save_keyword($post_id, $keyword, 0, 'pending');
                }
            }
        }
        
        return $cleaned_keywords;
    }

    /**
     * Keywords für einen Inhalt generieren
     * 
     * @param int    $post_id   Die Post-ID
     * @param array  $options   Zusätzliche Optionen
     * @return array|WP_Error  Die generierten Keywords oder ein Fehler
     */
    public function generate_keywords($post_id, $options = array()) {
        // Post abrufen
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Inhalt nicht gefunden.', 'alenseo'));
        }
        
        // Optionen vorbereiten
        $options = wp_parse_args($options, array(
            'max_keywords' => 5,
            'min_length' => 3,
            'force_generation' => false
        ));
        
        // Prüfen, ob bereits Keywords in der Datenbank vorhanden sind
        global $alenseo_database;
        $keyword_list = array();
        
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_keywords')) {
            $existing_db_keywords = $alenseo_database->get_keywords($post_id);
            if (!empty($existing_db_keywords) && !$options['force_generation']) {
                foreach ($existing_db_keywords as $keyword_data) {
                    $keyword_list[] = $keyword_data['keyword'];
                }
                
                if (!empty($keyword_list)) {
                    return $keyword_list;
                }
            }
        }
        
        // Alternativ in Post-Meta prüfen (Abwärtskompatibilität)
        $existing_keywords = get_post_meta($post_id, 'alenseo_focus_keywords', true);
        if (!empty($existing_keywords) && !$options['force_generation']) {
            // Bereits vorhandene Keywords als Array zurückgeben
            $keyword_list = array_map('trim', explode(',', $existing_keywords));
            
            // Auch in der Datenbank speichern, wenn verfügbar
            if (isset($alenseo_database) && method_exists($alenseo_database, 'save_keyword')) {
                foreach ($keyword_list as $keyword) {
                    $alenseo_database->save_keyword($post_id, $keyword, 0, 'pending');
                }
            }
            
            return $keyword_list;
        }
        
        // Claude API-Instanz prüfen
        if (!$this->claude_api || !$this->claude_api->is_api_configured()) {
            return new WP_Error('api_not_configured', __('Claude API ist nicht konfiguriert.', 'alenseo'));
        }
        
        // Prompt erstellen
        $prompt = $this->build_keyword_generation_prompt($post, $options);
        
        try {
            // API-Anfrage stellen
            $response = $this->claude_api->generate_text($prompt);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            // Antwort verarbeiten
            $keywords = $this->parse_keywords_response($response);
            
            if (empty($keywords)) {
                return new WP_Error('no_keywords_generated', __('Es konnten keine Keywords generiert werden.', 'alenseo'));
            }
            
            // Keywords filtern
            $filtered_keywords = array();
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (strlen($keyword) >= $options['min_length']) {
                    $filtered_keywords[] = $keyword;
                }
            }
            
            // Maximale Anzahl begrenzen
            $filtered_keywords = array_slice($filtered_keywords, 0, $options['max_keywords']);
            
            // Ergebnis als Komma-getrennte Liste speichern
            update_post_meta($post_id, 'alenseo_focus_keywords', implode(', ', $filtered_keywords));
            
            return $filtered_keywords;
            
        } catch (Exception $e) {
            return new WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Prompt für die Keyword-Generierung erstellen
     *
     * @param object $post     WordPress Post-Objekt
     * @param array  $options  Optionen für die Generierung
     * @return string Der Prompt für die API
     */
    private function build_keyword_generation_prompt($post, $options) {
        $title = $post->post_title;
        $content_excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 250, '...');
        $max_keywords = isset($options['max_keywords']) ? intval($options['max_keywords']) : 5;
        
        $prompt = "Du bist ein SEO-Experte mit jahrelanger Erfahrung in der Keyword-Analyse. Deine Aufgabe ist es, relevante Keywords für eine Webseite zu identifizieren.

Der Titel der Webseite lautet: \"{$title}\".

Hier ist ein Auszug aus dem Inhalt der Seite:
\"{$content_excerpt}\"

Bitte generiere die {$max_keywords} relevantesten Keywords oder Keyword-Phrasen für diese Seite. Beachte dabei folgende Kriterien:
1. Die Keywords sollten relevant für den Inhalt sein
2. Sie sollten ein realistisches Suchvolumen haben
3. Sie sollten eine moderate Wettbewerbssituation aufweisen
4. Sie können aus 1-4 Wörtern bestehen
5. Sie sollten natürlich klingen und nicht zu generisch sein

Gib nur die Keywords zurück, eines pro Zeile, ohne Nummerierungen oder andere Formatierungen.";

        return $prompt;
    }
    
    /**
     * Keywords aus der API-Antwort parsen
     *
     * @param string $response  Die API-Antwort
     * @return array Die extrahierten Keywords
     */
    private function parse_keywords_response($response) {
        // Zeilenumbrüche normalisieren und in einzelne Zeilen aufteilen
        $lines = preg_split('/\r\n|\r|\n/', $response);
        
        // Leere Zeilen entfernen
        $lines = array_filter($lines, function($line) {
            return !empty(trim($line));
        });
        
        // Nummerierungen, Bulletpoints und andere Formatierungen entfernen
        $keywords = array_map(function($line) {
            // Nummerierungen entfernen (z.B. "1. Keyword" oder "- Keyword")
            $line = preg_replace('/^(\d+\.|\-|\*)\s+/', '', trim($line));
            
            // Anführungszeichen entfernen
            $line = str_replace(array('"', "'"), '', $line);
            
            return trim($line);
        }, $lines);
        
        return $keywords;
    }
    
    /**
     * Inhaltsempfehlungen für einen Inhalt generieren
     * 
     * @param int    $post_id   Die Post-ID
     * @param string $keyword   Das Fokus-Keyword
     * @param array  $options   Zusätzliche Optionen
     * @return array|WP_Error  Die generierten Empfehlungen oder ein Fehler
     */
    public function get_content_recommendations($post_id, $keyword = '', $options = array()) {
        // Post abrufen
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Inhalt nicht gefunden.', 'alenseo'));
        }
        
        // Keyword abrufen wenn nicht angegeben
        if (empty($keyword)) {
            $keyword = get_post_meta($post_id, 'alenseo_focus_keywords', true);
            
            // Wenn immer noch leer, ersten Teil des Titels verwenden
            if (empty($keyword)) {
                $keyword = $post->post_title;
            }
        }
        
        // Claude API-Instanz prüfen
        if (!$this->claude_api || !$this->claude_api->is_api_configured()) {
            return new WP_Error('api_not_configured', __('Claude API ist nicht konfiguriert.', 'alenseo'));
        }
        
        // Optionen vorbereiten
        $options = wp_parse_args($options, array(
            'tone' => 'professional',
            'level' => 'moderate'
        ));
        
        // Prompt erstellen
        $prompt = $this->build_content_recommendations_prompt($post, $keyword, $options);
        
        try {
            // API-Anfrage stellen
            $response = $this->claude_api->generate_text($prompt);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            // Empfehlungen parsen
            $recommendations = $this->parse_recommendations_response($response);
            
            // Speichern (optional)
            update_post_meta($post_id, '_alenseo_content_recommendations', $recommendations);
            
            return $recommendations;
            
        } catch (Exception $e) {
            return new WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Empfehlungen aus der API-Antwort parsen
     *
     * @param string $response  Die API-Antwort
     * @return array Die extrahierten Empfehlungen
     */
    private function parse_recommendations_response($response) {
        // Grundstruktur für die Empfehlungen
        $recommendations = array(
            'keyword_usage' => array(),
            'headings' => array(),
            'content_structure' => array(),
            'readability' => array(),
            'general' => array()
        );
        
        // Zeilenumbrüche normalisieren und in einzelne Zeilen aufteilen
        $lines = preg_split('/\r\n|\r|\n/', $response);
        
        $current_section = 'general';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Abschnittsüberschriften identifizieren
            if (strpos($line, 'Keyword-Nutzung:') !== false) {
                $current_section = 'keyword_usage';
                continue;
            } elseif (strpos($line, 'Überschriften:') !== false) {
                $current_section = 'headings';
                continue;
            } elseif (strpos($line, 'Inhaltsstruktur:') !== false) {
                $current_section = 'content_structure';
                continue;
            } elseif (strpos($line, 'Lesbarkeit:') !== false) {
                $current_section = 'readability';
                continue;
            } elseif (strpos($line, 'Allgemeine Empfehlungen:') !== false) {
                $current_section = 'general';
                continue;
            }
            
            // Leere Zeilen überspringen
            if (empty($line)) {
                continue;
            }
            
            // Formatierungen wie Bulletpoints bereinigen
            $line = preg_replace('/^(\-|\*|\d+\.)\s+/', '', $line);
            
            // Zeile zum aktuellen Abschnitt hinzufügen, wenn nicht leer
            if (!empty($line)) {
                $recommendations[$current_section][] = $line;
            }
        }
        
        return $recommendations;
    }
}
