<?php
namespace SEOAI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Verwaltung aller URLs und deren SEO-Status
 */
class URLManager {
    /**
     * Instanz
     */
    private static $instance = null;

    /**
     * Konstruktor
     */
    private function __construct() {
        // AJAX Hooks für URL Management
        add_action('wp_ajax_seo_ai_url_bulk_analyze', [$this, 'ajax_bulk_analyze']);
        add_action('wp_ajax_seo_ai_url_delete', [$this, 'ajax_delete_url']);
        add_action('wp_ajax_seo_ai_url_toggle_monitoring', [$this, 'ajax_toggle_monitoring']);
        add_action('wp_ajax_seo_ai_url_export', [$this, 'ajax_export_urls']);
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
     * URL-Liste für Tabellenabfrage mit erweiterten Filteroptionen
     * @param array $args
     * @return array
     */
    public function get_urls($args = []) {
        global $wpdb;

        $defaults = [
            'posts_per_page' => 20,
            'paged' => 1,
            'search' => '',
            'post_type' => 'all',
            'seo_status' => 'all',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_missing' => false,
            'score_range' => [0, 100]
        ];

        $args = wp_parse_args($args, $defaults);

        // Basis Query
        $query_args = [
            'post_type' => $this->get_allowed_post_types($args['post_type']),
            'posts_per_page' => min(100, max(1, intval($args['posts_per_page']))),
            'paged' => max(1, intval($args['paged'])),
            'post_status' => 'publish',
            'orderby' => $this->sanitize_orderby($args['orderby']),
            'order' => in_array(strtoupper($args['order']), ['ASC', 'DESC']) ? strtoupper($args['order']) : 'DESC',
            'meta_query' => [],
            'no_found_rows' => false,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false
        ];

        // Suchfilter
        if (!empty($args['search'])) {
            $query_args['s'] = sanitize_text_field($args['search']);
        }

        // SEO Status Filter
        if ($args['seo_status'] !== 'all') {
            switch ($args['seo_status']) {
                case 'analyzed':
                    $query_args['meta_query'][] = [
                        'key' => '_seo_ai_score',
                        'compare' => 'EXISTS'
                    ];
                    break;
                case 'not_analyzed':
                    $query_args['meta_query'][] = [
                        'key' => '_seo_ai_score',
                        'compare' => 'NOT EXISTS'
                    ];
                    break;
                case 'critical':
                    $query_args['meta_query'][] = [
                        'key' => '_seo_ai_score',
                        'value' => 60,
                        'compare' => '<',
                        'type' => 'NUMERIC'
                    ];
                    break;
                case 'good':
                    $query_args['meta_query'][] = [
                        'key' => '_seo_ai_score',
                        'value' => 80,
                        'compare' => '>=',
                        'type' => 'NUMERIC'
                    ];
                    break;
            }
        }

        // Fehlende Meta-Daten Filter
        if ($args['meta_missing']) {
            $query_args['meta_query']['relation'] = 'OR';
            $query_args['meta_query'][] = [
                'key' => '_seo_ai_title',
                'compare' => 'NOT EXISTS'
            ];
            $query_args['meta_query'][] = [
                'key' => '_seo_ai_description',
                'compare' => 'NOT EXISTS'
            ];
        }

        // Score Range Filter
        if ($args['score_range'][0] > 0 || $args['score_range'][1] < 100) {
            $query_args['meta_query'][] = [
                'key' => '_seo_ai_score',
                'value' => $args['score_range'],
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            ];
        }

        // Meta Key für Sortierung
        if (in_array($args['orderby'], ['score', 'meta_title', 'meta_description'])) {
            $meta_keys = [
                'score' => '_seo_ai_score',
                'meta_title' => '_seo_ai_title',
                'meta_description' => '_seo_ai_description'
            ];
            $query_args['meta_key'] = $meta_keys[$args['orderby']];
            $query_args['orderby'] = $args['orderby'] === 'score' ? 'meta_value_num' : 'meta_value';
        }

        return new \WP_Query($query_args);
    }

    /**
     * Erlaubte Post Types basierend auf Filter
     */
    private function get_allowed_post_types($filter) {
        $allowed = ['post', 'page'];
        if (post_type_exists('product')) {
            $allowed[] = 'product';
        }

        if ($filter === 'all') {
            return $allowed;
        }

        return in_array($filter, $allowed) ? [$filter] : ['post'];
    }

    /**
     * OrderBy Parameter sanitizen
     */
    private function sanitize_orderby($orderby) {
        $allowed = ['date', 'title', 'modified', 'score', 'meta_title', 'meta_description', 'menu_order'];
        return in_array($orderby, $allowed) ? $orderby : 'date';
    }

    /**
     * URL-Statistiken für Dashboard-Widgets
     */
    public function get_url_stats() {
        global $wpdb;

        $cache_key = 'seo_ai_url_stats_' . get_current_user_id();
        $stats = get_transient($cache_key);

        if (false === $stats) {
            try {
                // Basis-Statistiken
                $total_posts = wp_count_posts()->publish;
                $total_pages = wp_count_posts('page')->publish;
                $total_products = post_type_exists('product') ? wp_count_posts('product')->publish : 0;
                $total_urls = $total_posts + $total_pages + $total_products;

                // SEO Analysierte URLs
                $analyzed = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                     JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = %s AND p.post_status = 'publish'",
                    '_seo_ai_score'
                )));

                // Durchschnittlicher Score
                $avg_score = floatval($wpdb->get_var($wpdb->prepare(
                    "SELECT AVG(CAST(pm.meta_value AS DECIMAL(5,2)))
                     FROM {$wpdb->postmeta} pm
                     JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = %s 
                     AND p.post_status = 'publish'
                     AND pm.meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'",
                    '_seo_ai_score'
                )));

                // Kritische URLs (Score < 60)
                $critical_urls = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$wpdb->postmeta} pm
                     JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                     WHERE pm.meta_key = %s 
                     AND CAST(pm.meta_value AS DECIMAL(5,2)) < 60
                     AND p.post_status = 'publish'",
                    '_seo_ai_score'
                )));

                // URLs ohne Meta Title
                $missing_titles = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                     WHERE p.post_status = 'publish'
                     AND p.post_type IN ('post', 'page', 'product')
                     AND (pm.meta_value IS NULL OR pm.meta_value = '')",
                    '_seo_ai_title'
                )));

                // URLs ohne Meta Description
                $missing_descriptions = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                     WHERE p.post_status = 'publish'
                     AND p.post_type IN ('post', 'page', 'product')
                     AND (pm.meta_value IS NULL OR pm.meta_value = '')",
                    '_seo_ai_description'
                )));

                $stats = [
                    'total_urls' => $total_urls,
                    'analyzed' => $analyzed,
                    'not_analyzed' => max(0, $total_urls - $analyzed),
                    'avg_score' => max(0, min(100, round($avg_score))),
                    'critical_urls' => $critical_urls,
                    'missing_titles' => $missing_titles,
                    'missing_descriptions' => $missing_descriptions,
                    'breakdown' => [
                        'posts' => $total_posts,
                        'pages' => $total_pages,
                        'products' => $total_products
                    ]
                ];

            } catch (Exception $e) {
                error_log('SEO AI URL Stats Error: ' . $e->getMessage());
                $stats = [
                    'total_urls' => 0,
                    'analyzed' => 0,
                    'not_analyzed' => 0,
                    'avg_score' => 0,
                    'critical_urls' => 0,
                    'missing_titles' => 0,
                    'missing_descriptions' => 0,
                    'breakdown' => ['posts' => 0, 'pages' => 0, 'products' => 0]
                ];
            }

            set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
        }

        return $stats;
    }

    /**
     * AJAX: Bulk-Analyse durchführen
     */
    public function ajax_bulk_analyze() {
        check_ajax_referer('seo_ai_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Keine Berechtigung', 'seo-ai-master'));
        }

        $post_ids = array_map('intval', $_POST['post_ids'] ?? []);
        $results = [];

        foreach ($post_ids as $post_id) {
            if ($post_id > 0 && get_post($post_id)) {
                $result = SEOAnalyzer::get_instance()->analyze_post($post_id);
                $results[] = [
                    'post_id' => $post_id,
                    'success' => !is_wp_error($result),
                    'data' => $result
                ];
            }
        }

        wp_send_json_success([
            'message' => sprintf(__('%d URLs analysiert', 'seo-ai-master'), count($results)),
            'results' => $results
        ]);
    }

    /**
     * AJAX: URL aus Monitoring entfernen
     */
    public function ajax_delete_url() {
        check_ajax_referer('seo_ai_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Keine Berechtigung', 'seo-ai-master'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        
        if ($post_id > 0) {
            // SEO Daten löschen
            delete_post_meta($post_id, '_seo_ai_score');
            delete_post_meta($post_id, '_seo_ai_title');
            delete_post_meta($post_id, '_seo_ai_description');
            delete_post_meta($post_id, '_seo_ai_analysis_date');

            wp_send_json_success([
                'message' => __('URL aus SEO-Monitoring entfernt', 'seo-ai-master')
            ]);
        } else {
            wp_send_json_error(__('Ungültige URL-ID', 'seo-ai-master'));
        }
    }

    /**
     * AJAX: Monitoring für URL umschalten
     */
    public function ajax_toggle_monitoring() {
        check_ajax_referer('seo_ai_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Keine Berechtigung', 'seo-ai-master'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        
        if ($post_id > 0) {
            $current_status = get_post_meta($post_id, '_seo_ai_monitoring', true);
            $new_status = $current_status === 'disabled' ? 'enabled' : 'disabled';
            
            update_post_meta($post_id, '_seo_ai_monitoring', $new_status);

            wp_send_json_success([
                'status' => $new_status,
                'message' => $new_status === 'enabled' 
                    ? __('Monitoring aktiviert', 'seo-ai-master')
                    : __('Monitoring deaktiviert', 'seo-ai-master')
            ]);
        } else {
            wp_send_json_error(__('Ungültige URL-ID', 'seo-ai-master'));
        }
    }

    /**
     * AJAX: URLs exportieren
     */
    public function ajax_export_urls() {
        check_ajax_referer('seo_ai_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'seo-ai-master'));
        }

        $format = sanitize_key($_POST['format'] ?? 'csv');
        $post_ids = array_map('intval', $_POST['post_ids'] ?? []);

        $export_data = $this->prepare_export_data($post_ids);

        switch ($format) {
            case 'csv':
                $this->export_csv($export_data);
                break;
            case 'json':
                $this->export_json($export_data);
                break;
            default:
                wp_send_json_error(__('Unbekanntes Export-Format', 'seo-ai-master'));
        }
    }

    /**
     * Export-Daten vorbereiten
     */
    private function prepare_export_data($post_ids = []) {
        $args = ['posts_per_page' => -1];
        if (!empty($post_ids)) {
            $args['post__in'] = $post_ids;
        }

        $query = $this->get_urls($args);
        $data = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $data[] = [
                    'ID' => $post_id,
                    'Title' => get_the_title(),
                    'URL' => get_permalink(),
                    'Type' => get_post_type(),
                    'SEO_Score' => get_post_meta($post_id, '_seo_ai_score', true) ?: 0,
                    'Meta_Title' => get_post_meta($post_id, '_seo_ai_title', true),
                    'Meta_Description' => get_post_meta($post_id, '_seo_ai_description', true),
                    'Last_Analyzed' => get_post_meta($post_id, '_seo_ai_analysis_date', true),
                    'Modified' => get_the_modified_date('Y-m-d H:i:s')
                ];
            }
            wp_reset_postdata();
        }

        return $data;
    }

    /**
     * CSV Export
     */
    private function export_csv($data) {
        if (empty($data)) {
            wp_send_json_error(__('Keine Daten zum Exportieren', 'seo-ai-master'));
            return;
        }

        $filename = 'seo-urls-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        // BOM für korrekte UTF-8 Darstellung in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($output, array_keys($data[0]));
        
        // Daten
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    /**
     * JSON Export
     */
    private function export_json($data) {
        wp_send_json_success([
            'data' => $data,
            'filename' => 'seo-urls-' . date('Y-m-d-H-i-s') . '.json',
            'count' => count($data)
        ]);
    }

    /**
     * URL-Manager-Seite rendern
     */
    public function render_page() {
        // Security Check
        if (!current_user_can('manage_options')) {
            wp_die(__('Sie haben keine Berechtigung für diese Seite.', 'seo-ai-master'));
        }

        // Template einbinden
        include SEO_AI_MASTER_PATH . 'templates/admin/url-manager.php';
    }
}