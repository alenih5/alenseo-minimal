/**
 * Batch-Analyzer für Alenseo SEO
 * 
 * @package    Alenseo
 * @subpackage Alenseo/assets/js
 */

(function($) {
    'use strict';
    
    // Batch-Analyzer Klasse
    var BatchAnalyzer = {
        /**
         * Initialisierung
         */
        init: function() {
            this.bindEvents();
            this.initializeUI();
        },
        
        /**
         * Event-Handler binden
         */
        bindEvents: function() {
            $('#alenseo-batch-analyze').on('click', this.startBatchAnalysis);
            $('#alenseo-batch-cancel').on('click', this.cancelBatchAnalysis);
        },
        
        /**
         * UI initialisieren
         */
        initializeUI: function() {
            // Progress-Bar initialisieren
            this.progressBar = $('#alenseo-batch-progress');
            this.progressBar.progressbar({
                value: 0
            });
            
            // Status-Container initialisieren
            this.statusContainer = $('#alenseo-batch-status');
        },
        
        /**
         * Batch-Analyse starten
         */
        startBatchAnalysis: function(e) {
            e.preventDefault();
            
            // Button deaktivieren
            $('#alenseo-batch-analyze').prop('disabled', true);
            
            // Status zurücksetzen
            BatchAnalyzer.resetStatus();
            
            // Post-IDs sammeln
            var postIds = [];
            $('.alenseo-post-checkbox:checked').each(function() {
                postIds.push($(this).val());
            });
            
            if (postIds.length === 0) {
                BatchAnalyzer.showError('Bitte wählen Sie mindestens einen Beitrag aus.');
                $('#alenseo-batch-analyze').prop('disabled', false);
                return;
            }
            
            // Batch-Analyse starten
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_batch_analyze',
                    post_ids: postIds,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BatchAnalyzer.batchId = response.data.batch_id;
                        BatchAnalyzer.startStatusCheck();
                    } else {
                        BatchAnalyzer.showError(response.data);
                    }
                },
                error: function() {
                    BatchAnalyzer.showError('Fehler bei der Verbindung zum Server.');
                }
            });
        },
        
        /**
         * Batch-Analyse abbrechen
         */
        cancelBatchAnalysis: function(e) {
            e.preventDefault();
            
            if (BatchAnalyzer.batchId) {
                // Status zurücksetzen
                BatchAnalyzer.resetStatus();
                
                // Button aktivieren
                $('#alenseo-batch-analyze').prop('disabled', false);
            }
        },
        
        /**
         * Status-Überprüfung starten
         */
        startStatusCheck: function() {
            this.statusCheckInterval = setInterval(this.checkStatus, 2000);
        },
        
        /**
         * Status überprüfen
         */
        checkStatus: function() {
            if (!BatchAnalyzer.batchId) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'alenseo_batch_status',
                    batch_id: BatchAnalyzer.batchId,
                    nonce: alenseoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BatchAnalyzer.updateStatus(response.data);
                        
                        // Wenn Analyse abgeschlossen, Intervall stoppen
                        if (response.data.status === 'completed') {
                            clearInterval(BatchAnalyzer.statusCheckInterval);
                            $('#alenseo-batch-analyze').prop('disabled', false);
                        }
                    } else {
                        BatchAnalyzer.showError(response.data);
                        clearInterval(BatchAnalyzer.statusCheckInterval);
                        $('#alenseo-batch-analyze').prop('disabled', false);
                    }
                },
                error: function() {
                    BatchAnalyzer.showError('Fehler bei der Verbindung zum Server.');
                    clearInterval(BatchAnalyzer.statusCheckInterval);
                    $('#alenseo-batch-analyze').prop('disabled', false);
                }
            });
        },
        
        /**
         * Status aktualisieren
         */
        updateStatus: function(data) {
            // Progress-Bar aktualisieren
            var progress = (data.processed / data.total) * 100;
            this.progressBar.progressbar('value', progress);
            
            // Status-Text aktualisieren
            var statusText = 'Verarbeitet: ' + data.processed + ' von ' + data.total + ' Beiträgen';
            statusText += ' (Erfolgreich: ' + data.success + ', Fehler: ' + data.failed + ')';
            
            if (data.status === 'completed') {
                statusText += ' - Analyse abgeschlossen';
            }
            
            this.statusContainer.html(statusText);
            
            // Fehler anzeigen, falls vorhanden
            if (data.errors && data.errors.length > 0) {
                var errorHtml = '<div class="alenseo-batch-errors">';
                errorHtml += '<h4>Fehler:</h4><ul>';
                
                data.errors.forEach(function(error) {
                    errorHtml += '<li>Beitrag ' + error.post_id + ': ' + error.error + '</li>';
                });
                
                errorHtml += '</ul></div>';
                this.statusContainer.append(errorHtml);
            }
        },
        
        /**
         * Status zurücksetzen
         */
        resetStatus: function() {
            this.batchId = null;
            this.progressBar.progressbar('value', 0);
            this.statusContainer.html('');
            clearInterval(this.statusCheckInterval);
        },
        
        /**
         * Fehler anzeigen
         */
        showError: function(message) {
            this.statusContainer.html('<div class="alenseo-error">' + message + '</div>');
        }
    };
    
    // Initialisierung wenn DOM bereit
    $(document).ready(function() {
        BatchAnalyzer.init();
    });
    
})(jQuery); 