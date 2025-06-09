<?php
if (!defined('ABSPATH')) exit;

/**
 * SEO AI Master - Final Working Version with Project Key Support
 * 
 * @package SEO_AI_Master
 * @version 1.5.0
 * @author AlenSEO
 */

// Zentrale Felddefinitionen
function seo_ai_get_allowed_fields() {
    return array(
        'claude_api_key', 'claude_enabled', 'claude_priority',
        'openai_api_key', 'openai_enabled', 'openai_model',
        'gemini_api_key', 'gemini_enabled',
        'monthly_limit'
    );
}

function seo_ai_get_checkbox_fields() {
    return array('claude_enabled', 'openai_enabled', 'gemini_enabled');
}

// API Status Funktionen
function seo_ai_save_api_status($provider, $status, $message = '', $last_checked = null) {
    $current_statuses = get_option('seo_ai_api_status', array());
    
    $current_statuses[$provider] = array(
        'status' => $status,
        'message' => $message,
        'last_checked' => $last_checked ?: current_time('timestamp'),
        'last_checked_human' => current_time('Y-m-d H:i:s')
    );
    
    return update_option('seo_ai_api_status', $current_statuses);
}

function seo_ai_get_api_status($provider = null) {
    $statuses = get_option('seo_ai_api_status', array());
    
    if ($provider) {
        return isset($statuses[$provider]) ? $statuses[$provider] : array(
            'status' => 'disconnected',
            'message' => 'Noch nicht getestet',
            'last_checked' => null,
            'last_checked_human' => null
        );
    }
    
    return $statuses;
}

// AJAX Handler
add_action('wp_ajax_seo_ai_test_api', 'seo_ai_test_api_ajax_handler');
add_action('wp_ajax_seo_ai_save_settings', 'seo_ai_save_settings_ajax_handler');

// Enqueue Scripts
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'seo-ai-settings') !== false) {
        wp_enqueue_script(
            'seo-ai-settings-ajax',
            plugin_dir_url(__FILE__) . '../assets/js/settings-ajax.js',
            array('jquery'),
            '1.5.0',
            true
        );
        
        wp_localize_script('seo-ai-settings-ajax', 'seoAiAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_ai_ajax_nonce')
        ));
    }
});

/**
 * AJAX Handler für API-Tests
 */
function seo_ai_test_api_ajax_handler() {
    if (!wp_verify_nonce($_POST['nonce'], 'seo_ai_ajax_nonce')) {
        wp_send_json_error('Sicherheitsfehler');
        return;
    }
    
    $provider = sanitize_text_field($_POST['provider']);
    $api_key = sanitize_text_field($_POST['api_key']);
    
    $result = test_api_connection_real($provider, $api_key);
    
    // Speichere das Ergebnis dauerhaft
    $status = $result['success'] ? 'connected' : 'error';
    $message = $result['message'];
    
    seo_ai_save_api_status($provider, $status, $message);
    
    $result['last_checked'] = current_time('Y-m-d H:i:s');
    $result['timestamp'] = current_time('timestamp');
    
    wp_send_json($result);
}

/**
 * AJAX Handler für Einstellungen speichern
 */
function seo_ai_save_settings_ajax_handler() {
    if (!wp_verify_nonce($_POST['nonce'], 'seo_ai_ajax_nonce')) {
        wp_send_json_error('Sicherheitsfehler');
        return;
    }
    
    $allowed_fields = seo_ai_get_allowed_fields();
    $checkbox_fields = seo_ai_get_checkbox_fields();
    $settings = array();
    
    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            
            if (in_array($field, $checkbox_fields)) {
                $settings[$field] = ($value === '1' || $value === 'on') ? 1 : 0;
            } else {
                $settings[$field] = $value;
            }
        } else {
            if (in_array($field, $checkbox_fields)) {
                $settings[$field] = 0;
            }
        }
    }
    
    $current_settings = get_option('seo_ai_settings', array());
    $merged_settings = array_merge($current_settings, $settings);
    
    $updated = update_option('seo_ai_settings', $merged_settings);
    
    if ($updated || get_option('seo_ai_settings') === $merged_settings) {
        wp_send_json_success(array(
            'message' => 'Einstellungen erfolgreich gespeichert',
            'saved_fields' => count($settings)
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Fehler beim Speichern der Einstellungen'
        ));
    }
}

/**
 * API-Verbindungstests
 */
