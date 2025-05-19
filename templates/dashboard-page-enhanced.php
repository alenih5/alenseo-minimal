<?php
/**
 * Enhanced Dashboard-Template für Alenseo SEO
 *
 * Zeigt eine Übersicht aller Seiten und Beiträge mit SEO-Status in einer grafischen Darstellung
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
$filter_keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '';

// Übersichtsdaten abrufen
$overview_data = $alenseo_dashboard->get_overview_data();
$last_update = get_option('alenseo_last_data_update', date('Y-m-d H:i:s'));

// Berechnung der grafischen Statusverteilung für das Kreisdiagramm
$well_performing = $overview_data['optimized_count'];
$underperforming = $overview_data['needs_improvement_count'];
$deadweight = $overview_data['poor_count'] ?? 0;
$excluded = $overview_data['no_keyword_count'];

// Prüfen, ob die Gesamtzahl > 0 ist, um Division durch 0 zu vermeiden
$total_count = $overview_data['total_count'] > 0 ? $overview_data['total_count'] : 1;

// Daten für das JavaScript vorbereiten
$chart_data = array(
    'performance_score' => $overview_data['average_score'],
    'status_distribution' => array(
        'well_performing' => $well_performing,
        'underperforming' => $underperforming,
        'deadweight' => $deadweight,
        'excluded' => $excluded
    ),
    'status_labels' => array(
        'well_performing' => __('Well performing', 'alenseo'),
        'underperforming' => __('Underperforming', 'alenseo'),
        'deadweight' => __('Deadweight', 'alenseo'),
        'excluded' => __('Excluded', 'alenseo')
    )
);

// Localize daten für JavaScript
wp_localize_script('alenseo-dashboard-js', 'alenseoChartData', $chart_data);
?>

<div class="wrap alenseo-dashboard">
    <header class="alenseo-dashboard-header">
        <div class="alenseo-dashboard-title">
            <h1 id="content-audit"><?php _e('Content Audit', 'alenseo'); ?></h1>
        </div>
        <div class="alenseo-dashboard-meta">
            <?php _e('Last update:', 'alenseo'); ?> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_update))); ?>
            
            <div class="alenseo-dashboard-actions">
                <a href="mailto:support@alenseo.com" class="button"><?php _e('Support', 'alenseo'); ?></a>
                <a href="#" class="button" id="alenseo-help-button"><?php _e('Help', 'alenseo'); ?></a>
                <span class="alenseo-analyzing"><?php _e('Analyzing:', 'alenseo'); ?> <span id="alenseo-analysis-percentage">0</span>%</span>
                <a href="#" class="button button-primary" id="alenseo-settings-button"><span class="dashicons dashicons-admin-generic"></span></a>
            </div>
        </div>
    </header>
    
    <div class="alenseo-dashboard-content">
        <!-- Performance Score Section -->
        <div class="alenseo-card alenseo-performance-card">
            <h2><?php _e('Performance Score', 'alenseo'); ?></h2>
            
            <div class="alenseo-performance-gauge">
                <canvas id="performanceGauge"></canvas>
            </div>
            
            <div class="alenseo-performance-description">
                <p><?php _e('This number estimates the strength of your website\'s content on a 100-point scale. It considers the proportion of content that performs well as compared to content that needs to be updated or removed.', 'alenseo'); ?></p>
                
                <div class="alenseo-performance-summary">
                    <?php printf(__('%d well performing pages out of %d', 'alenseo'), $well_performing, $total_count); ?>
                </div>
            </div>
        </div>
        
        <!-- Posts Status Distribution Chart -->
        <div class="alenseo-card alenseo-status-distribution-card">
            <h2><?php _e('Posts & pages by status', 'alenseo'); ?></h2>
            
            <div class="alenseo-status-chart">
                <canvas id="statusDistributionChart"></canvas>
            </div>
            
            <div class="alenseo-status-legend">
                <div class="alenseo-status-item well-performing">
                    <span class="alenseo-status-color"></span>
                    <span class="alenseo-status-label"><?php _e('Well performing', 'alenseo'); ?></span>
                    <span class="alenseo-status-count"><?php echo esc_html($well_performing); ?></span>
                </div>
                <div class="alenseo-status-item underperforming">
                    <span class="alenseo-status-color"></span>
                    <span class="alenseo-status-label"><?php _e('Underperforming', 'alenseo'); ?></span>
                    <span class="alenseo-status-count"><?php echo esc_html($underperforming); ?></span>
                </div>
                <div class="alenseo-status-item deadweight">
                    <span class="alenseo-status-color"></span>
                    <span class="alenseo-status-label"><?php _e('Deadweight', 'alenseo'); ?></span>
                    <span class="alenseo-status-count"><?php echo esc_html($deadweight); ?></span>
                </div>
                <div class="alenseo-status-item excluded">
                    <span class="alenseo-status-color"></span>
                    <span class="alenseo-status-label"><?php _e('Excluded', 'alenseo'); ?></span>
                    <span class="alenseo-status-count"><?php echo esc_html($excluded); ?></span>
                </div>
            </div>
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
    <div class="alenseo-api-stats alenseo-card">
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

    <!-- Benachrichtigung für Inhaltsänderungen -->
    <div class="alenseo-alert" id="performanceChangesAlert">
        <div class="alenseo-alert-icon">
            <span class="dashicons dashicons-warning"></span>
        </div>
        <div class="alenseo-alert-content">
            <h3><?php _e('Some articles are no longer "well-performing"', 'alenseo'); ?></h3>
            <p><?php _e('Several articles lost their "well-performing" status. It\'s worth checking them out and figuring out the causes.', 'alenseo'); ?></p>
            <button class="button" id="viewArticlesButton"><?php _e('View articles', 'alenseo'); ?></button>
        </div>
        <button class="alenseo-alert-close" id="closeAlertButton">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>

    <!-- Filter-Bereich -->
    <div class="alenseo-filters">
        <form method="get" id="alenseo-filter-form">
            <input type="hidden" name="page" value="alenseo-dashboard">
            <div class="alenseo-filter-group">
                <label for="alenseo-filter-status"><?php _e('Status', 'alenseo'); ?>:</label>
                <select name="status" id="alenseo-filter-status">
                    <option value=""><?php _e('Alle Status', 'alenseo'); ?></option>
                    <option value="optimized" <?php selected($filter_status, 'optimized'); ?>><?php _e('Optimiert', 'alenseo'); ?></option>
                    <option value="needs_improvement" <?php selected($filter_status, 'needs_improvement'); ?>><?php _e('Verbesserungswürdig', 'alenseo'); ?></option>
                    <option value="poor" <?php selected($filter_status, 'poor'); ?>><?php _e('Mangelhaft', 'alenseo'); ?></option>
                    <option value="no_keyword" <?php selected($filter_status, 'no_keyword'); ?>><?php _e('Ohne Keywords', 'alenseo'); ?></option>
                </select>
            </div>
            <div class="alenseo-filter-group">
                <label for="alenseo-filter-type"><?php _e('Typ', 'alenseo'); ?>:</label>
                <select name="type" id="alenseo-filter-type">
                    <option value=""><?php _e('Alle Typen', 'alenseo'); ?></option>
                    <option value="post" <?php selected($filter_type, 'post'); ?>><?php _e('Beiträge', 'alenseo'); ?></option>
                    <option value="page" <?php selected($filter_type, 'page'); ?>><?php _e('Seiten', 'alenseo'); ?></option>
                    <?php
                    // Zeige unterstützte Custom Post Types
                    $custom_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
                    foreach ($custom_types as $type) {
                        if (in_array($type->name, array('product', 'portfolio', 'project'))) {
                            echo '<option value="' . esc_attr($type->name) . '" ' . selected($filter_type, $type->name, false) . '>' . esc_html($type->labels->name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="alenseo-filter-group">
                <label for="alenseo-filter-keyword"><?php _e('Keywords', 'alenseo'); ?>:</label>
                <select name="keyword" id="alenseo-filter-keyword">
                    <option value=""><?php _e('Alle Keywords', 'alenseo'); ?></option>
                    <?php
                    // Liste der verwendeten Keywords
                    $keywords = $alenseo_dashboard->get_all_keywords();
                    foreach ($keywords as $keyword) {
                        echo '<option value="' . esc_attr($keyword) . '" ' . selected($filter_keyword, $keyword, false) . '>' . esc_html($keyword) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="alenseo-filter-group alenseo-search-group">
                <input type="text" name="search" id="alenseo-search-input" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php esc_attr_e('Suchen...', 'alenseo'); ?>">
                <button type="submit" id="alenseo-search-button" class="button"><span class="dashicons dashicons-search"></span></button>
            </div>
        </form>
    </div>

    <!-- Inhalt-Tabelle -->
    <div class="alenseo-table-container">
        <form id="alenseo-bulk-action-form">
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Mehrfachaktion auswählen', 'alenseo'); ?></label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Mehrfachaktionen', 'alenseo'); ?></option>
                        <option value="analyze"><?php _e('Analyse starten', 'alenseo'); ?></option>
                        <option value="optimize"><?php _e('Mit Claude optimieren', 'alenseo'); ?></option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Anwenden', 'alenseo'); ?>" disabled>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped alenseo-content-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input id="alenseo-select-all" type="checkbox"></td>
                        <th class="column-title"><?php _e('Titel', 'alenseo'); ?></th>
                        <th class="column-author"><?php _e('Autor', 'alenseo'); ?></th>
                        <th class="column-keyword"><?php _e('Ziel-Keyword', 'alenseo'); ?></th>
                        <th class="column-position"><?php _e('Position', 'alenseo'); ?></th>
                        <th class="column-traffic"><?php _e('Traffic gesamt', 'alenseo'); ?></th>
                        <th class="column-organic-traffic"><?php _e('Organischer Traffic', 'alenseo'); ?></th>
                        <th class="column-backlinks"><?php _e('Backlinks', 'alenseo'); ?></th>
                        <th class="column-ref-domains"><?php _e('Ref. Domains', 'alenseo'); ?></th>
                        <th class="column-date"><?php _e('Datum', 'alenseo'); ?></th>
                        <th class="column-actions"><?php _e('Aktionen', 'alenseo'); ?></th>
                    </tr>
                </thead>
                <tbody id="alenseo-content-list">
                    <?php
                    // Beiträge und Seiten abrufen
                    $items = $alenseo_dashboard->get_content_items($filter_status, $filter_type, $filter_search, $filter_keyword);
                    
                    if (empty($items)) {
                        echo '<tr><td colspan="11">' . __('Keine Einträge gefunden.', 'alenseo') . '</td></tr>';
                    } else {
                        foreach ($items as $item) {
                            $post_id = $item->ID;
                            $edit_link = get_edit_post_link($post_id);
                            $view_link = get_permalink($post_id);
                            
                            // SEO-Daten für diesen Beitrag abrufen
                            $seo_data = get_post_meta($post_id, '_alenseo_seo_data', true);
                            if (empty($seo_data)) {
                                $seo_data = array();
                            }
                            
                            // Status bestimmen
                            $status_class = 'no-keyword';
                            $status_text = __('Keine Keywords', 'alenseo');
                            
                            if (!empty($seo_data['keywords'])) {
                                $score = isset($seo_data['score']) ? intval($seo_data['score']) : 0;
                                
                                if ($score >= 70) {
                                    $status_class = 'optimized';
                                    $status_text = __('Optimiert', 'alenseo');
                                } else {
                                    $status_class = 'needs-improvement';
                                    $status_text = __('Verbesserungswürdig', 'alenseo');
                                }
                            }
                            
                            // Keyword anzeigen
                            $keyword = !empty($seo_data['keywords'][0]) ? $seo_data['keywords'][0] : '–';
                            
                            // Zusätzliche Daten
                            $position = !empty($seo_data['position']) ? $seo_data['position'] : '–';
                            $total_traffic = !empty($seo_data['total_traffic']) ? $seo_data['total_traffic'] : '–';
                            $organic_traffic = !empty($seo_data['organic_traffic']) ? $seo_data['organic_traffic'] : '–';
                            $backlinks = !empty($seo_data['backlinks']) ? $seo_data['backlinks'] : '–';
                            $ref_domains = !empty($seo_data['ref_domains']) ? $seo_data['ref_domains'] : '–';
                            
                            // Optimierungsstatus und Klasse
                            $optimization_status = '';
                            if (!empty($seo_data['is_optimized']) && $seo_data['is_optimized']) {
                                $optimization_status = '<span class="alenseo-tag optimized">' . __('Optimiert', 'alenseo') . '</span>';
                            }
                            
                            // Keyword-Status
                            $keyword_tag = '';
                            if (!empty($keyword) && $keyword !== '–') {
                                if (!empty($seo_data['keyword_status']) && $seo_data['keyword_status'] === 'approved') {
                                    $keyword_tag = '<span class="alenseo-tag keyword-approved">' . __('APPROVED', 'alenseo') . '</span>';
                                } elseif (!empty($seo_data['keyword_status']) && $seo_data['keyword_status'] === 'suggested') {
                                    $keyword_tag = '<span class="alenseo-tag keyword-suggested">' . __('SUGGESTED KEYWORD', 'alenseo') . '</span>';
                                }
                            }
                            
                            ?>
                            <tr class="alenseo-content-item <?php echo esc_attr($status_class); ?>" data-id="<?php echo esc_attr($post_id); ?>">
                                <th class="check-column">
                                    <input type="checkbox" class="alenseo-select-post" name="post_ids[]" value="<?php echo esc_attr($post_id); ?>">
                                </th>
                                <td class="column-title">
                                    <strong><a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($item->post_title); ?></a></strong>
                                    <div class="row-actions">
                                        <span class="edit"><a href="<?php echo esc_url($edit_link); ?>"><?php _e('Bearbeiten', 'alenseo'); ?></a> | </span>
                                        <span class="view"><a href="<?php echo esc_url($view_link); ?>" target="_blank"><?php _e('Ansehen', 'alenseo'); ?></a> | </span>
                                        <span class="analyze"><a href="#" class="alenseo-analyze-link" data-post-id="<?php echo esc_attr($post_id); ?>"><?php _e('Analysieren', 'alenseo'); ?></a></span>
                                    </div>
                                </td>
                                <td class="column-author"><?php echo esc_html(get_the_author_meta('display_name', $item->post_author)); ?></td>
                                <td class="column-keyword">
                                    <?php echo esc_html($keyword); ?>
                                    <?php echo $keyword_tag; ?>
                                </td>
                                <td class="column-position"><?php echo esc_html($position); ?></td>
                                <td class="column-traffic"><?php echo esc_html($total_traffic); ?></td>
                                <td class="column-organic-traffic"><?php echo esc_html($organic_traffic); ?></td>
                                <td class="column-backlinks"><?php echo esc_html($backlinks); ?></td>
                                <td class="column-ref-domains"><?php echo esc_html($ref_domains); ?></td>
                                <td class="column-date"><?php echo esc_html(get_the_date('', $post_id)); ?></td>
                                <td class="column-actions">
                                    <div class="alenseo-action-buttons">
                                        <?php if ($status_class === 'no-keyword') { ?>
                                            <button type="button" class="button alenseo-suggest-button" data-post-id="<?php echo esc_attr($post_id); ?>"><?php _e('Keywords', 'alenseo'); ?></button>
                                        <?php } elseif (!isset($seo_data['is_optimized']) || !$seo_data['is_optimized']) { ?>
                                            <button type="button" class="button alenseo-optimize-button" data-post-id="<?php echo esc_attr($post_id); ?>"><?php _e('Optimieren', 'alenseo'); ?></button>
                                        <?php } else { ?>
                                            <span class="alenseo-status-indicator optimized"><?php _e('Optimiert', 'alenseo'); ?></span>
                                        <?php } ?>
                                        
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=alenseo-page-detail&post_id=' . $post_id)); ?>" class="button alenseo-detail-button"><?php _e('Details', 'alenseo'); ?></a>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </form>
    </div>
</div>
