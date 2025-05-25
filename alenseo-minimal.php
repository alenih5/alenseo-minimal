<?php
/**
 * Plugin Name: Alenseo SEO Minimal
 * Plugin URI: https://www.imponi.ch
 * Description: Ein schlankes SEO-Plugin mit Claude AI-Integration für WordPress - Erweiterte Version mit Multi-Modell-Support
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
        
        // Zusätzliche Konstanten für die neuen Features
        define('ALENSEO_VERSION', $this->version);
        define('ALENSEO_PLUGIN_SLUG', 'alenseo-seo');
        define('ALENSEO_TEXT_DOMAIN', 'alenseo');
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
        
        // WordPress-Hooks für erweiterte Funktionen
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
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
        
        // Cleanup-Cron-Job registrieren
        if (!wp_next_scheduled('alenseo_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'alenseo_cleanup_cache');
        }
        add_action('alenseo_cleanup_cache', [$this, 'cleanup_cache']);
    }
    
    /**
     * Abhängigkeiten laden - ERWEITERTE VERSION
     */
    private function load_dependencies() {
        // Bestehende Dateien
        $existing_files = [
            'includes/class-database.php',
            'includes/minimal-admin.php',
            'includes/class-dashboard.php',
            'includes/class-meta-box.php'
        ];
        
        // Neue API-Komponenten
        $new_api_files = [
            'includes/class-ai-api.php',
            'includes/class-claude-api.php',
            'includes/class-chatgpt-api.php'
        ];
        
        // Neue AJAX-Handler
        $new_ajax_files = [
            'includes/alenseo-ajax-handlers.php',
            'includes/alenseo-settings-ajax.php'
        ];
        
        // Alle Dateien laden
        $all_files = array_merge($existing_files, $new_api_files, $new_ajax_files);
        
        foreach ($all_files as $file) {
            $file_path = ALENSEO_MINIMAL_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->log_debug("Loaded: {$file}");
            } else {
                $this->log_error("Missing file: {$file}");
            }
        }
        
        // Legacy-Unterstützung für alte claude-api.php (falls noch vorhanden)
        $legacy_claude = ALENSEO_MINIMAL_DIR . 'includes/class-claude-api-legacy.php';
        if (file_exists($legacy_claude) && !class_exists('Alenseo\\Alenseo_Claude_API')) {
            require_once $legacy_claude;
        }
    }
    
    /**
     * Komponenten initialisieren - ERWEITERTE VERSION
     */
    private function init_components() {
        try {
            // Datenbankkomponente
            if (class_exists('Alenseo\\Alenseo_Database')) {
                $this->components['database'] = new \Alenseo\Alenseo_Database();
            }
            
            // API-Komponenten (neue)
            if (class_exists('Alenseo\\Alenseo_Claude_API')) {
                $this->components['claude_api'] = new \Alenseo\Alenseo_Claude_API();
                $this->log_debug('Claude API initialisiert');
            }
            
            if (class_exists('Alenseo\\Alenseo_ChatGPT_API')) {
                $this->components['chatgpt_api'] = new \Alenseo\Alenseo_ChatGPT_API();
                $this->log_debug('ChatGPT API initialisiert');
            }
            
            // Admin-Komponenten
            if (is_admin()) {
                if (class_exists('Alenseo\\Alenseo_Minimal_Admin')) {
                    $this->components['admin'] = new \Alenseo\Alenseo_Minimal_Admin();
                }
                if (class_exists('Alenseo\\Alenseo_Dashboard')) {
                    $this->components['dashboard'] = new \Alenseo\Alenseo_Dashboard();
                }
            }
            
            // Meta Box (erweiterte Version)
            if (class_exists('Alenseo\\Alenseo_Meta_Box')) {
                $this->components['meta_box'] = new \Alenseo\Alenseo_Meta_Box();
            }
            
        } catch (\Exception $e) {
            $this->log_error('Fehler bei der Komponenten-Initialisierung: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX-Handler registrieren - ERWEITERTE VERSION
     */
    private function register_ajax_handlers() {
        // Page Optimizer AJAX Handler
        add_action('wp_ajax_alenseo_analyze_page', [$this, 'ajax_analyze_page']);
        
        // API Test Handlers (main class methods as fallback)
        add_action('wp_ajax_alenseo_test_claude_api', [$this, 'ajax_test_claude_api']);
        add_action('wp_ajax_alenseo_test_openai_api', [$this, 'ajax_test_openai_api']);
        
        // Settings AJAX handlers (from external file)
        if (function_exists('Alenseo\\alenseo_test_claude_api_settings')) {
            add_action('wp_ajax_alenseo_test_claude_api_settings', 'Alenseo\\alenseo_test_claude_api_settings');
        }
        
        if (function_exists('Alenseo\\alenseo_test_openai_api_settings')) {
            add_action('wp_ajax_alenseo_test_openai_api_settings', 'Alenseo\\alenseo_test_openai_api_settings');
        }
        
        if (function_exists('Alenseo\\alenseo_save_settings_ajax')) {
            add_action('wp_ajax_alenseo_save_settings', 'Alenseo\\alenseo_save_settings_ajax');
        }
        
        // Universal SEO request handler
        if (function_exists('Alenseo\\alenseo_universal_seo_request')) {
            add_action('wp_ajax_alenseo_universal_seo_request', 'Alenseo\\alenseo_universal_seo_request');
        }
        
        // Bulk analysis handlers
        add_action('wp_ajax_alenseo_load_posts', [$this, 'ajax_load_posts']);
        add_action('wp_ajax_alenseo_bulk_analyze', [$this, 'ajax_bulk_analyze']);
        
        // Dashboard data handlers
        add_action('wp_ajax_alenseo_get_dashboard_data', [$this, 'ajax_get_dashboard_data']);
        add_action('wp_ajax_alenseo_get_api_status', [$this, 'ajax_get_api_status']);
        
        // Existing handlers from the AJAX files
        if (function_exists('Alenseo\\alenseo_analyze_post_ajax')) {
            add_action('wp_ajax_alenseo_analyze_post', 'Alenseo\\alenseo_analyze_post_ajax');
            $this->log_debug('Neue AJAX-Handler erfolgreich geladen');
        }
        
        // Legacy-Support für alte Handler
        if (class_exists('Alenseo\\Alenseo_Ajax_Handlers')) {
            new \Alenseo\Alenseo_Ajax_Handlers();
        }
    }
    
    /**
     * Admin-Scripts laden - NEUE FUNKTION
     */
    public function enqueue_admin_scripts($hook) {
        // Nur in relevanten Admin-Bereichen laden
        $allowed_hooks = [
            'post.php',
            'post-new.php',
            'edit.php',
            'toplevel_page_alenseo-dashboard',
            'alenseo_page_alenseo-settings',
            'alenseo_page_alenseo-bulk-optimizer'
        ];
        
        if (!in_array($hook, $allowed_hooks) && strpos($hook, 'alenseo') === false) {
            return;
        }
        
        // JavaScript (neue erweiterte Version)
        wp_enqueue_script(
            'alenseo-admin-js',
            ALENSEO_MINIMAL_URL . 'assets/js/alenseo-admin.js',
            ['jquery', 'wp-util'],
            $this->version,
            true
        );
        
        // CSS
        wp_enqueue_style(
            'alenseo-admin-css',
            ALENSEO_MINIMAL_URL . 'assets/css/alenseo-admin.css',
            [],
            $this->version
        );
        
        // Lokalisierung für JavaScript
        wp_localize_script('alenseo-admin-js', 'alenseo_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
            'debug' => WP_DEBUG,
            'version' => $this->version,
            'text_domain' => 'alenseo',
            'strings' => [
                'processing' => __('Verarbeite...', 'alenseo'),
                'error' => __('Fehler:', 'alenseo'),
                'success' => __('Erfolgreich!', 'alenseo'),
                'confirm_action' => __('Sind Sie sicher?', 'alenseo')
            ],
            'settings' => [
                'timeout' => 30000,
                'retry_attempts' => 2,
                'rate_limit_delay' => 500
            ]
        ]);
        
        // Legacy-Support für bestehende JS-Variablen
        wp_localize_script('alenseo-admin-js', 'alenseoData', [
            'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
        ]);
        
        wp_localize_script('alenseo-admin-js', 'alenseoAdminData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alenseo_ajax_nonce'),
        ]);
        
        // Für die neue API
        wp_add_inline_script('alenseo-admin-js', 
            'window.alenseo_ajax_nonce = "' . wp_create_nonce('alenseo_ajax_nonce') . '";', 
            'before'
        );
    }
    
    /**
     * Admin-Menü hinzufügen - KONSOLIDIERTE VERSION (fixes duplicate menus)
     */
    public function add_admin_menu() {
        // Hauptmenü (Dashboard)
        add_menu_page(
            __('Alenseo SEO', 'alenseo'),
            __('Alenseo SEO', 'alenseo'),
            'manage_options',
            'alenseo-dashboard',
            [$this, 'display_dashboard_page'],
            'data:image/svg+xml;base64,' . base64_encode('<svg viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2"/></svg>'),
            80
        );
        
        // Unterseiten
        add_submenu_page(
            'alenseo-dashboard',
            __('Dashboard', 'alenseo'),
            __('Dashboard', 'alenseo'),
            'manage_options',
            'alenseo-dashboard',
            [$this, 'display_dashboard_page']
        );
        
        add_submenu_page(
            'alenseo-dashboard',
            __('Einstellungen', 'alenseo'),
            __('Einstellungen', 'alenseo'),
            'manage_options',
            'alenseo-settings',
            [$this, 'display_settings_page']
        );
        
        // Page Optimizer mit Content URLs und Image Display
        add_submenu_page(
            'alenseo-dashboard',
            __('Page Optimizer', 'alenseo'),
            __('Page Optimizer', 'alenseo'),
            'edit_posts',
            'alenseo-page-optimizer',
            [$this, 'display_page_optimizer_page']
        );
        
        // Bulk-Optimizer
        add_submenu_page(
            'alenseo-dashboard',
            __('Bulk-Optimizer', 'alenseo'),
            __('Bulk-Optimizer', 'alenseo'),
            'edit_posts',
            'alenseo-bulk-optimizer',
            [$this, 'display_bulk_optimizer_page']
        );
        
        // API-Status & Tests (integriert Claude API Test)
        add_submenu_page(
            'alenseo-dashboard',
            __('API-Status & Tests', 'alenseo'),
            __('API-Status & Tests', 'alenseo'),
            'manage_options',
            'alenseo-api-status',
            [$this, 'display_api_status_page']
        );
    }
    
    /**
     * Meta-Boxen hinzufügen - NEUE FUNKTION
     */
    public function add_meta_boxes() {
        $post_types = get_post_types(['public' => true]);
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'alenseo-seo-meta-box',
                __('Alenseo SEO', 'alenseo'),
                [$this, 'display_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Admin-Notices anzeigen - NEUE FUNKTION
     */
    public function show_admin_notices() {
        // API-Konfiguration prüfen
        $settings = get_option('alenseo_settings', []);
        $has_api = !empty($settings['claude_api_key']) || !empty($settings['openai_api_key']);
        
        if (!$has_api) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('Alenseo SEO:', 'alenseo') . '</strong> ';
            echo sprintf(
                __('Bitte konfigurieren Sie Ihre API-Schlüssel in den <a href="%s">Einstellungen</a>, um alle Funktionen zu nutzen.', 'alenseo'),
                admin_url('admin.php?page=alenseo-settings')
            );
            echo '</p></div>';
        }
        
        // Version-Upgrade-Notice
        $current_version = get_option('alenseo_version', '1.0.0');
        if (version_compare($current_version, $this->version, '<')) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . __('Alenseo SEO wurde aktualisiert!', 'alenseo') . '</strong> ';
            echo sprintf(
                __('Version %s bringt neue Features. <a href="%s">Mehr erfahren</a>', 'alenseo'),
                $this->version,
                admin_url('admin.php?page=alenseo-api-status')
            );
            echo '</p></div>';
            
            update_option('alenseo_version', $this->version);
        }
    }
    
    /**
     * Seiten-Display-Methoden
     */
    public function display_dashboard_page() {
        if (isset($this->components['dashboard'])) {
            $this->components['dashboard']->display();
        } else {
            $this->display_basic_dashboard();
        }
    }
    
    public function display_settings_page() {
        $this->display_enhanced_settings_page();
    }
    
    public function display_bulk_optimizer_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Bulk-Optimizer', 'alenseo') . '</h1>';
        echo '<p>' . __('Optimieren Sie mehrere Beiträge gleichzeitig mit KI-Unterstützung.', 'alenseo') . '</p>';
        echo '<div id="alenseo-bulk-optimizer-app"></div>';
        echo '</div>';
    }
    
    public function display_page_optimizer_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Page Optimizer', 'alenseo'); ?></h1>
            <p><?php _e('Analysieren und optimieren Sie einzelne Seiten mit KI-Unterstützung. Zeigen Sie Content URLs und Images an.', 'alenseo'); ?></p>
            
            <div class="alenseo-page-optimizer">
                <form id="page-optimizer-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="page-url"><?php _e('Seiten-URL oder ID', 'alenseo'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="page-url" name="page_url" class="regular-text" 
                                       placeholder="<?php _e('https://example.com/seite oder Post-ID eingeben', 'alenseo'); ?>" />
                                <button type="button" id="analyze-page-btn" class="button button-primary">
                                    <?php _e('Seite analysieren', 'alenseo'); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </form>
                
                <div id="page-analysis-results" style="margin-top: 20px; display: none;">
                    <h2><?php _e('Analyseergebnisse', 'alenseo'); ?></h2>
                    <div id="analysis-content"></div>
                </div>
                
                <div id="content-urls-section" style="margin-top: 20px; display: none;">
                    <h2><?php _e('Content URLs', 'alenseo'); ?></h2>
                    <div id="content-urls-list"></div>
                </div>
                
                <div id="images-section" style="margin-top: 20px; display: none;">
                    <h2><?php _e('Bilder auf der Seite', 'alenseo'); ?></h2>
                    <div id="images-gallery"></div>
                </div>
            </div>
        </div>
        
        <style>
        .alenseo-page-optimizer {
            max-width: 1000px;
        }
        .analysis-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            margin: 10px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .seo-score {
            font-size: 24px;
            font-weight: bold;
            padding: 10px;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 0;
        }
        .score-excellent { background: #d4edda; color: #155724; }
        .score-good { background: #fff3cd; color: #856404; }
        .score-poor { background: #f8d7da; color: #721c24; }
        .content-url-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
            margin: 5px 0;
            border-radius: 3px;
        }
        .image-item {
            display: inline-block;
            margin: 10px;
            text-align: center;
            vertical-align: top;
            max-width: 200px;
        }
        .image-item img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .image-info {
            font-size: 12px;
            margin-top: 5px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#analyze-page-btn').on('click', function() {
                var button = $(this);
                var pageUrl = $('#page-url').val().trim();
                
                if (!pageUrl) {
                    alert('<?php _e('Bitte geben Sie eine URL oder Post-ID ein.', 'alenseo'); ?>');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('Analysiere...', 'alenseo'); ?>');
                $('#page-analysis-results, #content-urls-section, #images-section').hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'alenseo_analyze_page',
                        page_url: pageUrl,
                        nonce: '<?php echo wp_create_nonce('alenseo_ajax_nonce'); ?>'
                    },
                    timeout: 30000,
                    success: function(response) {
                        button.prop('disabled', false).text('<?php _e('Seite analysieren', 'alenseo'); ?>');
                        
                        if (response.success) {
                            displayAnalysisResults(response.data);
                        } else {
                            $('#analysis-content').html('<div class="notice notice-error"><p>' + (response.data ? response.data.message : 'Unbekannter Fehler') + '</p></div>');
                            $('#page-analysis-results').show();
                        }
                    },
                    error: function(xhr, status, error) {
                        button.prop('disabled', false).text('<?php _e('Seite analysieren', 'alenseo'); ?>');
                        $('#analysis-content').html('<div class="notice notice-error"><p>Verbindungsfehler: ' + error + '</p></div>');
                        $('#page-analysis-results').show();
                    }
                });
            });
            
            function displayAnalysisResults(data) {
                var html = '';
                
                // SEO Score
                if (data.seo_score) {
                    var scoreClass = data.seo_score >= 80 ? 'score-excellent' : (data.seo_score >= 60 ? 'score-good' : 'score-poor');
                    html += '<div class="analysis-card">';
                    html += '<h3><?php _e('SEO Score', 'alenseo'); ?></h3>';
                    html += '<div class="seo-score ' + scoreClass + '">' + data.seo_score + '/100</div>';
                    html += '</div>';
                }
                
                // SEO Analysis
                if (data.analysis) {
                    html += '<div class="analysis-card">';
                    html += '<h3><?php _e('SEO Analyse', 'alenseo'); ?></h3>';
                    html += '<div>' + data.analysis + '</div>';
                    html += '</div>';
                }
                
                $('#analysis-content').html(html);
                $('#page-analysis-results').show();
                
                // Content URLs
                if (data.content_urls && data.content_urls.length > 0) {
                    var urlsHtml = '';
                    data.content_urls.forEach(function(url) {
                        urlsHtml += '<div class="content-url-item">';
                        urlsHtml += '<strong>URL:</strong> <a href="' + url.url + '" target="_blank">' + url.url + '</a><br>';
                        if (url.title) urlsHtml += '<strong>Titel:</strong> ' + url.title + '<br>';
                        if (url.description) urlsHtml += '<strong>Beschreibung:</strong> ' + url.description;
                        urlsHtml += '</div>';
                    });
                    $('#content-urls-list').html(urlsHtml);
                    $('#content-urls-section').show();
                }
                
                // Images
                if (data.images && data.images.length > 0) {
                    var imagesHtml = '';
                    data.images.forEach(function(image) {
                        imagesHtml += '<div class="image-item">';
                        imagesHtml += '<img src="' + image.src + '" alt="' + (image.alt || '') + '">';
                        imagesHtml += '<div class="image-info">';
                        if (image.alt) imagesHtml += '<strong>Alt:</strong> ' + image.alt + '<br>';
                        if (image.title) imagesHtml += '<strong>Titel:</strong> ' + image.title + '<br>';
                        if (image.size) imagesHtml += '<strong>Größe:</strong> ' + image.size;
                        imagesHtml += '</div>';
                        imagesHtml += '</div>';
                    });
                    $('#images-gallery').html(imagesHtml);
                    $('#images-section').show();
                }
            }
        });
        </script>
        <?php
    }

    public function display_api_status_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('API-Status & Tests', 'alenseo') . '</h1>';
        echo '<div id="alenseo-api-status-app"></div>';
        
        // Integration der Claude API Test Funktionalität
        echo '<div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">';
        echo '<h2>' . __('API Tests', 'alenseo') . '</h2>';
        echo '<button type="button" id="test-claude-api-btn" class="button button-secondary" style="margin-right: 10px;">' . __('Claude API testen', 'alenseo') . '</button>';
        echo '<button type="button" id="test-openai-api-btn" class="button button-secondary">' . __('OpenAI API testen', 'alenseo') . '</button>';
        echo '<div id="api-test-results-status" style="margin-top: 15px;"></div>';
        echo '</div>';
        
        echo '<script>
        jQuery(document).ready(function($) {
            // API Status anzeigen
            if (typeof Alenseo !== "undefined" && Alenseo.api) {
                Alenseo.api.getApiStatus().then(function(status) {
                    var html = "<div style=\"display: flex; gap: 20px; margin-bottom: 20px;\">";
                    html += "<div style=\"flex: 1; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: white;\">";
                    html += "<h2>Claude API</h2>";
                    html += status.claude.working ? "<span style=\"color: green; font-weight: bold;\">✓ Funktioniert</span>" : "<span style=\"color: red; font-weight: bold;\">✗ Nicht verfügbar</span>";
                    html += "<p>" + status.claude.message + "</p>";
                    html += "</div>";
                    
                    html += "<div style=\"flex: 1; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: white;\">";
                    html += "<h2>OpenAI API</h2>";
                    html += status.openai.working ? "<span style=\"color: green; font-weight: bold;\">✓ Funktioniert</span>" : "<span style=\"color: red; font-weight: bold;\">✗ Nicht verfügbar</span>";
                    html += "<p>" + status.openai.message + "</p>";
                    html += "</div>";
                    html += "</div>";
                    
                    $("#alenseo-api-status-app").html(html);
                });
            }
            
            // Claude API Test Button
            $("#test-claude-api-btn").on("click", function() {
                var button = $(this);
                button.prop("disabled", true).text("Teste Claude API...");
                $("#api-test-results-status").html("<p>Claude API wird getestet...</p>");
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "alenseo_test_claude_api",
                        nonce: "' . wp_create_nonce('alenseo_test_api_nonce') . '"
                    },
                    success: function(response) {
                        button.prop("disabled", false).text("Claude API testen");
                        if (response.success) {
                            $("#api-test-results-status").html("<div style=\"color: green; font-weight: bold;\">Claude API Test erfolgreich!</div>");
                        } else {
                            $("#api-test-results-status").html("<div style=\"color: red;\">Claude API Test fehlgeschlagen: " + (response.data ? response.data.message : "Unbekannter Fehler") + "</div>");
                        }
                    },
                    error: function() {
                        button.prop("disabled", false).text("Claude API testen");
                        $("#api-test-results-status").html("<div style=\"color: red;\">Verbindungsfehler beim Claude API Test</div>");
                    }
                });
            });
            
            // OpenAI API Test Button
            $("#test-openai-api-btn").on("click", function() {
                var button = $(this);
                button.prop("disabled", true).text("Teste OpenAI API...");
                $("#api-test-results-status").html("<p>OpenAI API wird getestet...</p>");
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "alenseo_test_openai_api",
                        nonce: "' . wp_create_nonce('alenseo_test_api_nonce') . '"
                    },
                    success: function(response) {
                        button.prop("disabled", false).text("OpenAI API testen");
                        if (response.success) {
                            $("#api-test-results-status").html("<div style=\"color: green; font-weight: bold;\">OpenAI API Test erfolgreich!</div>");
                        } else {
                            $("#api-test-results-status").html("<div style=\"color: red;\">OpenAI API Test fehlgeschlagen: " + (response.data ? response.data.message : "Unbekannter Fehler") + "</div>");
                        }
                    },
                    error: function() {
                        button.prop("disabled", false).text("OpenAI API testen");
                        $("#api-test-results-status").html("<div style=\"color: red;\">Verbindungsfehler beim OpenAI API Test</div>");
                    }
                });
            });
        });
        </script>';
        echo '</div>';
    }
    
    public function display_meta_box($post) {
        if (isset($this->components['meta_box'])) {
            $this->components['meta_box']->display($post);
        } else {
            $this->display_enhanced_meta_box($post);
        }
    }
    
