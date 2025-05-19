/**
 * JavaScript für das Dashboard
 *
 * @link       https://www.imponi.ch
 * @since      1.0.0
 *
 * @package    Alenseo
 */

jQuery(document).ready(function($) {
    'use strict';
    
    /**
     * Tabellen-Aktionen einrichten
     */
    function setupTableActions() {
        // Checkbox-Funktion für "Alle auswählen"
        $('#alenseo-select-all').on('change', function() {
            $('.alenseo-select-post').prop('checked', $(this).prop('checked'));
            updateBulkActionButton();
        });
        
        // Checkbox-Änderung
        $('.alenseo-select-post').on('change', function() {
            updateBulkActionButton();
        });
        
        // Filtern
        $('#alenseo-filter-status, #alenseo-filter-type').on('change', function() {
            $('#alenseo-filter-form').submit();
        });
        
        // Suche
        $('#alenseo-search-button').on('click', function(e) {
            e.preventDefault();
            $('#alenseo-filter-form').submit();
        });
        
        // Analyse-Button-Handler
        $('.alenseo-analyze-button').on('click', function() {
            var button = $(this);
            var row = button.closest('tr');
            var postId = button.data('post-id');
            
            // Button deaktivieren und Text ändern
            button.prop('disabled', true);
            var originalButtonHtml = button.html();
            button.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> ' + alenseoData.messages.analyzing);
            
            // AJAX-Anfrage zur Analyse
            $.ajax({
                url: alenseoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alenseo_analyze_post',
                    post_id: postId,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    // Button reaktivieren
                    button.prop('disabled', false);
                    button.html(originalButtonHtml);
                    
                    if (response.success) {
                        // Erfolgreich, Seite neuladen oder nur Zeile aktualisieren
                        location.reload();
                    } else {
                        // Fehler anzeigen
                        alert(response.data && response.data.message ? response.data.message : alenseoData.messages.error);
                    }
                },
                error: function() {
                    // Button reaktivieren
                    button.prop('disabled', false);
                    button.html(originalButtonHtml);
                    
                    // Fehler anzeigen
                    alert(alenseoData.messages.error);
                }
            });
        });
        
        // Keyword-Button-Handler
        $('.alenseo-keyword-button').on('click', function() {
            var postId = $(this).data('post-id');
            var postTitle = $(this).closest('tr').find('.alenseo-post-title').text();
            var currentKeyword = $(this).closest('tr').find('.alenseo-post-keyword-value').text();
            
            if (currentKeyword === "-") {
                currentKeyword = "";
            }
            
            // Dialog öffnen
            openKeywordDialog(postId, postTitle, currentKeyword);
        });
        
        // Detailansicht-Link
        $('.alenseo-post-title a').on('click', function(e) {
            e.preventDefault();
            var postId = $(this).data('post-id');
            window.location.href = alenseoData.detailPageUrl + '&post_id=' + postId;
        });
        
        // Vorschau-Button-Handler
        $('.alenseo-view-button').on('click', function() {
            var postId = $(this).data('post-id');
            var url = $(this).data('permalink');
            
            // Neues Fenster/Tab öffnen
            window.open(url, '_blank');
        });
    }
    
    /**
     * Massenaktionen einrichten
     */
    function setupBulkActions() {
        // Massenaktionen-Button
        $('#alenseo-bulk-action-apply').on('click', function() {
            var action = $('#alenseo-bulk-action').val();
            var selectedPosts = [];
            
            // Ausgewählte Posts sammeln
            $('.alenseo-select-post:checked').each(function() {
                selectedPosts.push($(this).val());
            });
            
            if (selectedPosts.length === 0) {
                alert('Bitte wähle mindestens eine Seite aus.');
                return;
            }
            
            switch (action) {
                case 'analyze':
                    bulkAnalyze(selectedPosts);
                    break;
                case 'set_keyword':
                    bulkSetKeyword(selectedPosts);
                    break;
                case 'optimize':
                    alert('Die Massenoptimierung ist in dieser Version noch nicht verfügbar.');
                    break;
                default:
                    alert('Bitte wähle eine Aktion aus.');
            }
        });
    }
    
    /**
     * Update des Massenaktionen-Buttons
     */
    function updateBulkActionButton() {
        var selectedCount = $('.alenseo-select-post:checked').length;
        var button = $('#alenseo-bulk-action-apply');
        
        if (selectedCount > 0) {
            button.text('Anwenden (' + selectedCount + ' ausgewählt)');
            button.prop('disabled', false);
        } else {
            button.text('Anwenden');
            button.prop('disabled', true);
        }
    }
    
    /**
     * Massenanalyse durchführen
     *
     * @param {Array} postIds Array mit Post-IDs
     */
    function bulkAnalyze(postIds) {
        var button = $('#alenseo-bulk-action-apply');
        var originalButtonText = button.text();
        
        // Button deaktivieren und Text ändern
        button.prop('disabled', true);
        button.text('Analysiere...');
        
        // Status-Container erstellen
        var statusContainer = $('<div class="alenseo-bulk-status"></div>');
        var progressBar = $('<div class="alenseo-progress-bar"><div class="alenseo-progress"></div></div>');
        var statusText = $('<div class="alenseo-status-text">Starte Analyse...</div>');
        
        statusContainer.append(progressBar).append(statusText);
        $('.alenseo-table-container').before(statusContainer);
        
        // Analyse sequentiell durchführen
        var totalPosts = postIds.length;
        var processedPosts = 0;
        var successPosts = 0;
        
        function analyzeNext() {
            if (processedPosts >= totalPosts) {
                // Alle Posts verarbeitet
                statusText.text('Analyse abgeschlossen. ' + successPosts + ' von ' + totalPosts + ' Seiten erfolgreich analysiert.');
                
                // Button reaktivieren
                button.prop('disabled', false);
                button.text(originalButtonText);
                
                // Nach kurzer Verzögerung Seite neuladen
                setTimeout(function() {
                    location.reload();
                }, 2000);
                
                return;
            }
            
            var postId = postIds[processedPosts];
            
            // Progress updaten
            var progress = Math.round((processedPosts / totalPosts) * 100);
            progressBar.find('.alenseo-progress').css('width', progress + '%');
            statusText.text('Analysiere Seite ' + (processedPosts + 1) + ' von ' + totalPosts + '...');
            
            // AJAX-Anfrage zur Analyse
            $.ajax({
                url: alenseoData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'alenseo_analyze_post',
                    post_id: postId,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    processedPosts++;
                    
                    if (response.success) {
                        successPosts++;
                    }
                    
                    // Nächsten Post analysieren
                    analyzeNext();
                },
                error: function() {
                    processedPosts++;
                    
                    // Trotz Fehler weitermachen
                    analyzeNext();
                }
            });
        }
        
        // Analyse starten
        analyzeNext();
    }
    
    /**
     * Keyword-Dialog öffnen
     *
     * @param {number} postId    Die Post-ID
     * @param {string} postTitle Der Titel des Posts
     * @param {string} currentKeyword Das aktuelle Keyword
     */
    function openKeywordDialog(postId, postTitle, currentKeyword) {
        // Dialog erstellen, falls noch nicht vorhanden
        if ($('#alenseo-keyword-dialog').length === 0) {
            var dialogHtml = 
                '<div id="alenseo-keyword-dialog" class="alenseo-dialog">' +
                '  <div class="alenseo-dialog-content">' +
                '    <div class="alenseo-dialog-header">' +
                '      <h2>Fokus-Keyword setzen</h2>' +
                '      <button type="button" class="alenseo-dialog-close">&times;</button>' +
                '    </div>' +
                '    <div class="alenseo-dialog-body">' +
                '      <div class="alenseo-dialog-post-title"></div>' +
                '      <div class="alenseo-keyword-input-group">' +
                '        <label for="alenseo-keyword-input">Keyword:</label>' +
                '        <input type="text" id="alenseo-keyword-input" placeholder="z.B. WordPress SEO Plugin">' +
                '      </div>' +
                '      <div class="alenseo-keyword-generate">' +
                '        <button type="button" id="alenseo-generate-keywords" class="button">Keywords generieren</button>' +
                '        <div class="alenseo-keyword-suggestions" style="display: none;">' +
                '          <h4>Vorschläge:</h4>' +
                '          <div class="alenseo-keyword-list"></div>' +
                '        </div>' +
                '      </div>' +
                '    </div>' +
                '    <div class="alenseo-dialog-footer">' +
                '      <button type="button" class="button alenseo-dialog-close">Abbrechen</button>' +
                '      <button type="button" class="button button-primary" id="alenseo-save-keyword">Keyword speichern</button>' +
                '    </div>' +
                '  </div>' +
                '</div>';
            
            $('body').append(dialogHtml);
            
            // Dialog-Handler
            $('.alenseo-dialog-close').on('click', function() {
                $('#alenseo-keyword-dialog').hide();
            });
            
            // Keyword-Vorschläge generieren
            $('#alenseo-generate-keywords').on('click', function() {
                var button = $(this);
                var currentPostId = $('#alenseo-keyword-dialog').data('post-id');
                
                // Button deaktivieren
                button.prop('disabled', true);
                button.text('Generiere Keywords...');
                
                // Vorschläge ausblenden
                $('.alenseo-keyword-suggestions').hide();
                
                // AJAX-Anfrage für Keyword-Vorschläge
                $.ajax({
                    url: alenseoData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'alenseo_claude_generate_keywords',
                        post_id: currentPostId,
                        nonce: alenseoData.nonce
                    },
                    success: function(response) {
                        // Button reaktivieren
                        button.prop('disabled', false);
                        button.text('Keywords generieren');
                        
                        if (response.success && response.data.keywords) {
                            // Vorschläge anzeigen
                            var keywordList = $('.alenseo-keyword-list');
                            keywordList.empty();
                            
                            $.each(response.data.keywords, function(index, keyword) {
                                var keywordEl = $('<div class="alenseo-keyword-item">' + keyword + '</div>');
                                
                                keywordEl.on('click', function() {
                                    // Keyword auswählen
                                    $('#alenseo-keyword-input').val(keyword);
                                    $('.alenseo-keyword-item').removeClass('selected');
                                    $(this).addClass('selected');
                                });
                                
                                keywordList.append(keywordEl);
                            });
                            
                            $('.alenseo-keyword-suggestions').show();
                        } else {
                            alert('Fehler beim Generieren von Keywords: ' + (response.data.message || 'Unbekannter Fehler'));
                        }
                    },
                    error: function() {
                        // Button reaktivieren
                        button.prop('disabled', false);
                        button.text('Keywords generieren');
                        
                        alert('Fehler bei der Kommunikation mit dem Server.');
                    }
                });
            });
            
            // Keyword speichern
            $('#alenseo-save-keyword').on('click', function() {
                var button = $(this);
                var currentPostId = $('#alenseo-keyword-dialog').data('post-id');
                var keyword = $('#alenseo-keyword-input').val().trim();
                
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
                        post_id: currentPostId,
                        keyword: keyword,
                        nonce: alenseoData.nonce
                    },
                    success: function(response) {
                        // Button reaktivieren
                        button.prop('disabled', false);
                        button.text(originalText);
                        
                        if (response.success) {
                            // Dialog schließen
                            $('#alenseo-keyword-dialog').hide();
                            
                            // Seite neuladen
                            location.reload();
                        } else {
                            alert('Fehler beim Speichern des Keywords: ' + (response.data.message || 'Unbekannter Fehler'));
                        }
                    },
                    error: function() {
                        // Button reaktivieren
                        button.prop('disabled', false);
                        button.text(originalText);
                        
                        alert('Fehler bei der Kommunikation mit dem Server.');
                    }
                });
            });
            
            // ESC-Taste zum Schließen
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('#alenseo-keyword-dialog').hide();
                }
            });
            
            // Klick außerhalb des Dialogs zum Schließen
            $(document).on('click', '.alenseo-dialog', function(e) {
                if ($(e.target).hasClass('alenseo-dialog')) {
                    $(this).hide();
                }
            });
        }
        
        // Dialog mit Daten füllen und anzeigen
        var dialog = $('#alenseo-keyword-dialog');
        dialog.data('post-id', postId);
        dialog.find('.alenseo-dialog-post-title').html('<strong>Seite:</strong> ' + postTitle);
        dialog.find('#alenseo-keyword-input').val(currentKeyword);
        dialog.find('.alenseo-keyword-suggestions').hide();
        dialog.find('.alenseo-keyword-list').empty();
        dialog.show();
    }
    
    /**
     * Keywords für mehrere Posts setzen (nicht implementiert)
     *
     * @param {Array} postIds Array mit Post-IDs
     */
    function bulkSetKeyword(postIds) {
        // In dieser Version nicht implementiert
        alert('Die Massen-Keyword-Setzung ist in dieser Version noch nicht verfügbar.');
    }
    
    /**
     * Initialisierung aller Funktionen
     */
    function init() {
        setupTableActions();
        setupBulkActions();
    }
    
    // Initialisierung starten
    init();
});