function test_api_connection_real($provider, $api_key) {
    if (empty($api_key)) {
        return array(
            'success' => false,
            'message' => 'API-Schlüssel fehlt'
        );
    }
    
    switch ($provider) {
        case 'claude':
            return test_claude_api_real($api_key);
        case 'openai':
            return test_openai_project_key($api_key);
        case 'gemini':
            return test_gemini_api_real($api_key);
        default:
            return array(
                'success' => false,
                'message' => 'Unbekannter Provider'
            );
    }
}

/**
 * Claude API Test
 */
function test_claude_api_real($api_key) {
    $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ),
        'body' => json_encode(array(
            'model' => 'claude-3-haiku-20240307',
            'max_tokens' => 10,
            'messages' => array(
                array('role' => 'user', 'content' => 'Hello')
            )
        )),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => 'Verbindungsfehler: ' . $response->get_error_message()
        );
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    
    if ($status_code === 200) {
        return array(
            'success' => true,
            'message' => 'Claude API erfolgreich verbunden ✅'
        );
    }
    
    return array(
        'success' => false,
        'message' => 'Claude Fehler (' . $status_code . ')'
    );
}

/**
 * SPEZIELLER OpenAI Project Key Test
 */
function test_openai_project_key($api_key) {
    // Erkenne Key-Typ
    $is_project_key = (strpos($api_key, 'sk-proj-') === 0);
    $is_org_key = (strpos($api_key, 'sk-') === 0 && !$is_project_key);
    
    if (!$is_project_key && !$is_org_key) {
        return array(
            'success' => false,
            'message' => 'Ungültiges API-Key Format (muss mit sk- oder sk-proj- beginnen)'
        );
    }
    
    // Modelle basierend auf Key-Typ
    $models_to_test = array();
    
    if ($is_project_key) {
        // Project Keys haben oft nur Zugriff auf bestimmte Modelle
        $models_to_test = array(
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-3.5-turbo',
            'gpt-4-turbo',
            'gpt-4'
        );
    } else {
        // Organization Keys haben meist vollen Zugriff
        $models_to_test = array(
            'gpt-3.5-turbo',
            'gpt-4o-mini',
            'gpt-4o',
            'gpt-4-turbo',
            'gpt-4'
        );
    }
    
    $last_error = '';
    $tested_count = 0;
    
    foreach ($models_to_test as $model) {
        $tested_count++;
        $result = test_openai_single_model($api_key, $model);
        
        if ($result['success']) {
            $key_type = $is_project_key ? 'Project Key' : 'Organization Key';
            return array(
                'success' => true,
                'message' => "OpenAI API verbunden ✅ ({$key_type}, {$model})",
                'model_used' => $model,
                'key_type' => $key_type
            );
        }
        
        $last_error = $result['message'];
        
        // Bei kritischen Fehlern abbrechen
        if (strpos($last_error, 'Ungültiger API-Key') !== false || 
            strpos($last_error, 'invalid_api_key') !== false ||
            strpos($last_error, 'Zugriff verweigert') !== false) {
            break;
        }
        
        // Bei Rate Limits abbrechen
        if (strpos($last_error, 'rate_limit') !== false) {
            break;
        }
        
        // Kurze Pause zwischen Tests
        usleep(500000); // 0.5 Sekunden
    }
    
    // Alle Tests fehlgeschlagen
    $key_type = $is_project_key ? 'Project Key' : 'Organization Key';
    return array(
        'success' => false,
        'message' => "OpenAI Test fehlgeschlagen ({$key_type}): {$last_error}",
        'models_tested' => $tested_count,
        'key_type' => $key_type,
        'last_error' => $last_error
    );
}

/**
 * Test einzelnes OpenAI Modell mit verbesserter Fehlerbehandlung
 */
