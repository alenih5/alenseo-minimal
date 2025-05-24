<?php
namespace Alenseo;

/**
 * Batch-Analyzer für Alenseo SEO
 *
 * Diese Klasse ermöglicht die effiziente Analyse mehrerer Beiträge
 * 
 * @link       https://www.imponi.ch
 * @since      2.0.4
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Alenseo_Batch_Analyzer {
    
    /**
     * Batch-Größe für die Analyse
     * 
     * @var int
     */
    private $batch_size = 10;
    
    /**
     * Verzögerung zwischen Batches in Sekunden
     * 
     * @var int
     */
    private $batch_delay = 2;
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Batch-Größe aus Einstellungen laden
        $settings = get_option('alenseo_settings', array());
        if (isset($settings['batch_size'])) {
            $this->batch_size = intval($settings['batch_size']);
        }
        if (isset($settings['batch_delay'])) {
            $this->batch_delay = intval($settings['batch_delay']);
        }
        
        // AJAX-Hooks registrieren
        add_action('wp_ajax_alenseo_batch_analyze', array($this, 'ajax_batch_analyze'));
        add_action('wp_ajax_alenseo_batch_status', array($this, 'ajax_batch_status'));
        add_action('wp_ajax_alenseo_clear_seo_data', array($this, 'ajax_clear_seo_data'));
    }
    
    /**
     * Batch-Analyse starten
     * 
     * @param array $post_ids Array von Post-IDs
     * @return array Status der Analyse
     */
    public function start_batch_analysis($post_ids) {
        // Batch-Status initialisieren
        $batch_id = uniqid('alenseo_batch_');
        $status = array(
            'batch_id' => $batch_id,
            'total' => count($post_ids),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => array(),
            'start_time' => time(),
            'end_time' => null,
            'status' => 'running'
        );
        
        // Status in der Datenbank speichern
        update_option('alenseo_batch_' . $batch_id, $status);
        
        // Ersten Batch starten
        $this->process_batch($batch_id, array_slice($post_ids, 0, $this->batch_size));
        
        return $status;
    }
    
    /**
     * Batch verarbeiten
     * 
     * @param string $batch_id Die Batch-ID
     * @param array $post_ids Array von Post-IDs für diesen Batch
     */
    private function process_batch($batch_id, $post_ids) {
        $status = get_option('alenseo_batch_' . $batch_id);
        $analyzer = new Alenseo_Enhanced_Analysis();
        
        foreach ($post_ids as $post_id) {
            try {
                $result = $analyzer->analyze_post($post_id);
                if ($result === true) {
                    $status['success']++;
                } else {
                    $status['failed']++;
                    $status['errors'][] = array(
                        'post_id' => $post_id,
                        'error' => $result
                    );
                }
            } catch (\Exception $e) {
                $status['failed']++;
                $status['errors'][] = array(
                    'post_id' => $post_id,
                    'error' => $e->getMessage()
                );
            }
            
            $status['processed']++;
        }
        
        // Status aktualisieren
        update_option('alenseo_batch_' . $batch_id, $status);
        
        // Nächsten Batch planen, wenn noch Posts übrig sind
        $remaining = $status['total'] - $status['processed'];
        if ($remaining > 0) {
            $next_batch = array_slice(
                $post_ids,
                $status['processed'],
                $this->batch_size
            );
            
            wp_schedule_single_event(
                time() + $this->batch_delay,
                'alenseo_process_batch',
                array($batch_id, $next_batch)
            );
        } else {
            // Analyse abgeschlossen
            $status['end_time'] = time();
            $status['status'] = 'completed';
            update_option('alenseo_batch_' . $batch_id, $status);
        }
    }
    
    /**
     * AJAX-Handler für Batch-Analyse
     */
    public function ajax_batch_analyze() {
        // Nonce prüfen
        if (!check_ajax_referer('alenseo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Ungültiger Nonce');
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unzureichende Berechtigungen');
        }
        
        // Post-IDs aus Request holen
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        
        if (empty($post_ids)) {
            wp_send_json_error('Keine Post-IDs angegeben');
        }
        
        // Batch-Analyse starten
        $status = $this->start_batch_analysis($post_ids);
        
        wp_send_json_success($status);
    }
    
    /**
     * AJAX-Handler für Batch-Status
     */
    public function ajax_batch_status() {
        // Nonce prüfen
        if (!check_ajax_referer('alenseo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Ungültiger Nonce');
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unzureichende Berechtigungen');
        }
        
        // Batch-ID aus Request holen
        $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';
        
        if (empty($batch_id)) {
            wp_send_json_error('Keine Batch-ID angegeben');
        }
        
        // Status abrufen
        $status = get_option('alenseo_batch_' . $batch_id);
        
        if (!$status) {
            wp_send_json_error('Batch nicht gefunden');
        }
        
        wp_send_json_success($status);
    }
    
    /**
     * AJAX-Handler für das Löschen der SEO-Daten
     */
    public function ajax_clear_seo_data() {
        // Nonce prüfen
        if (!check_ajax_referer('alenseo_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Ungültiger Nonce');
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unzureichende Berechtigungen');
        }
        
        // Post-IDs aus Request holen
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        
        if (empty($post_ids)) {
            wp_send_json_error('Keine Post-IDs angegeben');
        }
        
        // SEO-Daten für jeden Post löschen
        foreach ($post_ids as $post_id) {
            delete_post_meta($post_id, '_alenseo_seo_score');
            delete_post_meta($post_id, '_alenseo_seo_status');
            delete_post_meta($post_id, '_alenseo_last_analysis');
            delete_post_meta($post_id, '_alenseo_lsi_keywords');
            delete_post_meta($post_id, '_alenseo_lsi_analysis');
            delete_post_meta($post_id, '_alenseo_schema');
        }
        
        wp_send_json_success('SEO-Daten erfolgreich gelöscht');
    }
    
    /**
     * Batch-Status abrufen
     * 
     * @param string $batch_id Die Batch-ID
     * @return array|false Der Batch-Status oder false
     */
    public function get_batch_status($batch_id) {
        return get_option('alenseo_batch_' . $batch_id);
    }
    
    /**
     * Batch-Status löschen
     * 
     * @param string $batch_id Die Batch-ID
     * @return bool Erfolg oder Misserfolg
     */
    public function delete_batch_status($batch_id) {
        return delete_option('alenseo_batch_' . $batch_id);
    }
} 