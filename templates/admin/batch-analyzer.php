<?php
/**
 * Template für den Batch-Analyzer
 *
 * @package    Alenseo
 * @subpackage Alenseo/templates/admin
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap alenseo-batch-analyzer">
    <h1><?php _e('Batch-Analyse', 'alenseo'); ?></h1>
    
    <div class="alenseo-batch-controls">
        <div class="alenseo-bulk-actions">
            <select id="alenseo-bulk-action">
                <option value=""><?php _e('Massen-Aktionen', 'alenseo'); ?></option>
                <option value="analyze"><?php _e('Ausgewählte analysieren', 'alenseo'); ?></option>
                <option value="export"><?php _e('Ausgewählte exportieren', 'alenseo'); ?></option>
                <option value="clear"><?php _e('SEO-Daten löschen', 'alenseo'); ?></option>
            </select>
            <button id="alenseo-bulk-apply" class="button" disabled>
                <?php _e('Anwenden', 'alenseo'); ?>
            </button>
        </div>
        
        <div class="alenseo-action-buttons">
            <button id="alenseo-batch-analyze" class="button button-primary">
                <span class="dashicons dashicons-search" style="vertical-align: middle; margin-right: 5px;"></span>
                <?php _e('Ausgewählte Beiträge analysieren', 'alenseo'); ?>
            </button>
            
            <button id="alenseo-batch-cancel" class="button" style="display: none;">
                <span class="dashicons dashicons-no" style="vertical-align: middle; margin-right: 5px;"></span>
                <?php _e('Analyse abbrechen', 'alenseo'); ?>
            </button>
            
            <button id="alenseo-export-csv" class="button">
                <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
                <?php _e('Als CSV exportieren', 'alenseo'); ?>
            </button>
        </div>
    </div>
    
    <div class="alenseo-batch-progress-container" style="margin: 20px 0;">
        <div id="alenseo-batch-progress"></div>
        <div id="alenseo-batch-status" class="alenseo-batch-status"></div>
    </div>
    
    <div class="alenseo-batch-filters">
        <select id="alenseo-post-type-filter">
            <option value=""><?php _e('Alle Beitragstypen', 'alenseo'); ?></option>
            <?php
            $post_types = get_post_types(array('public' => true), 'objects');
            foreach ($post_types as $post_type) {
                echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->label) . '</option>';
            }
            ?>
        </select>
        
        <select id="alenseo-seo-status-filter">
            <option value=""><?php _e('Alle SEO-Status', 'alenseo'); ?></option>
            <option value="good"><?php _e('Gut', 'alenseo'); ?></option>
            <option value="ok"><?php _e('OK', 'alenseo'); ?></option>
            <option value="poor"><?php _e('Schlecht', 'alenseo'); ?></option>
            <option value="no_keyword"><?php _e('Kein Keyword', 'alenseo'); ?></option>
        </select>
        
        <input type="text" id="alenseo-search-filter" placeholder="<?php esc_attr_e('Suche...', 'alenseo'); ?>">
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="check-column">
                    <input type="checkbox" id="alenseo-select-all">
                </th>
                <th class="alenseo-sortable" data-column="title" data-direction="asc">
                    <?php _e('Titel', 'alenseo'); ?>
                </th>
                <th class="alenseo-sortable" data-column="type" data-direction="asc">
                    <?php _e('Typ', 'alenseo'); ?>
                </th>
                <th class="alenseo-sortable" data-column="seo-score" data-direction="desc">
                    <?php _e('SEO-Score', 'alenseo'); ?>
                </th>
                <th class="alenseo-sortable" data-column="status" data-direction="asc">
                    <?php _e('Status', 'alenseo'); ?>
                </th>
                <th class="alenseo-sortable" data-column="last-analysis" data-direction="desc">
                    <?php _e('Letzte Analyse', 'alenseo'); ?>
                </th>
            </tr>
        </thead>
        <tbody id="alenseo-posts-list">
            <?php
            $args = array(
                'post_type' => 'any',
                'posts_per_page' => 20,
                'paged' => 1
            );
            
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $seo_score = get_post_meta($post_id, '_alenseo_seo_score', true);
                    $seo_status = get_post_meta($post_id, '_alenseo_seo_status', true);
                    $last_analysis = get_post_meta($post_id, '_alenseo_last_analysis', true);
                    
                    // Status-Klasse bestimmen
                    $status_class = '';
                    switch ($seo_status) {
                        case 'good':
                            $status_class = 'alenseo-status-good';
                            break;
                        case 'ok':
                            $status_class = 'alenseo-status-ok';
                            break;
                        case 'poor':
                            $status_class = 'alenseo-status-poor';
                            break;
                        case 'no_keyword':
                            $status_class = 'alenseo-status-no-keyword';
                            break;
                    }
                    ?>
                    <tr data-post-type="<?php echo esc_attr(get_post_type()); ?>" 
                        data-seo-status="<?php echo esc_attr($seo_status); ?>"
                        data-main-keyword="<?php echo esc_attr(get_post_meta($post_id, '_alenseo_keyword', true)); ?>"
                        data-meta-description="<?php echo esc_attr(get_post_meta($post_id, '_alenseo_meta_description', true)); ?>">
                        <td>
                            <input type="checkbox" class="alenseo-post-checkbox" value="<?php echo esc_attr($post_id); ?>">
                        </td>
                        <td data-column="title">
                            <strong>
                                <a href="<?php echo get_edit_post_link(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </strong>
                        </td>
                        <td data-column="type"><?php echo get_post_type_object(get_post_type())->labels->singular_name; ?></td>
                        <td data-column="seo-score">
                            <?php if ($seo_score !== '') : ?>
                                <div class="alenseo-score-bar">
                                    <div class="alenseo-score-fill" style="width: <?php echo esc_attr($seo_score); ?>%;"></div>
                                    <span class="alenseo-score-text"><?php echo esc_html($seo_score); ?>%</span>
                                </div>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td data-column="status">
                            <span class="alenseo-status <?php echo esc_attr($status_class); ?>">
                                <?php
                                switch ($seo_status) {
                                    case 'good':
                                        _e('Gut', 'alenseo');
                                        break;
                                    case 'ok':
                                        _e('OK', 'alenseo');
                                        break;
                                    case 'poor':
                                        _e('Schlecht', 'alenseo');
                                        break;
                                    case 'no_keyword':
                                        _e('Kein Keyword', 'alenseo');
                                        break;
                                    default:
                                        _e('Nicht analysiert', 'alenseo');
                                }
                                ?>
                            </span>
                        </td>
                        <td data-column="last-analysis">
                            <?php
                            if ($last_analysis) {
                                echo human_time_diff(strtotime($last_analysis), current_time('timestamp')) . ' ' . __('her', 'alenseo');
                            } else {
                                _e('Nie', 'alenseo');
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="6"><?php _e('Keine Beiträge gefunden.', 'alenseo'); ?></td>
                </tr>
                <?php
            }
            
            wp_reset_postdata();
            ?>
        </tbody>
    </table>
    
    <div class="alenseo-pagination">
        <?php
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $query->max_num_pages,
            'current' => 1
        ));
        ?>
    </div>
</div>

<style>
.alenseo-batch-analyzer {
    margin: 20px;
}

.alenseo-batch-controls {
    margin: 20px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.alenseo-bulk-actions {
    display: flex;
    gap: 5px;
    align-items: center;
}

.alenseo-action-buttons {
    display: flex;
    gap: 5px;
}

.alenseo-batch-progress-container {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.alenseo-batch-status {
    margin-top: 10px;
    font-size: 14px;
}

.alenseo-batch-filters {
    margin: 20px 0;
}

.alenseo-batch-filters select,
.alenseo-batch-filters input {
    margin-right: 10px;
}

.alenseo-score-bar {
    background: #f0f0f1;
    height: 20px;
    border-radius: 3px;
    position: relative;
    overflow: hidden;
}

.alenseo-score-fill {
    background: #2271b1;
    height: 100%;
    transition: width 0.3s ease;
}

.alenseo-score-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #fff;
    font-size: 12px;
    text-shadow: 0 0 2px rgba(0,0,0,0.5);
}

.alenseo-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.alenseo-status-good {
    background: #dff0d8;
    color: #3c763d;
}

.alenseo-status-ok {
    background: #fcf8e3;
    color: #8a6d3b;
}

.alenseo-status-poor {
    background: #f2dede;
    color: #a94442;
}

.alenseo-status-no-keyword {
    background: #f5f5f5;
    color: #777;
}

.alenseo-error {
    color: #dc3232;
    margin-top: 10px;
}

.alenseo-batch-errors {
    margin-top: 10px;
    padding: 10px;
    background: #f8f8f8;
    border: 1px solid #ddd;
}

.alenseo-batch-errors h4 {
    margin: 0 0 10px 0;
    color: #dc3232;
}

.alenseo-batch-errors ul {
    margin: 0;
    padding-left: 20px;
}

.alenseo-pagination {
    margin-top: 20px;
    text-align: center;
}

.alenseo-sortable {
    cursor: pointer;
    position: relative;
    padding-right: 20px;
}

.alenseo-sortable:hover {
    background-color: #f0f0f1;
}

.alenseo-sort-indicator {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    color: #2271b1;
}

.alenseo-sortable.asc .alenseo-sort-indicator,
.alenseo-sortable.desc .alenseo-sort-indicator {
    opacity: 1;
}

@media screen and (max-width: 782px) {
    .alenseo-batch-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .alenseo-bulk-actions,
    .alenseo-action-buttons {
        width: 100%;
    }
    
    .alenseo-bulk-actions select,
    .alenseo-bulk-actions button,
    .alenseo-action-buttons button {
        width: 100%;
        margin: 5px 0;
    }
}
</style> 