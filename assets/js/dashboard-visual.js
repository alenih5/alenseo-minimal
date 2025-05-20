/**
 * Dashboard Visual JavaScript für Alenseo SEO
 * Enthält Funktionen für visuelle Darstellungen und Diagramme
 */

jQuery(document).ready(function($) {
    'use strict';
    
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
            var color;
            
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
        
        // Post-Typen Verteilung (Bar-Chart)
        if ($('#postTypesChart').length) {
            var typeLabels = [];
            var typeData = [];
            var typeColors = [];
            
            $('.post-type-data').each(function() {
                typeLabels.push($(this).data('label'));
                typeData.push($(this).data('count'));
                typeColors.push(getRandomColor());
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
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = function() {
            initCharts();
        };
        document.head.appendChild(script);
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
});