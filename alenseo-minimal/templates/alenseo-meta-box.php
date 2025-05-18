<?php
/**
 * Template für die Meta-Box mit Claude API-Integration
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

?>
<div class="alenseo-meta-box">
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
    </div>
    
    <div class="alenseo-meta-box-content">
        <div class="alenseo-tabs">
            <div class="alenseo-tab active" data-tab="keyword"><?php _e('Fokus-Keyword', 'alenseo'); ?></div>
            <div class="alenseo-tab" data-tab="analysis"><?php _e('SEO-Analyse', 'alenseo'); ?></div>
            <?php if ($claude_api_active) : ?>
            <div class="alenseo-tab" data-tab="suggestions"><?php _e('Optimierungsvorschläge', 'alenseo'); ?></div>
            <?php endif; ?>
        </div>
        
        <div class="alenseo-tab-content active" id="alenseo-tab-keyword">
            <div class="alenseo-field">
                <label for="alenseo_focus_keyword"><?php _e('Fokus-Keyword', 'alenseo'); ?></label>
                <input type="text" id="alenseo_focus_keyword" name="alenseo_focus_keyword" value="<?php echo esc_attr($focus_keyword); ?>" placeholder="<?php _e('z.B. WordPress SEO Plugin', 'alenseo'); ?>">
            </div>
            
            <div class="alenseo-keyword-generator">
                <div class="alenseo-kw-generator-trigger">
                    <button type="button" class="button" id="alenseo-generate-keywords">
                        <?php _e('Keywords generieren', 'alenseo'); ?>
                    </button>
                    <?php if ($claude_api_active) : ?>
                    <span class="alenseo-api-badge"><?php _e('mit Claude AI', 'alenseo'); ?></span>
                    <?php endif; ?>
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
            
            <div class="alenseo-field-info">
                <p class="description">
                    <?php _e('Das Fokus-Keyword ist der Begriff, für den diese Seite optimiert werden soll. Es sollte in Titel, URL, Überschriften und Inhalt verwendet werden.', 'alenseo'); ?>
                </p>
            </div>
        </div>
        
        <div class="alenseo-tab-content" id="alenseo-tab-analysis">
            <?php
            // Analyse-Elemente
            $analysis_items = array(
                'title' => array(
                    'label' => __('Titel', 'alenseo'),
                    'score' => isset($seo_data['title_score']) ? $seo_data['title_score'] : 0,
                    'message' => isset($seo_data['title_message']) ? $seo_data['title_message'] : __('Kein Fokus-Keyword gesetzt.', 'alenseo')
                ),
                'content' => array(
                    'label' => __('Inhalt', 'alenseo'),
                    'score' => isset($seo_data['content_score']) ? $seo_data['content_score'] : 0,
                    'message' => isset($seo_data['content_message']) ? $seo_data['content_message'] : __('Kein Fokus-Keyword gesetzt.', 'alenseo')
                ),
                'url' => array(
                    'label' => __('URL', 'alenseo'),
                    'score' => isset($seo_data['url_score']) ? $seo_data['url_score'] : 0,
                    'message' => isset($seo_data['url_message']) ? $seo_data['url_message'] : __('Kein Fokus-Keyword gesetzt.', 'alenseo')
                ),
                'meta_description' => array(
                    'label' => __('Meta-Beschreibung', 'alenseo'),
                    'score' => isset($seo_data['meta_description_score']) ? $seo_data['meta_description_score'] : 0,
                    'message' => isset($seo_data['meta_description_message']) ? $seo_data['meta_description_message'] : __('Kein Fokus-Keyword gesetzt.', 'alenseo')
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
                
                ?>
                <div class="alenseo-analysis-item">
                    <div class="alenseo-analysis-header <?php echo esc_attr($item_status); ?>">
                        <div class="alenseo-analysis-title"><?php echo esc_html($item['label']); ?></div>
                        <div class="alenseo-analysis-score"><?php echo esc_html($item['score']); ?></div>
                    </div>
                    <div class="alenseo-analysis-message">
                        <?php echo wp_kses_post($item['message']); ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        
        <?php if ($claude_api_active) : ?>
        <div class="alenseo-tab-content" id="alenseo-tab-suggestions">
            <div class="alenseo-suggestions-container">
                <?php if (empty($focus_keyword)) : ?>
                    <div class="alenseo-notice">
                        <p><?php _e('Bitte setze zuerst ein Fokus-Keyword, um Optimierungsvorschläge zu erhalten.', 'alenseo'); ?></p>
                    </div>
                <?php else : ?>
                    <div class="alenseo-suggestions-loading" style="display: none;">
                        <span class="spinner is-active"></span>
                        <span><?php _e('Optimierungsvorschläge werden generiert...', 'alenseo'); ?></span>
                    </div>
                    
                    <div class="alenseo-suggestions-content" style="display: none;">
                        <div class="alenseo-suggestion-section" id="alenseo-title-suggestion">
                            <h4><?php _e('Titel-Optimierung', 'alenseo'); ?></h4>
                            <div class="alenseo-suggestion-text"></div>
                            <button type="button" class="button alenseo-apply-suggestion" data-target="title">
                                <?php _e('Anwenden', 'alenseo'); ?>
                            </button>
                        </div>
                        
                        <div class="alenseo-suggestion-section" id="alenseo-meta-suggestion">
                            <h4><?php _e('Meta-Beschreibung', 'alenseo'); ?></h4>
                            <div class="alenseo-suggestion-text"></div>
                            <button type="button" class="button alenseo-apply-suggestion" data-target="meta">
                                <?php _e('Anwenden', 'alenseo'); ?>
                            </button>
                        </div>
                        
                        <div class="alenseo-suggestion-section" id="alenseo-content-suggestions">
                            <h4><?php _e('Inhalts-Optimierungen', 'alenseo'); ?></h4>
                            <ul class="alenseo-content-suggestion-list"></ul>
                        </div>
                    </div>
                    
                    <div class="alenseo-suggestion-error" style="display: none;"></div>
                    
                    <div class="alenseo-suggestions-actions">
                        <button type="button" class="button button-primary" id="alenseo-generate-suggestions">
                            <?php _e('Optimierungsvorschläge generieren', 'alenseo'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>