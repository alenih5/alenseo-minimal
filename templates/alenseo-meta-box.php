<?php
/**
 * Vereinfachte Meta-Box mit Dashboard-Link für KI-Optimierung
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

$settings = get_option('alenseo_settings', array());
$claude_api_active = !empty($settings['claude_api_key']);

// Daten abrufen
    $keyword = get_post_meta($post_id, '_alenseo_keyword', true);
    $meta_description = get_post_meta($post_id, '_alenseo_meta_description', true);
    $seo_score = get_post_meta($post_id, '_alenseo_seo_score', true);
    $seo_status = get_post_meta($post_id, '_alenseo_seo_status', true);

// Nonce für Sicherheit
wp_nonce_field('alenseo_meta_box', 'alenseo_meta_box_nonce');

// Link zur Detailansicht im Dashboard
$dashboard_url = admin_url('admin.php?page=alenseo-page-detail&post_id=' . $post_id);
?>

<div class="alenseo-meta-box alenseo-meta-box-simplified">
    <!-- SEO-Score & Status -->
    <div class="alenseo-seo-score-container">
        <div class="alenseo-seo-score-circle">
            <span class="alenseo-seo-score"><?php echo esc_html($seo_score); ?></span>
        </div>
        <div class="alenseo-seo-status alenseo-status-<?php echo esc_attr($seo_status); ?>">
                <?php
            switch($seo_status) {
                case 'good':
                    _e('Gut', 'alenseo');
                    break;
                case 'ok':
                    _e('OK', 'alenseo');
                    break;
                case 'poor':
                    _e('Verbesserung nötig', 'alenseo');
                    break;
                default:
                    _e('Unbekannt', 'alenseo');
            }
            ?>
                            </div>
                        </div>
    <!-- Keyword -->
    <div class="alenseo-keyword-section">
        <label for="alenseo_focus_keyword"><strong><?php _e('Fokus-Keyword', 'alenseo'); ?></strong></label>
        <input type="text" id="alenseo_focus_keyword" name="alenseo_keyword" value="<?php echo esc_attr($keyword); ?>" class="regular-text">
    </div>
    <!-- Meta-Description -->
    <div class="alenseo-meta-description-section">
        <label for="alenseo_meta_description"><strong><?php _e('Meta-Beschreibung', 'alenseo'); ?></strong></label>
        <textarea id="alenseo_meta_description" name="alenseo_meta_description" rows="3" class="large-text"><?php echo esc_textarea($meta_description); ?></textarea>
        <div class="alenseo-meta-description-count">
            <span id="alenseo_meta_description_count">0</span> / 160 Zeichen
        </div>
    </div>
    <!-- Dashboard-Link -->
    <div class="alenseo-dashboard-link">
        <a href="<?php echo esc_url($dashboard_url); ?>" class="button button-primary" target="_blank">
            <span class="dashicons dashicons-superhero"></span> <?php _e('Im Dashboard mit KI optimieren', 'alenseo'); ?>
        </a>
    </div>
</div>

<style>
.alenseo-meta-box-simplified {
    padding: 15px;
}
.alenseo-seo-score-container {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}
.alenseo-seo-score-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #f1f1f1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: bold;
    margin-right: 15px;
}
.alenseo-seo-status {
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 3px;
}
.alenseo-status-good { background: #46b450; color: #fff; }
.alenseo-status-ok { background: #ffb900; color: #fff; }
.alenseo-status-poor { background: #dc3232; color: #fff; }
.alenseo-keyword-section,
.alenseo-meta-description-section {
    margin-bottom: 15px;
}
.alenseo-meta-description-count {
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}
.alenseo-dashboard-link {
    margin-top: 20px;
    text-align: right;
}
.alenseo-dashboard-link .button {
    display: flex;
    align-items: center;
    gap: 8px;
}
</style>

<script>
jQuery(document).ready(function($) {
    function updateMetaDescriptionCount() {
        var text = $('#alenseo_meta_description').val();
        $('#alenseo_meta_description_count').text(text.length);
    }
    $('#alenseo_meta_description').on('input', updateMetaDescriptionCount);
    updateMetaDescriptionCount();
});
</script>
