<?php
namespace Alenseo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Claude (Anthropic) API-Klasse für Alenseo SEO
 * 
 * @since 2.0.0
 * @package Alenseo
 */
class Alenseo_Claude_API extends AI_API {
    private $api_key;
    private $model = 'claude-3-5-sonnet-20241022';
    private $api_url = 'https://api.anthropic.com/v1/messages';
    private $available_models;
    private $last_request_time = 0;
    private $rate_limit_delay = 100; // ms zwischen Anfragen

    public function __construct($api_key = null, $model = null) {
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
        $this->api_key = isset($settings['claude_api_key']) ? trim($settings['claude_api_key']) : '';
        
        $saved_model = isset($settings['claude_default_model']) ? $settings['claude_default_model'] : 'claude-3-5-sonnet-20241022';
        
        if (isset($this->available_models[$saved_model])) {
            $this->model = $saved_model;
        }
    }

    private function initialize_available_models() {
        $this->available_models = [
            'claude-3-haiku-20240307' => [
                'name' => 'Claude 3 Haiku',
                'description' => 'Schnellstes Modell für einfache Aufgaben',
                'speed' => 'sehr_schnell',
                'cost' => 'niedrig',
                'quality' => 'gut',
                'max_tokens' => 4096,
                'best_for' => ['quick_tasks', 'simple_seo', 'bulk_processing'],
                'avg_response_time' => 456
            ],
            'claude-3-5-sonnet-20241022' => [
                'name' => 'Claude 3.5 Sonnet',
                'description' => 'Empfohlenes Modell - beste Balance',
                'speed' => 'schnell',
                'cost' => 'mittel',
                'quality' => 'sehr_hoch',
                'max_tokens' => 8192,
                'best_for' => ['professional_seo', 'content_creation', 'analysis'],
                'avg_response_time' => 853
            ],
            'claude-3-opus-20240229' => [
                'name' => 'Claude 3 Opus',
                'description' => 'Höchste Qualität für komplexe Aufgaben',
                'speed' => 'langsam',
                'cost' => 'hoch',
                'quality' => 'exzellent',
                'max_tokens' => 4096,
                'best_for' => ['complex_analysis', 'premium_content'],
                'avg_response_time' => 2222
            ]
        ];
    }

    // Basis-Funktionalität
    public function is_api_configured() {
        return !empty($this->api_key) && $this->validate_api_key();
    }

    public function validate_api_key($key = null) {
        $test_key = $key ?: $this->api_key;
        
        if (empty($test_key)) {
            return new \WP_Error('no_api_key', 'Kein Claude API-Schlüssel gefunden.');
        }
        
        // Claude API-Schlüssel Format prüfen
        if (!preg_match('/^sk-ant-(?:api\d{2}-)?[a-zA-Z0-9_-]{50,}$/', $test_key)) {
            return new \WP_Error('invalid_format', 'Ungültiges Claude API-Schlüssel Format.');
        }
        
        return true;
    }

