/**
 * Dashboard-Funktionalität JavaScript mit grundlegender Fehlerbehandlung
 * Diese vereinfachte Version verwendet grundlegende Interaktionen für 
 * den Fall, dass die vollständige JavaScript-Datei nicht geladen werden kann
 */

// Selbstausführende anonyme Funktion, um globale Variablen zu vermeiden
(function($) {
    'use strict';

    // Hilfsklasse für das Loggen von Fehlern
    var AlenseoLogger = {
        log: function(message) {
            if (window.console && window.console.log) {
                console.log('Alenseo: ' + message);
            }
        },
        error: function(message) {
            if (window.console && window.console.error) {
                console.error('Alenseo Error: ' + message);
            }
        }
    };

    // Dashboard-Funktionen
    var AlenseoDashboard = {
        /**
         * Initialisierungsfunktion
         */
        init: function() {
            try {
                AlenseoLogger.log('Initialisiere Dashboard');
                this.initSelectAll();
                this.initFilters();
                this.initBulkActions();
                this.initActionButtons();
                this.initDialogs();
                AlenseoLogger.log('Dashboard erfolgreich initialisiert');
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Initialisierung des Dashboards: ' + e.message);
                this.showErrorMessage('Fehler bei der Initialisierung des Dashboards. Bitte aktualisiere die Seite und versuche es erneut.');
            }
        },

        /**
         * "Alle auswählen" Checkbox-Funktionalität
         */
        initSelectAll: function() {
            var self = this;
            
            try {
                var $selectAll = $('#alenseo-select-all');
                var $contentCheckboxes = $('.alenseo-select-content');
                var $selectedCount = $('#alenseo-selected-count');
                var $selectionInfo = $('.alenseo-selection-info');
                var $clearSelection = $('#alenseo-clear-selection');
                
                if ($selectAll.length) {
                    // "Alle auswählen" Checkbox-Handler
                    $selectAll.on('change', function() {
                        var isChecked = $(this).prop('checked');
                        $contentCheckboxes.prop('checked', isChecked);
                        self.updateSelectionInfo();
                    });
                    
                    // Einzelne Checkbox-Handler
                    $contentCheckboxes.on('change', function() {
                        self.updateSelectionInfo();
                        
                        // Aktualisiere "Alle auswählen" Checkbox
                        var allChecked = $contentCheckboxes.length === $contentCheckboxes.filter(':checked').length;
                        $selectAll.prop('checked', allChecked);
                    });
                    
                    // Auswahl löschen
                    if ($clearSelection.length) {
                        $clearSelection.on('click', function() {
                            $selectAll.prop('checked', false);
                            $contentCheckboxes.prop('checked', false);
                            self.updateSelectionInfo();
                        });
                    }
                }
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Initialisierung der Auswahlboxen: ' + e.message);
            }
        },
        
        /**
         * Aktualisiere die Auswahlinfo (Anzahl ausgewählter Elemente)
         */
        updateSelectionInfo: function() {
            try {
                var $selectedCount = $('#alenseo-selected-count');
                var $selectionInfo = $('.alenseo-selection-info');
                var $contentCheckboxes = $('.alenseo-select-content');
                
                var selectedCount = $contentCheckboxes.filter(':checked').length;
                
                if ($selectedCount.length) {
                    $selectedCount.text(selectedCount);
                    
                    if (selectedCount > 0) {
                        $selectionInfo.show();
                    } else {
                        $selectionInfo.hide();
                    }
                }
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Aktualisierung der Auswahlinformationen: ' + e.message);
            }
        },
        
        /**
         * Filter-Funktionalität initialisieren
         */
        initFilters: function() {
            var self = this;
            
            try {
                var $filterPostType = $('#alenseo-filter-post-type');
                var $filterStatus = $('#alenseo-filter-status');
                var $filterKeyword = $('#alenseo-filter-keyword');
                var $searchInput = $('#alenseo-search-input');
                var $searchButton = $('#alenseo-search-button');
                var $contentRows = $('.alenseo-content-row');
                
                // Filteränderungen behandeln
                $filterPostType.on('change', function() { self.applyFilters(); });
                $filterStatus.on('change', function() { self.applyFilters(); });
                $filterKeyword.on('change', function() { self.applyFilters(); });
                
                // Suche
                $searchButton.on('click', function() { self.applyFilters(); });
                $searchInput.on('keypress', function(e) {
                    if (e.which === 13) { // Enter-Taste
                        self.applyFilters();
                        e.preventDefault();
                    }
                });
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Initialisierung der Filter: ' + e.message);
            }
        },
        
        /**
         * Filter anwenden
         */
        applyFilters: function() {
            try {
                var $filterPostType = $('#alenseo-filter-post-type');
                var $filterStatus = $('#alenseo-filter-status');
                var $filterKeyword = $('#alenseo-filter-keyword');
                var $searchInput = $('#alenseo-search-input');
                var $contentRows = $('.alenseo-content-row');
                
                var postType = $filterPostType.val();
                var status = $filterStatus.val();
                var keyword = $filterKeyword.val();
                var searchQuery = $searchInput.val().toLowerCase();
                
                $contentRows.each(function() {
                    var $row = $(this);
                    var visible = true;
                    
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
                        var title = $row.find('.column-title a').text().toLowerCase();
                        if (title.indexOf(searchQuery) === -1) {
                            visible = false;
                        }
                    }
                    
                    // Zeile anzeigen oder verstecken
                    $row.toggle(visible);
                });
            } catch (e) {
                AlenseoLogger.error('Fehler beim Anwenden der Filter: ' + e.message);
            }
        },
        
        /**
         * Massenaktionen-Funktionalität
         */
        initBulkActions: function() {
            var self = this;
            
            try {
                var $bulkAction = $('#alenseo-bulk-action');
                var $applyBulkAction = $('#alenseo-apply-bulk-action');
                
                if ($applyBulkAction.length) {
                    $applyBulkAction.on('click', function() {
                        var action = $bulkAction.val();
                        
                        if (!action) {
                            alert('Bitte wähle eine Aktion aus.');
                            return;
                        }
                        
                        var selectedIds = self.getSelectedContentIds();
                        
                        if (selectedIds.length === 0) {
                            alert('Bitte wähle mindestens einen Inhalt aus.');
                            return;
                        }
                        
                        if (confirm('Möchtest du die Aktion "' + $bulkAction.find('option:selected').text() + '" für ' + selectedIds.length + ' Elemente ausführen?')) {
                            self.executeBulkAction(action, selectedIds);
                        }
                    });
                }
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Initialisierung der Massenaktionen: ' + e.message);
            }
        },
        
        /**
         * Ausgewählte Content-IDs abrufen
         */
        getSelectedContentIds: function() {
            var ids = [];
            
            try {
                $('.alenseo-select-content:checked').each(function() {
                    ids.push($(this).val());
                });
            } catch (e) {
                AlenseoLogger.error('Fehler beim Abrufen der ausgewählten IDs: ' + e.message);
            }
            
            return ids;
        },
        
        /**
         * Massenaktion ausführen
         */
        executeBulkAction: function(action, ids) {
            var self = this;
            
            try {
                // Fortschrittsanzeige initialisieren
                self.initProgressBar(ids.length);
                
                // AJAX-Anfrage für die entsprechende Aktion
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'alenseo_bulk_' + action,
                        post_ids: ids,
                        nonce: alenseoData.nonce
                    },
                    success: function(response) {
                        // Fortschrittsanzeige abschließen
                        self.completeProgressBar();
                        
                        if (response.success) {
                            alert(response.data.message || 'Aktion erfolgreich ausgeführt.');
                            
                            // Seite neuladen, um aktualisierte Daten anzuzeigen
                            location.reload();
                        } else {
                            alert(response.data.message || 'Fehler bei der Ausführung der Aktion.');
                            $('#alenseo-progress-bar').hide();
                        }
                    },
                    error: function() {
                        // Fortschrittsanzeige ausblenden
                        $('#alenseo-progress-bar').hide();
                        
                        alert('Fehler bei der Kommunikation mit dem Server.');
                    }
                });
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Ausführung der Massenaktion: ' + e.message);
                alert('Fehler bei der Ausführung der Aktion: ' + e.message);
                $('#alenseo-progress-bar').hide();
            }
        },
        
        /**
         * Fortschrittsanzeige initialisieren
         */
        initProgressBar: function(total) {
            try {
                var $progressBar = $('#alenseo-progress-bar');
                var $progressFill = $('.alenseo-progress-fill');
                var $progressCurrent = $('#alenseo-progress-current');
                var $progressTotal = $('#alenseo-progress-total');
                
                $progressFill.css('width', '0%');
                $progressCurrent.text('0');
                $progressTotal.text(total);
                $progressBar.show();
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Initialisierung der Fortschrittsanzeige: ' + e.message);
            }
        },
        
        /**
         * Fortschrittsanzeige abschließen
         */
        completeProgressBar: function() {
            try {
                var $progressBar = $('#alenseo-progress-bar');
                var $progressFill = $('.alenseo-progress-fill');
                
                $progressFill.css('width', '100%');
                
                setTimeout(function() {
                    $progressBar.fadeOut();
                }, 2000);
            } catch (e) {
                AlenseoLogger.error('Fehler beim Abschließen der Fortschrittsanzeige: ' + e.message);
            }
        },
        
        /**
         * Aktionsbuttons initialisieren
         */
        initActionButtons: function() {
            var self = this;
            
            try {
                // "Optimieren"-Button-Klick
                $('.alenseo-action-optimize').on('click', function() {
                    var contentId = $(this).data('id');
                    self.openOptimizationDialog(contentId);
                });
                
                // "Analysieren"-Button-Klick
                $('.alenseo-action-analyze').on('click', function() {
                    var contentId = $(this).data('id');
                    var button = $(this);
                    
                    // Button deaktivieren und Ladeanimation anzeigen
                    button.prop('disabled', true);
                    var originalHtml = button.html();
                    button.html('<span class="dashicons dashicons-update"></span>');
                    
                    // AJAX-Anfrage für Analyse
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'alenseo_analyze_post',
                            post_id: contentId,
                            nonce: alenseoData.nonce
                        },
                        success: function(response) {
                            // Button zurücksetzen
                            button.prop('disabled', false);
                            button.html(originalHtml);
                            
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
                            // Button zurücksetzen
                            button.prop('disabled', false);
                            button.html(originalHtml);
                            
                            // Fehlermeldung
                            alert('Fehler bei der Kommunikation mit dem Server.');
                        }
                    });
                });
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Initialisierung der Aktionsbuttons: ' + e.message);
            }
        },
        
        /**
         * Dialog-Funktionalität initialisieren
         */
        initDialogs: function() {
            var self = this;
            
            try {
                var $dialog = $('#alenseo-optimization-dialog');
                var $closeButtons = $('.alenseo-dialog-close');
                var $generateKeywordsButton = $('#alenseo-generate-keywords');
                var $startOptimizationButton = $('#alenseo-start-optimization');
                
                // Dialog schließen
                $closeButtons.on('click', function() {
                    self.closeDialog();
                });
                
                // Keywords generieren
                if ($generateKeywordsButton.length) {
                    $generateKeywordsButton.on('click', function() {
                        self.generateKeywords();
                    });
                }
                
                // Optimierung starten
                if ($startOptimizationButton.length) {
                    $startOptimizationButton.on('click', function() {
                        self.startOptimization();
                    });
                }
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Initialisierung der Dialoge: ' + e.message);
            }
        },
        
        /**
         * Optimierungsdialog öffnen
         */
        openOptimizationDialog: function(contentId) {
            var self = this;
            
            try {
                var $dialog = $('#alenseo-optimization-dialog');
                var $keywordSuggestions = $('.alenseo-keyword-suggestions');
                var $resultsSection = $('.alenseo-results-section');
                
                // Aktuelles Content-ID speichern
                self.currentContentId = contentId;
                
                // Aktuelle Keyword-Information abrufen
                var $row = $('tr[data-id="' + contentId + '"]');
                var $keywordEl = $row.find('.column-keyword .alenseo-keyword-badge');
                var $currentKeywordValue = $('#alenseo-current-keyword-value');
                
                if ($keywordEl.length > 0) {
                    self.currentKeyword = $keywordEl.text().trim();
                    $currentKeywordValue.text(self.currentKeyword);
                } else {
                    self.currentKeyword = '';
                    $currentKeywordValue.text('Nicht gesetzt');
                }
                
                // Dialog-Zustand zurücksetzen
                $keywordSuggestions.hide();
                $resultsSection.hide();
                
                // Dialog öffnen
                $dialog.fadeIn(200);
            } catch (e) {
                AlenseoLogger.error('Fehler beim Öffnen des Optimierungsdialogs: ' + e.message);
                alert('Fehler beim Öffnen des Dialogs. Bitte versuche es erneut.');
            }
        },
        
        /**
         * Dialog schließen
         */
        closeDialog: function() {
            try {
                var $dialog = $('#alenseo-optimization-dialog');
                $dialog.fadeOut(200);
                
                // Status zurücksetzen
                this.currentContentId = null;
                this.currentKeyword = '';
            } catch (e) {
                AlenseoLogger.error('Fehler beim Schließen des Dialogs: ' + e.message);
            }
        },
        
        /**
         * Keywords generieren
         */
        generateKeywords: function() {
            var self = this;
            
            try {
                if (!self.currentContentId) {
                    return;
                }
                
                var $keywordSuggestions = $('.alenseo-keyword-suggestions');
                var $keywordsLoader = $('.alenseo-keywords-loader');
                var $keywordList = $('.alenseo-keyword-list');
                var $generateKeywordsButton = $('#alenseo-generate-keywords');
                
                // UI-Status aktualisieren
                $keywordSuggestions.hide();
                $keywordsLoader.show();
                $generateKeywordsButton.prop('disabled', true);
                
                // AJAX-Anfrage für Keywords
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'alenseo_claude_generate_keywords',
                        post_id: self.currentContentId,
                        nonce: alenseoData.nonce
                    },
                    success: function(response) {
                        // UI zurücksetzen
                        $keywordsLoader.hide();
                        $generateKeywordsButton.prop('disabled', false);
                        
                        if (response.success && response.data.keywords) {
                            // Keywords anzeigen
                            self.displayKeywordSuggestions(response.data.keywords);
                        } else {
                            // Fehlerbehandlung
                            alert(response.data.message || 'Fehler bei der Keyword-Generierung.');
                        }
                    },
                    error: function() {
                        // UI zurücksetzen
                        $keywordsLoader.hide();
                        $generateKeywordsButton.prop('disabled', false);
                        
                        // Fehlerbehandlung
                        alert('Fehler bei der Kommunikation mit dem Server.');
                    }
                });
            } catch (e) {
                AlenseoLogger.error('Fehler bei der Keyword-Generierung: ' + e.message);
                alert('Fehler bei der Keyword-Generierung. Bitte versuche es erneut.');
                
                // UI zurücksetzen
                $('.alenseo-keywords-loader').hide();
                $('#alenseo-generate-keywords').prop('disabled', false);
            }
        },
        
        /**
         * Keyword-Vorschläge anzeigen
         */
        displayKeywordSuggestions: function(keywords) {
            var self = this;
            
            try {
                var $keywordSuggestions = $('.alenseo-keyword-suggestions');
                var $keywordList = $('.alenseo-keyword-list');
                
                // Liste leeren
                $keywordList.empty();
                
                // Keyword-Items erstellen
                $.each(keywords, function(index, keyword) {
                    var keywordText = keyword.keyword || keyword;
                    var score = keyword.score ? ' <small>(' + keyword.score + ')</small>' : '';
                    
                    var $keywordItem = $('<div class="alenseo-keyword-item"></div>');
                    $keywordItem.html('<span class="alenseo-keyword-text">' + keywordText + score + '</span>');
                    
                    // Klick-Event für Keyword-Auswahl
                    $keywordItem.on('click', function() {
                        // Ausgewähltes Keyword markieren
                        $('.alenseo-keyword-item').removeClass('selected');
                        $(this).addClass('selected');
                        
                        // Keyword speichern
                        self.currentKeyword = keywordText;
                        $('#alenseo-current-keyword-value').text(keywordText);
                        
                        // AJAX-Anfrage zum Speichern des Keywords
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'alenseo_save_keyword',
                                post_id: self.currentContentId,
                                keyword: keywordText,
                                nonce: alenseoData.nonce
                            },
                            success: function(response) {
                                if (!response.success) {
                                    AlenseoLogger.error('Fehler beim Speichern des Keywords: ' + (response.data.message || 'Unbekannter Fehler'));
                                }
                            },
                            error: function() {
                                AlenseoLogger.error('Fehler bei der Kommunikation mit dem Server beim Speichern des Keywords');
                            }
                        });
                    });
                    
                    $keywordList.append($keywordItem);
                });
                
                // Vorschläge anzeigen
                $keywordSuggestions.show();
            } catch (e) {
                AlenseoLogger.error('Fehler beim Anzeigen der Keyword-Vorschläge: ' + e.message);
            }
        },
        
        /**
         * Optimierung starten
         */
        startOptimization: function() {
            var self = this;
            
            try {
                if (!self.currentContentId) {
                    return;
                }
                
                // Prüfen, ob ein Keyword gesetzt ist
                if (!self.currentKeyword) {
                    alert('Bitte generiere und wähle zuerst ein Keyword aus.');
                    return;
                }
                
                // Optimierungsoptionen abrufen
                var optimizeTitle = $('input[name="optimize_title"]').prop('checked');
                var optimizeMeta = $('input[name="optimize_meta_description"]').prop('checked');
                var optimizeContent = $('input[name="optimize_content"]').prop('checked');
                
                if (!optimizeTitle && !optimizeMeta && !optimizeContent) {
                    alert('Bitte wähle mindestens ein Element zur Optimierung aus.');
                    return;
                }
                
                // UI aktualisieren
                var $optimizerLoader = $('.alenseo-optimizer-loader');
                var $startOptimizationButton = $('#alenseo-start-optimization');
                
                $optimizerLoader.show();
                $startOptimizationButton.prop('disabled', true);
                
                // AJAX-Anfrage für Optimierungsvorschläge
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'alenseo_claude_get_optimization_suggestions',
                        post_id: self.currentContentId,
                        keyword: self.currentKeyword,
                        nonce: alenseoData.nonce
                    },
                    success: function(response) {
                        // UI zurücksetzen
                        $optimizerLoader.hide();
                        $startOptimizationButton.prop('disabled', false);
                        
                        if (response.success && response.data.suggestions) {
                            // Optimierungsvorschläge anzeigen
                            self.displayOptimizationResults(response.data.suggestions, optimizeTitle, optimizeMeta, optimizeContent);
                        } else {
                            // Fehlerbehandlung
                            alert(response.data.message || 'Fehler bei der Generierung von Optimierungsvorschlägen.');
                        }
                    },
                    error: function() {
                        // UI zurücksetzen
                        $optimizerLoader.hide();
                        $startOptimizationButton.prop('disabled', false);
                        
                        // Fehlerbehandlung
                        alert('Fehler bei der Kommunikation mit dem Server.');
                    }
                });
            } catch (e) {
                AlenseoLogger.error('Fehler beim Starten der Optimierung: ' + e.message);
                alert('Fehler beim Starten der Optimierung. Bitte versuche es erneut.');
                
                // UI zurücksetzen
                $('.alenseo-optimizer-loader').hide();
                $('#alenseo-start-optimization').prop('disabled', false);
            }
        },
        
        /**
         * Optimierungsergebnisse anzeigen
         */
        displayOptimizationResults: function(suggestions, showTitle, showMeta, showContent) {
            var self = this;
            
            try {
                var $resultsSection = $('.alenseo-results-section');
                var $resultsContainer = $('.alenseo-results-container');
                
                // Container leeren
                $resultsContainer.empty();
                
                var resultsHtml = '';
                
                // Titel-Vorschlag anzeigen
                if (showTitle && suggestions.title) {
                    resultsHtml += '<div class="alenseo-result-section">' +
                        '<h4>Optimierter Titel</h4>' +
                        '<div class="alenseo-result-content">' + suggestions.title + '</div>' +
                        '<button type="button" class="button alenseo-apply-result" data-type="title" data-content="' + encodeURIComponent(suggestions.title) + '">' +
                        'Anwenden</button></div>';
                }
                
                // Meta-Description-Vorschlag anzeigen
                if (showMeta && suggestions.meta_description) {
                    resultsHtml += '<div class="alenseo-result-section">' +
                        '<h4>Optimierte Meta-Description</h4>' +
                        '<div class="alenseo-result-content">' + suggestions.meta_description + '</div>' +
                        '<button type="button" class="button alenseo-apply-result" data-type="meta_description" data-content="' + encodeURIComponent(suggestions.meta_description) + '">' +
                        'Anwenden</button></div>';
                }
                
                // Inhaltsvorschläge anzeigen
                if (showContent && suggestions.content && suggestions.content.length > 0) {
                    resultsHtml += '<div class="alenseo-result-section">' +
                        '<h4>Inhaltsoptimierungen</h4>' +
                        '<ul class="alenseo-content-suggestions">';
                    
                    // Jeder Vorschlag als Listenpunkt
                    $.each(suggestions.content, function(index, suggestion) {
                        resultsHtml += '<li>' + suggestion + '</li>';
                    });
                    
                    resultsHtml += '</ul></div>';
                }
                
                // Keine Vorschläge gefunden
                if (resultsHtml === '') {
                    resultsHtml = '<p>Keine Optimierungsvorschläge gefunden.</p>';
                }
                
                // HTML einfügen
                $resultsContainer.html(resultsHtml);
                
                // Event-Handler für "Anwenden"-Buttons
                $('.alenseo-apply-result').on('click', function() {
                    self.applyOptimizationSuggestion($(this));
                });
                
                // Ergebnisse anzeigen
                $resultsSection.show();
            } catch (e) {
                AlenseoLogger.error('Fehler beim Anzeigen der Optimierungsergebnisse: ' + e.message);
                alert('Fehler beim Anzeigen der Optimierungsergebnisse. Bitte versuche es erneut.');
            }
        },
        
        /**
         * Optimierungsvorschlag anwenden
         */
        applyOptimizationSuggestion: function($button) {
            var self = this;
            
            try {
                var type = $button.data('type');
                var content = decodeURIComponent($button.data('content'));
                
                // AJAX-Anfrage für das Anwenden des Vorschlags
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'alenseo_apply_suggestion',
                        post_id: self.currentContentId,
                        type: type,
                        content: content,
                        nonce: alenseoData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Button-Status aktualisieren
                            $button.text('Angewendet').prop('disabled', true);
                        } else {
                            alert(response.data.message || 'Fehler beim Anwenden des Vorschlags.');
                        }
                    },
                    error: function() {
                        alert('Fehler bei der Kommunikation mit dem Server.');
                    }
                });
            } catch (e) {
                AlenseoLogger.error('Fehler beim Anwenden des Optimierungsvorschlags: ' + e.message);
                alert('Fehler beim Anwenden des Optimierungsvorschlags. Bitte versuche es erneut.');
            }
        },
        
        /**
         * Fehlermeldung anzeigen
         */
        showErrorMessage: function(message) {
            try {
                var $errorMessage = $('<div class="alenseo-notice notice-error is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"></button></div>');
                $('.alenseo-optimizer-wrap').prepend($errorMessage);
                
                $errorMessage.find('.notice-dismiss').on('click', function() {
                    $errorMessage.slideUp();
                });
            } catch (e) {
                AlenseoLogger.error('Fehler beim Anzeigen der Fehlermeldung: ' + e.message);
                alert(message);
            }
        }
    };
    
    // Initialisierung beim Laden des Dokuments
    $(document).ready(function() {
        // Nur initialisieren, wenn Dashboard-Container vorhanden ist
        if ($('.alenseo-optimizer-wrap').length) {
            AlenseoDashboard.init();
        }
    });
    
})(jQuery);
