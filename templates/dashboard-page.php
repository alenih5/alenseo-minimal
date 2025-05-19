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
            <select name="status">
                <option value=""><?php _e('Alle Status', 'alenseo'); ?></option>
                <option value="good" <?php selected($filter_status, 'good'); ?>><?php _e('Gut', 'alenseo'); ?></option>
                <option value="needs_improvement" <?php selected($filter_status, 'needs_improvement'); ?>><?php _e('Verbesserungswürdig', 'alenseo'); ?></option>
                <option value="poor" <?php selected($filter_status, 'poor'); ?>><?php _e('Schlecht', 'alenseo'); ?></option>
            </select>
            <select name="type">
                <option value=""><?php _e('Alle Typen', 'alenseo'); ?></option>
                <option value="post" <?php selected($filter_type, 'post'); ?>><?php _e('Beiträge', 'alenseo'); ?></option>
                <option value="page" <?php selected($filter_type, 'page'); ?>><?php _e('Seiten', 'alenseo'); ?></option>
            </select>
            <input type="search" name="search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Suche...', 'alenseo'); ?>">
            <button type="submit" class="button"><?php _e('Filtern', 'alenseo'); ?></button>
        </form>
    </div>

    <!-- Haupttabelle -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Titel', 'alenseo'); ?></th>
                <th><?php _e('Typ', 'alenseo'); ?></th>
                <th><?php _e('SEO Score', 'alenseo'); ?></th>
                <th><?php _e('Status', 'alenseo'); ?></th>
                <th><?php _e('Aktionen', 'alenseo'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($overview_data['items'] as $item) : ?>
            <tr>
                <td>
                    <strong><a href="<?php echo esc_url(get_edit_post_link($item->ID)); ?>"><?php echo esc_html($item->post_title); ?></a></strong>
                </td>
                <td><?php echo esc_html(get_post_type_label($item->post_type)); ?></td>
                <td>
                    <div class="seo-score-badge score-<?php echo esc_attr($item->seo_status); ?>">
                        <?php echo esc_html($item->seo_score); ?>
                    </div>
                </td>
                <td>
                    <span class="status-badge status-<?php echo esc_attr($item->seo_status); ?>">
                        <?php echo esc_html($item->seo_status_label); ?>
                    </span>
                </td>
                <td>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=alenseo-optimizer&post_id=' . $item->ID)); ?>" class="button button-small">
                        <?php _e('Optimieren', 'alenseo'); ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