/**
 * Erweiterte Einstellungsseite - KORRIGIERTE VERSION ohne doppelte IDs
 */
private function display_enhanced_settings_page() {
    $settings = get_option('alenseo_settings', []);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- Inline CSS falls externes CSS nicht lädt -->
        <style>
        .alenseo-api-test-section {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .alenseo-api-test-section h2 {
            margin-top: 0;
        }
        .alenseo-test-button {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        #api-test-results {
            margin-top: 15px;
        }
        .notice {
            padding: 12px;
            margin: 15px 0;
            border-left: 4px solid;
            background: #fff;
        }
        .notice.notice-success {
            border-left-color: #46b450;
            background: #ecf7ed;
        }
        .notice.notice-error {
            border-left-color: #dc3232;
            background: #fbeaea;
        }
        .notice.notice-warning {
            border-left-color: #ffb900;
            background: #fff8e5;
        }
        </style>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('alenseo_settings_group');
            do_settings_sections('alenseo_settings_group');
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="alenseo-claude-api-key"><?php _e('Claude API-Schlüssel', 'alenseo'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               name="alenseo_settings[claude_api_key]" 
                               id="alenseo-claude-api-key"
                               value="<?php echo esc_attr($settings['claude_api_key'] ?? ''); ?>" 
                               class="regular-text" 
                               placeholder="sk-ant-api03-..." />
                        <p class="description">
                            <?php _e('Ihr Anthropic Claude API-Schlüssel für erweiterte SEO-Features.', 'alenseo'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="alenseo-claude-model"><?php _e('Claude Standard-Modell', 'alenseo'); ?></label>
                    </th>
                    <td>
                        <select name="alenseo_settings[claude_default_model]" id="alenseo-claude-model">
                            <option value="claude-3-haiku-20240307" <?php selected($settings['claude_default_model'] ?? '', 'claude-3-haiku-20240307'); ?>>
                                Claude 3 Haiku (Schnellstes - 456ms)
                            </option>
                            <option value="claude-3-5-sonnet-20241022" <?php selected($settings['claude_default_model'] ?? 'claude-3-5-sonnet-20241022', 'claude-3-5-sonnet-20241022'); ?>>
                                Claude 3.5 Sonnet (Empfohlen - 853ms)
                            </option>
                            <option value="claude-3-opus-20240229" <?php selected($settings['claude_default_model'] ?? '', 'claude-3-opus-20240229'); ?>>
                                Claude 3 Opus (Höchste Qualität - 2222ms)
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="alenseo-openai-api-key"><?php _e('OpenAI API-Schlüssel', 'alenseo'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               name="alenseo_settings[openai_api_key]" 
                               id="alenseo-openai-api-key"
                               value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" 
                               class="regular-text" 
                               placeholder="sk-proj-..." />
                        <p class="description">
                            <?php _e('Ihr OpenAI API-Schlüssel als Alternative zu Claude.', 'alenseo'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="alenseo-openai-model"><?php _e('OpenAI Modell', 'alenseo'); ?></label>
                    </th>
                    <td>
                        <select name="alenseo_settings[openai_model]" id="alenseo-openai-model">
                            <option value="gpt-3.5-turbo" <?php selected($settings['openai_model'] ?? 'gpt-3.5-turbo', 'gpt-3.5-turbo'); ?>>
                                GPT-3.5 Turbo (Standard)
                            </option>
                            <option value="gpt-4" <?php selected($settings['openai_model'] ?? '', 'gpt-4'); ?>>
                                GPT-4 (Höchste Qualität)
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="alenseo-default-provider"><?php _e('Standard-Anbieter', 'alenseo'); ?></label>
                    </th>
                    <td>
                        <select name="alenseo_settings[default_ai_provider]" id="alenseo-default-provider">
                            <option value="claude" <?php selected($settings['default_ai_provider'] ?? 'claude', 'claude'); ?>>
                                Claude (Anthropic) - Empfohlen
                            </option>
                            <option value="openai" <?php selected($settings['default_ai_provider'] ?? 'claude', 'openai'); ?>>
                                OpenAI (ChatGPT)
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="alenseo-auto-select"><?php _e('Automatische Modell-Auswahl', 'alenseo'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="alenseo_settings[auto_select_model]" 
                                   id="alenseo-auto-select"
                                   <?php checked($settings['auto_select_model'] ?? true); ?> />
                            <?php _e('Automatisch das beste Modell für jede Aufgabe wählen', 'alenseo'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Wenn aktiviert, wählt das Plugin automatisch das optimale Modell basierend auf der Aufgabe (Geschwindigkeit vs. Qualität).', 'alenseo'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <div class="alenseo-api-test-section">
            <h2><?php _e('API-Tests', 'alenseo'); ?></h2>
            <p><?php _e('Testen Sie Ihre API-Verbindungen:', 'alenseo'); ?></p>
            
            <button type="button" id="alenseo-test-claude-btn" class="button button-secondary alenseo-test-button">
                <?php _e('Claude API testen', 'alenseo'); ?>
            </button>
            
            <button type="button" id="alenseo-test-openai-btn" class="button button-secondary alenseo-test-button">
                <?php _e('OpenAI API testen', 'alenseo'); ?>
            </button>
            
            <div id="api-test-results"></div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        console.log('🚀 Alenseo Settings Page loaded');
        console.log('jQuery version:', $.fn.jquery);
        console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'UNDEFINED');
        
        // Claude API Test - EINDEUTIGE IDs
        $('#alenseo-test-claude-btn').on('click', function() {
            console.log('Claude API test clicked');
            var button = $(this);
            var apiKey = $('#alenseo-claude-api-key').val();
            var model = $('#alenseo-claude-model').val();
            
            if (!apiKey) {
                $('#api-test-results').html('<div class="notice notice-error"><p>Bitte geben Sie einen Claude API-Schlüssel ein.</p></div>');
                return;
            }
            
            button.prop('disabled', true).text('Teste Claude API...');
            $('#api-test-results').html('<div class="notice notice-info"><p>Claude API wird getestet...</p></div>');
            
            // Direkte AJAX-Anfrage
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_test_claude_api',
                    api_key: apiKey,
                    model: model,
                    nonce: '<?php echo wp_create_nonce('alenseo_test_api_nonce'); ?>'
                },
                timeout: 30000,
                success: function(response) {
                    console.log('Claude API response:', response);
                    button.prop('disabled', false).text('Claude API testen');
                    
                    if (response.success) {
                        var html = '<div class="notice notice-success"><p><strong>Claude API Test erfolgreich!</strong></p>';
                        if (response.data.details) {
                            html += '<ul>';
                            if (response.data.details.model_count) {
                                html += '<li>Verfügbare Modelle: ' + response.data.details.model_count + '</li>';
                            }
                            if (response.data.details.fastest_model) {
                                html += '<li>Schnellstes Modell: ' + response.data.details.fastest_model + '</li>';
                            }
                            if (response.data.details.recommended_model) {
                                html += '<li>Empfohlenes Modell: ' + response.data.details.recommended_model + '</li>';
                            }
                            html += '</ul>';
                        }
                        html += '</div>';
                        $('#api-test-results').html(html);
                    } else {
                        $('#api-test-results').html('<div class="notice notice-error"><p><strong>Claude API Test fehlgeschlagen:</strong> ' + (response.data ? response.data.message : 'Unbekannter Fehler') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Claude API AJAX error:', error);
                    button.prop('disabled', false).text('Claude API testen');
                    $('#api-test-results').html('<div class="notice notice-error"><p><strong>Verbindungsfehler:</strong> ' + error + '</p></div>');
                }
            });
        });
        
        // OpenAI API Test - EINDEUTIGE IDs  
        $('#alenseo-test-openai-btn').on('click', function() {
            console.log('OpenAI API test clicked');
            var button = $(this);
            var apiKey = $('#alenseo-openai-api-key').val();
            var model = $('#alenseo-openai-model').val();
            
            if (!apiKey) {
                $('#api-test-results').html('<div class="notice notice-error"><p>Bitte geben Sie einen OpenAI API-Schlüssel ein.</p></div>');
                return;
            }
            
            button.prop('disabled', true).text('Teste OpenAI API...');
            $('#api-test-results').html('<div class="notice notice-info"><p>OpenAI API wird getestet...</p></div>');
            
            // Direkte AJAX-Anfrage
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_test_openai_api',
                    api_key: apiKey,
                    model: model,
                    nonce: '<?php echo wp_create_nonce('alenseo_test_api_nonce'); ?>'
                },
                timeout: 30000,
                success: function(response) {
                    console.log('OpenAI API response:', response);
                    button.prop('disabled', false).text('OpenAI API testen');
                    
                    if (response.success) {
                        var html = '<div class="notice notice-success"><p><strong>OpenAI API Test erfolgreich!</strong></p>';
                        if (response.data.details && response.data.details.model) {
                            html += '<p>Modell: ' + response.data.details.model + '</p>';
                        }
                        html += '</div>';
                        $('#api-test-results').html(html);
                    } else {
                        $('#api-test-results').html('<div class="notice notice-error"><p><strong>OpenAI API Test fehlgeschlagen:</strong> ' + (response.data ? response.data.message : 'Unbekannter Fehler') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('OpenAI API AJAX error:', error);
                    button.prop('disabled', false).text('OpenAI API testen');
                    $('#api-test-results').html('<div class="notice notice-error"><p><strong>Verbindungsfehler:</strong> ' + error + '</p></div>');
                }
            });
        });
        
        // Test auf Plugin-Load
        if (typeof Alenseo !== 'undefined') {
            console.log('✅ Alenseo API object found:', Alenseo);
        } else {
            console.log('⚠️ Alenseo API object NOT found - using fallback AJAX');
        }
    });
    </script>
    <?php
}
    
    /**
     * Erweiterte Meta-Box
     */
    private function display_enhanced_meta_box($post) {
        wp_nonce_field('alenseo_meta_box', 'alenseo_meta_box_nonce');
        
        $keyword = get_post_meta($post->ID, '_alenseo_keyword', true);
        $meta_description = get_post_meta($post->ID, '_alenseo_meta_description', true);
        $seo_score = get_post_meta($post->ID, '_alenseo_seo_score', true);
        
        ?>
        <div class="alenseo-meta-box">
            <table class="form-table">
                <tr>
                    <th><label for="alenseo-keyword"><?php _e('Haupt-Keyword', 'alenseo'); ?></label></th>
                    <td>
                        <input type="text" id="alenseo-keyword" name="alenseo_keyword" 
                               value="<?php echo esc_attr($keyword); ?>" class="widefat" />
                        <button type="button" class="button" id="generate-keywords-ai">
                            <?php _e('KI: Keywords generieren', 'alenseo'); ?>
                        </button>
                        <div id="keyword-suggestions" style="margin-top: 10px;"></div>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="alenseo-meta-description"><?php _e('Meta-Description', 'alenseo'); ?></label></th>
                    <td>
                        <textarea id="alenseo-meta-description" name="alenseo_meta_description" 
                                  rows="3" class="widefat"><?php echo esc_textarea($meta_description); ?></textarea>
                        <button type="button" class="button" id="optimize-meta-description-ai">
                            <?php _e('KI: Optimieren', 'alenseo'); ?>
                        </button>
                        <span class="char-count"></span>
                    </td>
                </tr>
                
                <?php if ($seo_score): ?>
                <tr>
                    <th><?php _e('SEO-Score', 'alenseo'); ?></th>
                    <td>
                        <div class="alenseo-score-display">
                            <span class="score-value"><?php echo intval($seo_score); ?>/100</span>
                            <div class="score-bar">
                                <div class="score-progress" style="width: <?php echo intval($seo_score); ?>%"></div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <div class="alenseo-actions">
                <button type="button" class="button button-primary" id="analyze-post">
                    <?php _e('KI: SEO-Analyse durchführen', 'alenseo'); ?>
                </button>
                <button type="button" class="button" id="get-optimization-suggestions">
                    <?php _e('KI: Optimierungsvorschläge', 'alenseo'); ?>
                </button>
            </div>
            
            <div id="alenseo-results"></div>
        </div>
        <?php
    }
    
    /**
     * Basis-Dashboard (Fallback)
     */
    private function display_basic_dashboard() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Alenseo SEO Dashboard', 'alenseo') . '</h1>';
        echo '<p>' . __('Willkommen zum erweiterten Alenseo SEO Plugin mit Multi-Modell KI-Unterstützung!', 'alenseo') . '</p>';
        
        // API-Status anzeigen
        echo '<div id="dashboard-api-status">';
        echo '<h2>' . __('API-Status', 'alenseo') . '</h2>';
        echo '<div id="api-status-content">Lade Status...</div>';
        echo '</div>';
        
        echo '<script>
        jQuery(document).ready(function($) {
            if (typeof Alenseo !== "undefined" && Alenseo.api) {
                Alenseo.api.getApiStatus().then(function(status) {
                    var html = "<div style=\"display: flex; gap: 20px;\">";
                    
                    // Claude Status
                    html += "<div style=\"flex: 1; padding: 15px; border: 1px solid #ddd; border-radius: 5px;\">";
                    html += "<h3>Claude API</h3>";
                    if (status.claude.working) {
                        html += "<span style=\"color: green; font-weight: bold;\">✓ Funktioniert</span>";
                        html += "<p>Verfügbare Modelle: " + (status.claude.model_count || 0) + "</p>";
                    } else {
                        html += "<span style=\"color: red; font-weight: bold;\">✗ Nicht verfügbar</span>";
                    }
                    html += "<p>" + status.claude.message + "</p>";
                    html += "</div>";
                    
                    // OpenAI Status
                    html += "<div style=\"flex: 1; padding: 15px; border: 1px solid #ddd; border-radius: 5px;\">";
                    html += "<h3>OpenAI API</h3>";
                    if (status.openai.working) {
                        html += "<span style=\"color: green; font-weight: bold;\">✓ Funktioniert</span>";
                    } else {
                        html += "<span style=\"color: red; font-weight: bold;\">✗ Nicht verfügbar</span>";
                    }
                    html += "<p>" + status.openai.message + "</p>";
                    html += "</div>";
                    
                    html += "</div>";
                    
                    $("#api-status-content").html(html);
                }).catch(function(error) {
                    $("#api-status-content").html("<p style=\"color: red;\">Fehler beim Laden des API-Status</p>");
                });
            }
        });
        </script>';
        
        echo '</div>';
    }
    
    /**
     * Plugin aktivieren - ERWEITERTE VERSION
     */
    public function activate() {
        // Datenbank-Tabellen erstellen
        if (isset($this->components['database'])) {
            $this->components['database']->create_tables();
        } else {
            $this->create_database_tables_fallback();
        }
        
        // Erweiterte Standardeinstellungen
        $default_settings = [
            'claude_api_key' => '',
            'claude_default_model' => 'claude-3-5-sonnet-20241022',
            'openai_api_key' => '',
            'openai_model' => 'gpt-3.5-turbo',
            'default_ai_provider' => 'claude',
            'auto_select_model' => true,
            'analysis_depth' => 'standard',
            'auto_optimize' => false,
            'version' => $this->version,
            'activated_at' => current_time('mysql')
        ];
        
        if (!get_option('alenseo_settings')) {
            update_option('alenseo_settings', $default_settings);
        }
        
        // Cache-Verzeichnis erstellen
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/alenseo-cache';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
            $htaccess_content = "Options -Indexes\nDeny from all\n";
            file_put_contents($cache_dir . '/.htaccess', $htaccess_content);
        }
        
        // Cache leeren
        $this->delete_transients();
        
        // WordPress-Einstellungen registrieren
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * WordPress-Einstellungen registrieren
     */
    public function register_settings() {
        register_setting('alenseo_settings_group', 'alenseo_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }
    
    /**
     * Einstellungen sanitizen
     */
    public function sanitize_settings($settings) {
        if (!is_array($settings)) {
            $settings = [];
        }
        
        return [
            'claude_api_key' => sanitize_text_field($settings['claude_api_key'] ?? ''),
            'claude_default_model' => sanitize_text_field($settings['claude_default_model'] ?? 'claude-3-5-sonnet-20241022'),
            'openai_api_key' => sanitize_text_field($settings['openai_api_key'] ?? ''),
            'openai_model' => sanitize_text_field($settings['openai_model'] ?? 'gpt-3.5-turbo'),
            'default_ai_provider' => sanitize_text_field($settings['default_ai_provider'] ?? 'claude'),
            'auto_select_model' => (bool)($settings['auto_select_model'] ?? true),
            'analysis_depth' => sanitize_text_field($settings['analysis_depth'] ?? 'standard'),
            'auto_optimize' => (bool)($settings['auto_optimize'] ?? false),
            'last_updated' => current_time('mysql')
        ];
    }
    
    /**
     * Fallback für Datenbanktabellen
     */
    private function create_database_tables_fallback() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Erweiterte Analysen-Tabelle
        $table_name = $wpdb->prefix . 'alenseo_analysis';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL,
            analysis_type varchar(50) NOT NULL,
            analysis_data longtext,
            score int(3),
            provider varchar(20),
            model varchar(50),
            execution_time float,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY analysis_type (analysis_type),
            KEY provider (provider)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // API-Logs-Tabelle
        $log_table = $wpdb->prefix . 'alenseo_api_logs';
        $log_sql = "CREATE TABLE $log_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            provider varchar(20) NOT NULL,
            action varchar(50) NOT NULL,
            tokens_used int(10),
            execution_time float,
            success tinyint(1) DEFAULT 1,
            error_message text,
            user_id bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY provider (provider),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($log_sql);
    }
    
    /**
     * Cache bereinigen - ERWEITERTE VERSION
     */
    public function cleanup_cache() {
        // Transients bereinigen
        $this->delete_transients();
        
        // Cache-Verzeichnis leeren
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/alenseo-cache';
        
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.htaccess') {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Plugin deaktivieren - ERWEITERTE VERSION
     */
    public function deactivate() {
        // Cron-Jobs entfernen
        wp_clear_scheduled_hook('alenseo_cleanup_cache');
        
        // Cache leeren
        $this->delete_transients();
    }
    
    /**
     * Plugin deinstallieren - ERWEITERTE VERSION
     */
    public static function uninstall() {
        // Einstellungen löschen
        delete_option('alenseo_settings');
        delete_option('alenseo_version');
        
        // Datenbank-Tabellen löschen
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}alenseo_analysis");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}alenseo_keywords");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}alenseo_api_logs");
        
        // Cache-Verzeichnis löschen
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/alenseo-cache';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($cache_dir);
        }
    }
    
    /**
     * Plugin-Aktionslinks hinzufügen - ERWEITERTE VERSION
     */
    public function add_action_links($links) {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=alenseo-dashboard') . '">' . __('Dashboard', 'alenseo') . '</a>',
            '<a href="' . admin_url('admin.php?page=alenseo-settings') . '">' . __('Einstellungen', 'alenseo') . '</a>',
            '<a href="' . admin_url('admin.php?page=alenseo-api-status') . '">' . __('API-Status', 'alenseo') . '</a>'
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
            $links[] = '<a href="https://github.com/your-repo" target="_blank">' . __('GitHub', 'alenseo') . '</a>';
        }
        return $links;
    }
    
    /**
     * Transients löschen
     */
    private function delete_transients() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_alenseo_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_alenseo_%'");
    }
    
    /**
     * Textdomain laden
     */
    private function load_textdomain() {
        load_plugin_textdomain('alenseo', false, dirname(ALENSEO_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Logging-Funktionen
     */
    private function log_error($message) {
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log('[Alenseo Error] ' . $message);
        }
    }
    
    private function log_debug($message) {
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log('[Alenseo Debug] ' . $message);
        }
    }
    
    /**
     * PHP-Version-Hinweis
     */
    public function php_version_notice() {
        $message = sprintf(
            __('Alenseo SEO Minimal benötigt PHP %s oder höher. Du verwendest PHP %s.', 'alenseo'),
            ALENSEO_MIN_PHP_VERSION,
            PHP_VERSION
        );
        echo '<div class="error"><p>' . $message . '</p></div>';
    }
    
    /**
     * WordPress-Version-Hinweis
     */
    public function wp_version_notice() {
        $message = sprintf(
            __('Alenseo SEO Minimal benötigt WordPress %s oder höher. Du verwendest WordPress %s.', 'alenseo'),
            ALENSEO_MIN_WP_VERSION,
            $GLOBALS['wp_version']
        );
        echo '<div class="error"><p>' . $message . '</p></div>';
    }
    
    /**
     * Doppeltes Plugin-Hinweis
     */
    public function duplicate_plugin_notice() {
        $message = __('Eine andere Version von Alenseo SEO ist bereits aktiv. Bitte deaktiviere die andere Version.', 'alenseo');
        echo '<div class="error"><p>' . $message . '</p></div>';
    }
    
    /**
     * Komponente abrufen
     */
    public function get_component($name) {
        return isset($this->components[$name]) ? $this->components[$name] : null;
    }
    
    /**
     * Utility-Funktionen für externe Verwendung
     */
    public function get_api($provider = null) {
        $settings = get_option('alenseo_settings', []);
        $provider = $provider ?: ($settings['default_ai_provider'] ?? 'claude');
        
        if ($provider === 'claude' && isset($this->components['claude_api'])) {
            return $this->components['claude_api'];
        } elseif ($provider === 'openai' && isset($this->components['chatgpt_api'])) {
            return $this->components['chatgpt_api'];
        }
        
        return null;
    }

    /**
     * AJAX Handler für Page Optimizer
     */
    public function ajax_analyze_page() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unzureichende Berechtigungen.']);
            return;
        }
        
        $page_url = sanitize_text_field($_POST['page_url']);
        
        if (empty($page_url)) {
            wp_send_json_error(['message' => 'Keine URL oder Post-ID angegeben.']);
            return;
        }
        
        try {
            // Check if it's a post ID or URL
            if (is_numeric($page_url)) {
                $post_id = intval($page_url);
                $post = get_post($post_id);
                if (!$post) {
                    wp_send_json_error(['message' => 'Post mit dieser ID nicht gefunden.']);
                    return;
                }
                $url = get_permalink($post_id);
                $content = $post->post_content;
                $title = $post->post_title;
            } else {
                // Extract post ID from URL
                $post_id = url_to_postid($page_url);
                if ($post_id) {
                    $post = get_post($post_id);
                    $content = $post->post_content;
                    $title = $post->post_title;
                } else {
                    // External URL - try to fetch content
                    $response = wp_remote_get($page_url, ['timeout' => 30]);
                    if (is_wp_error($response)) {
                        wp_send_json_error(['message' => 'Fehler beim Abrufen der URL: ' . $response->get_error_message()]);
                        return;
                    }
                    $content = wp_remote_retrieve_body($response);
                    $title = $this->extract_title_from_html($content);
                    $post_id = 0;
                }
                $url = $page_url;
            }
            
            // Get API provider
            $api = $this->get_api();
            if (!$api || !$api->is_api_configured()) {
                wp_send_json_error(['message' => 'Keine API konfiguriert. Bitte konfigurieren Sie Ihre API-Schlüssel in den Einstellungen.']);
                return;
            }
            
            // Analyze content
            $analysis_result = $api->get_optimization_suggestions($content);
            if (is_wp_error($analysis_result)) {
                wp_send_json_error(['message' => 'API-Fehler: ' . $analysis_result->get_error_message()]);
                return;
            }
            
            // Extract content URLs and images
            $content_urls = $this->extract_content_urls($content, $url);
            $images = $this->extract_images($content, $url);
            
            // Calculate SEO score
            $seo_score = $this->calculate_seo_score($content, $title);
            
            $response_data = [
                'seo_score' => $seo_score,
                'analysis' => $analysis_result,
                'content_urls' => $content_urls,
                'images' => $images,
                'post_id' => $post_id,
                'url' => $url,
                'title' => $title
            ];
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Unerwarteter Fehler: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX-Handler zum Testen der Claude API
     */
    public function ajax_test_claude_api() {
        // Delegate to the function in the settings AJAX file
        if (function_exists('Alenseo\\alenseo_test_claude_api_settings')) {
            \Alenseo\alenseo_test_claude_api_settings();
        } else {
            // Fallback implementation
            if (!wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
                wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
                return;
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unzureichende Berechtigungen.']);
                return;
            }
            
            if (empty($_POST['api_key'])) {
                wp_send_json_error(['message' => 'Kein API-Schlüssel angegeben.']);
                return;
            }
            
            try {
                $api_key = sanitize_text_field($_POST['api_key']);
                $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'claude-3-5-sonnet-20241022';
                
                if (!class_exists('Alenseo\\Alenseo_Claude_API')) {
                    require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
                }
                
                $claude_api = new \Alenseo\Alenseo_Claude_API($api_key, $model);
                $test_result = $claude_api->test_api_key();
                
                if (is_array($test_result) && isset($test_result['success']) && $test_result['success']) {
                    wp_send_json_success([
                        'message' => 'Claude API erfolgreich getestet!',
                        'details' => $test_result
                    ]);
                } else {
                    $error_message = is_array($test_result) && isset($test_result['message']) 
                        ? $test_result['message'] 
                        : 'Unbekannter Fehler bei der API-Verbindung.';
                    wp_send_json_error(['message' => $error_message]);
                }
            } catch (\Exception $e) {
                wp_send_json_error(['message' => 'Fehler beim API-Test: ' . $e->getMessage()]);
            }
        }
    }
    
    /**
     * AJAX-Handler zum Testen der OpenAI API
     */
    public function ajax_test_openai_api() {
        // Delegate to the function in the settings AJAX file
        if (function_exists('Alenseo\\alenseo_test_openai_api_settings')) {
            \Alenseo\alenseo_test_openai_api_settings();
        } else {
            // Fallback implementation
            if (!wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
                wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
                return;
            }
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unzureichende Berechtigungen.']);
                return;
            }
            
            if (empty($_POST['api_key'])) {
                wp_send_json_error(['message' => 'Kein API-Schlüssel angegeben.']);
                return;
            }
            
            try {
                $api_key = sanitize_text_field($_POST['api_key']);
                $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gpt-3.5-turbo';
                
                if (!class_exists('Alenseo\\Alenseo_ChatGPT_API')) {
                    require_once ALENSEO_MINIMAL_DIR . 'includes/class-chatgpt-api.php';
                }
                
                $openai_api = new \Alenseo\Alenseo_ChatGPT_API($api_key, $model);
                $test_result = $openai_api->test_api_key();
                
                if (is_array($test_result) && isset($test_result['success']) && $test_result['success']) {
                    wp_send_json_success([
                        'message' => 'OpenAI API erfolgreich getestet!',
                        'details' => $test_result
                    ]);
                } else {
                    $error_message = is_array($test_result) && isset($test_result['message']) 
                        ? $test_result['message'] 
                        : 'Unbekannter Fehler bei der OpenAI API-Verbindung.';
                    wp_send_json_error(['message' => $error_message]);
                }
            } catch (\Exception $e) {
                wp_send_json_error(['message' => 'Fehler beim OpenAI API-Test: ' . $e->getMessage()]);
            }
        }
    }
    
    /**
     * AJAX-Handler zum Laden von Posts für Bulk Optimizer
     */
    public function ajax_load_posts() {
        if (!wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unzureichende Berechtigungen.']);
            return;
        }
        
        try {
            $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
            $per_page = isset($_POST['per_page']) ? max(1, min(50, intval($_POST['per_page']))) : 10;
            $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'any';
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            
            $args = [
                'post_type' => $post_type === 'any' ? ['post', 'page'] : [$post_type],
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'date',
                'order' => 'DESC'
            ];
            
            if (!empty($search)) {
                $args['s'] = $search;
            }
            
            $query = new WP_Query($args);
            $posts_data = [];
            
            foreach ($query->posts as $post) {
                $seo_score = get_post_meta($post->ID, '_alenseo_seo_score', true);
                $focus_keyword = get_post_meta($post->ID, '_alenseo_focus_keyword', true);
                $last_analyzed = get_post_meta($post->ID, '_alenseo_last_analyzed', true);
                
                $posts_data[] = [
                    'ID' => $post->ID,
                    'title' => $post->post_title,
                    'post_type' => $post->post_type,
                    'permalink' => get_permalink($post->ID),
                    'edit_link' => get_edit_post_link($post->ID),
                    'seo_score' => $seo_score ? intval($seo_score) : 0,
                    'focus_keyword' => $focus_keyword ?: '',
                    'last_analyzed' => $last_analyzed ? date('d.m.Y H:i', strtotime($last_analyzed)) : 'Nie',
                    'status' => $this->get_seo_status($seo_score),
                    'word_count' => str_word_count(strip_tags($post->post_content))
                ];
            }
            
            wp_send_json_success([
                'posts' => $posts_data,
                'total_posts' => $query->found_posts,
                'total_pages' => $query->max_num_pages,
                'current_page' => $page
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Fehler beim Laden der Posts: ' . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX-Handler für Bulk-Analyse
     */
    public function ajax_bulk_analyze() {
        if (!wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unzureichende Berechtigungen.']);
            return;
        }
        
        try {
            $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
            if (empty($post_ids)) {
                wp_send_json_error(['message' => 'Keine Posts ausgewählt.']);
                return;
            }
            
            $results = [];
            foreach ($post_ids as $post_id) {
                $post = get_post($post_id);
                if (!$post) {
                    continue;
                }
                
                // Simple SEO analysis
                $score = $this->calculate_seo_score($post->post_title, $post->post_content);
                update_post_meta($post_id, '_alenseo_seo_score', $score);
                update_post_meta($post_id, '_alenseo_last_analyzed', current_time('mysql'));
                
                $results[] = [
                    'post_id' => $post_id,
                    'title' => $post->post_title,
                    'score' => $score,
                    'status' => $this->get_seo_status($score)
                ];
            }
            
            wp_send_json_success([
                'message' => sprintf('Analyse für %d Posts abgeschlossen.', count($results)),
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Fehler bei der Bulk-Analyse: ' . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX-Handler für Dashboard-Daten
     */
    public function ajax_get_dashboard_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unzureichende Berechtigungen.']);
            return;
        }
        
        try {
            // Post statistics
            $total_posts = wp_count_posts(['post', 'page']);
            $analyzed_posts = $this->get_analyzed_posts_count();
            $avg_seo_score = $this->get_average_seo_score();
            
            wp_send_json_success([
                'stats' => [
                    'total_posts' => $total_posts->publish ?? 0,
                    'analyzed_posts' => $analyzed_posts,
                    'avg_seo_score' => $avg_seo_score,
                    'optimization_rate' => $total_posts->publish > 0 ? round(($analyzed_posts / $total_posts->publish) * 100, 1) : 0
                ],
                'recent_activity' => $this->get_recent_activity()
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Fehler beim Laden der Dashboard-Daten: ' . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX-Handler für API-Status
     */
    public function ajax_get_api_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'alenseo_ajax_nonce')) {
            wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unzureichende Berechtigungen.']);
            return;
        }
        
        try {
            $settings = get_option('alenseo_settings', []);
            $status = [
                'claude' => [
                    'configured' => !empty($settings['claude_api_key']),
                    'status' => 'unknown'
                ],
                'openai' => [
                    'configured' => !empty($settings['openai_api_key']),
                    'status' => 'unknown'
                ]
            ];
            
            wp_send_json_success($status);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Fehler beim Prüfen des API-Status: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Helper: SEO-Status basierend auf Score bestimmen
     */
    private function get_seo_status($score) {
        if (empty($score) || $score < 50) {
            return 'poor';
        } elseif ($score < 80) {
            return 'needs_improvement';
        } else {
            return 'good';
        }
    }
    
    /**
     * Helper: Anzahl analysierter Posts
     */
    private function get_analyzed_posts_count() {
        global $wpdb;
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_alenseo_seo_score' 
             AND meta_value != '' 
             AND meta_value IS NOT NULL"
        );
        return intval($count);
    }
    
    /**
     * Helper: Durchschnittlicher SEO-Score
     */
    private function get_average_seo_score() {
        global $wpdb;
        $avg = $wpdb->get_var(
            "SELECT AVG(CAST(meta_value AS DECIMAL(5,2))) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_alenseo_seo_score' 
             AND meta_value != '' 
             AND meta_value IS NOT NULL 
             AND meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'"
        );
        return $avg ? round(floatval($avg), 1) : 0;
    }
    
    /**
     * Helper: Letzte Aktivitäten
     */
    private function get_recent_activity() {
        global $wpdb;
        $activities = $wpdb->get_results(
            "SELECT p.ID, p.post_title, pm.meta_value as score, pm2.meta_value as analyzed_date
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_alenseo_seo_score'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_alenseo_last_analyzed'
             WHERE p.post_status = 'publish' 
             AND pm2.meta_value IS NOT NULL
             ORDER BY pm2.meta_value DESC
             LIMIT 10"
        );
        
        $recent = [];
        foreach ($activities as $activity) {
            $recent[] = [
                'post_id' => $activity->ID,
                'title' => $activity->post_title,
                'score' => intval($activity->score),
                'date' => $activity->analyzed_date ? date('d.m.Y H:i', strtotime($activity->analyzed_date)) : 'Unbekannt'
            ];
        }
        
        return $recent;
    }

    // ...existing code...
}