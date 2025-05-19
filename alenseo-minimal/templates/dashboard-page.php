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

<div class="wrap alenseo-dashboard-wrap">
    <div class="alenseo-dashboard-header">
        <div class="alenseo-dashboard-title">
            <h1><?php _e('Alenseo SEO Dashboard', 'alenseo'); ?> <span class="alenseo-dashboard-version">v<?php echo ALENSEO_MINIMAL_VERSION; ?></span></h1>
            
            <?php
            // Claude API-Status prüfen
            $settings = get_option('alenseo_settings', array());
            $claude_api_active = !empty($settings['claude_api_key']);
            ?>
            
            <?php if ($claude_api_active) : ?>
                <div class="alenseo-api-status">
                    <span class="alenseo-api-status-badge alenseo-api-status-active">
                        <span class="dashicons dashicons-yes-alt"></span> <?php _e('Claude API aktiv', 'alenseo'); ?>
                    </span>
                </div>
            <?php else : ?>
                <div class="alenseo-api-status">
                    <span class="alenseo-api-status-badge alenseo-api-status-inactive">
                        <span class="dashicons dashicons-warning"></span> <?php _e('Claude API nicht konfiguriert', 'alenseo'); ?>
                    </span>
                    <a href="<?php echo admin_url('admin.php?page=alenseo-minimal-settings'); ?>" class="button button-small">
                        <?php _e('Einstellungen', 'alenseo'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="alenseo-dashboard-stats">
            <div class="alenseo-stat-card alenseo-score-card">
                <div class="alenseo-score-chart <?php echo $overview_data['average_score'] >= 80 ? 'score-good' : ($overview_data['average_score'] >= 50 ? 'score-ok' : 'score-poor'); ?>">
                    <div class="alenseo-score-value"><?php echo esc_html($overview_data['average_score']); ?></div>
                </div>
                <div class="alenseo-score-info">
                    <div class="alenseo-score-title"><?php _e('Durchschnittlicher SEO-Score', 'alenseo'); ?></div>
                    <div class="alenseo-score-description">
                        <?php _e('Der durchschnittliche Score aller analysierten Seiten.', 'alenseo'); ?>
                    </div>
                </div>
            </div>
            
            <div class="alenseo-stat-card">
                <div class="alenseo-stat-value"><?php echo esc_html($overview_data['total_posts']); ?></div>
                <div class="alenseo-stat-label"><?php _e('Gesamtzahl der Seiten', 'alenseo'); ?></div>
            </div>
            
            <div class="alenseo-stat-card">
                <div class="alenseo-stat-value"><?php echo esc_html($overview_data['optimized_posts']); ?></div>
                <div class="alenseo-stat-label"><?php _e('Gut optimierte Seiten', 'alenseo'); ?></div>
            </div>
            
            <div class="alenseo-stat-card">
                <div class="alenseo-stat-value"><?php echo esc_html($overview_data['partially_optimized_posts']); ?></div>
                <div class="alenseo-stat-label"><?php _e('Teilweise optimierte Seiten', 'alenseo'); ?></div>
            </div>
            
            <div class="alenseo-stat-card">
                <div class="alenseo-stat-value"><?php echo esc_html($overview_data['unoptimized_posts'] + $overview_data['not_analyzed_posts']); ?></div>
                <div class="alenseo-stat-label"><?php _e('Nicht optimierte Seiten', 'alenseo'); ?></div>
            </div>
        </div>
    </div>
    
    <div class="alenseo-filter-bar">
        <form id="alenseo-filter-form" method="get" action="">
            <input type="hidden" name="page" value="alenseo-optimizer">
            
            <div class="alenseo-filter-group">
                <label for="alenseo-filter-status"><?php _e('Status:', 'alenseo'); ?></label>
                <select id="alenseo-filter-status" name="status">
                    <option value="" <?php selected($filter_status, ''); ?>><?php _e('Alle', 'alenseo'); ?></option>
                    <option value="good" <?php selected($filter_status, 'good'); ?>><?php _e('Gut optimiert', 'alenseo'); ?></option>
                    <option value="ok" <?php selected($filter_status, 'ok'); ?>><?php _e('Teilweise optimiert', 'alenseo'); ?></option>
                    <option value="poor" <?php selected($filter_status, 'poor'); ?>><?php _e('Optimierung nötig', 'alenseo'); ?></option>
                    <option value="unknown" <?php selected($filter_status, 'unknown'); ?>><?php _e('Nicht analysiert', 'alenseo'); ?></option>
                </select>
            </div>
            
            <div class="alenseo-filter-group">
                <label for="alenseo-filter-type"><?php _e('Typ:', 'alenseo'); ?></label>
                <select id="alenseo-filter-type" name="type">
                    <option value="" <?php selected($filter_type, ''); ?>><?php _e('Alle', 'alenseo'); ?></option>
                    <?php
                    // Post-Typen für Filter anzeigen
                    $post_types = get_post_types(array('public' => true), 'objects');
                    foreach ($post_types as $post_type) {
                        echo '<option value="' . esc_attr($post_type->name) . '" ' . selected($filter_type, $post_type->name, false) . '>' . esc_html($post_type->labels->singular_name) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="alenseo-search-group">
                <input type="text" id="alenseo-search-input" name="search" placeholder="<?php esc_attr_e('Suche nach Titel...', 'alenseo'); ?>" value="<?php echo esc_attr($filter_search); ?>">
                <button type="submit" id="alenseo-search-button" class="button"><?php _e('Suchen', 'alenseo'); ?></button>
            </div>
        </form>
    </div>
    
    <div class="alenseo-bulk-actions">
        <div class="alenseo-bulk-select">
            <select id="alenseo-bulk-action">
                <option value=""><?php _e('Massenaktionen', 'alenseo'); ?></option>
                <option value="analyze"><?php _e('Analysieren', 'alenseo'); ?></option>
                <option value="set_keyword"><?php _e('Keyword setzen', 'alenseo'); ?></option>
                <?php if ($claude_api_active) : ?>
                <option value="optimize"><?php _e('Optimieren', 'alenseo'); ?></option>
                <?php endif; ?>
            </select>
            <button type="button" id="alenseo-bulk-action-apply" class="button" disabled><?php _e('Anwenden', 'alenseo'); ?></button>
        </div>
    </div>
    
    <div class="alenseo-table-container">
        <table class="alenseo-table">
            <thead>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" id="alenseo-select-all">
                    </th>
                    <th><?php _e('Titel', 'alenseo'); ?></th>
                    <th><?php _e('Typ', 'alenseo'); ?></th>
                    <th><?php _e('Fokus-Keyword', 'alenseo'); ?></th>
                    <th><?php _e('SEO-Score', 'alenseo'); ?></th>
                    <th><?php _e('Status', 'alenseo'); ?></th>
                    <th><?php _e('Aktionen', 'alenseo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Beiträge und Seiten anzeigen
                
                // Filter-Argumente zusammenstellen
                $query_args = array();
                
                if (!empty($filter_type)) {
                    $query_args['post_type'] = $filter_type;
                }
                
                if (!empty($filter_search)) {
                    $query_args['s'] = $filter_search;
                }
                
                // Beiträge abrufen
                $posts = $alenseo_dashboard->get_all_posts($query_args);
                
                if (empty($posts)) {
                    echo '<tr><td colspan="7">' . __('Keine Beiträge oder Seiten gefunden.', 'alenseo') . '</td></tr>';
                } else {
                    foreach ($posts as $post) {
                        // SEO-Daten abrufen
                        $seo_data = $alenseo_dashboard->get_post_seo_data($post->ID);
                        
                        // Status-Filter anwenden
                        if (!empty($filter_status) && $seo_data['status'] !== $filter_status) {
                            continue;
                        }
                        
                        // Permalink
                        $permalink = get_permalink($post->ID);
                        
                        // Bearbeiten-Link
                        $edit_link = get_edit_post_link($post->ID);
                        
                        // Post-Typ-Label
                        $post_type_label = $alenseo_dashboard->get_post_type_label($post->post_type);
                        
                        // Keyword
                        $keyword = !empty($seo_data['keyword']) ? esc_html($seo_data['keyword']) : '-';
                        
                        // Score-Pill
                        $score_pill = $alenseo_dashboard->get_score_pill_html($seo_data['score']);
                        
                        // Status-Badge
                        $status_badge = $alenseo_dashboard->get_status_badge_html($seo_data['status'], $seo_data['status_text']);
                        ?>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" class="alenseo-select-post" value="<?php echo esc_attr($post->ID); ?>">
                            </td>
                            <td>
                                <div class="alenseo-post-title">
                                    <a href="#" data-post-id="<?php echo esc_attr($post->ID); ?>"><?php echo esc_html($post->post_title); ?></a>
                                </div>
                                <div class="alenseo-post-type"><?php echo esc_html($post_type_label); ?></div>
                            </td>
                            <td>
                                <?php echo esc_html($post_type_label); ?>
                            </td>
                            <td class="alenseo-post-keyword">
                                <span class="alenseo-post-keyword-value"><?php echo $keyword; ?></span>
                                <button type="button" class="alenseo-keyword-button button-link" data-post-id="<?php echo esc_attr($post->ID); ?>">
                                    <span class="dashicons dashicons-edit-large"></span>
                                </button>
                            </td>
                            <td class="alenseo-seo-score">
                                <?php echo $score_pill; ?>
                            </td>
                            <td class="alenseo-seo-status">
                                <?php echo $status_badge; ?>
                            </td>
                            <td class="alenseo-actions">
                                <button type="button" class="alenseo-action-button alenseo-analyze-button" data-post-id="<?php echo esc_attr($post->ID); ?>" title="<?php esc_attr_e('Analysieren', 'alenseo'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                                
                                <a href="<?php echo esc_url($edit_link); ?>" class="alenseo-action-button" title="<?php esc_attr_e('Bearbeiten', 'alenseo'); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                                
                                <button type="button" class="alenseo-action-button alenseo-view-button" data-post-id="<?php echo esc_attr($post->ID); ?>" data-permalink="<?php echo esc_url($permalink); ?>" title="<?php esc_attr_e('Ansehen', 'alenseo'); ?>">
                                    <span class="dashicons dashicons-welcome-view-site"></span>
                                </button>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    
    <?php if (empty($posts)) : ?>
    <div class="alenseo-no-posts">
        <p><?php _e('Keine Beiträge oder Seiten gefunden, die den Filterkriterien entsprechen.', 'alenseo'); ?></p>
    </div>
    <?php endif; ?>
</div>
