        $today = date('Y-m-d');
        
        // Wenn die Datenbank-Klasse verfügbar ist, dort den Score speichern
        global $alenseo_database;
        if (isset($alenseo_database) && method_exists($alenseo_database, 'get_score_history')) {
            // Score wird automatisch in der Datenbank gespeichert, wenn SEO-Scores aktualisiert werden
            // Dies ist nur ein Fallback für manuelle Updates
            return true;
        } else {
            // Fallback zur alten Methode über Optionen
            $history = get_option('alenseo_score_history', array());
            
            // Heutigen Score speichern/aktualisieren
            $history[$today] = $score;
            
            // Historie auf 30 Tage begrenzen
            if (count($history) > 30) {
                // Nach Datum sortieren
                ksort($history);
                
                // Älteste Einträge entfernen
                $history = array_slice($history, -30, 30, true);
            }
            
            return update_option('alenseo_score_history', $history);
        }
    }
}
