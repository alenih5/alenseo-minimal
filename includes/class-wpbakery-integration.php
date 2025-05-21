<?php
/**
 * WPBakery Integration für Alenseo SEO
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
 * Die WPBakery-Integrationsklasse für das Alenseo SEO Plugin
 */
class Alenseo_WPBakery_Integration {

    /**
     * Plugin-Einstellungen
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $settings    Die Plugin-Einstellungen.
     */
    private $settings;

    /**
     * Konstruktor
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Einstellungen laden
        $this->settings = get_option('alenseo_settings', array());
        
        // Hooks registrieren
        $this->init_hooks();
    }

    /**
     * Hooks registrieren
     */
    private function init_hooks() {
        // Nur initialisieren, wenn WPBakery aktiv ist
        if (!$this->is_wpbakery_active()) {
            return;
        }
        
        // Meta-Daten-Synchronisierung
        add_action('save_post', array($this, 'sync_wpbakery_meta'), 10, 2);
        
        // WPBakery-SEO-Meta-Box erweitern
        add_action('vc_backend_editor_render', array($this, 'extend_wpbakery_seo_metabox'));
        add_action('vc_frontend_editor_render', array($this, 'extend_wpbakery_seo_metabox'));
        
        // WPBakery-Assets laden
        add_action('admin_enqueue_scripts', array($this, 'enqueue_wpbakery_assets'));
        
        // AJAX-Hooks für WPBakery-Integration
        add_action('wp_ajax_alenseo_wpbakery_generate_keywords', array($this, 'ajax_generate_keywords'));
        add_action('wp_ajax_alenseo_wpbakery_optimize_meta', array($this, 'ajax_optimize_meta'));
    }

    /**
     * Prüfen, ob WPBakery aktiv ist
     */
    private function is_wpbakery_active() {
        return defined('WPB_VC_VERSION') || class_exists('WPBakeryVisualComposer');
    }

    /**
     * Meta-Daten zwischen WPBakery und Alenseo synchronisieren
     */
    public function sync_wpbakery_meta($post_id, $post) {
        // AutoSave überspringen
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Berechtigungen prüfen
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Prüfen, ob es sich um einen relevanten Post-Typ handelt
        $post_types = isset($this->settings['post_types']) ? $this->settings['post_types'] : array('post', 'page');
        if (!in_array($post->post_type, $post_types)) {
            return;
        }
        
        // 1. WPBakery zu Alenseo
        
        // Keyword
        $wpbakery_keyword = get_post_meta($post_id, 'vc_seo_keyword', true);
        if (!empty($wpbakery_keyword) && empty(get_post_meta($post_id, '_alenseo_keyword', true))) {
            update_post_meta($post_id, '_alenseo_keyword', $wpbakery_keyword);
        }
        
        // Meta Description
        $wpbakery_description = get_post_meta($post_id, 'vc_description', true);
        if (!empty($wpbakery_description) && empty(get_post_meta($post_id, '_alenseo_meta_description', true))) {
            update_post_meta($post_id, '_alenseo_meta_description', $wpbakery_description);
        }
        
        // 2. Alenseo zu WPBakery
        
        // Keyword
        $alenseo_keyword = get_post_meta($post_id, '_alenseo_keyword', true);
        if (!empty($alenseo_keyword) && empty(get_post_meta($post_id, 'vc_seo_keyword', true))) {
            update_post_meta($post_id, 'vc_seo_keyword', $alenseo_keyword);
        }
        
        // Meta Description
        $alenseo_description = get_post_meta($post_id, '_alenseo_meta_description', true);
        if (!empty($alenseo_description) && empty(get_post_meta($post_id, 'vc_description', true))) {
            update_post_meta($post_id, 'vc_description', $alenseo_description);
        }
    }

