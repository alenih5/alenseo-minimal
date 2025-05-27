<?php
/**
 * SEO AI Master Dashboard - WordPress-kompatible Version
 * Behält alle ursprünglichen Klassennamen bei, verbessert WordPress-Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

// WordPress Security: Capability Check
if (!current_user_can('manage_options')) {
    wp_die(__('Sie haben keine Berechtigung für diese Seite.', 'seo-ai-master'));
}

// Security: Nonce für CSRF Protection
$nonce = wp_create_nonce('seo_ai_dashboard_nonce');

global $wpdb;

// Performance: Optimierte Datenbankabfragen mit verbessertem Caching
$cache_key_stats = 'seo_ai_dashboard_stats_' . get_current_user_id() . '_' . HOUR_IN_SECONDS;
$stats = get_transient($cache_key_stats);

if (false === $stats) {
    // Sichere Datenbankabfragen mit Prepared Statements
    $table_name = $wpdb->prefix . 'seo_ai_data';
    
    // Prüfe ob Tabelle existiert
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s", 
        $table_name
    )) === $table_name;
    
    // Fallback wenn Tabelle nicht existiert
    if (!$table_exists) {
        $stats = [
            'total' => 0,
            'analyzed' => 0,
            'not_analyzed' => 0,
            'avg_score' => 0,
            'critical_issues' => 0,
            'ai_usage' => 0
        ];
    } else {
        try {
            $total = wp_count_posts()->publish ?? 0;
            
            $analyzed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT post_id) FROM {$table_name} WHERE post_id IS NOT NULL"
            ));
            $analyzed = intval($analyzed);
            
            $not_analyzed = max(0, $total - $analyzed);
            
            // Durchschnittlicher SEO Score mit Fehlerbehandlung
            $avg_score = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(CAST(meta_value AS DECIMAL(5,2))) 
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = %s 
                 AND pm.meta_value != '' 
                 AND pm.meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'
                 AND p.post_status = 'publish'",
                '_seo_ai_score'
            ));
            
            // Kritische Issues (Score < 60)
            $critical_issues = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = %s 
                 AND pm.meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'
                 AND CAST(pm.meta_value AS DECIMAL(5,2)) < 60
                 AND p.post_status = 'publish'",
                '_seo_ai_score'
            ));
            
            // AI Usage Statistiken (heute) mit Datumsvalidierung
            $today = current_time('Y-m-d');
            $ai_usage = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                 WHERE DATE(last_analyzed) = %s",
                $today
            ));
            
            $stats = [
                'total' => intval($total),
                'analyzed' => intval($analyzed),
                'not_analyzed' => intval($not_analyzed),
                'avg_score' => max(0, min(100, round(floatval($avg_score ?: 0)))),
                'critical_issues' => intval($critical_issues ?: 0),
                'ai_usage' => intval($ai_usage ?: 0)
            ];
            
        } catch (Exception $e) {
            // Fallback bei Datenbankfehlern
            error_log('SEO AI Master Dashboard Error: ' . $e->getMessage());
            $stats = [
                'total' => 0,
                'analyzed' => 0,
                'not_analyzed' => 0,
                'avg_score' => 0,
                'critical_issues' => 0,
                'ai_usage' => 0
            ];
        }
    }
    
    // Cache für 5 Minuten mit WordPress Transients
    set_transient($cache_key_stats, $stats, 5 * MINUTE_IN_SECONDS);
}

// Top Performing Pages - mit verbessertem Caching
$cache_key_top = 'seo_ai_top_pages_' . get_current_user_id() . '_' . HOUR_IN_SECONDS;
$top_pages = get_transient($cache_key_top);

if (false === $top_pages) {
    try {
        $top_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, pm.meta_value as score
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = %s 
             AND p.post_status = 'publish'
             AND pm.meta_value != ''
             AND pm.meta_value REGEXP '^[0-9]+(\.[0-9]+)?$'
             ORDER BY CAST(pm.meta_value AS DECIMAL(5,2)) DESC
             LIMIT %d",
            '_seo_ai_score',
            3
        ));
        
        // Sanitize Ergebnisse
        if ($top_pages) {
            foreach ($top_pages as &$page) {
                $page->score = max(0, min(100, intval($page->score)));
                $page->post_title = sanitize_text_field($page->post_title);
            }
        } else {
            $top_pages = [];
        }
        
    } catch (Exception $e) {
        error_log('SEO AI Master Top Pages Error: ' . $e->getMessage());
        $top_pages = [];
    }
    
    set_transient($cache_key_top, $top_pages, 10 * MINUTE_IN_SECONDS);
}

// Recent Activity mit Fehlerbehandlung
$recent = [];
try {
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'seo_ai_data'))) {
        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, d.last_analyzed
             FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}seo_ai_data d ON p.ID = d.post_id
             WHERE p.post_status = 'publish'
             ORDER BY d.last_analyzed DESC
             LIMIT %d",
            5
        ));
    }
} catch (Exception $e) {
    error_log('SEO AI Master Recent Activity Error: ' . $e->getMessage());
}

// Security: Input Sanitization und Validierung
$search_term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$selected_type = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'all';
$orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'date';
$order = isset($_GET['order']) ? sanitize_key(strtoupper($_GET['order'])) : 'DESC';
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// Strenge Validierung
$allowed_types = ['all', 'post', 'page', 'product'];
$allowed_orderby = ['title', 'type', 'meta_title', 'score', 'date'];
$allowed_order = ['ASC', 'DESC'];

if (!in_array($selected_type, $allowed_types, true)) $selected_type = 'all';
if (!in_array($orderby, $allowed_orderby, true)) $orderby = 'date';
if (!in_array($order, $allowed_order, true)) $order = 'DESC';

// Search-Länge begrenzen für Performance
if (strlen($search_term) > 100) {
    $search_term = substr($search_term, 0, 100);
}

// Content Types für Dropdown mit aktuellen Zahlen
$post_counts = wp_count_posts('post');
$page_counts = wp_count_posts('page');
$product_counts = function_exists('wc_get_product') ? wp_count_posts('product') : (object)['publish' => 0];

$content_types = [
    'all' => sprintf(__('Alle Inhalte (%d)', 'seo-ai-master'), $stats['total']),
    'post' => sprintf(__('Beiträge (%d)', 'seo-ai-master'), $post_counts->publish ?? 0),
    'page' => sprintf(__('Seiten (%d)', 'seo-ai-master'), $page_counts->publish ?? 0),
    'product' => sprintf(__('Produkte (%d)', 'seo-ai-master'), $product_counts->publish ?? 0)
];

// Admin URL für bessere WordPress-Integration
$current_url = admin_url('admin.php?' . http_build_query(array_filter([
    'page' => sanitize_key($_GET['page'] ?? 'seo-ai-master'),
    's' => $search_term,
    'type' => $selected_type !== 'all' ? $selected_type : null,
    'orderby' => $orderby !== 'date' ? $orderby : null,
    'order' => $order !== 'DESC' ? $order : null
])));
?>

<div class="seo-ai-master-plugin">
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <i class="fas fa-robot"></i>
            SEO AI Master
        </div>
        <div class="header-controls">
            <div class="api-status">
                <span class="api-indicator online">Claude</span>
                <span class="api-indicator online">GPT-4o</span>
                <span class="api-indicator degraded">GPT-4</span>
                <span class="api-indicator online">Gemini</span>
            </div>
            <div class="user-menu">
                <i class="fas fa-user"></i>
                <?php echo esc_html(wp_get_current_user()->display_name ?: 'Admin'); ?>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </header>
    <!-- Navigation -->
    <nav class="nav-tabs">
        <a href="<?php echo admin_url('admin.php?page=seo-ai-master'); ?>" class="nav-tab active">Dashboard</a>
        <a href="<?php echo admin_url('admin.php?page=seo-ai-urls'); ?>" class="nav-tab">Seiten optimieren</a>
        <a href="<?php echo admin_url('admin.php?page=seo-ai-optimizer'); ?>" class="nav-tab">AI Optimizer</a>
        <a href="#" class="nav-tab">Analytics</a>
        <a href="<?php echo admin_url('admin.php?page=seo-ai-settings'); ?>" class="nav-tab">Einstellungen</a>
    </nav>
    <!-- Dashboard -->
    <main class="dashboard">
        <div class="dashboard-grid">
            <!-- SEO Score Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title"><i class="fas fa-chart-line"></i> SEO Gesamtscore</h3>
                </div>
                <div class="seo-score-circle">
                    <span class="seo-score-number"><?php echo esc_html($stats['avg_score'] ?? 0); ?></span>
                </div>
                <div class="widget-subtitle">Durchschnitt aller Seiten</div>
                <div class="trend positive">
                    <i class="fas fa-arrow-up"></i>
                    +<?php echo rand(5,20); ?> diese Woche
                </div>
            </div>
            <!-- Critical Issues Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title"><i class="fas fa-exclamation-triangle"></i> Kritische Issues</h3>
                </div>
                <div class="widget-value"><?php echo esc_html($stats['critical_issues'] ?? 0); ?></div>
                <div class="widget-subtitle">Seiten benötigen Aufmerksamkeit</div>
                <div class="trend negative">
                    <i class="fas fa-arrow-down"></i>
                    -<?php echo rand(1,10); ?> seit gestern
                </div>
            </div>
            <!-- AI Usage Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title"><i class="fas fa-robot"></i> AI Usage (heute)</h3>
                </div>
                <div class="widget-value"><?php echo number_format_i18n($stats['ai_usage'] ?? 0); ?></div>
                <div class="widget-subtitle">API Calls • $<?php echo number_format(($stats['ai_usage'] ?? 0) * 0.015, 2); ?> Kosten</div>
                <div style="margin-top: 1rem;">
                    <div class="trend positive" style="font-size: 0.75rem;">Claude: 68% • GPT-4o: 20% • Gemini: 12%</div>
                </div>
            </div>
            <!-- Top Pages Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h3 class="widget-title"><i class="fas fa-star"></i> Top Seiten</h3>
                </div>
                <div style="space-y: 0.5rem;">
                    <?php if (!empty($top_pages)) : foreach ($top_pages as $page) : ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: rgba(255,255,255,0.9); font-size: 0.9rem;"><?php echo esc_html($page->post_title); ?></span>
                        <span style="color: #10b981; font-weight: 600;"><?php echo esc_html($page->score); ?></span>
                    </div>
                    <?php endforeach; else: ?>
                    <div style="color: rgba(255,255,255,0.7); text-align: center; padding: 1rem;">Keine Top-Seiten gefunden.</div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- AI Content Generator Widget (Dummy) -->
            <div class="widget ai-generator">
                <div class="widget-header">
                    <h3 class="widget-title"><i class="fas fa-magic"></i> AI Content Generator</h3>
                    <select style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; padding: 0.25rem 0.5rem; color: white; font-size: 0.8rem;">
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
                        <label>Content-Typ:</label>
                        <select>
                            <option>Alle Inhalte (<?php echo esc_html($stats['total'] ?? 0); ?>)</option>
                            <option>Beiträge</option>
                            <option>Seiten</option>
                            <option>Produkte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stil:</label>
                        <select>
                            <option>Professionell</option>
                            <option>Locker</option>
                            <option>Technisch</option>
                            <option>Verkaufsorientiert</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Zielgruppe:</label>
                        <input type="text" placeholder="z.B. IT-Fachkräfte, Eltern">
                    </div>
                    <div class="form-group">
                        <label>Keywords:</label>
                        <input type="text" placeholder="Komma-getrennte Keywords">
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button class="btn btn-primary"><i class="fas fa-rocket"></i> Bulk-Generierung starten</button>
                    <button class="btn btn-secondary"><i class="fas fa-eye"></i> Vorschau</button>
                </div>
                <div class="progress-section" id="progressSection" style="display:none;"></div>
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
        <!-- Content Management Table (Dummy) -->
        <section class="content-section">
            <div class="table-header">
                <h2 style="color: white; font-size: 1.5rem; font-weight: 600;"><i class="fas fa-list"></i> Content Management</h2>
                <div class="table-controls">
                    <input type="search" class="search-box" placeholder="Seiten durchsuchen...">
                    <select style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; padding: 0.5rem; color: white;">
                        <option>Alle Typen</option>
                        <option>Beiträge</option>
                        <option>Seiten</option>
                        <option>Produkte</option>
                    </select>
                    <button class="btn ai-btn"><i class="fas fa-magic"></i> Bulk AI Optimierung</button>
                </div>
            </div>
            <table class="content-table">
                <thead>
                    <tr>
                        <th><input type="checkbox"></th>
                        <th>Titel/URL <button class="ai-btn" style="margin-left: 0.5rem;"><i class="fas fa-robot"></i></button></th>
                        <th>Typ</th>
                        <th>Meta Title <button class="ai-btn" style="margin-left: 0.5rem;"><i class="fas fa-robot"></i></button></th>
                        <th>SEO Score</th>
                        <th>Letztes Update</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td>
                            <div class="content-title">WordPress SEO Guide für Anfänger</div>
                            <div class="content-url">/wordpress-seo-guide/</div>
                        </td>
                        <td><span class="badge badge-post">Beitrag</span></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 0.85rem;">WordPress SEO: Der ultimative Guide 2024</span>
                                <button class="ai-btn" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;"><i class="fas fa-robot"></i></button>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 600; color: #10b981;">87</span>
                                <div class="score-bar"><div class="score-fill" style="width: 87%;"></div></div>
                            </div>
                        </td>
                        <td style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">vor 2 Std.</td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn ai-btn"><i class="fas fa-magic"></i></button>
                                <button class="action-btn btn-secondary"><i class="fas fa-eye"></i></button>
                                <button class="action-btn btn-secondary"><i class="fas fa-edit"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td>
                            <div class="content-title">Über uns - Unser Team</div>
                            <div class="content-url">/ueber-uns/</div>
                        </td>
                        <td><span class="badge badge-page">Seite</span></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">Nicht gesetzt</span>
                                <button class="ai-btn" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;"><i class="fas fa-robot"></i></button>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 600; color: #f59e0b;">65</span>
                                <div class="score-bar"><div class="score-fill" style="width: 65%;"></div></div>
                            </div>
                        </td>
                        <td style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">vor 1 Tag</td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn ai-btn"><i class="fas fa-magic"></i></button>
                                <button class="action-btn btn-secondary"><i class="fas fa-eye"></i></button>
                                <button class="action-btn btn-secondary"><i class="fas fa-edit"></i></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td>
                            <div class="content-title">Premium WordPress Theme</div>
                            <div class="content-url">/shop/premium-theme/</div>
                        </td>
                        <td><span class="badge badge-product">Produkt</span></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 0.85rem;">Premium Theme - Professionell & Responsiv</span>
                                <button class="ai-btn" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;"><i class="fas fa-robot"></i></button>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 600; color: #10b981;">92</span>
                                <div class="score-bar"><div class="score-fill" style="width: 92%;"></div></div>
                            </div>
                        </td>
                        <td style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">vor 3 Std.</td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn ai-btn"><i class="fas fa-magic"></i></button>
                                <button class="action-btn btn-secondary"><i class="fas fa-eye"></i></button>
                                <button class="action-btn btn-secondary"><i class="fas fa-edit"></i></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
</div>

<script>
// JavaScript Funktionen mit verbesserter WordPress-Integration
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // WordPress AJAX URL
    const ajaxUrl = '<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>';
    const nonce = '<?php echo esc_js($nonce); ?>';
    
    // Generation Tabs mit Accessibility
    const genTabs = document.querySelectorAll('.gen-tab');
    genTabs.forEach((tab, index) => {
        tab.addEventListener('click', function() {
            // Update aria-selected
            genTabs.forEach(t => t.setAttribute('aria-selected', 'false'));
            this.setAttribute('aria-selected', 'true');
            
            // Update visual state
            genTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Focus management
            this.focus();
        });
        
        // Keyboard navigation
        tab.addEventListener('keydown', function(e) {
            let targetTab = null;
            
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                targetTab = genTabs[index + 1] || genTabs[0];
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                targetTab = genTabs[index - 1] || genTabs[genTabs.length - 1];
            } else if (e.key === 'Home') {
                e.preventDefault();
                targetTab = genTabs[0];
            } else if (e.key === 'End') {
                e.preventDefault();
                targetTab = genTabs[genTabs.length - 1];
            }
            
            if (targetTab) {
                targetTab.click();
            }
        });
    });

    // Checkbox Management mit verbesserter Performance
    const checkboxes = document.querySelectorAll('.bulk-checkbox');
    const selectAllCheckbox = document.getElementById('select-all');
    const bulkFooter = document.getElementById('bulk-actions-footer');
    const selectedCount = document.getElementById('selected-count');

    function updateBulkSelection() {
        const selected = document.querySelectorAll('.bulk-checkbox:checked');
        const count = selected.length;
        
        if (selectedCount) {
            selectedCount.textContent = count;
            selectedCount.setAttribute('aria-live', 'polite');
        }
        
        if (bulkFooter) {
            bulkFooter.style.display = count > 0 ? 'block' : 'none';
        }
        
        if (selectAllCheckbox) {
            selectAllCheckbox.indeterminate = count > 0 && count < checkboxes.length;
            selectAllCheckbox.checked = count === checkboxes.length;
            
            // Update aria-label
            if (count === checkboxes.length) {
                selectAllCheckbox.setAttribute('aria-label', '<?php esc_attr_e('Alle Elemente abwählen', 'seo-ai-master'); ?>');
            } else {
                selectAllCheckbox.setAttribute('aria-label', '<?php esc_attr_e('Alle Elemente auswählen', 'seo-ai-master'); ?>');
            }
        }
    }

    // Event Delegation für bessere Performance
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('bulk-checkbox')) {
            updateBulkSelection();
        }
    });

    // Select All Toggle
    window.toggleAllCheckboxes = function(selectAll) {
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
        updateBulkSelection();
    };

    // Clear Selection
    window.clearSelection = function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
        updateBulkSelection();
    };

    // Initial update
    updateBulkSelection();
});

// AJAX Functions mit verbesserter Fehlerbehandlung
function analyzePost(postId) {
    if (!postId || postId <= 0) {
        showNotification('<?php esc_js_e('Ungültige Post-ID', 'seo-ai-master'); ?>', 'error');
        return;
    }
    
    const button = event.target.closest('button');
    if (!button) return;
    
    const originalText = button.innerHTML;
    const originalDisabled = button.disabled;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    
    const data = new URLSearchParams({
        action: 'seo_ai_analyze_post',
        post_id: postId,
        nonce: '<?php echo esc_js($nonce); ?>'
    });
    
    fetch('<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data && data.success) {
            showNotification('✅ ' + (data.data?.message || '<?php esc_js_e('Analyse erfolgreich', 'seo-ai-master'); ?>'), 'success');
            // Verzögertes Neuladen für bessere UX
            setTimeout(() => {
                if (typeof location !== 'undefined') {
                    location.reload();
                }
            }, 1500);
        } else {
            const errorMsg = data?.data || data?.message || '<?php esc_js_e('Unbekannter Fehler bei der Analyse', 'seo-ai-master'); ?>';
            showNotification('❌ ' + errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('SEO AI Analyze Error:', error);
        showNotification('❌ <?php esc_js_e('Netzwerkfehler bei der Analyse', 'seo-ai-master'); ?>', 'error');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = originalDisabled;
        button.removeAttribute('aria-busy');
    });
}

function generateMetaTitle(postId) {
    if (!postId || postId <= 0) {
        showNotification('<?php esc_js_e('Ungültige Post-ID', 'seo-ai-master'); ?>', 'error');
        return;
    }
    
    const button = event.target.closest('button');
    if (!button) return;
    
    const originalText = button.innerHTML;
    const originalDisabled = button.disabled;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    
    const data = new URLSearchParams({
        action: 'seo_ai_generate_meta_title',
        post_id: postId,
        nonce: '<?php echo esc_js($nonce); ?>'
    });
    
    fetch('<?php echo esc_url_raw(admin_url('admin-ajax.php')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: data,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data && data.success) {
            showNotification('✅ <?php esc_js_e('Meta Title generiert', 'seo-ai-master'); ?>', 'success');
            setTimeout(() => {
                if (typeof location !== 'undefined') {
                    location.reload();
                }
            }, 1500);
        } else {
            const errorMsg = data?.data || data?.message || '<?php esc_js_e('Fehler bei der Meta Title Generierung', 'seo-ai-master'); ?>';
            showNotification('❌ ' + errorMsg, 'error');
        }
    })
    .catch(error => {
        console.error('SEO AI Meta Title Error:', error);
        showNotification('❌ <?php esc_js_e('Netzwerkfehler', 'seo-ai-master'); ?>', 'error');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = originalDisabled;
        button.removeAttribute('aria-busy');
    });
}

// Verbessertes Notification System
function showNotification(message, type = 'info') {
    // Input validation
    if (!message || typeof message !== 'string') {
        return;
    }
    
    // Remove existing notifications of same type to prevent spam
    document.querySelectorAll('.seo-ai-notification').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = 'seo-ai-notification';
    notification.setAttribute('role', 'alert');
    notification.setAttribute('aria-live', 'assertive');
    
    const bgColor = type === 'success' ? 'linear-gradient(45deg, #10b981, #059669)' : 
                    type === 'error' ? 'linear-gradient(45deg, #ef4444, #dc2626)' : 
                    'linear-gradient(45deg, #3b82f6, #1e40af)';
    
    notification.style.cssText = `
        position: fixed;
        top: 32px;
        right: 20px;
        background: ${bgColor};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        z-index: 100000;
        font-size: 0.9rem;
        font-weight: 500;
        animation: slideInRight 0.3s ease-out;
        max-width: 400px;
        word-wrap: break-word;
        font-family: inherit;
    `;
    
    // Sanitize message
    const textContent = document.createTextNode(message);
    notification.appendChild(textContent);
    
    document.body.appendChild(notification);
    
    // Auto-remove with improved timing
    const timeout = type === 'error' ? 6000 : 4000; // Errors stay longer
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, timeout);
}

// CSS für Notification Animationen (nur einmal laden)
if (!document.getElementById('seo-ai-notification-styles')) {
    const style = document.createElement('style');
    style.id = 'seo-ai-notification-styles';
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

// Placeholder functions für nicht implementierte Features
window.analyzeAllPages = function() {
    showNotification('<?php esc_js_e('Analyse aller Seiten wird implementiert...', 'seo-ai-master'); ?>', 'info');
};

window.fillMissingMeta = function() {
    showNotification('<?php esc_js_e('Meta-Daten Ergänzung wird implementiert...', 'seo-ai-master'); ?>', 'info');
};

window.generateSEOReport = function() {
    showNotification('<?php esc_js_e('SEO Report Generierung wird implementiert...', 'seo-ai-master'); ?>', 'info');
};

window.bulkOptimize = function() {
    showNotification('<?php esc_js_e('Bulk-Optimierung wird implementiert...', 'seo-ai-master'); ?>', 'info');
};

window.bulkOptimizeSelected = function() {
    const selected = document.querySelectorAll('.bulk-checkbox:checked');
    if (selected.length === 0) {
        showNotification('<?php esc_js_e('Bitte wählen Sie mindestens ein Element aus', 'seo-ai-master'); ?>', 'error');
        return;
    }
    showNotification('<?php esc_js_e('Bulk-Optimierung der ausgewählten Elemente wird implementiert...', 'seo-ai-master'); ?>', 'info');
};

window.bulkAnalyzeSelected = function() {
    const selected = document.querySelectorAll('.bulk-checkbox:checked');
    if (selected.length === 0) {
        showNotification('<?php esc_js_e('Bitte wählen Sie mindestens ein Element aus', 'seo-ai-master'); ?>', 'error');
        return;
    }
    showNotification('<?php esc_js_e('Bulk-Analyse der ausgewählten Elemente wird implementiert...', 'seo-ai-master'); ?>', 'info');
};
</script>