    public function test_api_key() {
        $validation = $this->validate_api_key();
        if (is_wp_error($validation)) {
            return [
                'success' => false,
                'message' => $validation->get_error_message()
            ];
        }
        
        $result = $this->generate_text('Antworte nur mit: API Test erfolgreich', [
            'max_tokens' => 10
        ]);
        
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => 'API-Verbindung fehlgeschlagen: ' . $result->get_error_message()
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Claude API-Verbindung erfolgreich!',
            'model_used' => $this->model,
            'response' => $result,
            'model_count' => count($this->available_models),
            'fastest_model' => 'claude-3-haiku-20240307',
            'recommended_model' => 'claude-3-5-sonnet-20241022'
        ];
    }

    public function test_api_connection($api_key = null, $model = null) {
        $test_key = $api_key ?: $this->api_key;
        $test_model = $model ?: $this->model;
        
        if (empty($test_key)) {
            return [
                'success' => false,
                'message' => 'Kein API-Schlüssel angegeben.'
            ];
        }
        
        // Test API call
        $body = [
            'model' => $test_model,
            'max_tokens' => 10,
            'messages' => [
                ['role' => 'user', 'content' => 'Sage nur: Test OK']
            ]
        ];

        $args = [
            'headers' => [
                'x-api-key' => $test_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ],
            'body' => json_encode($body),
            'timeout' => 30,
            'method' => 'POST'
        ];

        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Verbindungsfehler: ' . $response->get_error_message()
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        
        if ($code === 200) {
            $decoded = json_decode($body_response, true);
            if (isset($decoded['content'][0]['text'])) {
                return [
                    'success' => true,
                    'message' => 'Claude API Test erfolgreich!',
                    'data' => [
                        'model_used' => $test_model,
                        'response' => $decoded['content'][0]['text'],
                        'model_count' => count($this->available_models),
                        'fastest_model' => 'claude-3-haiku-20240307',
                        'recommended_model' => 'claude-3-5-sonnet-20241022'
                    ]
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'API Test fehlgeschlagen (Code: ' . $code . ')'
        ];
    }

    public function generate_text($prompt, $options = []) {
        if (!$this->is_api_configured()) {
            return new \WP_Error('api_not_configured', 'Claude API nicht konfiguriert.');
        }
        
        $max_tokens = isset($options['max_tokens']) ? $options['max_tokens'] : 1000;
        $model = isset($options['model']) ? $options['model'] : $this->model;
        
        $body = [
            'model' => $model,
            'max_tokens' => $max_tokens,
            'messages' => [
                ['role' => 'user', 'content' => $this->sanitize_prompt($prompt)]
            ]
        ];

        $args = [
            'headers' => [
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ],
            'body' => json_encode($body),
            'timeout' => 60,
            'method' => 'POST'
        ];

        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            return new \WP_Error('api_error', 'Claude API Fehler: ' . $code);
        }
        
        $decoded = json_decode($body_response, true);
        
        if (isset($decoded['content'][0]['text'])) {
            return $decoded['content'][0]['text'];
        }
        
        return new \WP_Error('parse_error', 'Unerwartete API-Antwort');
    }

    // SEO-spezifische Methoden
    public function generate_keywords($title, $content) {
        $prompt = "Generiere 10 relevante SEO-Keywords für diesen Inhalt:\n\nTitel: {$title}\n\nInhalt: " . $this->truncate_text($content, 800) . "\n\nAntworte nur mit einer kommagetrennten Liste der Keywords.";
        return $this->generate_text($prompt);
    }

    public function optimize_content($content, $keyword = '') {
        $keyword_text = $keyword ? " für das Keyword '{$keyword}'" : '';
        $prompt = "Optimiere diesen Inhalt für SEO{$keyword_text}:\n\n" . $this->truncate_text($content, 1000) . "\n\nGib den optimierten Inhalt zurück.";
        return $this->generate_text($prompt);
    }

    public function optimize_meta_description($title, $content, $keyword = '') {
        $keyword_text = $keyword ? " und das Keyword '{$keyword}'" : '';
        $prompt = "Schreibe eine optimale Meta-Description (150-160 Zeichen) für:\n\nTitel: {$title}\nInhalt: " . $this->truncate_text($content, 500) . "{$keyword_text}\n\nAntworte nur mit der Meta-Description.";
        return $this->generate_text($prompt);
    }

    public function generate_meta_title($content, $keyword = '') {
        $keyword_text = $keyword ? " mit dem Keyword '{$keyword}'" : '';
        $prompt = "Generiere einen optimalen SEO-Titel (50-60 Zeichen){$keyword_text} für:\n\n" . $this->truncate_text($content, 500) . "\n\nAntworte nur mit dem Titel.";
        return $this->generate_text($prompt);
    }

    public function get_optimization_suggestions($content) {
        $prompt = "Analysiere diesen Inhalt für SEO-Optimierungen:\n\n" . $this->truncate_text($content, 1000) . "\n\nGib konkrete Verbesserungsvorschläge für:\n1. Keyword-Optimierung\n2. Struktur und Lesbarkeit\n3. Meta-Tags\n4. Technische SEO\n\nAntworte in strukturierter Form.";
        return $this->generate_text($prompt, ['max_tokens' => 1500]);
    }

    public function analyze_keywords($content) {
        $prompt = "Analysiere die Keyword-Dichte und -Verteilung in diesem Text:\n\n" . $this->truncate_text($content, 1000) . "\n\nGib eine strukturierte Analyse zurück mit:\n1. Haupt-Keywords\n2. Keyword-Dichte\n3. Verbesserungsvorschläge";
        return $this->generate_text($prompt);
    }

    // Konfiguration
    public function set_api_key($key) {
        $this->api_key = trim($key);
    }

    public function set_model($model) {
        if (isset($this->available_models[$model])) {
            $this->model = $model;
        }
    }

    public function get_available_models() {
        return $this->available_models;
    }
}
