<?php
/**
 * Vereinfachtes Meta-Box-Template mit Verweis auf das zentrale Dashboard
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

// SEO-Score berechnen (falls nicht vorhanden)
$seo_score = isset($seo_data['seo_score']) ? $seo_data['seo_score'] : 0;

// Status basierend auf Score
$status_class = 'unknown';
$status_text = __('Unbekannt', 'alenseo');

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

// Fokus-Keyword abrufen
$focus_keyword = get_post_meta($post->ID, '_alenseo_keyword', true);

// Claude API verfügbar?
$settings = get_option('alenseo_settings', array());
$claude_api_active = !empty($settings['claude_api_key']);

// Dashboard-URL
$dashboard_url = admin_url('admin.php?page=alenseo-optimizer');

?>
<div class="alenseo-meta-box alenseo-meta-box-simplified">
    <div class="alenseo-meta-box-header">
        <div class="alenseo-score-container">
            <div class="alenseo-score-circle <?php echo esc_attr($status_class); ?>">
                <span class="alenseo-score-value"><?php echo esc_html($seo_score); ?></span>
            </div>
            <div class="alenseo-score-label">
                <strong><?php _e('SEO Score', 'alenseo'); ?></strong>
                <span class="status-<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
            </div>
        </div>
        <div class="alenseo-dashboard-link">
            <a href="<?php echo esc_url($dashboard_url); ?>" class="button">
                <span class="dashicons dashicons-superhero"></span>
                <?php _e('Zum SEO-Dashboard', 'alenseo'); ?>
            </a>
        </div>
    </div>
    
    <div class="alenseo-meta-box-content">
        <div class="alenseo-keyword-container">
            <div class="alenseo-field">
                <label for="alenseo_focus_keyword"><?php _e('Fokus-Keyword', 'alenseo'); ?></label>
                <div class="alenseo-keyword-input-group">
                    <input type="text" id="alenseo_focus_keyword" name="alenseo_focus_keyword" value="<?php echo esc_attr($focus_keyword); ?>" placeholder="<?php _e('z.B. WordPress SEO Plugin', 'alenseo'); ?>">
                    <button type="button" class="button" id="alenseo-save-keyword">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </button>
                </div>
            </div>
            
            <?php if ($claude_api_active) : ?>
            <div class="alenseo-keyword-generator">
                <div class="alenseo-kw-generator-trigger">
                    <button type="button" class="button" id="alenseo-generate-keywords" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <?php _e('Keywords generieren', 'alenseo'); ?>
                        <span class="alenseo-api-badge"><?php _e('Claude AI', 'alenseo'); ?></span>
                    </button>
                </div>
                
                <div class="alenseo-kw-generator-content" style="display: none;">
                    <div class="alenseo-loading" style="display: none;">
                        <span class="spinner is-active"></span>
                        <span><?php _e('Keywords werden generiert...', 'alenseo'); ?></span>
                    </div>
                    
                    <div class="alenseo-kw-suggestions" style="display: none;">
                        <h4><?php _e('Keyword-Vorschläge', 'alenseo'); ?></h4>
                        <ul class="alenseo-kw-list"></ul>
                    </div>
                    
                    <div class="alenseo-kw-error" style="display: none;"></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="alenseo-field-info">
                <p class="description">
                    <?php _e('Das Fokus-Keyword ist der Begriff, für den diese Seite optimiert werden soll.', 'alenseo'); ?>
                </p>
            </div>
        </div>
        
        <div class="alenseo-analysis-summary">
            <h4><?php _e('Schnellanalyse', 'alenseo'); ?></h4>
            
            <?php if (empty($focus_keyword)) : ?>
                <div class="alenseo-notice">
                    <p><?php _e('Bitte setze ein Fokus-Keyword, um die SEO-Analyse zu starten.', 'alenseo'); ?></p>
                </div>
            <?php else : ?>
                <?php
                // Vereinfachte Analyse-Elemente
                $analysis_items = array(
                    'title' => array(
                        'label' => __('Titel', 'alenseo'),
                        'score' => isset($seo_data['title_score']) ? $seo_data['title_score'] : 0,
                        'message' => isset($seo_data['title_message']) ? $seo_data['title_message'] : ''
                    ),
                    'content' => array(
                        'label' => __('Inhalt', 'alenseo'),
                        'score' => isset($seo_data['content_score']) ? $seo_data['content_score'] : 0,
                        'message' => isset($seo_data['content_message']) ? $seo_data['content_message'] : ''
                    ),
                    'meta_description' => array(
                        'label' => __('Meta-Beschreibung', 'alenseo'),
                        'score' => isset($seo_data['meta_description_score']) ? $seo_data['meta_description_score'] : 0,
                        'message' => isset($seo_data['meta_description_message']) ? $seo_data['meta_description_message'] : ''
                    )
                );
                
                foreach ($analysis_items as $key => $item) {
                    // Status-Klasse basierend auf Score
                    $item_status = 'unknown';
                    if ($item['score'] >= 80) {
                        $item_status = 'good';
                    } elseif ($item['score'] >= 50) {
                        $item_status = 'ok';
                    } elseif ($item['score'] > 0) {
                        $item_status = 'poor';
                    }
                    
                    // Nur anzeigen, wenn ein Score vorhanden ist
                    if ($item['score'] > 0) :
                    ?>
                    <div class="alenseo-analysis-item">
                        <div class="alenseo-analysis-header <?php echo esc_attr($item_status); ?>">
                            <div class="alenseo-analysis-title"><?php echo esc_html($item['label']); ?></div>
                            <div class="alenseo-analysis-score"><?php echo esc_html($item['score']); ?></div>
                        </div>
                    </div>
                    <?php
                    endif;
                }
                ?>
            <?php endif; ?>
            
            <div class="alenseo-analysis-actions">
                <button type="button" class="button alenseo-analyze-button" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <span class="dashicons dashicons-visibility"></span> <?php _e('Neu analysieren', 'alenseo'); ?>
                </button>
                
                <a href="<?php echo esc_url($dashboard_url); ?>" class="button button-primary alenseo-optimize-button">
                    <span class="dashicons dashicons-superhero"></span> <?php _e('Im Dashboard optimieren', 'alenseo'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Ergänzende Stile für die vereinfachte Meta-Box */
