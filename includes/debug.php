<?php
/**
 * Debug-Hilfsfunktionen für Alenseo SEO
 *
 * Diese Datei enthält Hilfsfunktionen für das Debugging
 * 
 * @link       https://www.imponi.ch
 * @since      2.0.3
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// WICHTIG: Keine WP_DEBUG Konstanten direkt definieren!
// Stattdessen nur überprüfen ob sie existieren.

/**
 * Sichere Alternative zu error_log, die einen Alenseo-spezifischen Präfix hinzufügt
 * 
 * @param string $message Die zu protokollierende Nachricht
 * @return bool True bei Erfolg, False bei Fehlschlag
 */
function alenseo_error_log($message) {
    return error_log('Alenseo SEO: ' . $message);
}

/**
 * Prüft, ob der Debug-Modus aktiviert ist
 * 
 * @return bool True wenn Debug aktiviert ist, sonst false
 */
function alenseo_is_debug_mode() {
    return defined('WP_DEBUG') && WP_DEBUG;
}

/**
 * Gibt Debug-Informationen aus, wenn der Debug-Modus aktiviert ist
 * 
 * @param string|array $data Die anzuzeigenden Debug-Daten
 * @param bool $die Ob die Ausführung angehalten werden soll
 */
function alenseo_debug($data, $die = false) {
    if (alenseo_is_debug_mode()) {
        echo '<pre>';
        if (is_array($data) || is_object($data)) {
            print_r($data);
        } else {
            echo $data;
        }
        echo '</pre>';
        
        if ($die) {
            die();
        }
    }
}