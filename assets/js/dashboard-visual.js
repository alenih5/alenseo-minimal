/**
 * Dashboard Visual JavaScript für Alenseo SEO
 * Enthält Funktionen für visuelle Darstellungen und Diagramme
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialisierung aller Komponenten
    initAllComponents();
    
    // Event-Handler für Filter und Sortierung
    initFiltersAndSorting();
    
    // Echtzeit-Updates einrichten
    setupRealTimeUpdates();
    
    function initAllComponents() {
        // Bestehende Initialisierungen
        initCircleProgress();
        initProgressBars();
        
        // Neue erweiterte Visualisierungen
        initAdvancedCharts();
        initHeatmap();
        initKeywordCloud();
        
        // Interaktive Elemente
        initInteractiveElements();
    }
    
    function initAdvancedCharts() {
        if (typeof Chart !== 'undefined') {
            initCharts();
            initPerformanceChart();
            initKeywordDistributionChart();
        } else {
            loadChartJs().then(() => {
                initCharts();
                initPerformanceChart();
                initKeywordDistributionChart();
            });
        }
    }
    
    function initPerformanceChart() {
        if ($('#performanceChart').length) {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: performanceData.labels,
                    datasets: [{
                        label: 'Ladezeit',
                        data: performanceData.loadTimes,
                        borderColor: '#3498db',
                        tension: 0.4
                    }, {
                        label: 'SEO-Score',
                        data: performanceData.seoScores,
                        borderColor: '#2ecc71',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y}`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
    
    function initKeywordDistributionChart() {
        if ($('#keywordDistributionChart').length) {
            const ctx = document.getElementById('keywordDistributionChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: keywordData.labels,
                    datasets: [{
                        label: 'Keyword-Verteilung',
                        data: keywordData.values,
                        backgroundColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }
    
    function initHeatmap() {
        if ($('#contentHeatmap').length) {
            const heatmapData = {
                max: 100,
                data: heatmapData.points
            };
            
            const heatmap = h337.create({
                container: document.getElementById('contentHeatmap'),
                radius: 50,
                maxOpacity: 0.6
            });
            
            heatmap.setData(heatmapData);
        }
    }
    
    function initKeywordCloud() {
        if ($('#keywordCloud').length) {
            WordCloud(document.getElementById('keywordCloud'), {
                list: keywordCloudData,
                gridSize: 16,
                weightFactor: 10,
                fontFamily: 'Arial',
                color: '#3498db',
                hover: window.drawBox,
                click: function(item) {
                    showKeywordDetails(item[0]);
                }
            });
        }
    }
    
    function initFiltersAndSorting() {
        // Filter-Event-Handler
        $('.filter-select').on('change', function() {
            applyFilters();
        });
        
        // Sortierung-Event-Handler
        $('.sort-header').on('click', function() {
            const column = $(this).data('column');
            const direction = $(this).data('direction') === 'asc' ? 'desc' : 'asc';
            sortTable(column, direction);
        });
    }
    
    function applyFilters() {
        const filters = {
            status: $('#status-filter').val(),
            score: $('#score-filter').val(),
            date: $('#date-filter').val()
        };
        
        $.ajax({
            url: alenseoData.ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_filter_content',
                filters: filters,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateContentDisplay(response.data);
                }
            }
        });
    }
    
    function sortTable(column, direction) {
        const $table = $('.content-table');
        const $rows = $table.find('tbody tr').toArray();
        
        $rows.sort((a, b) => {
            const aVal = $(a).find(`[data-column="${column}"]`).text();
            const bVal = $(b).find(`[data-column="${column}"]`).text();
            
            if (direction === 'asc') {
                return aVal.localeCompare(bVal);
            } else {
                return bVal.localeCompare(aVal);
            }
        });
        
        $table.find('tbody').append($rows);
    }
    
    function setupRealTimeUpdates() {
        // WebSocket-Verbindung für Echtzeit-Updates
        const ws = new WebSocket(alenseoData.wsUrl);
        
        ws.onmessage = function(event) {
            const data = JSON.parse(event.data);
            handleRealTimeUpdate(data);
        };
        
        // Fallback: Polling für Updates
        setInterval(checkForUpdates, 30000);
    }
    
    function handleRealTimeUpdate(data) {
        switch(data.type) {
            case 'score_update':
                updateScoreDisplay(data.postId, data.score);
                break;
            case 'keyword_update':
                updateKeywordDisplay(data.postId, data.keywords);
                break;
            case 'status_update':
                updateStatusDisplay(data.postId, data.status);
                break;
        }
    }
    
    function checkForUpdates() {
        $.ajax({
            url: alenseoData.ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_check_updates',
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    handleRealTimeUpdate(response.data);
                }
            }
        });
    }
    
    // Kreisdiagramm für die Gesamtpunktzahl initialisieren
    initCircleProgress();
    
    // Fortschrittsbalken für API-Nutzung initialisieren
    initProgressBars();
    
    // Charts initialisieren wenn Chart.js verfügbar ist
    if (typeof Chart !== 'undefined') {
        initCharts();
    } else {
        // Chart.js laden wenn nicht vorhanden
        loadChartJs();
    }
    
    // Event-Handler für die Bulk-Aktionen
    $('#doaction').on('click', function(e) {
        e.preventDefault();
        
        var selectedAction = $('#bulk-action-selector-top').val();
        if (selectedAction === '-1') {
            alert(alenseoData.messages.selectAction);
            return;
        }
        
        var selectedPosts = $('input[name="post[]"]:checked');
        if (selectedPosts.length === 0) {
            alert(alenseoData.messages.selectContent);
            return;
        }
        
        if (selectedAction === 'analyze') {
            bulkAnalyze(selectedPosts);
        }
    });
    
    /**
     * Kreisdiagramme für alle Statistiken initialisieren
     */
    function initCircleProgress() {
        $('.circle-progress').each(function() {
            var $this = $(this);
            var score = parseInt($this.data('score'));
            var color = '#3498db'; // Default: Blau
            
            // Farbe basierend auf Score setzen
            if (score >= 80) {
                color = '#2ecc71'; // Grün
            } else if (score >= 50) {
                color = '#f1c40f'; // Gelb
            } else if (score > 0) {
                color = '#e74c3c'; // Rot
            }
            
            // Farbauswahl basierend auf dem Boxtyp und Score
            if ($this.closest('.box-score').length) {
                color = score >= 70 ? '#46b450' : (score >= 50 ? '#ffb900' : '#dc3232');
            } else if ($this.closest('.box-optimized').length) {
                color = '#00a0d2'; // Blau für optimierte Seiten
            } else if ($this.closest('.box-improve').length) {
                color = '#ffb900'; // Gelb für zu verbessernde Seiten
            } else if ($this.closest('.box-no-keywords').length) {
                color = '#dc3232'; // Rot für Seiten ohne Keywords
            } else {
                color = score >= 70 ? '#46b450' : (score >= 50 ? '#ffb900' : '#dc3232');
            }
            
            // Kreisdiagramm zeichnen
            var angle = score / 100 * 360;
            var $rightFill = $this.find('.progress-fill-right');
            var $leftFill = $this.find('.progress-fill-left');
            
            if (angle <= 180) {
                $rightFill.css('transform', 'rotate(' + angle + 'deg)');
                $rightFill.css('background-color', color);
            } else {
                $rightFill.css('transform', 'rotate(180deg)');
                $rightFill.css('background-color', color);
                $leftFill.css('transform', 'rotate(' + (angle - 180) + 'deg)');
                $leftFill.css('background-color', color);
            }
            
            // Nummer im Kreis animieren
            var $countElement = $this.find('.progress-center');
            var originalValue = parseInt($countElement.text());
            
            $({ Counter: 0 }).animate({ Counter: originalValue }, {
                duration: 1500,
                easing: 'swing',
                step: function() {
                    $countElement.text(Math.round(this.Counter));
                }
            });
        });
    }
    
    /**
     * Fortschrittsbalken für API-Nutzung initialisieren
     */
    function initProgressBars() {
        $('.api-progress-bar').each(function() {
            var percent = $(this).data('percent');
            var color = percent >= 80 ? '#dc3232' : (percent >= 60 ? '#ffb900' : '#46b450');
            
            $(this).find('.api-progress-fill')
                .css('background-color', color)
                .css('width', percent + '%')
                .addClass('animate-fill');
        });
    }
    
    /**
     * Charts mit Chart.js initialisieren
     */
    function initCharts() {
        // Status-Verteilung (Donut-Chart)
        if ($('#statusChart').length) {
            var statusCtx = document.getElementById('statusChart').getContext('2d');
            var statusData = {
                datasets: [{
                    data: [
                        parseInt($('#optimized-count').val()) || 0,
                        parseInt($('#improve-count').val()) || 0,
                        parseInt($('#no-keywords-count').val()) || 0
                    ],
                    backgroundColor: [
                        '#46b450',
                        '#ffb900',
                        '#dc3232'
                    ]
                }],
                labels: [
                    'Optimiert',
                    'Zu verbessern',
                    'Ohne Keywords'
                ]
            };
            
            new Chart(statusCtx, {
                type: 'doughnut',
                data: statusData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // SEO-Score-Trend (Line-Chart)
        if ($('#seoTrendChart').length) {
            var trendLabels = [];
            var trendData = [];
            
            $('.trend-data').each(function() {
                trendLabels.push($(this).data('date'));
                trendData.push($(this).data('score'));
            });
            
            // Sicherstellen, dass die Daten korrekt für das Liniendiagramm sortiert sind
            var combinedData = [];
            for (var i = 0; i < trendLabels.length; i++) {
                combinedData.push({
                    date: trendLabels[i],
                    score: trendData[i]
                });
            }
            
            // Nach Datum sortieren
            combinedData.sort(function(a, b) {
                return new Date(a.date) - new Date(b.date);
            });
            
            // Sortierte Daten zurück in die Arrays
            trendLabels = [];
            trendData = [];
            for (var i = 0; i < combinedData.length; i++) {
                trendLabels.push(combinedData[i].date);
                trendData.push(combinedData[i].score);
            }
            
            var trendCtx = document.getElementById('seoTrendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'SEO-Score',
                        data: trendData,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Score'
                            }
                        }
                    }
                }
            });
        }
        
        // Post-Typen Verteilung (Bar-Chart)
        if ($('#postTypesChart').length) {
            var typeLabels = [];
            var typeData = [];
            var typeColors = [
                '#3498db',
                '#9b59b6',
                '#2ecc71',
                '#f1c40f',
                '#e74c3c',
                '#1abc9c',
                '#34495e'
            ];
            
            $('.post-type-data').each(function(index) {
                typeLabels.push($(this).data('label'));
                typeData.push($(this).data('count'));
                // Verwende die vordefinierten Farben oder generiere zufällige, wenn mehr als vorhandene benötigt werden
                if (index >= typeColors.length) {
                    typeColors.push(getRandomColor());
                }
            });
            
            var typeCtx = document.getElementById('postTypesChart').getContext('2d');
            new Chart(typeCtx, {
                type: 'bar',
                data: {
                    labels: typeLabels,
                    datasets: [{
                        label: 'Anzahl',
                        data: typeData,
                        backgroundColor: typeColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }
                }
            });
        }
    }
    
    /**
     * Chart.js dynamisch laden
     */
    function loadChartJs() {
        return new Promise((resolve, reject) => {
            if (typeof Chart !== 'undefined') {
                resolve();
                return;
            }
            
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = function() {
                resolve();
            };
            script.onerror = function() {
                reject(new Error('Failed to load Chart.js'));
            };
            document.head.appendChild(script);
        });
    }
    
    /**
     * Zufällige Farbe für Charts generieren
     */
    function getRandomColor() {
        var letters = '0123456789ABCDEF';
        var color = '#';
        for (var i = 0; i < 6; i++) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    }
    
    /**
     * Bulk-Analyse durchführen
     */
    function bulkAnalyze(selectedPosts) {
        var totalPosts = selectedPosts.length;
        var processedPosts = 0;
        var successCount = 0;
        var errorCount = 0;
        
        // Progress-Bar erstellen
        var progressHtml = '<div class="alenseo-bulk-progress">' +
                          '<div class="progress-text">0 / ' + totalPosts + ' ' + alenseoData.messages.analyzing + '</div>' +
                          '<div class="progress-bar-container"><div class="progress-bar"></div></div>' +
                          '</div>';
        
        $('.alenseo-content-list').prepend(progressHtml);
        
        // Posts nacheinander analysieren
        processNextPost(selectedPosts, 0, totalPosts, processedPosts, successCount, errorCount);
    }
    
    /**
     * Nächsten Beitrag in der Warteschlange verarbeiten
     */
    function processNextPost(selectedPosts, currentIndex, totalPosts, processedPosts, successCount, errorCount) {
        if (currentIndex >= selectedPosts.length) {
            // Alle Beiträge verarbeitet
            $('.alenseo-bulk-progress .progress-text').text(
                alenseoData.messages.allDone + ' (' + 
                successCount + ' erfolgreich, ' + 
                errorCount + ' fehlgeschlagen)'
            );
            
            // Nach kurzer Verzögerung Seite neu laden
            setTimeout(function() {
                window.location.reload();
            }, 3000);
            
            return;
        }
        
        var postId = selectedPosts.eq(currentIndex).val();
        
        // AJAX-Analyse für aktuellen Beitrag
        $.ajax({
            url: alenseoData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_analyze_content',
                post_id: postId,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                processedPosts++;
                if (response.success) {
                    successCount++;
                } else {
                    errorCount++;
                    console.error('Fehler bei Post ID ' + postId + ':', response.data);
                }
                
                // Fortschritt aktualisieren
                var percentComplete = Math.round((processedPosts / totalPosts) * 100);
                $('.alenseo-bulk-progress .progress-bar').css('width', percentComplete + '%');
                $('.alenseo-bulk-progress .progress-text').text(
                    processedPosts + ' / ' + totalPosts + ' ' + alenseoData.messages.analyzing
                );
                
                // Statuszeile für bearbeiteten Post aktualisieren
                var statusClass = response.success ? 'optimized' : 'to-improve';
                $('#post-' + postId + ' .status-col').html(
                    '<span class="status-badge ' + statusClass + '">' + 
                    (response.success ? 'Optimiert' : 'Zu verbessern') + 
                    '</span>'
                );
                
                // Nächsten Post verarbeiten
                processNextPost(
                    selectedPosts, 
                    currentIndex + 1, 
                    totalPosts, 
                    processedPosts, 
                    successCount, 
                    errorCount
                );
            },
            error: function() {
                processedPosts++;
                errorCount++;
                
                // Fortschritt aktualisieren
                var percentComplete = Math.round((processedPosts / totalPosts) * 100);
                $('.alenseo-bulk-progress .progress-bar').css('width', percentComplete + '%');
                $('.alenseo-bulk-progress .progress-text').text(
                    processedPosts + ' / ' + totalPosts + ' ' + alenseoData.messages.analyzing
                );
                
                // Nächsten Post verarbeiten
                processNextPost(
                    selectedPosts, 
                    currentIndex + 1, 
                    totalPosts, 
                    processedPosts, 
                    successCount, 
                    errorCount
                );
            }
        });
    }

    // API-Status aktualisieren
    function updateApiStatus() {
        $.ajax({
            url: alenseoData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_get_api_status',
                security: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data;
                    updateApiStatusUI(status);
                }
            }
        });
    }

    // API-Status UI aktualisieren
    function updateApiStatusUI(status) {
        const $statusIndicator = $('#alenseo-api-status');
        const $statusMessage = $('#alenseo-api-message');
        
        if (!$statusIndicator.length) {
            return;
        }

        // Status-Indikator aktualisieren
        $statusIndicator.removeClass('alenseo-status-valid alenseo-status-invalid alenseo-status-unknown')
            .addClass(status.valid ? 'alenseo-status-valid' : 
                     status.configured ? 'alenseo-status-invalid' : 'alenseo-status-unknown');

        // Status-Nachricht aktualisieren
        if ($statusMessage.length) {
            $statusMessage.text(status.message);
        }

        // Analyse-Button Status aktualisieren
        const $analyzeButtons = $('.alenseo-analyze-button');
        if (typeof alenseoData !== 'undefined' && alenseoData.isAdmin) {
            $analyzeButtons.prop('disabled', false).attr('title', '');
        } else {
            $analyzeButtons.prop('disabled', !status.valid)
                .attr('title', status.valid ? '' : status.message);
        }
    }

    // Analyse-Button Click-Handler
    $('.alenseo-analyze-button').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const postId = $button.data('post-id');
        
        if ($button.prop('disabled')) {
            return;
        }

        // Button deaktivieren und Loading-Status anzeigen
        $button.prop('disabled', true)
            .addClass('alenseo-loading')
            .html('<span class="spinner is-active"></span> ' + alenseoData.messages.analyzing);

        // Analyse starten
        $.ajax({
            url: alenseoData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_analyze_post',
                post_id: postId,
                security: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    // UI aktualisieren
                    updatePostUI(postId, response.data);
                } else {
                    showError(response.data.message);
                }
            },
            error: function() {
                showError(alenseoData.messages.error);
            },
            complete: function() {
                // Button-Status zurücksetzen
                $button.prop('disabled', false)
                    .removeClass('alenseo-loading')
                    .text(alenseoData.messages.analyze);
            }
        });
    });

    // Erfolgsmeldung anzeigen
    function showSuccess(message) {
        const $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
        $('.alenseo-notices').append($notice);
        
        // Automatisch ausblenden nach 5 Sekunden
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Fehlermeldung anzeigen
    function showError(message) {
        const $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
        $('.alenseo-notices').append($notice);
        
        // Automatisch ausblenden nach 5 Sekunden
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Post-UI aktualisieren
    function updatePostUI(postId, data) {
        const $row = $('tr[data-post-id="' + postId + '"]');
        if (!$row.length) {
            return;
        }

        // Score aktualisieren
        $row.find('.alenseo-score').text(data.score);

        // Status aktualisieren
        const $status = $row.find('.alenseo-status');
        $status.removeClass('alenseo-status-good alenseo-status-ok alenseo-status-poor')
            .addClass('alenseo-status-' + data.status)
            .text(data.status_text);

        // Letzte Analyse aktualisieren
        $row.find('.alenseo-last-analysis').text(data.last_analysis);
    }

    // Initial API-Status laden
    updateApiStatus();

    // API-Status alle 5 Minuten aktualisieren
    setInterval(updateApiStatus, 300000);

    // Externe Links im Dashboard immer in neuem Tab öffnen
    $(document).on('click', 'a[data-external]', function(e) {
        e.preventDefault();
        window.open($(this).attr('href'), '_blank');
    });
    // Interne Admin-Links korrekt behandeln
    $(document).on('click', 'a[data-admin-link]', function(e) {
        e.preventDefault();
        window.location.href = $(this).attr('href');
    });
});