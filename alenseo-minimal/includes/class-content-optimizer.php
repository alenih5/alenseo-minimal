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
     * Initialisierung der Klasse
     */
    public function __construct() {
        // Claude API-Klasse laden, wenn sie existiert
        if (class_exists('Alenseo_Claude_API')) {
            $this->claude_api = new Alenseo_Claude_API();
        } else {
            error_log('Alenseo_Content_Optimizer: Claude API-Klasse nicht gefunden.');
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
}
