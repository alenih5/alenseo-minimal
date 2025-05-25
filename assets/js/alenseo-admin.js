/**
 * Alenseo Admin JavaScript
 * Handles admin interface interactions, AJAX requests, and dashboard updates
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Global variables
    const alenseoAdmin = {
        ajaxUrl: alenseo_admin.ajax_url,
        nonce: alenseo_admin.nonce,
        isLoading: false
    };
    
    // Initialize all components
    initDashboard();
    initAPITests();
    initBulkOptimizer();
    initPageOptimizer();
    
    /**
     * Initialize Dashboard
     */
    function initDashboard() {
        if ($('.alenseo-dashboard-wrap').length) {
            loadDashboardData();
            loadAPIStatus();
            
            // Refresh every 5 minutes
            setInterval(loadDashboardData, 5 * 60 * 1000);
        }
    }
    
    /**
     * Load Dashboard Data
     */
    function loadDashboardData() {
        $.ajax({
            url: alenseoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_get_dashboard_data',
                nonce: alenseoAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateDashboardStats(response.data.stats);
                    updateRecentActivity(response.data.recent_activity);
                } else {
                    console.error('Dashboard data load failed:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Dashboard AJAX error:', error);
                showNotice('Fehler beim Laden der Dashboard-Daten', 'error');
            }
        });
    }
    
    /**
     * Load API Status
     */
    function loadAPIStatus() {
        $('.api-status-loading').show();
        $('.api-status-result').hide();
        
        $.ajax({
            url: alenseoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_get_api_status',
                nonce: alenseoAdmin.nonce
            },
            success: function(response) {
                $('.api-status-loading').hide();
                
                if (response.success && response.data) {
                    updateAPIStatus(response.data);
                } else {
                    $('.api-status-result').html('<span class="status-error">Status konnte nicht geladen werden</span>').show();
                }
            },
            error: function(xhr, status, error) {
                $('.api-status-loading').hide();
                $('.api-status-result').html('<span class="status-error">Verbindungsfehler</span>').show();
            }
        });
    }
    
    /**
     * Initialize API Tests
     */
    function initAPITests() {
        // Claude API Test
        $('#alenseo-test-claude-btn').on('click', function() {
            testAPI('claude', $(this));
        });
        
        // OpenAI API Test
        $('#alenseo-test-openai-btn').on('click', function() {
            testAPI('openai', $(this));
        });
    }
    
    /**
     * Test API Connection
     */
    function testAPI(provider, button) {
        const apiKeyField = provider === 'claude' ? '#alenseo-claude-api-key' : '#alenseo-openai-api-key';
        const modelField = provider === 'claude' ? '#alenseo-claude-model' : '#alenseo-openai-model';
        const apiKey = $(apiKeyField).val();
        const model = $(modelField).val();
        
        if (!apiKey) {
            showNotice(`Bitte geben Sie einen ${provider.toUpperCase()} API-Schlüssel ein.`, 'error');
            return;
        }
        
        const originalText = button.text();
        button.prop('disabled', true).text(`${provider.toUpperCase()} API wird getestet...`);
        
        $('#api-test-results').html('<div class="notice notice-info"><p>API wird getestet...</p></div>');
        
        $.ajax({
            url: alenseoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: `alenseo_test_${provider}_api`,
                api_key: apiKey,
                model: model,
                nonce: wp.ajax.settings.nonce || alenseoAdmin.nonce
            },
            timeout: 30000,
            success: function(response) {
                button.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    let html = `<div class="notice notice-success"><p><strong>${provider.toUpperCase()} API Test erfolgreich!</strong></p>`;
                    
                    if (response.data.details) {
                        html += '<ul>';
                        if (response.data.details.model_count) {
                            html += `<li>Verfügbare Modelle: ${response.data.details.model_count}</li>`;
                        }
                        if (response.data.details.fastest_model) {
                            html += `<li>Schnellstes Modell: ${response.data.details.fastest_model}</li>`;
                        }
                        if (response.data.details.recommended_model) {
                            html += `<li>Empfohlenes Modell: ${response.data.details.recommended_model}</li>`;
                        }
                        html += '</ul>';
                    }
                    html += '</div>';
                    
                    $('#api-test-results').html(html);
                    loadAPIStatus(); // Refresh API status
                } else {
                    const message = response.data && response.data.message ? response.data.message : 'Unbekannter Fehler';
                    $('#api-test-results').html(`<div class="notice notice-error"><p><strong>${provider.toUpperCase()} API Test fehlgeschlagen:</strong> ${message}</p></div>`);
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false).text(originalText);
                $('#api-test-results').html(`<div class="notice notice-error"><p><strong>Verbindungsfehler:</strong> ${error}</p></div>`);
            }
        });
    }
    
    /**
     * Initialize Bulk Optimizer
     */
    function initBulkOptimizer() {
        if ($('.alenseo-bulk-optimizer').length) {
            loadPostsTable();
            
            // Search functionality
            $('#posts-search').on('input', debounce(function() {
                loadPostsTable(1, $(this).val());
            }, 500));
            
            // Filter changes
            $('.filter-select').on('change', function() {
                loadPostsTable();
            });
            
            // Bulk analyze button
            $('#bulk-analyze-btn').on('click', function() {
                bulkAnalyzePosts();
            });
        }
    }
    
    /**
     * Initialize Page Optimizer
     */
    function initPageOptimizer() {
        if ($('.alenseo-page-optimizer').length) {
            loadPostsTable();
            
            // Analyze single post
            $(document).on('click', '.analyze-post-btn', function() {
                const postId = $(this).data('post-id');
                analyzePost(postId, $(this));
            });
        }
    }
    
    /**
     * Load Posts Table
     */
    function loadPostsTable(page = 1, search = '') {
        const postType = $('#post-type-filter').val() || 'any';
        const status = $('#status-filter').val() || '';
        
        $('.posts-loading').show();
        $('.posts-table-container').hide();
        
        $.ajax({
            url: alenseoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_load_posts',
                page: page,
                per_page: 10,
                post_type: postType,
                search: search,
                status: status,
                nonce: alenseoAdmin.nonce
            },
            success: function(response) {
                $('.posts-loading').hide();
                
                if (response.success && response.data) {
                    renderPostsTable(response.data);
                    renderPagination(response.data);
                    $('.posts-table-container').show();
                } else {
                    showNotice('Fehler beim Laden der Posts', 'error');
                }
            },
            error: function(xhr, status, error) {
                $('.posts-loading').hide();
                showNotice('Fehler beim Laden der Posts: ' + error, 'error');
            }
        });
    }
    
    /**
     * Render Posts Table
     */
    function renderPostsTable(data) {
        const tbody = $('.alenseo-posts-table tbody');
        tbody.empty();
        
        if (data.posts.length === 0) {
            tbody.append('<tr><td colspan="6">Keine Posts gefunden.</td></tr>');
            return;
        }
        
        data.posts.forEach(function(post) {
            const statusClass = `status-${post.status}`;
            const statusText = getStatusText(post.status);
            
            const row = `
                <tr>
                    <td>
                        <input type="checkbox" class="post-checkbox" value="${post.ID}">
                    </td>
                    <td>
                        <strong><a href="${post.edit_link}" target="_blank">${post.title}</a></strong><br>
                        <small>${post.post_type} | ${post.word_count} Wörter</small>
                    </td>
                    <td>
                        <span class="status-indicator ${statusClass}"></span>
                        ${statusText}
                    </td>
                    <td>${post.seo_score}/100</td>
                    <td>${post.focus_keyword || '<em>Nicht festgelegt</em>'}</td>
                    <td>${post.last_analyzed}</td>
                    <td>
                        <button class="button analyze-post-btn" data-post-id="${post.ID}">
                            Analysieren
                        </button>
                        <a href="${post.permalink}" target="_blank" class="button">Ansehen</a>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    /**
     * Bulk Analyze Posts
     */
    function bulkAnalyzePosts() {
        const selectedPosts = $('.post-checkbox:checked').map(function() {
            return this.value;
        }).get();
        
        if (selectedPosts.length === 0) {
            showNotice('Bitte wählen Sie mindestens einen Post aus.', 'warning');
            return;
        }
        
        const button = $('#bulk-analyze-btn');
        const originalText = button.text();
        button.prop('disabled', true).text('Analysiere...');
        
        $.ajax({
            url: alenseoAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_bulk_analyze',
                post_ids: selectedPosts,
                nonce: alenseoAdmin.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    loadPostsTable(); // Refresh table
                } else {
                    showNotice(response.data.message || 'Fehler bei der Analyse', 'error');
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false).text(originalText);
                showNotice('Fehler bei der Bulk-Analyse: ' + error, 'error');
            }
        });
    }
    
    /**
     * Update Dashboard Stats
     */
    function updateDashboardStats(stats) {
        $('.stat-total-posts .stat-number').text(stats.total_posts);
        $('.stat-analyzed-posts .stat-number').text(stats.analyzed_posts);
        $('.stat-avg-score .stat-number').text(stats.avg_seo_score);
        $('.stat-optimization-rate .stat-number').text(stats.optimization_rate + '%');
    }
    
    /**
     * Update Recent Activity
     */
    function updateRecentActivity(activities) {
        const container = $('.recent-activity-list');
        container.empty();
        
        if (activities.length === 0) {
            container.append('<p>Keine Aktivitäten vorhanden.</p>');
            return;
        }
        
        activities.forEach(function(activity) {
            const item = `
                <div class="activity-item">
                    <strong>${activity.title}</strong><br>
                    Score: ${activity.score}/100 | ${activity.date}
                </div>
            `;
            container.append(item);
        });
    }
    
    /**
     * Update API Status
     */
    function updateAPIStatus(status) {
        let html = '<div class="api-status-grid">';
        
        // Claude API Status
        html += '<div class="api-status-item">';
        html += '<strong>Claude API:</strong> ';
        if (status.claude.configured) {
            html += '<span class="status-configured">Konfiguriert</span>';
        } else {
            html += '<span class="status-not-configured">Nicht konfiguriert</span>';
        }
        html += '</div>';
        
        // OpenAI API Status
        html += '<div class="api-status-item">';
        html += '<strong>OpenAI API:</strong> ';
        if (status.openai.configured) {
            html += '<span class="status-configured">Konfiguriert</span>';
        } else {
            html += '<span class="status-not-configured">Nicht konfiguriert</span>';
        }
        html += '</div>';
        
        html += '</div>';
        $('.api-status-result').html(html).show();
    }
    
    /**
     * Helper Functions
     */
    function getStatusText(status) {
        switch (status) {
            case 'good': return 'Gut';
            case 'needs_improvement': return 'Verbesserung nötig';
            case 'poor': return 'Schlecht';
            default: return 'Unbekannt';
        }
    }
    
    function showNotice(message, type = 'info') {
        const notice = `<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`;
        $('.alenseo-notices').html(notice);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $('.notice').fadeOut();
        }, 5000);
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Handle notice dismissal
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut();
    });
    
    console.log('🚀 Alenseo Admin JavaScript loaded successfully');
});
