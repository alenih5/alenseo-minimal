<?php
namespace SEOAI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO-Analyse-Engine
 */
class SEOAnalyzer {
    /**
     * Instanz
     */
    private static $instance = null;
    /**
     * AI Connector
     */
    private $connector;
    /**
     * Database
     */
    private $db;

    /**
     * Konstruktor
     */
    private function __construct() {
        $this->connector = AI\Connector::get_instance();
        $this->db = Database::get_instance();
    }

    /**
     * Instanz holen
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Analyse eines Beitrags durchführen
     * @param int $post_id
     * @return array
     */
    public function analyze_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }
        $content = $post->post_content;
        $title = $post->post_title;
        $excerpt = $post->post_excerpt;
        $keywords = get_post_meta($post_id, '_seo_ai_keywords', true) ?? [];

        // Content analysieren
        $content_data = $this->connector->analyze_content($content);
        // Meta-Titel analysieren
        $title_data = $this->connector->analyze_meta_title($title, $keywords[0] ?? '');
        // Meta-Beschreibung analysieren
        $desc_data = $this->connector->analyze_meta_description($excerpt ?: wp_trim_words($content, 55), $keywords[0] ?? '');
        // Keyword-Analyse
        $suggestions = $this->connector->suggest_keywords($content);

        $result = [
            'content_analysis' => $content_data,
            'title_analysis'   => $title_data,
            'description_analysis' => $desc_data,
            'keyword_suggestions'  => $suggestions,
            'analyzed_at' => current_time('mysql')
        ];

        // Daten speichern
        $this->db->save_seo_data($post_id, $result);
        return $result;
    }

    /**
     * SEO-Daten abrufen
     */
    public function get_analysis($post_id) {
        return $this->db->get_seo_data($post_id);
    }
} 