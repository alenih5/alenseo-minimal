<?php
namespace SEOAI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta-Box für SEO AI Funktionen im Post-Editor
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
        add_meta_box(
            'seo-ai-meta-box',
            __('SEO AI Master', 'seo-ai-master'),
            [$this, 'render_meta_box'],
            ['post', 'page'],
            'side',
            'high'
        );
    }

    public function enqueue_assets($hook) {
        if (!in_array($hook, ['post.php','post-new.php'], true)) {
            return;
        }
        wp_enqueue_style(
            'seo-ai-meta-box',
            SEO_AI_MASTER_URL . 'assets/css/meta-box.css',
            [],
            SEO_AI_MASTER_VERSION
        );
        wp_enqueue_script(
            'seo-ai-meta-box',
            SEO_AI_MASTER_URL . 'assets/js/meta-box.js',
            ['jquery'],
            SEO_AI_MASTER_VERSION,
            true
        );
        wp_localize_script('seo-ai-meta-box', 'seoAiMeta', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('seo_ai_meta'),
            'i18n'    => [
                'analyze'      => __('Analysieren', 'seo-ai-master'),
                'generate'     => __('Generieren', 'seo-ai-master'),
                'optimize'     => __('Optimieren', 'seo-ai-master'),
                'keywords'     => __('Keywords vorschlagen', 'seo-ai-master'),
                'error'        => __('Fehler', 'seo-ai-master'),
                'loading'      => __('Bitte warten...', 'seo-ai-master')
            ]
        ]);
    }

    public function render_meta_box($post) {
        $seo_data    = get_post_meta($post->ID, '_seo_ai_data', true) ?: [];
        $meta_title  = get_post_meta($post->ID, '_seo_ai_title', true) ?: '';
        $meta_desc   = get_post_meta($post->ID, '_seo_ai_description', true) ?: '';
        $keywords    = get_post_meta($post->ID, '_seo_ai_keywords', true) ?: [];
        include SEO_AI_MASTER_PATH . 'templates/admin/meta-box.php';
    }

    public function ajax_analyze() {
        check_ajax_referer('seo_ai_meta','nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));}
        $post_id = intval($_POST['post_id']);
        try {
            $data = $this->analyzer->analyze_post($post_id);
            wp_send_json_success($data);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_generate_title() {
        check_ajax_referer('seo_ai_meta','nonce');
        $post_id = intval($_POST['post_id']);
        $content = get_post_field('post_content',$post_id);
        try {
            $title = $this->connector->generate_meta_title($content, '');
            update_post_meta($post_id,'_seo_ai_title',$title);
            wp_send_json_success(['title'=>$title]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_generate_description() {
        check_ajax_referer('seo_ai_meta','nonce');
        $post_id = intval($_POST['post_id']);
        $content = get_post_field('post_content',$post_id);
        try {
            $desc = $this->connector->generate_meta_description($content, '');
            update_post_meta($post_id,'_seo_ai_description',$desc);
            wp_send_json_success(['description'=>$desc]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_optimize_content() {
        check_ajax_referer('seo_ai_meta','nonce');
        $post_id = intval($_POST['post_id']);
        try {
            $optimized = $this->connector->optimize_content(get_post_field('post_content',$post_id));
            wp_send_json_success(['content'=>$optimized]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_suggest_keywords() {
        check_ajax_referer('seo_ai_meta','nonce');
        $post_id = intval($_POST['post_id']);
        try {
            $list = $this->connector->suggest_keywords(get_post_field('post_content',$post_id));
            update_post_meta($post_id,'_seo_ai_keywords',$list);
            wp_send_json_success(['keywords'=>$list]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}

new Meta_Box(); 