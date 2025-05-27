<?php
namespace SEOAI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta-Box für SEO AI Funktionen im Post-Editor (WordPress-optimiert)
 */
class Meta_Box {
    private $connector;
    private $analyzer;

    public function __construct() {
        $this->connector = \SEOAI\AI\Connector::get_instance();
        $this->analyzer  = \SEOAI\SEOAnalyzer::get_instance();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_seo_ai_meta_analyze',   [$this, 'ajax_analyze']);
        add_action('wp_ajax_seo_ai_meta_title',     [$this, 'ajax_generate_title']);
        add_action('wp_ajax_seo_ai_meta_desc',      [$this, 'ajax_generate_description']);
        add_action('wp_ajax_seo_ai_meta_optimize',  [$this, 'ajax_optimize_content']);
        add_action('wp_ajax_seo_ai_meta_keywords',  [$this, 'ajax_suggest_keywords']);
    }

    public function add_meta_box() {
        $post_types = get_post_types(['public' => true], 'names');
        
        // Erweiterte Post-Type-Unterstützung
        $supported_types = apply_filters('seo_ai_supported_post_types', ['post', 'page']);
        
        foreach ($supported_types as $post_type) {
            if (post_type_exists($post_type)) {
                add_meta_box(
                    'seo-ai-meta-box',
                    __('SEO AI Master', 'seo-ai-master'),
                    [$this, 'render_meta_box'],
                    $post_type,
                    'side',
                    'high'
                );
            }
        }
    }

