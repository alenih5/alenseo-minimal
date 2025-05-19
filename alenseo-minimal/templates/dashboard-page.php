<?php
/**
 * Template für ein minimales Dashboard - als Fallback wenn das reguläre Dashboard fehlt
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

// Zugriff auf die Dashboard-Klasse
$dashboard = $this;

// Versuche, Inhaltsdaten zu laden, mit Fehlerbehandlung
try {
    $contents_data = $dashboard->get_contents_for_dashboard();
    $contents = isset($contents_data['contents']) ? $contents_data['contents'] : array();
    $total = isset($contents_data['total']) ? $contents_data['total'] : 0;
    
    // Statistiken abrufen
    $stats = $dashboard->get_content_statistics();
} catch (Exception $e) {
    if (function_exists('alenseo_log')) {
        alenseo_log("Alenseo Dashboard Template Fehler: " . $e->getMessage());
    }
    
    // Standardwerte für Fehlerfall
    $contents = array();
    $total = 0;
    $stats = array(
        'total' => 0,
        'with_keyword' => 0,
        'optimized' => 0,
        'partially_optimized' => 0,
        'needs_optimization' => 0,
        'avg_score' => 0
    );
}

?>
<div class="wrap alenseo-optimizer-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-superhero"></span>
        <?php _e('Alenseo SEO-Optimierung', 'alenseo'); ?>
    </h1>
    
    <?php
    // Claude API verfügbar?
    $settings = get_option('alenseo_settings', array());
    $claude_api_active = !empty($settings['claude_api_key']);
    
    if (!$claude_api_active) : 
    ?>
    <div class="notice notice-warning">
        <p>
            <?php _e('Claude API ist nicht konfiguriert. Um die KI-gestützten Optimierungsfunktionen zu nutzen, bitte', 'alenseo'); ?>
            <a href="<?php echo admin_url('admin.php?page=alenseo-minimal-settings'); ?>"><?php _e('konfiguriere den API-Schlüssel in den Einstellungen', 'alenseo'); ?></a>.
        </p>
    </div>
    <?php endif; ?>
    
    <div class="alenseo-dashboard-header">
        <div class="alenseo-stats-overview">
            <div class="alenseo-stat-box alenseo-stat-box-primary">
                <div class="alenseo-stat-box-header">
                    <h3><?php _e('SEO-Score', 'alenseo'); ?></h3>
                </div>
                <div class="alenseo-stat-box-content">
                    <div class="alenseo-stat-circle" data-percentage="<?php echo esc_attr($stats['avg_score']); ?>">
                        <span class="alenseo-stat-value"><?php echo esc_html($stats['avg_score']); ?></span>
                    </div>
                    <div class="alenseo-stat-description">
                        <?php _e('Durchschnittlicher Score', 'alenseo'); ?>
                    </div>
                </div>
            </div>
            
            <div class="alenseo-stat-box">
                <div class="alenseo-stat-box-header">
                    <h3><?php _e('Status', 'alenseo'); ?></h3>
                </div>
                <div class="alenseo-stat-box-content">
                    <div class="alenseo-stat-bars">
                        <div class="alenseo-stat-bar">
                            <div class="alenseo-stat-bar-label">
                                <span class="status-good"></span>
                                <?php _e('Gut optimiert', 'alenseo'); ?>
                            </div>
                            <div class="alenseo-stat-bar-progress">
                                <div class="alenseo-stat-bar-fill alenseo-bar-good" style="width: <?php echo esc_attr($stats['total'] > 0 ? ($stats['optimized'] / $stats['total'] * 100) : 0); ?>%"></div>
                            </div>
                            <div class="alenseo-stat-bar-value"><?php echo esc_html($stats['optimized']); ?></div>
                        </div>
                        
                        <div class="alenseo-stat-bar">
                            <div class="alenseo-stat-bar-label">
                                <span class="status-ok"></span>
                                <?php _e('Teilweise optimiert', 'alenseo'); ?>
                            </div>
                            <div class="alenseo-stat-bar-progress">
                                <div class="alenseo-stat-bar-fill alenseo-bar-ok" style="width: <?php echo esc_attr($stats['total'] > 0 ? ($stats['partially_optimized'] / $stats['total'] * 100) : 0); ?>%"></div>
                            </div>
                            <div class="alenseo-stat-bar-value"><?php echo esc_html($stats['partially_optimized']); ?></div>
                        </div>
                        
                        <div class="alenseo-stat-bar">
                            <div class="alenseo-stat-bar-label">
                                <span class="status-poor"></span>
                                <?php _e('Optimierung nötig', 'alenseo'); ?>
                            </div>
                            <div class="alenseo-stat-bar-progress">
                                <div class="alenseo-stat-bar-fill alenseo-bar-poor" style="width: <?php echo esc_attr($stats['total'] > 0 ? ($stats['needs_optimization'] / $stats['total'] * 100) : 0); ?>%"></div>
                            </div>
                            <div class="alenseo-stat-bar-value"><?php echo esc_html($stats['needs_optimization']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alenseo-stat-box">
                <div class="alenseo-stat-box-header">
                    <h3><?php _e('Fortschritt', 'alenseo'); ?></h3>
                </div>
                <div class="alenseo-stat-box-content">
                    <div class="alenseo-progress-stats">
                        <div class="alenseo-progress-item">
                            <div class="alenseo-progress-label"><?php _e('Mit Keywords', 'alenseo'); ?></div>
                            <div class="alenseo-progress-bar">
                                <div class="alenseo-progress-fill" style="width: <?php echo esc_attr($stats['total'] > 0 ? ($stats['with_keyword'] / $stats['total'] * 100) : 0); ?>%"></div>
                            </div>
                            <div class="alenseo-progress-value"><?php echo esc_html($stats['with_keyword']); ?> / <?php echo esc_html($stats['total']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter- und Suchleiste -->
    <div class="alenseo-bulk-actions-bar">
        <div class="alenseo-search-filter">
            <div class="alenseo-filter-group">
                <label for="alenseo-filter-post-type"><?php _e('Typ:', 'alenseo'); ?></label>
                <select id="alenseo-filter-post-type">
                    <option value=""><?php _e('Alle', 'alenseo'); ?></option>
                    <?php 
                    // Post-Typen für den Filter abrufen
                    $post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
                    $post_type_objects = array();
                    
                    foreach ($post_types as $post_type) {
                        $object = get_post_type_object($post_type);
                        if ($object) {
                            echo '<option value="' . esc_attr($post_type) . '">' . esc_html($object->labels->singular_name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="alenseo-filter-group">
                <label for="alenseo-filter-status"><?php _e('Status:', 'alenseo'); ?></label>
                <select id="alenseo-filter-status">
                    <option value=""><?php _e('Alle', 'alenseo'); ?></option>
                    <option value="optimized"><?php _e('Gut optimiert', 'alenseo'); ?></option>
                    <option value="partially_optimized"><?php _e('Teilweise optimiert', 'alenseo'); ?></option>
                    <option value="needs_optimization"><?php _e('Optimierung nötig', 'alenseo'); ?></option>
                </select>
            </div>
            
            <div class="alenseo-filter-group">
                <label for="alenseo-filter-keyword"><?php _e('Keyword:', 'alenseo'); ?></label>
                <select id="alenseo-filter-keyword">
                    <option value=""><?php _e('Alle', 'alenseo'); ?></option>
                    <option value="with"><?php _e('Mit Keyword', 'alenseo'); ?></option>
                    <option value="without"><?php _e('Ohne Keyword', 'alenseo'); ?></option>
                </select>
            </div>
            
            <div class="alenseo-search-group">
                <input type="text" id="alenseo-search-input" placeholder="<?php esc_attr_e('Suchen...', 'alenseo'); ?>">
                <button type="button" id="alenseo-search-button" class="button"><?php _e('Suchen', 'alenseo'); ?></button>
            </div>
        </div>
        
        <!-- Massenaktionen -->
        <div class="alenseo-bulk-actions">
            <div class="alenseo-bulk-actions-select">
                <select id="alenseo-bulk-action">
                    <option value=""><?php _e('Massenaktionen', 'alenseo'); ?></option>
                    <option value="generate_keywords"><?php _e('Keywords generieren', 'alenseo'); ?></option>
                    <option value="optimize_titles"><?php _e('Titel optimieren', 'alenseo'); ?></option>
                    <option value="optimize_meta_descriptions"><?php _e('Meta-Beschreibungen optimieren', 'alenseo'); ?></option>
                    <option value="optimize_content"><?php _e('Inhalte optimieren', 'alenseo'); ?></option>
                    <option value="optimize_all"><?php _e('Komplettoptimierung', 'alenseo'); ?></option>
                </select>
                <button type="button" id="alenseo-apply-bulk-action" class="button"><?php _e('Anwenden', 'alenseo'); ?></button>
            </div>
            
            <div class="alenseo-selection-info" style="display: none;">
                <span id="alenseo-selected-count">0</span> <?php _e('Elemente ausgewählt', 'alenseo'); ?>
                <button type="button" id="alenseo-clear-selection" class="button button-link-delete"><?php _e('Auswahl aufheben', 'alenseo'); ?></button>
            </div>
        </div>
    </div>
    
    <!-- Fortschrittsanzeige für Massenaktionen -->
    <div id="alenseo-progress-bar" class="alenseo-progress-bar-container" style="display: none;">
        <div class="alenseo-progress-bar">
            <div class="alenseo-progress-fill" style="width: 0%"></div>
        </div>
        <div class="alenseo-progress-text"><?php _e('Optimiere...', 'alenseo'); ?> <span id="alenseo-progress-current">0</span>/<span id="alenseo-progress-total">0</span></div>
    </div>
    
    <!-- Inhaltsliste -->
    <div class="alenseo-contents-table-container">
        <table class="alenseo-contents-table widefat striped">
            <thead>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" id="alenseo-select-all">
                    </th>
                    <th class="column-title"><?php _e('Titel', 'alenseo'); ?></th>
                    <th class="column-type"><?php _e('Typ', 'alenseo'); ?></th>
                    <th class="column-keyword"><?php _e('Fokus-Keyword', 'alenseo'); ?></th>
                    <th class="column-score"><?php _e('SEO-Score', 'alenseo'); ?></th>
                    <th class="column-status"><?php _e('Status', 'alenseo'); ?></th>
                    <th class="column-actions"><?php _e('Aktionen', 'alenseo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contents)) : ?>
                    <tr>
                        <td colspan="7"><?php _e('Keine Inhalte gefunden.', 'alenseo'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($contents as $content) : ?>
                        <tr class="alenseo-content-row" 
                            data-id="<?php echo esc_attr($content['id']); ?>"
                            data-type="<?php echo esc_attr($content['type']); ?>"
                            data-status="<?php echo esc_attr($content['seo_status']); ?>"
                            data-has-keyword="<?php echo empty($content['focus_keyword']) ? 'without' : 'with'; ?>">
                            <td class="check-column">
                                <input type="checkbox" class="alenseo-select-content" value="<?php echo esc_attr($content['id']); ?>">
                            </td>
                            <td class="column-title">
                                <strong>
                                    <a href="<?php echo esc_url($content['edit_url']); ?>" class="row-title">
                                        <?php echo esc_html($content['title']); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo esc_url($content['edit_url']); ?>"><?php _e('Bearbeiten', 'alenseo'); ?></a> | 
                                    </span>
                                    <span class="view">
                                        <a href="<?php echo esc_url($content['view_url']); ?>" target="_blank"><?php _e('Ansehen', 'alenseo'); ?></a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-type"><?php echo esc_html($content['type']); ?></td>
                            <td class="column-keyword">
                                <?php if (!empty($content['focus_keyword'])) : ?>
                                    <span class="alenseo-keyword-badge"><?php echo esc_html($content['focus_keyword']); ?></span>
                                <?php else : ?>
                                    <span class="alenseo-no-keyword"><?php _e('Nicht gesetzt', 'alenseo'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-score">
                                <div class="alenseo-score-pill <?php echo esc_attr($dashboard->get_score_class($content['seo_score'])); ?>">
                                    <?php echo esc_html($content['seo_score']); ?>
                                </div>
                            </td>
                            <td class="column-status">
                                <?php echo $dashboard->get_status_html($content['seo_status']); ?>
                            </td>
                            <td class="column-actions">
                                <div class="alenseo-actions">
                                    <?php if ($claude_api_active) : ?>
                                        <button type="button" class="button button-small alenseo-action-optimize" data-id="<?php echo esc_attr($content['id']); ?>" title="<?php esc_attr_e('Optimieren', 'alenseo'); ?>">
                                            <span class="dashicons dashicons-superhero"></span>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="button button-small alenseo-action-analyze" data-id="<?php echo esc_attr($content['id']); ?>" title="<?php esc_attr_e('Analysieren', 'alenseo'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Optimierungsdialog -->
<div id="alenseo-optimization-dialog" class="alenseo-dialog" style="display: none;">
    <div class="alenseo-dialog-content">
        <div class="alenseo-dialog-header">
            <h2><?php _e('Content optimieren', 'alenseo'); ?></h2>
            <button type="button" class="alenseo-dialog-close">&times;</button>
        </div>
        <div class="alenseo-dialog-body">
            <div class="alenseo-dialog-section alenseo-keywords-section">
                <h3><?php _e('Fokus-Keyword', 'alenseo'); ?></h3>
                <div class="alenseo-current-keyword">
                    <p><?php _e('Aktuelles Keyword:', 'alenseo'); ?> <span id="alenseo-current-keyword-value"><?php _e('Nicht gesetzt', 'alenseo'); ?></span></p>
                    <button type="button" class="button" id="alenseo-generate-keywords">
                        <span class="dashicons dashicons-update"></span> <?php _e('Keywords generieren', 'alenseo'); ?>
                    </button>
                </div>
                <div class="alenseo-keyword-suggestions" style="display: none;">
                    <h4><?php _e('Vorschläge:', 'alenseo'); ?></h4>
                    <div class="alenseo-keyword-list"></div>
                </div>
                <div class="alenseo-dialog-loader alenseo-keywords-loader" style="display: none;">
                    <span class="spinner is-active"></span>
                    <span><?php _e('Keywords werden generiert...', 'alenseo'); ?></span>
                </div>
            </div>
            
            <div class="alenseo-dialog-section alenseo-optimization-section">
                <h3><?php _e('Optimierung', 'alenseo'); ?></h3>
                <div class="alenseo-optimization-options">
                    <p><?php _e('Wähle die Elemente, die optimiert werden sollen:', 'alenseo'); ?></p>
                    <div class="alenseo-optimization-checkbox-grid">
                        <label>
                            <input type="checkbox" name="optimize_title" checked> <?php _e('Titel', 'alenseo'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="optimize_meta_description" checked> <?php _e('Meta-Description', 'alenseo'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="optimize_content" checked> <?php _e('Inhaltsvorschläge', 'alenseo'); ?>
                        </label>
                    </div>
                    <div class="alenseo-optimization-actions">
                        <button type="button" class="button button-primary" id="alenseo-start-optimization">
                            <span class="dashicons dashicons-superhero"></span> <?php _e('Jetzt optimieren', 'alenseo'); ?>
                        </button>
                    </div>
                    <div class="alenseo-dialog-loader alenseo-optimizer-loader" style="display: none;">
                        <span class="spinner is-active"></span>
                        <span><?php _e('Optimierungsvorschläge werden generiert...', 'alenseo'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="alenseo-dialog-section alenseo-results-section" style="display: none;">
                <h3><?php _e('Optimierungsergebnisse', 'alenseo'); ?></h3>
                <div class="alenseo-results-container">
                    <!-- Hier werden die Ergebnisse dynamisch eingefügt -->
                </div>
            </div>
        </div>
        <div class="alenseo-dialog-footer">
            <button type="button" class="button alenseo-dialog-close"><?php _e('Schließen', 'alenseo'); ?></button>
        </div>
    </div>
</div>

<!-- Füge CSS-Stile inline hinzu, falls die CSS-Datei nicht geladen werden konnte -->
<style>
.alenseo-optimizer-wrap {
    margin: 20px 0;
}

.alenseo-optimizer-wrap h1 {
    display: flex;
    align-items: center;
}

.alenseo-optimizer-wrap h1 .dashicons {
    margin-right: 10px;
    font-size: 26px;
    width: 26px;
    height: 26px;
}

.alenseo-dashboard-header {
    margin: 20px 0;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    border-radius: 3px;
}

.alenseo-stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.alenseo-stat-box {
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
    overflow: hidden;
}

.alenseo-stat-box-primary {
    background: #fff;
    border-color: #007cba;
}

.alenseo-stat-box-header {
    padding: 10px 15px;
    background: #f0f0f0;
    border-bottom: 1px solid #e5e5e5;
}

.alenseo-stat-box-primary .alenseo-stat-box-header {
    background: #007cba;
    color: #fff;
}

.alenseo-stat-box-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.alenseo-stat-box-content {
    padding: 15px;
}

.alenseo-stat-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #f0f0f0;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 8px solid #ddd;
    position: relative;
}

.alenseo-stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #333;
}

.alenseo-stat-description {
    text-align: center;
    color: #666;
}

.alenseo-stat-bars {
    margin-top: 10px;
}

.alenseo-stat-bar {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.alenseo-stat-bar-label {
    flex: 0 0 150px;
    display: flex;
    align-items: center;
}

.alenseo-stat-bar-label span {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.alenseo-stat-bar-label .status-good {
    background-color: #46b450;
}

.alenseo-stat-bar-label .status-ok {
    background-color: #ffb900;
}

.alenseo-stat-bar-label .status-poor {
    background-color: #dc3232;
}

.alenseo-stat-bar-progress {
    flex: 1;
    height: 10px;
    background: #f0f0f0;
    border-radius: 5px;
    overflow: hidden;
    margin: 0 10px;
}

.alenseo-stat-bar-fill {
    height: 100%;
    border-radius: 5px;
    transition: width 0.3s ease;
}

.alenseo-bar-good {
    background-color: #46b450;
}

.alenseo-bar-ok {
    background-color: #ffb900;
}

.alenseo-bar-poor {
    background-color: #dc3232;
}

.alenseo-stat-bar-value {
    flex: 0 0 40px;
    text-align: right;
    font-weight: 600;
}

.alenseo-progress-stats {
    margin-top: 10px;
}

.alenseo-progress-item {
    margin-bottom: 15px;
}

.alenseo-progress-label {
    margin-bottom: 5px;
    font-weight: 500;
}

.alenseo-progress-bar {
    height: 10px;
    background: #f0f0f0;
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 5px;
}

.alenseo-progress-fill {
    height: 100%;
    background-color: #007cba;
    border-radius: 5px;
    transition: width 0.3s ease;
}

.alenseo-progress-value {
    text-align: right;
    font-size: 12px;
    color: #666;
}

.alenseo-bulk-actions-bar {
    margin-top: 20px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 10px 15px;
    border-radius: 3px;
}

.alenseo-search-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 15px;
}

.alenseo-filter-group, .alenseo-search-group {
    display: flex;
    align-items: center;
}

.alenseo-filter-group label {
    margin-right: 5px;
}

.alenseo-bulk-actions {
    display: flex;
    align-items: center;
    margin-top: 10px;
}

.alenseo-bulk-actions-select {
    display: flex;
    align-items: center;
}

.alenseo-bulk-actions-select select {
    margin-right: 5px;
}

.alenseo-selection-info {
    margin-left: 15px;
    display: flex;
    align-items: center;
}

.alenseo-progress-bar-container {
    margin: 15px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
}

.alenseo-progress-bar {
    height: 15px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}

.alenseo-progress-fill {
    height: 100%;
    background-color: #007cba;
    border-radius: 10px;
    transition: width 0.3s ease;
}

.alenseo-progress-text {
    text-align: center;
    font-size: 13px;
    color: #666;
}

.alenseo-contents-table-container {
    margin-top: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
    overflow: hidden;
}

.alenseo-contents-table {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
}

.alenseo-contents-table th {
    text-align: left;
    padding: 8px 10px;
}

.alenseo-contents-table th.check-column {
    width: 30px;
}

.alenseo-contents-table th.column-title {
    width: 30%;
}

.alenseo-contents-table th.column-type {
    width: 10%;
}

.alenseo-contents-table th.column-keyword {
    width: 20%;
}

.alenseo-contents-table th.column-score,
.alenseo-contents-table th.column-status {
    width: 15%;
}

.alenseo-contents-table th.column-actions {
    width: 10%;
}

.alenseo-contents-table td {
    padding: 12px 10px;
    vertical-align: middle;
}

.alenseo-keyword-badge {
    display: inline-block;
    padding: 3px 8px;
    background-color: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 12px;
    font-size: 12px;
    color: #333;
}

.alenseo-no-keyword {
    color: #999;
    font-style: italic;
    font-size: 12px;
}

.alenseo-score-pill {
    display: inline-block;
    width: 36px;
    height: 36px;
    line-height: 36px;
    text-align: center;
    border-radius: 50%;
    background: #f0f0f0;
    font-weight: bold;
    color: #fff;
}

.alenseo-score-pill.score-good {
    background-color: #46b450;
}

.alenseo-score-pill.score-ok {
    background-color: #ffb900;
}

.alenseo-score-pill.score-poor {
    background-color: #dc3232;
}

.alenseo-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.alenseo-status-good {
    background-color: #eaf9eb;
    color: #46b450;
}

.alenseo-status-ok {
    background-color: #fff8e5;
    color: #ffb900;
}

.alenseo-status-poor {
    background-color: #fbeaea;
    color: #dc3232;
}

.alenseo-actions {
    display: flex;
    gap: 5px;
}

.alenseo-actions button {
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.alenseo-actions .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.alenseo-dialog {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.alenseo-dialog-content {
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    background: #fff;
    border-radius: 5px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
}

.alenseo-dialog-header {
    padding: 15px 20px;
    background: #f9f9f9;
    border-bottom: 1px solid #e5e5e5;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.alenseo-dialog-header h2 {
    margin: 0;
    font-size: 18px;
}

.alenseo-dialog-close {
    cursor: pointer;
    background: none;
    border: none;
    font-size: 24px;
    line-height: 1;
    padding: 0;
    color: #666;
}

.alenseo-dialog-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.alenseo-dialog-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e5e5;
}

.alenseo-dialog-section:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.alenseo-dialog-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
    color: #23282d;
}

.alenseo-current-keyword {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
    padding: 10px 15px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
}

.alenseo-keyword-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.alenseo-keyword-item {
    padding: 8px 12px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.alenseo-keyword-item:hover,
.alenseo-keyword-item.selected {
    background: #f0f7fb;
    border-color: #007cba;
}

.alenseo-keyword-text {
    display: block;
    font-weight: 500;
}

.alenseo-keyword-text small {
    font-weight: normal;
    color: #666;
}

.alenseo-optimization-options {
    margin-top: 15px;
}

.alenseo-optimization-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin: 15px 0;
}

.alenseo-optimization-actions {
    margin-top: 20px;
    text-align: center;
}

.alenseo-dialog-loader {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 15px 0;
}

.alenseo-dialog-loader .spinner {
    float: none;
    margin: 0 10px 0 0;
}

.alenseo-result-section {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
}

.alenseo-result-section h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #23282d;
}

.alenseo-result-content {
    padding: 10px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 3px;
    margin-bottom: 10px;
}

.alenseo-content-suggestions {
    margin: 0;
    padding-left: 20px;
}

.alenseo-content-suggestions li {
    margin-bottom: 8px;
}

.alenseo-dialog-footer {
    padding: 15px 20px;
    background: #f9f9f9;
    border-top: 1px solid #e5e5e5;
    text-align: right;
}

@keyframes rotation {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.dashicons-update {
    animation: rotation 2s infinite linear;
}
</style>
