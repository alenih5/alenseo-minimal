/**
 * Alenseo SEO - Integrierte Admin.js
 * 
 * Kombiniert deine bestehenden Features mit der neuen erweiterten API
 * Behält alle deine UI-Funktionen bei und fügt moderne API-Features hinzu
 */

(function($) {
    'use strict';

    // Basis-Konfiguration (erweitert)
    const CONFIG = {
        ajaxUrl: ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: window.alenseo_ajax_nonce || window.alenseoData?.nonce || '',
        debug: window.alenseo_debug || false,
        timeout: 30000,
        retryAttempts: 2,
        rateLimitDelay: 500
    };

    /**
     * Moderne API-Klasse (aus meiner neuen Version)
     */
    class AlenseoAPI {
        constructor() {
            this.lastRequestTime = 0;
            this.requestQueue = [];
            this.activeRequests = new Map();
        }

        async enforceRateLimit() {
            const now = Date.now();
            const timeSinceLastRequest = now - this.lastRequestTime;
            
            if (timeSinceLastRequest < CONFIG.rateLimitDelay) {
                const delay = CONFIG.rateLimitDelay - timeSinceLastRequest;
                return new Promise(resolve => setTimeout(resolve, delay));
            }
            
            this.lastRequestTime = now;
            return Promise.resolve();
        }

        async makeRequest(action, data = {}, options = {}) {
            const defaults = {
                timeout: CONFIG.timeout,
                showProgress: true,
                showNotifications: true,
                useCache: false,
                retryAttempts: CONFIG.retryAttempts
            };

            options = Object.assign(defaults, options);
            await this.enforceRateLimit();

            const requestData = {
                action: `alenseo_${action}`,
                nonce: CONFIG.nonce,
                ...data
            };

            if (CONFIG.debug) {
                console.log(`[Alenseo] Making request: ${action}`, requestData);
            }

            return new Promise((resolve, reject) => {
                $.ajax({
                    url: CONFIG.ajaxUrl,
                    type: 'POST',
                    data: requestData,
                    timeout: options.timeout,
                    success: (response) => {
                        if (response.success) {
                            if (CONFIG.debug && response.data.execution_time) {
                                console.log(`[Alenseo] ${action} completed in ${response.data.execution_time}`);
                            }
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data?.message || 'Unbekannter Fehler'));
                        }
                    },
                    error: (xhr, status, error) => {
                        if (CONFIG.debug) {
                            console.error(`[Alenseo] Request failed: ${action}`, error);
                        }
                        reject(new Error(error || 'Netzwerkfehler'));
                    }
                });
            });
        }

        // Moderne API-Methoden
        generateKeywords(postId, options = {}) {
            return this.makeRequest('generate_keywords', {
                post_id: postId,
                prefer_speed: options.preferSpeed || false
            }, options);
        }

        generateMetaTitle(content, keyword = '', options = {}) {
            return this.makeRequest('generate_meta_title', {
                content: content,
                keyword: keyword,
                prefer_speed: options.preferSpeed || false
            }, options);
        }

        optimizeMetaDescription(postId, keyword = '', options = {}) {
            return this.makeRequest('optimize_meta_description', {
                post_id: postId,
                keyword: keyword
            }, options);
        }

        optimizeContent(postId, content, keyword = '', options = {}) {
            return this.makeRequest('optimize_content', {
                post_id: postId,
                content: content,
                keyword: keyword,
                prefer_quality: options.preferQuality || false
            }, options);
        }

        testApi(provider = 'claude', apiKey = '', model = '') {
            return this.makeRequest(`test_${provider}_api`, {
                api_key: apiKey,
                model: model
            });
        }

        getApiStatus() {
            return this.makeRequest('get_api_status', {}, {
                showProgress: false,
                useCache: true
            });
        }
    }

    // Global verfügbare API-Instanz
    window.Alenseo = window.Alenseo || {};
    window.Alenseo.api = new AlenseoAPI();

    // jQuery Ready - Alle deine bestehenden Features + Erweiterungen
    jQuery(document).ready(function($) {
        
        // === DEINE BESTEHENDEN FEATURES (erweitert) ===
        
        // Tab-Funktionalität für die Einstellungsseite
        $('.alenseo-settings-tab').on('click', function() {
            var tab = $(this).data('tab');
            
            // Aktiven Tab setzen
            $('.alenseo-settings-tab').removeClass('active');
            $(this).addClass('active');
            
            // Aktiven Inhalt setzen
            $('.alenseo-settings-section').removeClass('active');
            $('#alenseo-tab-' + tab).addClass('active');
        });
        
        // Notices ausblenden
        $('.alenseo-notice.is-dismissible').on('click', '.notice-dismiss', function() {
            $(this).parent().slideUp();
        });
        
        // Score-Kreis Animation (erweitert)
        $('.alenseo-stat-circle').each(function() {
            var percentage = $(this).data('percentage') || 0;
            $(this).css('--percentage', percentage);
            
            // Animiere den Score
            var $this = $(this);
            var start = 0;
            var end = percentage;
            var duration = 1000;
            
            $({ percentage: start }).animate({ percentage: end }, {
                duration: duration,
                easing: 'swing',
                step: function() {
                    $this.css('--percentage', this.percentage);
                    $this.find('.score-text').text(Math.round(this.percentage) + '%');
                }
            });
        });
        
        // Verbesserte Tooltips
        $('.alenseo-tooltip').hover(
            function() {
                var tooltipText = $(this).data('tooltip');
                var $tooltip = $('<div class="alenseo-tooltip-popup">' + tooltipText + '</div>');
                $('body').append($tooltip);
                
                var rect = this.getBoundingClientRect();
                $tooltip.css({
                    top: rect.top - $tooltip.outerHeight() - 10,
                    left: rect.left + (rect.width / 2) - ($tooltip.outerWidth() / 2)
                });
            },
            function() {
                $('.alenseo-tooltip-popup').remove();
            }
        );
        
        // Bestätigungsdialog für kritische Aktionen
        $('.alenseo-confirm-action').on('click', function(e) {
            var message = $(this).data('confirm') || 'Bist du sicher?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
        
        // Bulk-Aktionen (erweitert)
        $('#alenseo-bulk-apply').on('click', function() {
            var action = $('#alenseo-bulk-action').val();
            var selectedItems = $('.alenseo-bulk-check:checked');
            
            if (action === '') {
                alert('Bitte wähle eine Aktion aus.');
                return;
            }
            
            if (selectedItems.length === 0) {
                alert('Bitte wähle mindestens einen Eintrag aus.');
                return;
            }
            
            // Erweiterte Bulk-Aktionen mit neuer API
            if (action === 'optimize_bulk') {
                const postIds = selectedItems.map(function() {
                    return $(this).val();
                }).get();
                
                // Verwende neue API für Bulk-Optimierung
                bulkOptimizePosts(postIds);
                return;
            }
            
            // Standard-Aktion
            if (action === 'delete' && !confirm('Möchtest du ' + selectedItems.length + ' Einträge wirklich löschen?')) {
                return;
            }
            
            $('#alenseo-bulk-form').submit();
        });
        
        // Select All Checkbox
        $('#alenseo-select-all').on('change', function() {
            $('.alenseo-bulk-check').prop('checked', $(this).prop('checked'));
        });
        
        // Einzelne Checkboxen ändern auch Select All Status
        $('.alenseo-bulk-check').on('change', function() {
            var allChecked = $('.alenseo-bulk-check:checked').length === $('.alenseo-bulk-check').length;
            $('#alenseo-select-all').prop('checked', allChecked);
        });
        
        // === API-TESTS (Modernisiert) ===
        
        // Claude API Test (erweitert)
        $('#alenseo-test-api-key, #alenseo-api-test').on('click', function(e) {
            e.preventDefault();
            
            var apiKey = $('#claude_api_key').val();
            var model = $('#claude_model').val() || 'claude-3-5-sonnet-20241022';
            var testButton = $(this);
            var statusContainer = $('#alenseo-api-status-container, #api-test-result');
            
            if (!apiKey) {
                statusContainer.html('<div class="notice notice-error inline"><p>Bitte geben Sie einen API-Schlüssel ein.</p></div>');
                return;
            }
            
            // Button-Status während des Tests
            testButton.prop('disabled', true);
            var originalText = testButton.text();
            testButton.text('Teste API...');
            statusContainer.html('<div class="notice notice-info inline"><p>API wird getestet...</p></div>');
            
            // Verwende neue API
            window.Alenseo.api.testApi('claude', apiKey, model)
                .then(function(response) {
                    statusContainer.html(`
                        <div class="notice notice-success inline">
                            <p><strong>API-Test erfolgreich!</strong></p>
                            <ul>
                                <li>Verfügbare Modelle: ${response.details?.model_count || 'Unbekannt'}</li>
                                <li>Schnellstes Modell: ${response.details?.fastest_model || 'N/A'}</li>
                                <li>Empfohlenes Modell: ${response.details?.recommended_model || 'N/A'}</li>
                            </ul>
                        </div>
                    `);
                    localStorage.setItem('alenseo_api_status', 'active');
                })
                .catch(function(error) {
                    statusContainer.html(`
                        <div class="notice notice-error inline">
                            <p><strong>API-Test fehlgeschlagen:</strong> ${error.message}</p>
                        </div>
                    `);
                })
                .finally(function() {
                    testButton.prop('disabled', false);
                    testButton.text(originalText);
                });
        });
        
        // OpenAI API Test
        $('#alenseo-test-openai-api').on('click', function(e) {
            e.preventDefault();
            
            var apiKey = $('#openai_api_key').val();
            var model = $('#openai_model').val() || 'gpt-3.5-turbo';
            var testButton = $(this);
            var statusContainer = $('#openai-api-status-container');
            
            if (!apiKey) {
                statusContainer.html('<div class="notice notice-error inline"><p>Bitte geben Sie einen OpenAI API-Schlüssel ein.</p></div>');
                return;
            }
            
            testButton.prop('disabled', true);
            var originalText = testButton.text();
            testButton.text('Teste OpenAI API...');
            statusContainer.html('<div class="notice notice-info inline"><p>OpenAI API wird getestet...</p></div>');
            
            window.Alenseo.api.testApi('openai', apiKey, model)
                .then(function(response) {
                    statusContainer.html(`
                        <div class="notice notice-success inline">
                            <p><strong>OpenAI API-Test erfolgreich!</strong></p>
                            <p>Modell: ${response.details?.model || model}</p>
                        </div>
                    `);
                })
                .catch(function(error) {
                    statusContainer.html(`
                        <div class="notice notice-error inline">
                            <p><strong>OpenAI API-Test fehlgeschlagen:</strong> ${error.message}</p>
                        </div>
                    `);
                })
                .finally(function() {
                    testButton.prop('disabled', false);
                    testButton.text(originalText);
                });
        });
        
        // === ERWEITERTE FUNKTIONEN ===
        
        // Async-Fokus-Keyword-Speicherung (erweitert)
        $('#alenseo_focus_keyword').on('blur', function() {
            var keywordField = $(this);
            var keyword = keywordField.val();
            var postId = $('#post_ID').val();
            
            if (keywordField.data('original-value') === keyword) {
                return;
            }
            
            // Verwende neue API
            window.Alenseo.api.makeRequest('save_keyword', {
                post_id: postId,
                keyword: keyword
            }, {
                showProgress: false,
                showNotifications: false
            })
            .then(function(response) {
                keywordField.data('original-value', keyword);
                
                // Visuelles Feedback
                keywordField.css('border-color', '#46b450');
                setTimeout(function() {
                    keywordField.css('border-color', '');
                }, 1500);
                
                // Trigger event für andere Plugins
                $(document).trigger('alenseo:keyword_saved', {
                    postId: postId,
                    keyword: keyword
                });
            })
            .catch(function(error) {
                keywordField.css('border-color', '#dc3232');
                setTimeout(function() {
                    keywordField.css('border-color', '');
                }, 1500);
            });
        });
        
        // AI-Content-Generierung Buttons
        $('#generate-keywords-ai').on('click', function(e) {
            e.preventDefault();
            
            var postId = $('#post_ID').val();
            var button = $(this);
            
            button.prop('disabled', true).text('Generiere Keywords...');
            
            window.Alenseo.api.generateKeywords(postId, { preferSpeed: true })
                .then(function(response) {
                    if (response.keywords && response.keywords.length > 0) {
                        $('#alenseo_focus_keyword').val(response.keywords[0]);
                        
                        // Zeige alle Keywords als Vorschläge
                        var suggestions = response.keywords.map(kw => 
                            `<span class="keyword-suggestion" data-keyword="${kw}">${kw}</span>`
                        ).join(' ');
                        
                        $('#keyword-suggestions').html(suggestions);
                        $('.keyword-suggestion').on('click', function() {
                            $('#alenseo_focus_keyword').val($(this).data('keyword'));
                        });
                    }
                })
                .catch(function(error) {
                    alert('Fehler bei der Keyword-Generierung: ' + error.message);
                })
                .finally(function() {
                    button.prop('disabled', false).text('Keywords generieren');
                });
        });
        
        $('#optimize-meta-description-ai').on('click', function(e) {
            e.preventDefault();
            
            var postId = $('#post_ID').val();
            var keyword = $('#alenseo_focus_keyword').val();
            var button = $(this);
            
            button.prop('disabled', true).text('Optimiere...');
            
            window.Alenseo.api.optimizeMetaDescription(postId, keyword)
                .then(function(response) {
                    if (response.meta_description) {
                        $('#alenseo_meta_description').val(response.meta_description);
                        
                        // Zeige Charakter-Count
                        updateCharacterCount('#alenseo_meta_description', response.meta_description.length);
                    }
                })
                .catch(function(error) {
                    alert('Fehler bei der Meta-Description-Optimierung: ' + error.message);
                })
                .finally(function() {
                    button.prop('disabled', false).text('Optimieren');
                });
        });
        
        // === HILFSFUNKTIONEN ===
        
        // Bulk-Optimierung Funktion
        function bulkOptimizePosts(postIds) {
            var progress = 0;
            var total = postIds.length;
            
            // Progress-Modal anzeigen
            showProgressModal('Optimiere ' + total + ' Beiträge...', 0);
            
            // Sequential processing um API-Limits zu respektieren
            processPostSequentially(postIds, 0)
                .then(function(results) {
                    hideProgressModal();
                    
                    var successful = results.filter(r => r.success).length;
                    var failed = results.filter(r => !r.success).length;
                    
                    alert(`Bulk-Optimierung abgeschlossen!\nErfolgreich: ${successful}\nFehlgeschlagen: ${failed}`);
                    
                    // Tabelle aktualisieren
                    location.reload();
                })
                .catch(function(error) {
                    hideProgressModal();
                    alert('Fehler bei der Bulk-Optimierung: ' + error.message);
                });
        }
        
        async function processPostSequentially(postIds, index) {
            if (index >= postIds.length) {
                return [];
            }
            
            var postId = postIds[index];
            var results = [];
            
            try {
                updateProgressModal(Math.round(((index + 1) / postIds.length) * 100));
                
                // Keywords generieren für diesen Post
                var keywordResult = await window.Alenseo.api.generateKeywords(postId, { 
                    preferSpeed: true,
                    showProgress: false,
                    showNotifications: false 
                });
                
                // Kurze Pause zwischen Anfragen
                await new Promise(resolve => setTimeout(resolve, 500));
                
                results.push({ postId: postId, success: true, keywords: keywordResult.keywords });
                
            } catch (error) {
                results.push({ postId: postId, success: false, error: error.message });
            }
            
            // Nächsten Post verarbeiten
            var nextResults = await processPostSequentially(postIds, index + 1);
            return results.concat(nextResults);
        }
        
        function showProgressModal(message, percentage) {
            var modal = `
                <div id="alenseo-progress-modal" class="alenseo-modal">
                    <div class="alenseo-modal-content">
                        <h3>${message}</h3>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${percentage}%"></div>
                        </div>
                        <p class="progress-text">${percentage}%</p>
                    </div>
                </div>
            `;
            $('body').append(modal);
        }
        
        function updateProgressModal(percentage) {
            $('#alenseo-progress-modal .progress-fill').css('width', percentage + '%');
            $('#alenseo-progress-modal .progress-text').text(percentage + '%');
        }
        
        function hideProgressModal() {
            $('#alenseo-progress-modal').remove();
        }
        
        function updateCharacterCount(selector, count) {
            var counter = $(selector).siblings('.char-count');
            if (counter.length === 0) {
                counter = $('<span class="char-count"></span>');
                $(selector).after(counter);
            }
            
            var maxLength = selector.includes('meta_description') ? 155 : 60;
            var colorClass = count > maxLength ? 'over-limit' : (count > maxLength * 0.9 ? 'near-limit' : 'ok');
            
            counter.text(count + '/' + maxLength + ' Zeichen')
                   .removeClass('over-limit near-limit ok')
                   .addClass(colorClass);
        }
        
        // Character-Count für Meta-Felder
        $('#alenseo_meta_description, #alenseo_meta_title').on('input', function() {
            updateCharacterCount('#' + this.id, this.value.length);
        });
        
        // === DEINE BESTEHENDEN FEATURES BEIBEHALTEN ===
        
        // Datatable-Integration (falls vorhanden)
        if ($.fn.DataTable) {
            $('.alenseo-datatable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/German.json"
                }
            });
        }
        
        // Filter-Funktionalität
        $('.alenseo-filter-dropdown').on('change', function() {
            var filterValue = $(this).val();
            var filterTarget = $(this).data('filter-target');
            
            if (filterValue === '') {
                $(filterTarget).show();
            } else {
                $(filterTarget).hide();
                $(filterTarget + '[data-' + $(this).data('filter-attr') + '="' + filterValue + '"]').show();
            }
        });
        
        // Suchfeld
        $('.alenseo-search-input').on('keyup', function() {
            var searchValue = $(this).val().toLowerCase();
            var searchTarget = $(this).data('search-target');
            
            $(searchTarget).each(function() {
                var text = $(this).text().toLowerCase();
                if (text.indexOf(searchValue) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // Bearbeitbare Bereiche
        $('.alenseo-toggle-edit').on('click', function() {
            var targetId = $(this).data('target');
            $('#' + targetId + '-display').toggle();
            $('#' + targetId + '-edit').toggle();
        });
        
        $('.alenseo-edit-cancel').on('click', function() {
            var targetId = $(this).data('target');
            $('#' + targetId + '-edit').hide();
            $('#' + targetId + '-display').show();
        });
        
        // API-Status in localStorage speichern
        window.Alenseo.api.getApiStatus()
            .then(function(status) {
                if (status.claude?.working || status.openai?.working) {
                    localStorage.setItem('alenseo_api_status', 'active');
                }
            })
            .catch(function() {
                // Ignore errors for status check
            });
    });

    // === VANILLA JS FEATURES (Deine bestehenden) ===
    
    document.addEventListener('DOMContentLoaded', function () {
        // Lazy Loading
        const lazyLoadSections = document.querySelectorAll('[data-lazy-load]');
        lazyLoadSections.forEach(section => {
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const url = section.getAttribute('data-lazy-load');
                        fetch(url)
                            .then(response => response.text())
                            .then(html => {
                                section.innerHTML = html;
                            });
                        observer.unobserve(section);
                    }
                });
            });
            observer.observe(section);
        });

        // Native Tooltips (zusätzlich zu jQuery-Version)
        const tooltipElements = document.querySelectorAll('[data-tooltip]:not(.alenseo-tooltip)');
        tooltipElements.forEach(el => {
            el.addEventListener('mouseenter', function () {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.innerText = el.getAttribute('data-tooltip');
                document.body.appendChild(tooltip);

                const rect = el.getBoundingClientRect();
                tooltip.style.left = `${rect.left + window.scrollX}px`;
                tooltip.style.top = `${rect.top + window.scrollY - tooltip.offsetHeight}px`;
            });

            el.addEventListener('mouseleave', function () {
                document.querySelectorAll('.tooltip').forEach(tip => tip.remove());
            });
        });

        // Tutorial-System (erweitert)
        const tutorialSteps = [
            {
                element: '#menu-dashboard',
                message: 'Hier finden Sie das Dashboard mit allen wichtigen Informationen.'
            },
            {
                element: '#menu-settings', 
                message: 'Hier können Sie die Plugin-Einstellungen anpassen.'
            },
            {
                element: '#generate-keywords-ai',
                message: 'Verwenden Sie KI um automatisch relevante Keywords zu generieren.'
            }
        ];

        let currentStep = 0;

        function showTutorialStep(step) {
            if (step >= tutorialSteps.length) return;
            
            const stepData = tutorialSteps[step];
            const element = document.querySelector(stepData.element);

            if (element) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tutorial-tooltip';
                tooltip.innerHTML = `
                    <div>${stepData.message}</div>
                    <button onclick="nextTutorialStep()">Weiter</button>
                    <button onclick="closeTutorial()">Überspringen</button>
                `;
                document.body.appendChild(tooltip);

                const rect = element.getBoundingClientRect();
                tooltip.style.left = `${rect.left + window.scrollX}px`;
                tooltip.style.top = `${rect.top + window.scrollY - tooltip.offsetHeight}px`;
                
                // Highlight element
                element.style.outline = '2px solid #0073aa';
                element.style.outlineOffset = '2px';
                
                // Store reference to clean up
                tooltip.targetElement = element;
            }
        }

        // Tutorial-Steuerung
        window.nextTutorialStep = function() {
            const tooltip = document.querySelector('.tutorial-tooltip');
            if (tooltip) {
                if (tooltip.targetElement) {
                    tooltip.targetElement.style.outline = '';
                    tooltip.targetElement.style.outlineOffset = '';
                }
                tooltip.remove();
            }
            
            currentStep++;
            if (currentStep < tutorialSteps.length) {
                setTimeout(() => showTutorialStep(currentStep), 500);
            }
        };
        
        window.closeTutorial = function() {
            const tooltip = document.querySelector('.tutorial-tooltip');
            if (tooltip) {
                if (tooltip.targetElement) {
                    tooltip.targetElement.style.outline = '';
                    tooltip.targetElement.style.outlineOffset = '';
                }
                tooltip.remove();
            }
            localStorage.setItem('alenseo_tutorial_completed', 'true');
        };

        // Tutorial starten (falls nicht bereits abgeschlossen)
        if (!localStorage.getItem('alenseo_tutorial_completed') && tutorialSteps.length > 0) {
            setTimeout(() => showTutorialStep(currentStep), 2000);
        }
    });

    // CSS-Styles für neue Features
    const additionalStyles = `
        <style>
        .alenseo-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999999;
        }
        
        .alenseo-modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            min-width: 300px;
            text-align: center;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            transition: width 0.3s ease;
        }
        
        .keyword-suggestion {
            display: inline-block;
            background: #e7f3ff;
            border: 1px solid #0073aa;
            border-radius: 3px;
            padding: 4px 8px;
            margin: 2px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .keyword-suggestion:hover {
            background: #0073aa;
            color: white;
        }
        
        .char-count {
            font-size: 11px;
            margin-left: 10px;
        }
        
        .char-count.ok { color: #46b450; }
        .char-count.near-limit { color: #f56e28; }
        .char-count.over-limit { color: #dc3232; }
        
        .tutorial-tooltip {
            position: absolute;
            background: #0073aa;
            color: white;
            padding: 15px;
            border-radius: 5px;
            max-width: 250px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .tutorial-tooltip button {
            background: white;
            color: #0073aa;
            border: none;
            padding: 5px 10px;
            margin: 5px 2px 0 0;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
        }
        </style>
    `;
    
    $('head').append(additionalStyles);

})(jQuery);