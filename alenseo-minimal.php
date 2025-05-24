<?php
/**
 * Plugin Name: Alenseo SEO Minimal
 * Plugin URI: https://www.imponi.ch
 * Description: Ein schlankes SEO-Plugin mit Claude AI-Integration für WordPress
 * Version: 2.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Alen
 * Author URI: https://www.imponi.ch
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Company: Imponi Bern-Worb GmbH
 * Text Domain: alenseo
 * Domain Path: /languages
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Singleton-Klasse für bessere Struktur
final class Alenseo_SEO_Minimal {
    
    private static $instance = null;
    private $components = [];
    private $version = '2.1.0';
    
    /**
     * Singleton-Instanz abrufen
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor
     */
    private function __construct() {
        $this->define_constants();
        if ($this->check_requirements()) {
            $this->register_hooks();
        }
    }
    
    /**
     * Konstanten definieren
     */
    private function define_constants() {
define('ALENSEO_MINIMAL_DIR', plugin_dir_path(__FILE__));
define('ALENSEO_MINIMAL_URL', plugin_dir_url(__FILE__));
        define('ALENSEO_MINIMAL_VERSION', $this->version);
define('ALENSEO_PLUGIN_FILE', __FILE__);
        define('ALENSEO_MIN_PHP_VERSION', '7.4');
        define('ALENSEO_MIN_WP_VERSION', '5.8');
        define('ALENSEO_PLUGIN_BASENAME', plugin_basename(__FILE__));
    }
    
    /**
     * Anforderungen prüfen
     */
    private function check_requirements() {
        if (version_compare(PHP_VERSION, ALENSEO_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'php_version_notice']);
            return false;
        }
        
        global $wp_version;
        if (version_compare($wp_version, ALENSEO_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'wp_version_notice']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Hooks registrieren
     */
    private function register_hooks() {
        // Initialisierung nach dem Laden aller Plugins
        add_action('plugins_loaded', [$this, 'init'], 10);
        
        // Aktivierung/Deaktivierung
        register_activation_hook(ALENSEO_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(ALENSEO_PLUGIN_FILE, [$this, 'deactivate']);
        register_uninstall_hook(ALENSEO_PLUGIN_FILE, [__CLASS__, 'uninstall']);
        
        // Plugin-Links
        add_filter('plugin_action_links_' . ALENSEO_PLUGIN_BASENAME, [$this, 'add_action_links']);
        add_filter('plugin_row_meta', [$this, 'add_plugin_meta_links'], 10, 2);
        
        // Multisite-Unterstützung
        if (is_multisite()) {
            add_action('network_admin_menu', [$this, 'add_network_menu']);
        }
    }
    
    /**
     * Plugin initialisieren
     */
    public function init() {
        // Prüfen, ob bereits eine andere Version aktiv ist
        if (defined('ALENSEO_ACTIVE') && ALENSEO_ACTIVE !== ALENSEO_PLUGIN_FILE) {
            add_action('admin_notices', [$this, 'duplicate_plugin_notice']);
            return;
        }
        
        define('ALENSEO_ACTIVE', ALENSEO_PLUGIN_FILE);
        
        // Komponenten laden
        $this->load_dependencies();
        $this->init_components();
        
        // Textdomain laden
        $this->load_textdomain();
        
        // AJAX-Handler registrieren
        $this->register_ajax_handlers();
    }
    
    /**
     * Abhängigkeiten laden
     */
    private function load_dependencies() {
        $required_files = [
            'includes/class-database.php',
            'includes/debug.php',
    'includes/minimal-admin.php',
    'includes/class-minimal-analysis.php',
    'includes/class-enhanced-analysis.php',
    'includes/class-dashboard.php',
    'includes/class-meta-box.php',
    'includes/class-claude-api.php',
    'includes/class-content-optimizer.php',
    'includes/alenseo-ajax-handlers.php',
    'includes/alenseo-claude-ajax.php',
    'includes/alenseo-enhanced-ajax.php',
    'includes/alenseo-settings-ajax.php',
    'includes/alenseo-optimizer-ajax.php',
    'includes/alenseo-test-functions.php'
        ];

foreach ($required_files as $file) {
            $file_path = ALENSEO_MINIMAL_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                $this->log_error("Datei nicht gefunden: {$file}");
            }
        }
        
        // WPBakery-Integration nur laden, wenn verfügbar
        if ($this->is_wpbakery_active()) {
            $wpbakery_file = ALENSEO_MINIMAL_DIR . 'includes/class-wpbakery-integration.php';
            if (file_exists($wpbakery_file)) {
                require_once $wpbakery_file;
            }
        }
    }
    
    /**
     * Komponenten initialisieren
     */
    private function init_components() {
        try {
            // Datenbankkomponente zuerst
        if (class_exists('Alenseo_Database')) {
                $this->components['database'] = new Alenseo_Database();
            }
            
            // Admin-Komponenten
            if (is_admin()) {
        if (class_exists('Alenseo_Minimal_Admin')) {
                    $this->components['admin'] = new Alenseo_Minimal_Admin();
        }
        
        if (class_exists('Alenseo_Dashboard')) {
                    $this->components['dashboard'] = new Alenseo_Dashboard();
                }
            }
            
            // Meta Box für alle Bereiche
            if (class_exists('Alenseo_Meta_Box')) {
                $this->components['meta_box'] = new Alenseo_Meta_Box();
            }
            
            // WPBakery-Integration
            if ($this->is_wpbakery_active() && class_exists('Alenseo_WPBakery_Integration')) {
                $this->components['wpbakery'] = new Alenseo_WPBakery_Integration();
            }
            
            // Enhanced Analysis aktivieren
            if (class_exists('Alenseo_Enhanced_Analysis')) {
                add_filter('alenseo_use_enhanced_analysis', '__return_true');
            }
            
        } catch (Exception $e) {
            $this->log_error('Initialisierungsfehler: ' . $e->getMessage());
            add_action('admin_notices', [$this, 'init_error_notice']);
        }
    }
    
    /**
     * AJAX-Handler registrieren
     */
    private function register_ajax_handlers() {
        if (class_exists('Alenseo_Claude_API')) {
            Alenseo_Claude_API::register_ajax_handlers();
        }
    }
    
    /**
     * Plugin aktivieren
     */
    public function activate() {
        // Standardeinstellungen erstellen
        if (!get_option('alenseo_settings')) {
            $default_settings = [
                'claude_api_key' => '',
                'claude_model' => 'claude-3-haiku-20240307',
                'post_types' => ['post', 'page'],
                'seo_elements' => [
                    'meta_title' => true,
                    'meta_description' => true,
                    'headings' => true,
                    'content' => true
                ],
                'advanced' => [
                    'cache_duration' => 3600,
                    'track_api_usage' => true,
                    'store_seo_history' => true,
                    'debug_mode' => false
                ]
            ];
            update_option('alenseo_settings', $default_settings);
        }
        
        // Datenbank-Tabellen erstellen
        if (isset($this->components['database'])) {
            $this->components['database']->install();
        }
        
        // Rewrite-Regeln aktualisieren
        flush_rewrite_rules();
        
        // Aktivierungszeit speichern
        update_option('alenseo_activated', time());
        
        // Aktivierungsprotokoll
        $this->log_activation();
    }
    
    /**
     * Plugin deaktivieren
     */
    public function deactivate() {
        // Rewrite-Regeln zurücksetzen
        flush_rewrite_rules();
        
        // Geplante Aufgaben entfernen
        wp_clear_scheduled_hook('alenseo_daily_cleanup');
        
        // Transients löschen
        $this->delete_transients();
        
        // Deaktivierungsprotokoll
        $this->log_deactivation();
    }
    
    /**
     * Plugin deinstallieren
     */
    public static function uninstall() {
        // Einstellungen löschen
        delete_option('alenseo_settings');
        delete_option('alenseo_activated');
        delete_option('alenseo_cache_stats');
        delete_option('alenseo_api_usage');
        delete_option('alenseo_monthly_tokens');
        
        // Datenbank-Tabellen löschen
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}alenseo_seo_history");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}alenseo_api_logs");
        
        // Transients löschen
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_alenseo_%' 
             OR option_name LIKE '_transient_timeout_alenseo_%'"
        );
    }
    
    /**
     * Plugin-Action-Links hinzufügen
     */
    public function add_action_links($links) {
        $plugin_links = [
            '<a href="' . esc_url(admin_url('admin.php?page=alenseo-minimal-settings')) . '">' . __('Einstellungen', 'alenseo') . '</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=alenseo-optimizer')) . '">' . __('SEO-Optimierung', 'alenseo') . '</a>'
        ];
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Plugin-Meta-Links hinzufügen
     */
    public function add_plugin_meta_links($links, $file) {
        if ($file === ALENSEO_PLUGIN_BASENAME) {
            $links[] = '<a href="https://www.imponi.ch/support" target="_blank">' . __('Support', 'alenseo') . '</a>';
            $links[] = '<a href="https://www.imponi.ch/docs" target="_blank">' . __('Dokumentation', 'alenseo') . '</a>';
        }
        return $links;
    }
    
    /**
     * Multisite-Menü hinzufügen
     */
    public function add_network_menu() {
        add_menu_page(
            __('Alenseo Netzwerkeinstellungen', 'alenseo'),
            'Alenseo SEO',
            'manage_network_options',
            'alenseo-network-settings',
            [$this, 'render_network_settings']
        );
    }
    
    /**
     * Netzwerkeinstellungen rendern
     */
    public function render_network_settings() {
        // Formular verarbeiten
        if (isset($_POST['alenseo_network_nonce']) && 
            wp_verify_nonce($_POST['alenseo_network_nonce'], 'alenseo_network_settings')) {
            
            $global_setting = sanitize_text_field($_POST['global_seo_setting'] ?? '');
            update_site_option('alenseo_global_seo_setting', $global_setting);
            
            echo '<div class="notice notice-success"><p>' . __('Einstellungen gespeichert.', 'alenseo') . '</p></div>';
        }
        
        $current_setting = get_site_option('alenseo_global_seo_setting', '');
        ?>
        <div class="wrap">
            <h1><?php _e('Alenseo Netzwerkeinstellungen', 'alenseo'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('alenseo_network_settings', 'alenseo_network_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="global-seo-setting"><?php _e('Globale SEO-Einstellung:', 'alenseo'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="global-seo-setting" 
                                   name="global_seo_setting" 
                                   value="<?php echo esc_attr($current_setting); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Diese Einstellung gilt für alle Websites im Netzwerk.', 'alenseo'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Einstellungen speichern', 'alenseo')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Hilfsmethoden
     */
    
    private function is_wpbakery_active() {
        return defined('WPB_VC_VERSION') || class_exists('WPBakeryVisualComposer');
    }
    
    private function log_error($message) {
        if (WP_DEBUG && $this->is_debug_mode()) {
            error_log('Alenseo SEO: ' . $message);
        }
    }
    
    private function is_debug_mode() {
        $settings = get_option('alenseo_settings', []);
        return !empty($settings['advanced']['debug_mode']);
    }
    
    private function delete_transients() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_alenseo_%' 
             OR option_name LIKE '_transient_timeout_alenseo_%'"
        );
    }
    
    private function load_textdomain() {
        load_plugin_textdomain('alenseo', false, dirname(ALENSEO_PLUGIN_BASENAME) . '/languages');
    }
    
    private function log_activation() {
        $activation_log = get_option('alenseo_activation_log', []);
        $activation_log[] = [
            'time' => time(),
            'version' => $this->version,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ];
        update_option('alenseo_activation_log', $activation_log);
    }
    
    private function log_deactivation() {
        $deactivation_log = get_option('alenseo_deactivation_log', []);
        $deactivation_log[] = [
            'time' => time(),
            'version' => $this->version
        ];
        update_option('alenseo_deactivation_log', $deactivation_log);
    }
    
    /**
     * Admin-Notices
     */
    
    public function php_version_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>Alenseo SEO Minimal</strong> <?php _e('benötigt mindestens PHP', 'alenseo'); ?> <?php echo ALENSEO_MIN_PHP_VERSION; ?>. 
                <?php _e('Ihre Version:', 'alenseo'); ?> <?php echo PHP_VERSION; ?>
            </p>
        </div>
        <?php
    }
    
    public function wp_version_notice() {
        global $wp_version;
        ?>
        <div class="notice notice-error">
            <p>
                <strong>Alenseo SEO Minimal</strong> <?php _e('benötigt mindestens WordPress', 'alenseo'); ?> <?php echo ALENSEO_MIN_WP_VERSION; ?>. 
                <?php _e('Ihre Version:', 'alenseo'); ?> <?php echo $wp_version; ?>
            </p>
        </div>
        <?php
    }
    
    public function duplicate_plugin_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php _e('Eine andere Version von', 'alenseo'); ?> <strong>Alenseo SEO</strong> <?php _e('ist bereits aktiv.', 'alenseo'); ?> 
                <?php _e('Bitte deaktivieren Sie alle anderen Versionen des Plugins.', 'alenseo'); ?>
            </p>
        </div>
        <?php
    }
    
    public function init_error_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>Alenseo SEO Minimal:</strong> <?php _e('Initialisierungsfehler.', 'alenseo'); ?> 
                <?php _e('Bitte überprüfen Sie die Fehlerprotokolle oder kontaktieren Sie den Support.', 'alenseo'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Komponente abrufen
     */
    public function get_component($name) {
        return $this->components[$name] ?? null;
    }
}

// Plugin initialisieren
function alenseo_seo_minimal() {
    return Alenseo_SEO_Minimal::get_instance();
}

// Starte das Plugin
alenseo_seo_minimal(); 