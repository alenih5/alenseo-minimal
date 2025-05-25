<?php
namespace Alenseo;

/**
 * AJAX-Handler für Einstellungen in Alenseo SEO - FINALE VERSION
 *
 * Optimiert für die neue Multi-Modell Claude API und verbesserte OpenAI Integration
 * Alle API-Tests verwenden jetzt die intelligente Modell-Auswahl
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
 * AJAX-Handler zum Testen der Claude API - FINALE VERSION
 */
function alenseo_test_claude_api_settings() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_test_api_nonce')) {
        wp_send_json_error(['message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')]);
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unzureichende Berechtigungen.', 'alenseo')]);
        return;
    }
    
    // API-Schlüssel prüfen
    if (!isset($_POST['api_key']) || empty($_POST['api_key'])) {
        wp_send_json_error(['message' => __('Kein API-Schlüssel angegeben.', 'alenseo')]);
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'claude-3-5-sonnet-20241022';
    
    try {
        // Verwende die neue Claude API-Klasse mit Multi-Modell-Support
        if (!class_exists('Alenseo_Claude_API')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
        }
        
        $claude_api = new Alenseo_Claude_API($api_key, $model);
        
        // Erweiterten API-Test durchführen
        $test_result = $claude_api->test_api_key();
        
        if (is_array($test_result) && isset($test_result['success']) && $test_result['success']) {
            // Zusätzliche Modell-Informationen abrufen
            $api_status = $claude_api->get_api_status();
            $working_models = $claude_api->get_working_models();
            
            wp_send_json_success([
                'message' => __('Claude API erfolgreich getestet!', 'alenseo'),
                'details' => [
                    'working_models' => array_keys($working_models),
                    'model_count' => count($working_models),
                    'fastest_model' => $test_result['fastest_model'] ?? 'claude-3-haiku-20240307',
                    'recommended_model' => $test_result['recommended_model'] ?? 'claude-3-5-sonnet-20241022',
                    'response' => $test_result['response'] ?? '',
                    'model_details' => $working_models
                ],
                'status' => $api_status
            ]);
        } else {
            $error_message = is_array($test_result) && isset($test_result['message']) 
                ? $test_result['message'] 
                : __('Unbekannter Fehler bei der API-Verbindung.', 'alenseo');
            
            wp_send_json_error(['message' => $error_message]);
        }
    } catch (\Exception $e) {
        error_log('Alenseo Claude API Test Error: ' . $e->getMessage());
        wp_send_json_error(['message' => __('Fehler beim API-Test: ', 'alenseo') . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler zum Testen der OpenAI/ChatGPT API - VERBESSERTE VERSION
 */
function alenseo_test_openai_api_settings() {
    // Nonce prüfen
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'alenseo_test_api_nonce')) {
        wp_send_json_error(['message' => __('Sicherheitsüberprüfung fehlgeschlagen.', 'alenseo')]);
        return;
    }
    
    // Benutzerrechte prüfen
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unzureichende Berechtigungen.', 'alenseo')]);
        return;
    }
    
    // API-Schlüssel prüfen
    if (!isset($_POST['api_key']) || empty($_POST['api_key'])) {
        wp_send_json_error(['message' => __('Kein API-Schlüssel angegeben.', 'alenseo')]);
        return;
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gpt-3.5-turbo';
    
    try {
        // Verwende die verbesserte ChatGPT API-Klasse
        if (!class_exists('Alenseo_ChatGPT_API')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-chatgpt-api.php';
        }
        
        $openai_api = new Alenseo_ChatGPT_API($api_key, $model);
        $test_result = $openai_api->test_api_key();
        
        if (is_array($test_result) && isset($test_result['success']) && $test_result['success']) {
            wp_send_json_success([
                'message' => __('OpenAI API erfolgreich getestet!', 'alenseo'),
                'details' => [
                    'model' => $test_result['model'] ?? $model,
                    'response' => $test_result['response'] ?? '',
                    'api_configured' => true
                ]
            ]);
        } else {
            $error_message = is_array($test_result) && isset($test_result['message']) 
                ? $test_result['message'] 
                : __('Unbekannter Fehler bei der OpenAI API-Verbindung.', 'alenseo');
            
            wp_send_json_error(['message' => $error_message]);
        }
    } catch (\Exception $e) {
        error_log('Alenseo OpenAI API Test Error: ' . $e->getMessage());
        wp_send_json_error(['message' => __('Fehler beim OpenAI API-Test: ', 'alenseo') . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für universelle SEO-Anfragen - NEU
 */
function alenseo_universal_seo_request() {
    check_ajax_referer('alenseo_ajax_nonce', 'security');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Unzureichende Berechtigungen.', 'alenseo')]);
        return;
    }
    
    $task_type = isset($_POST['task_type']) ? sanitize_text_field($_POST['task_type']) : '';
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $provider_preference = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
    $prefer_speed = isset($_POST['prefer_speed']) && $_POST['prefer_speed'] === 'true';
    $prefer_quality = isset($_POST['prefer_quality']) && $_POST['prefer_quality'] === 'true';
    $prefer_cost = isset($_POST['prefer_cost']) && $_POST['prefer_cost'] === 'true';
    
    if (empty($task_type) || empty($content)) {
        wp_send_json_error(['message' => __('Unvollständige Anfrage.', 'alenseo')]);
        return;
    }
    
    try {
        $api_data = null;
        $start_time = microtime(true);
        
        // API-Provider auswählen
        if ($provider_preference === 'claude' || empty($provider_preference)) {
            if (!class_exists('Alenseo_Claude_API')) {
                require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
            }
            
            $claude_api = new Alenseo_Claude_API();
            if ($claude_api->is_api_configured()) {
                $api_data = ['provider' => 'claude', 'api' => $claude_api];
            }
        }
        
        if (!$api_data && ($provider_preference === 'openai' || empty($provider_preference))) {
            if (!class_exists('Alenseo_ChatGPT_API')) {
                require_once ALENSEO_MINIMAL_DIR . 'includes/class-chatgpt-api.php';
            }
            
            $openai_api = new Alenseo_ChatGPT_API();
            if ($openai_api->is_api_configured()) {
                $api_data = ['provider' => 'openai', 'api' => $openai_api];
            }
        }
        
        if (!$api_data) {
            wp_send_json_error(['message' => __('Keine API konfiguriert.', 'alenseo')]);
            return;
        }
        
        $result = null;
        
        // Aufgabe basierend auf Typ ausführen
        switch ($task_type) {
            case 'meta_title':
                $result = $api_data['api']->generate_meta_title($content, $keyword);
                break;
                
            case 'meta_description':
                $result = $api_data['api']->optimize_meta_description($title, $content, $keyword);
                break;
                
            case 'keywords':
                $result = $api_data['api']->generate_keywords($title, $content);
                break;
                
            case 'content_optimization':
                $result = $api_data['api']->optimize_content($content, $keyword);
                break;
                
            case 'seo_analysis':
                $result = $api_data['api']->get_optimization_suggestions($content);
                break;
                
            case 'keyword_analysis':
                $result = $api_data['api']->analyze_keywords($content);
                break;
                
            // Spezielle Claude-Funktionen
            case 'smart_optimization':
                if ($api_data['provider'] === 'claude' && method_exists($api_data['api'], 'generate_text_smart')) {
                    $task_mapping = [
                        'meta_title' => 'simple_meta_tags',
                        'meta_description' => 'meta_descriptions', 
                        'keywords' => 'quick_keywords',
                        'content' => 'content_creation',
                        'analysis' => 'complex_seo_analysis'
                    ];
                    
                    $mapped_task = $task_mapping[$content] ?? 'general';
                    $result = $api_data['api']->generate_text_smart($keyword, $mapped_task, [
                        'prefer_speed' => $prefer_speed,
                        'prefer_quality' => $prefer_quality,
                        'prefer_cost' => $prefer_cost
                    ]);
                } else {
                    wp_send_json_error(['message' => __('Smart-Optimierung nur mit Claude verfügbar.', 'alenseo')]);
                    return;
                }
                break;
                
            default:
                wp_send_json_error(['message' => __('Unbekannte Aufgabe.', 'alenseo')]);
                return;
        }
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        // Erfolgreiche Antwort mit erweiterten Informationen
        $response_data = [
            'result' => $result,
            'task_type' => $task_type,
            'provider' => $api_data['provider'],
            'execution_time' => $execution_time . 'ms',
            'message' => __('Aufgabe erfolgreich abgeschlossen.', 'alenseo')
        ];
        
        // Claude-spezifische Informationen hinzufügen
        if ($api_data['provider'] === 'claude' && method_exists($api_data['api'], 'get_working_models')) {
            $response_data['claude_info'] = [
                'available_models' => count($api_data['api']->get_working_models()),
                'used_intelligent_selection' => true
            ];
        }
        
        wp_send_json_success($response_data);
        
    } catch (\Exception $e) {
        error_log('Alenseo Universal SEO Request Error: ' . $e->getMessage());
        wp_send_json_error(['message' => __('Fehler bei der SEO-Anfrage: ', 'alenseo') . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für erweiterten API-Status - NEU
 */
function alenseo_get_extended_api_status() {
    check_ajax_referer('alenseo_ajax_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unzureichende Berechtigungen.', 'alenseo')]);
        return;
    }
    
    try {
        $status = [
            'claude' => [
                'configured' => false, 
                'working' => false, 
                'message' => 'Nicht konfiguriert',
                'model_count' => 0,
                'fastest_model' => null,
                'recommended_model' => null,
                'model_details' => []
            ],
            'openai' => [
                'configured' => false, 
                'working' => false, 
                'message' => 'Nicht konfiguriert',
                'model' => null
            ],
            'overall' => [
                'has_working_api' => false,
                'recommended_provider' => null,
                'total_models_available' => 0
            ]
        ];
        
        // Claude Status prüfen
        if (class_exists('Alenseo_Claude_API')) {
            try {
                $claude_api = new Alenseo_Claude_API();
                $claude_status = $claude_api->get_api_status();
                
                if ($claude_status['configured']) {
                    $status['claude'] = array_merge($status['claude'], $claude_status);
                    $status['claude']['model_details'] = $claude_api->get_working_models();
                    $status['claude']['model_count'] = count($claude_api->get_working_models());
                    
                    if ($claude_status['working']) {
                        $status['overall']['has_working_api'] = true;
                        $status['overall']['total_models_available'] += $status['claude']['model_count'];
                        
                        if (!$status['overall']['recommended_provider']) {
                            $status['overall']['recommended_provider'] = 'claude';
                        }
                    }
                }
            } catch (\Exception $e) {
                $status['claude']['message'] = 'Fehler: ' . $e->getMessage();
                error_log('Alenseo Claude Status Error: ' . $e->getMessage());
            }
        }
        
        // OpenAI Status prüfen
        if (class_exists('Alenseo_ChatGPT_API')) {
            try {
                $openai_api = new Alenseo_ChatGPT_API();
                if ($openai_api->is_api_configured()) {
                    $status['openai']['configured'] = true;
                    
                    // Schneller Test
                    $test_result = $openai_api->test_api_key();
                    if (is_array($test_result) && isset($test_result['success']) && $test_result['success']) {
                        $status['openai']['working'] = true;
                        $status['openai']['message'] = 'API funktioniert';
                        $status['openai']['model'] = $test_result['model'] ?? 'gpt-3.5-turbo';
                        $status['overall']['has_working_api'] = true;
                        $status['overall']['total_models_available'] += 1;
                        
                        if (!$status['overall']['recommended_provider']) {
                            $status['overall']['recommended_provider'] = 'openai';
                        }
                    } else {
                        $status['openai']['message'] = $test_result['message'] ?? 'Test fehlgeschlagen';
                    }
                }
            } catch (\Exception $e) {
                $status['openai']['message'] = 'Fehler: ' . $e->getMessage();
                error_log('Alenseo OpenAI Status Error: ' . $e->getMessage());
            }
        }
        
        // Empfehlung basierend auf verfügbaren Features
        if ($status['claude']['working'] && $status['openai']['working']) {
            $status['overall']['recommended_provider'] = 'claude'; // Claude hat mehr Features
        }
        
        wp_send_json_success($status);
        
    } catch (\Exception $e) {
        error_log('Alenseo Extended API Status Error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Fehler beim Abrufen des API-Status: ' . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für das Speichern der API-Einstellungen - VERBESSERTE VERSION
 */
function alenseo_save_api_settings() {
    check_ajax_referer('alenseo_ajax_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unzureichende Berechtigungen.', 'alenseo')]);
        return;
    }
    
    $claude_api_key = isset($_POST['claude_api_key']) ? sanitize_text_field($_POST['claude_api_key']) : '';
    $claude_model = isset($_POST['claude_model']) ? sanitize_text_field($_POST['claude_model']) : 'claude-3-5-sonnet-20241022';
    $openai_api_key = isset($_POST['openai_api_key']) ? sanitize_text_field($_POST['openai_api_key']) : '';
    $openai_model = isset($_POST['openai_model']) ? sanitize_text_field($_POST['openai_model']) : 'gpt-3.5-turbo';
    $default_provider = isset($_POST['default_provider']) ? sanitize_text_field($_POST['default_provider']) : 'claude';
    $auto_select_model = isset($_POST['auto_select_model']) && $_POST['auto_select_model'] === 'true';
    
    try {
        // Einstellungen laden und aktualisieren
        $settings = get_option('alenseo_settings', []);
        $settings['claude_api_key'] = $claude_api_key;
        $settings['claude_default_model'] = $claude_model;
        $settings['openai_api_key'] = $openai_api_key;
        $settings['openai_model'] = $openai_model;
        $settings['default_ai_provider'] = $default_provider;
        $settings['auto_select_model'] = $auto_select_model;
        $settings['last_updated'] = current_time('mysql');
        
        // Validierung der API-Schlüssel vor dem Speichern
        $validation_results = [
            'claude' => false,
            'openai' => false
        ];
        
        if (!empty($claude_api_key)) {
            if (class_exists('Alenseo_Claude_API')) {
                $claude_api = new Alenseo_Claude_API($claude_api_key, $claude_model);
                $validation = $claude_api->validate_api_key();
                $validation_results['claude'] = !is_wp_error($validation);
            }
        }
        
        if (!empty($openai_api_key)) {
            if (class_exists('Alenseo_ChatGPT_API')) {
                $openai_api = new Alenseo_ChatGPT_API($openai_api_key, $openai_model);
                $validation = $openai_api->validate_api_key();
                $validation_results['openai'] = !is_wp_error($validation);
            }
        }
        
        $saved = update_option('alenseo_settings', $settings);
        
        if ($saved) {
            wp_send_json_success([
                'message' => __('Einstellungen erfolgreich gespeichert.', 'alenseo'),
                'settings' => $settings,
                'validation' => $validation_results,
                'recommendations' => [
                    'claude_configured' => $validation_results['claude'],
                    'openai_configured' => $validation_results['openai'],
                    'suggested_provider' => $validation_results['claude'] ? 'claude' : ($validation_results['openai'] ? 'openai' : null)
                ]
            ]);
        } else {
            wp_send_json_error(['message' => __('Fehler beim Speichern der Einstellungen.', 'alenseo')]);
        }
        
    } catch (\Exception $e) {
        error_log('Alenseo Save Settings Error: ' . $e->getMessage());
        wp_send_json_error(['message' => __('Fehler beim Speichern: ', 'alenseo') . $e->getMessage()]);
    }
}

/**
 * AJAX-Handler für Claude-Modell-Empfehlungen - NEU
 */
function alenseo_get_claude_model_recommendations() {
    check_ajax_referer('alenseo_ajax_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unzureichende Berechtigungen.', 'alenseo')]);
        return;
    }
    
    $task_type = isset($_POST['task_type']) ? sanitize_text_field($_POST['task_type']) : 'general';
    $prefer_speed = isset($_POST['prefer_speed']) && $_POST['prefer_speed'] === 'true';
    $prefer_quality = isset($_POST['prefer_quality']) && $_POST['prefer_quality'] === 'true';
    $prefer_cost = isset($_POST['prefer_cost']) && $_POST['prefer_cost'] === 'true';
    
    try {
        if (!class_exists('Alenseo_Claude_API')) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
        }
        
        $claude_api = new Alenseo_Claude_API();
        
        if (!$claude_api->is_api_configured()) {
            wp_send_json_error(['message' => __('Claude API nicht konfiguriert.', 'alenseo')]);
            return;
        }
        
        $working_models = $claude_api->get_working_models();
        $recommended_model = $claude_api->select_best_model($task_type, $prefer_speed, $prefer_quality, $prefer_cost);
        
        $recommendations = [
            'recommended_model' => $recommended_model,
            'model_info' => $working_models[$recommended_model] ?? null,
            'all_models' => $working_models,
            'task_specific_info' => [
                'task_type' => $task_type,
                'optimized_for' => $prefer_speed ? 'speed' : ($prefer_quality ? 'quality' : ($prefer_cost ? 'cost' : 'balance'))
            ]
        ];
        
        wp_send_json_success($recommendations);
        
    } catch (\Exception $e) {
        error_log('Alenseo Claude Model Recommendations Error: ' . $e->getMessage());
        wp_send_json_error(['message' => __('Fehler beim Abrufen der Modell-Empfehlungen: ', 'alenseo') . $e->getMessage()]);
    }
}

// AJAX-Handler registrieren
add_action('wp_ajax_alenseo_test_claude_api', __NAMESPACE__ . '\alenseo_test_claude_api_settings');
add_action('wp_ajax_alenseo_test_openai_api', __NAMESPACE__ . '\alenseo_test_openai_api_settings');
add_action('wp_ajax_alenseo_universal_seo_request', __NAMESPACE__ . '\alenseo_universal_seo_request');
add_action('wp_ajax_alenseo_get_extended_api_status', __NAMESPACE__ . '\alenseo_get_extended_api_status');
add_action('wp_ajax_alenseo_save_api_settings', __NAMESPACE__ . '\alenseo_save_api_settings');
add_action('wp_ajax_alenseo_get_claude_model_recommendations', __NAMESPACE__ . '\alenseo_get_claude_model_recommendations');

// Abwärtskompatibilität für alte Handler-Namen
add_action('wp_ajax_alenseo_test_api', __NAMESPACE__ . '\alenseo_test_claude_api_settings');
add_action('wp_ajax_alenseo_test_api_key', __NAMESPACE__ . '\alenseo_test_claude_api_settings');

// Registrierung der Einstellungen für WordPress
add_action('admin_init', function() {
    register_setting('alenseo_settings_group', 'alenseo_settings', [
        'sanitize_callback' => function($settings) {
            // Sanitize alle Einstellungen
            if (!is_array($settings)) {
                $settings = [];
            }
            
            $sanitized = [
                'claude_api_key' => isset($settings['claude_api_key']) ? sanitize_text_field($settings['claude_api_key']) : '',
                'claude_default_model' => isset($settings['claude_default_model']) ? sanitize_text_field($settings['claude_default_model']) : 'claude-3-5-sonnet-20241022',
                'openai_api_key' => isset($settings['openai_api_key']) ? sanitize_text_field($settings['openai_api_key']) : '',
                'openai_model' => isset($settings['openai_model']) ? sanitize_text_field($settings['openai_model']) : 'gpt-3.5-turbo',
                'default_ai_provider' => isset($settings['default_ai_provider']) ? sanitize_text_field($settings['default_ai_provider']) : 'claude',
                'auto_select_model' => isset($settings['auto_select_model']) ? (bool)$settings['auto_select_model'] : true,
                'last_updated' => current_time('mysql')
            ];
            
            return $sanitized;
        }
    ]);
    
    // Legacy-Settings für Abwärtskompatibilität
    register_setting('alenseo_settings_group', 'alenseo_claude_api_key');
    register_setting('alenseo_settings_group', 'alenseo_claude_model');
    register_setting('alenseo_settings_group', 'alenseo_openai_api_key');
    register_setting('alenseo_settings_group', 'alenseo_openai_model');
});