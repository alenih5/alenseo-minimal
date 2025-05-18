<?php
/**
 * Admin-Funktionalitäten für Alenseo SEO
 *
 * @link       https://imponi.ch
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
 * Die Admin-Klasse für das Alenseo SEO Plugin
 */
class Alenseo_Minimal_Admin {

    /**
     * Plugin-Einstellungen
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Die Plugin-Einstellungen.
     */
    private $settings;

    /**
     * Analysis-Klasse
     *
     * @since    1.0.0
     * @access   private
     * @var      object    $analysis    Instance der Analysis-Klasse.
     */
    private $analysis;

    /**
     * Claude API-Klasse
     *
     * @since    1.0.0
     * @access   private
     * @var      object    $claude_api    Instance der Claude API-Klasse.
     */
    private $claude_api;

    /**
     * Konstruktor
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Debug-Log
        error_log("Alenseo Admin: Konstruktor aufgerufen");
        
        // Einstellungen laden
        $this->settings = get_option('alenseo_settings', array());
        
        // Admin-Hooks registrieren
        $this->init_hooks();
        
        error_log("Alenseo Admin: Konstruktor abgeschlossen");
    }

    /**
     * Analysis-Klasse initialisieren
     */
    private function init_analysis() {
        if (!$this->analysis) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-minimal-analysis.php';
            $this->analysis = new Alenseo_Minimal_Analysis();
        }
        
