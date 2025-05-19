<?php
/**
 * Meta-Box-Template für Alenseo SEO
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

// Überprüfen, ob Post-ID verfügbar ist
if (!isset($post_id)) {
    $post_id = $post->ID;
}

// Keyword und Meta-Description abrufen
if (!isset($keyword)) {
    $keyword = get_post_meta($post_id, '_alenseo_keyword', true);
}

if (!isset($meta_description)) {
    $meta_description = get_post_meta($post_id, '_alenseo_meta_description', true);
}

// SEO-Score und Status abrufen
if (!isset($seo_score)) {
    $seo_score = get_post_meta($post_id, '_alenseo_seo_score', true);
}

if (!isset($seo_status)) {
    $seo_status = get_post_meta($post_id, '_alenseo_seo_status', true);
}

// Statusklasse und Text bestimmen
$status_class = 'unknown';
$status_text = __('Nicht analysiert', 'alenseo');

if ($seo_score !== '') {
    if ($seo_score >= 80) {
        $status_class = 'good';
        $status_text = __('Gut optimiert', 'alenseo');
    } elseif ($seo_score >= 50) {
        $status_class = 'ok';
        $status_text = __('Teilweise optimiert', 'alenseo');
    } else {
        $status_class = 'poor';
        $status_text = __('Optimierung nötig', 'alenseo');
    }
}

// Claude API-Status prüfen
if (!isset($claude_api_active)) {
    $settings = get_option('alenseo_settings', array());
    $claude_api_active = !empty($settings['claude_api_key']);
}
?>

<div class="alenseo-meta-box">
    <div class="alenseo-meta-box-header">
        <div class="alenseo-meta-box-title">
            <h2><?php _e('Alenseo SEO Optimierung', 'alenseo'); ?></h2>
        </div>
        
        <?php if ($seo_score !== '') : ?>
            <div class="alenseo-meta-box-score">
                <div class="alenseo-score-pill <?php echo esc_attr('score-' . $status_class); ?>">
                    <?php echo esc_html($seo_score); ?>
                </div>
                <div class="alenseo-status <?php echo esc_attr('status-' . $status_class); ?>">
                    <?php echo esc_html($status_text); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="alenseo-meta-box-content">
        <div class="alenseo-meta-box-section">
            <label for="alenseo_keyword"><?php _e('Fokus-Keyword:', 'alenseo'); ?></label>
            <input type="text" id="alenseo_keyword" name="alenseo_keyword" value="<?php echo esc_attr($keyword); ?>" placeholder="<?php esc_attr_e('z.B. WordPress SEO Plugin', 'alenseo'); ?>">
            
            <?php if ($claude_api_active) : ?>
                <button type="button" id="alenseo_generate_keywords" class="button">
                    <?php _e('Keywords generieren', 'alenseo'); ?>
                </button>
                
                <div id="alenseo_keyword_suggestions" style="display: none;">
                    <h4><?php _e('Keyword-Vorschläge:', 'alenseo'); ?></h4>
                    <div id="alenseo_keyword_list"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="alenseo-meta-box-section">
            <label for="alenseo_meta_description"><?php _e('Meta-Description:', 'alenseo'); ?></label>
            <textarea id="alenseo_meta_description" name="alenseo_meta_description" rows="3" placeholder="<?php esc_attr_e('Eine prägnante Beschreibung für die Suchergebnisse (120-160 Zeichen).', 'alenseo'); ?>"><?php echo esc_textarea($meta_description); ?></textarea>
            <div class="alenseo-character-count">
                <span id="alenseo_meta_description_count"><?php echo strlen($meta_description); ?></span> / 160
            </div>
            
            <?php if ($claude_api_active) : ?>
                <button type="button" id="alenseo_generate_description" class="button">
                    <?php _e('Mit Claude optimieren', 'alenseo'); ?>
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($seo_score !== '') : ?>
            <div class="alenseo-meta-box-section">
                <h3><?php _e('SEO-Analyse', 'alenseo'); ?></h3>
                
                <?php
                // Detaillierte Analyseergebnisse abrufen
                $title_score = get_post_meta($post_id, '_alenseo_title_score', true);
                $title_message = get_post_meta($post_id, '_alenseo_title_message', true);
                $content_score = get_post_meta($post_id, '_alenseo_content_score', true);
                $content_message = get_post_meta($post_id, '_alenseo_content_message', true);
                $url_score = get_post_meta($post_id, '_alenseo_url_score', true);
                $url_message = get_post_meta($post_id, '_alenseo_url_message', true);
                $meta_description_score = get_post_meta($post_id, '_alenseo_meta_description_score', true);
                $meta_description_message = get_post_meta($post_id, '_alenseo_meta_description_message', true);
                
                // Funktion zum Bestimmen der Statusklasse
                function get_status_class($score) {
                    if ($score >= 80) {
                        return 'good';
                    } elseif ($score >= 50) {
                        return 'ok';
                    } else {
                        return 'poor';
                    }
                }
                ?>
                
                <div class="alenseo-analysis-items">
                    <?php if ($title_message) : ?>
                        <div class="alenseo-analysis-item <?php echo 'status-' . esc_attr(get_status_class($title_score)); ?>">
                            <div class="alenseo-analysis-item-header">
                                <span class="alenseo-analysis-item-title"><?php _e('Titel', 'alenseo'); ?></span>
                                <span class="alenseo-analysis-item-score"><?php echo esc_html($title_score); ?></span>
                            </div>
                            <div class="alenseo-analysis-item-message">
                                <?php echo esc_html($title_message); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($meta_description_message) : ?>
                        <div class="alenseo-analysis-item <?php echo 'status-' . esc_attr(get_status_class($meta_description_score)); ?>">
                            <div class="alenseo-analysis-item-header">
                                <span class="alenseo-analysis-item-title"><?php _e('Meta-Description', 'alenseo'); ?></span>
                                <span class="alenseo-analysis-item-score"><?php echo esc_html($meta_description_score); ?></span>
                            </div>
                            <div class="alenseo-analysis-item-message">
                                <?php echo esc_html($meta_description_message); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($url_message) : ?>
                        <div class="alenseo-analysis-item <?php echo 'status-' . esc_attr(get_status_class($url_score)); ?>">
                            <div class="alenseo-analysis-item-header">
                                <span class="alenseo-analysis-item-title"><?php _e('URL', 'alenseo'); ?></span>
                                <span class="alenseo-analysis-item-score"><?php echo esc_html($url_score); ?></span>
                            </div>
                            <div class="alenseo-analysis-item-message">
                                <?php echo esc_html($url_message); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($content_message) : ?>
                        <div class="alenseo-analysis-item <?php echo 'status-' . esc_attr(get_status_class($content_score)); ?>">
                            <div class="alenseo-analysis-item-header">
                                <span class="alenseo-analysis-item-title"><?php _e('Inhalt', 'alenseo'); ?></span>
                                <span class="alenseo-analysis-item-score"><?php echo esc_html($content_score); ?></span>
                            </div>
                            <div class="alenseo-analysis-item-message">
                                <?php echo esc_html($content_message); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="alenseo-meta-box-footer">
        <div class="alenseo-analyze-button-wrap">
            <button type="button" id="alenseo_analyze_button" class="button" data-post-id="<?php echo esc_attr($post_id); ?>">
                <?php _e('SEO analysieren', 'alenseo'); ?>
            </button>
            
            <?php if ($claude_api_active) : ?>
                <button type="button" id="alenseo_optimize_button" class="button button-primary" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <?php _e('Alles optimieren', 'alenseo'); ?>
                </button>
            <?php else : ?>
                <p class="alenseo-api-notice">
                    <?php _e('Claude API nicht konfiguriert. ', 'alenseo'); ?>
                    <a href="<?php echo admin_url('admin.php?page=alenseo-minimal-settings'); ?>"><?php _e('Jetzt einrichten', 'alenseo'); ?></a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Grundlegende Styles für die Meta-Box */
