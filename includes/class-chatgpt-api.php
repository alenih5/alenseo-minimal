<?php
namespace Alenseo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatGPT (OpenAI) API-Klasse für Alenseo SEO - KORRIGIERTE VERSION
 * 
 * Behebt Sichtbarkeits-Konflikte mit der Basis-Klasse AI_API
 * 
 * @since 2.0.0
 * @package Alenseo
 */
class Alenseo_ChatGPT_API extends AI_API {
    private $api_key;
    private $model = 'gpt-3.5-turbo'; // KORRIGIERT: Sicheres Standard-Modell
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $available_models;
    private $last_request_time = 0;
    private $rate_limit_delay = 100; // ms zwischen Anfragen

    public function __construct($api_key = null, $model = null) {
        // KORRIGIERT: Reihenfolge geändert - Modelle zuerst initialisieren
        $this->initialize_available_models();
        
        if ($api_key) {
            $this->api_key = trim($api_key);
        } else {
            $this->load_settings();
        }
        
        if ($model && isset($this->available_models[$model])) {
            $this->model = $model;
        }
    }

    private function load_settings() {
        $settings = get_option('alenseo_settings', []);
        $this->api_key = isset($settings['openai_api_key']) ? trim($settings['openai_api_key']) : '';
        
        // KORRIGIERT: Sicheres Standard-Modell verwenden
        $saved_model = isset($settings['openai_model']) ? $settings['openai_model'] : 'gpt-3.5-turbo';
        
        // Nur setzen wenn das Modell in der verfügbaren Liste ist
        if (isset($this->available_models[$saved_model])) {
            $this->model = $saved_model;
        } else {
            $this->model = 'gpt-3.5-turbo'; // Fallback auf sicheres Modell
        }
    }

    /**
     * Verfügbare OpenAI-Modelle - KORRIGIERT: Nur verfügbare Modelle
     */
    private function initialize_available_models() {
        // Basis-Modelle die fast alle API-Schlüssel haben
        $this->available_models = [
            'gpt-3.5-turbo' => [
                'name' => 'GPT-3.5 Turbo',
                'description' => 'Schnell und kostengünstig - Standard für alle',
                'speed' => 'sehr_schnell',
                'cost' => 'niedrig',
                'quality' => 'gut',
                'max_tokens' => 4096,
                'best_for' => ['quick_tasks', 'simple_seo', 'bulk_processing', 'general'],
                'cost_per_1k_tokens' => 0.0015,
                'requires_access' => false // Grundmodell
            ],
            'gpt-3.5-turbo-1106' => [
                'name' => 'GPT-3.5 Turbo (Updated)',
                'description' => 'Verbesserte Version von GPT-3.5',
                'speed' => 'sehr_schnell',
                'cost' => 'niedrig',
                'quality' => 'gut',
                'max_tokens' => 4096,
                'best_for' => ['quick_tasks', 'simple_seo', 'bulk_processing'],
                'cost_per_1k_tokens' => 0.001,
                'requires_access' => false
            ],
            'gpt-4o-mini' => [
                'name' => 'GPT-4o Mini',
                'description' => 'Günstige GPT-4 Alternative',
                'speed' => 'schnell',
                'cost' => 'niedrig',
                'quality' => 'hoch',
                'max_tokens' => 8192,
                'best_for' => ['professional_seo', 'content_creation'],
                'cost_per_1k_tokens' => 0.00015,
                'requires_access' => false // Meist verfügbar
            ]
        ];
        
        // Premium-Modelle (nur wenn Zugang verfügbar)
        $premium_models = [
            'gpt-4' => [
                'name' => 'GPT-4',
                'description' => 'Höchste Qualität (erfordert speziellen Zugang)',
                'speed' => 'langsam',
                'cost' => 'hoch',
                'quality' => 'sehr_hoch',
                'max_tokens' => 8192,
                'best_for' => ['premium_content', 'complex_analysis', 'creative_writing'],
                'cost_per_1k_tokens' => 0.03,
                'requires_access' => true
            ],
            'gpt-4-turbo' => [
                'name' => 'GPT-4 Turbo',
                'description' => 'GPT-4 mit besserer Geschwindigkeit',
                'speed' => 'mittel',
                'cost' => 'mittel_hoch',
                'quality' => 'sehr_hoch',
                'max_tokens' => 128000,
                'best_for' => ['professional_seo', 'comprehensive_analysis'],
                'cost_per_1k_tokens' => 0.01,
                'requires_access' => true
            ],
            'gpt-4o' => [
                'name' => 'GPT-4o',
                'description' => 'Neuestes GPT-4 Modell',
                'speed' => 'mittel',
                'cost' => 'hoch',
                'quality' => 'sehr_hoch',
                'max_tokens' => 8192,
                'best_for' => ['premium_content', 'complex_analysis'],
                'cost_per_1k_tokens' => 0.005,
                'requires_access' => true
            ]
        ];
        
        // Premium-Modelle nur hinzufügen wenn sie getestet wurden
        // Werden später durch detect_available_models() validiert
    }