.alenseo-meta-box-simplified .alenseo-meta-box-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.alenseo-dashboard-link {
    margin-left: auto;
}

.alenseo-dashboard-link .button {
    display: flex;
    align-items: center;
}

.alenseo-dashboard-link .dashicons {
    margin-right: 5px;
}

.alenseo-keyword-input-group {
    display: flex;
    align-items: center;
}

.alenseo-keyword-input-group input {
    flex: 1;
    margin-right: 10px;
}

.alenseo-keyword-input-group .button {
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.alenseo-analysis-summary {
    margin-top: 20px;
}

.alenseo-analysis-actions {
    margin-top: 15px;
    display: flex;
    justify-content: space-between;
}

.alenseo-api-badge {
    display: inline-block;
    background-color: #8458B3;
    color: white;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 8px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Fokus-Keyword speichern
    $('#alenseo-save-keyword').on('click', function() {
        var keyword = $('#alenseo_focus_keyword').val();
        var postId = <?php echo esc_js($post->ID); ?>;
        var button = $(this);
        
        // Button-Status
        var originalHtml = button.html();
        button.html('<span class="dashicons dashicons-update"></span>').prop('disabled', true);
        
        // AJAX-Anfrage
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_save_focus_keyword',
                post_id: postId,
                keyword: keyword,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                // Button-Status zurücksetzen
                button.html(originalHtml).prop('disabled', false);
                
                if (response.success) {
                    // Kurze Erfolgsanzeige
                    button.html('<span class="dashicons dashicons-yes"></span>');
                    setTimeout(function() {
                        button.html(originalHtml);
                    }, 1500);
                } else {
                    // Fehlerbehandlung
                    alert(response.data.message || 'Fehler beim Speichern des Keywords.');
                }
            },
            error: function() {
                // Button-Status zurücksetzen
                button.html(originalHtml).prop('disabled', false);
                
                // Fehlerbehandlung
                alert('Fehler bei der Verbindung zum Server.');
            }
        });
    });
    
    // Keywords generieren
    $('#alenseo-generate-keywords').on('click', function() {
        var postId = $(this).data('post-id');
        var generatorContent = $('.alenseo-kw-generator-content');
        var loadingIndicator = $('.alenseo-loading');
        var suggestionsContainer = $('.alenseo-kw-suggestions');
        var errorContainer = $('.alenseo-kw-error');
        var suggestionsList = $('.alenseo-kw-list');
        
        // Content anzeigen/verstecken
        if (generatorContent.is(':visible')) {
            generatorContent.slideUp();
        } else {
            generatorContent.slideDown();
            
            // UI-Status
            loadingIndicator.show();
            suggestionsContainer.hide();
            errorContainer.hide();
            
            // AJAX-Anfrage
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_enhanced_generate_keywords',
                    post_id: postId,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    // Loading ausblenden
                    loadingIndicator.hide();
                    
                    if (response.success && response.data.keywords) {
                        // Keywords anzeigen
                        displayKeywords(response.data.keywords);
                    } else {
                        // Fehlerbehandlung
                        errorContainer.html('<p>' + (response.data.message || 'Fehler beim Generieren der Keywords.') + '</p>');
                        errorContainer.show();
                    }
                },
                error: function() {
                    // Loading ausblenden
                    loadingIndicator.hide();
                    
                    // Fehlerbehandlung
                    errorContainer.html('<p>Fehler bei der Verbindung zum Server.</p>');
                    errorContainer.show();
                }
            });
        }
        
        // Keywords anzeigen
        function displayKeywords(keywords) {
            // Liste leeren
            suggestionsList.empty();
            
            // Keywords hinzufügen
            $.each(keywords, function(index, keyword) {
                var keywordText = keyword.keyword || keyword;
                var score = keyword.score ? ' <small>(' + keyword.score + ')</small>' : '';
                
                var listItem = $('<li class="alenseo-kw-item"></li>');
                listItem.html('<span class="alenseo-kw-text">' + keywordText + score + '</span> <button type="button" class="button button-small alenseo-kw-select">Auswählen</button>');
                
                // Keyword auswählen
                listItem.find('.alenseo-kw-select').on('click', function() {
                    $('#alenseo_focus_keyword').val(keywordText);
                    generatorContent.slideUp();
                    
                    // Keyword automatisch speichern
                    $('#alenseo-save-keyword').trigger('click');
                });
                
                suggestionsList.append(listItem);
            });
            
            // Vorschläge anzeigen
            suggestionsContainer.show();
        }
    });
    
    // Neu analysieren
    $('.alenseo-analyze-button').on('click', function() {
        var postId = $(this).data('post-id');
        var button = $(this);
        
        // Button-Status
        var originalHtml = button.html();
        button.html('<span class="dashicons dashicons-update"></span> ' + '<?php _e('Analysiere...', 'alenseo'); ?>').prop('disabled', true);
        
        // AJAX-Anfrage
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_analyze_post',
                post_id: postId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                // Button-Status zurücksetzen
                button.html(originalHtml).prop('disabled', false);
                
                if (response.success) {
                    // Seite neu laden, um Ergebnisse anzuzeigen
                    location.reload();
                } else {
                    // Fehlerbehandlung
                    alert(response.data.message || 'Fehler bei der Analyse.');
                }
            },
            error: function() {
                // Button-Status zurücksetzen
                button.html(originalHtml).prop('disabled', false);
                
                // Fehlerbehandlung
                alert('Fehler bei der Verbindung zum Server.');
            }
        });
    });
});
</script>
