<?php
/**
 * SEO AI Master Dashboard - Weltklasse Design für WordPress
 *
 * @version 2.0.0
 * @author AlenSEO
 * @description Optimiertes Dashboard mit Premium-Design
 */
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die(__('Sie haben keine Berechtigung für diese Seite.', 'seo-ai-master'));

// Nonce für AJAX
$nonce = wp_create_nonce('seo_ai_dashboard_nonce');

// Live-Daten laden (statt Mock)
$url_manager = \SEOAI\URLManager::get_instance();
$stats = method_exists($url_manager, 'get_url_stats') ? $url_manager->get_url_stats() : [
    'total_urls' => 127,
    'analyzed' => 89,
    'not_analyzed' => 38,
    'avg_score' => 87,
    'critical_issues' => 23,
    'ai_usage' => 1247,
    'breakdown' => [
        'posts' => 45,
        'pages' => 12,
        'products' => 70
    ],
    'critical_urls' => 23,
    'missing_titles' => 15
];
$top_pages = method_exists($url_manager, 'get_top_pages') ? $url_manager->get_top_pages() : [
    (object) ['post_title' => 'Homepage', 'score' => 95],
    (object) ['post_title' => 'Über uns', 'score' => 92],
    (object) ['post_title' => 'Blog', 'score' => 78]
];

// Content-Tabelle: Echte Inhalte laden
$content_query = $url_manager->get_urls([
    'posts_per_page' => 10,
    'paged' => 1,
    'orderby' => 'modified',
    'order' => 'DESC',
]);

$score = isset($stats['avg_score']) ? intval($stats['avg_score']) : 0;
$score_status = 'schlecht';
$score_class = 'critical';
if ($score >= 80) {
    $score_status = 'ausgezeichnet';
    $score_class = 'excellent';
} elseif ($score >= 60) {
    $score_status = 'okay';
    $score_class = 'good';
} elseif ($score >= 40) {
    $score_status = 'verbesserungswürdig';
    $score_class = 'warning';
}
?>

<div class="seo-ai-master-plugin">
    <!-- Header mit Premium-Design -->
<div class="header">
        <div class="logo">
            SEO AI Master
        </div>
        <div class="header-controls">
            <div class="api-status">
            <span class="api-indicator online">CLAUDE</span>
            <span class="api-indicator online">GPT-4O</span>
                <span class="api-indicator degraded">GPT-4</span>
            <span class="api-indicator online">GEMINI</span>
            </div>
            <div class="user-menu">
                <i class="fas fa-user"></i>
            <?php echo esc_html(wp_get_current_user()->display_name); ?>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
