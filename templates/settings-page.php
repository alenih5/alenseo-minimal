<?php
/**
 * Einstellungsseite für Alenseo SEO
 *
 * @link       https://www.imponi.ch
 * @since      1.0.0
 *
 * @package    Alenseo
 * @subpackage Alenseo/templates
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Einstellungen speichern
if (isset($_POST['alenseo_save_settings']) && check_admin_referer('alenseo_settings_nonce', 'alenseo_settings_nonce')) {
    // Einstellungen aus der Datenbank abrufen
    $settings = get_option('alenseo_settings', array());
    
    // Claude API-Einstellungen
    if (isset($_POST['claude_api_key'])) {
        $settings['claude_api_key'] = sanitize_text_field($_POST['claude_api_key']);
    }
    
    if (isset($_POST['claude_model'])) {
        $settings['claude_model'] = sanitize_text_field($_POST['claude_model']);
    }
    
    // Ensure the post types are saved and retrieved correctly
    if (isset($_POST['post_types'])) {
        $settings['post_types'] = array_map('sanitize_text_field', $_POST['post_types']);
    } else {
        $settings['post_types'] = array('post', 'page'); // Default post types
    }
    
    // SEO-Elemente
    $settings['seo_elements'] = array(
        'meta_title' => isset($_POST['seo_meta_title']),
        'meta_description' => isset($_POST['seo_meta_description']),
        'headings' => isset($_POST['seo_headings']),
        'content' => isset($_POST['seo_content'])
    );
    
    // Erweiterte Einstellungen
    $settings['advanced'] = array(
        'keyword_auto_generate' => isset($_POST['keyword_auto_generate']),
        'keyword_min_length' => isset($_POST['keyword_min_length']) ? intval($_POST['keyword_min_length']) : 3,
        'keyword_max_count' => isset($_POST['keyword_max_count']) ? intval($_POST['keyword_max_count']) : 5,
        'api_timeout' => isset($_POST['api_timeout']) ? intval($_POST['api_timeout']) : 30,
        'score_threshold' => isset($_POST['score_threshold']) ? intval($_POST['score_threshold']) : 70
    );

    // ChatGPT API-Schlüssel speichern
    if (isset($_POST['chatgpt_api_key'])) {
        update_option('alenseo_chatgpt_api_key', sanitize_text_field($_POST['chatgpt_api_key']));
    }
    
    // Einstellungen speichern
    update_option('alenseo_settings', $settings);
    
    // Erfolgsmeldung
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Einstellungen erfolgreich gespeichert.', 'alenseo') . '</p></div>';
}

// Einstellungen aus der Datenbank abrufen
$settings = get_option('alenseo_settings', array());

// Standardwerte setzen, falls nicht vorhanden
$settings = wp_parse_args($settings, array(
    'claude_api_key' => '',
    'claude_model' => 'claude-3-haiku-20240307',
    'post_types' => array('post', 'page'),
    'seo_elements' => array(
        'meta_title' => true,
        'meta_description' => true,
        'headings' => true,
        'content' => true
    ),
    'advanced' => array(
        'keyword_auto_generate' => true,
        'keyword_min_length' => 3,
        'keyword_max_count' => 5,
        'api_timeout' => 30,
        'score_threshold' => 70
    )
));

// Claude API-Instanz erstellen, falls die Klasse existiert
$api_configured = false;
$api_test_result = '';
$available_models = array(
    'claude-3-opus-20240229' => 'Claude 3 Opus',
    'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
    'claude-3-haiku-20240307' => 'Claude 3 Haiku'
);

if (class_exists('Alenseo_Claude_API')) {
    $claude_api = new Alenseo_Claude_API();
    $api_configured = $claude_api->is_api_configured();
    $available_models = $claude_api->get_available_models();
    
    // API-Test durchführen, wenn angefordert
    if (isset($_POST['test_api']) && check_admin_referer('alenseo_settings_nonce', 'alenseo_settings_nonce')) {
        $test_result = $claude_api->test_api_key();
        
        if ($test_result === true) {
            $api_test_result = 'success';
        } elseif (is_wp_error($test_result)) {
            $api_test_result = 'error';
            $api_error_message = $test_result->get_error_message();
        }
    }
}

// Verfügbare Post-Typen abrufen
$post_types = get_post_types(array(
    'public' => true
), 'objects');
?>

<div class="wrap">
    <h1><?php _e('Alenseo SEO Einstellungen', 'alenseo'); ?></h1>
    
    <div class="alenseo-settings-tab-nav">
        <a href="#general" class="nav-tab nav-tab-active"><?php _e('Allgemein', 'alenseo'); ?></a>
        <a href="#api" class="nav-tab"><?php _e('Claude API', 'alenseo'); ?></a>
        <a href="#advanced" class="nav-tab"><?php _e('Erweitert', 'alenseo'); ?></a>
        <a href="#chatgpt" class="nav-tab"><?php _e('ChatGPT API', 'alenseo'); ?></a>
    </div>
    
    <form method="post" action="">
        <?php wp_nonce_field('alenseo_settings_nonce', 'alenseo_settings_nonce'); ?>
        
        <div id="general" class="alenseo-settings-tab active">
            <div class="alenseo-settings-section">
                <h2><?php _e('Allgemeine Einstellungen', 'alenseo'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Post-Typen', 'alenseo'); ?></th>
                        <td>
                            <?php foreach ($post_types as $post_type) : ?>
                                <label>
                                    <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $settings['post_types'])); ?>>
                                    <?php echo esc_html($post_type->labels->singular_name); ?>
                                </label><br>
                            <?php endforeach; ?>
                            <p class="description"><?php _e('Wähle die Post-Typen aus, die von Alenseo SEO optimiert werden sollen.', 'alenseo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('SEO-Elemente', 'alenseo'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="seo_meta_title" <?php checked($settings['seo_elements']['meta_title']); ?>>
                                <?php _e('Meta-Titel', 'alenseo'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="seo_meta_description" <?php checked($settings['seo_elements']['meta_description']); ?>>
                                <?php _e('Meta-Description', 'alenseo'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="seo_headings" <?php checked($settings['seo_elements']['headings']); ?>>
                                <?php _e('Überschriften', 'alenseo'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="seo_content" <?php checked($settings['seo_elements']['content']); ?>>
                                <?php _e('Inhalt', 'alenseo'); ?>
                            </label><br>
                            <p class="description"><?php _e('Wähle die SEO-Elemente aus, die analysiert und optimiert werden sollen.', 'alenseo'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div id="api" class="alenseo-settings-tab">
            <div class="alenseo-settings-section">
                <h2><?php _e('Claude API-Einstellungen', 'alenseo'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('API-Schlüssel', 'alenseo'); ?></th>
                        <td>
                            <input type="password" name="claude_api_key" id="claude_api_key" value="<?php echo esc_attr($settings['claude_api_key']); ?>" class="regular-text">
                            <button type="button" id="toggle_api_key" class="button button-secondary">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button type="button" id="alenseo-api-test" class="button button-secondary">
                                <?php esc_html_e('API testen', 'alenseo'); ?>
                            </button>
                            
                            <div id="api-test-result">
                                <?php 
                                // Claude API-Objekt erstellen
                                $claude_api = new Alenseo_Claude_API(
                                    isset($settings['claude_api_key']) ? $settings['claude_api_key'] : '', 
                                    isset($settings['claude_model']) ? $settings['claude_model'] : 'claude-3-haiku-20240307'
                                );
                                
                                // API-Status anzeigen wenn API-Schlüssel konfiguriert ist
                                if ($claude_api->is_api_configured()) : 
                                    $test_result = $claude_api->test_api_key();
                                    if ($test_result['success']) :
                                ?>
                                    <span class="success"><?php echo esc_html($test_result['message']); ?></span>
                                <?php else : ?>
                                    <span class="error"><?php echo esc_html($test_result['message']); ?></span>
                                <?php endif; endif; ?>
                            </div>
                            
                            <p class="description">
                                <?php _e('Gib deinen Claude API-Schlüssel ein. Du kannst einen API-Schlüssel auf <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a> erstellen.', 'alenseo'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Modell', 'alenseo'); ?></th>
                        <td>
                            <select name="claude_model" id="claude_model">
                                <?php foreach ($available_models as $model_id => $model_name) : ?>
                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($settings['claude_model'], $model_id); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Wähle das zu verwendende Claude-Modell. Haiku ist schneller, während Opus präzisere Ergebnisse liefert.', 'alenseo'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div id="advanced" class="alenseo-settings-tab">
            <div class="alenseo-settings-section">
                <h2><?php _e('Erweiterte Einstellungen', 'alenseo'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Keyword-Auto-Generierung', 'alenseo'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="keyword_auto_generate" <?php checked($settings['advanced']['keyword_auto_generate']); ?>>
                                <?php _e('Automatische Keyword-Generierung aktivieren', 'alenseo'); ?>
                            </label>
                            <p class="description"><?php _e('Aktiviere diese Option, um automatisch Keywords zu generieren.', 'alenseo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Minimale Keyword-Länge', 'alenseo'); ?></th>
                        <td>
                            <input type="number" name="keyword_min_length" value="<?php echo esc_attr($settings['advanced']['keyword_min_length']); ?>" class="small-text">
                            <p class="description"><?php _e('Gib die minimale Länge für generierte Keywords ein.', 'alenseo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Maximale Keyword-Anzahl', 'alenseo'); ?></th>
                        <td>
                            <input type="number" name="keyword_max_count" value="<?php echo esc_attr($settings['advanced']['keyword_max_count']); ?>" class="small-text">
                            <p class="description"><?php _e('Gib die maximale Anzahl an generierten Keywords ein.', 'alenseo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('API-Timeout', 'alenseo'); ?></th>
                        <td>
                            <input type="number" name="api_timeout" value="<?php echo esc_attr($settings['advanced']['api_timeout']); ?>" class="small-text">
                            <p class="description"><?php _e('Gib das Timeout für API-Anfragen in Sekunden ein.', 'alenseo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Score-Schwellenwert', 'alenseo'); ?></th>
                        <td>
                            <input type="number" name="score_threshold" value="<?php echo esc_attr($settings['advanced']['score_threshold']); ?>" class="small-text">
                            <p class="description"><?php _e('Gib den Schwellenwert für den SEO-Score ein.', 'alenseo'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div id="chatgpt" class="alenseo-settings-tab">
            <div class="alenseo-settings-section">
                <h2><?php _e('ChatGPT API-Einstellungen', 'alenseo'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('API-Schlüssel', 'alenseo'); ?></th>
                        <td>
                            <input type="password" name="chatgpt_api_key" id="chatgpt_api_key" value="<?php echo esc_attr(get_option('alenseo_chatgpt_api_key')); ?>" class="regular-text">
                            <button type="button" id="toggle_chatgpt_api_key" class="button button-secondary">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <p class="description"><?php _e('Gib deinen ChatGPT API-Schlüssel ein. Du kannst einen API-Schlüssel auf <a href="https://platform.openai.com/" target="_blank">platform.openai.com</a> erstellen.', 'alenseo'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="alenseo_save_settings" class="button button-primary" value="<?php esc_attr_e('Einstellungen speichern', 'alenseo'); ?>">
        </p>
    </form>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab-Navigation debuggen und sicherstellen, dass die Inhalte korrekt angezeigt werden
        $('.alenseo-settings-tab-nav a').on('click', function(e) {
            e.preventDefault();

            // Aktiven Tab ändern
            $('.alenseo-settings-tab-nav a').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Tab-Inhalt ändern
            var target = $(this).attr('href').substring(1);
            $('.alenseo-settings-tab').removeClass('active');
            $('#' + target).addClass('active');
        });

        // Sicherstellen, dass der aktive Tab beim Laden korrekt gesetzt ist
        var activeTab = sessionStorage.getItem('alenseo_active_tab');
        if (activeTab) {
            $('.alenseo-settings-tab-nav a[href="#' + activeTab + '"]').trigger('click');
        } else {
            $('.alenseo-settings-tab-nav a.nav-tab-active').trigger('click');
        }

        // API-Schlüssel ein-/ausblenden
        $('#toggle_api_key').on('click', function() {
            var apiKeyField = $('#claude_api_key');
            var icon = $(this).find('.dashicons');
            
            if (apiKeyField.attr('type') === 'password') {
                apiKeyField.attr('type', 'text');
                icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                apiKeyField.attr('type', 'password');
                icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });
        
        // API-Test-Button
        $('#alenseo-api-test').on('click', function() {
            var apiKey = $('#claude_api_key').val();
            var model = $('#claude_model').val();
            
            if (!apiKey) {
                $('#api-test-result').html('<span class="error"><?php _e('Bitte geben Sie einen API-Schlüssel ein.', 'alenseo'); ?></span>');
                return;
            }
            
            // Button-Status ändern
            var $button = $(this);
            var originalText = $button.text();
            $button.text('<?php _e('Wird getestet...', 'alenseo'); ?>').prop('disabled', true);
            
            // AJAX-Anfrage zum Testen der API
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_test_api',
                    api_key: apiKey,
                    model: model,
                    nonce: '<?php echo wp_create_nonce('alenseo_test_api_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#api-test-result').html('<span class="success">' + response.data.message + '</span>');
                    } else {
                        $('#api-test-result').html('<span class="error">' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $('#api-test-result').html('<span class="error"><?php _e('Fehler bei der API-Verbindung.', 'alenseo'); ?></span>');
                },
                complete: function() {
                    // Button zurücksetzen
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Setze den zuvor aktiven Tab wieder
        var activeTab = sessionStorage.getItem('alenseo_active_tab');
        if (activeTab) {
            $('.alenseo-settings-tab-nav a[href="#' + activeTab + '"]').trigger('click');
        }
        
        // Form-Speichern überschreiben, um den aktiven Tab zu erhalten
        $('form').on('submit', function() {
            var activeTab = $('.alenseo-settings-tab-nav a.nav-tab-active').attr('href').substring(1);
            sessionStorage.setItem('alenseo_active_tab', activeTab);
        });

        // ChatGPT API-Schlüssel ein-/ausblenden
        $('#toggle_chatgpt_api_key').on('click', function() {
            var apiKeyField = $('#chatgpt_api_key');
            var icon = $(this).find('.dashicons');

            if (apiKeyField.attr('type') === 'password') {
                apiKeyField.attr('type', 'text');
                icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                apiKeyField.attr('type', 'password');
                icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });
    });
    </script>
    
    <style>
    .alenseo-settings-tab-nav {
        margin-bottom: 15px;
    }
    
    .alenseo-settings-tab {
        display: none;
    }
    
    .alenseo-settings-tab.active {
        display: block;
    }
    
    .alenseo-settings-section {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .alenseo-settings-section h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    </style>
</div>

<h2>KI-Integration</h2>
<form method="post" action="options.php">
    <?php settings_fields('alenseo_settings_group'); ?>
    <?php do_settings_sections('alenseo_settings_group'); ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Claude API-Schlüssel</th>
            <td><input type="text" name="alenseo_claude_api_key" value="<?php echo esc_attr(get_option('alenseo_claude_api_key')); ?>" /></td>
        </tr>
    </table>
    <?php submit_button(); ?>
</form>
