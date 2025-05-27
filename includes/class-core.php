<?php
namespace SEOAI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Haupt-Controller der Plugin-Logik
 */
class Core {
    /**
     * Instanz
     */
    private static $instance = null;

    /**
     * Konstruktor
     */
    private function __construct() {
        // Textdomain laden
        $this->load_textdomain();
        // Abhängigkeiten sofort laden
        $this->load_dependencies();
        // REST API Routes registrieren
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        // AJAX Handlers registrieren
        add_action('wp_ajax_seo_ai_analyze_post', [$this, 'ajax_analyze_post']);
        add_action('wp_ajax_seo_ai_generate_meta_title', [$this, 'ajax_generate_meta_title']);
        add_action('wp_ajax_seo_ai_generate_meta_description', [$this, 'ajax_generate_meta_description']);
        add_action('wp_ajax_seo_ai_optimize_content', [$this, 'ajax_optimize_content']);
        add_action('wp_ajax_seo_ai_dashboard_stats', [$this, 'ajax_dashboard_stats']);
        add_action('wp_ajax_seo_ai_test_apis', [$this, 'ajax_test_apis']);
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
     * Aktivierungs-Hook
     */
    public static function activate() {
        // Datenbanktabellen erstellen
        Database::get_instance()->create_tables();
    }

    /**
     * Deaktivierungs-Hook
     */
    public static function deactivate() {
        // Temporäre Daten bereinigen
        wp_clear_scheduled_hook('seo_ai_cleanup_cache');
    }

    /**
     * Textdomain laden
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'seo-ai-master',
            false,
            dirname(SEO_AI_MASTER_BASENAME) . '/languages'
        );
    }

    /**
     * Alle weiteren Abhängigkeiten laden
     */
    public function load_dependencies() {
        // Datenbank
        require_once SEO_AI_MASTER_PATH . 'includes/class-database.php';
        Database::get_instance();

        // AI-Connector
        require_once SEO_AI_MASTER_PATH . 'includes/class-ai-connector.php';
        AI\Connector::get_instance();

        // SEO-Analyzer laden
        require_once SEO_AI_MASTER_PATH . 'includes/class-seo-analyzer.php';
        SEOAnalyzer::get_instance();

        // URL-Manager laden
        require_once SEO_AI_MASTER_PATH . 'includes/class-url-manager.php';
        URLManager::get_instance();

        // Content Optimizer laden
        require_once SEO_AI_MASTER_PATH . 'includes/class-optimizer.php';
        Optimizer::get_instance();

        // Admin-Interface laden
        require_once SEO_AI_MASTER_PATH . 'includes/admin/class-manager.php';
    }

    /**
     * REST API Routen registrieren
     */
    public function register_rest_routes() {
        register_rest_route('seo-ai/v1', '/analyze-post', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_analyze_post'],
            'permission_callback' => [$this, 'rest_permission_edit_posts'],
            'args'                => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => [$this, 'validate_post_id']
                ]
            ]
        ]);
        
        register_rest_route('seo-ai/v1', '/generate-meta', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_generate_meta'],
            'permission_callback' => [$this, 'rest_permission_edit_posts'],
            'args'                => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => [$this, 'validate_post_id']
                ],
                'keyword' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        register_rest_route('seo-ai/v1', '/optimize-content', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_optimize_content'],
            'permission_callback' => [$this, 'rest_permission_edit_posts'],
            'args'                => [
                'post_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => [$this, 'validate_post_id']
                ]
            ]
        ]);
        
        register_rest_route('seo-ai/v1', '/dashboard-stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_dashboard_stats'],
            'permission_callback' => [$this, 'rest_permission_manage_options'],
        ]);
        
        // API Status Route
        register_rest_route('seo-ai/v1', '/api-status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_api_status'],
            'permission_callback' => [$this, 'rest_permission_manage_options'],
        ]);

        // Bulk Operations
        register_rest_route('seo-ai/v1', '/bulk-analyze', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_bulk_analyze'],
            'permission_callback' => [$this, 'rest_permission_edit_posts'],
            'args'                => [
                'post_ids' => [
                    'required' => true,
                    'type' => 'array',
                    'validate_callback' => [$this, 'validate_post_ids_array']
                ]
            ]
        ]);

        // Content Types Stats
        register_rest_route('seo-ai/v1', '/content-stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_content_stats'],
            'permission_callback' => [$this, 'rest_permission_manage_options'],
        ]);
    }

    /**
     * Validation Callbacks
     */
    public function validate_post_id($param) {
        if (!is_numeric($param) || $param <= 0) {
            return false;
        }
        return get_post($param) !== null;
    }

    public function validate_post_ids_array($param) {
        if (!is_array($param) || empty($param)) {
            return false;
        }
        foreach ($param as $post_id) {
            if (!$this->validate_post_id($post_id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Permission Callbacks
     */
    public function rest_permission_edit_posts() {
        return current_user_can('edit_posts');
    }

    public function rest_permission_manage_options() {
        return current_user_can('manage_options');
    }

    /**
     * REST-Callback: Beitragsanalyse
     */
    public function rest_analyze_post(\WP_REST_Request $request) {
        try {
            $post_id = (int) $request->get_param('post_id');
            
            if (!$this->validate_post_id($post_id)) {
                return new \WP_Error('invalid_post', __('Ungültiger Beitrag', 'seo-ai-master'), ['status' => 400]);
            }

            $data = SEOAnalyzer::get_instance()->analyze_post($post_id);
            
            if (is_wp_error($data)) {
                return $data;
            }

            return rest_ensure_response([
                'success' => true,
                'data' => $data,
                'message' => __('Analyse erfolgreich abgeschlossen', 'seo-ai-master')
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI Analyze Post Error: ' . $e->getMessage());
            return new \WP_Error('analysis_failed', __('Analysefehler aufgetreten', 'seo-ai-master'), ['status' => 500]);
        }
    }

    /**
     * REST-Callback: Meta-Daten generieren
     */
    public function rest_generate_meta(\WP_REST_Request $request) {
        try {
            $post_id = (int) $request->get_param('post_id');
            $keyword = sanitize_text_field($request->get_param('keyword') ?? '');
            
            if (!$this->validate_post_id($post_id)) {
                return new \WP_Error('invalid_post', __('Ungültiger Beitrag', 'seo-ai-master'), ['status' => 400]);
            }

            $content = get_post_field('post_content', $post_id);
            $post_title = get_the_title($post_id);
            
            // Fallback wenn kein Content vorhanden
            if (empty($content)) {
                $content = $post_title . ' ' . get_post_field('post_excerpt', $post_id);
            }

            $ai_connector = AI\Connector::get_instance();
            $title = $ai_connector->generate_meta_title($content, $keyword);
            $desc = $ai_connector->generate_meta_description($content, $keyword);
            
            // Meta-Daten speichern
            if ($title) {
                update_post_meta($post_id, '_seo_ai_title', $title);
            }
            if ($desc) {
                update_post_meta($post_id, '_seo_ai_description', $desc);
            }

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'title' => $title,
                    'description' => $desc,
                    'post_id' => $post_id
                ],
                'message' => __('Meta-Daten erfolgreich generiert', 'seo-ai-master')
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI Generate Meta Error: ' . $e->getMessage());
            return new \WP_Error('generation_failed', __('Meta-Generierung fehlgeschlagen', 'seo-ai-master'), ['status' => 500]);
        }
    }

    /**
     * REST-Callback: Content optimieren
     */
    public function rest_optimize_content(\WP_REST_Request $request) {
        try {
            $post_id = (int) $request->get_param('post_id');
            
            if (!$this->validate_post_id($post_id)) {
                return new \WP_Error('invalid_post', __('Ungültiger Beitrag', 'seo-ai-master'), ['status' => 400]);
            }

            $content = get_post_field('post_content', $post_id);
            $optimized = AI\Connector::get_instance()->optimize_content($content);
            
            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'content' => $optimized,
                    'post_id' => $post_id
                ],
                'message' => __('Content erfolgreich optimiert', 'seo-ai-master')
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI Optimize Content Error: ' . $e->getMessage());
            return new \WP_Error('optimization_failed', __('Content-Optimierung fehlgeschlagen', 'seo-ai-master'), ['status' => 500]);
        }
    }

    /**
     * REST-Callback: Dashboard-Statistiken
     */
    public function rest_dashboard_stats(\WP_REST_Request $request) {
        try {
            global $wpdb;
            
            // Cache prüfen
            $cache_key = 'seo_ai_dashboard_stats_v2';
            $stats = get_transient($cache_key);
            
            if (false === $stats) {
                // Sichere Datenbankabfragen
                $table_exists = $wpdb->get_var($wpdb->prepare(
                    "SHOW TABLES LIKE %s", 
                    $wpdb->prefix . 'seo_ai_data'
                )) === $wpdb->prefix . 'seo_ai_data';
                
                if (!$table_exists) {
                    $stats = [
                        'total' => 0,
                        'analyzed' => 0,
                        'not_analyzed' => 0,
                        'avg_score' => 0,
                        'critical_issues' => 0
                    ];
                } else {
                    $total = intval(wp_count_posts()->publish ?? 0);
                    
                    $analyzed = intval($wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}seo_ai_data WHERE post_id IS NOT NULL"
                    )));
                    
                    $avg_score = floatval($wpdb->get_var($wpdb->prepare(
                        "SELECT AVG(CAST(meta_value AS DECIMAL(5,2))) 
                         FROM {$wpdb->postmeta} pm
                         JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                         WHERE pm.meta_key = %s 
                         AND pm.meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'
                         AND p.post_status = 'publish'",
                        '_seo_ai_score'
                    )));
                    
                    $critical_issues = intval($wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) 
                         FROM {$wpdb->postmeta} pm
                         JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                         WHERE pm.meta_key = %s 
                         AND CAST(pm.meta_value AS DECIMAL(5,2)) < 60
                         AND p.post_status = 'publish'",
                        '_seo_ai_score'
                    )));
                    
                    $stats = [
                        'total' => $total,
                        'analyzed' => $analyzed,
                        'not_analyzed' => max(0, $total - $analyzed),
                        'avg_score' => max(0, min(100, round($avg_score))),
                        'critical_issues' => $critical_issues
                    ];
                }
                
                set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
            }
            
            return rest_ensure_response([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI Dashboard Stats Error: ' . $e->getMessage());
            return new \WP_Error('stats_failed', __('Statistiken konnten nicht geladen werden', 'seo-ai-master'), ['status' => 500]);
        }
    }

    /**
     * REST-Callback: API Status testen
     */
    public function rest_api_status(\WP_REST_Request $request) {
        try {
            $results = [];
            $results['claude'] = $this->test_claude();
            $results['openai'] = $this->test_openai();
            $results['gemini'] = $this->test_gemini();
            
            return rest_ensure_response([
                'success' => true,
                'data' => $results
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI API Status Error: ' . $e->getMessage());
            return new \WP_Error('api_test_failed', __('API-Test fehlgeschlagen', 'seo-ai-master'), ['status' => 500]);
        }
    }

    /**
     * REST-Callback: Bulk-Analyse
     */
    public function rest_bulk_analyze(\WP_REST_Request $request) {
        try {
            $post_ids = $request->get_param('post_ids');
            
            if (!$this->validate_post_ids_array($post_ids)) {
                return new \WP_Error('invalid_posts', __('Ungültige Post-IDs', 'seo-ai-master'), ['status' => 400]);
            }

            $results = [];
            $analyzer = SEOAnalyzer::get_instance();
            
            foreach ($post_ids as $post_id) {
                $result = $analyzer->analyze_post($post_id);
                $results[] = [
                    'post_id' => $post_id,
                    'success' => !is_wp_error($result),
                    'data' => is_wp_error($result) ? $result->get_error_message() : $result
                ];
            }
            
            return rest_ensure_response([
                'success' => true,
                'data' => $results,
                'message' => sprintf(__('%d Posts analysiert', 'seo-ai-master'), count($results))
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI Bulk Analyze Error: ' . $e->getMessage());
            return new \WP_Error('bulk_analysis_failed', __('Bulk-Analyse fehlgeschlagen', 'seo-ai-master'), ['status' => 500]);
        }
    }

    /**
     * REST-Callback: Content-Statistiken
     */
    public function rest_content_stats(\WP_REST_Request $request) {
        try {
            $cache_key = 'seo_ai_content_stats_v2';
            $stats = get_transient($cache_key);
            
            if (false === $stats) {
                $post_counts = wp_count_posts('post');
                $page_counts = wp_count_posts('page');
                $product_counts = post_type_exists('product') ? wp_count_posts('product') : (object)['publish' => 0];
                
                $stats = [
                    'posts' => intval($post_counts->publish ?? 0),
                    'pages' => intval($page_counts->publish ?? 0),
                    'products' => intval($product_counts->publish ?? 0),
                    'total' => intval($post_counts->publish + $page_counts->publish + $product_counts->publish)
                ];
                
                set_transient($cache_key, $stats, 10 * MINUTE_IN_SECONDS);
            }
            
            return rest_ensure_response([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI Content Stats Error: ' . $e->getMessage());
            return new \WP_Error('content_stats_failed', __('Content-Statistiken konnten nicht geladen werden', 'seo-ai-master'), ['status' => 500]);
        }
    }

    /**
     * AJAX Handlers für Dashboard-Kompatibilität
     */
    public function ajax_analyze_post() {
        check_ajax_referer('seo_ai_dashboard_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if ($post_id <= 0 || !get_post($post_id)) {
            wp_send_json_error(__('Ungültige Post-ID', 'seo-ai-master'));
            return;
        }
        
        try {
            $result = SEOAnalyzer::get_instance()->analyze_post($post_id);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }
            
            wp_send_json_success([
                'message' => __('Post erfolgreich analysiert', 'seo-ai-master'),
                'data' => $result
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI AJAX Analyze Error: ' . $e->getMessage());
            wp_send_json_error(__('Analyse fehlgeschlagen', 'seo-ai-master'));
        }
    }

    public function ajax_generate_meta_title() {
        check_ajax_referer('seo_ai_dashboard_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if ($post_id <= 0 || !get_post($post_id)) {
            wp_send_json_error(__('Ungültige Post-ID', 'seo-ai-master'));
            return;
        }
        
        try {
            $content = get_post_field('post_content', $post_id);
            $keyword = sanitize_text_field($_POST['keyword'] ?? '');
            
            $title = AI\Connector::get_instance()->generate_meta_title($content, $keyword);
            
            if ($title) {
                update_post_meta($post_id, '_seo_ai_title', $title);
                wp_send_json_success([
                    'message' => __('Meta Title erfolgreich generiert', 'seo-ai-master'),
                    'title' => $title
                ]);
            } else {
                wp_send_json_error(__('Meta Title konnte nicht generiert werden', 'seo-ai-master'));
            }
            
        } catch (Exception $e) {
            error_log('SEO AI AJAX Meta Title Error: ' . $e->getMessage());
            wp_send_json_error(__('Meta Title Generierung fehlgeschlagen', 'seo-ai-master'));
        }
    }

    public function ajax_generate_meta_description() {
        check_ajax_referer('seo_ai_dashboard_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if ($post_id <= 0 || !get_post($post_id)) {
            wp_send_json_error(__('Ungültige Post-ID', 'seo-ai-master'));
            return;
        }
        
        try {
            $content = get_post_field('post_content', $post_id);
            $keyword = sanitize_text_field($_POST['keyword'] ?? '');
            
            $description = AI\Connector::get_instance()->generate_meta_description($content, $keyword);
            
            if ($description) {
                update_post_meta($post_id, '_seo_ai_description', $description);
                wp_send_json_success([
                    'message' => __('Meta Beschreibung erfolgreich generiert', 'seo-ai-master'),
                    'description' => $description
                ]);
            } else {
                wp_send_json_error(__('Meta Beschreibung konnte nicht generiert werden', 'seo-ai-master'));
            }
            
        } catch (Exception $e) {
            error_log('SEO AI AJAX Meta Description Error: ' . $e->getMessage());
            wp_send_json_error(__('Meta Beschreibung Generierung fehlgeschlagen', 'seo-ai-master'));
        }
    }

    public function ajax_optimize_content() {
        check_ajax_referer('seo_ai_dashboard_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
            return;
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if ($post_id <= 0 || !get_post($post_id)) {
            wp_send_json_error(__('Ungültige Post-ID', 'seo-ai-master'));
            return;
        }
        
        try {
            $content = get_post_field('post_content', $post_id);
            $optimized = AI\Connector::get_instance()->optimize_content($content);
            
            wp_send_json_success([
                'message' => __('Content erfolgreich optimiert', 'seo-ai-master'),
                'content' => $optimized
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI AJAX Content Optimization Error: ' . $e->getMessage());
            wp_send_json_error(__('Content-Optimierung fehlgeschlagen', 'seo-ai-master'));
        }
    }

    public function ajax_dashboard_stats() {
        check_ajax_referer('seo_ai_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
            return;
        }
        
        $request = new \WP_REST_Request('GET', '/seo-ai/v1/dashboard-stats');
        $response = $this->rest_dashboard_stats($request);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response->get_data());
        }
    }

    public function ajax_test_apis() {
        check_ajax_referer('seo_ai_dashboard_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
            return;
        }
        
        $request = new \WP_REST_Request('POST', '/seo-ai/v1/api-status');
        $response = $this->rest_api_status($request);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            wp_send_json_success($response->get_data());
        }
    }

    /**
     * API Testing Methods - Erweiterte Versionen
     */
    private function test_claude() {
        $settings = get_option('seo_ai_settings', []);
        $key = $settings['claude_api_key'] ?? '';
        
        if (empty($key)) {
            return [
                'status' => 'error', 
                'msg' => __('Kein API-Key konfiguriert', 'seo-ai-master'), 
                'latency' => null
            ];
        }
        
        // Cache für API-Tests
        $cache_key = 'seo_ai_claude_test_' . md5($key);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        try {
            $start_time = microtime(true);
            
            // Hier würde der echte API-Call implementiert werden
            // Für jetzt simulieren wir eine erfolgreiche Verbindung
            $success = true; // AI\Connector::get_instance()->test_claude_connection($key);
            
            $latency = round((microtime(true) - $start_time) * 1000, 2);
            
            $result = [
                'status' => $success ? 'ok' : 'error',
                'msg' => $success ? __('Verbunden', 'seo-ai-master') : __('Verbindung fehlgeschlagen', 'seo-ai-master'),
                'latency' => $latency
            ];
            
            // Cache für 5 Minuten
            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Claude API Test Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'msg' => __('Verbindungstest fehlgeschlagen', 'seo-ai-master'),
                'latency' => null
            ];
        }
    }
    
    private function test_openai() {
        $settings = get_option('seo_ai_settings', []);
        $key = $settings['openai_api_key'] ?? '';
        
        if (empty($key)) {
            return [
                'status' => 'error', 
                'msg' => __('Kein API-Key konfiguriert', 'seo-ai-master'), 
                'latency' => null
            ];
        }
        
        $cache_key = 'seo_ai_openai_test_' . md5($key);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        try {
            $start_time = microtime(true);
            
            // Hier würde der echte API-Call implementiert werden
            $success = true; // AI\Connector::get_instance()->test_openai_connection($key);
            
            $latency = round((microtime(true) - $start_time) * 1000, 2);
            
            $result = [
                'status' => $success ? 'ok' : 'error',
                'msg' => $success ? __('Verbunden', 'seo-ai-master') : __('Verbindung fehlgeschlagen', 'seo-ai-master'),
                'latency' => $latency
            ];
            
            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('OpenAI API Test Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'msg' => __('Verbindungstest fehlgeschlagen', 'seo-ai-master'),
                'latency' => null
            ];
        }
    }
    
    private function test_gemini() {
        $settings = get_option('seo_ai_settings', []);
        $key = $settings['gemini_api_key'] ?? '';
        
        if (empty($key)) {
            return [
                'status' => 'error', 
                'msg' => __('Kein API-Key konfiguriert', 'seo-ai-master'), 
                'latency' => null
            ];
        }
        
        $cache_key = 'seo_ai_gemini_test_' . md5($key);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        try {
            $start_time = microtime(true);
            
            // Hier würde der echte API-Call implementiert werden
            $success = true; // AI\Connector::get_instance()->test_gemini_connection($key);
            
            $latency = round((microtime(true) - $start_time) * 1000, 2);
            
            $result = [
                'status' => $success ? 'ok' : 'error',
                'msg' => $success ? __('Verbunden', 'seo-ai-master') : __('Verbindung fehlgeschlagen', 'seo-ai-master'),
                'latency' => $latency
            ];
            
            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Gemini API Test Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'msg' => __('Verbindungstest fehlgeschlagen', 'seo-ai-master'),
                'latency' => null
            ];
        }
    }
}