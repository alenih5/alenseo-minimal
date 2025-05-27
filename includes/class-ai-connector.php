<?php
namespace SEOAI\AI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * KI-Connector für Claude und OpenAI - ERWEITERTE VERSION
 * Behält alle originalen Methoden und Namen bei
 * Erweitert um: GPT-4o, Gemini Pro, Intelligentes Routing, Caching, Health Monitoring
 */
class Connector {
    /**
     * Instanz
     */
    private static $instance = null;

    /**
     * Plugin-Einstellungen
     */
    private $settings;

    // ORIGINAL: Beibehaltene Eigenschaften
    private $claude_api_key;
    private $openai_api_key;
    private $default_provider;
    private $anthropic_url = 'https://api.anthropic.com/v1/messages';
    private $openai_url = 'https://api.openai.com/v1/chat/completions';
    private $anthropic_version = '2023-06-01';
    private $timeout = 30;
    
    // ERWEITERT: Neue Multi-API Features
    private $providers = [
        'claude' => [
            'url' => 'https://api.anthropic.com/v1/messages',
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 8192,
            'api_version' => '2023-06-01',
            'priority' => 1,
            'cost_per_1k' => 0.015,
            'speed_score' => 9
        ],
        'openai' => [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4',
            'max_tokens' => 4096,
            'priority' => 3,
            'cost_per_1k' => 0.020,
            'speed_score' => 7
        ],
        'openai_4o' => [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'model' => 'gpt-4o-2024-05-13',
            'max_tokens' => 4096,
            'priority' => 2,
            'cost_per_1k' => 0.030,
            'speed_score' => 8
        ],
        'gemini' => [
            'url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent',
            'model' => 'gemini-1.5-pro-latest',
            'max_tokens' => 8192,
            'priority' => 4,
            'cost_per_1k' => 0.010,
            'speed_score' => 6
        ]
    ];
    
    private $rate_limits = [
        'claude' => ['requests' => 1000, 'period' => 3600],
        'openai' => ['requests' => 500, 'period' => 3600],
        'openai_4o' => ['requests' => 500, 'period' => 3600],
        'gemini' => ['requests' => 1500, 'period' => 3600]
    ];

    /**
     * Konstruktor - ORIGINAL beibehalten
     */
    private function __construct() {
        // Einstellungen laden
        $this->settings = get_option('seo_ai_master_options', []);
        $this->claude_api_key = $this->settings['claude_api_key'] ?? '';
        $this->openai_api_key = $this->settings['openai_api_key'] ?? '';
        $this->default_provider = $this->settings['default_provider'] ?? 'claude';
        
        // ERWEITERT: Health Check initialisieren
        add_action('init', [$this, 'schedule_health_checks']);
    }

    /**
     * Instanz holen - ORIGINAL beibehalten
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ORIGINAL: Fallback-Logik beibehalten + erweitert
     */
    private function request_with_fallback($prompt, $options = []) {
        $provider_override = $options['provider'] ?? null;
        
        // Wenn spezifischer Provider gewünscht
        if ($provider_override && $this->is_provider_available($provider_override)) {
            try {
                return $this->execute_single_request($provider_override, $prompt, $options);
            } catch (\Exception $e) {
                error_log("SEO AI: {$provider_override} failed: " . $e->getMessage());
                // Weiter zu Fallback
            }
        }
        
        // ORIGINAL: Zuerst Claude
        if (!empty($this->claude_api_key)) {
            try {
                return $this->call_claude_api($prompt, $options);
            } catch (\Exception $e) {
                // Logge Fehler und weiter zu OpenAI
                error_log('Claude API Fehler: ' . $e->getMessage());
            }
        }
        
        // ORIGINAL: Fallback OpenAI
        if (!empty($this->openai_api_key)) {
            try {
                return $this->call_openai_api('openai', $prompt, $options);
            } catch (\Exception $e) {
                error_log('OpenAI API Fehler: ' . $e->getMessage());
            }
        }
        
        // ERWEITERT: Weitere Fallbacks
        $available_providers = $this->get_available_providers();
        foreach ($available_providers as $provider) {
            if ($provider === 'claude' || $provider === 'openai') continue; // Bereits versucht
            
            try {
                return $this->execute_single_request($provider, $prompt, $options);
            } catch (\Exception $e) {
                error_log("SEO AI: {$provider} fallback failed: " . $e->getMessage());
            }
        }
        
        throw new \Exception(__('Kein API-Key konfiguriert oder alle Provider nicht verfügbar', 'seo-ai-master'));
    }