        return $this->analysis;
    }

    /**
     * Claude API-Klasse initialisieren
     */
    private function init_claude_api() {
        if (!$this->claude_api && !empty($this->settings['claude_api_key'])) {
            require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
            $this->claude_api = new Alenseo_Claude_API();
        }
        
        return $this->claude_api;
    }

    /**
     * Admin-Hooks registrieren
     *
     * @since    1.0.0
     */
    private function init_hooks() {
        error_log("Alenseo Admin: init_hooks aufgerufen");
        
        // Admin-Menü hinzufügen
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin-Assets laden
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Meta-Box-Hooks hinzufügen
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        
        // API-Status-Benachrichtigung
        add_action('admin_notices', array($this, 'display_api_status_notice'));
        
        error_log("Alenseo Admin: init_hooks abgeschlossen");
    }

    /**
     * Admin-Menü hinzufügen
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        error_log("Alenseo Admin: add_admin_menu aufgerufen");
        
        // Hauptmenü
        add_menu_page(
            __('Alenseo SEO', 'alenseo'),
            __('Alenseo SEO', 'alenseo'),
            'manage_options',
            'alenseo-minimal',
            array($this, 'render_dashboard_page'),
            'dashicons-chart-line',
            80
        );
        
        // Untermenü: Dashboard
        add_submenu_page(
            'alenseo-minimal',
            __('Dashboard', 'alenseo'),
            __('Dashboard', 'alenseo'),
            'manage_options',
            'alenseo-minimal',
            array($this, 'render_dashboard_page')
        );
        
        // Untermenü: Einstellungen
        add_submenu_page(
            'alenseo-minimal',
            __('Einstellungen', 'alenseo'),
            __('Einstellungen', 'alenseo'),
            'manage_options',
            'alenseo-minimal-settings',
            array($this, 'render_settings_page')
        );
        
        error_log("Alenseo Admin: add_admin_menu erfolgreich");
    }

    /**
     * Admin-Assets laden
     *
     * @since    1.0.0
     * @param    string    $hook    Hook-Name der aktuellen Admin-Seite.
     */
    public function enqueue_admin_assets($hook) {
        // Debug-Log
        error_log("Alenseo Admin: enqueue_admin_assets für Hook '$hook'");
        
        // Assets nur auf Plugin-Seiten oder bei Meta-Box-Ansicht laden
        $load_assets = false;
        
        // Fall 1: Plugin-Admin-Seiten
        if (strpos($hook, 'alenseo-minimal') !== false) {
            $load_assets = true;
        }
        
        // Fall 2: Post-Edit-Seite mit Meta-Box
        $post_types = isset($this->settings['post_types']) ? $this->settings['post_types'] : array('post', 'page');
        $current_screen = get_current_screen();
        
        if ($current_screen && in_array($current_screen->post_type, $post_types) && 
            ($hook === 'post.php' || $hook === 'post-new.php')) {
            $load_assets = true;
        }
        
        // Assets laden, wenn nötig
        if ($load_assets) {
            // CSS für Keyword Generator
            $css_path = ALENSEO_MINIMAL_URL . 'assets/css/enhanced-keyword-generator.css';
            wp_enqueue_style(
                'alenseo-keyword-generator',
                $css_path,
                array(),
                ALENSEO_MINIMAL_VERSION
            );
            
            // JavaScript für Keyword Generator
            $js_path = ALENSEO_MINIMAL_URL . 'assets/js/enhanced-keyword-generator.js';
            wp_enqueue_script(
                'alenseo-keyword-generator',
                $js_path,
                array('jquery'),
                ALENSEO_MINIMAL_VERSION,
                true
            );
            
            // AJAX-Daten für JavaScript
            wp_localize_script('alenseo-keyword-generator', 'alenseoData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('alenseo_ajax_nonce')
            ));
            
            error_log("Alenseo Admin: Assets geladen");
        }
    }

    /**
     * Dashboard-Seite rendern
     *
     * @since    1.0.0
     */
    public function render_dashboard_page() {
        error_log("Alenseo Admin: render_dashboard_page aufgerufen");
        
        // Analysis-Klasse initialisieren
        $this->init_analysis();
        
        // Post-Typen für die Analyse
        $post_types = isset($this->settings['post_types']) ? $this->settings['post_types'] : array('post', 'page');
        
        // WP_Query für veröffentlichte Beiträge
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $query = new WP_Query($args);
        
        // Statistiken berechnen
        $stats = array(
            'total' => 0,
            'optimized' => 0,
            'partially_optimized' => 0,
            'needs_optimization' => 0,
            'avg_score' => 0,
            'total_score' => 0
        );
        
        $posts_data = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // SEO-Daten abrufen
                $seo_data = $this->analysis->get_seo_data($post_id);
                
                // Statistiken aktualisieren
                $stats['total']++;
                $stats['total_score'] += $seo_data['seo_score'];
                
                // Status basierend auf Score
                if ($seo_data['seo_score'] >= 80) {
                    $stats['optimized']++;
                    $status = 'optimized';
                } elseif ($seo_data['seo_score'] >= 50) {
                    $stats['partially_optimized']++;
                    $status = 'partially_optimized';
                } else {
                    $stats['needs_optimization']++;
                    $status = 'needs_optimization';
                }
                
                $posts_data[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'score' => $seo_data['seo_score'],
                    'status' => $status,
                    'keyword' => $seo_data['focus_keyword'],
                    'edit_url' => get_edit_post_link($post_id),
                    'view_url' => get_permalink($post_id)
                );
            }
            
            // Durchschnittlichen Score berechnen
            if ($stats['total'] > 0) {
                $stats['avg_score'] = round($stats['total_score'] / $stats['total']);
            }
        }
        
        wp_reset_postdata();
        
        // Dashboard-Seite HTML
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Alenseo SEO Dashboard', 'alenseo'); ?>
            </h1>
            
            <?php
            // API-Status anzeigen
            $api_key = isset($this->settings['claude_api_key']) ? $this->settings['claude_api_key'] : '';
            if (empty($api_key)) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('Claude API ist nicht konfiguriert. Um erweiterte Funktionen wie intelligente Keyword-Generierung und Optimierungsvorschläge zu nutzen, bitte API-Schlüssel in den', 'alenseo'); ?>
                        <a href="<?php echo admin_url('admin.php?page=alenseo-minimal-settings'); ?>"><?php _e('Einstellungen', 'alenseo'); ?></a>
                        <?php _e('konfigurieren.', 'alenseo'); ?>
                    </p>
                </div>
                <?php
            }
            ?>
            
            <div class="alenseo-dashboard-stats" style="display: flex; margin-top: 20px; margin-bottom: 20px;">
                <div class="alenseo-stat-box" style="flex: 1; background: #fff; padding: 20px; margin-right: 20px; border: 1px solid #ddd; border-radius: 4px; text-align: center;">
                    <h3><?php _e('SEO-Gesamtscore', 'alenseo'); ?></h3>
                    <div style="font-size: 36px; font-weight: bold; margin: 10px 0; color: #0073aa;">
                        <?php echo esc_html($stats['avg_score']); ?>
                    </div>
                    <p><?php _e('Durchschnittlicher Score aller Seiten', 'alenseo'); ?></p>
                </div>
                
                <div class="alenseo-stat-box" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <h3><?php _e('Seitenstatus', 'alenseo'); ?></h3>
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        <li style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee;">
                            <span style="color: #46b450;"><?php _e('Gut optimiert', 'alenseo'); ?></span>
                            <span style="font-weight: bold;"><?php echo esc_html($stats['optimized']); ?></span>
                        </li>
                        <li style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee;">
                            <span style="color: #ffb900;"><?php _e('Teilweise optimiert', 'alenseo'); ?></span>
                            <span style="font-weight: bold;"><?php echo esc_html($stats['partially_optimized']); ?></span>
                        </li>
                        <li style="display: flex; justify-content: space-between; padding: 5px 0;">
                            <span style="color: #dc3232;"><?php _e('Optimierung nötig', 'alenseo'); ?></span>
                            <span style="font-weight: bold;"><?php echo esc_html($stats['needs_optimization']); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="alenseo-recent-posts">
                <h2><?php _e('Neueste Beiträge', 'alenseo'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Titel', 'alenseo'); ?></th>
                            <th width="200"><?php _e('Fokus-Keyword', 'alenseo'); ?></th>
                            <th width="100"><?php _e('SEO Score', 'alenseo'); ?></th>
                            <th width="100"><?php _e('Status', 'alenseo'); ?></th>
                            <th width="150"><?php _e('Aktionen', 'alenseo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts_data)) : ?>
                            <tr>
                                <td colspan="5"><?php _e('Keine Beiträge gefunden.', 'alenseo'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($posts_data as $post) : ?>
                                <tr>
                                    <td>
                                        <strong><a href="<?php echo esc_url($post['edit_url']); ?>"><?php echo esc_html($post['title']); ?></a></strong>
                                    </td>
                                    <td>
                                        <?php echo esc_html($post['keyword'] ?: '—'); ?>
                                    </td>
                                    <td>
                                        <div style="display: inline-block; width: 40px; height: 40px; line-height: 40px; text-align: center; border-radius: 50%; background-color: <?php echo ($post['score'] >= 80) ? '#46b450' : (($post['score'] >= 50) ? '#ffb900' : '#dc3232'); ?>; color: white; font-weight: bold;">
                                            <?php echo esc_html($post['score']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($post['status'] === 'optimized') : ?>
                                            <span style="color: #46b450;"><?php _e('Gut optimiert', 'alenseo'); ?></span>
                                        <?php elseif ($post['status'] === 'partially_optimized') : ?>
                                            <span style="color: #ffb900;"><?php _e('Teilweise optimiert', 'alenseo'); ?></span>
                                        <?php else : ?>
                                            <span style="color: #dc3232;"><?php _e('Optimierung nötig', 'alenseo'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($post['edit_url']); ?>" class="button button-small"><?php _e('Bearbeiten', 'alenseo'); ?></a>
                                        <a href="<?php echo esc_url($post['view_url']); ?>" class="button button-small" target="_blank"><?php _e('Ansehen', 'alenseo'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alenseo-dashboard-actions" style="margin-top: 20px;">
                <a href="<?php echo admin_url('edit.php'); ?>" class="button"><?php _e('Alle Beiträge', 'alenseo'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=alenseo-minimal-settings'); ?>" class="button"><?php _e('Einstellungen', 'alenseo'); ?></a>
            </div>
        </div>
        <?php
        
        error_log("Alenseo Admin: render_dashboard_page erfolgreich");
    }

    /**
     * Einstellungsseite rendern
     *
     * @since    1.0.0
     */
    public function render_settings_page() {
        error_log("Alenseo Admin: render_settings_page aufgerufen");
        
        // Template für Einstellungsseite einbinden
        include_once ALENSEO_MINIMAL_DIR . 'templates/settings-page.php';
        
        error_log("Alenseo Admin: render_settings_page erfolgreich");
    }

    /**
     * Meta-Box für Beiträge/Seiten hinzufügen
     */
    public function add_meta_boxes() {
        // Analysis-Klasse initialisieren
        $this->init_analysis();
        
        // Post-Typen für die Meta-Box
        $post_types = isset($this->settings['post_types']) ? $this->settings['post_types'] : array('post', 'page');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'alenseo_seo_meta_box',
                __('Alenseo SEO', 'alenseo'),
                array($this, 'render_meta_box'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Meta-Box rendern
     */
    public function render_meta_box($post) {
        // Nonce für Sicherheit
        wp_nonce_field('alenseo_meta_box', 'alenseo_meta_box_nonce');
        
        // Analysis-Klasse initialisieren
        $this->init_analysis();
        
        // SEO-Daten abrufen
        $seo_data = $this->analysis->get_seo_data($post->ID);
        
        // Template für Meta-Box einbinden
        include_once ALENSEO_MINIMAL_DIR . 'templates/alenseo-meta-box.php';
    }

    /**
     * Meta-Box-Daten speichern
     */
    public function save_meta_box_data($post_id) {
        // Nonce prüfen
        if (!isset($_POST['alenseo_meta_box_nonce']) || !wp_verify_nonce($_POST['alenseo_meta_box_nonce'], 'alenseo_meta_box')) {
            return;
        }
        
        // Auto-Save überspringen
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Berechtigung prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Fokus-Keyword speichern
        if (isset($_POST['alenseo_focus_keyword'])) {
            $keyword = sanitize_text_field($_POST['alenseo_focus_keyword']);
            update_post_meta($post_id, '_alenseo_keyword', $keyword);
        }
    }

    /**
     * API-Status-Benachrichtigung anzeigen
     */
    public function display_api_status_notice() {
        // Nur auf Plugin-Seiten anzeigen
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'alenseo') === false) {
            return;
        }
        
        // Claude API-Status prüfen
        $settings = get_option('alenseo_settings', array());
        $api_key = isset($settings['claude_api_key']) ? $settings['claude_api_key'] : '';
        $api_files_exist = file_exists(ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php');
        
        if ($api_files_exist && !empty($api_key)) {
            // API ist bereits konfiguriert
            return;
        }
        
        // Datei-Status anzeigen (nur in der Entwicklung)
        if (WP_DEBUG) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . __('Alenseo SEO Claude API Status:', 'alenseo') . '</strong>';
            echo '<ul>';
            echo '<li>' . __('API Schlüssel konfiguriert:', 'alenseo') . ' ' . (!empty($api_key) ? '✓' : '✗') . '</li>';
            echo '<li>' . __('API-Dateien installiert:', 'alenseo') . ' ' . ($api_files_exist ? '✓' : '✗') . '</li>';
            echo '<li>' . __('Templates-Pfad:', 'alenseo') . ' ' . ALENSEO_MINIMAL_DIR . 'templates/</li>';
            echo '</ul>';
            echo '</p></div>';
        }
    }
}