    /**
     * WPBakery SEO Meta-Box erweitern
     */
    public function extend_wpbakery_seo_metabox() {
        // Diese Funktion wird aufgerufen, wenn der WPBakery-Editor geladen wird
        add_action('admin_footer', array($this, 'add_wpbakery_seo_buttons'));
    }

    /**
     * Buttons zur WPBakery SEO Meta-Box hinzufügen
     */
    public function add_wpbakery_seo_buttons() {
        // Claude API verfügbar?
        $api_key = isset($this->settings['claude_api_key']) ? $this->settings['claude_api_key'] : '';
        if (empty($api_key)) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Nur fortfahren, wenn WPBakery SEO Meta-Box existiert
            if ($('.vc_ui-panel-window .vc_ui-panel-content .vc_column[data-vc-shortcode-param-name="seo_options"]').length === 0) {
                return;
            }
            
            // Buttons zur WPBakery SEO Meta-Box hinzufügen
            var $seoOptions = $('.vc_ui-panel-window .vc_ui-panel-content .vc_column[data-vc-shortcode-param-name="seo_options"]');
            var $seoKeywordField = $seoOptions.find('input[name="vc_seo_keyword"]');
            var $seoDescriptionField = $seoOptions.find('textarea[name="vc_description"]');
            
            if ($seoKeywordField.length > 0) {
                // Button-Container erstellen
                var $buttonContainer = $('<div class="alenseo-wpbakery-buttons" style="margin-top: 10px;"></div>');
                
                // Keyword-Button hinzufügen
                var $keywordButton = $('<button type="button" class="button alenseo-wpbakery-generate-keyword" style="margin-right: 10px;"><span class="dashicons dashicons-awards" style="vertical-align: middle; margin-right: 5px;"></span> Keywords generieren</button>');
                $buttonContainer.append($keywordButton);
                
                // Optimierungsbutton hinzufügen
                var $optimizeButton = $('<button type="button" class="button alenseo-wpbakery-optimize-meta"><span class="dashicons dashicons-superhero" style="vertical-align: middle; margin-right: 5px;"></span> Mit Claude optimieren</button>');
                $buttonContainer.append($optimizeButton);
                
                // Status-Container hinzufügen
                var $statusContainer = $('<div class="alenseo-wpbakery-status" style="margin-top: 10px; display: none;"></div>');
                $buttonContainer.append($statusContainer);
                
                // Buttons nach dem Keyword-Feld einfügen
                $seoKeywordField.after($buttonContainer);
                
                // Keyword-Button-Klick
                $keywordButton.on('click', function(e) {
                    e.preventDefault();
                    
                    // Post-ID abrufen
                    var postId = $('#post_ID').val();
                    
                    if (!postId) {
                        alert('Post-ID konnte nicht gefunden werden.');
                        return;
                    }
                    
                    // Button deaktivieren und Statusanzeige
                    $keywordButton.prop('disabled', true);
                    $statusContainer.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Keywords werden generiert...').show();
                    
                    // AJAX-Anfrage für Keywords
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'alenseo_wpbakery_generate_keywords',
                            post_id: postId,
                            nonce: '<?php echo wp_create_nonce('alenseo_ajax_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success && response.data.keywords) {
                                // Keyword-Dialog anzeigen
                                showKeywordDialog(response.data.keywords);
                            } else {
                                $statusContainer.html('<span style="color: #dc3232;">Fehler: ' + (response.data.message || 'Unbekannter Fehler') + '</span>');
                                setTimeout(function() {
                                    $statusContainer.fadeOut();
                                }, 3000);
                            }
                            
                            $keywordButton.prop('disabled', false);
                        },
                        error: function() {
                            $statusContainer.html('<span style="color: #dc3232;">Fehler bei der Verbindung zum Server.</span>');
                            $keywordButton.prop('disabled', false);
                            
                            setTimeout(function() {
                                $statusContainer.fadeOut();
                            }, 3000);
                        }
                    });
                });
                
                // Optimierungs-Button-Klick
                $optimizeButton.on('click', function(e) {
                    e.preventDefault();
                    
                    // Post-ID abrufen
                    var postId = $('#post_ID').val();
                    
                    if (!postId) {
                        alert('Post-ID konnte nicht gefunden werden.');
                        return;
                    }
                    
                    // Keyword prüfen
                    var keyword = $seoKeywordField.val();
                    
                    if (!keyword) {
                        alert('Bitte gib zuerst ein Fokus-Keyword ein.');
                        $seoKeywordField.focus();
                        return;
                    }
                    
                    // Button deaktivieren und Statusanzeige
                    $optimizeButton.prop('disabled', true);
                    $statusContainer.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Optimierungsvorschläge werden generiert...').show();
                    
                    // AJAX-Anfrage für Optimierungsvorschläge
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'alenseo_wpbakery_optimize_meta',
                            post_id: postId,
                            keyword: keyword,
                            nonce: '<?php echo wp_create_nonce('alenseo_ajax_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success && response.data.suggestions) {
                                // Optimierungsdialog anzeigen
                                showOptimizationDialog(response.data.suggestions);
                            } else {
                                $statusContainer.html('<span style="color: #dc3232;">Fehler: ' + (response.data.message || 'Unbekannter Fehler') + '</span>');
                                setTimeout(function() {
                                    $statusContainer.fadeOut();
                                }, 3000);
                            }
                            
                            $optimizeButton.prop('disabled', false);
                        },
                        error: function() {
                            $statusContainer.html('<span style="color: #dc3232;">Fehler bei der Verbindung zum Server.</span>');
                            $optimizeButton.prop('disabled', false);
                            
                            setTimeout(function() {
                                $statusContainer.fadeOut();
                            }, 3000);
                        }
                    });
                });
                
                // Keyword-Dialog anzeigen
                function showKeywordDialog(keywords) {
                    // Dialog-Container erstellen
                    var $dialog = $('<div class="alenseo-wpbakery-dialog" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 500px; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 99999;"></div>');
                    
                    // Dialog-Inhalt
                    $dialog.html(
                        '<h3 style="margin-top: 0;">Keyword-Vorschläge</h3>' +
                        '<p>Wähle ein Keyword aus:</p>' +
                        '<div class="alenseo-wpbakery-keywords" style="max-height: 300px; overflow-y: auto;"></div>' +
                        '<div class="alenseo-wpbakery-dialog-actions" style="margin-top: 20px; text-align: right;">' +
                        '<button type="button" class="button alenseo-wpbakery-dialog-close" style="margin-right: 10px;">Abbrechen</button>' +
                        '</div>'
                    );
                    
                    // Keyword-Liste erstellen
                    var $keywordList = $dialog.find('.alenseo-wpbakery-keywords');
                    
                    // Keywords hinzufügen
                    $.each(keywords, function(index, keyword) {
                        var keywordText = keyword.keyword || keyword;
                        var score = keyword.score ? ' <small>(' + keyword.score + ')</small>' : '';
                        
                        var $keywordItem = $('<div class="alenseo-wpbakery-keyword-item" style="padding: 10px; margin-bottom: 5px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;"></div>');
                        $keywordItem.html('<span class="alenseo-wpbakery-keyword-text">' + keywordText + score + '</span>');
                        
                        $keywordItem.on('click', function() {
                            // Keyword in das Feld eintragen
                            $seoKeywordField.val(keywordText);
                            
                            // Dialog schließen
                            $dialog.remove();
                            $overlay.remove();
                            
                            // Statusmeldung
                            $statusContainer.html('<span style="color: #46b450;">Keyword erfolgreich gesetzt.</span>');
                            setTimeout(function() {
                                $statusContainer.fadeOut();
                            }, 2000);
                        });
                        
                        $keywordList.append($keywordItem);
                    });
                    
                    // Dialog schließen
                    $dialog.find('.alenseo-wpbakery-dialog-close').on('click', function() {
                        $dialog.remove();
                        $overlay.remove();
                    });
                    
                    // Overlay erstellen
                    var $overlay = $('<div class="alenseo-wpbakery-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 99998;"></div>');
                    
                    // Overlay-Klick schließt Dialog
                    $overlay.on('click', function() {
                        $dialog.remove();
                        $overlay.remove();
                    });
                    
                    // Dialog und Overlay zur Seite hinzufügen
                    $('body').append($overlay).append($dialog);
                }
                
                // Optimierungsdialog anzeigen
                function showOptimizationDialog(suggestions) {
                    // Dialog-Container erstellen
                    var $dialog = $('<div class="alenseo-wpbakery-dialog" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 600px; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 99999;"></div>');
                    
                    // Dialog-Inhalt
                    var dialogContent = '<h3 style="margin-top: 0;">Optimierungsvorschläge</h3>';
                    
                    // Meta-Description-Vorschlag
                    if (suggestions.meta_description) {
                        dialogContent += '<div class="alenseo-wpbakery-suggestion">' +
                            '<h4>Meta-Description</h4>' +
                            '<div class="alenseo-wpbakery-suggestion-text" style="padding: 10px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 10px;">' + suggestions.meta_description + '</div>' +
                            '<button type="button" class="button alenseo-wpbakery-apply-suggestion" data-type="meta_description">Anwenden</button>' +
                            '</div>';
                    }
                    
                    // Titel-Vorschlag
                    if (suggestions.title) {
                        dialogContent += '<div class="alenseo-wpbakery-suggestion" style="margin-top: 20px;">' +
                            '<h4>Titel</h4>' +
                            '<div class="alenseo-wpbakery-suggestion-text" style="padding: 10px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 10px;">' + suggestions.title + '</div>' +
                            '<button type="button" class="button alenseo-wpbakery-apply-suggestion" data-type="title">Anwenden</button>' +
                            '</div>';
                    }
                    
                    // Content-Vorschläge
                    if (suggestions.content && suggestions.content.length > 0) {
                        dialogContent += '<div class="alenseo-wpbakery-suggestion" style="margin-top: 20px;">' +
                            '<h4>Inhaltsvorschläge</h4>' +
                            '<ul class="alenseo-wpbakery-content-suggestions" style="margin-left: 20px;">';
                            
                        $.each(suggestions.content, function(index, suggestion) {
                            dialogContent += '<li>' + suggestion + '</li>';
                        });
                        
                        dialogContent += '</ul></div>';
                    }
                    
                    // Dialog-Aktionen
                    dialogContent += '<div class="alenseo-wpbakery-dialog-actions" style="margin-top: 20px; text-align: right;">' +
                        '<button type="button" class="button alenseo-wpbakery-dialog-close">Schließen</button>' +
                        '</div>';
                    
                    $dialog.html(dialogContent);
                    
                    // Vorschläge anwenden
                    $dialog.find('.alenseo-wpbakery-apply-suggestion').on('click', function() {
                        var type = $(this).data('type');
                        var text = $(this).prev('.alenseo-wpbakery-suggestion-text').text();
                        
                        if (type === 'meta_description') {
                            // Meta-Description anwenden
                            $seoDescriptionField.val(text);
                        } else if (type === 'title') {
                            // Titel anwenden
                            $('#title').val(text);
                        }
                        
                        // Status-Benachrichtigung
                        $(this).text('Angewendet').prop('disabled', true);
                    });
                    
                    // Dialog schließen
                    $dialog.find('.alenseo-wpbakery-dialog-close').on('click', function() {
                        $dialog.remove();
                        $overlay.remove();
                    });
                    
                    // Overlay erstellen
                    var $overlay = $('<div class="alenseo-wpbakery-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 99998;"></div>');
                    
                    // Overlay-Klick schließt Dialog
                    $overlay.on('click', function() {
                        $dialog.remove();
                        $overlay.remove();
                    });
                    
                    // Dialog und Overlay zur Seite hinzufügen
                    $('body').append($overlay).append($dialog);
                    
                    // Statusmeldung
                    $statusContainer.html('<span style="color: #46b450;">Optimierungsvorschläge generiert.</span>');
                    setTimeout(function() {
                        $statusContainer.fadeOut();
                    }, 2000);
                }
            }
        });
        </script>
        <style>
        /* WPBakery-Integration Styles */
        .alenseo-wpbakery-buttons {
            display: flex;
            align-items: center;
        }
        
        .alenseo-wpbakery-keyword-item:hover {
            background-color: #f0f0f0 !important;
            border-color: #0073aa !important;
        }
        
        .alenseo-wpbakery-suggestion {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .alenseo-wpbakery-suggestion h4 {
            margin-bottom: 8px;
        }
        </style>
        <?php
    }

    /**
     * WPBakery-Assets laden
     */
    public function enqueue_wpbakery_assets($hook) {
        // Nur auf Beitrags- und Seitenerstellungsseiten laden
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        // Nur laden, wenn WPBakery aktiv ist
        if (!$this->is_wpbakery_active()) {
            return;
        }
        
        // WPBakery-Integration-Styles
        wp_enqueue_style(
            'alenseo-wpbakery-integration',
            ALENSEO_MINIMAL_URL . 'assets/css/wpbakery-integration.css',
            array(),
            ALENSEO_MINIMAL_VERSION
        );
    }

    /**
     * AJAX-Handler für Keyword-Generierung
     */
    public function ajax_generate_keywords() {
        // Sicherheits-Check
        check_ajax_referer('alenseo_ajax_nonce', 'nonce');
        
        // Berechtigungen prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
            return;
        }
        
        // Post-ID holen
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Ungültige Post-ID.', 'alenseo')));
            return;
        }
        
        // Claude API-Klasse laden
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
        $claude_api = new Alenseo_Claude_API();
        
        // Prüfen, ob API aktiv ist
        if (!$claude_api->is_active()) {
            wp_send_json_error(array(
                'message' => __('Claude API ist nicht aktiv. Bitte konfiguriere den API-Schlüssel in den Einstellungen.', 'alenseo')
            ));
            return;
        }
        
        // Keywords generieren
        $result = $claude_api->generate_keywords($post_id);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'keywords' => $result['keywords']
            ));
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX-Handler für Optimierungsvorschläge
     */
    public function ajax_optimize_meta() {
        // Sicherheits-Check
        check_ajax_referer('alenseo_ajax_nonce', 'nonce');
        
        // Berechtigungen prüfen
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Keine ausreichenden Berechtigungen.', 'alenseo')));
            return;
        }
        
        // Parameter holen
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Ungültige Post-ID.', 'alenseo')));
            return;
        }
        
        if (empty($keyword)) {
            wp_send_json_error(array('message' => __('Kein Fokus-Keyword angegeben.', 'alenseo')));
            return;
        }
        
        // Claude API-Klasse laden
        require_once ALENSEO_MINIMAL_DIR . 'includes/class-claude-api.php';
        $claude_api = new Alenseo_Claude_API();
        
        // Prüfen, ob API aktiv ist
        if (!$claude_api->is_active()) {
            wp_send_json_error(array(
                'message' => __('Claude API ist nicht aktiv. Bitte konfiguriere den API-Schlüssel in den Einstellungen.', 'alenseo')
            ));
            return;
        }
        
        // Keyword in den Post-Meta speichern
        update_post_meta($post_id, '_alenseo_keyword', $keyword);
        update_post_meta($post_id, 'vc_seo_keyword', $keyword);
        
        // Optimierungsvorschläge generieren
        $result = $claude_api->get_optimization_suggestions($post_id, $keyword);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'suggestions' => $result['suggestions']
            ));
        } else {
            wp_send_json_error($result);
        }
    }
}