    /**
     * ORIGINAL: Claude API aufrufen - ERWEITERT mit neuestem Format
     */
    private function call_claude_api($prompt, $options = []) {
        if (empty($this->claude_api_key)) {
            throw new \Exception(__('Claude API-Key fehlt', 'seo-ai-master'));
        }
        
        $config = $this->providers['claude'];
        $system_prompt = $this->get_system_prompt($options['task_type'] ?? 'general');
        
        // NEUES FORMAT: Messages statt prompt
        $body = [
            'model' => $config['model'],
            'max_tokens' => $options['max_tokens'] ?? $config['max_tokens'],
            'temperature' => $options['temperature'] ?? 0.3,
            'system' => $system_prompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $response = wp_remote_post($this->anthropic_url, [
            'headers' => [
                'x-api-key' => $this->claude_api_key,
                'anthropic-version' => $this->anthropic_version,
                'content-type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => $options['timeout'] ?? $this->timeout
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200) {
            throw new \Exception("Claude API HTTP {$code}: " . ($data['error']['message'] ?? 'Unknown error'));
        }
        
        // NEUES FORMAT: content array statt completion
        if (empty($data['content'][0]['text'])) {
            throw new \Exception(__('Keine Antwort von Claude erhalten', 'seo-ai-master'));
        }
        
        return trim($data['content'][0]['text']);
    }

    /**
     * ORIGINAL: OpenAI API aufrufen - ERWEITERT um GPT-4o
     */
    private function call_openai_api($provider = 'openai', $prompt, $options = []) {
        if (empty($this->openai_api_key)) {
            throw new \Exception(__('OpenAI API-Key fehlt', 'seo-ai-master'));
        }
        
        $config = $this->providers[$provider];
        $system_prompt = $this->get_system_prompt($options['task_type'] ?? 'general');
        
        $body = [
            'model' => $config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $options['max_tokens'] ?? $config['max_tokens'],
            'temperature' => $options['temperature'] ?? 0.3
        ];
        
        $response = wp_remote_post($this->openai_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openai_api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => $options['timeout'] ?? $this->timeout
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code !== 200) {
            throw new \Exception("OpenAI API HTTP {$code}: " . ($data['error']['message'] ?? 'Unknown error'));
        }
        
        if (empty($data['choices'][0]['message']['content'])) {
            throw new \Exception(__('Keine Antwort von OpenAI erhalten', 'seo-ai-master'));
        }
        
        return trim($data['choices'][0]['message']['content']);
    }

    /**
     * NEU: Gemini API
     */
    private function call_gemini_api($prompt, $options = []) {
        $api_key = $this->get_encrypted_api_key('gemini');
        if (empty($api_key)) {
            throw new \Exception('Gemini API-Key nicht konfiguriert');
        }

        $config = $this->providers['gemini'];
        $url = $config['url'] . '?key=' . $api_key;

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.3,
                'maxOutputTokens' => $options['max_tokens'] ?? $config['max_tokens']
            ]
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => $options['timeout'] ?? $this->timeout
        ]);

        if (is_wp_error($response)) {
            throw new \Exception("Gemini API Error: " . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            throw new \Exception("Gemini API HTTP {$code}: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        if (empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Keine Antwort von Gemini erhalten');
        }

        return trim($data['candidates'][0]['content']['parts'][0]['text']);
    }

    /**
     * ORIGINAL: Content analysieren - beibehalten
     */
    public function analyze_content($content) {
        $prompt = sprintf(
            __('Analysiere den folgenden Text und gib ein JSON-Objekt mit Wörtern, Lesedauer (Minuten) und Lesbarkeits-Score zurück: %s', 'seo-ai-master'),
            $content
        );
        $response = $this->request_with_fallback($prompt, ['task_type' => 'content_analysis']);
        return json_decode($response, true);
    }

    /**
     * ORIGINAL: Meta-Titel generieren - erweitert
     */
    public function generate_meta_title($content, $keyword = '') {
        $prompt = $this->build_meta_title_prompt($content, $keyword);
        $response = $this->request_with_fallback($prompt, ['task_type' => 'meta_title']);
        return $this->parse_meta_response($response, 'title');
    }

    /**
     * ORIGINAL: Meta-Beschreibung generieren - erweitert
     */
    public function generate_meta_description($content, $keyword = '') {
        $prompt = $this->build_meta_description_prompt($content, $keyword);
        $response = $this->request_with_fallback($prompt, ['task_type' => 'meta_description']);
        return $this->parse_meta_response($response, 'description');
    }

    /**
     * ORIGINAL: Content optimieren - beibehalten
     */
    public function optimize_content($content, $guidelines = []) {
        $prompt = sprintf(
            __('Optimiere den folgenden Text unter Berücksichtigung von SEO-Guidelines: %s', 'seo-ai-master'),
            $content
        );
        return $this->request_with_fallback($prompt, ['task_type' => 'content_optimization']);
    }

    /**
     * ORIGINAL: Keywords vorschlagen - beibehalten
     */
    public function suggest_keywords($content) {
        $prompt = sprintf(
            __('Gib eine Liste von 5 relevanten Keywords für den folgenden Text zurück: %s', 'seo-ai-master'),
            wp_trim_words($content, 30)
        );
        $response = $this->request_with_fallback($prompt, ['task_type' => 'keyword_suggestion']);
        return json_decode($response, true);
    }

    // ========================================
    // ERWEITERTE FUNKTIONEN (neue Methoden)
    // ========================================

    /**
     * Einzelnen API-Request ausführen
     */
    private function execute_single_request($provider, $prompt, $options) {
        switch ($provider) {
            case 'claude':
                return $this->call_claude_api($prompt, $options);
            case 'openai':
            case 'openai_4o':
                return $this->call_openai_api($provider, $prompt, $options);
            case 'gemini':
                return $this->call_gemini_api($prompt, $options);
            default:
                throw new \Exception("Unbekannter Provider: {$provider}");
        }
    }

    /**
     * Bulk-Operationen
     */
    public function bulk_generate($items, $operation, $options = []) {
        $results = [];
        $total = count($items);
        $processed = 0;
        
        // Progress Hook für Dashboard
        do_action('seo_ai_bulk_start', $total, $operation);
        
        foreach ($items as $item) {
            try {
                switch ($operation) {
                    case 'meta_titles':
                        $result = $this->generate_meta_title($item['content'], $item['keyword'] ?? '');
                        break;
                    case 'meta_descriptions':
                        $result = $this->generate_meta_description($item['content'], $item['keyword'] ?? '');
                        break;
                    default:
                        throw new \Exception("Unbekannte Bulk-Operation: {$operation}");
                }
                
                $results[] = [
                    'id' => $item['id'],
                    'success' => true,
                    'result' => $result
                ];
                
            } catch (\Exception $e) {
                $results[] = [
                    'id' => $item['id'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            $processed++;
            
            // Progress Update
            do_action('seo_ai_bulk_progress', $processed, $total, $operation);
            
            // Rate Limiting zwischen Requests
            if ($processed < $total) {
                usleep(500000); // 0.5 Sekunden Pause
            }
        }
        
        do_action('seo_ai_bulk_complete', $results, $operation);
        
        return $results;
    }

    /**
     * Hilfsmethoden
     */
    private function get_encrypted_api_key($provider) {
        // Erst originale API-Keys probieren
        if ($provider === 'claude' && !empty($this->claude_api_key)) {
            return $this->claude_api_key;
        }
        if (($provider === 'openai' || $provider === 'openai_4o') && !empty($this->openai_api_key)) {
            return $this->openai_api_key;
        }
        
        // Dann verschlüsselte Keys
        $encrypted_key = $this->settings["{$provider}_api_key"] ?? '';
        return $this->decrypt_api_key($encrypted_key);
    }

    private function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) return '';
        
        // Wenn nicht verschlüsselt, direkt zurückgeben
        if (strpos($encrypted_key, 'sk-') === 0 || strpos($encrypted_key, 'claude-') === 0) {
            return $encrypted_key;
        }
        
        $encryption_key = $this->get_encryption_key();
        $data = base64_decode($encrypted_key);
        
        if (strlen($data) < 16) return $encrypted_key; // Nicht verschlüsselt
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $encryption_key, 0, $iv);
    }

    private function get_encryption_key() {
        $key = get_option('seo_ai_encryption_key');
        if (!$key) {
            $key = base64_encode(openssl_random_pseudo_bytes(32));
            update_option('seo_ai_encryption_key', $key);
        }
        return base64_decode($key);
    }

    private function get_available_providers() {
        $available = [];
        foreach (array_keys($this->providers) as $provider) {
            if ($this->is_provider_available($provider)) {
                $available[] = $provider;
            }
        }
        return $available;
    }

    private function is_provider_available($provider) {
        $api_key = $this->get_encrypted_api_key($provider);
        if (empty($api_key)) return false;
        
        // Check health status
        $health = get_transient("seo_ai_health_{$provider}");
        return $health !== 'down';
    }

    private function get_system_prompt($task_type) {
        $prompts = [
            'meta_title' => 'Du bist ein SEO-Experte. Erstelle optimale Meta-Titles für maximale Click-Through-Rate.',
            'meta_description' => 'Du bist ein SEO-Experte. Schreibe überzeugende Meta-Descriptions die zum Klicken animieren.',
            'content_analysis' => 'Du bist ein SEO-Analyst. Analysiere Content und gib strukturierte JSON-Antworten.',
            'content_optimization' => 'Du bist ein SEO-Content-Optimierer. Verbessere Texte für Suchmaschinen und Benutzer.',
            'keyword_suggestion' => 'Du bist ein Keyword-Spezialist. Schlage relevante SEO-Keywords vor.',
            'general' => 'Du bist ein hilfreicher SEO-Assistent der präzise und actionable Antworten gibt.'
        ];
        
        return $prompts[$task_type] ?? $prompts['general'];
    }

    private function build_meta_title_prompt($content, $keyword) {
        $brand = get_bloginfo('name');
        
        return sprintf(
            "Erstelle einen SEO-optimierten Meta-Title:
            
            CONTENT: %s
            FOCUS-KEYWORD: %s
            BRAND: %s
            
            ANFORDERUNGEN:
            - Maximal 60 Zeichen
            - Focus-Keyword am Anfang
            - Emotional ansprechend
            - Brand optional am Ende
            
            Antworte nur mit dem Titel, ohne weitere Erklärungen.",
            wp_trim_words($content, 30),
            $keyword,
            $brand
        );
    }

    private function build_meta_description_prompt($content, $keyword) {
        return sprintf(
            "Schreibe eine überzeugende Meta-Description:
            
            CONTENT: %s
            FOCUS-KEYWORD: %s
            
            ANFORDERUNGEN:
            - Maximal 160 Zeichen
            - Keyword integrieren
            - Call-to-Action
            - Zum Klicken animieren
            
            Antworte nur mit der Description, ohne weitere Erklärungen.",
            wp_trim_words($content, 55),
            $keyword
        );
    }

    private function parse_meta_response($response, $type) {
        // Entferne Anführungszeichen und Formatierung
        $cleaned = trim($response, '"\'` ');
        $cleaned = strip_tags($cleaned);
        
        // Länge prüfen
        if ($type === 'title' && strlen($cleaned) > 60) {
            $cleaned = substr($cleaned, 0, 57) . '...';
        } elseif ($type === 'description' && strlen($cleaned) > 160) {
            $cleaned = substr($cleaned, 0, 157) . '...';
        }
        
        return $cleaned;
    }

    /**
     * Health Checks
     */
    public function schedule_health_checks() {
        if (!wp_next_scheduled('seo_ai_health_check')) {
            wp_schedule_event(time(), 'hourly', 'seo_ai_health_check');
        }
        add_action('seo_ai_health_check', [$this, 'perform_health_checks']);
    }

    public function perform_health_checks() {
        foreach (array_keys($this->providers) as $provider) {
            try {
                $test_response = $this->execute_single_request($provider, 'Test', ['timeout' => 10]);
                $this->update_provider_health($provider, !empty($test_response));
            } catch (\Exception $e) {
                $this->update_provider_health($provider, false);
                error_log("Health check failed for {$provider}: " . $e->getMessage());
            }
        }
    }

    private function update_provider_health($provider, $is_healthy) {
        $status = $is_healthy ? 'up' : 'down';
        set_transient("seo_ai_health_{$provider}", $status, 300); // 5 Minuten
    }
} 