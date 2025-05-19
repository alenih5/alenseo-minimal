/**
 * Enhanced Keyword Generator JavaScript mit Claude API-Integration
 */

jQuery(document).ready(function($) {
    // Basisvariablen
    var postId = $('#post_ID').val();
    var keywordField = $('#alenseo_focus_keyword');
    var generateBtn = $('#alenseo-generate-keywords');
    var suggestionsContainer = $('.alenseo-kw-suggestions');
    var suggestionsList = $('.alenseo-kw-list');
    var loadingIndicator = $('.alenseo-keyword-generator .alenseo-loading');
    var errorContainer = $('.alenseo-kw-error');
    var generatorContent = $('.alenseo-kw-generator-content');
    
    // Tab-Funktionalität
    $('.alenseo-tab').on('click', function() {
        var tab = $(this).data('tab');
        
        // Aktiven Tab setzen
        $('.alenseo-tab').removeClass('active');
        $(this).addClass('active');
        
        // Aktiven Inhalt setzen
        $('.alenseo-tab-content').removeClass('active');
        $('#alenseo-tab-' + tab).addClass('active');
    });
    
    // Keyword-Generator-Trigger
    generateBtn.on('click', function() {
        // Content anzeigen/verstecken
        if (generatorContent.is(':visible')) {
            generatorContent.slideUp();
        } else {
            generatorContent.slideDown();
            generateKeywords();
        }
    });
    
    // Keywords generieren
    function generateKeywords() {
        // Sichtbarkeit zurücksetzen
        suggestionsContainer.hide();
        errorContainer.hide();
        loadingIndicator.show();
        
        // AJAX-Anfrage für Keywords
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_claude_generate_keywords',
                post_id: postId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                loadingIndicator.hide();
                
                if (response.success && response.data.success) {
                    // Vorschläge anzeigen
                    displayKeywordSuggestions(response.data.keywords);
                } else {
                    // Bei Fehler mit Claude API, lokalen Generator verwenden
                    fallbackToLocalGenerator();
                }
            },
            error: function() {
                loadingIndicator.hide();
                fallbackToLocalGenerator();
            }
        });
    }
    
    // Fallback zum lokalen Keyword-Generator
    function fallbackToLocalGenerator() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_generate_keywords',
                post_id: postId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayKeywordSuggestions(response.data.keywords);
                } else {
                    showError(response.data.message || 'Fehler beim Generieren der Keywords.');
                }
            },
            error: function() {
                showError('Fehler bei der Verbindung zum Server.');
            }
        });
    }
    
    // Keyword-Vorschläge anzeigen
    function displayKeywordSuggestions(keywords) {
        if (!keywords || keywords.length === 0) {
            showError('Keine Keywords gefunden.');
            return;
        }
        
        // Liste leeren
        suggestionsList.empty();
        
        // Keywords hinzufügen
        $.each(keywords, function(index, keyword) {
            var item = $('<li class="alenseo-kw-item"></li>');
            var keywordText = keyword.keyword || keyword; // Unterstütze sowohl Objekte als auch Strings
            var score = keyword.score || '';
            
            var itemHtml = '<span class="alenseo-kw-text">' + keywordText + '</span>';
            if (score) {
                itemHtml += ' <small class="alenseo-kw-score">(' + score + ')</small>';
            }
            itemHtml += '<button type="button" class="button button-small alenseo-kw-select">Auswählen</button>';
            
            item.html(itemHtml);
            
            item.find('.alenseo-kw-select').on('click', function() {
                keywordField.val(keywordText);
                generatorContent.slideUp();
            });
            
            suggestionsList.append(item);
        });
        
        // Container anzeigen
        suggestionsContainer.show();
    }
    
    // Fehlermeldung anzeigen
    function showError(message) {
        errorContainer.html('<p class="alenseo-error">' + message + '</p>');
        errorContainer.show();
    }
    
    // Optimierungsvorschläge-Tab
    if ($('#alenseo-generate-suggestions').length) {
        var suggestionsBtn = $('#alenseo-generate-suggestions');
        var suggestionsLoading = $('.alenseo-suggestions-loading');
        var suggestionsContent = $('.alenseo-suggestions-content');
        var suggestionsError = $('.alenseo-suggestion-error');
        
        // Optimierungsvorschläge generieren
        suggestionsBtn.on('click', function() {
            var keyword = keywordField.val();
            
            if (!keyword) {
                suggestionsError.html('<p class="alenseo-error">Bitte gib ein Fokus-Keyword ein.</p>');
                suggestionsError.show();
                return;
            }
            
            // UI aktualisieren
            suggestionsContent.hide();
            suggestionsError.hide();
            suggestionsLoading.show();
            
            // AJAX-Anfrage für Optimierungsvorschläge
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_claude_get_optimization_suggestions',
                    post_id: postId,
                    keyword: keyword,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    suggestionsLoading.hide();
                    
                    if (response.success && response.data.success) {
                        displayOptimizationSuggestions(response.data.suggestions);
                    } else {
                        suggestionsError.html('<p class="alenseo-error">' + (response.data.message || 'Fehler beim Generieren der Optimierungsvorschläge.') + '</p>');
                        suggestionsError.show();
                    }
                },
                error: function() {
                    suggestionsLoading.hide();
                    suggestionsError.html('<p class="alenseo-error">Fehler bei der Verbindung zum Server.</p>');
                    suggestionsError.show();
                }
            });
        });
        
        // Optimierungsvorschläge anzeigen
        function displayOptimizationSuggestions(suggestions) {
            // Titel-Vorschlag
            if (suggestions.title) {
                $('#alenseo-title-suggestion .alenseo-suggestion-text').html(suggestions.title);
                $('#alenseo-title-suggestion').show();
            } else {
                $('#alenseo-title-suggestion').hide();
            }
            
            // Meta-Beschreibung
            if (suggestions.meta_description) {
                $('#alenseo-meta-suggestion .alenseo-suggestion-text').html(suggestions.meta_description);
                $('#alenseo-meta-suggestion').show();
            } else {
                $('#alenseo-meta-suggestion').hide();
            }
            
            // Inhaltsvorschläge
            var contentList = $('#alenseo-content-suggestions .alenseo-content-suggestion-list');
            contentList.empty();
            
            if (suggestions.content && suggestions.content.length > 0) {
                $.each(suggestions.content, function(index, item) {
                    var listItem = $('<li class="alenseo-content-suggestion-item"></li>');
                    listItem.html(item);
                    contentList.append(listItem);
                });
                $('#alenseo-content-suggestions').show();
            } else {
                $('#alenseo-content-suggestions').hide();
            }
            
            // Vorschläge anzeigen
            suggestionsContent.show();
        }
        
        // Vorschläge anwenden
        $('.alenseo-apply-suggestion').on('click', function() {
            var target = $(this).data('target');
            var content = $(this).siblings('.alenseo-suggestion-text').html();
            
            if (target === 'title') {
                // Titel anwenden (Classic Editor)
                $('#title').val(content);
                
                // Titel anwenden (Gutenberg)
                if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                    wp.data.dispatch('core/editor').editPost({ title: content });
                }
            } else if (target === 'meta') {
                // Meta-Beschreibung anwenden (verschiedene SEO-Plugins)
                
                // Yoast SEO
                if ($('#yoast_wpseo_metadesc').length) {
                    $('#yoast_wpseo_metadesc').val(content);
                }
                
                // All in One SEO
                if ($('#aioseo-description').length) {
                    $('#aioseo-description').val(content);
                }
                
                // RankMath
                if ($('#rank-math-description-textarea').length) {
                    $('#rank-math-description-textarea').val(content);
                }
                
                // SEOPress
                if ($('#seopress_titles_desc_meta').length) {
                    $('#seopress_titles_desc_meta').val(content);
                }
            }
        });
    }
    
    // Analyse-Button
    $('.alenseo-analyze-button').on('click', function() {
        var button = $(this);
        var postId = button.data('post-id');
        
        // Button deaktivieren und Ladeanimation anzeigen
        button.prop('disabled', true);
        var originalText = button.html();
        button.html('<span class="dashicons dashicons-update"></span> Analysiere...');
        
        // AJAX-Anfrage für Analyse
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_analyze_post',
                post_id: postId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                // Button zurücksetzen
                button.prop('disabled', false);
                button.html(originalText);
                
                if (response.success) {
                    // Erfolgsmeldung
                    alert(response.data.message || 'Analyse erfolgreich durchgeführt.');
                    
                    // Seite neu laden, um Ergebnisse anzuzeigen
                    location.reload();
                } else {
                    // Fehlermeldung
                    alert(response.data.message || 'Fehler bei der Analyse.');
                }
            },
            error: function() {
                // Button zurücksetzen
                button.prop('disabled', false);
                button.html(originalText);
                
                // Fehlermeldung
                alert('Fehler bei der Kommunikation mit dem Server.');
            }
        });
    });
});