    public function enqueue_assets($hook) {
        // Nur auf Post-Edit-Seiten laden
        if (!in_array($hook, ['post.php','post-new.php'], true)) {
            return;
        }
        
        // Prüfen ob unser Meta-Box auf dieser Seite ist
        global $post;
        if (!$post || !in_array($post->post_type, ['post', 'page'], true)) {
            return;
        }
        
        // Font Awesome (falls nicht bereits geladen)
        if (!wp_style_is('seo-ai-fa', 'enqueued')) {
            wp_enqueue_style(
                'seo-ai-fa',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                [],
                '6.4.0'
            );
        }
        
        // Meta Box CSS (mit Cache-Busting)
        wp_enqueue_style(
            'seo-ai-meta-box',
            SEO_AI_MASTER_URL . 'assets/css/meta-box.css',
            ['seo-ai-fa'],
            SEO_AI_MASTER_VERSION . '-' . filemtime(SEO_AI_MASTER_PATH . 'assets/css/meta-box.css')
        );
        
        // Meta Box JavaScript (mit verbesserter Abhängigkeitsverwaltung)
        wp_enqueue_script(
            'seo-ai-meta-box',
            SEO_AI_MASTER_URL . 'assets/js/meta-box.js',
            ['jquery', 'wp-api'],
            SEO_AI_MASTER_VERSION . '-' . filemtime(SEO_AI_MASTER_PATH . 'assets/js/meta-box.js'),
            true
        );
        
        // JavaScript-Lokalisierung (erweitert)
        wp_localize_script('seo-ai-meta-box', 'seoAiMeta', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('seo_ai_meta'),
            'postId'  => $post ? $post->ID : 0,
            'postType' => $post ? $post->post_type : 'post',
            'restUrl' => rest_url('wp/v2/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'analyze'      => __('Analysieren', 'seo-ai-master'),
                'generate'     => __('Generieren', 'seo-ai-master'),
                'optimize'     => __('Optimieren', 'seo-ai-master'),
                'keywords'     => __('Keywords vorschlagen', 'seo-ai-master'),
                'error'        => __('Fehler', 'seo-ai-master'),
                'loading'      => __('Bitte warten...', 'seo-ai-master'),
                'success'      => __('Erfolgreich', 'seo-ai-master'),
                'analyzing'    => __('Analysiere...', 'seo-ai-master'),
                'generating'   => __('Generiere...', 'seo-ai-master'),
                'optimizing'   => __('Optimiere...', 'seo-ai-master'),
                'timeout'      => __('Zeitüberschreitung', 'seo-ai-master'),
                'network_error' => __('Netzwerkfehler', 'seo-ai-master'),
                'invalid_response' => __('Ungültige Antwort', 'seo-ai-master')
            ]
        ]);
    }

    public function render_meta_box($post) {
        // Nonce für Sicherheit
        wp_nonce_field('seo_ai_meta_box', 'seo_ai_meta_box_nonce');
        
        // SEO-Daten aus Post Meta laden
        $seo_data    = get_post_meta($post->ID, '_seo_ai_data', true) ?: [];
        $meta_title  = get_post_meta($post->ID, '_seo_ai_title', true) ?: '';
        $meta_desc   = get_post_meta($post->ID, '_seo_ai_description', true) ?: '';
        $keywords    = get_post_meta($post->ID, '_seo_ai_keywords', true) ?: [];
        $score       = get_post_meta($post->ID, '_seo_ai_score', true) ?: 0;
        $last_analyzed = get_post_meta($post->ID, '_seo_ai_last_analyzed', true) ?: '';
        
        // Sanitize Daten
        $meta_title = sanitize_text_field($meta_title);
        $meta_desc = sanitize_textarea_field($meta_desc);
        $score = max(0, min(100, intval($score)));
        
        if (is_array($keywords)) {
            $keywords = array_map('sanitize_text_field', $keywords);
        } else {
            $keywords = [];
        }
        
        // Template-Variablen für bessere Übersicht
        $template_vars = [
            'post_id' => $post->ID,
            'post_type' => $post->post_type,
            'seo_data' => $seo_data,
            'meta_title' => $meta_title,
            'meta_desc' => $meta_desc,
            'keywords' => $keywords,
            'score' => $score,
            'last_analyzed' => $last_analyzed,
            'has_content' => !empty($post->post_content),
            'content_length' => strlen($post->post_content),
            'title_length' => strlen($meta_title),
            'desc_length' => strlen($meta_desc)
        ];
        
        // Template einbinden
        include SEO_AI_MASTER_PATH . 'templates/admin/meta-box.php';
    }

    /**
     * AJAX: Post analysieren (verbessert)
     */
    public function ajax_analyze() {
        // Verbesserte Nonce-Prüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'seo_ai_meta')) {
            wp_send_json_error(__('Sicherheitsfehler: Ungültiger Nonce', 'seo-ai-master'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(__('Ungültige Post-ID', 'seo-ai-master'));
        }
        
        // Prüfen ob Benutzer den Post bearbeiten kann
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Keine Berechtigung für diesen Post', 'seo-ai-master'));
        }
        
        try {
            $data = $this->analyzer->analyze_post($post_id);
            
            // Zusätzliche Metadaten speichern
            update_post_meta($post_id, '_seo_ai_last_analyzed', current_time('mysql'));
            
            wp_send_json_success($data);
        } catch (\Exception $e) {
            error_log('SEO AI Meta Box Analyze Error: ' . $e->getMessage());
            wp_send_json_error(__('Analysefehler: ', 'seo-ai-master') . $e->getMessage());
        }
    }

    /**
     * AJAX: Meta Title generieren (verbessert)
     */
    public function ajax_generate_title() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'seo_ai_meta')) {
            wp_send_json_error(__('Sicherheitsfehler', 'seo-ai-master'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(__('Ungültige Post-ID', 'seo-ai-master'));
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Keine Berechtigung für diesen Post', 'seo-ai-master'));
        }
        
        $content = get_post_field('post_content', $post_id);
        $post_title = get_post_field('post_title', $post_id);
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        
        if (empty($content) && empty($post_title)) {
            wp_send_json_error(__('Kein Inhalt zum Analysieren verfügbar', 'seo-ai-master'));
        }
        
        try {
            // Fallback auf Post-Titel wenn kein Content
            $source_text = !empty($content) ? $content : $post_title;
            $title = $this->connector->generate_meta_title($source_text, $keywords);
            
            // Validierung des generierten Titels
            if (empty($title) || strlen($title) < 10) {
                throw new \Exception(__('Generierter Titel ist zu kurz oder leer', 'seo-ai-master'));
            }
            
            if (strlen($title) > 60) {
                $title = substr($title, 0, 57) . '...';
            }
            
            // Meta-Daten aktualisieren
            update_post_meta($post_id, '_seo_ai_title', sanitize_text_field($title));
            update_post_meta($post_id, '_seo_ai_title_generated', current_time('mysql'));
            
            wp_send_json_success([
                'title' => $title,
                'length' => strlen($title),
                'message' => __('Meta Title erfolgreich generiert', 'seo-ai-master')
            ]);
            
        } catch (\Exception $e) {
            error_log('SEO AI Meta Box Title Generation Error: ' . $e->getMessage());
            wp_send_json_error(__('Fehler bei der Titel-Generierung: ', 'seo-ai-master') . $e->getMessage());
        }
    }

    /**
     * AJAX: Meta Description generieren (verbessert)
     */
    public function ajax_generate_description() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'seo_ai_meta')) {
            wp_send_json_error(__('Sicherheitsfehler', 'seo-ai-master'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(__('Ungültige Post-ID', 'seo-ai-master'));
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Keine Berechtigung für diesen Post', 'seo-ai-master'));
        }
        
        $content = get_post_field('post_content', $post_id);
        $post_title = get_post_field('post_title', $post_id);
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        
        if (empty($content) && empty($post_title)) {
            wp_send_json_error(__('Kein Inhalt zum Analysieren verfügbar', 'seo-ai-master'));
        }
        
        try {
            $source_text = !empty($content) ? $content : $post_title;
            $desc = $this->connector->generate_meta_description($source_text, $keywords);
            
            // Validierung der Description
            if (empty($desc) || strlen($desc) < 50) {
                throw new \Exception(__('Generierte Beschreibung ist zu kurz', 'seo-ai-master'));
            }
            
            if (strlen($desc) > 160) {
                $desc = substr($desc, 0, 157) . '...';
            }
            
            // Meta-Daten aktualisieren
            update_post_meta($post_id, '_seo_ai_description', sanitize_textarea_field($desc));
            update_post_meta($post_id, '_seo_ai_description_generated', current_time('mysql'));
            
            wp_send_json_success([
                'description' => $desc,
                'length' => strlen($desc),
                'message' => __('Meta Description erfolgreich generiert', 'seo-ai-master')
            ]);
            
        } catch (\Exception $e) {
            error_log('SEO AI Meta Box Description Generation Error: ' . $e->getMessage());
            wp_send_json_error(__('Fehler bei der Description-Generierung: ', 'seo-ai-master') . $e->getMessage());
        }
    }

    /**
     * AJAX: Content optimieren (verbessert)
     */
    public function ajax_optimize_content() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'seo_ai_meta')) {
            wp_send_json_error(__('Sicherheitsfehler', 'seo-ai-master'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(__('Ungültige Post-ID', 'seo-ai-master'));
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Keine Berechtigung für diesen Post', 'seo-ai-master'));
        }
        
        $content = get_post_field('post_content', $post_id);
        
        if (empty($content)) {
            wp_send_json_error(__('Kein Inhalt zum Optimieren verfügbar', 'seo-ai-master'));
        }
        
        try {
            $optimized = $this->connector->optimize_content($content);
            
            if (empty($optimized)) {
                throw new \Exception(__('Optimierung lieferte keinen Inhalt', 'seo-ai-master'));
            }
            
            // Optimierung nicht automatisch speichern - nur zurückgeben
            wp_send_json_success([
                'content' => $optimized,
                'original_length' => strlen($content),
                'optimized_length' => strlen($optimized),
                'message' => __('Content erfolgreich optimiert', 'seo-ai-master')
            ]);
            
        } catch (\Exception $e) {
            error_log('SEO AI Meta Box Content Optimization Error: ' . $e->getMessage());
            wp_send_json_error(__('Fehler bei der Content-Optimierung: ', 'seo-ai-master') . $e->getMessage());
        }
    }

    /**
     * AJAX: Keywords vorschlagen (verbessert)
     */
    public function ajax_suggest_keywords() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'seo_ai_meta')) {
            wp_send_json_error(__('Sicherheitsfehler', 'seo-ai-master'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(__('Ungültige Post-ID', 'seo-ai-master'));
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(__('Keine Berechtigung für diesen Post', 'seo-ai-master'));
        }
        
        $content = get_post_field('post_content', $post_id);
        $post_title = get_post_field('post_title', $post_id);
        
        if (empty($content) && empty($post_title)) {
            wp_send_json_error(__('Kein Inhalt für Keyword-Analyse verfügbar', 'seo-ai-master'));
        }
        
        try {
            $source_text = !empty($content) ? $content : $post_title;
            $list = $this->connector->suggest_keywords($source_text);
            
            // Keywords validieren und bereinigen
            if (!is_array($list)) {
                $list = [];
            }
            
            $list = array_filter(array_map('sanitize_text_field', $list));
            $list = array_slice($list, 0, 10); // Max 10 Keywords
            
            // Meta-Daten aktualisieren
            update_post_meta($post_id, '_seo_ai_keywords', $list);
            update_post_meta($post_id, '_seo_ai_keywords_generated', current_time('mysql'));
            
            wp_send_json_success([
                'keywords' => $list,
                'count' => count($list),
                'message' => sprintf(__('%d Keywords erfolgreich generiert', 'seo-ai-master'), count($list))
            ]);
            
        } catch (\Exception $e) {
            error_log('SEO AI Meta Box Keyword Suggestion Error: ' . $e->getMessage());
            wp_send_json_error(__('Fehler bei der Keyword-Generierung: ', 'seo-ai-master') . $e->getMessage());
        }
    }
}

new Meta_Box();