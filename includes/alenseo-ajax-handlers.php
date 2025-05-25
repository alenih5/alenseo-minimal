<?php
namespace Alenseo;

/**
 * AJAX-Handler für Alenseo SEO - FINALE VERSION
 *
 * Optimiert für die neue Multi-Modell Claude API mit intelligenter Modell-Auswahl
 * Alle Handler verwenden jetzt die verbesserten API-Klassen
 * 
 * @link       https://www.imponi.ch
 * @since      2.0.0
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Basisklasse für AJAX-Handler mit gemeinsamer Funktionalität
 */
class Alenseo_AJAX_Base {
    
    /**
     * Standardvalidierung für alle AJAX-Requests
     */
    protected static function validate_ajax_request($action, $required_capability = 'edit_posts', $check_post_id = false) {
        // Nonce prüfen
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
            return false;
        }
        
        // Benutzerrechte prüfen
        if (!current_user_can($required_capability)) {
            wp_send_json_error(['message' => 'Unzureichende Berechtigungen.']);
            return false;
        }
        
        // Optional: Post-ID prüfen
        if ($check_post_id) {
            if (!isset($_POST['post_id'])) {
                wp_send_json_error(['message' => 'Fehlende Post-ID.']);
                return false;
            }
            
            $post_id = intval($_POST['post_id']);
            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(['message' => 'Beitrag nicht gefunden.']);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * API-Provider auswählen basierend auf Einstellungen und Verfügbarkeit
     */
    protected static function get_api_provider($prefer_provider = null, $task_type = 'general') {
        $settings = get_option('alenseo_settings', []);
        $default_provider = $settings['default_ai_provider'] ?? 'claude';
        
        // Gewünschten Provider verwenden falls angegeben
        $target_provider = $prefer_provider ?: $default_provider;
        
        try {
            if ($target_provider === 'claude' || $target_provider === 'anthropic') {
                if (!class_exists('Alenseo_Claude_API')) {
                    require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
                }
                
                $api = new \Alenseo_Claude_API();
                if ($api->is_api_configured()) {
                    return ['provider' => 'claude', 'api' => $api];
                }
            }
            
            if ($target_provider === 'openai' || $target_provider === 'chatgpt') {
                if (!class_exists('Alenseo_ChatGPT_API')) {
                    require_once ALENSEO_MINIMAL_DIR . 'includes/class-chatgpt-api.php';
                }
                
                $api = new \Alenseo_ChatGPT_API();
                if ($api->is_api_configured()) {
                    return ['provider' => 'openai', 'api' => $api];
                }
            }
            
            // Fallback: Anderen Provider versuchen
            if ($target_provider !== 'claude') {
                if (!class_exists('Alenseo_Claude_API')) {
                    require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
                }
                
                $api = new \Alenseo_Claude_API();
                if ($api->is_api_configured()) {
                    return ['provider' => 'claude', 'api' => $api];
                }
            }
            
            if ($target_provider !== 'openai') {
                if (!class_exists('Alenseo_ChatGPT_API')) {
                    require_once ALENSEO_MINIMAL_DIR . 'includes/class-chatgpt-api.php';
                }
                
                $api = new \Alenseo_ChatGPT_API();
                if ($api->is_api_configured()) {
                    return ['provider' => 'openai', 'api' => $api];
                }
            }
            
        } catch (\Exception $e) {
            error_log('Alenseo API Provider Error: ' . $e->getMessage());
        }
        
        return null;
    }
}

/**
 * AJAX-Handler für die Analyse eines Beitrags - OPTIMIERT
 */
function alenseo_analyze_post_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('analyze_post', 'edit_posts', true)) {
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    
    try {
        if (!class_exists('Alenseo_Minimal_Analysis')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-minimal-analysis.php';
        }
        
        $analyzer = new \Alenseo_Minimal_Analysis();
        $result = $analyzer->analyze_post($post_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Analyse erfolgreich durchgeführt.',
            'score' => get_post_meta($post_id, '_alenseo_seo_score', true),
            'status' => get_post_meta($post_id, '_alenseo_seo_status', true),
            'details' => $result
        ]);
        
    } catch (\Exception $e) {
        error_log('Alenseo SEO - AJAX-Fehler: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Fehler bei der Analyse: ' . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler zum Speichern eines Keywords - OPTIMIERT
 */
function alenseo_save_keyword_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('save_keyword', 'edit_posts')) {
        return;
    }
    
    if (!isset($_POST['post_id']) || !isset($_POST['keyword'])) {
        wp_send_json_error(['message' => 'Fehlende Parameter.']);
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    $keyword = sanitize_text_field($_POST['keyword']);
    
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error(['message' => 'Beitrag nicht gefunden.']);
        return;
    }
    
    $result = update_post_meta($post_id, '_alenseo_keyword', $keyword);
    
    if (false !== $result) {
        // Automatische Neuanalyse nach Keyword-Änderung
        if (class_exists('Alenseo_Minimal_Analysis')) {
            $analyzer = new \Alenseo_Minimal_Analysis();
            $analyzer->analyze_post($post_id);
        }
        
        wp_send_json_success([
            'message' => 'Keyword erfolgreich gespeichert.',
            'keyword' => $keyword
        ]);
    } else {
        wp_send_json_error(['message' => 'Fehler beim Speichern des Keywords.']);
    }
}

