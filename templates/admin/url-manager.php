<?php
/**
 * SEO AI Master URL Manager - WordPress Template
 * Vollständige URL-Verwaltung mit fortgeschrittenen Filteroptionen
 */

if (!defined('ABSPATH')) {
    exit;
}

// WordPress Security: Capability Check
if (!current_user_can('manage_options')) {
    wp_die(__('Sie haben keine Berechtigung für diese Seite.', 'seo-ai-master'));
}

// Security: Nonce für CSRF Protection
$nonce = wp_create_nonce('seo_ai_nonce');

// URL Manager Instanz
$url_manager = \SEOAI\URLManager::get_instance();

// Request Parameter sanitizen
$search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$selected_type = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'all';
$seo_status = isset($_GET['seo_status']) ? sanitize_key($_GET['seo_status']) : 'all';
$orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'date';
$order = isset($_GET['order']) ? sanitize_key(strtoupper($_GET['order'])) : 'DESC';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$view_mode = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'table';

// Parameter validieren
$allowed_types = ['all', 'post', 'page', 'product'];
$allowed_status = ['all', 'analyzed', 'not_analyzed', 'critical', 'good'];
$allowed_orderby = ['title', 'type', 'score', 'date', 'modified'];
$allowed_order = ['ASC', 'DESC'];
$allowed_views = ['table', 'grid', 'compact'];

if (!in_array($selected_type, $allowed_types, true)) $selected_type = 'all';
if (!in_array($seo_status, $allowed_status, true)) $seo_status = 'all';
if (!in_array($orderby, $allowed_orderby, true)) $orderby = 'date';
if (!in_array($order, $allowed_order, true)) $order = 'DESC';
if (!in_array($view_mode, $allowed_views, true)) $view_mode = 'table';

// URL-Statistiken laden
$stats = $url_manager->get_url_stats();

// URL-Abfrage durchführen
$url_query_args = [
    'posts_per_page' => 20,
    'paged' => $paged,
    'search' => $search_term,
    'post_type' => $selected_type,
    'seo_status' => $seo_status,
    'orderby' => $orderby,
    'order' => $order
];

$url_query = $url_manager->get_urls($url_query_args);