.alenseo-meta-box {
    margin: -6px -12px -12px;
}

.alenseo-meta-box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 12px;
    border-bottom: 1px solid #eee;
}

.alenseo-meta-box-title h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.alenseo-meta-box-score {
    display: flex;
    align-items: center;
}

.alenseo-score-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    color: #fff;
    font-weight: bold;
    font-size: 12px;
    margin-right: 8px;
}

.alenseo-score-pill.score-good {
    background: #46b450;
}

.alenseo-score-pill.score-ok {
    background: #ffb900;
}

.alenseo-score-pill.score-poor {
    background: #dc3232;
}

.alenseo-score-pill.score-unknown {
    background: #72777c;
}

.alenseo-status {
    display: inline-block;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.alenseo-status.status-good {
    background: #eaf9eb;
    color: #46b450;
}

.alenseo-status.status-ok {
    background: #fff8e5;
    color: #ffb900;
}

.alenseo-status.status-poor {
    background: #fbeaea;
    color: #dc3232;
}

.alenseo-status.status-unknown {
    background: #f1f1f1;
    color: #72777c;
}

.alenseo-meta-box-content {
    padding: 12px;
}

.alenseo-meta-box-section {
    margin-bottom: 16px;
}

.alenseo-meta-box-section:last-child {
    margin-bottom: 0;
}

.alenseo-meta-box-section label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.alenseo-meta-box-section input[type="text"],
.alenseo-meta-box-section textarea {
    width: 100%;
    margin-bottom: 8px;
}

.alenseo-character-count {
    text-align: right;
    color: #666;
    font-size: 12px;
    margin-top: -5px;
    margin-bottom: 8px;
}

.alenseo-meta-box-section h3 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 14px;
    padding-bottom: 6px;
    border-bottom: 1px solid #eee;
}

