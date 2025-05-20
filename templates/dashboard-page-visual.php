<?php
/**
 * Visual Dashboard-Template für Alenseo SEO
 *
 * Zeigt eine grafische Übersicht aller Seiten und Beiträge mit SEO-Status an
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

// Verteilung nach Post-Typen berechnen
$post_types = get_post_types(array('public' => true), 'objects');
$post_type_counts = array();
foreach ($post_types as $post_type_name => $post_type_obj) {
    if (in_array($post_type_name, array('attachment'))) {
        continue;
    }
    
    $count = wp_count_posts($post_type_name);
    $total = 0;
    foreach ($count as $status_count) {
        $total += $status_count;
    }
    
    // Nur Post-Typen mit Inhalten anzeigen
    if ($total > 0) {
        $post_type_counts[$post_type_name] = array(
            'name' => $post_type_obj->labels->name,
            'count' => $total
        );
    }
}

// Zusätzliche Statistikdaten
$claude_api = new Alenseo_Claude_API();
$claude_api_stats = array(
    'daily_requests' => get_option('alenseo_daily_requests', 0),
    'daily_limit' => 50, // Beispiel: 50 Anfragen pro Tag
    'monthly_tokens' => get_option('alenseo_monthly_tokens', 0),
    'token_limit' => 1000000, // Beispiel: 1 Million Tokens pro Monat
    'cache_hits' => get_option('alenseo_api_cache_hits', 0),
    'total_requests' => get_option('alenseo_api_total_requests', 1), // +1 um Division durch 0 zu vermeiden
);

$cache_hit_rate = ($claude_api_stats['cache_hits'] / $claude_api_stats['total_requests']) * 100;
$cache_hit_rate = round($cache_hit_rate);
?>

<div class="wrap alenseo-dashboard">
    <h1><?php _e('Alenseo SEO Dashboard', 'alenseo'); ?></h1>
    
    <!-- Statistik-Übersicht -->
    <div class="alenseo-stats-overview">
        <div class="alenseo-stat-box box-score">
            <h3><?php _e('Gesamtpunktzahl', 'alenseo'); ?></h3>
            
            <!-- Kreisdiagramm für die Punktzahl -->
            <div class="circle-progress" data-score="<?php echo esc_attr($overview_data['average_score']); ?>">
                <div class="progress-fill">
                    <div class="progress-fill-right"></div>
                    <div class="progress-fill-left"></div>
                </div>
                <div class="progress-center">
                    <?php echo esc_html($overview_data['average_score']); ?>
                </div>
            </div>
            
            <div class="stat-label"><?php _e('von 100', 'alenseo'); ?></div>
        </div>
        
        <div class="alenseo-stat-box box-optimized">
            <h3><?php _e('Optimierte Seiten', 'alenseo'); ?></h3>
            
            <!-- Kreisdiagramm für optimierte Seiten -->
            <div class="circle-progress" data-score="<?php echo esc_attr(($overview_data['total_count'] > 0) ? round(($overview_data['optimized_count'] / $overview_data['total_count']) * 100) : 0); ?>">
                <div class="progress-fill">
                    <div class="progress-fill-right"></div>
                    <div class="progress-fill-left"></div>
                </div>
                <div class="progress-center">
                    <?php echo esc_html($overview_data['optimized_count']); ?>
                </div>
            </div>
            
            <div class="stat-label"><?php echo sprintf(__('von %d', 'alenseo'), $overview_data['total_count']); ?></div>
        </div>
        
        <div class="alenseo-stat-box box-improve">
            <h3><?php _e('Zu verbessern', 'alenseo'); ?></h3>
            
            <!-- Kreisdiagramm für zu verbessernde Seiten -->
            <div class="circle-progress" data-score="<?php echo esc_attr(($overview_data['total_count'] > 0) ? round(($overview_data['to_improve_count'] / $overview_data['total_count']) * 100) : 0); ?>">
                <div class="progress-fill">
                    <div class="progress-fill-right"></div>
                    <div class="progress-fill-left"></div>
                </div>
                <div class="progress-center">
                    <?php echo esc_html($overview_data['to_improve_count']); ?>
                </div>
            </div>
            
            <div class="stat-label"><?php echo sprintf(__('von %d', 'alenseo'), $overview_data['total_count']); ?></div>
        </div>
        
        <div class="alenseo-stat-box box-no-keywords">
            <h3><?php _e('Ohne Keywords', 'alenseo'); ?></h3>
            
            <!-- Kreisdiagramm für Seiten ohne Keywords -->
            <div class="circle-progress" data-score="<?php echo esc_attr(($overview_data['total_count'] > 0) ? round(($overview_data['no_keywords_count'] / $overview_data['total_count']) * 100) : 0); ?>">
                <div class="progress-fill">
                    <div class="progress-fill-right"></div>
                    <div class="progress-fill-left"></div>
                </div>
                <div class="progress-center">
                    <?php echo esc_html($overview_data['no_keywords_count']); ?>
                </div>
            </div>
            
            <div class="stat-label"><?php echo sprintf(__('von %d', 'alenseo'), $overview_data['total_count']); ?></div>
        </div>
    </div>
    
    <!-- Versteckte Felder für Chart.js -->
    <input type="hidden" id="optimized-count" value="<?php echo esc_attr($overview_data['optimized_count']); ?>">
    <input type="hidden" id="improve-count" value="<?php echo esc_attr($overview_data['to_improve_count']); ?>">
    <input type="hidden" id="no-keywords-count" value="<?php echo esc_attr($overview_data['no_keywords_count']); ?>">
    
    <?php foreach ($post_type_counts as $type => $data): ?>
    <div class="post-type-data" data-label="<?php echo esc_attr($data['name']); ?>" data-count="<?php echo esc_attr($data['count']); ?>"></div>
    <?php endforeach; ?>

    <!-- Datenvisualisierung mit Charts -->
    <div class="alenseo-charts-container">
        <div class="chart-box">
            <h3><?php _e('SEO-Status-Verteilung', 'alenseo'); ?></h3>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
        
        <div class="chart-box">
            <h3><?php _e('Inhalte nach Typ', 'alenseo'); ?></h3>
            <div class="chart-container">
                <canvas id="postTypesChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Claude AI API-Nutzung -->
    <div class="alenseo-api-usage">
        <h2><?php _e('Claude AI API-Nutzung', 'alenseo'); ?></h2>
        <div class="api-stats-container">
            <div class="api-stat-box">
                <h4><?php _e('Anfragen heute', 'alenseo'); ?></h4>
                <div class="api-stat-value"><?php echo esc_html($claude_api_stats['daily_requests']); ?></div>
                <div class="api-stat-label"><?php echo sprintf(__('von %d', 'alenseo'), $claude_api_stats['daily_limit']); ?></div>
                <div class="api-progress-bar" data-percent="<?php echo esc_attr(($claude_api_stats['daily_requests'] / $claude_api_stats['daily_limit']) * 100); ?>">
                    <div class="api-progress-fill"></div>
                </div>
            </div>
            
            <div class="api-stat-box">
                <h4><?php _e('Tokens genutzt', 'alenseo'); ?></h4>
                <div class="api-stat-value"><?php echo esc_html(number_format($claude_api_stats['monthly_tokens'], 0, ',', '.')); ?></div>
                <div class="api-stat-label"><?php _e('in diesem Monat', 'alenseo'); ?></div>
                <div class="api-progress-bar" data-percent="<?php echo esc_attr(($claude_api_stats['monthly_tokens'] / $claude_api_stats['token_limit']) * 100); ?>">
                    <div class="api-progress-fill"></div>
                </div>
            </div>
            
            <div class="api-stat-box">
                <h4><?php _e('Cache-Treffer', 'alenseo'); ?></h4>
                <div class="api-stat-value"><?php echo esc_html($cache_hit_rate); ?>%</div>
                <div class="api-stat-label"><?php _e('API-Einsparung', 'alenseo'); ?></div>
                <div class="api-progress-bar" data-percent="<?php echo esc_attr($cache_hit_rate); ?>">
                    <div class="api-progress-fill"></div>
                </div>
            </div>
            
            <div class="api-stat-box">
                <h4><?php _e('API-Status', 'alenseo'); ?></h4>
                <div class="api-stat-value" style="color: <?php echo $claude_api->is_api_configured() ? '#46b450' : '#dc3232'; ?>">
                    <?php echo $claude_api->is_api_configured() ? __('Betriebsbereit', 'alenseo') : __('Nicht konfiguriert', 'alenseo'); ?>
                </div>
                <div class="api-stat-label">
                    <?php if (!$claude_api->is_api_configured()): ?>
                    <a href="<?php echo admin_url('admin.php?page=alenseo-settings'); ?>"><?php _e('Konfigurieren', 'alenseo'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inhaltsliste -->
    <div class="alenseo-content-list">
        <h2><?php _e('Inhalte optimieren', 'alenseo'); ?></h2>
        
        <!-- Filter-Bar -->
        <div class="alenseo-filter-bar">
            <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                <input type="hidden" name="page" value="alenseo-seo" />
                
                <div class="filter-group">
                    <label for="filter-status"><?php _e('Status:', 'alenseo'); ?></label>
                    <select name="status" id="filter-status">
                        <option value="" <?php selected($filter_status, ''); ?>><?php _e('Alle', 'alenseo'); ?></option>
                        <option value="optimized" <?php selected($filter_status, 'optimized'); ?>><?php _e('Optimiert', 'alenseo'); ?></option>
                        <option value="to-improve" <?php selected($filter_status, 'to-improve'); ?>><?php _e('Zu verbessern', 'alenseo'); ?></option>
                        <option value="no-keywords" <?php selected($filter_status, 'no-keywords'); ?>><?php _e('Ohne Keywords', 'alenseo'); ?></option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter-type"><?php _e('Typ:', 'alenseo'); ?></label>
                    <select name="type" id="filter-type">
                        <option value="" <?php selected($filter_type, ''); ?>><?php _e('Alle', 'alenseo'); ?></option>
                        <?php foreach ($post_types as $post_type_name => $post_type_obj): ?>
                        <?php if (!in_array($post_type_name, array('attachment'))): ?>
                        <option value="<?php echo esc_attr($post_type_name); ?>" <?php selected($filter_type, $post_type_name); ?>><?php echo esc_html($post_type_obj->labels->name); ?></option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter-search"><?php _e('Suche:', 'alenseo'); ?></label>
                    <input type="text" name="search" id="filter-search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php esc_attr_e('Titel oder Keywords', 'alenseo'); ?>" />
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="button"><?php _e('Filtern', 'alenseo'); ?></button>
                </div>
            </form>
        </div>
        
        <?php
        // Filter-Parameter für die Abfrage erstellen
        $query_args = array(
            'posts_per_page' => 20,
            'paged' => isset($_GET['paged']) ? intval($_GET['paged']) : 1
        );
        
        // Post-Typ Filter
        if (!empty($filter_type)) {
            $query_args['post_type'] = $filter_type;
        } else {
            $query_args['post_type'] = array_keys($post_types);
            $query_args['post_type'] = array_diff($query_args['post_type'], array('attachment'));
        }
        
        // Suche
        if (!empty($filter_search)) {
            $query_args['s'] = $filter_search;
        }
        
        // Alle Beiträge und Seiten abrufen
        $posts_query = new WP_Query($query_args);
        $posts = $posts_query->posts;
        
        // Bulk-Aktionen-Form
        ?>
        <form method="post" id="posts-filter">
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Bulk-Aktion auswählen', 'alenseo'); ?></label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk-Aktionen', 'alenseo'); ?></option>
                        <option value="analyze"><?php _e('Analysieren', 'alenseo'); ?></option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Anwenden', 'alenseo'); ?>">
                </div>
            </div>
        
            <table class="alenseo-posts-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="cb-select-all"></th>
                        <th><?php _e('Titel', 'alenseo'); ?></th>
                        <th><?php _e('Typ', 'alenseo'); ?></th>
                        <th><?php _e('Keywords', 'alenseo'); ?></th>
                        <th><?php _e('Punktzahl', 'alenseo'); ?></th>
                        <th><?php _e('Status', 'alenseo'); ?></th>
                        <th><?php _e('Aktionen', 'alenseo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($posts)): ?>
                    <tr>
                        <td colspan="7"><?php _e('Keine Inhalte gefunden.', 'alenseo'); ?></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                    <?php
                    // SEO-Daten für diesen Beitrag abrufen
                    $seo_data = get_post_meta($post->ID, 'alenseo_seo_data', true);
                    $seo_score = isset($seo_data['score']) ? intval($seo_data['score']) : 0;
                    $seo_keywords = get_post_meta($post->ID, 'alenseo_focus_keywords', true);
                    
                    // Status bestimmen
                    $status = empty($seo_keywords) ? 'no-keywords' : (($seo_score >= 70) ? 'optimized' : 'to-improve');
                    
                    // Filterstatus prüfen
                    if (!empty($filter_status) && $filter_status !== $status) {
                        continue;
                    }
                    
                    // Statustext
                    $status_text = '';
                    switch ($status) {
                        case 'optimized':
                            $status_text = __('Optimiert', 'alenseo');
                            $status_class = 'optimized';
                            break;
                        case 'to-improve':
                            $status_text = __('Zu verbessern', 'alenseo');
                            $status_class = 'to-improve';
                            break;
                        case 'no-keywords':
                            $status_text = __('Keine Keywords', 'alenseo');
                            $status_class = 'no-keywords';
                            break;
                    }
                    ?>
                    <tr id="post-<?php echo $post->ID; ?>">
                        <td><input type="checkbox" name="post[]" value="<?php echo $post->ID; ?>"></td>
                        <td>
                            <a href="<?php echo get_edit_post_link($post->ID); ?>" target="_blank">
                                <?php echo esc_html($post->post_title); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name); ?></td>
                        <td><?php echo empty($seo_keywords) ? '—' : esc_html($seo_keywords); ?></td>
                        <td><?php echo $seo_score > 0 ? $seo_score . '/100' : '—'; ?></td>
                        <td class="status-col">
                            <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </td>
                        <td>
                            <a href="#" class="action-button analyze" data-post-id="<?php echo $post->ID; ?>">
                                <span class="dashicons dashicons-chart-bar"></span> <?php _e('Analysieren', 'alenseo'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=alenseo-page-detail&post_id=' . $post->ID); ?>" class="action-button view">
                                <span class="dashicons dashicons-visibility"></span> <?php _e('Details', 'alenseo'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
        
        <?php
        // Pagination anzeigen
        $total_pages = $posts_query->max_num_pages;
        if ($total_pages > 1) {
            echo '<div class="tablenav bottom"><div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => isset($_GET['paged']) ? intval($_GET['paged']) : 1,
                'add_args' => array()
            ));
            echo '</div></div>';
        }
        ?>
    </div>
</div><!-- .wrap -->