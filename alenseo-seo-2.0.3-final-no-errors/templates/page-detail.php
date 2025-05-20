<?php /** * Template für die Detailansicht einer einzelnen Seite * * @link https://www.imponi.ch * @since 1.0.0 * * @package Alenseo * @subpackage Alenseo/templates */ // Direkter Zugriff verhindern if (!defined('ABSPATH')) { exit; } // Prüfen, ob eine Post-ID übergeben wurde $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0; if (!$post_id) { wp_die(__('Keine Seite ausgewählt.', 'alenseo')); } // Post-Daten abrufen $post = get_post($post_id); if (!$post) { wp_die(__('Seite nicht gefunden.', 'alenseo')); } // Meta-Daten abrufen $focus_keyword = get_post_meta($post_id, '_alenseo_keyword', true); $seo_score = (int)get_post_meta($post_id, '_alenseo_seo_score', true); $seo_status = get_post_meta($post_id, '_alenseo_seo_status', true); $last_analysis = get_post_meta($post_id, '_alenseo_last_analysis', true); // Meta-Description aus verschiedenen Quellen abrufen $meta_description = get_post_meta($post_id, '_alenseo_meta_description', true); if (empty($meta_description)) { // Yoast SEO $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true); // All in One SEO if (empty($meta_description)) { $meta_description = get_post_meta($post_id, '_aioseo_description', true); } if (empty($meta_description)) { $meta_description = get_post_meta($post_id, '_aioseop_description', true); } // Rank Math if (empty($meta_description)) { $meta_description = get_post_meta($post_id, 'rank_math_description', true); } // SEOPress if (empty($meta_description)) { $meta_description = get_post_meta($post_id, '_seopress_titles_desc', true); } // WPBakery if (empty($meta_description)) { $meta_description = get_post_meta($post_id, 'vc_description', true); } // Fallback: Excerpt if (empty($meta_description) && !empty($post->post_excerpt)) { $meta_description = $post->post_excerpt; } } // SEO-Status-Klasse bestimmen $status_class = 'unknown'; if ($seo_score >= 80) { $status_class = 'good'; $status_text = __('Gut optimiert', 'alenseo'); } elseif ($seo_score >= 50) { $status_class = 'ok'; $status_text = __('Teilweise optimiert', 'alenseo'); } else { $status_class = 'poor'; $status_text = __('Optimierung nötig', 'alenseo'); } // Überprüfen ob Claude API verfügbar ist $settings = get_option('alenseo_settings', array()); $claude_api_active = !empty($settings['claude_api_key']); // Zurück-URL berechnen $back_url = admin_url('admin.php?page=alenseo-optimizer'); // Dashboard-Objekt für Helper-Funktionen abrufen global $alenseo_dashboard; if (!isset($alenseo_dashboard) && class_exists('Alenseo_Dashboard')) { $alenseo_dashboard = new Alenseo_Dashboard(); } ?> <div class="wrap alenseo-page-detail-wrap"> <h1 class="wp-heading-inline"> <a href="<?php echo esc_url($back_url); ?>" class="page-title-action alenseo-back-button"> <span class="dashicons dashicons-arrow-left-alt"></span> <?php _e('Zurück zur Übersicht', 'alenseo'); ?> </a> <?php echo esc_html($post->post_title); ?> <a href="<?php echo get_edit_post_link($post_id); ?>" class="page-title-action"> <span class="dashicons dashicons-edit"></span> <?php _e('Bearbeiten', 'alenseo'); ?> </a> <a href="<?php echo get_permalink($post_id); ?>" class="page-title-action" target="_blank"> <span class="dashicons dashicons-visibility"></span> <?php _e('Ansehen', 'alenseo'); ?> </a> </h1>
<div class="alenseo-page-header">
    <div class="alenseo-page-meta">
        <div class="alenseo-meta-item">
            <span class="alenseo-meta-label"><?php _e('Typ:', 'alenseo'); ?></span>
            <span class="alenseo-meta-value"><?php echo get_post_type_object($post->post_type)->labels->singular_name; ?></span>
        </div>
        
        <div class="alenseo-meta-item">
            <span class="alenseo-meta-label"><?php _e('Fokus-Keyword:', 'alenseo'); ?></span>
            <span class="alenseo-meta-value">
                <?php if (!empty($focus_keyword)) : ?>
                    <span class="alenseo-keyword-badge"><?php echo esc_html($focus_keyword); ?></span>
                    <button type="button" class="alenseo-change-keyword button-link">
                        <span class="dashicons dashicons-edit-large"></span>
                    </button>
                <?php else : ?>
                    <span class="alenseo-no-keyword"><?php _e('Nicht gesetzt', 'alenseo'); ?></span>
                    <button type="button" class="alenseo-set-keyword button-primary button-small">
                        <?php _e('Keyword setzen', 'alenseo'); ?>
                    </button>
                <?php endif; ?>
            </span>
        </div>
        
        <div class="alenseo-meta-item">
            <span class="alenseo-meta-label"><?php _e('SEO-Score:', 'alenseo'); ?></span>
            <span class="alenseo-meta-value">
                <div class="alenseo-score-pill <?php echo esc_attr('score-' . $status_class); ?>">
                    <?php echo esc_html($seo_score); ?>
                </div>
                <span class="alenseo-status alenseo-status-<?php echo esc_attr($status_class); ?>">
                    <?php echo esc_html($status_text); ?>
                </span>
            </span>
        </div>
        
        <?php if (!empty($last_analysis)) : ?>
        <div class="alenseo-meta-item">
            <span class="alenseo-meta-label"><?php _e('Letzte Analyse:', 'alenseo'); ?></span>
            <span class="alenseo-meta-value">
                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_analysis)); ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="alenseo-page-actions">
        <button type="button" class="button alenseo-analyze-button" data-post-id="<?php echo esc_attr($post_id); ?>">
            <span class="dashicons dashicons-visibility"></span> <?php _e('Neu analysieren', 'alenseo'); ?>
        </button>
        <?php if ($claude_api_active) : ?>
        <button type="button" class="button button-primary alenseo-optimize-all-button" data-post-id="<?php echo esc_attr($post_id); ?>">
            <span class="dashicons dashicons-superhero"></span> <?php _e('Alles optimieren', 'alenseo'); ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs für verschiedene SEO-Bereiche -->
