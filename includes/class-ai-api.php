<?php
namespace Alenseo;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Basis AI-API-Klasse für gemeinsame Funktionalität
 * 
 * Diese abstrakte Klasse definiert die gemeinsamen Methoden und Eigenschaften
 * für alle AI-API-Implementierungen (Claude, OpenAI, etc.)
 * 
 * @since 2.0.0
 * @package Alenseo
 */
abstract class AI_API {
    
    /**
     * Logging-Funktion für Debug-Zwecke
     */
    protected function log($message, $level = 'info') {
        if (WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("Alenseo AI API [{$level}]: {$message}");
        }
    }
    
    /**
     * Text bereinigen (HTML-Tags entfernen, trimmen)
     */
    protected function clean_text($text) {
        // HTML-Tags entfernen und whitespace normalisieren
        $cleaned = trim(strip_tags($text));
        // Mehrfache Leerzeichen durch einzelne ersetzen
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        return $cleaned;
    }
    
    /**
     * Abstakte Methoden, die von allen AI-APIs implementiert werden müssen
     */
    
    // Basis-Funktionalität
    abstract public function is_api_configured();
    abstract public function validate_api_key($key = null);
    abstract public function test_api_key();
    abstract public function generate_text($prompt, $options = []);
    
    // SEO-spezifische Methoden
    abstract public function generate_keywords($title, $content);
    abstract public function optimize_content($content, $keyword = '');
    abstract public function optimize_meta_description($title, $content, $keyword = '');
    abstract public function generate_meta_title($content, $keyword = '');
    abstract public function get_optimization_suggestions($content);
    abstract public function analyze_keywords($content);
    
    // Konfiguration
    abstract public function set_api_key($key);
    abstract public function set_model($model);
    
    /**
     * Standard-Implementierung für häufige Hilfsmethoden
     */
    
    /**
     * Text auf maximale Länge kürzen (für API-Limits)
     */
    protected function truncate_text($text, $max_length = 1000) {
        $cleaned = $this->clean_text($text);
        if (strlen($cleaned) <= $max_length) {
            return $cleaned;
        }
        
        // Intelligent kürzen - am letzten Satzende vor dem Limit
        $truncated = substr($cleaned, 0, $max_length);
        $last_sentence_end = strrpos($truncated, '.');
        
        if ($last_sentence_end !== false && $last_sentence_end > ($max_length * 0.7)) {
            return substr($truncated, 0, $last_sentence_end + 1);
        }
        
        // Fallback: Am letzten Leerzeichen kürzen
        $last_space = strrpos($truncated, ' ');
        if ($last_space !== false) {
            return substr($truncated, 0, $last_space) . '...';
        }
        
        return $truncated . '...';
    }
    
    /**
     * Sicherheitsprüfung für Content
     */
    protected function sanitize_prompt($prompt) {
        // Entferne potentiell schädliche Inhalte
        $dangerous_patterns = [
            '/\b(ignore|forget|disregard)\s+(previous|all|above|system)\s+(instructions|prompts?|rules?)\b/i',
            '/\b(act|behave|pretend)\s+as\s+(?:if\s+)?(?:you\s+)?(?:are|were)\s+(?:a|an|not)\b/i',
            '/\bjailbreak\b/i',
            '/\bprompt\s+injection\b/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            $prompt = preg_replace($pattern, '[FILTERED]', $prompt);
        }
        
        return $this->clean_text($prompt);
    }
    
    /**
     * Standard-Optionen für API-Anfragen
     */
    protected function get_default_options() {
        return [
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'timeout' => 30,
            'retry_attempts' => 2,
            'system_prompt' => 'Du bist ein SEO-Experte, der bei der Optimierung von Website-Inhalten hilft. Antworte präzise und auf Deutsch.'
        ];
    }
    
    /**
     * Optionen zusammenführen mit Defaults
     */
    protected function merge_options($options = []) {
        return wp_parse_args($options, $this->get_default_options());
    }
    
    /**
     * Fehlermeldung standardisieren
     */
    protected function create_error($code, $message, $data = null) {
        $this->log("Error [{$code}]: {$message}", 'error');
        return new \WP_Error($code, $message, $data);
    }
    
    /**
     * Erfolgreiche Antwort validieren
     */
    protected function validate_response($response, $expected_fields = []) {
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (empty($response)) {
            return $this->create_error('empty_response', 'Leere Antwort von der API erhalten.');
        }
        
        // Prüfe erwartete Felder
        foreach ($expected_fields as $field) {
            if (!isset($response[$field])) {
                return $this->create_error('missing_field', "Erwartetes Feld '{$field}' fehlt in der API-Antwort.");
            }
        }
        
        return $response;
    }
    
    /**
     * Performance-Messung
     */
    protected function measure_performance($callback, $context = 'API Call') {
        $start_time = microtime(true);
        $result = $callback();
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        $this->log("{$context} completed in {$execution_time}ms");
        
        return [
            'result' => $result,
            'execution_time' => $execution_time
        ];
    }
    
    /**
     * Rate-Limiting-Implementierung (kann von Subklassen überschrieben werden)
     */
    protected function enforce_rate_limit($delay_ms = 100) {
        static $last_request_time = 0;
        
        $current_time = microtime(true) * 1000;
        $time_since_last = $current_time - $last_request_time;
        
        if ($time_since_last < $delay_ms) {
            $sleep_time = ($delay_ms - $time_since_last) * 1000; // μs
            usleep(intval($sleep_time));
        }
        
        $last_request_time = microtime(true) * 1000;
    }
    
    /**
     * WordPress-spezifische Hilfsmethoden
     */
    
    /**
     * Post-Metadaten abrufen mit Fallback
     */
    protected function get_post_meta_with_fallback($post_id, $meta_key, $default = '') {
        $value = get_post_meta($post_id, $meta_key, true);
        return !empty($value) ? $value : $default;
    }
    
    /**
     * Sichere Meta-Aktualisierung
     */
    protected function safe_update_post_meta($post_id, $meta_key, $value) {
        if (!current_user_can('edit_post', $post_id)) {
            return $this->create_error('insufficient_permissions', 'Unzureichende Berechtigungen zum Bearbeiten des Beitrags.');
        }
        
        $result = update_post_meta($post_id, $meta_key, $value);
        
        if ($result === false) {
            return $this->create_error('meta_update_failed', 'Fehler beim Aktualisieren der Post-Metadaten.');
        }
        
        return $result;
    }
    
    /**
     * Cache-Management (einfache Implementierung)
     */
    protected function get_cache($key, $expiration = 3600) {
        return get_transient("alenseo_ai_cache_{$key}");
    }
    
    protected function set_cache($key, $value, $expiration = 3600) {
        return set_transient("alenseo_ai_cache_{$key}", $value, $expiration);
    }
    
    protected function delete_cache($key) {
        return delete_transient("alenseo_ai_cache_{$key}");
    }
    
    /**
     * Cache-Schlüssel generieren basierend auf Parametern
     */
    protected function generate_cache_key($prefix, $params) {
        $key_data = is_array($params) ? $params : [$params];
        $key_data[] = get_current_user_id(); // User-spezifisches Caching
        
        return $prefix . '_' . md5(serialize($key_data));
    }
}