/**
 * AJAX-Handler für die Keyword-Generierung - NEUE VERSION mit intelligenter Modell-Auswahl
 */
function alenseo_generate_keywords_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('generate_keywords', 'edit_posts')) {
        return;
    }
    
    if (!isset($_POST['post_id'])) {
        wp_send_json_error(['message' => 'Fehlende Post-ID.']);
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    $prefer_speed = isset($_POST['prefer_speed']) && $_POST['prefer_speed'] === 'true';
    
    try {
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Beitrag nicht gefunden.']);
            return;
        }
        
        $api_data = Alenseo_AJAX_Base::get_api_provider(null, 'keywords');
        if (!$api_data) {
            wp_send_json_error(['message' => 'Keine API konfiguriert.']);
            return;
        }
        
        $start_time = microtime(true);
        
        // Claude API mit intelligenter Modell-Auswahl
        if ($api_data['provider'] === 'claude') {
            $keywords = $api_data['api']->generate_keywords($post->post_title, $post->post_content);
        } else {
            // OpenAI API
            $keywords = $api_data['api']->generate_keywords($post->post_title, $post->post_content);
        }
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($keywords)) {
            wp_send_json_error(['message' => $keywords->get_error_message()]);
            return;
        }
        
        wp_send_json_success([
            'message' => 'Keywords erfolgreich generiert.',
            'keywords' => $keywords,
            'provider' => $api_data['provider'],
            'execution_time' => $execution_time . 'ms'
        ]);
        
    } catch (\Exception $e) {
        error_log('Alenseo SEO - Keyword-Generierung Fehler: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Fehler bei der Keyword-Generierung: ' . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für Content-Optimierung - NEUE VERSION
 */
function alenseo_optimize_content_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('optimize_content', 'edit_posts')) {
        return;
    }
    
    if (!isset($_POST['post_id']) || !isset($_POST['content'])) {
        wp_send_json_error(['message' => 'Fehlende Parameter.']);
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    $content = wp_kses_post($_POST['content']);
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    $prefer_quality = isset($_POST['prefer_quality']) && $_POST['prefer_quality'] === 'true';
    
    try {
        $api_data = Alenseo_AJAX_Base::get_api_provider(null, 'content_optimization');
        if (!$api_data) {
            wp_send_json_error(['message' => 'Keine API konfiguriert.']);
            return;
        }
        
        $start_time = microtime(true);
        
        $optimized_content = $api_data['api']->optimize_content($content, $keyword);
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($optimized_content)) {
            wp_send_json_error(['message' => $optimized_content->get_error_message()]);
            return;
        }
        
        // Optimierten Content speichern
        update_post_meta($post_id, '_alenseo_optimized_content', $optimized_content);
        
        wp_send_json_success([
            'message' => 'Content erfolgreich optimiert.',
            'content' => $optimized_content,
            'provider' => $api_data['provider'],
            'execution_time' => $execution_time . 'ms'
        ]);
        
    } catch (\Exception $e) {
        error_log('Alenseo SEO - Content-Optimierung Fehler: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Fehler bei der Content-Optimierung: ' . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für Meta-Description-Optimierung - NEUE VERSION
 */
function alenseo_optimize_meta_description_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('optimize_meta_description', 'edit_posts')) {
        return;
    }
    
    if (!isset($_POST['post_id']) || !isset($_POST['keyword'])) {
        wp_send_json_error(['message' => 'Fehlende Parameter.']);
        return;
    }
    
    $post_id = intval($_POST['post_id']);
    $keyword = sanitize_text_field($_POST['keyword']);
    
    try {
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Beitrag nicht gefunden.']);
            return;
        }
        
        $api_data = Alenseo_AJAX_Base::get_api_provider(null, 'meta_descriptions');
        if (!$api_data) {
            wp_send_json_error(['message' => 'Keine API konfiguriert.']);
            return;
        }
        
        $start_time = microtime(true);
        
        $optimized_meta = $api_data['api']->optimize_meta_description($post->post_title, $post->post_content, $keyword);
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($optimized_meta)) {
            wp_send_json_error(['message' => $optimized_meta->get_error_message()]);
            return;
        }
        
        update_post_meta($post_id, '_alenseo_meta_description', $optimized_meta);
        
        wp_send_json_success([
            'message' => 'Meta-Beschreibung erfolgreich optimiert.',
            'meta_description' => $optimized_meta,
            'provider' => $api_data['provider'],
            'execution_time' => $execution_time . 'ms'
        ]);
        
    } catch (\Exception $e) {
        error_log('Alenseo SEO - Meta-Description-Optimierung Fehler: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Fehler bei der Meta-Description-Optimierung: ' . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für Meta-Title-Generierung - NEU
 */
function alenseo_generate_meta_title_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('generate_meta_title', 'edit_posts')) {
        return;
    }
    
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    $prefer_speed = isset($_POST['prefer_speed']) && $_POST['prefer_speed'] === 'true';
    
    if (empty($content)) {
        wp_send_json_error(['message' => 'Kein Inhalt angegeben.']);
        return;
    }
    
    try {
        $api_data = Alenseo_AJAX_Base::get_api_provider(null, 'simple_meta_tags');
        if (!$api_data) {
            wp_send_json_error(['message' => 'Keine API konfiguriert.']);
            return;
        }
        
        $start_time = microtime(true);
        
        $meta_title = $api_data['api']->generate_meta_title($content, $keyword);
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($meta_title)) {
            wp_send_json_error(['message' => $meta_title->get_error_message()]);
            return;
        }
        
        wp_send_json_success([
            'meta_title' => $meta_title,
            'provider' => $api_data['provider'],
            'execution_time' => $execution_time . 'ms'
        ]);
        
    } catch (\Exception $e) {
        error_log('Alenseo Meta-Title Fehler: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Fehler bei der Meta-Title-Generierung: ' . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für SEO-Optimierungsvorschläge - NEU
 */
function alenseo_optimization_suggestions_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('optimization_suggestions', 'edit_posts')) {
        return;
    }
    
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    $prefer_quality = isset($_POST['prefer_quality']) && $_POST['prefer_quality'] === 'true';
    
    if (empty($content)) {
        wp_send_json_error(['message' => 'Kein Inhalt angegeben.']);
        return;
    }
    
    try {
        $api_data = Alenseo_AJAX_Base::get_api_provider(null, 'complex_seo_analysis');
        if (!$api_data) {
            wp_send_json_error(['message' => 'Keine API konfiguriert.']);
            return;
        }
        
        $start_time = microtime(true);
        
        $suggestions = $api_data['api']->get_optimization_suggestions($content);
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($suggestions)) {
            wp_send_json_error(['message' => $suggestions->get_error_message()]);
            return;
        }
        
        wp_send_json_success([
            'suggestions' => $suggestions,
            'provider' => $api_data['provider'],
            'execution_time' => $execution_time . 'ms'
        ]);
        
    } catch (\Exception $e) {
        error_log('Alenseo Optimierungsvorschläge Fehler: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Fehler bei der Optimierungsanalyse: ' . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für Keyword-Analyse - NEU
 */
function alenseo_keyword_analysis_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('keyword_analysis', 'edit_posts')) {
        return;
    }
    
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    
    if (empty($content)) {
        wp_send_json_error(['message' => 'Kein Inhalt angegeben.']);
        return;
    }
    
    try {
        $api_data = Alenseo_AJAX_Base::get_api_provider(null, 'keyword_analysis');
        if (!$api_data) {
            wp_send_json_error(['message' => 'Keine API konfiguriert.']);
            return;
        }
        
        $start_time = microtime(true);
        
        $analysis = $api_data['api']->analyze_keywords($content);
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($analysis)) {
            wp_send_json_error(['message' => $analysis->get_error_message()]);
            return;
        }
        
        wp_send_json_success([
            'analysis' => $analysis,
            'provider' => $api_data['provider'],
            'execution_time' => $execution_time . 'ms'
        ]);
        
    } catch (\Exception $e) {
        error_log('Alenseo Keyword-Analyse Fehler: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Fehler bei der Keyword-Analyse: ' . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für Bulk-Analyse - NEU
 */
function alenseo_bulk_analyze_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('bulk_analyze', 'edit_posts')) {
        return;
    }
    
    $contents = isset($_POST['contents']) && is_array($_POST['contents']) ? 
        array_map('sanitize_textarea_field', $_POST['contents']) : [];
    $prefer_cost = isset($_POST['prefer_cost']) && $_POST['prefer_cost'] === 'true';
    
    if (empty($contents)) {
        wp_send_json_error(['message' => 'Keine Inhalte angegeben.']);
        return;
    }
    
    try {
        $api_data = Alenseo_AJAX_Base::get_api_provider(null, 'bulk_processing');
        if (!$api_data) {
            wp_send_json_error(['message' => 'Keine API konfiguriert.']);
            return;
        }
        
        $start_time = microtime(true);
        $results = [];
        
        foreach ($contents as $index => $content) {
            if (empty(trim($content))) continue;
            
            $result = $api_data['api']->get_optimization_suggestions($content);
            
            if (is_wp_error($result)) {
                $results[$index] = [
                    'success' => false,
                    'error' => $result->get_error_message()
                ];
            } else {
                $results[$index] = [
                    'success' => true,
                    'suggestions' => $result
                ];
            }
        }
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        wp_send_json_success([
            'results' => $results,
            'total_processed' => count($results),
            'provider' => $api_data['provider'],
            'execution_time' => $execution_time . 'ms'
        ]);
        
    } catch (\Exception $e) {
        error_log('Alenseo Bulk-Analyse Fehler: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Fehler bei der Bulk-Analyse: ' . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für API-Status - NEU
 */
function alenseo_get_api_status_ajax() {
    if (!Alenseo_AJAX_Base::validate_ajax_request('get_api_status', 'manage_options')) {
        return;
    }
    
    try {
        $status = [
            'claude' => ['configured' => false, 'working' => false, 'message' => 'Nicht konfiguriert'],
            'openai' => ['configured' => false, 'working' => false, 'message' => 'Nicht konfiguriert']
        ];
        
        // Claude Status prüfen
        if (class_exists('Alenseo_Claude_API')) {
            $claude_api = new \Alenseo_Claude_API();
            $claude_status = $claude_api->get_api_status();
            $status['claude'] = $claude_status;
        }
        
        // OpenAI Status prüfen
        if (class_exists('Alenseo_ChatGPT_API')) {
            $openai_api = new \Alenseo_ChatGPT_API();
            if ($openai_api->is_api_configured()) {
                $status['openai']['configured'] = true;
                $test_result = $openai_api->test_api_key();
                if (is_array($test_result) && isset($test_result['success']) && $test_result['success']) {
                    $status['openai']['working'] = true;
                    $status['openai']['message'] = 'API funktioniert';
                } else {
                    $status['openai']['message'] = $test_result['message'] ?? 'Test fehlgeschlagen';
                }
            }
        }
        
        wp_send_json_success($status);
        
    } catch (\Exception $e) {
        wp_send_json_error(['message' => 'Fehler beim Abrufen des API-Status: ' . $e->getMessage()]);
    }
}

// Register AJAX handlers
add_action('wp_ajax_alenseo_analyze_post', __NAMESPACE__ . '\alenseo_analyze_post_ajax');
add_action('wp_ajax_alenseo_save_keyword', __NAMESPACE__ . '\alenseo_save_keyword_ajax');
add_action('wp_ajax_alenseo_generate_keywords', __NAMESPACE__ . '\alenseo_generate_keywords_ajax');
add_action('wp_ajax_alenseo_optimize_content', __NAMESPACE__ . '\alenseo_optimize_content_ajax');
add_action('wp_ajax_alenseo_optimize_meta_description', __NAMESPACE__ . '\alenseo_optimize_meta_description_ajax');
add_action('wp_ajax_alenseo_generate_meta_title', __NAMESPACE__ . '\alenseo_generate_meta_title_ajax');
add_action('wp_ajax_alenseo_optimization_suggestions', __NAMESPACE__ . '\alenseo_optimization_suggestions_ajax');
add_action('wp_ajax_alenseo_keyword_analysis', __NAMESPACE__ . '\alenseo_keyword_analysis_ajax');
add_action('wp_ajax_alenseo_bulk_analyze', __NAMESPACE__ . '\alenseo_bulk_analyze_ajax');
add_action('wp_ajax_alenseo_get_api_status', __NAMESPACE__ . '\alenseo_get_api_status_ajax');

/**
 * JavaScript-API für das Frontend - NEUE VERSION
 */
function alenseo_frontend_js_api() {
    if (!is_admin()) return;
    
    ?>
    <script type="text/javascript">
    // Erweiterte Alenseo JavaScript API
    window.AlenseoAPI = {
        
        // Basis-AJAX-Funktion mit standardisierten Parametern
        makeRequest: function(action, data, options) {
            var defaults = {
                timeout: 30000,
                showProgress: true,
                showErrors: true
            };
            options = Object.assign(defaults, options || {});
            
            data.action = 'alenseo_' + action;
            data.nonce = '<?php echo wp_create_nonce('alenseo_ajax_nonce'); ?>';
            
            if (options.showProgress && window.AlenseoUI) {
                window.AlenseoUI.showProgress('Verarbeite Anfrage...');
            }
            
            return jQuery.post(ajaxurl, data)
                .done(function(response) {
                    if (options.showProgress && window.AlenseoUI) {
                        window.AlenseoUI.hideProgress();
                    }
                    
                    if (response.success && response.data.execution_time) {
                        console.log('✅ ' + action + ' completed in ' + response.data.execution_time);
                    }
                })
                .fail(function(xhr, status, error) {
                    if (options.showProgress && window.AlenseoUI) {
                        window.AlenseoUI.hideProgress();
                    }
                    
                    if (options.showErrors) {
                        console.error('❌ ' + action + ' failed:', error);
                        if (window.AlenseoUI) {
                            window.AlenseoUI.showError('Fehler bei ' + action + ': ' + error);
                        }
                    }
                });
        },
        
        // Keywords generieren
        generateKeywords: function(postId, preferSpeed) {
            return this.makeRequest('generate_keywords', {
                post_id: postId,
                prefer_speed: preferSpeed || false
            });
        },
        
        // Meta-Title generieren
        generateMetaTitle: function(content, keyword, preferSpeed) {
            return this.makeRequest('generate_meta_title', {
                content: content,
                keyword: keyword || '',
                prefer_speed: preferSpeed || false
            });
        },
        
        // Meta-Description optimieren
        optimizeMetaDescription: function(postId, keyword) {
            return this.makeRequest('optimize_meta_description', {
                post_id: postId,
                keyword: keyword || ''
            });
        },
        
        // Content optimieren
        optimizeContent: function(postId, content, keyword, preferQuality) {
            return this.makeRequest('optimize_content', {
                post_id: postId,
                content: content,
                keyword: keyword || '',
                prefer_quality: preferQuality || false
            });
        },
        
        // SEO-Optimierungsvorschläge
        getOptimizationSuggestions: function(content, preferQuality) {
            return this.makeRequest('optimization_suggestions', {
                content: content,
                prefer_quality: preferQuality || false
            });
        },
        
        // Keyword-Analyse
        analyzeKeywords: function(content) {
            return this.makeRequest('keyword_analysis', {
                content: content
            });
        },
        
        // Bulk-Analyse
        bulkAnalyze: function(contents, preferCost) {
            return this.makeRequest('bulk_analyze', {
                contents: contents,
                prefer_cost: preferCost || false
            });
        },
        
        // API-Status abrufen
        getApiStatus: function() {
            return this.makeRequest('get_api_status', {});
        },
        
        // Keyword speichern
        saveKeyword: function(postId, keyword) {
            return this.makeRequest('save_keyword', {
                post_id: postId,
                keyword: keyword
            });
        },
        
        // Post analysieren
        analyzePost: function(postId) {
            return this.makeRequest('analyze_post', {
                post_id: postId
            });
        }
    };
    
    // Convenience-Wrapper für häufige Aufgaben
    window.AlenseoQuick = {
        
        // Komplette Post-Optimierung
        optimizePostComplete: function(postId, keyword) {
            var promises = [];
            
            // Keywords generieren (schnell)
            promises.push(AlenseoAPI.generateKeywords(postId, true));
            
            // Meta-Description optimieren
            if (keyword) {
                promises.push(AlenseoAPI.optimizeMetaDescription(postId, keyword));
            }
            
            // Post-Analyse durchführen
            promises.push(AlenseoAPI.analyzePost(postId));
            
            return jQuery.when.apply(jQuery, promises).then(function() {
                var results = Array.prototype.slice.call(arguments);
                return {
                    keywords: results[0] ? results[0][0].data.keywords : null,
                    meta_description: results[1] ? results[1][0].data.meta_description : null,
                    analysis: results[2] ? results[2][0].data : null
                };
            });
        },
        
        // Schneller SEO-Check
        quickSeoCheck: function(content) {
            return AlenseoAPI.getOptimizationSuggestions(content, false);
        },
        
        // Meta-Tags für neuen Content generieren
        generateMetaTags: function(content, keyword) {
            var titlePromise = AlenseoAPI.generateMetaTitle(content, keyword, true);
            
            return titlePromise.then(function(titleResponse) {
                var title = titleResponse.data.meta_title;
                
                // Simuliere Meta-Description mit gleichem Content
                return AlenseoAPI.makeRequest('optimize_meta_description', {
                    post_id: 0, // Dummy ID für neue Posts
                    keyword: keyword || ''
                }).then(function(descResponse) {
                    return {
                        title: title,
                        description: descResponse.data.meta_description,
                        execution_time: (titleResponse.data.execution_time || 0) + 
                                       (descResponse.data.execution_time || 0)
                    };
                });
            });
        }
    };
    
    // Event-System für Plugin-Integration
    window.AlenseoEvents = {
        callbacks: {},
        
        on: function(event, callback) {
            if (!this.callbacks[event]) {
                this.callbacks[event] = [];
            }
            this.callbacks[event].push(callback);
        },
        
        trigger: function(event, data) {
            if (this.callbacks[event]) {
                this.callbacks[event].forEach(function(callback) {
                    callback(data);
                });
            }
        }
    };
    
    console.log('🚀 Alenseo API v2.0 geladen!');
    console.log('Beispiel: AlenseoAPI.generateKeywords(123, true)');
    console.log('Schnell: AlenseoQuick.optimizePostComplete(123, "keyword")');
    </script>
    <?php
}
add_action('admin_footer', __NAMESPACE__ . '\alenseo_frontend_js_api');