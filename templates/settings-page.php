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
                <tr>
                    <th>Claude API-Schlüssel</th>
                    <td>
                        <input type="password" name="claude_api_key" value="<?php echo esc_attr($settings['claude_api_key']); ?>">
                        <span class="status-indicator">✔</span>
                    </td>
                </tr>
                <tr>
                    <th>ChatGPT API-Schlüssel</th>
                    <td>
                        <input type="password" name="chatgpt_api_key" value="<?php echo esc_attr($settings['chatgpt_api_key']); ?>">
                        <span class="status-indicator">✔</span>
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
                        <textarea name="website_briefing" rows="5" cols="50"><?php echo esc_textarea($settings['website_briefing']); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th>Keywords</th>
                    <td>
                        <input type="text" name="custom_keywords" value="<?php echo esc_attr($settings['custom_keywords']); ?>">
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
