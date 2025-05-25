<?php
namespace Alenseo;

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
        // DISABLED: Admin-Menü wird jetzt zentral in der Hauptklasse verwaltet
        // add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin-Assets laden
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Alenseo SEO', 'alenseo'),
            __('Alenseo SEO', 'alenseo'),
            'manage_options',
            'alenseo-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-bar',
            80
        );
        
        add_submenu_page(
            'alenseo-dashboard',
            __('Dashboard', 'alenseo'),
            __('Dashboard', 'alenseo'),
            'manage_options',
            'alenseo-dashboard',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'alenseo-dashboard',
            __('Einstellungen', 'alenseo'),
            __('Einstellungen', 'alenseo'),
            'manage_options',
            'alenseo-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'alenseo-dashboard',
            __('Seiten optimieren', 'alenseo'),
            __('Seiten optimieren', 'alenseo'),
            'manage_options',
            'alenseo-optimizer',
            array($this, 'render_optimizer_page')
        );
    }
    
    /**
     * Dashboard-Seite rendern
     */
    public function render_dashboard_page() {
        include ALENSEO_MINIMAL_DIR . 'templates/dashboard-page-visual.php';
    }
    
    /**
     * Einstellungsseite rendern
     */
    public function render_settings_page() {
        include ALENSEO_MINIMAL_DIR . 'templates/settings-page.php';
    }
    
    /**
     * Optimizer-Seite rendern
     */
    public function render_optimizer_page() {
        include ALENSEO_MINIMAL_DIR . 'templates/optimizer-page.php';
    }
    
    /**
     * Admin-Assets laden
     */
    public function enqueue_admin_assets($hook) {
        // Nur auf Plugin-Seiten laden
        if (strpos($hook, 'alenseo') === false) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'alenseo-admin-css',
            ALENSEO_MINIMAL_URL . 'assets/css/admin.css',
            array(),
            ALENSEO_MINIMAL_VERSION
        );
        
        // Dashboard Visual CSS & JS nur auf Dashboard-Seite laden
        if (isset($_GET['page']) && $_GET['page'] === 'alenseo-dashboard') {
            wp_enqueue_style(
                'alenseo-dashboard-visual-css',
                ALENSEO_MINIMAL_URL . 'assets/css/dashboard-visual.css',
                array(),
                ALENSEO_MINIMAL_VERSION
            );
            wp_enqueue_script(
                'alenseo-dashboard-visual-js',
                ALENSEO_MINIMAL_URL . 'assets/js/dashboard-visual.js',
                array('jquery'),
                ALENSEO_MINIMAL_VERSION,
                true
            );
        }
        
        // Admin JS
        wp_enqueue_script(
            'alenseo-admin-js',
            ALENSEO_MINIMAL_URL . 'assets/js/admin.js',
            array('jquery'),
            ALENSEO_MINIMAL_VERSION,
            true
        );
        
        // AJAX-URL und Nonce für JavaScript
        wp_localize_script('alenseo-admin-js', 'alenseoData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alenseo_ajax_nonce')
        ));
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