// Bulk Actions verarbeiten
if ('POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer('seo_ai_url_bulk', 'seo_ai_url_bulk_nonce')) {
    $action = sanitize_key($_POST['bulk_action'] ?? '');
    $post_ids = array_map('intval', (array)($_POST['post_ids'] ?? []));
    
    if (!empty($post_ids) && !empty($action)) {
        $message = '';
        switch ($action) {
            case 'analyze':
                foreach ($post_ids as $post_id) {
                    \SEOAI\SEOAnalyzer::get_instance()->analyze_post($post_id);
                }
                $message = sprintf(__('%d URLs analysiert', 'seo-ai-master'), count($post_ids));
                break;
                
            case 'generate_meta':
                foreach ($post_ids as $post_id) {
                    // Meta-Daten generieren
                    $content = get_post_field('post_content', $post_id);
                    $title = \SEOAI\AI\Connector::get_instance()->generate_meta_title($content, '');
                    $desc = \SEOAI\AI\Connector::get_instance()->generate_meta_description($content, '');
                    update_post_meta($post_id, '_seo_ai_title', $title);
                    update_post_meta($post_id, '_seo_ai_description', $desc);
                }
                $message = sprintf(__('Meta-Daten für %d URLs generiert', 'seo-ai-master'), count($post_ids));
                break;
                
            case 'remove_monitoring':
                foreach ($post_ids as $post_id) {
                    delete_post_meta($post_id, '_seo_ai_score');
                    delete_post_meta($post_id, '_seo_ai_title');
                    delete_post_meta($post_id, '_seo_ai_description');
                }
                $message = sprintf(__('%d URLs aus SEO-Monitoring entfernt', 'seo-ai-master'), count($post_ids));
                break;
        }
        
        if ($message) {
            echo '<div class="updated notice is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}

// Content Types für Dropdown
$content_types = [
    'all' => sprintf(__('Alle Inhalte (%d)', 'seo-ai-master'), $stats['total_urls']),
    'post' => sprintf(__('Beiträge (%d)', 'seo-ai-master'), $stats['breakdown']['posts']),
    'page' => sprintf(__('Seiten (%d)', 'seo-ai-master'), $stats['breakdown']['pages']),
];

if ($stats['breakdown']['products'] > 0) {
    $content_types['product'] = sprintf(__('Produkte (%d)', 'seo-ai-master'), $stats['breakdown']['products']);
}

// SEO Status Optionen
$seo_status_options = [
    'all' => __('Alle Status', 'seo-ai-master'),
    'analyzed' => sprintf(__('Analysiert (%d)', 'seo-ai-master'), $stats['analyzed']),
    'not_analyzed' => sprintf(__('Nicht analysiert (%d)', 'seo-ai-master'), $stats['not_analyzed']),
    'critical' => sprintf(__('Kritisch (< 60) (%d)', 'seo-ai-master'), $stats['critical_urls']),
    'good' => sprintf(__('Gut (≥ 80)', 'seo-ai-master'))
];

// Beispiel-Status für KI-Modelle (später dynamisch setzen)
$claude_connected = true;
$gpt4o_connected = true;
$gpt4_connected = false;
$gemini_connected = true;
?>

<style>
/* SEO AI Master Styles - Vollständige CSS aus den Prototypen */
.seo-ai-master-plugin * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.seo-ai-master-plugin .wrap {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    margin: -20px -20px 0 -22px;
    padding: 20px;
    color: #1a1a1a;
}

.seo-ai-master-plugin .wrap h1 {
    color: white;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.seo-ai-master-plugin .wrap h1 i {
    background: linear-gradient(45deg, #f093fb, #f5576c);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 2.5rem;
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
}

.stat-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-card-title {
    color: white;
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    color: white;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-card-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

/* URL Management Section */
.url-management-section {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Filter Controls */
.filter-controls {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.85rem;
    font-weight: 500;
}

.filter-input, .filter-select {
    background: rgba(30, 32, 44, 0.95) !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.2) !important;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    min-width: 150px;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: #f093fb;
    box-shadow: 0 0 0 3px rgba(240, 147, 251, 0.1);
}

.filter-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

/* View Mode Toggle */
.view-toggle {
    display: flex;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 0.25rem;
    gap: 0.25rem;
}

.view-btn {
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
}

.view-btn.active {
    background: linear-gradient(45deg, #f093fb, #f5576c);
    color: white;
}

.view-btn:hover:not(.active) {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(45deg, #f093fb, #f5576c);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(240, 147, 251, 0.3);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

.btn-success {
    background: linear-gradient(45deg, #10b981, #059669);
    color: white;
}

.btn-danger {
    background: linear-gradient(45deg, #ef4444, #dc2626);
    color: white;
}

.btn-small {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
}

/* URL Table */
.url-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    overflow: hidden;
}

.url-table th {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.url-table th a {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.url-table th a:hover {
    color: #f093fb;
}

.url-table td {
    padding: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
    vertical-align: middle;
}

.url-table tr:hover {
    background: rgba(255, 255, 255, 0.05);
}

.url-title {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.url-path {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
}

.url-meta {
    font-size: 0.85rem;
    line-height: 1.4;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Badge Styles */
.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-post {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.badge-page {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.badge-product {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.badge-analyzed {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.badge-not-analyzed {
    background: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
}

.badge-critical {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

/* Score Display */
.score-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.score-number {
    font-weight: 700;
    min-width: 30px;
}

.score-number.good { color: #10b981; }
.score-number.warning { color: #f59e0b; }
.score-number.critical { color: #ef4444; }

.score-bar {
    width: 60px;
    height: 8px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    overflow: hidden;
}

.score-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    padding: 0.4rem 0.6rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.action-btn:hover {
    transform: scale(1.1);
}

/* Bulk Actions */
.bulk-actions {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1rem;
    display: none;
}

.bulk-actions.show {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.bulk-selection-info {
    color: white;
    font-weight: 500;
}

.bulk-action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.pagination a, .pagination span {
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.pagination a {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.pagination a:hover {
    background: rgba(255, 255, 255, 0.2);
}

.pagination .current {
    background: linear-gradient(45deg, #f093fb, #f5576c);
    color: white;
}

/* Loading States */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .section-header {
        flex-direction: column;
        align-items: stretch;
    }
}

@media (max-width: 768px) {
    .seo-ai-master-plugin .wrap {
        margin: -20px -10px 0 -12px;
        padding: 10px;
    }
    
    .url-table {
        font-size: 0.8rem;
    }
    
    .url-table th,
    .url-table td {
        padding: 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .bulk-actions.show {
        flex-direction: column;
        align-items: stretch;
    }
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255, 255, 255, 0.7);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    color: white;
    margin-bottom: 0.5rem;
}

/* Notification System */
.seo-ai-notification {
    position: fixed;
    top: 32px;
    right: 20px;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    color: white;
    font-weight: 500;
    z-index: 100000;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    animation: slideInRight 0.3s ease-out;
}

.seo-ai-notification.success {
    background: linear-gradient(45deg, #10b981, #059669);
}

.seo-ai-notification.error {
    background: linear-gradient(45deg, #ef4444, #dc2626);
}

.seo-ai-notification.info {
    background: linear-gradient(45deg, #3b82f6, #1e40af);
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

/* Erste Reihe: 4 Boxen */
.stats-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}
</style>

<div class="seo-ai-master-plugin">
    <div class="wrap">
        <!-- Plugin-Header wie im Dashboard -->
        <div class="header">
            <div class="logo">
                <i class="fas fa-list-ul" aria-hidden="true"></i>
                SEO AI Master
            </div>
            <div class="header-controls">
                <div class="api-status">
                    <span class="api-indicator <?php echo $claude_connected ? 'online' : 'offline'; ?>">CLAUDE</span>
                    <span class="api-indicator <?php echo $gpt4o_connected ? 'online' : 'offline'; ?>">GPT-4O</span>
                    <span class="api-indicator <?php echo $gpt4_connected ? 'online' : 'offline'; ?>">GPT-4</span>
                    <span class="api-indicator <?php echo $gemini_connected ? 'online' : 'offline'; ?>">GEMINI</span>
                </div>
                <div class="user-menu">
                    <i class="fas fa-user"></i>
                    <?php echo esc_html(wp_get_current_user()->display_name); ?>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
        <!-- Navigation wie im Dashboard -->
        <nav class="nav-tabs">
            <a href="<?php echo admin_url('admin.php?page=seo-ai-master'); ?>" class="nav-tab">Dashboard</a>
            <a href="<?php echo admin_url('admin.php?page=seo-ai-urls'); ?>" class="nav-tab active">URLs Verwalten</a>
            <a href="<?php echo admin_url('admin.php?page=seo-ai-optimizer'); ?>" class="nav-tab">AI Optimizer</a>
            <a href="<?php echo admin_url('admin.php?page=seo-ai-analytics'); ?>" class="nav-tab">Analytics</a>
            <a href="<?php echo admin_url('admin.php?page=seo-ai-settings'); ?>" class="nav-tab">Einstellungen</a>
        </nav>
        <h1>
            <i class="fas fa-list-ul" aria-hidden="true"></i>
            <?php _e('URL Management', 'seo-ai-master'); ?>
        </h1>
        
        <!-- Statistics Grid: Erste Reihe mit 4 Boxen -->
        <div class="stats-grid-4">
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3 class="stat-card-title"><i class="fas fa-globe" aria-hidden="true"></i> <?php _e('Gesamt URLs', 'seo-ai-master'); ?></h3>
                </div>
                <div class="stat-card-value"><?php echo number_format_i18n($stats['total_urls']); ?></div>
                <div class="stat-card-subtitle">
                    <?php printf(__('%d Beiträge • %d Seiten • %d Produkte', 'seo-ai-master'), $stats['breakdown']['posts'], $stats['breakdown']['pages'], $stats['breakdown']['products']); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3 class="stat-card-title"><i class="fas fa-chart-line" aria-hidden="true"></i> <?php _e('SEO Analysiert', 'seo-ai-master'); ?></h3>
                </div>
                <div class="stat-card-value"><?php echo number_format_i18n($stats['analyzed']); ?></div>
                <div class="stat-card-subtitle">
                    <?php printf(__('Ø Score: %d • %d nicht analysiert', 'seo-ai-master'), $stats['avg_score'], $stats['not_analyzed']); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3 class="stat-card-title"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <?php _e('Kritische URLs', 'seo-ai-master'); ?></h3>
                </div>
                <div class="stat-card-value" style="color: #ef4444;"><?php echo number_format_i18n($stats['critical_urls']); ?></div>
                <div class="stat-card-subtitle"><?php _e('Score < 60 - benötigen Aufmerksamkeit', 'seo-ai-master'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3 class="stat-card-title"><i class="fas fa-tags" aria-hidden="true"></i> <?php _e('Fehlende Meta-Daten', 'seo-ai-master'); ?></h3>
                </div>
                <div class="stat-card-value" style="color: #f59e0b;"><?php echo number_format_i18n($stats['missing_titles']); ?></div>
                <div class="stat-card-subtitle">
                    <?php printf(__('%d ohne Titel • %d ohne Beschreibung', 'seo-ai-master'), $stats['missing_titles'], $stats['missing_descriptions']); ?>
                </div>
            </div>
        </div>

        <!-- URL Management Section -->
        <div class="url-management-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-cogs" aria-hidden="true"></i>
                    <?php _e('URL Verwaltung', 'seo-ai-master'); ?>
                </h2>
                
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <div class="view-toggle">
                        <button class="view-btn <?php echo $view_mode === 'table' ? 'active' : ''; ?>" 
                                onclick="changeView('table')" title="<?php esc_attr_e('Tabellenansicht', 'seo-ai-master'); ?>">
                            <i class="fas fa-table" aria-hidden="true"></i>
                        </button>
                        <button class="view-btn <?php echo $view_mode === 'grid' ? 'active' : ''; ?>" 
                                onclick="changeView('grid')" title="<?php esc_attr_e('Rasteransicht', 'seo-ai-master'); ?>">
                            <i class="fas fa-th" aria-hidden="true"></i>
                        </button>
                        <button class="view-btn <?php echo $view_mode === 'compact' ? 'active' : ''; ?>" 
                                onclick="changeView('compact')" title="<?php esc_attr_e('Kompakte Ansicht', 'seo-ai-master'); ?>">
                            <i class="fas fa-list" aria-hidden="true"></i>
                        </button>
                    </div>
                    
                    <button class="btn btn-primary" onclick="exportURLs()">
                        <i class="fas fa-download" aria-hidden="true"></i>
                        <?php _e('Export', 'seo-ai-master'); ?>
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <form method="get" class="filter-controls">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'seo-ai-urls'); ?>">
                
                <div class="filter-group">
                    <label class="filter-label" for="search-input"><?php _e('Suche', 'seo-ai-master'); ?></label>
                    <input type="search" 
                           id="search-input"
                           name="s" 
                           class="filter-input" 
                           value="<?php echo esc_attr($search_term); ?>" 
                           placeholder="<?php esc_attr_e('URLs durchsuchen...', 'seo-ai-master'); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="type-filter"><?php _e('Content-Typ', 'seo-ai-master'); ?></label>
                    <select id="type-filter" name="type" class="filter-select">
                        <?php foreach ($content_types as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_type, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label" for="status-filter"><?php _e('SEO Status', 'seo-ai-master'); ?></label>
                    <select id="status-filter" name="seo_status" class="filter-select">
                        <?php foreach ($seo_status_options as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($seo_status, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <?php _e('Filtern', 'seo-ai-master'); ?>
                    </button>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <a href="<?php echo esc_url(remove_query_arg(['s', 'type', 'seo_status', 'orderby', 'order', 'paged'])); ?>" 
                       class="btn btn-secondary">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <?php _e('Zurücksetzen', 'seo-ai-master'); ?>
                    </a>
                </div>
            </form>

            <!-- URL Table -->
            <?php if ($url_query->have_posts()): ?>
                <table class="url-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;">
                                <input type="checkbox" id="select-all-urls" onchange="toggleAllURLs(this)">
                            </th>
                            <th>
                                <a href="<?php echo esc_url(add_query_arg(['orderby' => 'title', 'order' => ($orderby === 'title' && $order === 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                    <?php _e('URL / Titel', 'seo-ai-master'); ?>
                                    <?php if ($orderby === 'title'): ?>
                                        <i class="fas fa-chevron-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th><?php _e('Typ', 'seo-ai-master'); ?></th>
                            <th><?php _e('Meta Titel', 'seo-ai-master'); ?></th>
                            <th><?php _e('Meta Beschreibung', 'seo-ai-master'); ?></th>
                            <th>
                                <a href="<?php echo esc_url(add_query_arg(['orderby' => 'score', 'order' => ($orderby === 'score' && $order === 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                    <?php _e('SEO Score', 'seo-ai-master'); ?>
                                    <?php if ($orderby === 'score'): ?>
                                        <i class="fas fa-chevron-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="<?php echo esc_url(add_query_arg(['orderby' => 'modified', 'order' => ($orderby === 'modified' && $order === 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                    <?php _e('Letztes Update', 'seo-ai-master'); ?>
                                    <?php if ($orderby === 'modified'): ?>
                                        <i class="fas fa-chevron-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th><?php _e('Aktionen', 'seo-ai-master'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($url_query->have_posts()): $url_query->the_post(); 
                            $post_id = get_the_ID();
                            $score = get_post_meta($post_id, '_seo_ai_score', true);
                            $meta_title = get_post_meta($post_id, '_seo_ai_title', true);
                            $meta_desc = get_post_meta($post_id, '_seo_ai_description', true);
                            $score = $score ? max(0, min(100, intval($score))) : 0;
                            
                            $score_class = 'critical';
                            $score_color = '#ef4444';
                            if ($score >= 80) {
                                $score_class = 'good';
                                $score_color = '#10b981';
                            } elseif ($score >= 60) {
                                $score_class = 'warning';
                                $score_color = '#f59e0b';
                            }
                            
                            $post_type_obj = get_post_type_object(get_post_type());
                            $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : get_post_type();
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" 
                                           name="bulk_urls[]" 
                                           value="<?php echo esc_attr($post_id); ?>" 
                                           class="url-checkbox"
                                           onchange="updateBulkSelection()">
                                </td>
                                <td>
                                    <div class="url-title">
                                        <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" 
                                           style="color: #f093fb; text-decoration: none; font-weight: 600;">
                                            <?php echo esc_html(wp_trim_words(get_the_title(), 6)); ?>
                                        </a>
                                    </div>
                                    <div class="url-path">
                                        <i class="fas fa-link" aria-hidden="true"></i>
                                        <?php echo esc_html(wp_make_link_relative(get_permalink())); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo esc_attr(get_post_type()); ?>">
                                        <?php echo esc_html($post_type_label); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($meta_title): ?>
                                        <div class="url-meta" title="<?php echo esc_attr($meta_title); ?>">
                                            <?php echo esc_html(wp_trim_words($meta_title, 8)); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: rgba(255,255,255,0.5); font-style: italic;">
                                            <?php _e('Nicht gesetzt', 'seo-ai-master'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($meta_desc): ?>
                                        <div class="url-meta" title="<?php echo esc_attr($meta_desc); ?>">
                                            <?php echo esc_html(wp_trim_words($meta_desc, 10)); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: rgba(255,255,255,0.5); font-style: italic;">
                                            <?php _e('Nicht gesetzt', 'seo-ai-master'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="score-display">
                                        <span class="score-number <?php echo esc_attr($score_class); ?>">
                                            <?php echo esc_html($score ?: '—'); ?>
                                        </span>
                                        <?php if ($score > 0): ?>
                                            <div class="score-bar">
                                                <div class="score-fill" 
                                                     style="width: <?php echo esc_attr($score); ?>%; background: <?php echo esc_attr($score_color); ?>;"></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <time datetime="<?php echo esc_attr(get_the_modified_date('c')); ?>" 
                                          style="font-size: 0.85rem;">
                                        <?php echo esc_html(human_time_diff(get_the_modified_time('U'), current_time('timestamp')) . ' ' . __('ago', 'seo-ai-master')); ?>
                                    </time>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn btn-primary btn-small" 
                                                onclick="analyzeURL(<?php echo esc_attr($post_id); ?>)"
                                                title="<?php esc_attr_e('SEO analysieren', 'seo-ai-master'); ?>">
                                            <i class="fas fa-search" aria-hidden="true"></i>
                                        </button>
                                        
                                        <button class="action-btn btn-secondary btn-small" 
                                                onclick="generateMeta(<?php echo esc_attr($post_id); ?>)"
                                                title="<?php esc_attr_e('Meta-Daten generieren', 'seo-ai-master'); ?>">
                                            <i class="fas fa-magic" aria-hidden="true"></i>
                                        </button>
                                        
                                        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" 
                                           class="action-btn btn-secondary btn-small" 
                                           target="_blank" 
                                           rel="noopener noreferrer"
                                           title="<?php esc_attr_e('URL ansehen', 'seo-ai-master'); ?>">
                                            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                                        </a>
                                        
                                        <button class="action-btn btn-danger btn-small" 
                                                onclick="removeFromMonitoring(<?php echo esc_attr($post_id); ?>)"
                                                title="<?php esc_attr_e('Aus Monitoring entfernen', 'seo-ai-master'); ?>">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($url_query->max_num_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $current_query_args = array_filter($_GET, function($k) {
                            return !in_array($k, ['paged'], true);
                        }, ARRAY_FILTER_USE_KEY);
                        
                        echo paginate_links([
                            'base' => esc_url_raw(add_query_arg('paged', '%#%')),
                            'format' => '',
                            'current' => $paged,
                            'total' => $url_query->max_num_pages,
                            'prev_text' => '<i class="fas fa-chevron-left"></i> ' . __('Zurück', 'seo-ai-master'),
                            'next_text' => __('Weiter', 'seo-ai-master') . ' <i class="fas fa-chevron-right"></i>',
                            'add_args' => $current_query_args
                        ]);
                        ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <h3><?php _e('Keine URLs gefunden', 'seo-ai-master'); ?></h3>
                    <p><?php _e('Mit den aktuellen Filtereinstellungen wurden keine URLs gefunden.', 'seo-ai-master'); ?></p>
                    
                    <?php if (!empty($search_term) || $selected_type !== 'all' || $seo_status !== 'all'): ?>
                        <a href="<?php echo esc_url(remove_query_arg(['s', 'type', 'seo_status'])); ?>" 
                           class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-times" aria-hidden="true"></i>
                            <?php _e('Filter zurücksetzen', 'seo-ai-master'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; 
            wp_reset_postdata(); ?>

            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulk-actions">
                <div class="bulk-selection-info">
                    <span id="selected-urls-count">0</span> <?php _e('URLs ausgewählt', 'seo-ai-master'); ?>
                </div>
                <div class="bulk-action-buttons">
                    <button class="btn btn-primary" onclick="bulkAnalyzeURLs()">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <?php _e('Ausgewählte analysieren', 'seo-ai-master'); ?>
                    </button>
                    <button class="btn btn-secondary" onclick="bulkGenerateMeta()">
                        <i class="fas fa-magic" aria-hidden="true"></i>
                        <?php _e('Meta-Daten generieren', 'seo-ai-master'); ?>
                    </button>
                    <button class="btn btn-danger" onclick="bulkRemoveMonitoring()">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <?php _e('Aus Monitoring entfernen', 'seo-ai-master'); ?>
                    </button>
                    <button class="btn btn-secondary" onclick="clearURLSelection()">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <?php _e('Auswahl aufheben', 'seo-ai-master'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    const ajaxUrl = '<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>';
    const nonce = '<?php echo esc_js($nonce); ?>';
    
    // Bulk Selection Management
    window.updateBulkSelection = function() {
        const checkboxes = document.querySelectorAll('.url-checkbox');
        const checked = document.querySelectorAll('.url-checkbox:checked');
        const count = checked.length;
        
        const selectAll = document.getElementById('select-all-urls');
        const bulkActions = document.getElementById('bulk-actions');
        const countSpan = document.getElementById('selected-urls-count');
        
        if (selectAll) {
            selectAll.indeterminate = count > 0 && count < checkboxes.length;
            selectAll.checked = count === checkboxes.length;
        }
        
        if (countSpan) {
            countSpan.textContent = count;
        }
        
        if (bulkActions) {
            if (count > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
        }
    };
    
    window.toggleAllURLs = function(selectAll) {
        const checkboxes = document.querySelectorAll('.url-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
        updateBulkSelection();
    };
    
    window.clearURLSelection = function() {
        const checkboxes = document.querySelectorAll('.url-checkbox');
        const selectAll = document.getElementById('select-all-urls');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        if (selectAll) selectAll.checked = false;
        updateBulkSelection();
    };
    
    // Event Delegation für Checkboxes
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('url-checkbox')) {
            updateBulkSelection();
        }
    });
    
    // Initial update
    updateBulkSelection();
});

// URL Analysis Functions
function analyzeURL(postId) {
    if (!postId || postId <= 0) {
        showNotification('<?php echo esc_js( __('Ungültige URL-ID', 'seo-ai-master') ); ?>', 'error');
        return;
    }
    
    const button = event.target.closest('button');
    if (!button) return;
    
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner spinner"></i>';
    button.disabled = true;
    
    fetch('<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'seo_ai_analyze_post',
            post_id: postId,
            nonce: '<?php echo esc_js($nonce); ?>'
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ <?php echo esc_js( __('URL erfolgreich analysiert', 'seo-ai-master') ); ?>', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('❌ ' + (data.data || '<?php echo esc_js( __('Analyse fehlgeschlagen', 'seo-ai-master') ); ?>'), 'error');
        }
    })
    .catch(error => {
        console.error('URL Analysis Error:', error);
        showNotification('❌ <?php echo esc_js( __('Netzwerkfehler', 'seo-ai-master') ); ?>', 'error');
    })
    .finally(() => {
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function generateMeta(postId) {
    if (!postId || postId <= 0) {
        showNotification('<?php echo esc_js( __('Ungültige URL-ID', 'seo-ai-master') ); ?>', 'error');
        return;
    }
    
    const button = event.target.closest('button');
    if (!button) return;
    
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner spinner"></i>';
    button.disabled = true;
    
    fetch('<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'seo_ai_generate_meta',
            post_id: postId,
            nonce: '<?php echo esc_js($nonce); ?>'
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ <?php echo esc_js( __('Meta-Daten erfolgreich generiert', 'seo-ai-master') ); ?>', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('❌ ' + (data.data || '<?php echo esc_js( __('Meta-Generierung fehlgeschlagen', 'seo-ai-master') ); ?>'), 'error');
        }
    })
    .catch(error => {
        console.error('Meta Generation Error:', error);
        showNotification('❌ <?php echo esc_js( __('Netzwerkfehler', 'seo-ai-master') ); ?>', 'error');
    })
    .finally(() => {
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function removeFromMonitoring(postId) {
    if (!confirm('<?php echo esc_js( __('URL wirklich aus SEO-Monitoring entfernen?', 'seo-ai-master') ); ?>')) {
        return;
    }
    
    const button = event.target.closest('button');
    if (!button) return;
    
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner spinner"></i>';
    button.disabled = true;
    
    fetch('<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'seo_ai_url_delete',
            post_id: postId,
            nonce: '<?php echo esc_js($nonce); ?>'
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ ' + (data.data?.message || '<?php echo esc_js( __('URL entfernt', 'seo-ai-master') ); ?>'), 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('❌ ' + (data.data || '<?php echo esc_js( __('Entfernung fehlgeschlagen', 'seo-ai-master') ); ?>'), 'error');
        }
    })
    .catch(error => {
        console.error('URL Removal Error:', error);
        showNotification('❌ <?php echo esc_js( __('Netzwerkfehler', 'seo-ai-master') ); ?>', 'error');
    })
    .finally(() => {
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

// Bulk Actions
function bulkAnalyzeURLs() {
    const selected = Array.from(document.querySelectorAll('.url-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        showNotification('<?php echo esc_js( __('Bitte wählen Sie mindestens eine URL aus', 'seo-ai-master') ); ?>', 'error');
        return;
    }
    
    if (!confirm('<?php printf(esc_js(__('%d URLs analysieren?', 'seo-ai-master')), "' + selected.length + '"); ?>')) {
        return;
    }
    
    const button = event.target;
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner spinner"></i> <?php echo esc_js( __('Analysiere...', 'seo-ai-master') ); ?>';
    button.disabled = true;
    
    fetch('<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'seo_ai_url_bulk_analyze',
            post_ids: selected,
            nonce: '<?php echo esc_js($nonce); ?>'
        }),
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('✅ ' + (data.data?.message || '<?php echo esc_js( __('Bulk-Analyse abgeschlossen', 'seo-ai-master') ); ?>'), 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('❌ ' + (data.data || '<?php echo esc_js( __('Bulk-Analyse fehlgeschlagen', 'seo-ai-master') ); ?>'), 'error');
        }
    })
    .catch(error => {
        console.error('Bulk Analysis Error:', error);
        showNotification('❌ <?php echo esc_js( __('Netzwerkfehler bei Bulk-Analyse', 'seo-ai-master') ); ?>', 'error');
    })
    .finally(() => {
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function bulkGenerateMeta() {
    const selected = Array.from(document.querySelectorAll('.url-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        showNotification('<?php echo esc_js( __('Bitte wählen Sie mindestens eine URL aus', 'seo-ai-master') ); ?>', 'error');
        return;
    }
    
    showNotification('<?php echo esc_js( __('Bulk Meta-Generierung wird implementiert...', 'seo-ai-master') ); ?>', 'info');
}

function bulkRemoveMonitoring() {
    const selected = Array.from(document.querySelectorAll('.url-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        showNotification('<?php echo esc_js( __('Bitte wählen Sie mindestens eine URL aus', 'seo-ai-master') ); ?>', 'error');
        return;
    }
    
    if (!confirm('<?php printf(esc_js(__('%d URLs wirklich aus SEO-Monitoring entfernen?', 'seo-ai-master')), "' + selected.length + '"); ?>')) {
        return;
    }
    
    showNotification('<?php echo esc_js( __('Bulk-Entfernung wird implementiert...', 'seo-ai-master') ); ?>', 'info');
}

// View Mode Change
function changeView(mode) {
    const url = new URL(window.location);
    url.searchParams.set('view', mode);
    window.location.href = url.toString();
}

// Export Function
function exportURLs() {
    const selected = Array.from(document.querySelectorAll('.url-checkbox:checked')).map(cb => cb.value);
    const format = 'csv'; // Standardformat
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>';
    form.style.display = 'none';
    
    // Action Field
    const actionField = document.createElement('input');
    actionField.type = 'hidden';
    actionField.name = 'action';
    actionField.value = 'seo_ai_url_export';
    form.appendChild(actionField);
    
    // Nonce Field
    const nonceField = document.createElement('input');
    nonceField.type = 'hidden';
    nonceField.name = 'nonce';
    nonceField.value = '<?php echo esc_js($nonce); ?>';
    form.appendChild(nonceField);
    
    // Format Field
    const formatField = document.createElement('input');
    formatField.type = 'hidden';
    formatField.name = 'format';
    formatField.value = format;
    form.appendChild(formatField);
    
    // Selected URLs
    selected.forEach(postId => {
        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'post_ids[]';
        field.value = postId;
        form.appendChild(field);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    
    showNotification('<?php echo esc_js( __('Export wird vorbereitet...', 'seo-ai-master') ); ?>', 'info');
}

// Notification System
function showNotification(message, type = 'info') {
    if (!message || typeof message !== 'string') return;
    
    // Remove existing notifications
    document.querySelectorAll('.seo-ai-notification').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `seo-ai-notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, type === 'error' ? 6000 : 4000);
}
</script>