function test_openai_single_model($api_key, $model) {
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
            'User-Agent' => 'SEO-AI-Master-WordPress/1.5'
        ),
        'body' => json_encode(array(
            'model' => $model,
            'messages' => array(
                array('role' => 'user', 'content' => 'Test')
            ),
            'max_tokens' => 1,
            'temperature' => 0
        )),
        'timeout' => 20
    ));
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => 'Netzwerkfehler: ' . $response->get_error_message()
        );
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    // Erfolg
    if ($status_code === 200) {
        $data = json_decode($body, true);
        if (isset($data['choices']) && is_array($data['choices'])) {
            return array(
                'success' => true,
                'message' => "Erfolgreich mit {$model}"
            );
        }
    }
    
    // Fehleranalyse
    $error_data = json_decode($body, true);
    
    if (isset($error_data['error'])) {
        $error = $error_data['error'];
        $error_code = isset($error['code']) ? $error['code'] : '';
        $error_msg = isset($error['message']) ? $error['message'] : '';
        
        switch ($error_code) {
            case 'invalid_api_key':
                return array(
                    'success' => false,
                    'message' => 'Ungültiger API-Key'
                );
                
            case 'model_not_found':
                return array(
                    'success' => false,
                    'message' => "Modell {$model} nicht verfügbar für diesen Key"
                );
                
            case 'insufficient_quota':
            case 'quota_exceeded':
                return array(
                    'success' => false,
                    'message' => 'Kontingent aufgebraucht - Billing aktivieren'
                );
                
            case 'rate_limit_exceeded':
                return array(
                    'success' => false,
                    'message' => 'Rate Limit erreicht'
                );
                
            case 'invalid_request_error':
                if (strpos($error_msg, 'does not exist') !== false) {
                    return array(
                        'success' => false,
                        'message' => "Modell {$model} existiert nicht"
                    );
                }
                break;
                
            case 'permission_denied':
                return array(
                    'success' => false,
                    'message' => 'Berechtigung für dieses Modell fehlt'
                );
        }
        
        // Fallback mit Original-Fehlermeldung
        return array(
            'success' => false,
            'message' => $error_msg ?: "OpenAI Fehler: {$error_code}"
        );
    }
    
    // HTTP Status-basierte Fehler
    switch ($status_code) {
        case 401:
            return array('success' => false, 'message' => 'Ungültiger API-Key (401)');
        case 403:
            return array('success' => false, 'message' => 'Zugriff verweigert (403) - Billing prüfen');
        case 429:
            return array('success' => false, 'message' => 'Rate Limit erreicht (429)');
        case 404:
            return array('success' => false, 'message' => "Modell {$model} nicht gefunden (404)");
        default:
            return array('success' => false, 'message' => "HTTP Fehler {$status_code}");
    }
}

/**
 * Gemini API Test
 */
function test_gemini_api_real($api_key) {
    $response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key, array(
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'contents' => array(
                array('parts' => array(array('text' => 'Hello')))
            )
        )),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => 'Verbindungsfehler: ' . $response->get_error_message()
        );
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    
    if ($status_code === 200) {
        return array(
            'success' => true,
            'message' => 'Gemini API erfolgreich verbunden ✅'
        );
    } else {
        $body = wp_remote_retrieve_body($response);
        $error_data = json_decode($body, true);
        
        return array(
            'success' => false,
            'message' => 'Gemini Fehler: ' . ($error_data['error']['message'] ?? 'Unbekannter Fehler')
        );
    }
}

// Lade aktuelle Einstellungen
$options = get_option('seo_ai_settings', array());

// Handle Form Submission (Fallback für Non-AJAX)
if (isset($_POST['seo_ai_nonce']) && wp_verify_nonce($_POST['seo_ai_nonce'], 'seo_ai_save_settings')) {
    $allowed_fields = seo_ai_get_allowed_fields();
    $checkbox_fields = seo_ai_get_checkbox_fields();
    $settings = array();
    
    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            
            if (in_array($field, $checkbox_fields)) {
                $settings[$field] = ($value === '1' || $value === 'on') ? 1 : 0;
            } else {
                $settings[$field] = $value;
            }
        } else {
            if (in_array($field, $checkbox_fields)) {
                $settings[$field] = 0;
            }
        }
    }
    
    $current_settings = get_option('seo_ai_settings', array());
    $merged_settings = array_merge($current_settings, $settings);
    
    $updated = update_option('seo_ai_settings', $merged_settings);
    $options = $merged_settings;
    
    if ($updated || get_option('seo_ai_settings') === $merged_settings) {
        echo '<div class="notice notice-success is-dismissible"><p>✅ Einstellungen erfolgreich gespeichert!</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>❌ Fehler beim Speichern der Einstellungen.</p></div>';
    }
}

// Lade gespeicherte API-Status
$api_statuses = seo_ai_get_api_status();
?>