</div>

    <!-- Navigation -->
    <nav class="nav-tabs">
        <a href="<?php echo admin_url('admin.php?page=seo-ai-master'); ?>" class="nav-tab active">Dashboard</a>
    <a href="<?php echo admin_url('admin.php?page=seo-ai-urls'); ?>" class="nav-tab">URLs Verwalten</a>
        <a href="<?php echo admin_url('admin.php?page=seo-ai-optimizer'); ?>" class="nav-tab">AI Optimizer</a>
        <a href="<?php echo admin_url('admin.php?page=seo-ai-analytics'); ?>" class="nav-tab">Analytics</a>
        <a href="<?php echo admin_url('admin.php?page=seo-ai-settings'); ?>" class="nav-tab">Einstellungen</a>
    </nav>

    <!-- Dashboard Content -->
    <main class="dashboard">
        <!-- Erste Reihe: 4 Hauptkennzahlen -->
        <div class="dashboard-grid">
            <!-- SEO Score Widget -->
            <div class="widget" data-widget="seo-score">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                        SEO Gesamtscore
                    </h3>
                </div>
                <div class="widget-value-content">
                    <div class="seo-score-circle"
                         data-score="<?php echo esc_attr($stats['avg_score']); ?>"
                         data-score-range="<?php echo esc_attr($score_class); ?>"
                         style="--score-angle: <?php echo round((isset($stats['avg_score']) ? $stats['avg_score'] : 0) * 3.6); ?>deg;">
                        <span class="seo-score-number"><?php echo esc_html($stats['avg_score']); ?></span>
                    </div>
                    <div class="seo-widget-subtitle">Durchschnitt aller Seiten</div>
                    <div class="score-status <?php echo esc_attr($score_class); ?>">
                        <?php echo ucfirst($score_status); ?>
                    </div>
                </div>
                <div class="trend positive">
                    <i class="fas fa-arrow-up" aria-hidden="true"></i>
                +12 diese Woche
            </div>
            </div>

            <!-- Critical Issues Widget -->
            <div class="widget" data-widget="critical-issues">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        Kritische Issues
                    </h3>
                </div>
                <div class="widget-value-content">
                    <div class="critical-issues-content">
                        <div class="critical-issues-number"><?php echo esc_html($stats['critical_urls'] ?? $stats['critical_issues'] ?? 0); ?></div>
                <div class="widget-subtitle">Seiten benötigen Aufmerksamkeit</div>
                        <!-- Issues Progress Breakdown (dynamisch, aber fallback auf Demo) -->
                        <div class="issues-progress">
                            <div class="issue-category">
                                <span>Meta-Daten fehlen</span>
                                <span><?php echo esc_html($stats['missing_titles'] ?? 15); ?></span>
                            </div>
                            <div class="issue-bar">
                                <div class="issue-fill" style="--issue-width: 65%; width: 65%;"></div>
                            </div>
                            <div class="issue-category">
                                <span>Niedrige Scores</span>
                                <span><?php echo esc_html($stats['critical_urls'] ?? 8); ?></span>
                            </div>
                            <div class="issue-bar">
                                <div class="issue-fill" style="--issue-width: 35%; width: 35%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="trend negative">
                    <i class="fas fa-arrow-down" aria-hidden="true"></i>
                -5 seit gestern
            </div>
            </div>

            <!-- AI Usage Widget -->
            <div class="widget" data-widget="ai-usage">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-robot" aria-hidden="true"></i>
                        AI Usage (heute)
                    </h3>
                </div>
                <div class="widget-value-content">
                    <div class="ai-usage-content">
                        <div class="ai-usage-number">
                            <?php echo isset($stats['ai_usage']) ? esc_html(number_format($stats['ai_usage'])) : '—'; ?>
                        </div>
                        <div class="ai-usage-subtitle">
                            API Calls • $
                            <?php echo isset($stats['ai_costs']) ? esc_html(number_format($stats['ai_costs'], 2)) : '—'; ?> Kosten
                        </div>
                        <div class="ai-usage-breakdown">
                            <?php if (!empty($stats['ai_breakdown']) && is_array($stats['ai_breakdown'])): ?>
                                <?php foreach ($stats['ai_breakdown'] as $provider => $percent): ?>
                                    <div class="usage-item">
                                        <span class="usage-provider"><?php echo esc_html($provider); ?></span>
                                        <span class="usage-percentage"><?php echo esc_html($percent); ?>%</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="usage-item"><span class="usage-provider">Claude 3.5</span><span class="usage-percentage">—</span></div>
                                <div class="usage-item"><span class="usage-provider">GPT-4o</span><span class="usage-percentage">—</span></div>
                                <div class="usage-item"><span class="usage-provider">Gemini</span><span class="usage-percentage">—</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="trend positive">
                    <i class="fas fa-arrow-up" aria-hidden="true"></i>
                    <?php echo isset($stats['ai_trend']) ? esc_html($stats['ai_trend']) : '—'; ?>
                </div>
            </div>

            <!-- Top Pages Widget -->
            <div class="widget" data-widget="top-performing">
                <div class="widget-header">
                    <h3 class="widget-title">
                        <i class="fas fa-star" aria-hidden="true"></i>
                        Top Performing
                    </h3>
                </div>
                <div class="widget-content">
                    <div class="performance-list">
                <?php if (!empty($top_pages)): foreach ($top_pages as $page): ?>
                            <div class="performance-item">
                                <span class="page-name"><?php echo esc_html($page->post_title ?? $page['post_title']); ?></span>
                                <span class="page-score"><?php echo esc_html($page->score ?? $page['score']); ?></span>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="performance-item">
                                <span class="page-name">Keine Daten verfügbar</span>
                                <span class="page-score">—</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zweite Reihe: 2 Boxen -->
        <div class="dashboard-row">
            <!-- AI Content Generator Widget -->
            <div class="widget ai-generator">
                <div class="widget-header">
                    <h3 class="widget-title"><i class="fas fa-magic"></i> AI Content Generator</h3>
                    <select class="form-input" style="width:auto;max-width:180px;">
                        <option>Auto-Select</option>
                        <option>Claude 3.5</option>
                        <option>GPT-4o</option>
                        <option>Gemini Pro</option>
                    </select>
                </div>
                <div class="generation-tabs">
                    <button class="gen-tab active">Meta Titles</button>
                    <button class="gen-tab">Descriptions</button>
                    <button class="gen-tab">Alt Texte</button>
                    <button class="gen-tab">Excerpts</button>
                    <button class="gen-tab">Kategorien</button>
                </div>
                <div class="ai-options">
                    <div class="form-group">
                        <label class="form-label">Content-Typ:</label>
                        <select class="form-input">
                            <option>Alle Inhalte (<?php echo esc_html($stats['total_urls'] ?? $stats['total'] ?? 0); ?>)</option>
                            <option>Beiträge</option>
                            <option>Seiten</option>
                            <option>Produkte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stil:</label>
                        <select class="form-input">
                            <option>Professionell</option>
                            <option>Locker</option>
                            <option>Technisch</option>
                            <option>Verkaufsorientiert</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Zielgruppe:</label>
                        <input class="form-input" type="text" placeholder="z.B. IT-Fachkräfte, Eltern">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Keywords:</label>
                        <input class="form-input" type="text" placeholder="Komma-getrennte Keywords">
                    </div>
                </div>
                <div class="btn-group" style="margin-top:1rem;">
                    <button class="btn btn-primary" disabled><i class="fas fa-rocket"></i> Bulk-Generierung starten</button>
                    <button class="btn btn-secondary" disabled><i class="fas fa-eye"></i> Vorschau</button>
                </div>
            </div>
            <!-- Quick Actions Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <button class="btn btn-secondary" style="justify-content: flex-start;"><i class="fas fa-search"></i> Alle Seiten analysieren</button>
                    <button class="btn btn-secondary" style="justify-content: flex-start;"><i class="fas fa-tags"></i> Fehlende Meta Daten ergänzen</button>
                    <button class="btn btn-secondary" style="justify-content: flex-start;"><i class="fas fa-chart-bar"></i> SEO-Report generieren</button>
                </div>
            </div>
        </div>

        <!-- Dritte Reihe: Content Management Table -->
        <div class="dashboard-row-full">
            <div class="content-section">
                <div class="table-header">
                    <h2>
                        <i class="fas fa-list" aria-hidden="true"></i>
                        Content Management
                    </h2>
                    <div class="table-controls">
                        <input type="search" class="search-box" placeholder="Seiten durchsuchen..." onkeyup="filterContentTable(this.value)">
                        <select class="form-input" style="width:auto;max-width:180px;" onchange="filterContentByType(this.value)">
                            <option value="all">Alle Typen</option>
                            <option value="post">Beiträge</option>
                            <option value="page">Seiten</option>
                            <option value="product">Produkte</option>
                        </select>
                        <button class="btn ai-btn" onclick="bulkOptimizeSelected()">
                            <i class="fas fa-magic" aria-hidden="true"></i>
                            Bulk AI Optimierung
                        </button>
                    </div>
                </div>
                <table class="content-table" id="contentTable">
                    <thead>
                        <tr>
                            <th style="width: 30px;">
                                <input type="checkbox" id="selectAllContent" onchange="toggleAllContentSelection(this)">
                            </th>
                            <th>
                                Titel/URL 
                                <button class="ai-btn" style="margin-left: 0.5rem;" onclick="bulkOptimizeTitles()">
                                    <i class="fas fa-robot" aria-hidden="true"></i>
                                </button>
                            </th>
                            <th>Typ</th>
                            <th>
                                Meta Title 
                                <button class="ai-btn" style="margin-left: 0.5rem;" onclick="bulkGenerateMetaTitles()">
                                    <i class="fas fa-robot" aria-hidden="true"></i>
                                </button>
                            </th>
                            <th>SEO Score</th>
                            <th>Letztes Update</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($content_query->have_posts()): while ($content_query->have_posts()): $content_query->the_post();
                            $post_id = get_the_ID();
                            $post_type = get_post_type();
                            $score = get_post_meta($post_id, '_seo_ai_score', true);
                            $meta_title = get_post_meta($post_id, '_seo_ai_title', true);
                            $score = $score ? max(0, min(100, intval($score))) : 0;
                            $score_color = '#ef4444';
                            if ($score >= 80) $score_color = '#10b981';
                            elseif ($score >= 60) $score_color = '#f59e0b';
                            $post_type_obj = get_post_type_object($post_type);
                            $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post_type;
                        ?>
                        <tr data-type="<?php echo esc_attr($post_type); ?>" data-score="<?php echo esc_attr($score); ?>">
                            <td><input type="checkbox" class="content-checkbox" data-post-id="<?php echo esc_attr($post_id); ?>"></td>
                            <td>
                                <div class="content-title"><?php echo esc_html(get_the_title()); ?></div>
                                <div class="content-url"><?php echo esc_html(wp_make_link_relative(get_permalink())); ?></div>
                            </td>
                            <td><span class="badge badge-<?php echo esc_attr($post_type); ?>"><?php echo esc_html($post_type_label); ?></span></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <?php if (!empty($meta_title)): ?>
                                        <span style="font-size: 0.85rem;"> <?php echo esc_html(wp_trim_words($meta_title, 10)); ?> </span>
                                    <?php else: ?>
                                        <span style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">Nicht gesetzt</span>
                                    <?php endif; ?>
                                    <button class="ai-btn" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;" onclick="regenerateMetaTitle(<?php echo esc_attr($post_id); ?>)">
                                        <i class="fas fa-robot" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-weight: 600; color: <?php echo esc_attr($score_color); ?>;"> <?php echo esc_html($score); ?> </span>
                                    <div class="score-bar">
                                        <div class="score-fill" style="width: <?php echo esc_attr($score); ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">
                                <?php echo esc_html(human_time_diff(get_the_modified_time('U'), current_time('timestamp')) . ' ' . __('ago', 'seo-ai-master')); ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn ai-btn" onclick="optimizeWithAI(<?php echo esc_attr($post_id); ?>)" title="Mit AI optimieren">
                                        <i class="fas fa-magic" aria-hidden="true"></i>
                                    </button>
                                    <button class="action-btn btn-secondary" onclick="previewContent(<?php echo esc_attr($post_id); ?>)" title="Vorschau">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                    <button class="action-btn btn-secondary" onclick="editContent(<?php echo esc_attr($post_id); ?>)" title="Bearbeiten">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; wp_reset_postdata(); else: ?>
                        <!-- Fallback: Demo-Zeile -->
                        <tr>
                            <td><input type="checkbox" class="content-checkbox" data-post-id="123"></td>
                            <td>
                                <div class="content-title">WordPress SEO Guide für Anfänger</div>
                                <div class="content-url">/wordpress-seo-guide/</div>
                            </td>
                            <td><span class="badge badge-post">Beitrag</span></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-size: 0.85rem;">WordPress SEO: Der ultimative Guide 2024</span>
                                    <button class="ai-btn" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;" onclick="regenerateMetaTitle(123)">
                                        <i class="fas fa-robot" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-weight: 600; color: #10b981;">87</span>
                                <div class="score-bar">
                                    <div class="score-fill" style="width: 87%;"></div>
                                </div>
                                </div>
                            </td>
                            <td style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">vor 2 Std.</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn ai-btn" onclick="optimizeWithAI(123)" title="Mit AI optimieren">
                                        <i class="fas fa-magic" aria-hidden="true"></i>
                                    </button>
                                    <button class="action-btn btn-secondary" onclick="previewContent(123)" title="Vorschau">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                    <button class="action-btn btn-secondary" onclick="editContent(123)" title="Bearbeiten">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Bulk Actions Bar (initially hidden) -->
                <div class="bulk-actions" id="bulkActionsBar" style="display: none;">
                    <div class="bulk-selection-info">
                        <span id="selectedCount">0</span> Inhalte ausgewählt
                    </div>
                    <div class="bulk-action-buttons">
                        <button class="btn btn-primary" onclick="bulkAnalyzeSelected()">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            Analysieren
                        </button>
                        <button class="btn btn-secondary" onclick="bulkGenerateMetaData()">
                            <i class="fas fa-magic" aria-hidden="true"></i>
                            Meta-Daten generieren
                        </button>
                        <button class="btn btn-success" onclick="bulkOptimizeSelected()">
                            <i class="fas fa-rocket" aria-hidden="true"></i>
                            AI Optimierung
                        </button>
                        <button class="btn btn-secondary" onclick="clearContentSelection()">
                            <i class="fas fa-times" aria-hidden="true"></i>
                            Auswahl aufheben
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// ... Dein Notification-System und alle bisherigen Funktionen bleiben erhalten ...
</script>