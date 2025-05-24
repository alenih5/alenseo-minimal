<?php
namespace Alenseo;

/**
 * W3 Total Cache Integration für Alenseo SEO
 * 
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Alenseo_W3_Total_Cache_Integration {
    
    /**
     * Konstruktor
     */
    public function __construct() {
        // Prüfen ob W3 Total Cache aktiv ist
        if (defined('W3TC')) {
            $this->init_hooks();
        }
    }
    
    /**
     * Initialisiert die Hooks für die Integration
     */
    private function init_hooks() {
        // Cache leeren wenn SEO-Daten aktualisiert werden
        add_action('alenseo_seo_data_updated', array($this, 'clear_cache'));
        
        // Cache leeren wenn API-Einstellungen geändert werden
        add_action('alenseo_api_settings_updated', array($this, 'clear_cache'));
        
        // Cache leeren wenn Plugin deaktiviert wird
        add_action('alenseo_plugin_deactivated', array($this, 'clear_cache'));
    }
    
    /**
     * Leert den W3 Total Cache
     */
    public function clear_cache() {
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
    }
    
    /**
     * Fügt SEO-Daten zum Cache hinzu
     * 
     * @param array $seo_data Die SEO-Daten
     * @return array Die modifizierten SEO-Daten
     */
    public function cache_seo_data($seo_data) {
        if (function_exists('w3tc_flush_all')) {
            // Cache-Tags für W3 Total Cache
            $seo_data['_w3tc_cache_tags'] = array('alenseo', 'seo_data');
            
            // Cache-Gruppe für W3 Total Cache
            $seo_data['_w3tc_cache_group'] = 'alenseo';
        }
        
        return $seo_data;
    }
    
    /**
     * Prüft ob der Cache aktiv ist
     * 
     * @return bool True wenn Cache aktiv ist
     */
    public function is_cache_enabled() {
        return defined('W3TC') && function_exists('w3tc_flush_all');
    }
} 