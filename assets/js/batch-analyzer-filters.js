/**
 * Filter-Funktionalität für den Batch-Analyzer
 * 
 * @package    Alenseo
 * @subpackage Alenseo/assets/js
 */

(function($) {
    'use strict';
    
    // Filter-Klasse
    var BatchFilters = {
        /**
         * Initialisierung
         */
        init: function() {
            this.bindEvents();
            this.initializeFilters();
        },
        
        /**
         * Event-Handler binden
         */
        bindEvents: function() {
            $('#alenseo-post-type-filter').on('change', this.filterPosts);
            $('#alenseo-seo-status-filter').on('change', this.filterPosts);
            $('#alenseo-search-filter').on('keyup', this.debounce(this.filterPosts, 300));
            $('#alenseo-select-all').on('change', this.toggleAllCheckboxes);
            $('#alenseo-export-csv').on('click', this.exportToCSV);
            $('.alenseo-sortable').on('click', this.sortTable);
            $('#alenseo-bulk-action').on('change', this.toggleBulkApply);
            $('#alenseo-bulk-apply').on('click', this.applyBulkAction);
            $('.alenseo-post-checkbox').on('change', this.updateBulkApply);
        },
        
        /**
         * Filter initialisieren
         */
        initializeFilters: function() {
            // Filter-Werte aus URL-Parametern laden
            var urlParams = new URLSearchParams(window.location.search);
            var postType = urlParams.get('post_type');
            var seoStatus = urlParams.get('seo_status');
            var search = urlParams.get('search');
            
            if (postType) {
                $('#alenseo-post-type-filter').val(postType);
            }
            if (seoStatus) {
                $('#alenseo-seo-status-filter').val(seoStatus);
            }
            if (search) {
                $('#alenseo-search-filter').val(search);
            }
            
            // Initiale Filterung durchführen
            this.filterPosts();
        },
        
        /**
         * Beiträge filtern
         */
        filterPosts: function() {
            var postType = $('#alenseo-post-type-filter').val();
            var seoStatus = $('#alenseo-seo-status-filter').val();
            var search = $('#alenseo-search-filter').val().toLowerCase();
            
            // URL-Parameter aktualisieren
            var url = new URL(window.location.href);
            if (postType) {
                url.searchParams.set('post_type', postType);
            } else {
                url.searchParams.delete('post_type');
            }
            if (seoStatus) {
                url.searchParams.set('seo_status', seoStatus);
            } else {
                url.searchParams.delete('seo_status');
            }
            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            window.history.replaceState({}, '', url);
            
            // Beiträge filtern
            $('#alenseo-posts-list tr').each(function() {
                var $row = $(this);
                var rowPostType = $row.data('post-type');
                var rowSeoStatus = $row.data('seo-status');
                var rowTitle = $row.find('td:nth-child(2)').text().toLowerCase();
                
                var showRow = true;
                
                // Post-Typ-Filter
                if (postType && rowPostType !== postType) {
                    showRow = false;
                }
                
                // SEO-Status-Filter
                if (seoStatus && rowSeoStatus !== seoStatus) {
                    showRow = false;
                }
                
                // Suchfilter
                if (search && !rowTitle.includes(search)) {
                    showRow = false;
                }
                
                $row.toggle(showRow);
            });
            
            // "Keine Beiträge gefunden" anzeigen/ausblenden
            var visibleRows = $('#alenseo-posts-list tr:visible').length;
            if (visibleRows === 0) {
                if ($('#alenseo-no-posts').length === 0) {
                    $('#alenseo-posts-list').append(
                        '<tr id="alenseo-no-posts"><td colspan="6">' + 
                        alenseoData.i18n.noPostsFound + 
                        '</td></tr>'
                    );
                }
            } else {
                $('#alenseo-no-posts').remove();
            }
            
            // Checkbox-Status aktualisieren
            BatchFilters.updateSelectAllCheckbox();
        },
        
        /**
         * Alle Checkboxen umschalten
         */
        toggleAllCheckboxes: function() {
            var isChecked = $(this).prop('checked');
            $('.alenseo-post-checkbox:visible').prop('checked', isChecked);
        },
        
        /**
         * Select-All-Checkbox aktualisieren
         */
        updateSelectAllCheckbox: function() {
            var $visibleCheckboxes = $('.alenseo-post-checkbox:visible');
            var $checkedCheckboxes = $visibleCheckboxes.filter(':checked');
            
            $('#alenseo-select-all').prop('checked', 
                $visibleCheckboxes.length > 0 && 
                $visibleCheckboxes.length === $checkedCheckboxes.length
            );
        },
        
        /**
         * Debounce-Funktion für Suchfilter
         */
        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this;
                var args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        },
        
        /**
         * Gefilterte Beiträge als CSV exportieren
         */
        exportToCSV: function(e) {
            e.preventDefault();
            
            var csv = [];
            var headers = [
                'ID',
                'Titel',
                'Typ',
                'SEO-Score',
                'Status',
                'Letzte Analyse',
                'Hauptkeyword',
                'Meta-Beschreibung'
            ];
            csv.push(headers.join(','));
            
            $('#alenseo-posts-list tr:visible').each(function() {
                var $row = $(this);
                var postId = $row.find('.alenseo-post-checkbox').val();
                var title = $row.find('td:nth-child(2)').text().trim();
                var type = $row.find('td:nth-child(3)').text().trim();
                var score = $row.find('.alenseo-score-text').text().trim();
                var status = $row.find('.alenseo-status').text().trim();
                var lastAnalysis = $row.find('td:nth-child(6)').text().trim();
                
                // Zusätzliche Metadaten abrufen
                var mainKeyword = $row.data('main-keyword') || '';
                var metaDescription = $row.data('meta-description') || '';
                
                // CSV-Zeile erstellen
                var row = [
                    postId,
                    '"' + title.replace(/"/g, '""') + '"',
                    '"' + type.replace(/"/g, '""') + '"',
                    score,
                    '"' + status.replace(/"/g, '""') + '"',
                    '"' + lastAnalysis.replace(/"/g, '""') + '"',
                    '"' + mainKeyword.replace(/"/g, '""') + '"',
                    '"' + metaDescription.replace(/"/g, '""') + '"'
                ];
                
                csv.push(row.join(','));
            });
            
            // CSV-Datei herunterladen
            var csvContent = csv.join('\n');
            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'alenseo-seo-analyse-' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        
        /**
         * Tabelle sortieren
         */
        sortTable: function() {
            var $header = $(this);
            var column = $header.data('column');
            var direction = $header.data('direction') === 'asc' ? 'desc' : 'asc';
            
            // Sortierrichtung aktualisieren
            $('.alenseo-sortable').removeClass('asc desc');
            $header.addClass(direction).data('direction', direction);
            
            // Sortierindikator aktualisieren
            $('.alenseo-sort-indicator').remove();
            $header.append('<span class="alenseo-sort-indicator dashicons dashicons-arrow-' + 
                (direction === 'asc' ? 'up' : 'down') + '"></span>');
            
            // Tabelle sortieren
            var $tbody = $('#alenseo-posts-list');
            var rows = $tbody.find('tr').get();
            
            rows.sort(function(a, b) {
                var aVal = $(a).find('td[data-column="' + column + '"]').text().trim();
                var bVal = $(b).find('td[data-column="' + column + '"]').text().trim();
                
                // Numerische Werte
                if (column === 'seo-score') {
                    aVal = parseFloat(aVal) || 0;
                    bVal = parseFloat(bVal) || 0;
                }
                // Datumswerte
                else if (column === 'last-analysis') {
                    aVal = aVal === 'Nie' ? 0 : new Date(aVal).getTime();
                    bVal = bVal === 'Nie' ? 0 : new Date(bVal).getTime();
                }
                
                if (direction === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });
            
            $.each(rows, function(index, row) {
                $tbody.append(row);
            });
        },
        
        /**
         * Bulk-Apply-Button aktivieren/deaktivieren
         */
        toggleBulkApply: function() {
            var action = $(this).val();
            var hasChecked = $('.alenseo-post-checkbox:checked').length > 0;
            $('#alenseo-bulk-apply').prop('disabled', !action || !hasChecked);
        },
        
        /**
         * Bulk-Apply-Button bei Checkbox-Änderungen aktualisieren
         */
        updateBulkApply: function() {
            var action = $('#alenseo-bulk-action').val();
            var hasChecked = $('.alenseo-post-checkbox:checked').length > 0;
            $('#alenseo-bulk-apply').prop('disabled', !action || !hasChecked);
        },
        
        /**
         * Massen-Aktion ausführen
         */
        applyBulkAction: function(e) {
            e.preventDefault();
            
            var action = $('#alenseo-bulk-action').val();
            var postIds = [];
            
            $('.alenseo-post-checkbox:checked').each(function() {
                postIds.push($(this).val());
            });
            
            if (postIds.length === 0) {
                BatchFilters.showError(alenseoData.i18n.noPostsSelected);
                return;
            }
            
            switch (action) {
                case 'analyze':
                    BatchFilters.startBatchAnalysis(postIds);
                    break;
                    
                case 'export':
                    BatchFilters.exportSelectedToCSV(postIds);
                    break;
                    
                case 'clear':
                    if (confirm(alenseoData.i18n.confirmClearData)) {
                        BatchFilters.clearSeoData(postIds);
                    }
                    break;
            }
            
            // Bulk-Action zurücksetzen
            $('#alenseo-bulk-action').val('');
            $('#alenseo-bulk-apply').prop('disabled', true);
        },
        
        /**
         * Ausgewählte Beiträge als CSV exportieren
         */
        exportSelectedToCSV: function(postIds) {
            var csv = [];
            var headers = [
                'ID',
                'Titel',
                'Typ',
                'SEO-Score',
                'Status',
                'Letzte Analyse',
                'Hauptkeyword',
                'Meta-Beschreibung'
            ];
            csv.push(headers.join(','));
            
            postIds.forEach(function(postId) {
                var $row = $('#alenseo-posts-list tr[data-post-id="' + postId + '"]');
                var title = $row.find('td[data-column="title"]').text().trim();
                var type = $row.find('td[data-column="type"]').text().trim();
                var score = $row.find('.alenseo-score-text').text().trim();
                var status = $row.find('.alenseo-status').text().trim();
                var lastAnalysis = $row.find('td[data-column="last-analysis"]').text().trim();
                var mainKeyword = $row.data('main-keyword') || '';
                var metaDescription = $row.data('meta-description') || '';
                
                var row = [
                    postId,
                    '"' + title.replace(/"/g, '""') + '"',
                    '"' + type.replace(/"/g, '""') + '"',
                    score,
                    '"' + status.replace(/"/g, '""') + '"',
                    '"' + lastAnalysis.replace(/"/g, '""') + '"',
                    '"' + mainKeyword.replace(/"/g, '""') + '"',
                    '"' + metaDescription.replace(/"/g, '""') + '"'
                ];
                
                csv.push(row.join(','));
            });
            
            // CSV-Datei herunterladen
            var csvContent = csv.join('\n');
            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'alenseo-seo-analyse-selected-' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        
        /**
         * SEO-Daten für ausgewählte Beiträge löschen
         */
        clearSeoData: function(postIds) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_clear_seo_data',
                    post_ids: postIds,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Tabelle aktualisieren
                        postIds.forEach(function(postId) {
                            var $row = $('#alenseo-posts-list tr[data-post-id="' + postId + '"]');
                            $row.find('.alenseo-score-bar').html('-');
                            $row.find('.alenseo-status').text(alenseoData.i18n.notAnalyzed).removeClass().addClass('alenseo-status');
                            $row.find('td[data-column="last-analysis"]').text(alenseoData.i18n.never);
                            $row.data('seo-status', '');
                        });
                        
                        // Erfolgsmeldung anzeigen
                        BatchFilters.showSuccess(alenseoData.i18n.dataCleared);
                    } else {
                        BatchFilters.showError(response.data);
                    }
                },
                error: function() {
                    BatchFilters.showError(alenseoData.i18n.ajaxError);
                }
            });
        },
        
        /**
         * Erfolgsmeldung anzeigen
         */
        showSuccess: function(message) {
            var $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
            $('.alenseo-batch-analyzer').prepend($notice);
            
            // Automatisch ausblenden nach 3 Sekunden
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        /**
         * Fehlermeldung anzeigen
         */
        showError: function(message) {
            var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
            $('.alenseo-batch-analyzer').prepend($notice);
            
            // Automatisch ausblenden nach 3 Sekunden
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Initialisierung wenn DOM bereit
    $(document).ready(function() {
        BatchFilters.init();
    });
    
})(jQuery); 