.alenseo-analysis-items {
    margin-top: 10px;
}

.alenseo-analysis-item {
    border-left: 4px solid #ccc;
    padding: 8px 12px;
    margin-bottom: 10px;
    background: #f9f9f9;
}

.alenseo-analysis-item:last-child {
    margin-bottom: 0;
}

.alenseo-analysis-item.status-good {
    border-left-color: #46b450;
}

.alenseo-analysis-item.status-ok {
    border-left-color: #ffb900;
}

.alenseo-analysis-item.status-poor {
    border-left-color: #dc3232;
}

.alenseo-analysis-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.alenseo-analysis-item-title {
    font-weight: 600;
}

.alenseo-analysis-item-score {
    background: #eee;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
}

.alenseo-analysis-item-message {
    font-size: 12px;
    line-height: 1.4;
}

.alenseo-meta-box-footer {
    padding: 12px;
    border-top: 1px solid #eee;
    background: #f9f9f9;
}

.alenseo-analyze-button-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.alenseo-api-notice {
    margin: 0;
    font-size: 12px;
    font-style: italic;
}

#alenseo_keyword_suggestions {
    margin-top: 10px;
}

#alenseo_keyword_list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px;
    margin-top: 6px;
}

.alenseo-keyword-item {
    padding: 5px 8px;
    background: #f1f1f1;
    border: 1px solid #ddd;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 12px;
}

