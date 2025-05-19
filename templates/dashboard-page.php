<?php
/**
 * Dashboard-Template für Alenseo SEO
 *
 * Zeigt eine Übersicht aller Seiten und Beiträge mit SEO-Status an
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

// Globale Dashboard-Instanz für Helper-Funktionen sicherstellen
global $alenseo_dashboard;
if (!isset($alenseo_dashboard) || !is_a($alenseo_dashboard, 'Alenseo_Dashboard')) {
    $alenseo_dashboard = new Alenseo_Dashboard();
}

// Aktuelle Filter abrufen
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
$filter_search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Übersichtsdaten abrufen
$overview_data = $alenseo_dashboard->get_overview_data();
?>

<div class="wrap alenseo-dashboard">
    <h1><?php _e('Alenseo SEO Dashboard', 'alenseo'); ?></h1>
    
    <!-- Statistik-Übersicht -->
    <div class="alenseo-stats-overview">
        <div class="alenseo-stat-box">
            <h3><?php _e('Gesamtpunktzahl', 'alenseo'); ?></h3>
            <div class="stat-number <?php echo ($overview_data['average_score'] >= 70) ? 'good' : (($overview_data['average_score'] >= 50) ? 'ok' : 'poor'); ?>">
                <?php echo esc_html($overview_data['average_score']); ?>
            </div>
            <div class="stat-label"><?php _e('von 100', 'alenseo'); ?></div>
        </div>
        <div class="alenseo-stat-box">
            <h3><?php _e('Optimierte Seiten', 'alenseo'); ?></h3>
            <div class="stat-number good"><?php echo esc_html($overview_data['optimized_count']); ?></div>
            <div class="stat-label"><?php echo sprintf(__('von %d', 'alenseo'), $overview_data['total_count']); ?></div>
        </div>
        <div class="alenseo-stat-box">
            <h3><?php _e('Zu verbessern', 'alenseo'); ?></h3>
            <div class="stat-number warning"><?php echo esc_html($overview_data['needs_improvement_count']); ?></div>
            <div class="stat-label"><?php echo sprintf(__('von %d', 'alenseo'), $overview_data['total_count']); ?></div>
        </div>
        <div class="alenseo-stat-box">
            <h3><?php _e('Ohne Keywords', 'alenseo'); ?></h3>
            <div class="stat-number attention"><?php echo esc_html($overview_data['no_keyword_count']); ?></div>
            <div class="stat-label"><?php echo sprintf(__('von %d', 'alenseo'), $overview_data['total_count']); ?></div>
        </div>
    </div>
    
    <?php 
    // Claude API-Status prüfen
    $settings = get_option('alenseo_settings', array());
    $claude_api_active = !empty($settings['claude_api_key']);
    
    // API-Statistiken abrufen, wenn verfügbar
    if ($claude_api_active && method_exists($alenseo_dashboard, 'get_api_usage_stats')) {
        $api_stats = $alenseo_dashboard->get_api_usage_stats();
    ?>
    <!-- Claude API-Nutzung -->
    <div class="alenseo-api-stats">
        <h2><?php _e('Claude AI API-Nutzung', 'alenseo'); ?></h2>
        <div class="alenseo-stats-overview">
            <div class="alenseo-stat-box">
                <h3><?php _e('Anfragen heute', 'alenseo'); ?></h3>
                <div class="stat-number"><?php echo esc_html($api_stats['requests_today']); ?></div>
                <div class="stat-label"><?php echo sprintf(__('von %d', 'alenseo'), $api_stats['daily_limit']); ?></div>
            </div>
            <div class="alenseo-stat-box">
                <h3><?php _e('Tokens genutzt', 'alenseo'); ?></h3>
                <div class="stat-number"><?php echo esc_html(number_format($api_stats['tokens_used'])); ?></div>
                <div class="stat-label"><?php _e('in diesem Monat', 'alenseo'); ?></div>
            </div>
            <div class="alenseo-stat-box">
                <h3><?php _e('Cache-Treffer', 'alenseo'); ?></h3>
                <div class="stat-number"><?php echo esc_html($api_stats['cache_hit_percentage']); ?>%</div>
                <div class="stat-label"><?php _e('API-Einsparung', 'alenseo'); ?></div>
            </div>
            <div class="alenseo-stat-box">
                <h3><?php _e('API-Status', 'alenseo'); ?></h3>
                <div class="stat-number <?php echo ($api_stats['status'] === 'ok') ? 'good' : 'attention'; ?>"><?php echo esc_html($api_stats['status_message']); ?></div>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- Filter-Bereich -->
    <div class="alenseo-filters">
        <form method="get">
            <input type="hidden" name="page" value="alenseo-seo">
            <select name="status" id="alenseo-filter-status">
                <option value=""><?php _e('Alle Status', 'alenseo'); ?></option>
                <option value="good" <?php selected($filter_status, 'good'); ?>><?php _e('Gut', 'alenseo'); ?></option>
                <option value="needs_improvement" <?php selected($filter_status, 'needs_improvement'); ?>><?php _e('Verbesserungswürdig', 'alenseo'); ?></option>
                <option value="poor" <?php selected($filter_status, 'poor'); ?>><?php _e('Schlecht', 'alenseo'); ?></option>
            </select>
            <select name="type" id="alenseo-filter-post-type">
                <option value=""><?php _e('Alle Typen', 'alenseo'); ?></option>
                <option value="post" <?php selected($filter_type, 'post'); ?>><?php _e('Beiträge', 'alenseo'); ?></option>
                <option value="page" <?php selected($filter_type, 'page'); ?>><?php _e('Seiten', 'alenseo'); ?></option>
            </select>
            <select name="keyword" id="alenseo-filter-keyword">
                <option value=""><?php _e('Alle Keyword-Status', 'alenseo'); ?></option>
                <option value="yes"><?php _e('Mit Keyword', 'alenseo'); ?></option>
                <option value="no"><?php _e('Ohne Keyword', 'alenseo'); ?></option>
            </select>
            <input type="search" name="search" id="alenseo-search-input" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Suche...', 'alenseo'); ?>">
            <button type="submit" id="alenseo-search-button" class="button"><?php _e('Filtern', 'alenseo'); ?></button>
        </form>
    </div>

    <!-- Massenoptimierungs-Steuerelemente -->
    <div class="alenseo-bulk-controls">
        <div class="alenseo-selection-info" style="display:none;">
            <span id="alenseo-selected-count">0</span> <?php _e('Element(e) ausgewählt', 'alenseo'); ?>
            <button type="button" id="alenseo-clear-selection" class="button-link"><?php _e('Auswahl zurücksetzen', 'alenseo'); ?></button>
        </div>
        
        <div class="alenseo-bulk-actions">
            <select id="alenseo-bulk-action">
                <option value=""><?php _e('Massenaktionen', 'alenseo'); ?></option>
                <option value="generate_keywords"><?php _e('Keywords generieren', 'alenseo'); ?></option>
                <option value="optimize_titles"><?php _e('Titel optimieren', 'alenseo'); ?></option>
                <option value="optimize_meta_descriptions"><?php _e('Meta-Descriptions optimieren', 'alenseo'); ?></option>
                <option value="optimize_all"><?php _e('Alles optimieren', 'alenseo'); ?></option>
            </select>
            <button type="button" id="alenseo-apply-bulk-action" class="button"><?php _e('Anwenden', 'alenseo'); ?></button>
        </div>
    </div>
    
    <!-- Fortschrittsanzeige für Massenoptimierung -->
    <div id="alenseo-progress-bar" style="display:none;">
        <div class="alenseo-progress-label"><?php _e('Optimierung läuft...', 'alenseo'); ?></div>
        <div class="alenseo-progress-track">
            <div class="alenseo-progress-fill"></div>
        </div>
        <div class="alenseo-progress-info">
            <span id="alenseo-progress-current">0</span> / <span id="alenseo-progress-total">0</span>
        </div>
    </div>

    <!-- Haupttabelle -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="column-cb check-column">
                    <input type="checkbox" id="alenseo-select-all">
                </th>
                <th class="column-title"><?php _e('Titel', 'alenseo'); ?></th>
                <th class="column-type"><?php _e('Typ', 'alenseo'); ?></th>
                <th class="column-keyword"><?php _e('Keyword', 'alenseo'); ?></th>
                <th class="column-score"><?php _e('SEO Score', 'alenseo'); ?></th>
                <th class="column-status"><?php _e('Status', 'alenseo'); ?></th>
                <th class="column-actions"><?php _e('Aktionen', 'alenseo'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($overview_data['items'] as $item) : 
                $keyword = get_post_meta($item->ID, '_alenseo_keyword', true);
                $has_keyword = !empty($keyword) ? 'yes' : 'no';
            ?>
            <tr class="alenseo-content-row" data-id="<?php echo $item->ID; ?>" data-type="<?php echo $item->post_type; ?>" data-status="<?php echo $item->seo_status; ?>" data-has-keyword="<?php echo $has_keyword; ?>">
                <td>
                    <input type="checkbox" class="alenseo-select-content" value="<?php echo $item->ID; ?>">
                </td>
                <td class="column-title">
                    <strong><a href="<?php echo esc_url(get_edit_post_link($item->ID)); ?>"><?php echo esc_html($item->post_title); ?></a></strong>
                </td>
                <td class="column-type"><?php echo esc_html(get_post_type_label($item->post_type)); ?></td>
                <td class="column-keyword">
                    <?php if (!empty($keyword)) : ?>
                        <span class="alenseo-keyword-badge"><?php echo esc_html($keyword); ?></span>
                    <?php else : ?>
                        <span class="alenseo-no-keyword"><?php _e('Kein Keyword', 'alenseo'); ?></span>
                    <?php endif; ?>
                </td>
                <td class="column-score">
                    <div class="seo-score-badge score-<?php echo esc_attr($item->seo_status); ?>">
                        <?php echo esc_html($item->seo_score); ?>
                    </div>
                </td>
                <td class="column-status">
                    <span class="status-badge status-<?php echo esc_attr($item->seo_status); ?>">
                        <?php echo esc_html($item->seo_status_label); ?>
                    </span>
                </td>
                <td class="column-actions">
                    <button type="button" class="button button-small alenseo-action-optimize" data-id="<?php echo $item->ID; ?>">
                        <?php _e('Optimieren', 'alenseo'); ?>
                    </button>
                    <button type="button" class="button button-small alenseo-action-analyze" data-id="<?php echo $item->ID; ?>">
                        <?php _e('Analysieren', 'alenseo'); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if (current_user_can('manage_options') && isset($_GET['debug']) && $_GET['debug'] === 'true'): ?>
    <!-- Debug-Bereich (nur für Administratoren) -->
    <div class="alenseo-debug-section" style="margin-top: 30px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
        <h3><?php _e('Debug & Test-Bereich', 'alenseo'); ?></h3>
        <p><?php _e('Dieser Bereich ist nur für Entwickler und Administratoren sichtbar.', 'alenseo'); ?></p>
        
        <div class="alenseo-test-buttons" style="margin: 15px 0;">
            <button id="alenseo-test-batch" class="button"><?php _e('Batch-Optimierung testen', 'alenseo'); ?></button>
            <button id="alenseo-test-db" class="button"><?php _e('Datenbank-Performance testen', 'alenseo'); ?></button>
        </div>
        
        <div id="alenseo-test-results" style="margin-top: 15px; padding: 10px; border: 1px solid #eee; background: #fff; max-height: 300px; overflow-y: auto; display: none;">
            <h4><?php _e('Test-Ergebnisse', 'alenseo'); ?></h4>
            <pre id="alenseo-test-output" style="white-space: pre-wrap;"></pre>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                $('#alenseo-test-batch').on('click', function() {
                    const button = $(this);
                    const resultsDiv = $('#alenseo-test-results');
                    const outputPre = $('#alenseo-test-output');
                    
                    button.prop('disabled', true).text('<?php _e('Test läuft...', 'alenseo'); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'alenseo_test_batch_optimize',
                            nonce: alenseoData.nonce
                        },
                        success: function(response) {
                            button.prop('disabled', false).text('<?php _e('Batch-Optimierung testen', 'alenseo'); ?>');
                            
                            if (response.success) {
                                let output = "=== TEST ERFOLGREICH ===\n\n";
                                
                                // Log ausgeben
                                output += "--- LOG ---\n";
                                response.data.log.forEach(function(line) {
                                    output += line + "\n";
                                });
                                
                                // JSON-Ergebnisse formatieren
                                output += "\n--- DETAIL-ERGEBNISSE ---\n";
                                output += JSON.stringify(response.data.results, null, 2);
                                
                                outputPre.text(output);
                            } else {
                                let output = "=== TEST FEHLGESCHLAGEN ===\n\n";
                                output += "Fehler: " + response.data.message + "\n\n";
                                
                                if (response.data.log) {
                                    output += "--- LOG ---\n";
                                    response.data.log.forEach(function(line) {
                                        output += line + "\n";
                                    });
                                }
                                
                                outputPre.text(output);
                            }
                            
                            resultsDiv.show();
                        },
                        error: function() {
                            button.prop('disabled', false).text('<?php _e('Batch-Optimierung testen', 'alenseo'); ?>');
                            outputPre.text("=== FEHLER ===\nFehler bei der Kommunikation mit dem Server.");
                            resultsDiv.show();
                        }
                    });
                });
                
                $('#alenseo-test-db').on('click', function() {
                    const button = $(this);
                    const resultsDiv = $('#alenseo-test-results');
                    const outputPre = $('#alenseo-test-output');
                    
                    button.prop('disabled', true).text('<?php _e('Test läuft...', 'alenseo'); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'alenseo_test_database_performance',
                            nonce: alenseoData.nonce
                        },
                        success: function(response) {
                            button.prop('disabled', false).text('<?php _e('Datenbank-Performance testen', 'alenseo'); ?>');
                            
                            if (response.success) {
                                let output = "=== PERFORMANCE-TEST ERGEBNISSE ===\n\n";
                                
                                // Log ausgeben
                                response.data.log.forEach(function(line) {
                                    output += line + "\n";
                                });
                                
                                // Zeiten formatieren
                                output += "\n--- ZEITEN (SEKUNDEN) ---\n";
                                output += "Test 1 (nicht optimiert): " + response.data.times.test1.toFixed(4) + "\n";
                                output += "Test 2 (optimiert): " + response.data.times.test2.toFixed(4) + "\n";
                                
                                if (response.data.times.test3 !== null) {
                                    output += "Test 3 (mit Cache): " + response.data.times.test3.toFixed(4) + "\n";
                                }
                                
                                output += "Gesamt: " + response.data.times.total.toFixed(4) + "\n";
                                
                                outputPre.text(output);
                            } else {
                                outputPre.text("=== TEST FEHLGESCHLAGEN ===\n" + response.data.message);
                            }
                            
                            resultsDiv.show();
                        },
                        error: function() {
                            button.prop('disabled', false).text('<?php _e('Datenbank-Performance testen', 'alenseo'); ?>');
                            outputPre.text("=== FEHLER ===\nFehler bei der Kommunikation mit dem Server.");
                            resultsDiv.show();
                        }
                    });
                });
            });
        </script>
    </div>
    <?php endif; ?>
    
    <!-- Optimierungsdialog -->
    <div id="alenseo-optimization-dialog" class="alenseo-dialog" style="display:none;">
        <div class="alenseo-dialog-content">
            <div class="alenseo-dialog-header">
                <h2><?php _e('Inhalt optimieren', 'alenseo'); ?></h2>
                <button type="button" class="alenseo-dialog-close dashicons dashicons-no-alt"></button>
            </div>
            <div class="alenseo-dialog-body">
                <div class="alenseo-keyword-section">
                    <h3><?php _e('Keyword', 'alenseo'); ?></h3>
                    <p><?php _e('Aktuelles Keyword:', 'alenseo'); ?> <strong id="alenseo-current-keyword-value"></strong></p>
                    
                    <button type="button" id="alenseo-generate-keywords" class="button">
                        <?php _e('Keywords generieren', 'alenseo'); ?>
                    </button>
                    
                    <div class="alenseo-keywords-loader" style="display:none;">
                        <span class="spinner is-active"></span> <?php _e('Keywords werden generiert...', 'alenseo'); ?>
                    </div>
                    
                    <div class="alenseo-keyword-suggestions" style="display:none;">
                        <h4><?php _e('Keyword-Vorschläge', 'alenseo'); ?></h4>
                        <div class="alenseo-keyword-list"></div>
                        <p class="description"><?php _e('Klicke auf ein Keyword, um es auszuwählen.', 'alenseo'); ?></p>
                    </div>
                </div>
                
                <div class="alenseo-optimization-options">
                    <h3><?php _e('Optimierungsoptionen', 'alenseo'); ?></h3>
                    <label>
                        <input type="checkbox" name="optimize_title" checked> <?php _e('Titel optimieren', 'alenseo'); ?>
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="optimize_meta_description" checked> <?php _e('Meta-Description optimieren', 'alenseo'); ?>
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="optimize_content"> <?php _e('Inhaltsvorschläge anzeigen', 'alenseo'); ?>
                    </label>
                    
                    <div class="alenseo-optimization-actions">
                        <button type="button" id="alenseo-start-optimization" class="button button-primary">
                            <?php _e('Optimierung starten', 'alenseo'); ?>
                        </button>
                    </div>
                    
                    <div class="alenseo-optimizer-loader" style="display:none;">
                        <span class="spinner is-active"></span> <?php _e('Optimierung wird ausgeführt...', 'alenseo'); ?>
                    </div>
                </div>
                
                <div class="alenseo-results-section" style="display:none;">
                    <h3><?php _e('Optimierungsvorschläge', 'alenseo'); ?></h3>
                    <div class="alenseo-results-container"></div>
                </div>
            </div>
            <div class="alenseo-dialog-footer">
                <button type="button" class="button alenseo-dialog-close"><?php _e('Schließen', 'alenseo'); ?></button>
            </div>
        </div>
    </div>
</div>