    /**
     * Verfügbare Modelle für diesen API-Schlüssel ermitteln
     */
    public function detect_available_models() {
        if (empty($this->api_key)) {
            return ['gpt-3.5-turbo']; // Fallback
        }
        
        $available = ['gpt-3.5-turbo']; // Basis-Modell ist fast immer verfügbar
        
        // Teste weitere verfügbare Modelle
        $models_to_test = ['gpt-4o-mini', 'gpt-3.5-turbo-1106', 'gpt-4o', 'gpt-4', 'gpt-4-turbo'];
        
        foreach ($models_to_test as $model) {
            if ($this->test_model_access($model)) {
                $available[] = $model;
            }
        }
        
        return $available;
    }

    /**
     * Teste ob ein spezifisches Modell verfügbar ist
     */
    private function test_model_access($model) {
        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Hi']
            ],
            'max_tokens' => 5
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 10,
            'method' => 'POST'
        ];

        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        return $code === 200; // Nur bei 200 ist das Modell verfügbar
    }

    /**
     * Intelligente Modell-Auswahl - KORRIGIERT: Nur verfügbare Modelle verwenden
     */
    public function select_best_model($task_type, $prefer_speed = false, $prefer_quality = false, $prefer_cost = false) {
        // Verfügbare Modelle ermitteln
        $available_model_ids = $this->detect_available_models();
        
        // Nur verfügbare Modelle berücksichtigen
        $suitable_models = [];
        foreach ($available_model_ids as $model_id) {
            if (isset($this->available_models[$model_id])) {
                $model_info = $this->available_models[$model_id];
                if (in_array($task_type, $model_info['best_for'])) {
                    $suitable_models[$model_id] = $model_info;
                }
            }
        }
        
        // Fallback: Alle verfügbaren Modelle wenn keine spezifische Eignung
        if (empty($suitable_models)) {
            foreach ($available_model_ids as $model_id) {
                if (isset($this->available_models[$model_id])) {
                    $suitable_models[$model_id] = $this->available_models[$model_id];
                }
            }
        }
        
        // Fallback: gpt-3.5-turbo wenn gar nichts verfügbar
        if (empty($suitable_models)) {
            return 'gpt-3.5-turbo';
        }
        
        // Nach Präferenz sortieren
        if ($prefer_speed) {
            // Nach Geschwindigkeit sortieren
            $speed_order = ['sehr_schnell' => 1, 'schnell' => 2, 'mittel' => 3, 'langsam' => 4];
            uasort($suitable_models, function($a, $b) use ($speed_order) {
                return $speed_order[$a['speed']] <=> $speed_order[$b['speed']];
            });
        } elseif ($prefer_cost) {
            // Nach Kosten sortieren
            uasort($suitable_models, function($a, $b) {
                return $a['cost_per_1k_tokens'] <=> $b['cost_per_1k_tokens'];
            });
        } elseif ($prefer_quality) {
            // Nach Qualität sortieren
            $quality_order = ['sehr_hoch' => 1, 'hoch' => 2, 'gut' => 3];
            uasort($suitable_models, function($a, $b) use ($quality_order) {
                return $quality_order[$a['quality']] <=> $quality_order[$b['quality']];
            });
        } else {
            // Standard: Bestes verfügbares Modell bevorzugen
            if (in_array('gpt-4o-mini', $available_model_ids)) {
                return 'gpt-4o-mini';
            }
            if (in_array('gpt-3.5-turbo-1106', $available_model_ids)) {
                return 'gpt-3.5-turbo-1106';
            }
            if (isset($suitable_models['gpt-3.5-turbo'])) {
                return 'gpt-3.5-turbo';
            }
        }
        
        return array_key_first($suitable_models);
    }

    public function set_api_key($key) {
        $this->api_key = trim($key);
    }

    public function set_model($model) {
        if (isset($this->available_models[$model])) {
            $this->model = $model;
        }
    }

    public function is_api_configured() {
        return !empty($this->api_key);
    }

    public function get_available_models() {
        return $this->available_models;
    }

    /**
     * Text generieren mit Rate-Limiting
     */
    public function generate_text($prompt, $options = []) {
        // Rate-Limiting (jetzt protected statt private)
        $this->enforce_rate_limit();
        
        $defaults = [
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'system_prompt' => 'Du bist ein SEO-Experte, der bei der Optimierung von Website-Inhalten hilft. Antworte präzise und auf Deutsch.',
            'model' => $this->model
        ];
        $options = wp_parse_args($options, $defaults);

        // Modell-spezifische Anpassungen
        $model_info = $this->available_models[$options['model']] ?? $this->available_models[$this->model];
        if ($options['max_tokens'] > $model_info['max_tokens']) {
            $options['max_tokens'] = $model_info['max_tokens'];
        }

        $messages = [
            ['role' => 'system', 'content' => $options['system_prompt']],
            ['role' => 'user', 'content' => $prompt]
        ];

        $body = [
            'model' => $options['model'],
            'messages' => $messages,
            'max_tokens' => absint($options['max_tokens']),
            'temperature' => floatval($options['temperature'])
        ];

        $response = $this->make_api_request($body);
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->extract_text_from_response($response);
    }

    /**
     * Intelligente Text-Generierung mit automatischer Modell-Auswahl (ähnlich wie Claude)
     */
    public function generate_text_smart($prompt, $task_type = 'general', $options = []) {
        $defaults = [
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'system_prompt' => 'Du bist ein SEO-Experte, der bei der Optimierung von Website-Inhalten hilft. Antworte präzise und auf Deutsch.',
            'prefer_speed' => false,
            'prefer_quality' => false,
            'prefer_cost' => false,
            'fallback_enabled' => true
        ];
        $options = wp_parse_args($options, $defaults);

        // Bestes Modell für die Aufgabe auswählen
        $selected_model = $this->select_best_model(
            $task_type, 
            $options['prefer_speed'], 
            $options['prefer_quality'], 
            $options['prefer_cost']
        );
        
        $options['model'] = $selected_model;
        
        if (WP_DEBUG) {
            error_log("Alenseo OpenAI: Verwende Modell $selected_model für Aufgabe $task_type");
        }
        
        return $this->generate_text($prompt, $options);
    }

    /**
     * Keywords generieren - optimiert
     */
    public function generate_keywords($title, $content) {
        $prompt = "Analysiere den folgenden Beitrag und generiere 5 relevante SEO-Keywords. Gib nur die Keywords zurück, getrennt durch Kommas, ohne zusätzliche Erklärungen.\n\nTitel: " . $this->clean_text($title) . "\n\nInhalt: " . $this->clean_text(substr($content, 0, 1000));
        
        $result = $this->generate_text_smart($prompt, 'quick_tasks', [
            'max_tokens' => 100,
            'temperature' => 0.5,
            'prefer_speed' => true // Verwende schnellstes Modell
        ]);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $keywords = preg_split('/,\s*/', trim($result));
        return array_map('trim', array_filter($keywords));
    }

    /**
     * Content optimieren
     */
    public function optimize_content($content, $keyword = '') {
        $content_clean = $this->clean_text($content);
        $prompt = "Optimiere den folgenden Text für SEO";
        if (!empty($keyword)) {
            $prompt .= " mit Fokus auf das Keyword '{$keyword}'";
        }
        $prompt .= ". Behalte die ursprüngliche Struktur bei und verbessere nur die SEO-Aspekte:\n\n{$content_clean}";
        
        return $this->generate_text_smart($prompt, 'professional_seo', [
            'max_tokens' => 1500,
            'temperature' => 0.6,
            'prefer_quality' => true // Verwende qualitativ besseres Modell
        ]);
    }

    /**
     * Meta-Description optimieren
     */
    public function optimize_meta_description($title, $content, $keyword = '') {
        $title_clean = $this->clean_text($title);
        $content_clean = $this->clean_text(substr($content, 0, 500));
        
        $prompt = "Schreibe eine SEO-optimierte Meta-Description (maximal 155 Zeichen) für folgenden Inhalt";
        if (!empty($keyword)) {
            $prompt .= " mit dem Keyword '{$keyword}'";
        }
        $prompt .= ":\n\nTitel: {$title_clean}\nInhalt: {$content_clean}\n\nNur die Meta-Description zurückgeben, keine Erklärungen:";
        
        return $this->generate_text_smart($prompt, 'simple_seo', [
            'max_tokens' => 80,
            'temperature' => 0.5,
            'prefer_speed' => true
        ]);
    }

    /**
     * Meta-Title generieren
     */
    public function generate_meta_title($content, $keyword = '') {
        $content_clean = $this->clean_text(substr($content, 0, 500));
        
        $prompt = "Schreibe einen SEO-optimierten Meta-Title (maximal 60 Zeichen) für folgenden Inhalt";
        if (!empty($keyword)) {
            $prompt .= " mit dem Keyword '{$keyword}'";
        }
        $prompt .= ":\n\n{$content_clean}\n\nNur den Meta-Title zurückgeben, keine Erklärungen:";
        
        return $this->generate_text_smart($prompt, 'simple_seo', [
            'max_tokens' => 40,
            'temperature' => 0.5,
            'prefer_speed' => true
        ]);
    }

    /**
     * SEO-Optimierungsvorschläge
     */
    public function get_optimization_suggestions($content) {
        $content_clean = $this->clean_text(substr($content, 0, 1000));
        
        $prompt = "Analysiere den folgenden Text und gib konkrete SEO-Optimierungsvorschläge:\n\n{$content_clean}\n\nBitte strukturierte Vorschläge in Stichpunkten:";
        
        return $this->generate_text_smart($prompt, 'detailed_analysis', [
            'max_tokens' => 500,
            'temperature' => 0.6,
            'prefer_quality' => true
        ]);
    }

    /**
     * Keyword-Analyse
     */
    public function analyze_keywords($content) {
        $content_clean = $this->clean_text(substr($content, 0, 1000));
        
        $prompt = "Analysiere den folgenden Text und identifiziere die wichtigsten Keywords und deren Häufigkeit:\n\n{$content_clean}\n\nGib die Top 10 Keywords mit ihrer Relevanz zurück:";
        
        return $this->generate_text_smart($prompt, 'detailed_analysis', [
            'max_tokens' => 300,
            'temperature' => 0.3
        ]);
    }

    /**
     * API-Schlüssel validieren
     */
    public function validate_api_key($key = null) {
        $key_to_validate = $key ?: $this->api_key;
        
        if (empty($key_to_validate)) {
            return new \WP_Error('no_api_key', 'Kein API-Schlüssel vorhanden.');
        }
        
        // OpenAI API-Key Format: sk-... oder sk-proj-... (neue project keys)
        // Erlaubt: Buchstaben, Zahlen, Unterstriche, Bindestriche
        if (!preg_match('/^sk(-proj)?-[a-zA-Z0-9_-]{20,}$/', $key_to_validate)) {
            return new \WP_Error('invalid_api_key', 'Ungültiges OpenAI API-Schlüssel-Format. Erwartet: sk-... oder sk-proj-...');
        }
        
        return true;
    }

    /**
     * API-Schlüssel testen - KORRIGIERT: Verwendet verfügbare Modelle
     */
    public function test_api_key() {
        $validation = $this->validate_api_key();
        if (is_wp_error($validation)) {
            return [
                'success' => false,
                'message' => $validation->get_error_message()
            ];
        }
        
        // Ermittle verfügbare Modelle
        $available_models = $this->detect_available_models();
        $test_model = $available_models[0]; // Verwende das erste verfügbare Modell
        
        $result = $this->generate_text('Antworte nur mit: API Test erfolgreich', [
            'max_tokens' => 10,
            'temperature' => 0.1,
            'model' => $test_model
        ]);
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => 'API-Verbindung fehlgeschlagen: ' . $result->get_error_message()
            ];
        }
        
        return [
            'success' => true,
            'message' => 'OpenAI API-Verbindung erfolgreich! ' . count($available_models) . ' Modelle verfügbar.',
            'model_used' => $test_model,
            'response' => $result,
            'available_models' => $available_models,
            'total_models' => count($available_models),
            'has_gpt4_access' => in_array('gpt-4', $available_models) || in_array('gpt-4o', $available_models),
            'recommended_model' => $this->get_recommended_model($available_models)
        ];
    }

    /**
     * Empfohlenes Modell basierend auf verfügbaren Modellen
     */
    private function get_recommended_model($available_models) {
        // Präferenz-Reihenfolge (beste zuerst)
        $preference = ['gpt-4o', 'gpt-4o-mini', 'gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo-1106', 'gpt-3.5-turbo'];
        
        foreach ($preference as $preferred) {
            if (in_array($preferred, $available_models)) {
                return $preferred;
            }
        }
        
        return 'gpt-3.5-turbo'; // Fallback
    }

    /**
     * API-Status abrufen - KORRIGIERT: Realistische Modell-Informationen
     */
    public function get_api_status() {
        if (!$this->is_api_configured()) {
            return [
                'configured' => false,
                'message' => 'OpenAI API nicht konfiguriert',
                'available_models' => 0
            ];
        }

        $available_models = $this->detect_available_models();
        $has_gpt4 = in_array('gpt-4', $available_models) || in_array('gpt-4o', $available_models);

        return [
            'configured' => true,
            'working' => true,
            'message' => count($available_models) . ' OpenAI-Modelle verfügbar',
            'available_models' => count($available_models),
            'available_model_list' => $available_models,
            'current_model' => $this->model,
            'fastest_model' => 'gpt-3.5-turbo',
            'best_quality' => $has_gpt4 ? 'gpt-4o' : 'gpt-4o-mini',
            'recommended' => $this->get_recommended_model($available_models),
            'has_gpt4_access' => $has_gpt4,
            'access_level' => $has_gpt4 ? 'Premium (GPT-4 verfügbar)' : 'Standard (GPT-3.5/4o-mini)'
        ];
    }

    /**
     * Rate-Limiting durchsetzen - KORRIGIERT: Jetzt kompatibel mit Parent-Klasse
     */
    protected function enforce_rate_limit($delay_ms = null) {
        // Verwende den übergebenen Wert oder Fallback auf die Instanz-Variable
        $delay = $delay_ms !== null ? $delay_ms : $this->rate_limit_delay;
        
        $current_time = microtime(true) * 1000; // ms
        $time_since_last = $current_time - $this->last_request_time;
        
        if ($time_since_last < $delay) {
            $sleep_time = ($delay - $time_since_last) * 1000; // μs
            usleep(intval($sleep_time));
        }
        
        $this->last_request_time = microtime(true) * 1000;
    }

    /**
     * API-Anfrage durchführen - KORRIGIERT: protected statt private
     */
    protected function make_api_request($body) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', 'Kein API-Schlüssel konfiguriert.');
        }
        
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
            'method' => 'POST'
        ];
        
        $this->log("OpenAI API Request to: {$this->api_url}");
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            $this->log("OpenAI API Request Error: " . $response->get_error_message(), 'error');
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            $error_data = json_decode($body_response, true);
            $error_message = 'OpenAI API-Fehler (Code: ' . $code . ')';
            
            if (isset($error_data['error']['message'])) {
                $error_message .= ': ' . $error_data['error']['message'];
            } else {
                $error_message .= ': ' . $body_response;
            }
            
            $this->log("OpenAI API Response Error: " . $error_message, 'error');
            return new \WP_Error('api_error', $error_message);
        }
        
        $decoded = json_decode($body_response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON Decode Error: " . json_last_error_msg(), 'error');
            return new \WP_Error('api_error', 'Fehler beim Dekodieren der OpenAI API-Antwort.');
        }
        
        return $decoded;
    }

    /**
     * Text aus API-Antwort extrahieren - KORRIGIERT: protected statt private
     */
    protected function extract_text_from_response($response) {
        if (!isset($response['choices'][0]['message']['content'])) {
            $this->log("Invalid OpenAI Response Structure", 'error');
            return new \WP_Error('api_error', 'Keine gültige Antwort von der OpenAI API erhalten.');
        }
        
        return trim($response['choices'][0]['message']['content']);
    }

    /**
     * Test API connection with specific key and model
     */
    public function test_api_connection($api_key = null, $model = null) {
        $test_key = $api_key ?: $this->api_key;
        $test_model = $model ?: $this->model;
        
        if (empty($test_key)) {
            return [
                'success' => false,
                'message' => 'Kein API-Schlüssel angegeben.'
            ];
        }
        
        // Temporarily set the API key for testing
        $original_key = $this->api_key;
        $original_model = $this->model;
        $this->api_key = $test_key;
        $this->model = $test_model;
        
        try {
            $result = $this->generate_text('Antworte nur mit: Test OK', [
                'max_tokens' => 10,
                'temperature' => 0.1
            ]);
            
            // Restore original settings
            $this->api_key = $original_key;
            $this->model = $original_model;
            
            if (is_wp_error($result)) {
                return [
                    'success' => false,
                    'message' => 'API Test fehlgeschlagen: ' . $result->get_error_message()
                ];
            }
            
            return [
                'success' => true,
                'message' => 'OpenAI API Test erfolgreich!',
                'data' => [
                    'model' => $test_model,
                    'response' => $result
                ]
            ];
            
        } catch (Exception $e) {
            // Restore original settings
            $this->api_key = $original_key;
            $this->model = $original_model;
            
            return [
                'success' => false,
                'message' => 'Test Fehler: ' . $e->getMessage()
            ];
        }
    }
}