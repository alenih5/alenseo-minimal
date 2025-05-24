<?php
/**
 * Admin-Klasse für Alenseo SEO
 *
 * Diese Datei enthält die Admin-Funktionalitäten des Plugins
 * 
 * @link       https://www.imponi.ch
 * @since      1.0.0
 *
 * @package    Alenseo
 * @subpackage Alenseo/includes
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Die Admin-Klasse
 */
class Alenseo_Minimal_Admin {
    
    /**
     * Initialisierung der Klasse
     */
    public function __construct() {
        // Meta-Box hinzufügen
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Meta-Box-Daten speichern
        add_action('save_post', array($this, 'save_meta_box_data'));
        
        // Admin-Assets laden
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Meta-Box hinzufügen 
     * Deaktiviert, um Dopplung mit class-meta-box.php zu vermeiden
     */
    public function add_meta_boxes() {
        // Deaktiviert wegen Dopplung mit class-meta-box.php
        return;
        
        // Einstellungen abrufen
        $settings = get_option('alenseo_settings', array());
        $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        
        // Meta-Box zu allen ausgewählten Post-Typen hinzufügen
        foreach ($post_types as $post_type) {
            // Deaktiviert
            /*
            add_meta_box(
                'alenseo_meta_box',
                __('Alenseo SEO', 'alenseo'),
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
            */
        }
    }
    
    /**
     * Meta-Box rendern
     * 
     * @param WP_Post $post Das aktuelle Post-Objekt
     */
    public function render_meta_box($post) {
        // Nonce für Sicherheitsüberprüfung erstellen
        wp_nonce_field('alenseo_meta_box', 'alenseo_meta_box_nonce');
        
        // Meta-Box-Template laden
        $template_file = ALENSEO_MINIMAL_DIR . 'templates/alenseo-meta-box.php';
        
        if (file_exists($template_file)) {
            // Daten für das Template vorbereiten
            $post_id = $post->ID;
            $keyword = get_post_meta($post->ID, '_alenseo_keyword', true);
            $meta_description = get_post_meta($post->ID, '_alenseo_meta_description', true);
            $seo_score = get_post_meta($post->ID, '_alenseo_seo_score', true);
            $seo_status = get_post_meta($post->ID, '_alenseo_seo_status', true);
            
            // Claude API-Status prüfen
            $settings = get_option('alenseo_settings', array());
            $claude_api_active = !empty($settings['claude_api_key']);
            
            // Template einbinden
            include $template_file;
        } else {
            // Fallback, wenn Template nicht gefunden wird
            echo '<p>' . __('Meta-Box-Template konnte nicht geladen werden.', 'alenseo') . '</p>';
        }
    }
    
    /**
     * Meta-Box-Daten speichern
     * 
     * @param int $post_id Die Post-ID
     */
    public function save_meta_box_data($post_id) {
        // Autosave prüfen
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Nonce prüfen
        if (!isset($_POST['alenseo_meta_box_nonce']) || !wp_verify_nonce($_POST['alenseo_meta_box_nonce'], 'alenseo_meta_box')) {
            return;
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Keyword speichern
        if (isset($_POST['alenseo_keyword'])) {
            update_post_meta($post_id, '_alenseo_keyword', sanitize_text_field($_POST['alenseo_keyword']));
        }
        
        // Meta-Description speichern
        if (isset($_POST['alenseo_meta_description'])) {
            update_post_meta($post_id, '_alenseo_meta_description', sanitize_textarea_field($_POST['alenseo_meta_description']));
        }
        
        // SEO-Analyse durchführen, wenn ein Keyword gesetzt ist
        $keyword = isset($_POST['alenseo_keyword']) ? sanitize_text_field($_POST['alenseo_keyword']) : '';
        if (!empty($keyword) && class_exists('Alenseo_Minimal_Analysis')) {
            $analyzer = new Alenseo_Minimal_Analysis();
            $analyzer->analyze_post($post_id);
        }
    }
    
    /**
     * Admin-Menü hinzufügen
     * Hinweis: Diese Funktion wird nur ausgeführt, wenn die Dashboard-Klasse nicht geladen ist
     */
    public function add_admin_menu() {
        // Prüfen, ob bereits ein Menüpunkt existiert (z.B. durch Dashboard-Klasse)
        global $submenu;
        if (isset($submenu['alenseo-optimizer'])) {
            return;
        }
        
        // Admin-Menüpunkt hinzufügen
        add_menu_page(
            __('Alenseo SEO', 'alenseo'),
            __('Alenseo SEO', 'alenseo'),
            'manage_options',
            'alenseo-minimal-settings',
            array($this, 'render_settings_page'),
            'dashicons-chart-bar',
            80
        );
    }
    
    /**
     * Einstellungsseite rendern
     */
    public function render_settings_page() {
        // Einstellungs-Template laden
        $template_file = ALENSEO_MINIMAL_DIR . 'templates/settings-page.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // Fallback, wenn Template nicht gefunden wird
            echo '<div class="wrap"><h1>' . __('Alenseo SEO Einstellungen', 'alenseo') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Einstellungen-Template konnte nicht geladen werden.', 'alenseo') . '</p></div>';
            echo '</div>';
        }
    }
    
    /**
     * Admin-Assets laden
     * 
     * @param string $hook Die aktuelle Admin-Seite
     */
    public function enqueue_admin_assets($hook) {
        // Meta-Box-Assets laden, wenn wir auf einer Post-Bearbeitungsseite sind
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            // Meta-Box CSS
            if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/meta-box.css')) {
                wp_enqueue_style(
                    'alenseo-meta-box-css',
                    ALENSEO_MINIMAL_URL . 'assets/css/meta-box.css',
                    array(),
                    ALENSEO_MINIMAL_VERSION
                );
            }
            
            // Meta-Box JS
            if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/meta-box.js')) {
                wp_enqueue_script(
                    'alenseo-meta-box-js',
                    ALENSEO_MINIMAL_URL . 'assets/js/meta-box.js',
                    array('jquery'),
                    ALENSEO_MINIMAL_VERSION,
                    true
                );
                
                // AJAX-URL und Nonce für JavaScript
                wp_localize_script('alenseo-meta-box-js', 'alenseoData', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('alenseo_ajax_nonce')
                ));
            }
        }
        
        // Plugin-Einstellungsseite-Assets laden
        if ('toplevel_page_alenseo-minimal-settings' === $hook || strpos($hook, 'alenseo-settings') !== false) {
            // Admin CSS
            if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/css/admin.css')) {
                wp_enqueue_style(
                    'alenseo-admin-css',
                    ALENSEO_MINIMAL_URL . 'assets/css/admin.css',
                    array(),
                    ALENSEO_MINIMAL_VERSION
                );
            }
            
            // Admin JS
            if (file_exists(ALENSEO_MINIMAL_DIR . 'assets/js/admin.js')) {
                wp_enqueue_script(
                    'alenseo-admin-js',
                    ALENSEO_MINIMAL_URL . 'assets/js/admin.js',
                    array('jquery'),
                    ALENSEO_MINIMAL_VERSION,
                    true
                );
                
                // AJAX-URL und Nonce für JavaScript
                wp_localize_script('alenseo-admin-js', 'alenseoAdminData', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
                    'messages' => array(
                        'apiTestSuccess' => __('API-Test erfolgreich!', 'alenseo'),
                        'apiTestFailed' => __('API-Test fehlgeschlagen: ', 'alenseo'),
                        'apiTesting' => __('API wird getestet...', 'alenseo'),
                    )
                ));
            }
        }
    }

    public function display_api_status() {
        $claude_api = new \Alenseo\Alenseo_Claude_API();
        $status = $claude_api->get_api_status();
        
        $status_class = '';
        $status_icon = '';
        
        switch ($status['status']) {
            case 'connected':
                $status_class = 'alenseo-status-connected';
                $status_icon = '✓';
                break;
            case 'error':
                $status_class = 'alenseo-status-error';
                $status_icon = '⚠';
                break;
            case 'not-configured':
                $status_class = 'alenseo-status-not-configured';
                $status_icon = '⚙';
                break;
            default:
                $status_class = 'alenseo-status-unknown';
                $status_icon = '?';
        }
        
        ?>
        <div class="alenseo-api-status">
            <div class="alenseo-status-indicator <?php echo esc_attr($status_class); ?>">
                <span class="alenseo-status-icon"><?php echo esc_html($status_icon); ?></span>
                <span class="alenseo-status-text"><?php echo esc_html($status['message']); ?></span>
            </div>
            
            <?php if ($status['status'] === 'connected'): ?>
            <div class="alenseo-rate-limits">
                <h4><?php _e('API-Nutzung', 'alenseo'); ?></h4>
                <div class="alenseo-rate-limit-bar">
                    <div class="alenseo-rate-limit-label"><?php _e('Anfragen', 'alenseo'); ?></div>
                    <div class="alenseo-progress-bar">
                        <div class="alenseo-progress" style="width: <?php echo esc_attr(($status['rate_limits']['requests']['used'] / $status['rate_limits']['requests']['max']) * 100); ?>%"></div>
                    </div>
                    <div class="alenseo-rate-limit-count">
                        <?php echo esc_html($status['rate_limits']['requests']['used']); ?>/<?php echo esc_html($status['rate_limits']['requests']['max']); ?>
                    </div>
                </div>
                
                <div class="alenseo-rate-limit-bar">
                    <div class="alenseo-rate-limit-label"><?php _e('Tokens', 'alenseo'); ?></div>
                    <div class="alenseo-progress-bar">
                        <div class="alenseo-progress" style="width: <?php echo esc_attr(($status['rate_limits']['tokens']['used'] / $status['rate_limits']['tokens']['max']) * 100); ?>%"></div>
                    </div>
                    <div class="alenseo-rate-limit-count">
                        <?php echo esc_html($status['rate_limits']['tokens']['used']); ?>/<?php echo esc_html($status['rate_limits']['tokens']['max']); ?>
                    </div>
                </div>
                
                <div class="alenseo-reset-time">
                    <?php printf(
                        __('Zurücksetzung um %s', 'alenseo'),
                        date_i18n(get_option('time_format'), strtotime($status['rate_limits']['requests']['reset_time']))
                    ); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
            .alenseo-api-status {
                background: #fff;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                margin: 20px 0;
            }
            
            .alenseo-status-indicator {
                display: flex;
                align-items: center;
                padding: 10px;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            
            .alenseo-status-connected {
                background: #e7f5ea;
                color: #1e7e34;
            }
            
            .alenseo-status-error {
                background: #fbe7e7;
                color: #dc3545;
            }
            
            .alenseo-status-not-configured {
                background: #fff3cd;
                color: #856404;
            }
            
            .alenseo-status-icon {
                font-size: 20px;
                margin-right: 10px;
            }
            
            .alenseo-rate-limits {
                margin-top: 15px;
            }
            
            .alenseo-rate-limit-bar {
                margin: 10px 0;
            }
            
            .alenseo-progress-bar {
                background: #f0f0f0;
                height: 20px;
                border-radius: 10px;
                overflow: hidden;
                margin: 5px 0;
            }
            
            .alenseo-progress {
                background: #0073aa;
                height: 100%;
                transition: width 0.3s ease;
            }
            
            .alenseo-rate-limit-count {
                font-size: 12px;
                color: #666;
            }
            
            .alenseo-reset-time {
                font-size: 12px;
                color: #666;
                margin-top: 10px;
            }
        </style>
        <?php
    }
}
