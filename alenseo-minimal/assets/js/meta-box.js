/**
 * Alenseo SEO Meta-Box JavaScript
 */

jQuery(document).ready(function($) {
    console.log('Alenseo SEO meta-box.js geladen');
    
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
            url: alenseoData.ajaxUrl,
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
    
    // Keyword-Vorschläge-Button
    $('.alenseo-generate-button').on('click', function() {
        var button = $(this);
        var postId = button.data('post-id');
        var suggestionsContainer = $('.alenseo-keyword-suggestions');
        var suggestionsList = $('.alenseo-keyword-suggestions-list');
        
        // Container anzeigen und Button deaktivieren
        suggestionsContainer.show();
        button.prop('disabled', true);
        var originalText = button.html();
        button.html('<span class="dashicons dashicons-update"></span> Generiere...');
        
        // Lade-Animation anzeigen
        suggestionsList.html('<div class="alenseo-keyword-suggestions-loading"><span class="dashicons dashicons-update"></span> Generiere Vorschläge...</div>');
        
        // AJAX-Anfrage für Keyword-Vorschläge
        $.ajax({
            url: alenseoData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_generate_keywords',
                post_id: postId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                // Button zurücksetzen
                button.prop('disabled', false);
                button.html(originalText);
                
                if (response.success && response.data.keywords && response.data.keywords.length > 0) {
                    // Vorschläge anzeigen
                    renderKeywordSuggestions(suggestionsList, response.data.keywords);
                } else {
                    // Meldung, wenn keine Vorschläge gefunden wurden
                    suggestionsList.html('<div class="notice notice-info"><p>Keine Keyword-Vorschläge gefunden.</p></div>');
                }
            },
            error: function() {
                // Button zurücksetzen
                button.prop('disabled', false);
                button.html(originalText);
                
                // Fehlermeldung
                suggestionsList.html('<div class="notice notice-error"><p>Fehler bei der Kommunikation mit dem Server.</p></div>');
            }
        });
    });
    
    /**
     * Keyword-Vorschläge rendern
     */
    function renderKeywordSuggestions(container, keywords) {
        var html = '';
        
        keywords.forEach(function(keyword) {
            html += '<div class="alenseo-keyword-suggestion">';
            html += '<div class="alenseo-keyword-suggestion-text">' + keyword.keyword + '</div>';
            html += '<div class="alenseo-keyword-suggestion-actions">';
            if (keyword.score) {
                html += '<span class="alenseo-keyword-suggestion-score">Score: ' + keyword.score + '</span> ';
            }
            html += '<button type="button" class="button button-small alenseo-keyword-suggestion-button" data-keyword="' + keyword.keyword + '">';
            html += '<span class="dashicons dashicons-yes"></span> Auswählen';
            html += '</button>';
            html += '</div>';
            html += '</div>';
        });
        
        // HTML einfügen
        container.html(html);
        
        // Event-Listener für Auswahl-Buttons
        $('.alenseo-keyword-suggestion-button').on('click', function() {
            var keyword = $(this).data('keyword');
            $('#alenseo_focus_keyword').val(keyword);
            
            // Keyword-Vorschläge ausblenden
            $('.alenseo-keyword-suggestions').hide();
        });
    }
    
    // Speichern des Keywords bei Enter-Taste
    $('#alenseo_focus_keyword').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            // Fokus entfernen, um sicherzustellen, dass das Feld aktualisiert wird
            $(this).blur();
        }
    });
});