<!-- Plugin Container -->
<div class="seo-ai-master-plugin">
    <div id="seo-ai-settings-page">
        <!-- Settings Sidebar -->
        <aside class="settings-sidebar">
            <h2 class="settings-title">
                <i class="fas fa-cogs"></i>
                Einstellungen
            </h2>
            
            <ul class="settings-nav">
                <li class="active" data-section="api-settings">
                    <i class="fas fa-key"></i>
                    API Konfiguration
                </li>
                <li data-section="general-settings">
                    <i class="fas fa-sliders-h"></i>
                    Allgemeine Einstellungen
                </li>
                <li data-section="automation-settings">
                    <i class="fas fa-magic"></i>
                    Automation Regeln
                    <span class="update-badge">Neu</span>
                </li>
                <li data-section="performance-settings">
                    <i class="fas fa-tachometer-alt"></i>
                    Performance
                </li>
                <li data-section="backup-settings">
                    <i class="fas fa-shield-alt"></i>
                    Backup & Restore
                </li>
                <li data-section="support-settings">
                    <i class="fas fa-life-ring"></i>
                    Support & Diagnostics
                </li>
            </ul>
        </aside>

        <!-- Hauptinhalt -->
        <main class="settings-content">
            <!-- API Configuration Section -->
            <section class="settings-section active" id="api-settings">
                <h2 class="section-title"><i class="fas fa-key"></i>API Konfiguration</h2>
                <p class="section-description">Optimiert für Project Keys (sk-proj-...) und Organization Keys (sk-...). Testet automatisch alle verfügbaren Modelle.</p>
                
                <form method="post" action="" id="seo-ai-api-form">
                    <?php wp_nonce_field('seo_ai_save_settings', 'seo_ai_nonce'); ?>
                    
                    <div class="api-provider-cards">
                        <!-- Claude API -->
                        <div class="api-card <?php echo !empty($options['claude_api_key']) ? 'has-key' : ''; ?>" id="claude-card">
                            <div class="api-card-header">
                                <h3 class="api-provider claude"><i class="fas fa-brain"></i>Claude 3.5 Sonnet (Anthropic)</h3>
                                <span class="api-badge primary">Primär</span>
                            </div>
                            
                            <div class="api-card-body">
                                <label class="form-label">API Key</label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-input api-key-input" 
                                           name="claude_api_key" 
                                           id="claude_api_key" 
                                           placeholder="sk-ant-api03-xxxxxxxx..." 
                                           value="<?php echo esc_attr($options['claude_api_key'] ?? ''); ?>"
                                           autocomplete="off">
                                    <i class="input-icon fas fa-eye" 
                                       data-input-id="claude_api_key"
                                       role="button"
                                       tabindex="0"></i>
                                </div>
                                <p class="form-help">Ihren API-Key finden Sie in der Anthropic Console unter "API Keys".</p>
                                
                                <div class="api-status <?php echo isset($api_statuses['claude']) ? $api_statuses['claude']['status'] : 'disconnected'; ?>" id="claude-status">
                                    <?php 
                                    if (isset($api_statuses['claude'])) {
                                        echo esc_html($api_statuses['claude']['message']);
                                        if ($api_statuses['claude']['last_checked_human']) {
                                            echo '<small style="display: block; margin-top: 0.25rem; opacity: 0.7;">Zuletzt geprüft: ' . esc_html($api_statuses['claude']['last_checked_human']) . '</small>';
                                        }
                                    } else {
                                        echo 'Nicht verbunden';
                                    }
                                    ?>
                                </div>
                                
                                <button type="button" class="btn" onclick="testApiConnection('claude', event)">
                                    <i class="fas fa-plug"></i>
                                    <span>Verbindung testen</span>
                                </button>
                                
                                <label class="form-label">Priorität</label>
                                <select class="form-select" name="claude_priority">
                                    <option value="1" <?php selected(($options['claude_priority'] ?? '1'),'1'); ?>>Höchste (1)</option>
                                    <option value="2" <?php selected(($options['claude_priority'] ?? '2'),'2'); ?>>Hoch (2)</option>
                                    <option value="3" <?php selected(($options['claude_priority'] ?? '3'),'3'); ?>>Normal (3)</option>
                                    <option value="4" <?php selected(($options['claude_priority'] ?? '4'),'4'); ?>>Niedrig (4)</option>
                                </select>
                                
                                <div class="api-toggle">
                                    <input type="checkbox" id="claude_enabled" name="claude_enabled" <?php checked(!empty($options['claude_enabled'])); ?>>
                                    <div class="toggle-switch<?php echo !empty($options['claude_enabled']) ? ' active' : ''; ?>" 
                                         data-input-id="claude_enabled"
                                         role="switch"
                                         tabindex="0">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <label for="claude_enabled" class="checkbox-label">API aktiviert</label>
                                </div>
                            </div>
                        </div>

                        <!-- OpenAI API - Optimiert für Project Keys -->
                        <div class="api-card <?php echo !empty($options['openai_api_key']) ? 'has-key' : ''; ?>" id="openai-card">
                            <div class="api-card-header">
                                <h3 class="api-provider openai"><i class="fas fa-robot"></i>GPT-4o & GPT-4 (OpenAI)</h3>
                                <span class="api-badge fallback">Project Key Ready</span>
                            </div>
                            
                            <div class="api-card-body">
                                <label class="form-label">API Key</label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-input api-key-input" 
                                           name="openai_api_key" 
                                           id="openai_api_key" 
                                           placeholder="sk-proj-... oder sk-..." 
                                           value="<?php echo esc_attr($options['openai_api_key'] ?? ''); ?>"
                                           autocomplete="off">
                                    <i class="input-icon fas fa-eye" 
                                       data-input-id="openai_api_key"
                                       role="button"
                                       tabindex="0"></i>
                                </div>
                                <p class="form-help">
                                    <strong>Project Keys optimiert:</strong> Testet automatisch verfügbare Modelle für Ihren Key-Typ.
                                </p>
                                
                                <div class="api-status <?php echo isset($api_statuses['openai']) ? $api_statuses['openai']['status'] : 'disconnected'; ?>" id="openai-status">
                                    <?php 
                                    if (isset($api_statuses['openai'])) {
                                        echo esc_html($api_statuses['openai']['message']);
                                        if ($api_statuses['openai']['last_checked_human']) {
                                            echo '<small style="display: block; margin-top: 0.25rem; opacity: 0.7;">Zuletzt geprüft: ' . esc_html($api_statuses['openai']['last_checked_human']) . '</small>';
                                        }
                                    } else {
                                        echo 'Nicht verbunden';
                                    }
                                    ?>
                                </div>
                                
                                <button type="button" class="btn" onclick="testApiConnection('openai', event)">
                                    <i class="fas fa-plug"></i>
                                    <span>Verbindung testen</span>
                                </button>
                                
                                <label class="form-label">Bevorzugtes Modell</label>
                                <select class="form-select" name="openai_model">
                                    <option value="gpt-4o-mini" <?php selected(($options['openai_model'] ?? 'gpt-4o-mini'),'gpt-4o-mini'); ?>>GPT-4o Mini (Empfohlen für Project Keys)</option>
                                    <option value="gpt-4o" <?php selected(($options['openai_model'] ?? ''),'gpt-4o'); ?>>GPT-4o (Neuestes)</option>
                                    <option value="gpt-3.5-turbo" <?php selected(($options['openai_model'] ?? ''),'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Kostengünstig)</option>
                                    <option value="gpt-4-turbo" <?php selected(($options['openai_model'] ?? ''),'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                    <option value="gpt-4" <?php selected(($options['openai_model'] ?? ''),'gpt-4'); ?>>GPT-4</option>
                                </select>
                                
                                <div class="api-toggle">
                                    <input type="checkbox" id="openai_enabled" name="openai_enabled" <?php checked(!empty($options['openai_enabled'])); ?>>
                                    <div class="toggle-switch<?php echo !empty($options['openai_enabled']) ? ' active' : ''; ?>" 
                                         data-input-id="openai_enabled"
                                         role="switch"
                                         tabindex="0">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <label for="openai_enabled" class="checkbox-label">API aktiviert</label>
                                </div>
                            </div>
                        </div>

                        <!-- Gemini API -->
                        <div class="api-card <?php echo !empty($options['gemini_api_key']) ? 'has-key' : ''; ?>" id="gemini-card">
                            <div class="api-card-header">
                                <h3 class="api-provider gemini"><i class="fas fa-gem"></i>Gemini Pro (Google)</h3>
                                <span class="api-badge">Kosteneffizient</span>
                            </div>
                            
                            <div class="api-card-body">
                                <label class="form-label">API Key</label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-input api-key-input" 
                                           name="gemini_api_key" 
                                           id="gemini_api_key" 
                                           placeholder="AIzaSyXXXXXXXXXXXXXXXXXXXXXX..." 
                                           value="<?php echo esc_attr($options['gemini_api_key'] ?? ''); ?>"
                                           autocomplete="off">
                                    <i class="input-icon fas fa-eye" 
                                       data-input-id="gemini_api_key"
                                       role="button"
                                       tabindex="0"></i>
                                </div>
                                <p class="form-help">Generieren Sie einen API-Key in der Google Cloud Console für Generative AI.</p>
                                
                                <div class="api-status <?php echo isset($api_statuses['gemini']) ? $api_statuses['gemini']['status'] : 'disconnected'; ?>" id="gemini-status">
                                    <?php 
                                    if (isset($api_statuses['gemini'])) {
                                        echo esc_html($api_statuses['gemini']['message']);
                                        if ($api_statuses['gemini']['last_checked_human']) {
                                            echo '<small style="display: block; margin-top: 0.25rem; opacity: 0.7;">Zuletzt geprüft: ' . esc_html($api_statuses['gemini']['last_checked_human']) . '</small>';
                                        }
                                    } else {
                                        echo 'Nicht verbunden';
                                    }
                                    ?>
                                </div>
                                
                                <button type="button" class="btn" onclick="testApiConnection('gemini', event)">
                                    <i class="fas fa-plug"></i>
                                    <span>Verbindung testen</span>
                                </button>
                                
                                <div class="api-toggle">
                                    <input type="checkbox" id="gemini_enabled" name="gemini_enabled" <?php checked(!empty($options['gemini_enabled'])); ?>>
                                    <div class="toggle-switch<?php echo !empty($options['gemini_enabled']) ? ' active' : ''; ?>" 
                                         data-input-id="gemini_enabled"
                                         role="switch"
                                         tabindex="0">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <label for="gemini_enabled" class="checkbox-label">API aktiviert</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Usage Limits -->
                    <div class="api-card">
                        <div class="api-card-header">
                            <h3 class="api-provider"><i class="fas fa-chart-line"></i>Usage Limits & Kosten</h3>
                        </div>
                        
                        <div class="api-card-body">
                            <label class="form-label">Maximale monatliche Kosten</label>
                            <p class="form-help">Plugin stoppt AI-Generierung automatisch wenn Limit erreicht wird.</p>
                            <input type="number" class="form-input" name="monthly_limit" value="<?php echo esc_attr($options['monthly_limit'] ?? '100'); ?>" min="10" step="10">
                            <span style="color: rgba(255,255,255,0.7); font-size: 0.8rem; margin-top: 0.25rem; display: block;">USD pro Monat</span>
                            
                            <div class="progress-container">
                                <div class="progress-label">
                                    <span>Aktueller Verbrauch</span>
                                    <span>$89.50 / $100.00</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 89.5%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save"></i>
                            <span>Einstellungen speichern</span>
                        </button>
                        <button class="btn btn-secondary" type="button" onclick="testAllAPIs(event)">
                            <i class="fas fa-check-circle"></i>
                            <span>Alle APIs testen</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Weitere Sections (Platzhalter) -->
            <section class="settings-section" id="general-settings">
                <h2><i class="fas fa-sliders-h"></i> Allgemeine Einstellungen</h2>
                <p>Hier werden die allgemeinen Einstellungen angezeigt...</p>
            </section>
            
            <section class="settings-section" id="automation-settings">
                <h2><i class="fas fa-magic"></i> Automation Regeln</h2>
                <p>Hier werden die Automation-Regeln konfiguriert...</p>
            </section>
            
            <section class="settings-section" id="performance-settings">
                <h2><i class="fas fa-tachometer-alt"></i> Performance</h2>
                <p>Hier werden die Performance-Einstellungen verwaltet...</p>
            </section>
            
            <section class="settings-section" id="backup-settings">
                <h2><i class="fas fa-shield-alt"></i> Backup & Restore</h2>
                <p>Hier können Backups erstellt und wiederhergestellt werden...</p>
            </section>
            
            <section class="settings-section" id="support-settings">
                <h2><i class="fas fa-life-ring"></i> Support & Diagnostics</h2>
                <p>Hier finden Sie Support-Informationen und Diagnose-Tools...</p>
            </section>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const settingsContainer = document.querySelector('.seo-ai-master-plugin');
    if (!settingsContainer) return;
    
    // Navigation
    const navItems = document.querySelectorAll('.seo-ai-master-plugin .settings-nav li');
    const sections = document.querySelectorAll('.seo-ai-master-plugin .settings-section');
    
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            const targetSection = this.getAttribute('data-section');
            
            navItems.forEach(nav => nav.classList.remove('active'));
            sections.forEach(section => section.classList.remove('active'));
            
            this.classList.add('active');
            const targetElement = document.getElementById(targetSection);
            if (targetElement) {
                targetElement.classList.add('active');
            }
        });
    });
    
    // Toggle Switches
    settingsContainer.addEventListener('click', function(e) {
        const toggleSwitch = e.target.closest('.toggle-switch');
        if (toggleSwitch) {
            const inputId = toggleSwitch.getAttribute('data-input-id');
            if (inputId) {
                const input = document.getElementById(inputId);
                const isActive = toggleSwitch.classList.contains('active');
                
                if (isActive) {
                    toggleSwitch.classList.remove('active');
                    input.checked = false;
                } else {
                    toggleSwitch.classList.add('active');
                    input.checked = true;
                }
            }
        }
    });
    
    // Password Visibility
    settingsContainer.addEventListener('click', function(e) {
        const eyeIcon = e.target.closest('.input-icon');
        if (eyeIcon && (eyeIcon.classList.contains('fa-eye') || eyeIcon.classList.contains('fa-eye-slash'))) {
            const inputId = eyeIcon.getAttribute('data-input-id');
            if (inputId) {
                const input = document.getElementById(inputId);
                const icon = input.nextElementSibling;
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }
    });
    
    loadSavedApiStatuses();
    setupFormSubmitHandler();
});

