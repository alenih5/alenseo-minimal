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
    
    // Post-Typen
    $settings['post_types'] = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array('post', 'page');
    
    // SEO-Elemente
    $settings['seo_elements'] = array(
        'meta_title' => isset($_POST['seo_meta_title']),
        'meta_description' => isset($_POST['seo_meta_description']),
        'headings' => isset($_POST['seo_headings']),
        'content' => isset($_POST['seo_content'])
    );
    
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
                
                <p><?php _e('Erweiterte Einstellungen werden in zukünftigen Versionen verfügbar sein.', 'alenseo'); ?></p>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="alenseo_save_settings" class="button button-primary" value="<?php esc_attr_e('Einstellungen speichern', 'alenseo'); ?>">
        </p>
    </form>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab-Navigation
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