<div class="alenseo-page-tabs">
    <div class="nav-tab-wrapper">
        <a href="#meta" class="nav-tab nav-tab-active"><?php _e('Meta-Daten', 'alenseo'); ?></a>
        <a href="#content" class="nav-tab"><?php _e('Inhalt', 'alenseo'); ?></a>
        <a href="#analysis" class="nav-tab"><?php _e('Analyse', 'alenseo'); ?></a>
        <?php if ($claude_api_active) : ?>
        <a href="#suggestions" class="nav-tab"><?php _e('Optimierungsvorschläge', 'alenseo'); ?></a>
        <?php endif; ?>
    </div>
    
    <!-- Meta-Daten-Tab -->
    <div id="meta" class="alenseo-tab-content active">
        <div class="alenseo-section">
            <h3><?php _e('Meta-Titel', 'alenseo'); ?></h3>
            <div class="alenseo-section-content">
                <div class="alenseo-content-preview">
                    <p class="alenseo-current-content"><?php echo esc_html($post->post_title); ?></p>
                    <div class="alenseo-content-stats">
                        <span class="alenseo-content-length" data-optimal-min="30" data-optimal-max="60">
                            <?php 
                            $title_length = mb_strlen($post->post_title);
                            $title_class = ($title_length < 30 || $title_length > 60) ? 'warning' : 'good';
                            ?>
                            <span class="stat-value <?php echo esc_attr($title_class); ?>"><?php echo esc_html($title_length); ?></span> <?php _e('Zeichen', 'alenseo'); ?>
                        </span>
                        <?php if (!empty($focus_keyword) && stripos($post->post_title, $focus_keyword) !== false) : ?>
                            <span class="alenseo-keyword-presence good">
                                <span class="dashicons dashicons-yes-alt"></span> <?php _e('Keyword enthalten', 'alenseo'); ?>
                            </span>
                        <?php elseif (!empty($focus_keyword)) : ?>
                            <span class="alenseo-keyword-presence warning">
                                <span class="dashicons dashicons-warning"></span> <?php _e('Keyword fehlt', 'alenseo'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($claude_api_active) : ?>
                <div class="alenseo-optimization-tools">
                    <button type="button" class="button alenseo-optimize-button" data-type="title" data-post-id="<?php echo esc_attr($post_id); ?>">
                        <span class="dashicons dashicons-superhero"></span> <?php _e('Mit Claude optimieren', 'alenseo'); ?>
                    </button>
                </div>
                <div class="alenseo-optimization-result" id="title-optimization-result" style="display: none;">
                    <h4><?php _e('Optimierungsvorschlag:', 'alenseo'); ?></h4>
                    <div class="alenseo-suggestion-content"></div>
                    <div class="alenseo-suggestion-actions">
                        <button type="button" class="button button-primary alenseo-apply-suggestion" data-type="title">
                            <?php _e('Vorschlag übernehmen', 'alenseo'); ?>
                        </button>
                        <button type="button" class="button alenseo-cancel-suggestion">
                            <?php _e('Verwerfen', 'alenseo'); ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="alenseo-section">
            <h3><?php _e('Meta-Description', 'alenseo'); ?></h3>
            <div class="alenseo-section-content">
                <div class="alenseo-content-preview">
                    <?php if (!empty($meta_description)) : ?>
                        <p class="alenseo-current-content"><?php echo esc_html($meta_description); ?></p>
                        <div class="alenseo-content-stats">
                            <span class="alenseo-content-length" data-optimal-min="120" data-optimal-max="160">
                                <?php 
                                $desc_length = mb_strlen($meta_description);
                                $desc_class = ($desc_length < 120 || $desc_length > 160) ? 'warning' : 'good';
                                ?>
                                <span class="stat-value <?php echo esc_attr($desc_class); ?>"><?php echo esc_html($desc_length); ?></span> <?php _e('Zeichen', 'alenseo'); ?>
                            </span>
                            <?php if (!empty($focus_keyword) && stripos($meta_description, $focus_keyword) !== false) : ?>
                                <span class="alenseo-keyword-presence good">
                                    <span class="dashicons dashicons-yes-alt"></span> <?php _e('Keyword enthalten', 'alenseo'); ?>
                                </span>
                            <?php elseif (!empty($focus_keyword)) : ?>
                                <span class="alenseo-keyword-presence warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Keyword fehlt', 'alenseo'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <p class="alenseo-empty-content"><?php _e('Keine Meta-Description definiert.', 'alenseo'); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($claude_api_active) : ?>
                <div class="alenseo-optimization-tools">
                    <button type="button" class="button alenseo-optimize-button" data-type="meta_description" data-post-id="<?php echo esc_attr($post_id); ?>">
                        <span class="dashicons dashicons-superhero"></span> <?php _e('Mit Claude optimieren', 'alenseo'); ?>
                    </button>
                </div>
                <div class="alenseo-optimization-result" id="meta_description-optimization-result" style="display: none;">
                    <h4><?php _e('Optimierungsvorschlag:', 'alenseo'); ?></h4>
                    <div class="alenseo-suggestion-content"></div>
                    <div class="alenseo-suggestion-actions">
                        <button type="button" class="button button-primary alenseo-apply-suggestion" data-type="meta_description">
                            <?php _e('Vorschlag übernehmen', 'alenseo'); ?>
                        </button>
                        <button type="button" class="button alenseo-cancel-suggestion">
                            <?php _e('Verwerfen', 'alenseo'); ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="alenseo-section">
            <h3><?php _e('URL / Permalink', 'alenseo'); ?></h3>
            <div class="alenseo-section-content">
                <div class="alenseo-content-preview">
                    <p class="alenseo-current-content alenseo-url-display"><?php echo esc_html(get_permalink($post_id)); ?></p>
                    <div class="alenseo-content-stats">
                        <?php
                        $slug = $post->post_name;
                        $slug_length = mb_strlen($slug);
                        $slug_class = ($slug_length < 3 || $slug_length > 75) ? 'warning' : 'good';
                        
                        $has_keyword_in_slug = false;
                        if (!empty($focus_keyword)) {
                            $keyword_slug = sanitize_title($focus_keyword);
                            $has_keyword_in_slug = (strpos($slug, $keyword_slug) !== false);
                        }
                        ?>
                        <span class="alenseo-content-length">
                            <span class="stat-value <?php echo esc_attr($slug_class); ?>"><?php echo esc_html($slug_length); ?></span> <?php _e('Zeichen', 'alenseo'); ?>
                        </span>
                        <?php if (!empty($focus_keyword) && $has_keyword_in_slug) : ?>
                            <span class="alenseo-keyword-presence good">
                                <span class="dashicons dashicons-yes-alt"></span> <?php _e('Keyword enthalten', 'alenseo'); ?>
                            </span>
                        <?php elseif (!empty($focus_keyword)) : ?>
                            <span class="alenseo-keyword-presence warning">
                                <span class="dashicons dashicons-warning"></span> <?php _e('Keyword fehlt', 'alenseo'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="alenseo-note">
                    <p><span class="dashicons dashicons-info"></span> <?php _e('Hinweis: Die URL sollte nur über die WordPress-Bearbeitungsseite geändert werden, um Weiterleitungsprobleme zu vermeiden.', 'alenseo'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inhalt-Tab -->
    <div id="content" class="alenseo-tab-content">
        <div class="alenseo-section">
            <h3><?php _e('Seiteninhalt', 'alenseo'); ?></h3>
            <div class="alenseo-section-content">
                <?php
                $content = $post->post_content;
                $content_text = wp_strip_all_tags($content);
                $word_count = str_word_count($content_text);
                
                // Keyword-Dichte berechnen
                $keyword_count = 0;
                $keyword_density = 0;
                if (!empty($focus_keyword) && $word_count > 0) {
                    $keyword_count = substr_count(strtolower($content_text), strtolower($focus_keyword));
                    $keyword_density = ($keyword_count / $word_count) * 100;
                }
                
                // Überschriften extrahieren
                $headings = array();
                $heading_matches = array();
                
                // H1-Überschriften
                preg_match_all('/<h1[^>]*>(.*?)<\/h1>/si', $content, $h1_matches);
                if (!empty($h1_matches[1])) {
                    $headings['h1'] = $h1_matches[1];
                }
                
                // H2-Überschriften
                preg_match_all('/<h2[^>]*>(.*?)<\/h2>/si', $content, $h2_matches);
                if (!empty($h2_matches[1])) {
                    $headings['h2'] = $h2_matches[1];
                }
                
                // H3-Überschriften
                preg_match_all('/<h3[^>]*>(.*?)<\/h3>/si', $content, $h3_matches);
                if (!empty($h3_matches[1])) {
                    $headings['h3'] = $h3_matches[1];
                }
                
                // Bilder zählen
                preg_match_all('/<img[^>]*>/i', $content, $image_matches);
                $image_count = count($image_matches[0]);
                
                // Alt-Texte zählen
                $images_with_alt = 0;
                foreach ($image_matches[0] as $img) {
                    if (preg_match('/alt=["\'][^"\']*["\']/', $img)) {
                        $images_with_alt++;
                    }
                }
                
                // Links zählen
                preg_match_all('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si', $content, $link_matches);
                $link_count = count($link_matches[0]);
                
                // Externe und interne Links unterscheiden
                $external_links = 0;
                $internal_links = 0;
                $site_url = site_url();
                
                foreach ($link_matches[1] as $link) {
                    if (strpos($link, $site_url) === 0 || strpos($link, '/') === 0) {
                        $internal_links++;
                    } else {
                        $external_links++;
                    }
                }
                ?>
                
                <div class="alenseo-content-stats-grid">
                    <div class="alenseo-content-stat-card">
                        <div class="alenseo-stat-title"><?php _e('Wortanzahl', 'alenseo'); ?></div>
                        <div class="alenseo-stat-value"><?php echo number_format_i18n($word_count); ?></div>
                        <div class="alenseo-stat-footer">
                            <?php if ($word_count < 300) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Zu wenig Inhalt. Mindestens 300 Wörter empfohlen.', 'alenseo'); ?>
                                </span>
                            <?php elseif ($word_count >= 300 && $word_count < 600) : ?>
                                <span class="alenseo-stat-ok">
                                    <span class="dashicons dashicons-yes"></span> <?php _e('Ausreichend. Mehr Inhalt könnte SEO-Wert erhöhen.', 'alenseo'); ?>
                                </span>
                            <?php else : ?>
                                <span class="alenseo-stat-good">
                                    <span class="dashicons dashicons-yes-alt"></span> <?php _e('Gute Inhaltsmenge für SEO.', 'alenseo'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($focus_keyword)) : ?>
                    <div class="alenseo-content-stat-card">
                        <div class="alenseo-stat-title"><?php _e('Keyword-Dichte', 'alenseo'); ?></div>
                        <div class="alenseo-stat-value"><?php echo number_format_i18n($keyword_density, 2); ?>%</div>
                        <div class="alenseo-stat-footer">
                            <?php if ($keyword_count === 0) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Keyword nicht im Inhalt gefunden.', 'alenseo'); ?>
                                </span>
                            <?php elseif ($keyword_density < 0.5) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Keyword-Dichte zu niedrig. Ideal: 0,5-2,5%.', 'alenseo'); ?>
                                </span>
                            <?php elseif ($keyword_density > 2.5) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Keyword-Dichte zu hoch (Keyword-Stuffing).', 'alenseo'); ?>
                                </span>
                            <?php else : ?>
                                <span class="alenseo-stat-good">
                                    <span class="dashicons dashicons-yes-alt"></span> <?php _e('Optimale Keyword-Dichte.', 'alenseo'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="alenseo-content-stat-card">
                        <div class="alenseo-stat-title"><?php _e('Überschriften', 'alenseo'); ?></div>
                        <div class="alenseo-stat-value">
                            <?php 
                            $h1_count = isset($headings['h1']) ? count($headings['h1']) : 0;
                            $h2_count = isset($headings['h2']) ? count($headings['h2']) : 0;
                            $h3_count = isset($headings['h3']) ? count($headings['h3']) : 0;
                            
                            echo esc_html(sprintf(
                                __('H1: %1$d, H2: %2$d, H3: %3$d', 'alenseo'),
                                $h1_count, $h2_count, $h3_count
                            ));
                            ?>
                        </div>
                        <div class="alenseo-stat-footer">
                            <?php if ($h1_count === 0 && $h2_count === 0 && $h3_count === 0) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Keine Überschriften gefunden.', 'alenseo'); ?>
                                </span>
                            <?php elseif ($h1_count > 1) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Zu viele H1-Überschriften (maximal eine empfohlen).', 'alenseo'); ?>
                                </span>
                            <?php elseif ($h2_count === 0) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Keine H2-Überschriften gefunden.', 'alenseo'); ?>
                                </span>
                            <?php else : ?>
                                <span class="alenseo-stat-good">
                                    <span class="dashicons dashicons-yes-alt"></span> <?php _e('Gute Überschriftenstruktur.', 'alenseo'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="alenseo-content-stat-card">
                        <div class="alenseo-stat-title"><?php _e('Bilder', 'alenseo'); ?></div>
                        <div class="alenseo-stat-value">
                            <?php echo number_format_i18n($image_count); ?>
                            <?php if ($image_count > 0) : ?>
                                <small>(<?php echo sprintf(__('%d mit Alt-Text', 'alenseo'), $images_with_alt); ?>)</small>
                            <?php endif; ?>
                        </div>
                        <div class="alenseo-stat-footer">
                            <?php if ($image_count === 0) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Keine Bilder gefunden. Bilder verbessern SEO.', 'alenseo'); ?>
                                </span>
                            <?php elseif ($images_with_alt < $image_count) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Nicht alle Bilder haben Alt-Texte.', 'alenseo'); ?>
                                </span>
                            <?php else : ?>
                                <span class="alenseo-stat-good">
                                    <span class="dashicons dashicons-yes-alt"></span> <?php _e('Alle Bilder haben Alt-Texte. Sehr gut!', 'alenseo'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="alenseo-content-stat-card">
                        <div class="alenseo-stat-title"><?php _e('Links', 'alenseo'); ?></div>
                        <div class="alenseo-stat-value">
                            <?php echo sprintf(__('Intern: %1$d, Extern: %2$d', 'alenseo'), $internal_links, $external_links); ?>
                        </div>
                        <div class="alenseo-stat-footer">
                            <?php if ($link_count === 0) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Keine Links gefunden. Links verbessern SEO.', 'alenseo'); ?>
                                </span>
                            <?php elseif ($external_links === 0) : ?>
                                <span class="alenseo-stat-warning">
                                    <span class="dashicons dashicons-warning"></span> <?php _e('Keine externen Links. Diese können SEO verbessern.', 'alenseo'); ?>
                                </span>
                            <?php else : ?>
                                <span class="alenseo-stat-good">
                                    <span class="dashicons dashicons-yes-alt"></span> <?php _e('Gute Linkstruktur.', 'alenseo'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="alenseo-content-details">
                    <?php if (!empty($headings)) : ?>
                    <div class="alenseo-content-detail-section">
                        <h4><?php _e('Überschriften', 'alenseo'); ?></h4>
                        <div class="alenseo-content-detail-list">
                            <?php foreach ($headings as $tag => $headers) : ?>
                                <div class="alenseo-detail-group">
                                    <div class="alenseo-detail-title"><?php echo strtoupper($tag); ?></div>
                                    <ul class="alenseo-detail-items">
                                        <?php foreach ($headers as $header) : ?>
                                            <li>
                                                <?php echo esc_html(wp_strip_all_tags($header)); ?>
                                                <?php if (!empty($focus_keyword) && stripos($header, $focus_keyword) !== false) : ?>
                                                    <span class="alenseo-keyword-highlight" title="<?php esc_attr_e('Enthält Fokus-Keyword', 'alenseo'); ?>">
                                                        <span class="dashicons dashicons-yes-alt"></span>
                                                    </span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($claude_api_active) : ?>
                <div class="alenseo-optimization-tools">
                    <button type="button" class="button alenseo-optimize-button" data-type="content" data-post-id="<?php echo esc_attr($post_id); ?>">
                        <span class="dashicons dashicons-superhero"></span> <?php _e('Inhalt mit Claude optimieren', 'alenseo'); ?>
                    </button>
                </div>
                <div class="alenseo-optimization-result" id="content-optimization-result" style="display: none;">
                    <h4><?php _e('Inhaltsoptimierungsvorschläge:', 'alenseo'); ?></h4>
                    <div class="alenseo-suggestion-content"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Analyse-Tab -->
    <div id="analysis" class="alenseo-tab-content">
        <div class="alenseo-section">
            <h3><?php _e('SEO-Analyse', 'alenseo'); ?></h3>
            <div class="alenseo-section-content">
                <div class="alenseo-analysis-overview">
                    <div class="alenseo-score-circle large <?php echo esc_attr('score-' . $status_class); ?>">
                        <div class="alenseo-score-value"><?php echo esc_html($seo_score); ?></div>
                        <div class="alenseo-score-label"><?php _e('SEO-Score', 'alenseo'); ?></div>
                    </div>
                    
                    <div class="alenseo-analysis-status">
                        <div class="alenseo-status alenseo-status-<?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html($status_text); ?>
                        </div>
                        
                        <?php if (!empty($last_analysis)) : ?>
                            <div class="alenseo-analysis-date">
                                <?php echo sprintf(__('Letzte Analyse: %s', 'alenseo'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_analysis))); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alenseo-analysis-actions">
                            <button type="button" class="button alenseo-analyze-button" data-post-id="<?php echo esc_attr($post_id); ?>">
                                <span class="dashicons dashicons-visibility"></span> <?php _e('Neu analysieren', 'alenseo'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php
                // Detaillierte Analyseergebnisse
                $title_score = (int)get_post_meta($post_id, '_alenseo_title_score', true);
                $title_message = get_post_meta($post_id, '_alenseo_title_message', true);
                $content_score = (int)get_post_meta($post_id, '_alenseo_content_score', true);
                $content_message = get_post_meta($post_id, '_alenseo_content_message', true);
                $url_score = (int)get_post_meta($post_id, '_alenseo_url_score', true);
                $url_message = get_post_meta($post_id, '_alenseo_url_message', true);
                $meta_description_score = (int)get_post_meta($post_id, '_alenseo_meta_description_score', true);
                $meta_description_message = get_post_meta($post_id, '_alenseo_meta_description_message', true);
                
                // Helper-Funktion für Status-Klassen
                function get_score_class($score) {
                    if ($score >= 80) {
                        return 'good';
                    } elseif ($score >= 50) {
                        return 'ok';
                    } else {
                        return 'poor';
                    }
                }
                ?>
                
                <div class="alenseo-analysis-details">
                    <div class="alenseo-analysis-item">
                        <div class="alenseo-analysis-header <?php echo esc_attr(get_score_class($title_score)); ?>">
                            <div class="alenseo-analysis-title"><?php _e('Titel', 'alenseo'); ?></div>
                            <div class="alenseo-analysis-score"><?php echo esc_html($title_score); ?></div>
                        </div>
                        <div class="alenseo-analysis-message">
                            <?php echo !empty($title_message) ? esc_html($title_message) : __('Keine Analyse-Daten vorhanden.', 'alenseo'); ?>
                        </div>
                    </div>
                    
                    <div class="alenseo-analysis-item">
                        <div class="alenseo-analysis-header <?php echo esc_attr(get_score_class($meta_description_score)); ?>">
                            <div class="alenseo-analysis-title"><?php _e('Meta-Description', 'alenseo'); ?></div>
                            <div class="alenseo-analysis-score"><?php echo esc_html($meta_description_score); ?></div>
                        </div>
                        <div class="alenseo-analysis-message">
                            <?php echo !empty($meta_description_message) ? esc_html($meta_description_message) : __('Keine Analyse-Daten vorhanden.', 'alenseo'); ?>
                        </div>
                    </div>
                    
                    <div class="alenseo-analysis-item">
                        <div class="alenseo-analysis-header <?php echo esc_attr(get_score_class($url_score)); ?>">
                            <div class="alenseo-analysis-title"><?php _e('URL', 'alenseo'); ?></div>
                            <div class="alenseo-analysis-score"><?php echo esc_html($url_score); ?></div>
                        </div>
                        <div class="alenseo-analysis-message">
                            <?php echo !empty($url_message) ? esc_html($url_message) : __('Keine Analyse-Daten vorhanden.', 'alenseo'); ?>
                        </div>
                    </div>
                    
                    <div class="alenseo-analysis-item">
                        <div class="alenseo-analysis-header <?php echo esc_attr(get_score_class($content_score)); ?>">
                            <div class="alenseo-analysis-title"><?php _e('Inhalt', 'alenseo'); ?></div>
                            <div class="alenseo-analysis-score"><?php echo esc_html($content_score); ?></div>
                        </div>
                        <div class="alenseo-analysis-message">
                            <?php echo !empty($content_message) ? esc_html($content_message) : __('Keine Analyse-Daten vorhanden.', 'alenseo'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($claude_api_active) : ?>
    <!-- Optimierungsvorschläge-Tab -->
    <div id="suggestions" class="alenseo-tab-content">
        <div class="alenseo-section">
            <h3><?php _e('Optimierungsvorschläge', 'alenseo'); ?></h3>
            <div class="alenseo-section-content">
                <?php if (empty($focus_keyword)) : ?>
                    <div class="alenseo-notice notice-warning">
                        <p>
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('Für Optimierungsvorschläge wird ein Fokus-Keyword benötigt.', 'alenseo'); ?>
                            <button type="button" class="button button-small alenseo-set-keyword">
                                <?php _e('Keyword setzen', 'alenseo'); ?>
                            </button>
                        </p>
                    </div>
                <?php else : ?>
                    <div class="alenseo-optimization-intro">
                        <p>
                            <?php echo sprintf(__('Erhalte KI-gestützte Optimierungsvorschläge für "%s".', 'alenseo'), '<strong>' . esc_html($focus_keyword) . '</strong>'); ?>
                        </p>
                        <div class="alenseo-optimization-options">
                            <label>
                                <input type="checkbox" name="optimize_title" checked>
                                <?php _e('Titel optimieren', 'alenseo'); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="optimize_meta_description" checked>
                                <?php _e('Meta-Description optimieren', 'alenseo'); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="optimize_content" checked>
                                <?php _e('Inhaltsvorschläge', 'alenseo'); ?>
                            </label>
                        </div>
                        <div class="alenseo-advanced-options">
                            <a href="#" class="alenseo-toggle-advanced">
                                <span class="dashicons dashicons-arrow-right-alt2"></span> <?php _e('Erweiterte Optionen', 'alenseo'); ?>
                            </a>
                            <div class="alenseo-advanced-options-content" style="display: none;">
                                <div class="alenseo-advanced-option">
                                    <label for="alenseo-optimization-tone"><?php _e('Tonfall:', 'alenseo'); ?></label>
                                    <select id="alenseo-optimization-tone">
                                        <option value="professional"><?php _e('Professionell', 'alenseo'); ?></option>
                                        <option value="friendly"><?php _e('Freundlich', 'alenseo'); ?></option>
                                        <option value="casual"><?php _e('Casual', 'alenseo'); ?></option>
                                        <option value="formal"><?php _e('Formell', 'alenseo'); ?></option>
                                    </select>
                                </div>
                                <div class="alenseo-advanced-option">
                                    <label for="alenseo-optimization-level"><?php _e('Optimierungsgrad:', 'alenseo'); ?></label>
                                    <select id="alenseo-optimization-level">
                                        <option value="light"><?php _e('Leicht', 'alenseo'); ?></option>
                                        <option value="moderate" selected><?php _e('Moderat', 'alenseo'); ?></option>
                                        <option value="aggressive"><?php _e('Aggressiv', 'alenseo'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="alenseo-optimization-actions">
                            <button type="button" class="button button-primary alenseo-bulk-optimize-button" data-post-id="<?php echo esc_attr($post_id); ?>">
                                <span class="dashicons dashicons-superhero"></span> <?php _e('Optimierungsvorschläge generieren', 'alenseo'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="alenseo-optimization-status" style="display: none;">
                        <div class="alenseo-loader">
                            <span class="spinner is-active"></span>
                            <span class="alenseo-loader-text"><?php _e('Generiere Optimierungsvorschläge...', 'alenseo'); ?></span>
                        </div>
                    </div>
                    
                    <div class="alenseo-bulk-suggestions" style="display: none;">
                        <div class="alenseo-suggestion-section" id="title-suggestion-section">
                            <h4><?php _e('Titel-Optimierung', 'alenseo'); ?></h4>
                            <div class="alenseo-before-after">
                                <div class="alenseo-before">
                                    <h5><?php _e('Aktuell', 'alenseo'); ?></h5>
                                    <div class="alenseo-content-box"><?php echo esc_html($post->post_title); ?></div>
                                </div>
                                <div class="alenseo-after">
                                    <h5><?php _e('Optimiert', 'alenseo'); ?></h5>
                                    <div class="alenseo-content-box alenseo-suggestion-content"></div>
                                </div>
                            </div>
                            <div class="alenseo-suggestion-actions">
                                <button type="button" class="button button-primary alenseo-apply-suggestion" data-type="title">
                                    <?php _e('Anwenden', 'alenseo'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="alenseo-suggestion-section" id="meta-description-suggestion-section">
                            <h4><?php _e('Meta-Description-Optimierung', 'alenseo'); ?></h4>
                            <div class="alenseo-before-after">
                                <div class="alenseo-before">
                                    <h5><?php _e('Aktuell', 'alenseo'); ?></h5>
                                    <div class="alenseo-content-box">
                                        <?php echo !empty($meta_description) ? esc_html($meta_description) : '<em>' . __('Keine Meta-Description vorhanden', 'alenseo') . '</em>'; ?>
                                    </div>
                                </div>
                                <div class="alenseo-after">
                                    <h5><?php _e('Optimiert', 'alenseo'); ?></h5>
                                    <div class="alenseo-content-box alenseo-suggestion-content"></div>
                                </div>
                            </div>
                            <div class="alenseo-suggestion-actions">
                                <button type="button" class="button button-primary alenseo-apply-suggestion" data-type="meta_description">
                                    <?php _e('Anwenden', 'alenseo'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="alenseo-suggestion-section" id="content-suggestion-section">
                            <h4><?php _e('Inhaltsoptimierungen', 'alenseo'); ?></h4>
                            <div class="alenseo-content-suggestions">
                                <div class="alenseo-content-box alenseo-suggestion-content">
                                    <!-- Hier werden die Inhaltsvorschläge eingefügt -->
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Keyword-Dialog -->
<div id="alenseo-keyword-dialog" class="alenseo-dialog" style="display: none;">
    <div class="alenseo-dialog-content">
        <div class="alenseo-dialog-header">
            <h2><?php _e('Fokus-Keyword setzen', 'alenseo'); ?></h2>
            <button type="button" class="alenseo-dialog-close">&times;</button>
        </div>
        <div class="alenseo-dialog-body">
            <div class="alenseo-dialog-section">
                <p><?php _e('Gib ein Keyword ein, auf das diese Seite optimiert werden soll:', 'alenseo'); ?></p>
                <div class="alenseo-keyword-input-group">
                    <input type="text" id="alenseo-keyword-input" placeholder="<?php esc_attr_e('z.B. WordPress SEO Plugin', 'alenseo'); ?>">
                </div>
            </div>
            
            <?php if ($claude_api_active) : ?>
            <div class="alenseo-dialog-section">
                <div class="alenseo-keyword-generator">
                    <p><?php _e('Oder lass Claude AI passende Keywords für diese Seite generieren:', 'alenseo'); ?></p>
                    <button type="button" class="button" id="alenseo-dialog-generate-keywords" data-post-id="<?php echo esc_attr($post_id); ?>">
                        <span class="dashicons dashicons-update"></span> <?php _e('Keywords generieren', 'alenseo'); ?>
                    </button>
                </div>
                
                <div class="alenseo-dialog-loader" style="display: none;">
                    <span class="spinner is-active"></span>
                    <span><?php _e('Keywords werden generiert...', 'alenseo'); ?></span>
                </div>
                
                <div class="alenseo-keyword-suggestions" style="display: none;">
                    <h4><?php _e('Vorschläge:', 'alenseo'); ?></h4>
                    <div class="alenseo-keyword-list">
                        <!-- Hier werden die Keyword-Vorschläge eingefügt -->
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="alenseo-dialog-footer">
            <button type="button" class="button alenseo-dialog-close"><?php _e('Abbrechen', 'alenseo'); ?></button>
            <button type="button" class="button button-primary" id="alenseo-save-keyword" data-post-id="<?php echo esc_attr($post_id); ?>">
                <?php _e('Keyword speichern', 'alenseo'); ?>
            </button>
        </div>
    </div>
</div>
</div>