function loadSavedApiStatuses() {
    const providers = ['claude', 'openai', 'gemini'];
    
    providers.forEach(provider => {
        const statusElement = document.getElementById(provider + '-status');
        const cardElement = document.getElementById(provider + '-card');
        const apiKeyInput = document.getElementById(provider + '_api_key');
        
        if (statusElement && cardElement && apiKeyInput) {
            if (apiKeyInput.value.trim() !== '') {
                cardElement.classList.add('has-key');
            }
            
            const currentStatus = statusElement.className.match(/api-status\s+(\w+)/);
            if (currentStatus && currentStatus[1] !== 'disconnected') {
                if (currentStatus[1] === 'connected') {
                    cardElement.classList.add('connected');
                    cardElement.classList.remove('error');
                } else if (currentStatus[1] === 'error') {
                    cardElement.classList.add('error');
                    cardElement.classList.remove('connected');
                }
            }
        }
    });
}

function setupFormSubmitHandler() {
    const form = document.getElementById('seo-ai-api-form');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitButton = this.querySelector('button[type="submit"]');
        if (!submitButton) return;
        
        const originalHTML = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Speichere...';
        submitButton.disabled = true;
        
        const formData = new FormData(this);
        formData.append('action', 'seo_ai_save_settings');
        formData.append('nonce', (typeof seoAiAjax !== 'undefined') ? seoAiAjax.nonce : '');
        
        const toggles = ['claude_enabled', 'openai_enabled', 'gemini_enabled'];
        toggles.forEach(toggleId => {
            const checkbox = document.getElementById(toggleId);
            if (checkbox) {
                formData.set(toggleId, checkbox.checked ? '1' : '0');
            }
        });
        
        if (typeof jQuery === 'undefined' || typeof seoAiAjax === 'undefined') {
            submitButton.innerHTML = originalHTML;
            submitButton.disabled = false;
            this.submit();
            return;
        }
        
        jQuery.ajax({
            url: seoAiAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitButton.innerHTML = originalHTML;
                submitButton.disabled = false;
                
                if (response && response.success) {
                    showToast(response.data.message || 'Einstellungen gespeichert', 'success');
                } else {
                    const errorMsg = (response && response.data) ? response.data : 'Unbekannter Fehler';
                    showToast('Fehler beim Speichern: ' + errorMsg, 'error');
                }
            },
            error: function(xhr, status, error) {
                submitButton.innerHTML = originalHTML;
                submitButton.disabled = false;
                showToast('Netzwerkfehler beim Speichern: ' + error, 'error');
            }
        });
    });
}

