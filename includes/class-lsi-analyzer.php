<?php
namespace Alenseo;

/**
 * LSI-Keyword-Analyzer für Alenseo SEO
 *
 * Diese Klasse analysiert semantisch verwandte Keywords
 * 
 * @link       https://www.imponi.ch
 * @since      2.0.4
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Alenseo_LSI_Analyzer {
    
    /**
     * Claude API-Instanz
     * 
     * @var Alenseo_Claude_API
     */
    private $claude_api;
    
    /**
     * Cache-Dauer in Sekunden
     * 
     * @var int
     */
    private $cache_expiry = 86400; // 24 Stunden
    
    /**
     * Konstruktor
     */
    public function __construct() {
        $this->claude_api = new Alenseo_Claude_API();
        
        // Cache-Dauer aus Einstellungen laden
        $settings = get_option('alenseo_settings', array());
        if (isset($settings['lsi_cache_expiry'])) {
            $this->cache_expiry = intval($settings['lsi_cache_expiry']);
        }
    }
    
    /**
     * LSI-Keywords für ein Hauptkeyword analysieren
     * 
     * @param string $keyword Das Hauptkeyword
     * @param string $content Der zu analysierende Inhalt
     * @return array Array mit LSI-Keywords und Relevanz
     */
    public function analyze_lsi_keywords($keyword, $content = '') {
        // Cache-Check
        $cache_key = 'alenseo_lsi_' . md5($keyword . $content);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Claude API für LSI-Analyse nutzen
        $prompt = $this->build_lsi_prompt($keyword, $content);
        $response = $this->claude_api->generate_text($prompt);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        // LSI-Keywords aus der Antwort extrahieren
        $lsi_keywords = $this->parse_lsi_response($response);
        
        // Ergebnisse cachen
        set_transient($cache_key, $lsi_keywords, $this->cache_expiry);
        
        return $lsi_keywords;
    }
    
    /**
     * Prompt für LSI-Analyse erstellen
     * 
     * @param string $keyword Das Hauptkeyword
     * @param string $content Der zu analysierende Inhalt
     * @return string Der Prompt für die Claude API
     */
    private function build_lsi_prompt($keyword, $content) {
        $prompt = "Analysiere das folgende Keyword und den Inhalt auf semantisch verwandte Keywords (LSI-Keywords).\n\n";
        $prompt .= "Hauptkeyword: " . $keyword . "\n\n";
        
        if (!empty($content)) {
            $prompt .= "Inhalt:\n" . $content . "\n\n";
        }
        
        $prompt .= "Bitte liste die wichtigsten LSI-Keywords mit ihrer Relevanz (0-100) auf. ";
        $prompt .= "Berücksichtige dabei:\n";
        $prompt .= "1. Semantische Ähnlichkeit zum Hauptkeyword\n";
        $prompt .= "2. Häufigkeit im Inhalt (falls vorhanden)\n";
        $prompt .= "3. Suchvolumen und Wettbewerb\n";
        $prompt .= "4. Nutzerintention\n\n";
        $prompt .= "Formatiere die Ausgabe als JSON mit folgender Struktur:\n";
        $prompt .= "{\n";
        $prompt .= "  \"keywords\": [\n";
        $prompt .= "    {\"keyword\": \"keyword1\", \"relevance\": 85, \"type\": \"synonym\"},\n";
        $prompt .= "    {\"keyword\": \"keyword2\", \"relevance\": 75, \"type\": \"related\"}\n";
        $prompt .= "  ]\n";
        $prompt .= "}";
        
        return $prompt;
    }
    
    /**
     * LSI-Antwort parsen
     * 
     * @param string $response Die Antwort von Claude
     * @return array Die geparsten LSI-Keywords
     */
    private function parse_lsi_response($response) {
        $result = array(
            'success' => true,
            'keywords' => array()
        );
        
        try {
            // JSON aus der Antwort extrahieren
            preg_match('/\{.*\}/s', $response, $matches);
            if (empty($matches)) {
                throw new \Exception('Keine gültige JSON-Antwort gefunden');
            }
            
            $json = json_decode($matches[0], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON-Parsing-Fehler: ' . json_last_error_msg());
            }
            
            if (!isset($json['keywords']) || !is_array($json['keywords'])) {
                throw new \Exception('Ungültige JSON-Struktur');
            }
            
            $result['keywords'] = $json['keywords'];
            
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * LSI-Keywords im Inhalt analysieren
     * 
     * @param string $content Der zu analysierende Inhalt
     * @param array $lsi_keywords Die LSI-Keywords
     * @return array Analyseergebnis
     */
    public function analyze_content_for_lsi($content, $lsi_keywords) {
        $result = array(
            'success' => true,
            'analysis' => array()
        );
        
        foreach ($lsi_keywords as $keyword) {
            $keyword_text = $keyword['keyword'];
            $count = substr_count(strtolower($content), strtolower($keyword_text));
            
            $result['analysis'][] = array(
                'keyword' => $keyword_text,
                'relevance' => $keyword['relevance'],
                'type' => $keyword['type'],
                'count' => $count,
                'density' => $this->calculate_density($content, $keyword_text)
            );
        }
        
        return $result;
    }
    
    /**
     * Keyword-Dichte berechnen
     * 
     * @param string $content Der Inhalt
     * @param string $keyword Das Keyword
     * @return float Die Keyword-Dichte in Prozent
     */
    private function calculate_density($content, $keyword) {
        $word_count = str_word_count(strip_tags($content));
        if ($word_count === 0) {
            return 0;
        }
        
        $keyword_count = substr_count(strtolower($content), strtolower($keyword));
        return ($keyword_count / $word_count) * 100;
    }
    
    /**
     * LSI-Keywords für einen Post analysieren
     * 
     * @param int $post_id Die Post-ID
     * @return array Analyseergebnis
     */
    public function analyze_post_lsi($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'error' => 'Post nicht gefunden'
            );
        }
        
        // Hauptkeyword abrufen
        $main_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
        if (empty($main_keyword)) {
            return array(
                'success' => false,
                'error' => 'Kein Hauptkeyword gesetzt'
            );
        }
        
        // LSI-Keywords analysieren
        $lsi_result = $this->analyze_lsi_keywords($main_keyword, $post->post_content);
        if (!$lsi_result['success']) {
            return $lsi_result;
        }
        
        // LSI-Keywords im Inhalt analysieren
        $content_analysis = $this->analyze_content_for_lsi($post->post_content, $lsi_result['keywords']);
        
        // Ergebnisse speichern
        update_post_meta($post_id, '_alenseo_lsi_keywords', $lsi_result['keywords']);
        update_post_meta($post_id, '_alenseo_lsi_analysis', $content_analysis);
        
        return array(
            'success' => true,
            'lsi_keywords' => $lsi_result['keywords'],
            'content_analysis' => $content_analysis
        );
    }
} 