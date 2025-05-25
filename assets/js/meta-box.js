/**
 * Alenseo SEO Meta-Box JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // API-Status prüfen
    function checkApiStatus() {
        $.ajax({
            url: alenseoData.ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_test_api',
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.alenseo-api-status').text('API-Verbindung aktiv').css('color', '#28a745');
                } else {
                    $('.alenseo-api-status').text('API-Verbindung nicht verfügbar').css('color', '#dc3545');
                }
            },
            error: function() {
                $('.alenseo-api-status').text('API-Verbindung nicht verfügbar').css('color', '#dc3545');
            }
        });
    }
    
    // Initial API-Status prüfen
    checkApiStatus();
    
    // Post analysieren
    $('.alenseo-analyze-button').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const postId = $button.data('post-id');
        
        $button.prop('disabled', true).html('<span class="alenseo-loading"></span> ' + alenseoData.i18n.analyzing);
        
        $.ajax({
            url: alenseoData.ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_analyze_post',
                post_id: postId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateSeoScore(response.data.score, response.data.status);
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', alenseoData.i18n.error);
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + alenseoData.i18n.analyze);
            }
        });
    });
    
    // Keywords generieren
    $('.alenseo-generate-keywords-button').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const postId = $button.data('post-id');
        
        $button.prop('disabled', true).html('<span class="alenseo-loading"></span> ' + alenseoData.i18n.generating);
        
        $.ajax({
            url: alenseoData.ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_generate_keywords',
                post_id: postId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderKeywordSuggestions(response.data.suggestions);
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', alenseoData.i18n.error);
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-lightbulb"></span> ' + alenseoData.i18n.generateKeywords);
            }
        });
    });
    
    // Meta-Beschreibung optimieren
    $('.alenseo-optimize-meta-button').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const postId = $button.data('post-id');
        const keyword = $('#alenseo_focus_keyword').val();
        
        if (!keyword) {
            showNotice('error', alenseoData.i18n.keywordRequired);
            return;
        }
        
        $button.prop('disabled', true).html('<span class="alenseo-loading"></span> ' + alenseoData.i18n.optimizing);
        
        $.ajax({
            url: alenseoData.ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_optimize_meta_description',
                post_id: postId,
                keyword: keyword,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#alenseo_meta_description').val(response.data.meta_description);
                    updateMetaDescriptionCount();
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', alenseoData.i18n.error);
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-edit"></span> ' + alenseoData.i18n.optimizeMeta);
            }
        });
    });
    
    // Content optimieren
    $('.alenseo-optimize-content-button').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const postId = $button.data('post-id');
        const keyword = $('#alenseo_focus_keyword').val();
        
        if (!keyword) {
            showNotice('error', alenseoData.i18n.keywordRequired);
            return;
        }
        
        $button.prop('disabled', true).html('<span class="alenseo-loading"></span> ' + alenseoData.i18n.optimizing);
        
        $.ajax({
            url: alenseoData.ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_optimize_content',
                post_id: postId,
                keyword: keyword,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                        tinyMCE.get('content').setContent(response.data.content);
                    } else {
                        $('#content').val(response.data.content);
                    }
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message);
                }
            },
            error: function() {
                showNotice('error', alenseoData.i18n.error);
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-edit"></span> ' + alenseoData.i18n.optimizeContent);
            }
        });
    });
    
    // Keyword-Vorschläge rendern
    function renderKeywordSuggestions(suggestions) {
        const $container = $('.alenseo-keyword-suggestions');
        const $list = $('.alenseo-keyword-suggestions-list');
        
        $list.empty();
        
        suggestions.forEach(function(suggestion) {
            $list.append(`
                <div class="alenseo-keyword-suggestion" data-keyword="${suggestion.keyword}">
                    ${suggestion.keyword}
                </div>
            `);
        });
        
        $container.show();
    }
    
    // Keyword-Vorschlag auswählen
    $(document).on('click', '.alenseo-keyword-suggestion', function() {
        const keyword = $(this).data('keyword');
        $('#alenseo_focus_keyword').val(keyword);
        $('.alenseo-keyword-suggestions').hide();
    });
    
    // SEO-Score aktualisieren
    function updateSeoScore(score, status) {
        $('.alenseo-seo-score').text(score);
        $('.alenseo-seo-status')
            .removeClass('alenseo-status-good alenseo-status-ok alenseo-status-poor')
            .addClass('alenseo-status-' + status)
            .text(getStatusText(status));
    }
    
    // Status-Text abrufen
    function getStatusText(status) {
        switch(status) {
            case 'good':
                return alenseoData.i18n.statusGood;
            case 'ok':
                return alenseoData.i18n.statusOk;
            case 'poor':
                return alenseoData.i18n.statusPoor;
            default:
                return alenseoData.i18n.statusUnknown;
        }
    }
    
    // Benachrichtigung anzeigen
    function showNotice(type, message) {
        const $notice = $('<div>')
            .addClass('alenseo-notice alenseo-notice-' + type)
            .text(message);
        
        $('.alenseo-notices').append($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Meta-Beschreibung Zeichen zählen
    function updateMetaDescriptionCount() {
        const count = $('#alenseo_meta_description').val().length;
        $('#alenseo_meta_description_count').text(count);
    }
    
    // Meta-Beschreibung Zeichen zählen bei Änderung
    $('#alenseo_meta_description').on('input', updateMetaDescriptionCount);
    
    // Initial Meta-Beschreibung Zeichen zählen
    updateMetaDescriptionCount();
});