function testApiConnection(provider, event) {
    if (!event || !event.target) return;
    
    const button = event.target.closest('.btn');
    const statusElement = document.getElementById(provider + '-status');
    const cardElement = document.getElementById(provider + '-card');
    const apiKeyInput = document.getElementById(provider + '_api_key');
    
    if (!button || !statusElement || !cardElement || !apiKeyInput) {
        showToast('❌ Interface-Fehler: Elemente nicht gefunden', 'error');
        return;
    }
    
    if (!apiKeyInput.value.trim()) {
        showToast('⚠️ Bitte geben Sie zuerst einen API-Key ein', 'warning');
        return;
    }
    
    button.classList.add('loading');
    button.disabled = true;
    statusElement.className = 'api-status loading';
    statusElement.innerHTML = 'Teste verschiedene Modelle...';
    
    if (typeof jQuery === 'undefined' || typeof seoAiAjax === 'undefined') {
        button.classList.remove('loading');
        button.disabled = false;
        statusElement.className = 'api-status error';
        statusElement.textContent = 'jQuery nicht verfügbar';
        showToast('❌ jQuery ist nicht geladen', 'error');
        return;
    }
    
    jQuery.ajax({
        url: seoAiAjax.ajaxurl,
        type: 'POST',
        data: {
            action: 'seo_ai_test_api',
            provider: provider,
            api_key: apiKeyInput.value,
            nonce: seoAiAjax.nonce
        },
        timeout: 60000, // 60 Sekunden für Multi-Model-Test
        success: function(response) {
            button.classList.remove('loading');
            button.disabled = false;
            
            if (response && response.success) {
                statusElement.className = 'api-status connected';
                
                let statusHTML = response.message || 'Verbunden';
                if (response.last_checked) {
                    statusHTML += '<small style="display: block; margin-top: 0.25rem; opacity: 0.7;">Zuletzt geprüft: ' + response.last_checked + '</small>';
                }
                statusElement.innerHTML = statusHTML;
                
                cardElement.classList.add('connected');
                cardElement.classList.remove('error');
                showToast('✅ Erfolgreich verbunden mit ' + provider.toUpperCase() + ' - Status gespeichert!', 'success');
            } else {
                const errorMsg = (response && response.message) ? response.message : 'Unbekannter Fehler';
                statusElement.className = 'api-status error';
                
                let errorHTML = errorMsg;
                if (response.last_checked) {
                    errorHTML += '<small style="display: block; margin-top: 0.25rem; opacity: 0.7;">Zuletzt geprüft: ' + response.last_checked + '</small>';
                }
                statusElement.innerHTML = errorHTML;
                
                cardElement.classList.add('error');
                cardElement.classList.remove('connected');
                showToast('❌ ' + provider.toUpperCase() + ' Fehler: ' + errorMsg, 'error');
            }
        },
        error: function(xhr, status, error) {
            button.classList.remove('loading');
            button.disabled = false;
            statusElement.className = 'api-status error';
            statusElement.innerHTML = 'Netzwerkfehler<small style="display: block; margin-top: 0.25rem; opacity: 0.7;">Zuletzt geprüft: ' + new Date().toLocaleString() + '</small>';
            cardElement.classList.add('error');
            cardElement.classList.remove('connected');
            
            showToast('🔴 Netzwerkfehler beim Testen von ' + provider.toUpperCase() + ': ' + error, 'error');
        }
    });
}

