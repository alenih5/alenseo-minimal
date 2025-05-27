<?php
namespace SEOAI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content-Optimierung-Engine
 */
class Optimizer {
    /**
     * Instanz
     */
    private static $instance = null;
    /**
     * AI Connector
     */
    private $connector;

    /**
     * Konstruktor
     */
    private function __construct() {
        $this->connector = AI\Connector::get_instance();
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
     * Content optimieren
     * @param int $post_id
     * @return string
     */
    public function optimize_post_content($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }
        $content = $post->post_content;
        // Optimierung über AI-Connector
        $optimized = $this->connector->optimize_content($content);
        // Aktualisieren des Beitrags
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $optimized
        ]);
        return $optimized;
    }
} 