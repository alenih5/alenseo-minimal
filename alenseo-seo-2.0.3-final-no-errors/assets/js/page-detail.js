/**
 * JavaScript für die Detailansicht einer einzelnen Seite
 *
 * @link       https://www.imponi.ch
 * @since      1.0.0
 *
 * @package    Alenseo
 */

jQuery(document).ready(function($) {
    'use strict';
    
    /**
     * Tab-Navigation
     */
    function initTabNavigation() {
        $('.alenseo-page-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href').substring(1);
            
            // Aktiven Tab und Inhalt ändern
            $('.alenseo-page-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.alenseo-tab-content').removeClass('active');
            $('#' + target).addClass('active');
            
            // Hash in URL setzen
            if (history.pushState) {
                history.pushState(null, null, '#' + target);
            } else {
                location.hash = '#' + target;
            }
        });
        
        // Beim Laden den richtigen Tab basierend auf Hash öffnen
        var hash = window.location.hash;
        if (hash) {
            var tabLink = $('.alenseo-page-tabs .nav-tab[href="' + hash + '"]');
            if (tabLink.length) {
                tabLink.trigger('click');
            }
        }
    }
    
    /**
     * Keyword-Management
     */
    function initKeywordManagement() {
        // Keyword-Dialog öffnen
        $('.alenseo-change-keyword, .alenseo-set-keyword').on('click', function() {
            $('#alenseo-keyword-dialog').fadeIn(200);
            
            // Aktuelles Keyword in das Eingabefeld einfügen
            var currentKeyword = $('.alenseo-keyword-badge').text();
            if (currentKeyword) {
                $('#alenseo-keyword-input').val(currentKeyword);
            }
        });
        
        // Dialog schließen
        $('.alenseo-dialog-close').on('click', function() {
            $(this).closest('.alenseo-dialog').fadeOut(200);
        });
        
        // Keywords im Dialog generieren
        $('#alenseo-dialog-generate-keywords').on('click', function() {
            var button = $(this);
            var postId = button.data('post-id');
            var loader = button.closest('.alenseo-dialog-section').find('.alenseo-dialog-loader');
            var suggestions = button.closest('.alenseo-dialog-section').find('.alenseo-keyword-suggestions');
            var keywordList = suggestions.find('.alenseo-keyword-list');
            
            // Button deaktivieren und Loader anzeigen
            button.prop('disabled', true);
            loader.show();
            suggestions.hide();
            
            // AJAX-Anfrage für Keywords
            $.ajax({
                url: alenseoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alenseo_claude_generate_keywords',
                    post_id: postId,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    // Button reaktivieren und Loader ausblenden
                    button.prop('disabled', false);
                    loader.hide();
                    
                    if (response.success && response.data.keywords) {
                        // Keyword-Liste leeren
                        keywordList.empty();
                        
                        // Keywords einfügen
                        $.each(response.data.keywords, function(index, keyword) {
                            var keywordText = keyword.keyword || keyword;
                            var score = keyword.score ? ' <small>(' + keyword.score + ')</small>' : '';
                            
                            var keywordItem = $('<div class="alenseo-keyword-item"></div>');
                            keywordItem.html('<span class="alenseo-keyword-text">' + keywordText + score + '</span>');
                            
                            // Keyword-Auswahl-Handler
                            keywordItem.on('click', function() {
                                $('.alenseo-keyword-item').removeClass('selected');
                                $(this).addClass('selected');
                                
                                // Gewähltes Keyword in das Eingabefeld übernehmen
                                $('#alenseo-keyword-input').val(keywordText);
                            });
                            
                            keywordList.append(keywordItem);
                        });
                        
                        // Vorschläge anzeigen
                        suggestions.show();
                    } else {
                        // Fehlermeldung
                        alert(response.data && response.data.message ? response.data.message : 'Fehler beim Generieren der Keywords.');
                    }
                },
                error: function() {
                    // Button reaktivieren und Loader ausblenden
                    button.prop('disabled', false);
                    loader.hide();
                    
                    // Fehlermeldung
                    alert('Fehler bei der Kommunikation mit dem Server.');
                }
            });
        });
        
        // Keyword speichern
        $('#alenseo-save-keyword').on('click', function() {
            var button = $(this);
            var postId = button.data('post-id');
            var keyword = $('#alenseo-keyword-input').val().trim();
            
            if (!keyword) {
                alert('Bitte gib ein Keyword ein oder wähle einen Vorschlag aus.');
                return;
            }
            
            // Button deaktivieren
            button.prop('disabled', true);
            var originalText = button.text();
            button.text('Speichere...');
            
            // AJAX-Anfrage zum Speichern des Keywords
            $.ajax({
                url: alenseoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alenseo_save_keyword',
                    post_id: postId,
                    keyword: keyword,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    // Button reaktivieren
                    button.prop('disabled', false);
                    button.text(originalText);
                    
                    if (response.success) {
                        // Dialog schließen
                        $('#alenseo-keyword-dialog').fadeOut(200);
                        
                        // Seite neuladen, um die Änderungen zu sehen
                        location.reload();
                    } else {
                        // Fehlermeldung
                        alert(response.data && response.data.message ? response.data.message : 'Fehler beim Speichern des Keywords.');
                    }
                },
                error: function() {
                    // Button reaktivieren
                    button.prop('disabled', false);
                    button.text(originalText);
                    
                    // Fehlermeldung
                    alert('Fehler bei der Kommunikation mit dem Server.');
                }
            });
        });
    }
    
    /**
     * Optimierungstools und Vorschläge
     */
    function initOptimizationTools() {
        // Einzelne Optimierungs-Button-Handler
        $('.alenseo-optimize-button').on('click', function() {
            var button = $(this);
            var type = button.data('type');
            var postId = button.data('post-id');
            var resultContainer = $('#' + type + '-optimization-result');
            
            // Button deaktivieren und Text ändern
            button.prop('disabled', true);
            var originalText = button.html();
            button.html('<span class="dashicons dashicons-update"></span> ' + alenseoData.messages.optimizing);
            
            // Fokus-Keyword abrufen
            var keyword = $('.alenseo-keyword-badge').text();
            
            if (!keyword) {
                // Wenn kein Keyword, Dialog öffnen
                button.prop('disabled', false);
                button.html(originalText);
                alert('Bitte setze zuerst ein Fokus-Keyword.');
                $('.alenseo-set-keyword').trigger('click');
                return;
            }
            
            // AJAX-Anfrage für Optimierungsvorschläge
            $.ajax({
                url: alenseoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alenseo_claude_get_optimization_suggestions',
                    post_id: postId,
                    keyword: keyword,
                    optimize_type: type,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    // Button reaktivieren und Text zurücksetzen
                    button.prop('disabled', false);
                    button.html(originalText);
                    
                    if (response.success && response.data.suggestions) {
                        var suggestions = response.data.suggestions;
                        
                        // Je nach Typ den Inhalt aktualisieren
                        if (type === 'title' && suggestions.title) {
                            resultContainer.find('.alenseo-suggestion-content').text(suggestions.title);
                            resultContainer.show();
                        } else if (type === 'meta_description' && suggestions.meta_description) {
                            resultContainer.find('.alenseo-suggestion-content').text(suggestions.meta_description);
                            resultContainer.show();
                        } else if (type === 'content' && suggestions.content) {
                            // Content-Vorschläge als Liste darstellen
                            var contentHtml = '<ul>';
                            $.each(suggestions.content, function(index, suggestion) {
                                contentHtml += '<li>' + suggestion + '</li>';
                            });
                            contentHtml += '</ul>';
                            
                            resultContainer.find('.alenseo-suggestion-content').html(contentHtml);
                            resultContainer.show();
                        } else {
                            alert('Keine Optimierungsvorschläge für dieses Element gefunden.');
                        }
                    } else {
                        // Fehlermeldung
                        alert(response.data && response.data.message ? response.data.message : 'Fehler beim Generieren der Optimierungsvorschläge.');
                    }
                },
                error: function() {
                    // Button reaktivieren und Text zurücksetzen
                    button.prop('disabled', false);
                    button.html(originalText);
                    
                    // Fehlermeldung
                    alert('Fehler bei der Kommunikation mit dem Server.');
                }
            });
        });
        
        // Vorschlag anwenden
        $('.alenseo-apply-suggestion').on('click', function() {
            var button = $(this);
            var type = button.data('type');
            var postId = $('.alenseo-page-actions button:first').data('post-id');
            var content = button.closest('.alenseo-optimization-result, .alenseo-suggestion-section').find('.alenseo-suggestion-content').text();
            
            // Button deaktivieren
            button.prop('disabled', true);
            var originalText = button.text();
            button.text('Wird angewendet...');
            
            // AJAX-Anfrage zum Anwenden des Vorschlags
            $.ajax({
                url: alenseoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alenseo_apply_suggestion',
                    post_id: postId,
                    type: type,
                    content: content,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Erfolgstext
                        button.text('Angewendet');
                        
                        // Nach 1 Sekunde Seite neuladen
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        // Button reaktivieren und Fehlertext
                        button.prop('disabled', false);
                        button.text(originalText);
                        
                        // Fehlermeldung
                        alert(response.data && response.data.message ? response.data.message : 'Fehler beim Anwenden des Vorschlags.');
                    }
                },
                error: function() {
                    // Button reaktivieren und Text zurücksetzen
                    button.prop('disabled', false);
                    button.text(originalText);
                    
                    // Fehlermeldung
                    alert('Fehler bei der Kommunikation mit dem Server.');
                }
            });
        });
        
        // Vorschlag verwerfen
        $('.alenseo-cancel-suggestion').on('click', function() {
            $(this).closest('.alenseo-optimization-result').hide();
        });
        
        // Alle optimieren (Bulk-Optimize-Button)
        $('.alenseo-optimize-all-button, .alenseo-bulk-optimize-button').on('click', function() {
            var button = $(this);
            var postId = button.data('post-id');
            
            // Fokus-Keyword abrufen
            var keyword = $('.alenseo-keyword-badge').text();
            
            if (!keyword) {
                // Wenn kein Keyword, Dialog öffnen
                alert('Bitte setze zuerst ein Fokus-Keyword.');
                $('.alenseo-set-keyword').trigger('click');
                return;
            }
            
            // Zu optimierende Elemente prüfen
            var optimizeTitle = $('input[name="optimize_title"]').prop('checked');
            var optimizeMeta = $('input[name="optimize_meta_description"]').prop('checked');
            var optimizeContent = $('input[name="optimize_content"]').prop('checked');
            
            if (!optimizeTitle && !optimizeMeta && !optimizeContent) {
                alert('Bitte wähle mindestens ein Element zur Optimierung aus.');
                return;
            }
            
            // Tonfall und Optimierungsgrad
            var tone = $('#alenseo-optimization-tone').val() || 'professional';
            var level = $('#alenseo-optimization-level').val() || 'moderate';
            
            // Button deaktivieren und Status anzeigen
            button.prop('disabled', true);
            var originalText = button.html();
            button.html('<span class="dashicons dashicons-update"></span> Optimiere...');
            
            // Status-Container anzeigen
            $('.alenseo-optimization-status').show();
            
            // AJAX-Anfrage für Optimierungsvorschläge
            $.ajax({
                url: alenseoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alenseo_claude_get_optimization_suggestions',
                    post_id: postId,
                    keyword: keyword,
                    optimize_title: optimizeTitle,
                    optimize_meta_description: optimizeMeta,
                    optimize_content: optimizeContent,
                    tone: tone,
                    level: level,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    // Button reaktivieren und Text zurücksetzen
                    button.prop('disabled', false);
                    button.html(originalText);
                    
                    // Status-Container ausblenden
                    $('.alenseo-optimization-status').hide();
                    
                    if (response.success && response.data.suggestions) {
                        var suggestions = response.data.suggestions;
                        
                        // Suggestions-Container zeigen
                        $('.alenseo-bulk-suggestions').show();
                        
                        // Titel-Vorschlag
                        if (optimizeTitle && suggestions.title) {
                            $('#title-suggestion-section').find('.alenseo-suggestion-content').text(suggestions.title);
                            $('#title-suggestion-section').show();
                        } else {
                            $('#title-suggestion-section').hide();
                        }
                        
                        // Meta-Description-Vorschlag
                        if (optimizeMeta && suggestions.meta_description) {
                            $('#meta-description-suggestion-section').find('.alenseo-suggestion-content').text(suggestions.meta_description);
                            $('#meta-description-suggestion-section').show();
                        } else {
                            $('#meta-description-suggestion-section').hide();
                        }
                        
                        // Content-Vorschläge
                        if (optimizeContent && suggestions.content && suggestions.content.length > 0) {
                            // Content-Vorschläge als Liste darstellen
                            var contentHtml = '<ul>';
                            $.each(suggestions.content, function(index, suggestion) {
                                contentHtml += '<li>' + suggestion + '</li>';
                            });
                            contentHtml += '</ul>';
                            
                            $('#content-suggestion-section').find('.alenseo-suggestion-content').html(contentHtml);
                            $('#content-suggestion-section').show();
                        } else {
                            $('#content-suggestion-section').hide();
                        }
                        
                        // Zum Vorschlagsbereich scrollen
                        $('html, body').animate({
                            scrollTop: $('.alenseo-bulk-suggestions').offset().top - 50
                        }, 500);
                    } else {
                        // Fehlermeldung
                        alert(response.data && response.data.message ? response.data.message : 'Fehler beim Generieren der Optimierungsvorschläge.');
                    }
                },
                error: function() {
                    // Button reaktivieren und Text zurücksetzen
                    button.prop('disabled', false);
                    button.html(originalText);
                    
                    // Status-Container ausblenden
                    $('.alenseo-optimization-status').hide();
                    
                    // Fehlermeldung
                    alert('Fehler bei der Kommunikation mit dem Server.');
                }
            });
        });
    }
    
    /**
     * Optimierungsvorschläge abrufen
     */
    function getOptimizationSuggestions(postId, keyword, options) {
        var action = options.enhanced ? 'alenseo_claude_get_enhanced_optimization_suggestions' : 'alenseo_claude_get_basic_optimization_suggestions';
        
        return $.ajax({
            url: alenseoData.ajaxUrl,
            type: 'POST',
            data: {
                action: action,
                nonce: alenseoData.nonce,
                post_id: postId,
                keyword: keyword,
                optimize_type: options.type || '',
                optimize_title: options.title || false,
                optimize_meta_description: options.metaDescription || false,
                optimize_content: options.content || false,
                tone: options.tone || 'professional',
                level: options.level || 'moderate'
            }
        });
    }
    
    /**
     * Analyse-Button-Handler
     */
    function initAnalyzeButtonHandlers() {
        // Analyse-Button-Klick
        $('.alenseo-analyze-button').on('click', function() {
            var button = $(this);
            var postId = button.data('post-id');
            
            // Button deaktivieren und Text ändern
            button.prop('disabled', true);
            var originalText = button.html();
            button.html('<span class="dashicons dashicons-update"></span> Analysiere...');
            
            // AJAX-Anfrage für Analyse
            $.ajax({
                url: alenseoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alenseo_analyze_post',
                    post_id: postId,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    // Button reaktivieren und Text zurücksetzen
                    button.prop('disabled', false);
                    button.html(originalText);
                    
                    if (response.success) {
                        // Erfolgsmeldung und Seite neu laden
                        alert(response.data.message || 'Analyse erfolgreich durchgeführt.');
                        location.reload();
                    } else {
                        // Fehlermeldung
                        alert(response.data.message || 'Fehler bei der Analyse.');
                    }
                },
                error: function() {
                    // Button reaktivieren und Text zurücksetzen
                    button.prop('disabled', false);
                    button.html(originalText);
                    
                    // Fehlermeldung
                    alert('Fehler bei der Kommunikation mit dem Server.');
                }
            });
        });
    }
    
    /**
     * Erweiterte Optionen ein-/ausblenden
     */
    function initAdvancedOptionsToggle() {
        $('.alenseo-toggle-advanced').on('click', function(e) {
            e.preventDefault();
            
            var content = $('.alenseo-advanced-options-content');
            var icon = $(this).find('.dashicons');
            
            if (content.is(':visible')) {
                content.slideUp();
                icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
            } else {
                content.slideDown();
                icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
            }
        });
    }
    
    /**
     * Initialisierung aller Funktionen
     */
    function init() {
        // Tab-Navigation initialisieren
        initTabNavigation();
        
        // Keyword-Management initialisieren
        initKeywordManagement();
        
        // Optimierungstools initialisieren
        initOptimizationTools();
        
        // Analyse-Button-Handler initialisieren
        initAnalyzeButtonHandlers();
        
        // Erweiterte Optionen Toggle initialisieren
        initAdvancedOptionsToggle();
        
        // ESC-Taste zum Schließen von Dialogen
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.alenseo-dialog').fadeOut(200);
            }
        });
        
        // Klick außerhalb des Dialogs zum Schließen
        $(document).on('click', '.alenseo-dialog', function(e) {
            if ($(e.target).hasClass('alenseo-dialog')) {
                $(this).fadeOut(200);
            }
        });
    }
    
    // Funktionen initialisieren
    init();
});