function testAllAPIs(event) {
    if (!event || !event.target) return;
    
    const button = event.target;
    const originalHTML = button.innerHTML;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Teste alle APIs...';
    button.disabled = true;
    
    const providers = ['claude', 'openai', 'gemini'];
    const validProviders = [];
    
    providers.forEach(provider => {
        const apiKeyInput = document.getElementById(provider + '_api_key');
        if (apiKeyInput && apiKeyInput.value.trim() !== '') {
            validProviders.push(provider);
        }
    });
    
    if (validProviders.length === 0) {
        button.innerHTML = originalHTML;
        button.disabled = false;
        showToast('⚠️ Keine API-Keys zum Testen gefunden', 'warning');
        return;
    }
    
    let completed = 0;
    const total = validProviders.length;
    
    validProviders.forEach((provider, index) => {
        setTimeout(() => {
            const testButton = document.querySelector(`#${provider}-card .btn`);
            if (testButton) {
                const artificialEvent = {
                    target: testButton,
                    preventDefault: function() {},
                    stopPropagation: function() {}
                };
                
                testApiConnection(provider, artificialEvent);
            }
            
            completed++;
            
            if (completed === total) {
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                    showToast(`✅ Alle API-Tests abgeschlossen! (${total} Provider getestet)`, 'success');
                }, 2000);
            }
        }, index * 3000); // 3 Sekunden zwischen Tests
    });
}

// Einfaches Toast System
function showToast(message, type = 'info', duration = 4000) {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#f59e0b'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        z-index: 1000;
        font-weight: 600;
        max-width: 400px;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, duration);
}
</script>