<?php

public function enqueue_admin_scripts() {
    // Bestehende Scripts
    wp_enqueue_script('alenseo-admin', plugin_dir_url(__FILE__) . '../assets/js/admin.js', array('jquery'), ALENSEO_VERSION, true);
    wp_enqueue_script('alenseo-dashboard-visual', plugin_dir_url(__FILE__) . '../assets/js/dashboard-visual.js', array('jquery'), ALENSEO_VERSION, true);
    
    // Neue Bibliotheken für erweiterte Visualisierungen
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js', array(), '3.7.0', true);
    wp_enqueue_script('heatmap-js', 'https://cdn.jsdelivr.net/npm/heatmap.js@2.0.5/heatmap.min.js', array(), '2.0.5', true);
    wp_enqueue_script('wordcloud-js', 'https://cdn.jsdelivr.net/npm/wordcloud@1.1.0/src/wordcloud2.js', array(), '1.1.0', true);
    
    // Lokalisierung für JavaScript
    wp_localize_script('alenseo-dashboard-visual', 'alenseoData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'wsUrl' => $this->get_websocket_url(),
        'nonce' => wp_create_nonce('alenseo_nonce'),
        'messages' => array(
            'selectAction' => __('Bitte wählen Sie eine Aktion aus.', 'alenseo'),
            'selectContent' => __('Bitte wählen Sie mindestens einen Beitrag aus.', 'alenseo'),
            'updateSuccess' => __('Update erfolgreich.', 'alenseo'),
            'updateError' => __('Fehler beim Update.', 'alenseo')
        )
    ));
}

private function get_websocket_url() {
    $protocol = is_ssl() ? 'wss' : 'ws';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host . '/ws/alenseo';
} 