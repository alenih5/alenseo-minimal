<?php
/**
 * SEO-Optimizer-Template für Alenseo SEO
 *
 * Zeigt eine Übersicht aller Seiten und Beiträge mit Optimierungsoptionen an
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

// API-Konfiguration prüfen
$settings = get_option('alenseo_settings', []);
$claude_api_key = isset($settings['claude_api_key']) ? $settings['claude_api_key'] : '';
$openai_api_key = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
$api_configured = !empty($claude_api_key) || !empty($openai_api_key);

/**
 * WordPress-Posts und -Pages mit SEO-Daten laden
 */
function alenseo_get_posts_for_optimizer($args = []) {
    $defaults = [
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    
    $args = wp_parse_args($args, $defaults);
    $posts = get_posts($args);
    $posts_with_seo = [];
    
    foreach ($posts as $post) {
        // SEO-Meta-Daten abrufen
        $seo_title = get_post_meta($post->ID, '_alenseo_seo_title', true);
        $seo_description = get_post_meta($post->ID, '_alenseo_seo_description', true);
        $focus_keyword = get_post_meta($post->ID, '_alenseo_focus_keyword', true);
        $seo_score = get_post_meta($post->ID, '_alenseo_seo_score', true);
        
        // SEO-Status bestimmen
        $seo_status = 'unknown';
        if (!empty($seo_score)) {
            if ($seo_score >= 80) {
                $seo_status = 'good';
            } elseif ($seo_score >= 60) {
                $seo_status = 'needs_improvement';
            } else {
                $seo_status = 'poor';
            }
        }
        
        // Post-Objekt erweitern
        $post->seo_title = $seo_title;
        $post->seo_description = $seo_description;
        $post->keyword = $focus_keyword;
        $post->seo_score = $seo_score ?: 0;
        $post->seo_status = $seo_status;
        $post->permalink = get_permalink($post->ID);
        $post->edit_link = get_edit_post_link($post->ID);
        
        $posts_with_seo[] = $post;
    }
    
    return $posts_with_seo;
}

// Posts laden
$posts = alenseo_get_posts_for_optimizer();

// Globale Dashboard-Instanz für Helper-Funktionen sicherstellen
global $alenseo_dashboard;
if (!isset($alenseo_dashboard) || !is_a($alenseo_dashboard, 'Alenseo_Dashboard')) {
    if (class_exists('Alenseo_Dashboard')) {
        $alenseo_dashboard = new Alenseo_Dashboard();
    }
}

// API-Status prüfen
if (!$api_configured && !current_user_can('manage_options')) {
    ?>
    <div class="wrap alenseo-optimizer-wrap">
        <h1><span class="dashicons dashicons-chart-bar"></span> <?php _e('SEO-Optimierung', 'alenseo'); ?></h1>
        
        <div class="notice notice-warning">
            <p><?php _e('Die Claude API ist nicht konfiguriert. Bitte geben Sie zuerst Ihren API-Schlüssel in den Einstellungen ein.', 'alenseo'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=alenseo-settings'); ?>" class="button button-primary"><?php _e('Zu den Einstellungen', 'alenseo'); ?></a></p>
        </div>
    </div>
    <?php
    return;
}

// Filter und Sortierung vorbereiten
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
$filter_keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '';
$filter_search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Erste Tabellenseite generieren
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Filtern
$filtered_posts = array();
foreach ($posts as $post) {
    // Status-Filter
    if (!empty($filter_status)) {
        if ($filter_status === 'good' && $post->seo_status !== 'good') {
            continue;
        } else if ($filter_status === 'needs_improvement' && $post->seo_status !== 'needs_improvement') {
            continue;
        } else if ($filter_status === 'poor' && $post->seo_status !== 'poor') {
            continue;
        }
    }
    
    // Typ-Filter
    if (!empty($filter_type) && $post->post_type !== $filter_type) {
        continue;
    }
    
    // Keyword-Filter
    if (!empty($filter_keyword)) {
        if ($filter_keyword === 'has' && empty($post->keyword)) {
            continue;
        } else if ($filter_keyword === 'missing' && !empty($post->keyword)) {
            continue;
        }
    }
    
    // Suche
    if (!empty($filter_search)) {
        if (stripos($post->post_title, $filter_search) === false && stripos($post->permalink, $filter_search) === false) {
            continue;
        }
    }
    
    $filtered_posts[] = $post;
}

// Paginierung
$total_items = count($filtered_posts);
$total_pages = ceil($total_items / $items_per_page);
$current_page_items = array_slice($filtered_posts, $offset, $items_per_page);
?>

<div class="wrap alenseo-optimizer-wrap alenseo-bulk-optimizer">
    <h1><span class="dashicons dashicons-chart-bar"></span> <?php _e('SEO-Optimierung', 'alenseo'); ?></h1>
    
    <!-- Notices Container -->
    <div class="alenseo-notices"></div>
    
    <!-- Filter-Bereich -->
    <div class="alenseo-filters">
        <select id="post-type-filter" class="filter-select">
            <option value="any"><?php _e('Alle Post-Typen', 'alenseo'); ?></option>
            <option value="post"><?php _e('Beiträge', 'alenseo'); ?></option>
            <option value="page"><?php _e('Seiten', 'alenseo'); ?></option>
        </select>
        
        <select id="status-filter" class="filter-select">
            <option value=""><?php _e('Alle Status', 'alenseo'); ?></option>
            <option value="good"><?php _e('Gut', 'alenseo'); ?></option>
            <option value="needs_improvement"><?php _e('Verbesserung nötig', 'alenseo'); ?></option>
            <option value="poor"><?php _e('Schlecht', 'alenseo'); ?></option>
        </select>
        
        <input type="search" id="posts-search" placeholder="<?php _e('Posts durchsuchen...', 'alenseo'); ?>">
        
        <button type="button" class="button" onclick="loadPostsTable(1)"><?php _e('Filter anwenden', 'alenseo'); ?></button>
    </div>
    
    <!-- Bulk Actions -->
    <div class="bulk-actions">
        <button type="button" id="bulk-analyze-btn" class="button button-primary">
            <?php _e('Ausgewählte analysieren', 'alenseo'); ?>
        </button>
        <button type="button" class="button" onclick="$('.post-checkbox').prop('checked', true)">
            <?php _e('Alle auswählen', 'alenseo'); ?>
        </button>
        <button type="button" class="button" onclick="$('.post-checkbox').prop('checked', false)">
            <?php _e('Auswahl aufheben', 'alenseo'); ?>
        </button>
    </div>
    
    <!-- Loading State -->
    <div class="posts-loading">
        <span class="alenseo-loading"></span>
        <p><?php _e('Lade Posts...', 'alenseo'); ?></p>
    </div>
    
    <!-- Posts Table Container -->
    <div class="posts-table-container" style="display: none;">
        <table class="alenseo-posts-table">
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" id="select-all-posts">
                    </th>
                    <th><?php _e('Titel', 'alenseo'); ?></th>
                    <th><?php _e('Status', 'alenseo'); ?></th>
                    <th><?php _e('SEO-Score', 'alenseo'); ?></th>
                    <th><?php _e('Focus Keyword', 'alenseo'); ?></th>
                    <th><?php _e('Zuletzt analysiert', 'alenseo'); ?></th>
                    <th><?php _e('Aktionen', 'alenseo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Wird per AJAX geladen -->
            </tbody>
        </table>
        
        <!-- Pagination will be inserted here -->
        <div class="alenseo-pagination"></div>
    </div>
    
    <!-- No API Warning -->
    <?php if (!$api_configured): ?>
    <div class="notice notice-warning">
        <p><?php _e('Keine API konfiguriert. Bitte konfigurieren Sie mindestens eine API in den Einstellungen.', 'alenseo'); ?></p>
        <p><a href="<?php echo admin_url('admin.php?page=alenseo-settings'); ?>" class="button button-primary"><?php _e('Zu den Einstellungen', 'alenseo'); ?></a></p>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Select all checkbox functionality
    $('#select-all-posts').on('change', function() {
        $('.post-checkbox').prop('checked', this.checked);
    });
    
    // Update select-all when individual checkboxes change
    $(document).on('change', '.post-checkbox', function() {
        const totalCheckboxes = $('.post-checkbox').length;
        const checkedCheckboxes = $('.post-checkbox:checked').length;
        $('#select-all-posts').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
});
</script>
            
            <select name="status" id="alenseo-filter-status">
                <option value=""><?php _e('Alle Status', 'alenseo'); ?></option>
                <option value="good" <?php selected($filter_status, 'good'); ?>><?php _e('Gut', 'alenseo'); ?></option>
                <option value="needs_improvement" <?php selected($filter_status, 'needs_improvement'); ?>><?php _e('Verbesserungswürdig', 'alenseo'); ?></option>
                <option value="poor" <?php selected($filter_status, 'poor'); ?>><?php _e('Schlecht', 'alenseo'); ?></option>
            </select>
            
            <select name="type" id="alenseo-filter-post-type">
                <option value=""><?php _e('Alle Typen', 'alenseo'); ?></option>
                <?php foreach ($post_types as $type) : ?>
                    <?php if (in_array($type->name, array('post', 'page'))) : ?>
                        <option value="<?php echo esc_attr($type->name); ?>" <?php selected($filter_type, $type->name); ?>>
                            <?php echo esc_html($type->labels->name); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            
            <select name="keyword" id="alenseo-filter-keyword">
                <option value=""><?php _e('Alle Keywords', 'alenseo'); ?></option>
                <option value="has" <?php selected($filter_keyword, 'has'); ?>><?php _e('Mit Keyword', 'alenseo'); ?></option>
                <option value="missing" <?php selected($filter_keyword, 'missing'); ?>><?php _e('Ohne Keyword', 'alenseo'); ?></option>
            </select>
            
            <input type="search" name="search" id="alenseo-search-input" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Suche...', 'alenseo'); ?>">
            <button type="submit" class="button" id="alenseo-search-button"><?php _e('Filtern', 'alenseo'); ?></button>
            
            <?php if (!empty($filter_status) || !empty($filter_type) || !empty($filter_keyword) || !empty($filter_search)) : ?>
                <a href="<?php echo admin_url('admin.php?page=alenseo-optimizer'); ?>" class="button"><?php _e('Filter zurücksetzen', 'alenseo'); ?></a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Massenoptimierungs-Steuerelemente -->
    <div class="alenseo-bulk-controls">
        <div class="alenseo-bulk-actions">
            <select id="alenseo-bulk-action">
                <option value=""><?php _e('Massenaktionen', 'alenseo'); ?></option>
                <option value="analyze_content"><?php _e('Inhalte analysieren', 'alenseo'); ?></option>
                <option value="generate_keywords"><?php _e('Keywords generieren', 'alenseo'); ?></option>
                <option value="optimize_titles"><?php _e('Titel optimieren', 'alenseo'); ?></option>
                <option value="optimize_meta_descriptions"><?php _e('Meta-Beschreibungen optimieren', 'alenseo'); ?></option>
                <option value="optimize_all"><?php _e('Vollständig optimieren', 'alenseo'); ?></option>
            </select>
            <button type="button" id="alenseo-apply-bulk-action" class="button"><?php _e('Anwenden', 'alenseo'); ?></button>
        </div>
        
        <div class="alenseo-selection-info" style="display: none;">
            <span><strong id="alenseo-selected-count">0</strong> <?php _e('Elemente ausgewählt', 'alenseo'); ?></span>
            <a href="#" id="alenseo-clear-selection"><?php _e('Auswahl aufheben', 'alenseo'); ?></a>
        </div>
    </div>
    
    <!-- Fortschrittsanzeige (zu Beginn ausgeblendet) -->
    <div id="alenseo-progress-bar" style="display: none;">
        <div class="alenseo-progress-label"><?php _e('Optimierung läuft...', 'alenseo'); ?></div>
        <div class="alenseo-progress-track">
            <div class="alenseo-progress-fill"></div>
        </div>
        <div class="alenseo-progress-info">
            <span id="alenseo-progress-current">0</span> <?php _e('von', 'alenseo'); ?> <span id="alenseo-progress-total">0</span> <?php _e('verarbeitet', 'alenseo'); ?>
        </div>
    </div>
    
    <!-- Inhaltsliste -->
    <table class="wp-list-table widefat fixed striped alenseo-content-table">
        <thead>
            <tr>
                <th class="column-cb check-column">
                    <input type="checkbox" id="alenseo-select-all">
                </th>
                <th class="column-title"><?php _e('Titel', 'alenseo'); ?></th>
                <th class="column-type"><?php _e('Typ', 'alenseo'); ?></th>
                <th class="column-keyword"><?php _e('Keyword', 'alenseo'); ?></th>
                <th class="column-url"><?php _e('URL', 'alenseo'); ?></th>
                <th class="column-score"><?php _e('SEO Score', 'alenseo'); ?></th>
                <th class="column-actions"><?php _e('Aktionen', 'alenseo'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($current_page_items)) : ?>
                <tr>
                    <td colspan="7"><?php _e('Keine Inhalte gefunden.', 'alenseo'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($current_page_items as $item) : ?>
                    <tr class="alenseo-content-row" data-id="<?php echo esc_attr($item->ID); ?>" data-type="<?php echo esc_attr($item->post_type); ?>" data-status="<?php echo esc_attr($item->seo_status); ?>" data-has-keyword="<?php echo empty($item->keyword) ? 'missing' : 'has'; ?>">
                        <td class="column-cb check-column">
                            <input type="checkbox" class="alenseo-select-content" value="<?php echo esc_attr($item->ID); ?>">
                        </td>
                        <td class="column-title">
                            <strong><a href="<?php echo esc_url(get_edit_post_link($item->ID)); ?>" target="_blank"><?php echo esc_html($item->post_title); ?></a></strong>
                        </td>
                        <td class="column-type">
                            <?php echo esc_html(get_post_type_object($item->post_type)->labels->singular_name); ?>
                        </td>
                        <td class="column-keyword">
                            <?php if (!empty($item->keyword)) : ?>
                                <span class="alenseo-keyword-badge"><?php echo esc_html($item->keyword); ?></span>
                            <?php else : ?>
                                <span class="alenseo-no-keyword"><?php _e('Nicht gesetzt', 'alenseo'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-url">
                            <a href="<?php echo esc_url($item->permalink); ?>" target="_blank" title="<?php echo esc_attr($item->permalink); ?>"><?php echo esc_url(wp_trim_words($item->permalink, 5, '...')); ?></a>
                        </td>
                        <td class="column-score">
                            <div class="seo-score-badge score-<?php echo esc_attr($item->seo_status); ?>">
                                <?php echo esc_html($item->seo_score); ?>
                            </div>
                        </td>
                        <td class="column-actions">
                            <button type="button" class="button button-small alenseo-action-optimize" data-id="<?php echo esc_attr($item->ID); ?>"><?php _e('Optimieren', 'alenseo'); ?></button>
                            <button type="button" class="button button-small alenseo-action-analyze" data-id="<?php echo esc_attr($item->ID); ?>"><?php _e('Analysieren', 'alenseo'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Paginierung -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo sprintf(_n('%s Element', '%s Elemente', $total_items, 'alenseo'), number_format_i18n($total_items)); ?></span>
            <span class="pagination-links">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $page
                ));
                ?>
            </span>
        </div>
    <?php endif; ?>
    
    <!-- Optimierungsdialog (zu Beginn ausgeblendet) -->
    <div id="alenseo-optimization-dialog" class="alenseo-dialog" style="display: none;">
        <div class="alenseo-dialog-content">
            <div class="alenseo-dialog-header">
                <h2><?php _e('Inhalt optimieren', 'alenseo'); ?></h2>
                <span class="dashicons dashicons-no-alt alenseo-dialog-close"></span>
            </div>
            
            <div class="alenseo-dialog-body">
                <!-- Keyword-Bereich -->
                <div class="alenseo-keyword-section">
                    <h3><?php _e('Fokus-Keyword', 'alenseo'); ?></h3>
                    <p><?php _e('Aktuell: ', 'alenseo'); ?><strong id="alenseo-current-keyword-value">Nicht gesetzt</strong></p>
                    
                    <button type="button" id="alenseo-generate-keywords" class="button button-primary"><?php _e('Keywords generieren', 'alenseo'); ?></button>
                    
                    <div class="alenseo-keywords-loader" style="display: none;">
                        <span class="spinner is-active"></span> <?php _e('Keywords werden generiert...', 'alenseo'); ?>
                    </div>
                    
                    <div class="alenseo-keyword-suggestions" style="display: none;">
                        <h4><?php _e('Keyword-Vorschläge', 'alenseo'); ?></h4>
                        <p><?php _e('Klicken Sie auf ein Keyword, um es auszuwählen:', 'alenseo'); ?></p>
                        <div class="alenseo-keyword-list"></div>
                    </div>
                </div>
                
                <!-- Optimierungsoptionen -->
                <div class="alenseo-optimization-options">
                    <h3><?php _e('Was soll optimiert werden?', 'alenseo'); ?></h3>
                    
                    <label>
                        <input type="checkbox" name="optimize_title" checked> 
                        <?php _e('Titel', 'alenseo'); ?>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="optimize_meta_description" checked> 
                        <?php _e('Meta-Description', 'alenseo'); ?>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="optimize_content"> 
                        <?php _e('Content-Vorschläge', 'alenseo'); ?>
                    </label>
                    
                    <div class="alenseo-optimization-actions">
                        <button type="button" id="alenseo-start-optimization" class="button button-primary"><?php _e('Optimierung starten', 'alenseo'); ?></button>
                        
                        <div class="alenseo-optimizer-loader" style="display: none;">
                            <span class="spinner is-active"></span> <?php _e('Optimierung läuft...', 'alenseo'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Ergebnisbereich -->
                <div class="alenseo-results-section" style="display: none;">
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