.alenseo-keyword-item:hover,
.alenseo-keyword-item.selected {
    background: #f0f7fb;
    border-color: #007cba;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Zeichenzähler für Meta-Description
    $('#alenseo_meta_description').on('input', function() {
        var count = $(this).val().length;
        $('#alenseo_meta_description_count').text(count);
        
        // Farbe je nach Zeichenanzahl ändern
        if (count < 120 || count > 160) {
            $('#alenseo_meta_description_count').css('color', '#dc3232');
        } else {
            $('#alenseo_meta_description_count').css('color', '#46b450');
        }
    });
    
    // SEO analysieren Button
    $('#alenseo_analyze_button').on('click', function() {
        var button = $(this);
        var postId = button.data('post-id');
        var keyword = $('#alenseo_keyword').val();
        
        if (!keyword) {
            alert('Bitte gib ein Fokus-Keyword ein, bevor du die Analyse startest.');
            return;
        }
        
        // Button deaktivieren und Text ändern
        var originalText = button.text();
        button.prop('disabled', true).text('Analysiere...');
        
        // Formular speichern (damit das Keyword gespeichert wird)
        $('#publish').click();
        
        // Fortsetzen der Analyse nach dem Speichern des Formulars
        setTimeout(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_analyze_post',
                    post_id: postId,
                    nonce: $('#alenseo_meta_box_nonce').val()
                },
                success: function(response) {
                    // Button zurücksetzen
                    button.prop('disabled', false).text(originalText);
                    
                    if (response.success) {
                        // Erfolg-Nachricht und Seite neu laden
                        alert('Die SEO-Analyse wurde erfolgreich durchgeführt.');
                        location.reload();
                    } else {
                        // Fehler-Nachricht
                        alert('Fehler bei der Analyse: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                    }
                },
                error: function() {
                    // Button zurücksetzen
                    button.prop('disabled', false).text(originalText);
                    
                    // Fehler-Nachricht
                    alert('Es ist ein Fehler bei der Kommunikation mit dem Server aufgetreten.');
                }
            });
        }, 2000);
    });
    
    // Keywords generieren Button
    $('#alenseo_generate_keywords').on('click', function() {
        var button = $(this);
        var postId = $('#alenseo_analyze_button').data('post-id');
        
        // Button deaktivieren und Text ändern
        var originalText = button.text();
        button.prop('disabled', true).text('Generiere...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_claude_generate_keywords',
                post_id: postId,
                nonce: $('#alenseo_meta_box_nonce').val()
            },
            success: function(response) {
                // Button zurücksetzen
                button.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    // Erfolg-Nachricht und Vorschläge anzeigen
                    var keywordList = $('#alenseo_keyword_list');
                    keywordList.empty();
                    
                    $.each(response.data.keywords, function(index, keyword) {
                        var keywordItem = $('<div class="alenseo-keyword-item">' + keyword + '</div>');
                        
                        keywordItem.on('click', function() {
                            $('.alenseo-keyword-item').removeClass('selected');
                            $(this).addClass('selected');
                            $('#alenseo_keyword').val(keyword);
                        });
                        
                        keywordList.append(keywordItem);
                    });
                    
                    $('#alenseo_keyword_suggestions').show();
                } else {
                    // Fehler-Nachricht
                    alert('Fehler beim Generieren von Keywords: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                }
            },
            error: function() {
                // Button zurücksetzen
                button.prop('disabled', false).text(originalText);
                
                // Fehler-Nachricht
                alert('Es ist ein Fehler bei der Kommunikation mit dem Server aufgetreten.');
            }
        });
    });
    
    // Meta-Description generieren Button
    $('#alenseo_generate_description').on('click', function() {
        var button = $(this);
        var postId = $('#alenseo_analyze_button').data('post-id');
        var keyword = $('#alenseo_keyword').val();
        
        if (!keyword) {
            alert('Bitte gib zuerst ein Fokus-Keyword ein.');
            return;
        }
        
        // Button deaktivieren und Text ändern
        var originalText = button.text();
        button.prop('disabled', true).text('Generiere...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_claude_get_optimization_suggestions',
                post_id: postId,
                keyword: keyword,
                optimize_type: 'meta_description',
                nonce: $('#alenseo_meta_box_nonce').val()
            },
            success: function(response) {
                // Button zurücksetzen
                button.prop('disabled', false).text(originalText);
                
                if (response.success && response.data.suggestions && response.data.suggestions.meta_description) {
                    // Erfolg-Nachricht und Meta-Description setzen
                    $('#alenseo_meta_description').val(response.data.suggestions.meta_description).trigger('input');
                } else {
                    // Fehler-Nachricht
                    alert('Fehler beim Generieren der Meta-Description: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                }
            },
            error: function() {
                // Button zurücksetzen
                button.prop('disabled', false).text(originalText);
                
                // Fehler-Nachricht
                alert('Es ist ein Fehler bei der Kommunikation mit dem Server aufgetreten.');
            }
        });
    });
    
    // Alles optimieren Button
    $('#alenseo_optimize_button').on('click', function() {
        var button = $(this);
        var postId = button.data('post-id');
        var keyword = $('#alenseo_keyword').val();
        
        if (!keyword) {
            alert('Bitte gib zuerst ein Fokus-Keyword ein.');
            return;
        }
        
        // Button deaktivieren und Text ändern
        var originalText = button.text();
        button.prop('disabled', true).text('Optimiere...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_claude_get_optimization_suggestions',
                post_id: postId,
                keyword: keyword,
                optimize_title: true,
                optimize_meta_description: true,
                nonce: $('#alenseo_meta_box_nonce').val()
            },
            success: function(response) {
                // Button zurücksetzen
                button.prop('disabled', false).text(originalText);
                
                if (response.success && response.data.suggestions) {
                    // Erfolgsmeldung und Formular speichern
                    alert('Optimierungsvorschläge wurden generiert. Die Vorschläge werden nach dem Speichern in der Detailansicht verfügbar sein.');
                    
                    // Meta-Description übernehmen, wenn vorhanden
                    if (response.data.suggestions.meta_description) {
                        $('#alenseo_meta_description').val(response.data.suggestions.meta_description).trigger('input');
                    }
                    
                    // Formular speichern
                    $('#publish').click();
                } else {
                    // Fehler-Nachricht
                    alert('Fehler beim Generieren der Optimierungsvorschläge: ' + (response.data ? response.data.message : 'Unbekannter Fehler'));
                }
            },
            error: function() {
                // Button zurücksetzen
                button.prop('disabled', false).text(originalText);
                
                // Fehler-Nachricht
                alert('Es ist ein Fehler bei der Kommunikation mit dem Server aufgetreten.');
            }
        });
    });
});
</script>
