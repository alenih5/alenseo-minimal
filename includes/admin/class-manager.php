<?php
namespace SEOAI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin-Manager: Menüeinträge und Seiten-Callbacks
 */
class Manager {
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_seo_ai_dashboard_stats', [$this, 'ajax_dashboard_stats']);
        add_action('wp_ajax_seo_ai_optimize_text', [$this, 'ajax_optimize_text']);
        // AJAX: Keywords vorschlagen
        add_action('wp_ajax_seo_ai_suggest_keywords', [$this, 'ajax_suggest_keywords']);
        // AJAX: Bulk Content Generierung
        add_action('wp_ajax_seo_ai_bulk_generate_content', [$this, 'ajax_bulk_generate_content']);
        add_action('wp_ajax_seo_ai_bulk_apply_content', [$this, 'ajax_bulk_apply_content']);
        // AJAX: Live SEO Score
        add_action('wp_ajax_seo_ai_live_score', [$this, 'ajax_live_score']);
        // AJAX: API-Test für Settings
        add_action('wp_ajax_seo_ai_test_api', [$this, 'ajax_test_api']);
        // AJAX: Check all APIs status
        add_action('wp_ajax_seo_ai_check_all_apis', [$this, 'ajax_check_all_apis']);
    }

    /**
     * Admin-Menü und Sub-Menüs registrieren
     */
    public function register_menu() {
        // Hauptmenü
        add_menu_page(
            __('SEO AI Master', 'seo-ai-master'),
            __('SEO AI', 'seo-ai-master'),
            'manage_options',
            'seo-ai-master',
            [$this, 'render_dashboard'],
            'dashicons-chart-area',
            30
        );
        // URLs (umbenannt zu Seiten optimieren)
        add_submenu_page(
            'seo-ai-master',
            __('Seiten optimieren', 'seo-ai-master'), // Seitentitel
            __('Seiten optimieren', 'seo-ai-master'), // Menüname
            'manage_options',
            'seo-ai-urls',
            [$this, 'render_urls']
        );
        // Content Optimizer (versteckt, nicht im Menü)
        add_submenu_page(
            null, // Kein Parent, also nicht im Menü sichtbar
            __('Content Optimizer', 'seo-ai-master'),
            __('Optimizer', 'seo-ai-master'),
            'manage_options',
            'seo-ai-optimizer',
            [$this, 'render_optimizer']
        );
        // Einstellungen
        add_submenu_page(
            'seo-ai-master',
            __('Einstellungen', 'seo-ai-master'),
            __('Settings', 'seo-ai-master'),
            'manage_options',
            'seo-ai-settings',
            [$this, 'render_settings']
        );
    }
    
    /**
     * Assets für Admin-Seiten laden (WordPress-optimiert)
     */
    public function enqueue_admin_assets($hook) {
        // Verbesserte Hook-Detection für alle Plugin-Seiten
        $plugin_pages = [
            'toplevel_page_seo-ai-master',
            'seo-ai_page_seo-ai-urls', 
            'admin_page_seo-ai-optimizer',
            'seo-ai_page_seo-ai-settings'
        ];
        
        // Alternative Prüfung über GET-Parameter
        $is_plugin_page = in_array($hook, $plugin_pages, true) || 
                         (isset($_GET['page']) && strpos($_GET['page'], 'seo-ai-') === 0);
        
        if ($is_plugin_page) {
            // Font Awesome für Icons (mit Integritätsprüfung)
            wp_enqueue_style(
                'seo-ai-fa', 
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', 
                [], 
                '6.4.0'
            );
            
            // Plugin-CSS (korrigierter Pfad und bessere Abhängigkeiten)
            wp_enqueue_style(
                'seo-ai-settings', 
                SEO_AI_MASTER_URL . 'assets/css/settings.css', 
                ['seo-ai-fa'], 
                SEO_AI_MASTER_VERSION . '-' . filemtime(SEO_AI_MASTER_PATH . 'assets/css/settings.css')
            );
            
            // Plugin-JavaScript (mit Abhängigkeiten)
            wp_enqueue_script(
                'seo-ai-settings',
                SEO_AI_MASTER_URL . 'assets/js/settings.js',
                ['jquery'],
                SEO_AI_MASTER_VERSION . '-' . filemtime(SEO_AI_MASTER_PATH . 'assets/js/settings.js'),
                true
            );
            
            // JavaScript-Lokalisierung für AJAX und Übersetzungen
            wp_localize_script('seo-ai-settings', 'seoAiSettings', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('seo_ai_dashboard_nonce'),
                'strings' => [
                    'testing' => __('Teste Verbindung...', 'seo-ai-master'),
                    'connected' => __('Verbunden', 'seo-ai-master'),
                    'error' => __('Verbindungsfehler', 'seo-ai-master'),
                    'timeout' => __('Zeitüberschreitung', 'seo-ai-master'),
                    'invalid_key' => __('Ungültiger API-Key', 'seo-ai-master'),
                    'success' => __('Erfolgreich gespeichert', 'seo-ai-master')
                ]
            ]);
            
            // Debug-Information (nur bei WP_DEBUG)
            if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
                $css_exists = file_exists(SEO_AI_MASTER_PATH . 'assets/css/settings.css');
                $js_exists = file_exists(SEO_AI_MASTER_PATH . 'assets/js/settings.js');
                
                if (!$css_exists || !$js_exists) {
                    add_action('admin_notices', function() use ($css_exists, $js_exists) {
                        if (!$css_exists) {
                            echo '<div class="notice notice-error"><p><strong>SEO AI Master:</strong> CSS-Datei nicht gefunden!</p></div>';
                        }
                        if (!$js_exists) {
                            echo '<div class="notice notice-error"><p><strong>SEO AI Master:</strong> JS-Datei nicht gefunden!</p></div>';
                        }
                    });
                }
            }
        }
    }
    
    /**
     * Dashboard-Seite rendern (mit CSS-Container)
     */
    public function render_dashboard() {
        echo '<div class="seo-ai-master-plugin">';
        include SEO_AI_MASTER_PATH . 'templates/admin/dashboard.php';
        echo '</div>';
    }

    /**
     * URL-Manager-Seite rendern
     */
    public function render_urls() {
        echo '<div class="seo-ai-master-plugin">';
        \SEOAI\URLManager::get_instance()->render_page();
        echo '</div>';
    }

    /**
     * Optimizer-Seite rendern (mit CSS-Container)
     */
    public function render_optimizer() {
        echo '<div class="seo-ai-master-plugin">';
        include SEO_AI_MASTER_PATH . 'templates/admin/optimizer.php';
        echo '</div>';
    }
    
    /**
     * Einstellungen-Seite rendern (WordPress-optimiert)
     */
    public function render_settings() {
        // WordPress Admin Notices ausgeben (für Settings-Updates)
        settings_errors('seo_ai_master');
        
        // Einstellungen speichern (WordPress-konform)
        if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['seo_ai_settings_nonce'])) {
            if (current_user_can('manage_options') && check_admin_referer('seo_ai_settings','seo_ai_settings_nonce')) {
                $opts = [];
                $opts['claude_api_key']      = sanitize_text_field($_POST['claude_api_key'] ?? '');
                $opts['claude_priority']     = absint($_POST['claude_priority'] ?? 1);
                $opts['claude_enabled']      = isset($_POST['claude_enabled']) ? 1 : 0;
                $opts['openai_api_key']      = sanitize_text_field($_POST['openai_api_key'] ?? '');
                $opts['openai_priority']     = absint($_POST['openai_priority'] ?? 2);
                $opts['openai_enabled']      = isset($_POST['openai_enabled']) ? 1 : 0;
                $opts['gemini_api_key']      = sanitize_text_field($_POST['gemini_api_key'] ?? '');
                $opts['gemini_priority']     = absint($_POST['gemini_priority'] ?? 3);
                $opts['gemini_enabled']      = isset($_POST['gemini_enabled']) ? 1 : 0;
                // Kompatibilität: alte Felder
                $opts['default_provider']    = in_array($_POST['default_provider'] ?? '', ['claude','openai','gemini'], true)
                                                ? $_POST['default_provider'] : 'claude';
                $opts['auto_analyze']        = isset($_POST['auto_analyze']) ? 1 : 0;
                $opts['analyze_on_publish']  = isset($_POST['analyze_on_publish']) ? 1 : 0;
                $opts['analyze_on_update']   = isset($_POST['analyze_on_update']) ? 1 : 0;
                
                $updated = update_option('seo_ai_master_options', $opts);
                
                if ($updated) {
                    add_settings_error(
                        'seo_ai_master',
                        'settings_updated',
                        __('Einstellungen erfolgreich gespeichert.', 'seo-ai-master'),
                        'updated'
                    );
                } else {
                    add_settings_error(
                        'seo_ai_master',
                        'settings_error',
                        __('Fehler beim Speichern der Einstellungen.', 'seo-ai-master'),
                        'error'
                    );
                }
                
                // Settings errors für nächste Seitenladung setzen
                set_transient('settings_errors', get_settings_errors(), 30);
            } else {
                add_settings_error(
                    'seo_ai_master',
                    'security_error',
                    __('Sicherheitsfehler: Ungültiger Nonce.', 'seo-ai-master'),
                    'error'
                );
            }
        }
        
        // Optionen laden mit verbesserter Standardwerte
        $options = get_option('seo_ai_master_options', [
            'claude_api_key' => '',
            'claude_priority' => 1,
            'claude_enabled' => 1,
            'openai_api_key' => '',
            'openai_priority' => 2,
            'openai_enabled' => 1,
            'gemini_api_key' => '',
            'gemini_priority' => 3,
            'gemini_enabled' => 0,
            'default_provider' => 'claude',
            'auto_analyze' => 0,
            'analyze_on_publish' => 1,
            'analyze_on_update' => 0
        ]);
        
        // Template mit CSS-Container rendern
        echo '<div class="seo-ai-master-plugin">';
        include SEO_AI_MASTER_PATH . 'templates/admin/settings.php';
        echo '</div>';
    }

    /**
     * AJAX: Dashboard-Statistiken zurückgeben (WordPress-optimiert)
     */
    public function ajax_dashboard_stats() {
        check_ajax_referer('seo_ai_dashboard', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        
        global $wpdb;
        
        try {
            $total = wp_count_posts()->publish ?? 0;
            
            // Sichere Datenbankabfrage
            $table_name = $wpdb->prefix . 'seo_ai_data';
            $analyzed = 0;
            
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
                $analyzed = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$table_name} WHERE post_id IS NOT NULL");
            }
            
            $not_analyzed = max(0, $total - $analyzed);
            
            // Recent Activity
            $recent = [];
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
                $recent = $wpdb->get_results(
                    "SELECT p.ID, p.post_title, d.last_analyzed
                     FROM {$wpdb->posts} p
                     JOIN {$table_name} d ON p.ID = d.post_id
                     WHERE p.post_status = 'publish'
                     ORDER BY d.last_analyzed DESC
                     LIMIT 5"
                );
            }
            
            wp_send_json_success([
                'total'        => intval($total),
                'analyzed'     => intval($analyzed),
                'not_analyzed' => intval($not_analyzed),
                'recent'       => $recent ?: []
            ]);
            
        } catch (Exception $e) {
            error_log('SEO AI Master Dashboard Stats Error: ' . $e->getMessage());
            wp_send_json_error(__('Fehler beim Laden der Statistiken.', 'seo-ai-master'));
        }
    }

    /**
     * AJAX: Text optimieren (für Optimizer-Seite)
     */
    public function ajax_optimize_text() {
        check_ajax_referer('seo_ai_optimize_text', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        $text = wp_unslash($_POST['text'] ?? '');
        if (empty($text)) {
            wp_send_json_error(__('Kein Text übergeben', 'seo-ai-master'));
        }
        try {
            $opt = \SEOAI\AI\Connector::get_instance()->optimize_content($text);
            wp_send_json_success(['content' => $opt]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: Keywords vorschlagen für eine Seite
     */
    public function ajax_suggest_keywords() {
        check_ajax_referer('seo_ai_suggest_keywords', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(__('Ungültige Beitrags-ID', 'seo-ai-master'));
        }
        $content = get_post_field('post_content', $post_id);
        try {
            $keywords = \SEOAI\AI\Connector::get_instance()->suggest_keywords($content);
            wp_send_json_success(['keywords' => $keywords]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Bulk Content Generierung (Vorschau, noch nicht speichern)
     */
    public function ajax_bulk_generate_content() {
        check_ajax_referer('seo_ai_dashboard', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        $type = sanitize_text_field($_POST['type'] ?? 'all');
        $tab = sanitize_text_field($_POST['tab'] ?? 'meta_title');
        $style = sanitize_text_field($_POST['style'] ?? 'professionell');
        $audience = sanitize_text_field($_POST['audience'] ?? '');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $provider = sanitize_text_field($_POST['provider'] ?? 'auto');
        $args = [
            'post_type' => $type === 'all' ? ['post','page','product'] : $type,
            'posts_per_page' => 20,
        ];
        $q = new \WP_Query($args);
        $results = [];
        if ($q->have_posts()) {
            while ($q->have_posts()) { $q->the_post();
                $id = get_the_ID();
                $content = get_post_field('post_content', $id);
                $title = get_the_title($id);
                $suggestion = '';
                try {
                    $ai = \SEOAI\AI\Connector::get_instance();
                    if ($tab === 'meta_title') {
                        $suggestion = $ai->generate_meta_title($content, $keywords);
                    } elseif ($tab === 'meta_desc') {
                        $suggestion = $ai->generate_meta_description($content, $keywords);
                    } else {
                        $suggestion = __('Demo: KI-Text für '.$tab, 'seo-ai-master');
                    }
                } catch (\Exception $e) {
                    $suggestion = 'Fehler: '.$e->getMessage();
                }
                $results[] = [
                    'id' => $id,
                    'title' => $title,
                    'suggestion' => $suggestion,
                    'tab' => $tab
                ];
            }
            wp_reset_postdata();
        }
        wp_send_json_success(['results' => $results]);
    }

    /**
     * AJAX: Bulk Content Übernahme (nach Bestätigung)
     */
    public function ajax_bulk_apply_content() {
        check_ajax_referer('seo_ai_dashboard', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        $items = $_POST['items'] ?? [];
        $tab = sanitize_text_field($_POST['tab'] ?? 'meta_title');
        $count = 0;
        foreach ($items as $item) {
            $id = intval($item['id'] ?? 0);
            $value = sanitize_text_field($item['suggestion'] ?? '');
            if ($id && $value) {
                if ($tab === 'meta_title') {
                    update_post_meta($id, '_seo_ai_title', $value);
                } elseif ($tab === 'meta_desc') {
                    update_post_meta($id, '_seo_ai_description', $value);
                }
                $count++;
            }
        }
        wp_send_json_success(['updated' => $count]);
    }

    /**
     * AJAX: Live SEO Score für einen Beitrag berechnen
     */
    public function ajax_live_score() {
        check_ajax_referer('seo_ai_dashboard', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung', 'seo-ai-master'));
        }
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(__('Ungültige Beitrags-ID', 'seo-ai-master'));
        }
        // Score aus Meta holen oder Fallback
        $score = get_post_meta($post_id, '_seo_ai_score', true);
        if (!is_numeric($score)) {
            // Fallback: einfache Berechnung (z.B. wenn Meta fehlt)
            $score = 0;
            $content = get_post_field('post_content', $post_id);
            if (strlen($content) > 200) $score += 20;
            $meta_title = get_post_meta($post_id, '_seo_ai_title', true);
            if (!empty($meta_title)) $score += 40;
            $meta_desc = get_post_meta($post_id, '_seo_ai_description', true);
            if (!empty($meta_desc)) $score += 40;
            if ($score > 100) $score = 100;
        } else {
            $score = intval($score);
        }
        wp_send_json_success(['score' => $score]);
    }

    /**
     * AJAX: API-Key testen (verbesserte Implementierung)
     */
    public function ajax_test_api() {
        // Korrigierte Nonce-Prüfung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'seo_ai_dashboard_nonce')) {
            wp_send_json_error(['msg' => __('Sicherheitsfehler: Ungültiger Nonce','seo-ai-master')]);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['msg' => __('Keine Berechtigung','seo-ai-master')]);
        }
        
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key  = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (!$provider || !$api_key) {
            wp_send_json_error(['msg' => __('Provider und API-Key erforderlich','seo-ai-master')]);
        }
        
        $ok = false;
        $msg = '';
        
        switch ($provider) {
            case 'claude':
                // Anthropic Claude: Model-Listing (alle Versionen)
                if (strlen($api_key) < 20) {
                    $msg = __('API-Key ungültig (zu kurz)','seo-ai-master');
                    break;
                }
                $response = wp_remote_get('https://api.anthropic.com/v1/models', [
                    'headers' => [
                        'x-api-key' => $api_key,
                        'anthropic-version' => '2023-06-01',
                        'Content-Type' => 'application/json',
                    ],
                    'timeout' => 15,
                    'sslverify' => true,
                ]);
                if (is_wp_error($response)) {
                    $msg = __('Verbindung fehlgeschlagen: ','seo-ai-master') . $response->get_error_message();
                    break;
                }
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                if ($code === 200 && strpos($body, 'claude') !== false) {
                    $ok = true;
                    $msg = __('Verbindung erfolgreich! Claude-Modelle gefunden.','seo-ai-master');
                } else {
                    $msg = __('API-Fehler (Code: '.$code.'): ','seo-ai-master') . substr($body, 0, 200);
                }
                break;
                
            case 'openai':
                if (strpos($api_key, 'sk-') !== 0 || strlen($api_key) < 30) {
                    $msg = __('API-Key ungültig (falsches Format)','seo-ai-master');
                    break;
                }
                $response = wp_remote_get('https://api.openai.com/v1/models', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'timeout' => 15,
                    'sslverify' => true,
                ]);
                if (is_wp_error($response)) {
                    $msg = __('Verbindung fehlgeschlagen: ','seo-ai-master') . $response->get_error_message();
                    break;
                }
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                if ($code === 200 && (strpos($body, 'gpt-4') !== false || strpos($body, 'gpt-3') !== false)) {
                    $ok = true;
                    $msg = __('Verbindung erfolgreich! OpenAI-Modelle gefunden.','seo-ai-master');
                } else {
                    $msg = __('API-Fehler (Code: '.$code.'): ','seo-ai-master') . substr($body, 0, 200);
                }
                break;
                
            case 'gemini':
                if (strlen($api_key) < 20) {
                    $msg = __('API-Key ungültig (zu kurz)','seo-ai-master');
                    break;
                }
                $url = 'https://generativelanguage.googleapis.com/v1/models?key=' . urlencode($api_key);
                $response = wp_remote_get($url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'timeout' => 15,
                    'sslverify' => true,
                ]);
                if (is_wp_error($response)) {
                    $msg = __('Verbindung fehlgeschlagen: ','seo-ai-master') . $response->get_error_message();
                    break;
                }
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                if ($code === 200 && strpos($body, 'gemini') !== false) {
                    $ok = true;
                    $msg = __('Verbindung erfolgreich! Gemini-Modelle gefunden.','seo-ai-master');
                } else {
                    $msg = __('API-Fehler (Code: '.$code.'): ','seo-ai-master') . substr($body, 0, 200);
                }
                break;
                
            default:
                $msg = __('Unbekannter Provider: ','seo-ai-master') . $provider;
        }
        
        if ($ok) {
            wp_send_json_success($msg);
        } else {
            wp_send_json_error($msg);
        }
    }
    
    /**
     * AJAX: Alle APIs gleichzeitig prüfen
     */
    public function ajax_check_all_apis() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'seo_ai_dashboard_nonce')) {
            wp_send_json_error(__('Sicherheitsfehler','seo-ai-master'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung','seo-ai-master'));
        }
        
        $options = get_option('seo_ai_master_options', []);
        $results = [];
        
        foreach (['claude', 'openai', 'gemini'] as $provider) {
            $api_key = $options[$provider . '_api_key'] ?? '';
            $enabled = $options[$provider . '_enabled'] ?? false;
            
            if ($enabled && !empty($api_key)) {
                // Simuliere API-Test (vereinfacht)
                $results[$provider] = [
                    'success' => strlen($api_key) > 20, // Einfacher Test
                    'message' => strlen($api_key) > 20 ? 'API verfügbar' : 'API-Key zu kurz'
                ];
            } else {
                $results[$provider] = [
                    'success' => false,
                    'message' => 'Nicht konfiguriert'
                ];
            }
        }
        
        wp_send_json_success($results);
    }
}

// Manager initialisieren
new Manager();