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

    // Website-Briefing und Keywords speichern
    if (isset($_POST['website_briefing'])) {
        $settings['website_briefing'] = sanitize_textarea_field($_POST['website_briefing']);
    }

    if (isset($_POST['custom_keywords'])) {
        $settings['custom_keywords'] = sanitize_text_field($_POST['custom_keywords']);
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
    ),
    'website_briefing' => '',
    'custom_keywords' => ''
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

$openai_api_key = $settings['openai_api_key'] ?? '';
$claude_api_key = $settings['claude_api_key'] ?? '';
?>

<div class="wrap">
    <h1><?php _e('Alenseo SEO Einstellungen', 'alenseo'); ?></h1>

    <div class="alenseo-settings-tab-nav">
        <a href="#general" class="nav-tab nav-tab-active">Allgemein</a>
        <a href="#api" class="nav-tab">KI API</a>
        <a href="#expand" class="nav-tab">Erweitern</a>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('alenseo_settings_nonce', 'alenseo_settings_nonce'); ?>

        <!-- General Tab -->
        <div id="general" class="alenseo-settings-tab active">
            <h2>Allgemeine Einstellungen</h2>
            <table class="form-table">
                <tr>
                    <th>Post-Typen</th>
                    <td>
                        <?php foreach ($post_types as $post_type) : ?>
                            <label>
                                <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $settings['post_types'])); ?>>
                                <?php echo esc_html($post_type->labels->singular_name); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- API Tab -->
        <div id="api" class="alenseo-settings-tab">
            <h2>KI API Einstellungen</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">OpenAI API Key (ChatGPT)</th>
                    <td style="position:relative;">
                        <input type="text" id="openai_api_key" name="alenseo_settings[openai_api_key]" value="<?php echo esc_attr($openai_api_key); ?>" size="60" autocomplete="off" />
                        <span id="openai-api-status-indicator" style="display:inline-block;width:18px;height:18px;border-radius:50%;margin-left:10px;vertical-align:middle;background:#ccc;border:1px solid #bbb;"></span>
                        <span id="openai-api-status-text" style="margin-left:10px;color:#666;">
                            <?php echo !empty($openai_api_key) ? __('Status unbekannt', 'alenseo') : __('Nicht verbunden', 'alenseo'); ?>
                        </span>
                        <p class="description"><?php _e('Trage hier deinen OpenAI API-Key für ChatGPT ein.', 'alenseo'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Claude API Key</th>
                    <td style="position:relative;">
                        <input type="text" id="claude_api_key" name="alenseo_settings[claude_api_key]" value="<?php echo esc_attr($claude_api_key); ?>" size="60" autocomplete="off" />
                        <span id="claude-api-status-indicator" style="display:inline-block;width:18px;height:18px;border-radius:50%;margin-left:10px;vertical-align:middle;background:#ccc;border:1px solid #bbb;"></span>
                        <span id="claude-api-status-text" style="margin-left:10px;color:#666;">
                            <?php echo $api_configured ? __('API verbunden', 'alenseo') : __('Nicht verbunden', 'alenseo'); ?>
                        </span>
                        <p class="description"><?php _e('Trage hier deinen Claude API-Key ein.', 'alenseo'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Expand Tab -->
        <div id="expand" class="alenseo-settings-tab">
            <h2>Erweitern</h2>
            <table class="form-table">
                <tr>
                    <th>Website-Briefing</th>
                    <td>
                        <textarea name="website_briefing" rows="5" cols="50"><?php echo esc_textarea(isset($settings['website_briefing']) ? $settings['website_briefing'] : ''); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>Keywords</th>
                    <td>
                        <input type="text" name="custom_keywords" value="<?php echo esc_attr(isset($settings['custom_keywords']) ? $settings['custom_keywords'] : ''); ?>">
                        <p class="description">Geben Sie Schlüsselwörter ein, die der Generator verwenden soll.</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="alenseo_save_settings" class="button-primary" value="Einstellungen speichern">
        </p>
    </form>
</div>

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
                action: 'alenseo_test_claude_api',
                api_key: apiKey,
                model: model,
                nonce: '<?php echo wp_create_nonce('alenseo_ajax_nonce'); ?>'
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

    function updateClaudeApiStatus(status, message) {
        var $indicator = $('#claude-api-status-indicator');
        var $text = $('#claude-api-status-text');
        if(status === 'success') {
            $indicator.css('background','#28a745').css('border-color','#28a745');
            $text.text(message || 'API verbunden').css('color','#28a745');
        } else if(status === 'error') {
            $indicator.css('background','#dc3545').css('border-color','#dc3545');
            $text.text(message || 'API nicht verbunden').css('color','#dc3545');
        } else {
            $indicator.css('background','#ccc').css('border-color','#bbb');
            $text.text(message || 'Status unbekannt').css('color','#666');
        }
    }

    // Initialstatus setzen
    <?php if ($api_configured): ?>
        updateClaudeApiStatus('success', '<?php echo esc_js(__('API verbunden', 'alenseo')); ?>');
    <?php else: ?>
        updateClaudeApiStatus('error', '<?php echo esc_js(__('Nicht verbunden', 'alenseo')); ?>');
    <?php endif; ?>

    $('#claude_api_key').on('change blur', function(){
        var key = $(this).val();
        if(!key) {
            updateClaudeApiStatus('error', '<?php echo esc_js(__('Kein API-Key eingegeben', 'alenseo')); ?>');
            return;
        }        // AJAX-Check
        $.post(ajaxurl, {
            action: 'alenseo_test_claude_api',
            nonce: '<?php echo wp_create_nonce('alenseo_ajax_nonce'); ?>',
            api_key: key,
            model: '<?php echo esc_js($settings['claude_model']); ?>'
        }, function(resp){
            if(resp.success) {
                updateClaudeApiStatus('success', resp.data && resp.data.message ? resp.data.message : 'API verbunden');
            } else {
                // Fehlertext IMMER anzeigen
                var msg = (resp.data && resp.data.message) ? resp.data.message : 'API nicht verbunden';
                updateClaudeApiStatus('error', msg);
            }
        });
    });

    function updateOpenAiApiStatus(status, message) {
        var $indicator = $('#openai-api-status-indicator');
        var $text = $('#openai-api-status-text');
        if(status === 'success') {
            $indicator.css('background','#28a745').css('border-color','#28a745');
            $text.text(message || 'API verbunden').css('color','#28a745');
        } else if(status === 'error') {
            $indicator.css('background','#dc3545').css('border-color','#dc3545');
            $text.text(message || 'API nicht verbunden').css('color','#dc3545');
        } else {
            $indicator.css('background','#ccc').css('border-color','#bbb');
            $text.text(message || 'Status unbekannt').css('color','#666');
        }
    }

    // Initialstatus für OpenAI
    <?php if (!empty($openai_api_key)): ?>
        updateOpenAiApiStatus('unknown', '<?php echo esc_js(__('Status unbekannt', 'alenseo')); ?>');
    <?php else: ?>
        updateOpenAiApiStatus('error', '<?php echo esc_js(__('Nicht verbunden', 'alenseo')); ?>');
    <?php endif; ?>

    $('#openai_api_key').on('change blur', function(){
        var key = $(this).val();
        if(!key) {
            updateOpenAiApiStatus('error', '<?php echo esc_js(__('Kein API-Key eingegeben', 'alenseo')); ?>');
            return;
        }
        // AJAX-Check        $.post(ajaxurl, {
            action: 'alenseo_test_openai_api',
            nonce: '<?php echo wp_create_nonce('alenseo_ajax_nonce'); ?>',
            api_key: key
        }, function(resp){
            if(resp.success) {
                updateOpenAiApiStatus('success', resp.data && resp.data.message ? resp.data.message : 'API verbunden');
            } else {
                updateOpenAiApiStatus('error', resp.data && resp.data.message ? resp.data.message : 'API nicht verbunden');
            }
        });
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
