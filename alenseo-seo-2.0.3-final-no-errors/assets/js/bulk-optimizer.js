/**
 * JavaScript für die Massenoptimierung im Dashboard
 */

jQuery(document).ready(function($) {
    // Konstanten und Variablen
    const contentRows = $('.alenseo-content-row');
    const selectAllCheckbox = $('#alenseo-select-all');
    const contentCheckboxes = $('.alenseo-select-content');
    const bulkActionSelect = $('#alenseo-bulk-action');
    const applyBulkActionButton = $('#alenseo-apply-bulk-action');
    const selectionInfo = $('.alenseo-selection-info');
    const selectedCountEl = $('#alenseo-selected-count');
    const clearSelectionButton = $('#alenseo-clear-selection');
    const progressBarContainer = $('#alenseo-progress-bar');
    const progressBarFill = $('.alenseo-progress-fill');
    const progressCurrent = $('#alenseo-progress-current');
    const progressTotal = $('#alenseo-progress-total');
    
    // Filter-Elemente
    const filterPostType = $('#alenseo-filter-post-type');
    const filterStatus = $('#alenseo-filter-status');
    const filterKeyword = $('#alenseo-filter-keyword');
    const searchInput = $('#alenseo-search-input');
    const searchButton = $('#alenseo-search-button');
    
    // Dialog-Elemente
    const optimizationDialog = $('#alenseo-optimization-dialog');
    const dialogCloseButtons = $('.alenseo-dialog-close');
    const generateKeywordsButton = $('#alenseo-generate-keywords');
    const keywordSuggestions = $('.alenseo-keyword-suggestions');
    const keywordList = $('.alenseo-keyword-list');
    const keywordsLoader = $('.alenseo-keywords-loader');
    const currentKeywordValue = $('#alenseo-current-keyword-value');
    const startOptimizationButton = $('#alenseo-start-optimization');
    const optimizerLoader = $('.alenseo-optimizer-loader');
    const resultsSection = $('.alenseo-results-section');
    const resultsContainer = $('.alenseo-results-container');
    
    // Aktions-Buttons
    const optimizeButtons = $('.alenseo-action-optimize');
    const analyzeButtons = $('.alenseo-action-analyze');
    
    // State-Verwaltung
    let selectedContentIds = [];
    let currentContentId = null;
    let currentKeyword = '';
    
    // AJAX-Nonce für Sicherheit
    const ajaxNonce = alenseoBulkOptimizerParams.nonce || '';
    
    // Select All Checkbox
    selectAllCheckbox.on('change', function() {
        const isChecked = $(this).prop('checked');
        contentCheckboxes.prop('checked', isChecked);
        updateSelectedContentIds();
        updateSelectionUI();
    });
    
    // Einzelne Checkboxen
    contentCheckboxes.on('change', function() {
        updateSelectedContentIds();
        updateSelectionUI();
        
        // Update "Select All" Status
        const allChecked = contentCheckboxes.length === contentCheckboxes.filter(':checked').length;
        selectAllCheckbox.prop('checked', allChecked);
    });
    
    // Auswahl zurücksetzen
    clearSelectionButton.on('click', function() {
        contentCheckboxes.prop('checked', false);
        selectAllCheckbox.prop('checked', false);
        updateSelectedContentIds();
        updateSelectionUI();
    });
    
    // Filterung anwenden
    function applyFilters() {
        const postType = filterPostType.val();
        const status = filterStatus.val();
        const keyword = filterKeyword.val();
        const searchQuery = searchInput.val().toLowerCase();
        
        contentRows.each(function() {
            const $row = $(this);
            let visible = true;
            
            // Post-Typ-Filter
            if (postType && $row.data('type').toLowerCase() !== postType.toLowerCase()) {
                visible = false;
            }
            
            // Status-Filter
            if (status && $row.data('status') !== status) {
                visible = false;
            }
            
            // Keyword-Filter
            if (keyword && $row.data('has-keyword') !== keyword) {
                visible = false;
            }
            
            // Suche
            if (searchQuery) {
                const title = $row.find('.column-title a').text().toLowerCase();
                if (title.indexOf(searchQuery) === -1) {
                    visible = false;
                }
            }
            
            // Zeile anzeigen oder verstecken
            $row.toggle(visible);
        });
    }
    
    // Filter-Events
    filterPostType.on('change', applyFilters);
    filterStatus.on('change', applyFilters);
    filterKeyword.on('change', applyFilters);
    searchButton.on('click', applyFilters);
    searchInput.on('keypress', function(e) {
        if (e.which === 13) { // Enter-Taste
            applyFilters();
            e.preventDefault();
        }
    });
    
    // Bulk-Aktion anwenden
    applyBulkActionButton.on('click', function() {
        const action = bulkActionSelect.val();
        
        if (!action) {
            alert(alenseoData.messages.selectAction || 'Bitte wähle eine Aktion aus.');
            return;
        }
        
        if (selectedContentIds.length === 0) {
            alert(alenseoData.messages.selectContent || 'Bitte wähle mindestens einen Inhalt aus.');
            return;
        }
        
        // Bestätigung für Massenaktionen
        if (!confirm('Möchtest du die Aktion "' + bulkActionSelect.find('option:selected').text() + '" für ' + selectedContentIds.length + ' Elemente ausführen?')) {
            return;
        }
        
        // Fortschrittsanzeige initialisieren
        initProgressBar(selectedContentIds.length);
        
        // AJAX-Anfrage für die entsprechende Aktion
        switch (action) {
            case 'analyze_content':
                processBulkAction('alenseo_bulk_analyze', selectedContentIds);
                break;
            case 'generate_keywords':
                processBulkAction('alenseo_bulk_generate_keywords', selectedContentIds);
                break;
            case 'optimize_titles':
                processBulkAction('alenseo_bulk_optimize_titles', selectedContentIds);
                break;
            case 'optimize_meta_descriptions':
                processBulkAction('alenseo_bulk_optimize_meta_descriptions', selectedContentIds);
                break;
            case 'optimize_content':
                processBulkAction('alenseo_bulk_optimize_content', selectedContentIds);
                break;
            case 'optimize_all':
                processBulkAction('alenseo_bulk_optimize_all', selectedContentIds);
                break;
        }
    });
    
    // "Optimieren"-Button-Klick
    optimizeButtons.on('click', function() {
        const contentId = $(this).data('id');
        currentContentId = contentId;
        
        // Aktuelle Keyword-Information abrufen
        const $row = $(`tr[data-id="${contentId}"]`);
        const keywordEl = $row.find('.column-keyword .alenseo-keyword-badge');
        
        if (keywordEl.length > 0) {
            currentKeyword = keywordEl.text().trim();
            currentKeywordValue.text(currentKeyword);
        } else {
            currentKeyword = '';
            currentKeywordValue.text('Nicht gesetzt');
        }
        
        // Dialog öffnen
        openOptimizationDialog();
    });
    
    // "Analysieren"-Button-Klick
    analyzeButtons.on('click', function() {
        const contentId = $(this).data('id');
        const button = $(this);
        
        // Button deaktivieren und Ladeanimation anzeigen
        button.prop('disabled', true);
        const originalHtml = button.html();
        button.html('<span class="dashicons dashicons-update spinning"></span>');
        
        // AJAX-Anfrage für Analyse
        $.ajax({
            url: alenseoData.ajaxUrl || ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_analyze_content',
                post_id: contentId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                // Button zurücksetzen
                button.prop('disabled', false);
                button.html(originalHtml);
                
                if (response.success) {
                    // Erfolgsmeldung und Zeile aktualisieren
                    const $row = $(`tr[data-id="${contentId}"]`);
                    
                    // Update status column
                    const statusColumn = $row.find('.column-status');
                    if (statusColumn.length) {
                        const status = response.data.status || 'to-improve';
                        const statusText = status === 'optimized' ? 'Optimiert' : 'Zu verbessern';
                        statusColumn.html(`<span class="alenseo-status-badge ${status}">${statusText}</span>`);
                    }
                    
                    // Update score column if it exists
                    const scoreColumn = $row.find('.column-score');
                    if (scoreColumn.length && response.data.score) {
                        scoreColumn.text(response.data.score);
                    }
                    
                    // Show success message
                    alert(response.data.message || 'Analyse erfolgreich durchgeführt.');
                } else {
                    // Fehlermeldung
                    alert(response.data.message || 'Fehler bei der Analyse.');
                }
            },
            error: function() {
                // Button zurücksetzen
                button.prop('disabled', false);
                button.html(originalHtml);
                
                // Fehlermeldung
                alert('Fehler bei der Kommunikation mit dem Server.');
            }
        });
    });
    
    // Dialog schließen
    dialogCloseButtons.on('click', function() {
        closeOptimizationDialog();
    });
    
    // Keywords generieren
    generateKeywordsButton.on('click', function() {
        if (!currentContentId) {
            return;
        }
        
        // UI-Status aktualisieren
        keywordSuggestions.hide();
        keywordsLoader.show();
        generateKeywordsButton.prop('disabled', true);
        
        // AJAX-Anfrage für Keywords
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_claude_generate_keywords',
                post_id: currentContentId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                // UI zurücksetzen
                keywordsLoader.hide();
                generateKeywordsButton.prop('disabled', false);
                
                if (response.success && response.data.keywords) {
                    // Keywords anzeigen
                    displayKeywordSuggestions(response.data.keywords);
                } else {
                    // Fehlerbehandlung
                    alert(response.data.message || 'Fehler bei der Keyword-Generierung.');
                }
            },
            error: function() {
                // UI zurücksetzen
                keywordsLoader.hide();
                generateKeywordsButton.prop('disabled', false);
                
                // Fehlerbehandlung
                alert('Fehler bei der Kommunikation mit dem Server.');
            }
        });
    });
    
    // Optimierung starten
    startOptimizationButton.on('click', function() {
        if (!currentContentId) {
            return;
        }
        
        // Prüfen, ob ein Keyword gesetzt ist
        if (!currentKeyword) {
            alert('Bitte generiere und wähle zuerst ein Keyword aus.');
            return;
        }
        
        // Optimierungsoptionen abrufen
        const optimizeTitle = $('input[name="optimize_title"]').prop('checked');
        const optimizeMeta = $('input[name="optimize_meta_description"]').prop('checked');
        const optimizeContent = $('input[name="optimize_content"]').prop('checked');
        
        if (!optimizeTitle && !optimizeMeta && !optimizeContent) {
            alert('Bitte wähle mindestens ein Element zur Optimierung aus.');
            return;
        }
        
        // UI aktualisieren
        optimizerLoader.show();
        startOptimizationButton.prop('disabled', true);
        
        // AJAX-Anfrage für Optimierungsvorschläge
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_claude_get_optimization_suggestions',
                post_id: currentContentId,
                keyword: currentKeyword,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                // UI zurücksetzen
                optimizerLoader.hide();
                startOptimizationButton.prop('disabled', false);
                
                if (response.success && response.data.suggestions) {
                    // Optimierungsvorschläge anzeigen
                    displayOptimizationResults(response.data.suggestions, optimizeTitle, optimizeMeta, optimizeContent);
                } else {
                    // Fehlerbehandlung
                    alert(response.data.message || 'Fehler bei der Generierung von Optimierungsvorschlägen.');
                }
            },
            error: function() {
                // UI zurücksetzen
                optimizerLoader.hide();
                startOptimizationButton.prop('disabled', false);
                
                // Fehlerbehandlung
                alert('Fehler bei der Kommunikation mit dem Server.');
            }
        });
    });
    
    // Hilfsfunktionen
    
    // Ausgewählte Content-IDs aktualisieren
    function updateSelectedContentIds() {
        selectedContentIds = [];
        contentCheckboxes.each(function() {
            if ($(this).prop('checked')) {
                selectedContentIds.push($(this).val());
            }
        });
    }
    
    // UI für Auswahl aktualisieren
    function updateSelectionUI() {
        if (selectedContentIds.length > 0) {
            selectionInfo.show();
            selectedCountEl.text(selectedContentIds.length);
        } else {
            selectionInfo.hide();
        }
    }
    
    // Fortschrittsanzeige initialisieren
    function initProgressBar(total) {
        progressBarFill.css('width', '0%');
        progressCurrent.text('0');
        progressTotal.text(total);
        progressBarContainer.show();
    }
    
    // Fortschrittsanzeige aktualisieren
    function updateProgressBar(current, total) {
        const percentage = (current / total) * 100;
        progressBarFill.css('width', percentage + '%');
        progressCurrent.text(current);
    }
    
    // Fortschrittsanzeige abschließen
    function completeProgressBar() {
        progressBarFill.css('width', '100%');
        
        // Nach 2 Sekunden ausblenden
        setTimeout(function() {
            progressBarContainer.fadeOut();
        }, 2000);
    }
    
    // Bulk-Aktion verarbeiten
    function processBulkAction(action, contentIds) {
        // UI-Elemente deaktivieren
        applyBulkActionButton.prop('disabled', true);
        
        // Batch-Einstellungen
        const batchSize = 5; // Anzahl der Elemente pro Batch
        
        // Optimierungseinstellungen abrufen
        const optimizeSettings = {
            optimize_title: action === 'alenseo_bulk_optimize_titles' || action === 'alenseo_bulk_optimize_all',
            optimize_meta_description: action === 'alenseo_bulk_optimize_meta_descriptions' || action === 'alenseo_bulk_optimize_all',
            optimize_content: action === 'alenseo_bulk_optimize_content' || action === 'alenseo_bulk_optimize_all',
            tone: 'professional', // Standardwert oder aus einer Auswahl übernehmen
            level: 'moderate'     // Standardwert oder aus einer Auswahl übernehmen
        };
        
        // Batch-Verarbeitung starten
        processBatch(action, contentIds, 0, batchSize, optimizeSettings);
    }
    
    // Batch-Verarbeitung
    function processBatch(action, contentIds, batchIndex, batchSize, settings) {
        // AJAX-Anfrage senden
        $.ajax({
            url: alenseoData.ajaxUrl || ajaxurl,
            type: 'POST',
            data: {
                action: action,
                post_ids: contentIds,
                batch_size: batchSize,
                batch_index: batchIndex,
                settings: settings,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Fortschritt aktualisieren
                    updateProgressBar(response.data.stats.processed, response.data.stats.total);
                    
                    // UI für verarbeitete Posts aktualisieren
                    if (response.data.results) {
                        Object.keys(response.data.results).forEach(function(postId) {
                            const result = response.data.results[postId];
                            const $row = $(`tr[data-id="${postId}"]`);
                            
                            // Update status column
                            const statusColumn = $row.find('.column-status');
                            if (statusColumn.length && result.success) {
                                const status = result.status || 'to-improve';
                                const statusText = status === 'optimized' ? 'Optimiert' : 
                                                  status === 'no_keyword' ? 'Keine Keywords' : 'Zu verbessern';
                                statusColumn.html(`<span class="alenseo-status-badge ${status}">${statusText}</span>`);
                            }
                            
                            // Update score column if it exists
                            const scoreColumn = $row.find('.column-score');
                            if (scoreColumn.length && result.score) {
                                scoreColumn.text(result.score);
                            }
                        });
                    }
                    
                    // Wenn nicht abgeschlossen, nächsten Batch verarbeiten
                    if (!response.data.completed) {
                        processBatch(action, contentIds, response.data.next_batch, batchSize, settings);
                    } else {
                        // Abschluss
                        completeProgressBar();
                        
                        // UI-Elemente zurücksetzen
                        applyBulkActionButton.prop('disabled', false);
                        
                        // Erfolgsmeldung anzeigen
                        alert(response.data.message || alenseoData.messages.allDone);
                        
                        // Seite neu laden, um aktualisierte Daten anzuzeigen
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    // UI-Elemente zurücksetzen
                    applyBulkActionButton.prop('disabled', false);
                    
                    // Fehlerbehandlung
                    alert(response.data.message || alenseoData.messages.error);
                    progressBarContainer.hide();
                }
            },
            error: function() {
                // UI-Elemente zurücksetzen
                applyBulkActionButton.prop('disabled', false);
                
                // Fehlerbehandlung
                alert(alenseoData.messages.error);
                progressBarContainer.hide();
            }
        });
    }
    
    // Optimierungsdialog öffnen
    function openOptimizationDialog() {
        // Dialog-Zustand zurücksetzen
        keywordSuggestions.hide();
        resultsSection.hide();
        
        // Dialog öffnen
        optimizationDialog.fadeIn(200);
    }
    
    // Optimierungsdialog schließen
    function closeOptimizationDialog() {
        optimizationDialog.fadeOut(200);
        
        // Status zurücksetzen
        currentContentId = null;
        currentKeyword = '';
    }
    
    // Keyword-Vorschläge anzeigen
    function displayKeywordSuggestions(keywords) {
        // Liste leeren
        keywordList.empty();
        
        // Keyword-Items erstellen
        $.each(keywords, function(index, keyword) {
            const keywordText = keyword.keyword || keyword;
            const score = keyword.score ? ' <small>(' + keyword.score + ')</small>' : '';
            
            const keywordItem = $('<div class="alenseo-keyword-item"></div>');
            keywordItem.html('<span class="alenseo-keyword-text">' + keywordText + score + '</span>');
            
            // Klick-Event für Keyword-Auswahl
            keywordItem.on('click', function() {
                // Ausgewähltes Keyword markieren
                $('.alenseo-keyword-item').removeClass('selected');
                $(this).addClass('selected');
                
                // Keyword speichern
                currentKeyword = keywordText;
                currentKeywordValue.text(keywordText);
                
                // AJAX-Anfrage zum Speichern des Keywords
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'alenseo_save_keyword',
                        post_id: currentContentId,
                        keyword: keywordText,
                        nonce: alenseoData.nonce
                    },
                    success: function(response) {
                        if (!response.success) {
                            console.error('Fehler beim Speichern des Keywords:', response.data.message);
                        }
                    }
                });
            });
            
            keywordList.append(keywordItem);
        });
        
        // Vorschläge anzeigen
        keywordSuggestions.show();
    }
    
    // Optimierungsergebnisse anzeigen
    function displayOptimizationResults(suggestions, showTitle, showMeta, showContent) {
        // Container leeren
        resultsContainer.empty();
        
        let resultsHtml = '';
        
        // Titel-Vorschlag anzeigen
        if (showTitle && suggestions.title) {
            resultsHtml += `
                <div class="alenseo-result-section">
                    <h4>Optimierter Titel</h4>
                    <div class="alenseo-result-content">${suggestions.title}</div>
                    <button type="button" class="button alenseo-apply-result" data-type="title" data-content="${encodeURIComponent(suggestions.title)}">
                        Anwenden
                    </button>
                </div>
            `;
        }
        
        // Meta-Description-Vorschlag anzeigen
        if (showMeta && suggestions.meta_description) {
            resultsHtml += `
                <div class="alenseo-result-section">
                    <h4>Optimierte Meta-Description</h4>
                    <div class="alenseo-result-content">${suggestions.meta_description}</div>
                    <button type="button" class="button alenseo-apply-result" data-type="meta_description" data-content="${encodeURIComponent(suggestions.meta_description)}">
                        Anwenden
                    </button>
                </div>
            `;
        }
        
        // Inhaltsvorschläge anzeigen
        if (showContent && suggestions.content && suggestions.content.length > 0) {
            resultsHtml += `
                <div class="alenseo-result-section">
                    <h4>Inhaltsoptimierungen</h4>
                    <ul class="alenseo-content-suggestions">
            `;
            
            // Jeder Vorschlag als Listenpunkt
            suggestions.content.forEach(function(suggestion) {
                resultsHtml += `<li>${suggestion}</li>`;
            });
            
            resultsHtml += `
                    </ul>
                </div>
            `;
        }
        
        // Keine Vorschläge gefunden
        if (resultsHtml === '') {
            resultsHtml = '<p>Keine Optimierungsvorschläge gefunden.</p>';
        }
        
        // HTML einfügen
        resultsContainer.html(resultsHtml);
        
        // Event-Handler für "Anwenden"-Buttons
        $('.alenseo-apply-result').on('click', function() {
            const type = $(this).data('type');
            const content = decodeURIComponent($(this).data('content'));
            const button = $(this);
            
            // AJAX-Anfrage für das Anwenden des Vorschlags
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_apply_suggestion',
                    post_id: currentContentId,
                    type: type,
                    content: content,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Button-Status aktualisieren
                        button.text('Angewendet').prop('disabled', true);
                    } else {
                        alert(response.data.message || 'Fehler beim Anwenden des Vorschlags.');
                    }
                },
                error: function() {
                    alert('Fehler bei der Kommunikation mit dem Server.');
                }
            });
        });
        
        // Ergebnisse anzeigen
        resultsSection.show();
    }
    
    // Einzelne Analyse-Funktion
    analyzeButtons.on('click', function() {
        const $button = $(this);
        const postId = $button.data('post-id');
        const $row = $button.closest('.alenseo-content-row');
        const $statusCell = $row.find('.alenseo-content-status');
        const $scoreCell = $row.find('.alenseo-content-score');
        
        // Button-Status ändern
        $button.prop('disabled', true).addClass('button-busy');
        $button.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> ' + alenseoBulkOptimizerParams.analyzing);
        
        // AJAX-Anfrage für Analyse
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_analyze_content',
                post_id: postId,
                nonce: ajaxNonce
            },
            success: function(response) {
                if (response.success) {
                    // Status und Score aktualisieren
                    const score = response.data.score || 0;
                    const status = response.data.status || 'poor';
                    
                    // UI aktualisieren
                    updateContentRow($row, score, status);
                    
                    // Erfolgsmeldung
                    showNotification('success', alenseoBulkOptimizerParams.analyzeSuccess);
                } else {
                    // Fehlermeldung
                    showNotification('error', response.data.message || alenseoBulkOptimizerParams.analyzeError);
                }
            },
            error: function() {
                showNotification('error', alenseoBulkOptimizerParams.ajaxError);
            },
            complete: function() {
                // Button zurücksetzen
                $button.prop('disabled', false).removeClass('button-busy');
                $button.html('<span class="dashicons dashicons-search"></span> ' + alenseoBulkOptimizerParams.analyze);
            }
        });
    });
    
    // Einzelne Optimierung-Funktion
    optimizeButtons.on('click', function() {
        const $button = $(this);
        const postId = $button.data('post-id');
        const $row = $button.closest('.alenseo-content-row');
        
        // Button-Status ändern
        $button.prop('disabled', true).addClass('button-busy');
        $button.html('<span class="spinner is-active" style="float: none; margin: 0;"></span> ' + alenseoBulkOptimizerParams.optimizing);
        
        // AJAX-Anfrage für Optimierung
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_optimize_single_content',
                post_id: postId,
                optimize_type: 'all',
                nonce: ajaxNonce
            },
            success: function(response) {
                if (response.success) {
                    // Status und Score aktualisieren
                    const score = response.data.score || 0;
                    const status = response.data.status || 'poor';
                    
                    // UI aktualisieren
                    updateContentRow($row, score, status);
                    
                    // Wenn Empfehlungen zurückgegeben wurden, Dialog anzeigen
                    if (response.data.recommendations) {
                        showRecommendationsDialog(postId, response.data.recommendations);
                    } else {
                        // Erfolgsmeldung ohne Dialog
                        showNotification('success', alenseoBulkOptimizerParams.optimizeSuccess);
                    }
                } else {
                    // Fehlermeldung
                    showNotification('error', response.data.message || alenseoBulkOptimizerParams.optimizeError);
                }
            },
            error: function() {
                showNotification('error', alenseoBulkOptimizerParams.ajaxError);
            },
            complete: function() {
                // Button zurücksetzen
                $button.prop('disabled', false).removeClass('button-busy');
                $button.html('<span class="dashicons dashicons-admin-generic"></span> ' + alenseoBulkOptimizerParams.optimize);
            }
        });
    });
    
    // Massenoptimierung anwenden
    applyBulkActionButton.on('click', function() {
        const action = bulkActionSelect.val();
        
        // Prüfen, ob eine Aktion ausgewählt wurde
        if (!action) {
            showNotification('error', alenseoBulkOptimizerParams.noActionSelected);
            return;
        }
        
        // Prüfen, ob Inhalte ausgewählt wurden
        if (selectedContentIds.length === 0) {
            showNotification('error', alenseoBulkOptimizerParams.noContentSelected);
            return;
        }
        
        // Bestätigungsdialog anzeigen
        if (confirm(alenseoBulkOptimizerParams.bulkConfirmation.replace('%count%', selectedContentIds.length))) {
            // Fortschrittsanzeige initialisieren
            progressBarContainer.show();
            progressBarFill.css('width', '0%');
            progressCurrent.text('0');
            progressTotal.text(selectedContentIds.length);
            
            // Button-Status ändern
            applyBulkActionButton.prop('disabled', true);
            bulkActionSelect.prop('disabled', true);
            
            // Massenoptimierung starten
            processBulkAction(action, selectedContentIds);
        }
    });
    
    // Inhaltszeile nach Optimierung/Analyse aktualisieren
    function updateContentRow($row, score, status) {
        const $scoreCell = $row.find('.alenseo-content-score');
        const $statusCell = $row.find('.alenseo-content-status');
        
        // Score aktualisieren
        $scoreCell.text(score);
        
        // Status-Klassen entfernen
        $row.removeClass('status-good status-needs-improvement status-poor');
        $statusCell.removeClass('status-good status-needs-improvement status-poor');
        
        // Status-Text und -Klasse setzen
        let statusText = alenseoBulkOptimizerParams.statusPoor;
        let statusClass = 'status-poor';
        
        if (status === 'good') {
            statusText = alenseoBulkOptimizerParams.statusGood;
            statusClass = 'status-good';
        } else if (status === 'needs_improvement') {
            statusText = alenseoBulkOptimizerParams.statusNeedsImprovement;
            statusClass = 'status-needs-improvement';
        }
        
        $statusCell.text(statusText).addClass(statusClass);
        $row.addClass(statusClass);
    }
    
    // Empfehlungsdialog anzeigen
    function showRecommendationsDialog(postId, recommendations) {
        // TODO: Implementieren Sie einen Dialog für die Anzeige von Empfehlungen
        console.log('Empfehlungen für Post ' + postId, recommendations);
        
        // Hier könnte ein Modal-Dialog erstellt werden, der die Empfehlungen anzeigt
        let recommendationHtml = '<div class="alenseo-recommendations-dialog">';
        recommendationHtml += '<h3>' + alenseoBulkOptimizerParams.recommendationsTitle + '</h3>';
        
        // Kategorien durchgehen
        for (const category in recommendations) {
            if (recommendations.hasOwnProperty(category) && recommendations[category].length > 0) {
                let categoryTitle = '';
                
                switch (category) {
                    case 'keyword_usage':
                        categoryTitle = alenseoBulkOptimizerParams.keywordUsage;
                        break;
                    case 'headings':
                        categoryTitle = alenseoBulkOptimizerParams.headings;
                        break;
                    case 'content_structure':
                        categoryTitle = alenseoBulkOptimizerParams.contentStructure;
                        break;
                    case 'readability':
                        categoryTitle = alenseoBulkOptimizerParams.readability;
                        break;
                    default:
                        categoryTitle = alenseoBulkOptimizerParams.generalRecommendations;
                }
                
                recommendationHtml += '<h4>' + categoryTitle + '</h4><ul>';
                
                recommendations[category].forEach(function(item) {
                    recommendationHtml += '<li>' + item + '</li>';
                });
                
                recommendationHtml += '</ul>';
            }
        }
        
        recommendationHtml += '</div>';
        
        // Vereinfachte Anzeige als Alert (in Zukunft durch ein Modal ersetzen)
        alert(alenseoBulkOptimizerParams.recommendationsAvailable);
    }
    
    // Benachrichtigung anzeigen
    function showNotification(type, message) {
        // Bestehende Benachrichtigungen entfernen
        $('.alenseo-notification').remove();
        
        // Neue Benachrichtigung erstellen
        const notification = $('<div class="alenseo-notification ' + type + '"><p>' + message + '</p></div>');
        $('.alenseo-optimizer-wrap h1').after(notification);
        
        // Nach 5 Sekunden automatisch ausblenden
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
});
