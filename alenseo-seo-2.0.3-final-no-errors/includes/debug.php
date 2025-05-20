<?php
/**
 * KRITISCHER FIX: Debug-Handler für Alenseo SEO
 * 
 * Diese Datei enthält Hilfsfunktionen für das Debugging und
 * WICHTIGE FIXES für Debug-Konstanten-Konflikte
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

// WICHTIG: Stelle ursprünglichen Error Handler wieder her
restore_error_handler();

// WICHTIG: Verhindern, dass WP_DEBUG-Konstanten neu definiert werden
// Dadurch werden die WordPress-eigenen Debug-Einstellungen respektiert
function alenseo_prevent_debug_conflicts() {
    // Setze den Standard-PHP-Error-Handler zurück
    restore_error_handler();
}
// Führe diese Funktion sofort aus
alenseo_prevent_debug_conflicts();

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
 * Nutzt die vorhandenen WordPress-Debug-Einstellungen
 * 
 * @return bool True wenn Debug aktiviert ist, sonst false
 */
function alenseo_is_debug_mode() {
    // Sicheres Prüfen der WordPress-Debug-Einstellungen
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