<?php
/**
 * Template für die Einstellungsseite mit Claude API-Konfiguration
 *
 * @link       https://imponi.ch
 * @since      1.0.0
 *
 * @package    Alenseo
 * @subpackage Alenseo/templates
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Aktuelle Einstellungen laden
$settings = get_option('alenseo_settings', array());

// Standardwerte für Einstellungen
$default_settings = array(
    'claude_api_key' => '',
    'claude_model' => 'claude-3-haiku-20240307',
    'post_types' => array('post', 'page'),
    'seo_elements' => array(
        'meta_title' => true,
        'meta_description' => true,
        'headings' => true,
        'content' => true
    )
);

// Einstellungen mit Standardwerten zusammenführen
$settings = wp_parse_args($settings, $default_settings);

// Einstellungen speichern, wenn das Formular abgeschickt wurde
if (isset($_POST['alenseo_save_settings']) && check_admin_referer('alenseo_settings_nonce', 'alenseo_settings_nonce')) {
    
    // API-Schlüssel speichern
    $settings['claude_api_key'] = isset($_POST['claude_api_key']) ? sanitize_text_field($_POST['claude_api_key']) : '';
    
    // Claude-Modell speichern
    $settings['claude_model'] = isset($_POST['claude_model']) ? sanitize_text_field($_POST['claude_model']) : 'claude-3-haiku-20240307';
    
    // Post-Typen speichern
    $settings['post_types'] = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array('post', 'page');
    
    // SEO-Elemente speichern
    $settings['seo_elements'] = array(
        'meta_title' => isset($_POST['seo_elements']['meta_title']),
        'meta_description' => isset($_POST['seo_elements']['meta_description']),
        'headings' => isset($_POST['seo_elements']['headings']),
        'content' => isset($_POST['seo_elements']['content'])
    );
    
    // Einstellungen in der Datenbank speichern
    update_option('alenseo_settings', $settings);
    
    // Erfolgsmeldung anzeigen
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Einstellungen erfolgreich gespeichert.', 'alenseo') . '</p></div>';
}

// Verfügbare Claude-Modelle
$claude_models = array(
    'claude-3-haiku-20240307' => 'Claude 3 Haiku (schnell, günstig)',
    'claude-3-sonnet-20240229' => 'Claude 3 Sonnet (ausgewogen)',
    'claude-3-opus-20240229' => 'Claude 3 Opus (leistungsstark, teurer)'
);

// CSS für die Einstellungsseite
?>
<style>
    .alenseo-settings-container {
        max-width: 900px;
        margin-top: 20px;
    }
    
    .alenseo-settings-tabs {
        display: flex;
        margin-bottom: 0;
        border-bottom: 1px solid #ccc;
    }
    
    .alenseo-settings-tab {
        padding: 10px 15px;
        background: #f1f1f1;
        border: 1px solid #ccc;
        border-bottom: none;
        margin-right: 5px;
        cursor: pointer;
    }
    
    .alenseo-settings-tab.active {
        background: #fff;
        border-bottom: 1px solid #fff;
        margin-bottom: -1px;
    }
    
    .alenseo-settings-content {
        background: #fff;
        border: 1px solid #ccc;
        border-top: none;
        padding: 20px;
    }
    
    .alenseo-settings-section {
        display: none;
    }
    
    .alenseo-settings-section.active {
        display: block;
    }
    
    .alenseo-api-key-container {
        display: flex;
        align-items: center;
        max-width: 600px;
    }
    
    .alenseo-api-key-input {
        flex-grow: 1;
        margin-right: 10px !important;
    }
    
    .alenseo-api-status {
        margin-top: 10px;
        padding: 10px;
        border-radius: 3px;
        display: none;
    }
    
    .alenseo-api-status.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .alenseo-api-status.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .alenseo-checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }
    
    .alenseo-checkbox-item {
        display: flex;
        align-items: center;
    }
    
    .alenseo-checkbox-item input {
        margin-right: 5px;
    }

    .alenseo-api-info {
        margin-top: 15px;
        padding: 15px;
        background-color: #f0f8ff;
        border: 1px solid #add8e6;
        border-radius: 5px;
    }

    .alenseo-api-info h3 {
        margin-top: 0;
        color: #0073aa;
    }

    .alenseo-api-info ul {
        margin-left: 20px;
    }
</style>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('Alenseo SEO Einstellungen', 'alenseo'); ?>
    </h1>
    
    <div class="alenseo-settings-container">
        <div class="alenseo-settings-tabs">
            <div class="alenseo-settings-tab active" data-tab="general"><?php _e('Allgemein', 'alenseo'); ?></div>
            <div class="alenseo-settings-tab" data-tab="api"><?php _e('Claude API', 'alenseo'); ?></div>
            <div class="alenseo-settings-tab" data-tab="seo"><?php _e('SEO-Einstellungen', 'alenseo'); ?></div>
        </div>
        
        <form method="post" action="">
            <?php
            // Nonce für Sicherheit
            wp_nonce_field('alenseo_settings_nonce', 'alenseo_settings_nonce');
            ?>
            
            <div class="alenseo-settings-content">
                <!-- Allgemeine Einstellungen -->
                <div class="alenseo-settings-section active" id="alenseo-tab-general">
                    <h2><?php _e('Allgemeine Einstellungen', 'alenseo'); ?></h2>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('Post-Typen', 'alenseo'); ?></th>
                            <td>
                                <div class="alenseo-checkbox-grid">
                                    <?php
                                    $post_types = get_post_types(array('public' => true), 'objects');
                                    $enabled_post_types = $settings['post_types'];
                                    
                                    foreach ($post_types as $post_type) {
                                        ?>
                                        <div class="alenseo-checkbox-item">
                                            <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $enabled_post_types)); ?>>
                                            <label><?php echo esc_html($post_type->label); ?></label>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                                <p class="description"><?php _e('Wähle die Post-Typen aus, für die Alenseo SEO aktiviert sein soll.', 'alenseo'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Claude API Einstellungen -->
                <div class="alenseo-settings-section" id="alenseo-tab-api">
                    <h2><?php _e('Claude API Einstellungen', 'alenseo'); ?></h2>
                    
                    <div class="alenseo-api-info">
                        <h3><?php _e('Claude API-Funktionen', 'alenseo'); ?></h3>
                        <p><?php _e('Mit der Claude API erhältst du folgende KI-gestützte Funktionen:', 'alenseo'); ?></p>
                        <ul>
                            <li><?php _e('Intelligenter Keyword-Generator, der auf Basis des Inhalts relevante Keywords vorschlägt', 'alenseo'); ?></li>
                            <li><?php _e('Optimierungsvorschläge für Titel, Meta-Beschreibung und Inhalt', 'alenseo'); ?></li>
                            <li><?php _e('SEO-Analyse mit spezifischen Verbesserungsmöglichkeiten', 'alenseo'); ?></li>
                        </ul>
                        <p><?php _e('Um diese Funktionen zu nutzen, benötigst du einen API-Schlüssel von Anthropic.', 'alenseo'); ?></p>
                    </div>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('Claude API-Schlüssel', 'alenseo'); ?></th>
                            <td>
                                <div class="alenseo-api-key-container">
                                    <input type="password" name="claude_api_key" id="claude_api_key" value="<?php echo esc_attr($settings['claude_api_key']); ?>" class="regular-text alenseo-api-key-input">
                                    <button type="button" class="button" id="alenseo-test-api"><?php _e('API testen', 'alenseo'); ?></button>
                                </div>
                                <div class="alenseo-api-status" id="alenseo-api-status"></div>
                                <p class="description">
                                    <?php _e('Trage deinen Claude API-Schlüssel ein. <a href="https://console.anthropic.com/keys" target="_blank">API-Schlüssel erhalten</a>', 'alenseo'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row"><?php _e('Claude Modell', 'alenseo'); ?></th>
                            <td>
                                <select name="claude_model" id="claude_model" class="regular-text">
                                    <?php foreach ($claude_models as $model_id => $model_name) : ?>
                                        <option value="<?php echo esc_attr($model_id); ?>" <?php selected($settings['claude_model'], $model_id); ?>>
                                            <?php echo esc_html($model_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php _e('Wähle das Claude-Modell, das für die SEO-Analyse verwendet werden soll.', 'alenseo'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- SEO-Einstellungen -->
                <div class="alenseo-settings-section" id="alenseo-tab-seo">
                    <h2><?php _e('SEO-Einstellungen', 'alenseo'); ?></h2>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('SEO-Elemente', 'alenseo'); ?></th>
                            <td>
                                <?php
                                $seo_elements = $settings['seo_elements'];
                                ?>
                                <div class="alenseo-checkbox-grid">
                                    <div class="alenseo-checkbox-item">
                                        <input type="checkbox" name="seo_elements[meta_title]" value="1" <?php checked(!empty($seo_elements['meta_title'])); ?>>
                                        <label><?php _e('Meta-Titel', 'alenseo'); ?></label>
                                    </div>
                                    
                                    <div class="alenseo-checkbox-item">
                                        <input type="checkbox" name="seo_elements[meta_description]" value="1" <?php checked(!empty($seo_elements['meta_description'])); ?>>
                                        <label><?php _e('Meta-Description', 'alenseo'); ?></label>
                                    </div>
                                    
                                    <div class="alenseo-checkbox-item">
                                        <input type="checkbox" name="seo_elements[headings]" value="1" <?php checked(!empty($seo_elements['headings'])); ?>>
                                        <label><?php _e('Überschriften', 'alenseo'); ?></label>
                                    </div>
                                    
                                    <div class="alenseo-checkbox-item">
                                        <input type="checkbox" name="seo_elements[content]" value="1" <?php checked(!empty($seo_elements['content'])); ?>>
                                        <label><?php _e('Inhalt', 'alenseo'); ?></label>
                                    </div>
                                </div>
                                <p class="description">
                                    <?php _e('Wähle die SEO-Elemente aus, die analysiert und optimiert werden sollen.', 'alenseo'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <p class="submit">
                <input type="submit" name="alenseo_save_settings" class="button-primary" value="<?php _e('Einstellungen speichern', 'alenseo'); ?>">
            </p>
        </form>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Tab-Funktionalität
    $('.alenseo-settings-tab').on('click', function() {
        var tab = $(this).data('tab');
        
        // Aktiven Tab setzen
        $('.alenseo-settings-tab').removeClass('active');
        $(this).addClass('active');
        
        // Aktiven Inhalt setzen
        $('.alenseo-settings-section').removeClass('active');
        $('#alenseo-tab-' + tab).addClass('active');
    });
    
    // API-Test-Funktionalität
    $('#alenseo-test-api').on('click', function() {
        var api_key = $('#claude_api_key').val();
        var status_container = $('#alenseo-api-status');
        
        if (!api_key) {
            status_container.html('<?php _e('Bitte gib einen API-Schlüssel ein.', 'alenseo'); ?>');
            status_container.removeClass('success').addClass('error').show();
            return;
        }
        
        // Button-Status ändern
        var $button = $(this);
        var original_text = $button.text();
        $button.text('<?php _e('Teste...', 'alenseo'); ?>').prop('disabled', true);
        
        // Status zurücksetzen
        status_container.html('').removeClass('success error').hide();
        
        // AJAX-Anfrage senden
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_test_claude_api',
                nonce: '<?php echo wp_create_nonce('alenseo_ajax_nonce'); ?>',
                api_key: api_key
            },
            success: function(response) {
                if (response.success) {
                    status_container.html('<?php _e('API-Verbindung erfolgreich hergestellt.', 'alenseo'); ?>');
                    status_container.removeClass('error').addClass('success').show();
                } else {
                    var error_message = response.data.message || '<?php _e('Ein unbekannter Fehler ist aufgetreten.', 'alenseo'); ?>';
                    status_container.html(error_message);
                    status_container.removeClass('success').addClass('error').show();
                }
            },
            error: function() {
                status_container.html('<?php _e('Fehler bei der Verbindung zum Server.', 'alenseo'); ?>');
                status_container.removeClass('success').addClass('error').show();
            },
            complete: function() {
                // Button-Status zurücksetzen
                $button.text(original_text).prop('disabled', false);
            }
        });
    });
});
</script>
