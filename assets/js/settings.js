/**
 * SEO AI Master - Settings JavaScript (WordPress Compatible)
 * @version 1.0.2
 * @author AlenSEO
 * @description WordPress-optimierte Version mit verbesserter Sicherheit und Performance
 */

(function($) {
    'use strict';
    
    // WordPress-kompatible Initialisierung
    $(document).ready(function() {
        
        // Konstanten und Konfiguration
        const CONFIG = {
            API_TEST_TIMEOUT: 15000, // 15 Sekunden für bessere Stabilität
            TOAST_DURATION: 4000,
            STATUS_CHECK_INTERVAL: 5 * 60 * 1000, // 5 Minuten
            DEBOUNCE_DELAY: 500,
            MAX_RETRIES: 2
        };
        
        // State Management
        let testInProgress = false;
        let statusCheckInterval = null;
        let retryCount = 0;
        
        // WordPress-sichere Plugin-Container-Referenz
        const $plugin = $('.seo-ai-master-plugin');
        
        // Früher Exit wenn Plugin-Container nicht gefunden
        if (!$plugin.length) {
            console.warn('SEO AI Master: Plugin container not found');
            return;
        }
        
        // WordPress AJAX-Konfiguration mit Fallbacks
        const ajaxConfig = {
            url: (typeof seoAiSettings !== 'undefined' && seoAiSettings.ajaxUrl) ? 
                 seoAiSettings.ajaxUrl : 
                 (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'),
            nonce: (typeof seoAiSettings !== 'undefined' && seoAiSettings.nonce) ? 
                   seoAiSettings.nonce : 
                   $('#seo_ai_nonce').val() || $('input[name*="_nonce"]').first().val()
        };
        
        // Sicherheitsvalidierung
        if (!ajaxConfig.nonce) {
            console.error('SEO AI Master: Security nonce not found');
            showToast('Sicherheitsfehler: Seite neu laden erforderlich', 'error', 8000);
            return;
        }
        
        /**
         * Status-Management für API-Karten
         * @param {jQuery} $card - API-Karte
         * @param {string} status - Status (success, error, loading, disconnected)
         * @param {string} msg - Nachricht
         */
        function setStatus($card, status, msg) {
            if (!$card || !$card.length) {
                console.warn('SEO AI Master: Invalid card element');
                return;
            }
            
            const $status = $card.find('.api-status');
            if (!$status.length) {
                console.warn('SEO AI Master: Status element not found in card');
                return;
            }
            
            // Alle Status-Klassen entfernen
            $status.removeClass('connected error loading disconnected').text('');
            $card.removeClass('connected error');
            
            // Accessibility: aria-live für Status-Updates
            $status.attr('aria-live', 'polite');
            
            switch(status) {
                case 'success':
                    $status.addClass('connected').text('Verbunden');
                    $card.addClass('connected').removeClass('error');
                    $status.attr('aria-label', 'API erfolgreich verbunden');
                    break;
                case 'error':
                    $status.addClass('error').text('Verbindungsfehler');
                    $card.addClass('error').removeClass('connected');
                    $status.attr('aria-label', 'API Verbindungsfehler');
                    break;
                case 'loading':
                    $status.addClass('loading').text('Teste Verbindung...');
                    $card.removeClass('connected error');
                    $status.attr('aria-label', 'API-Verbindung wird getestet');
                    break;
                default:
                    $status.addClass('disconnected').text('Nicht verbunden');
                    $card.removeClass('connected error');
                    $status.attr('aria-label', 'API nicht verbunden');
            }
            
            if (msg) {
                $status.attr('title', sanitizeHtml(msg));
                // Toast mit verbesserter Fehlerbehandlung
                try {
                    showToast(msg, status === 'success' ? 'success' : status === 'error' ? 'error' : 'info');
                } catch (e) {
                    console.error('SEO AI Master: Toast error:', e);
                }
            }
        }
        
        /**
         * Verbesserte Toast-Benachrichtigungen mit WordPress-Integration
         * @param {string} message - Nachricht
         * @param {string} type - Typ (success, error, warning, info)
         * @param {number} duration - Anzeigedauer in ms
         */
        function showToast(message, type = 'info', duration = CONFIG.TOAST_DURATION) {
            // Input-Validierung
            if (!message || typeof message !== 'string') {
                console.warn('SEO AI Master: Invalid toast message');
                return null;
            }
            
            // HTML-Sanitization
            message = sanitizeHtml(message);
            
            // Maximale Anzahl gleichzeitiger Toasts begrenzen
            const existingToasts = $plugin.find('.seo-ai-toast');
            if (existingToasts.length >= 3) {
                existingToasts.first().remove();
            }
            
            const iconMap = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };
            
            const $toast = $(`
                <div class="seo-ai-toast ${type}" role="alert" aria-live="assertive">
                    <i class="toast-icon ${iconMap[type] || iconMap.info}" aria-hidden="true"></i>
                    <div class="toast-content">
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close" type="button" aria-label="Benachrichtigung schließen">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                    <div class="toast-progress"></div>
                </div>
            `);
            
            // Event-Handler für Close-Button
            $toast.find('.toast-close').on('click', function() {
                $toast.removeClass('show');
                setTimeout(() => $toast.remove(), 300);
            });
            
            $plugin.append($toast);
            
            // Show animation mit Verzögerung für bessere Performance
            requestAnimationFrame(() => {
                setTimeout(() => $toast.addClass('show'), 50);
            });
            
            // Auto-remove mit cleanup
            const removeTimer = duration > 0 ? setTimeout(() => {
                if ($toast.length && $toast.parent().length) {
                    $toast.removeClass('show');
                    setTimeout(() => {
                        if ($toast.length && $toast.parent().length) {
                            $toast.remove();
                        }
                    }, 300);
                }
            }, duration) : null;
            
            // Cleanup bei manueller Entfernung
            $toast.data('removeTimer', removeTimer);
            
            return $toast;
        }
        
        /**
         * HTML-Sanitization für XSS-Schutz
         * @param {string} html - HTML-String
         * @returns {string} - Bereinigter String
         */
        function sanitizeHtml(html) {
            if (typeof html !== 'string') return '';
            
            const div = document.createElement('div');
            div.textContent = html;
            return div.innerHTML;
        }
        
        /**
         * Provider aus API-Karte ermitteln
         * @param {jQuery} $card - API-Karte
         * @returns {string} - Provider-Name
         */
        function getProviderFromCard($card) {
            if (!$card || !$card.length) return 'unknown';
            
            // ID-basierte Erkennung (bevorzugt)
            const cardId = $card.attr('id');
            if (cardId && cardId.includes('-card')) {
                return cardId.replace('-card', '');
            }
            
            // Fallback: Header-Text analysieren
            const headerText = $card.find('.api-provider').text().toLowerCase();
            if (headerText.includes('claude')) return 'claude';
            if (headerText.includes('gpt') || headerText.includes('openai')) return 'openai';
            if (headerText.includes('gemini')) return 'gemini';
            
            // Data-Attribute prüfen
            const provider = $card.data('provider');
            if (provider) return provider;
            
            console.warn('SEO AI Master: Could not determine provider from card');
            return 'unknown';
        }
        
        /**
         * Verbesserte API-Key-Format-Validierung
         * @param {string} provider - Provider-Name
         * @param {string} apiKey - API-Key
         * @returns {boolean} - Validierungsergebnis
         */
        function validateApiKeyFormat(provider, apiKey) {
            if (!apiKey || typeof apiKey !== 'string') return false;
            
            // Trim und Basis-Validierung
            apiKey = apiKey.trim();
            if (apiKey.length < 10) return false;
            
            const patterns = {
                claude: /^sk-ant-api03-[a-zA-Z0-9_-]{95}$/,
                openai: /^sk-[a-zA-Z0-9]{48,51}$/, // Leicht flexibler für verschiedene Versionen
                gemini: /^AIzaSy[a-zA-Z0-9_-]{33}$/
            };
            
            // Pattern-basierte Validierung
            if (patterns[provider]) {
                return patterns[provider].test(apiKey);
            }
            
            // Generische Validierung für unbekannte Provider
            return apiKey.length >= 20 && /^[a-zA-Z0-9_-]+$/.test(apiKey);
        }
        
        /**
         * Hauptfunktion für API-Tests mit verbesserter Fehlerbehandlung
         * @param {jQuery} $card - API-Karte
         * @param {jQuery} $btn - Test-Button (optional)
         */
        function testApi($card, $btn) {
            // Validierung
            if (!$card || !$card.length) {
                console.error('SEO AI Master: Invalid card for API test');
                return;
            }
            
            if (testInProgress && retryCount >= CONFIG.MAX_RETRIES) {
                showToast('Ein API-Test läuft bereits. Bitte warten...', 'warning');
                return;
            }
            
            const provider = getProviderFromCard($card);
            if (provider === 'unknown') {
                setStatus($card, 'error', 'Unbekannter API-Provider');
                return;
            }
            
            const $apiKeyInput = $card.find('input[type=password], input[name$="_api_key"]').first();
            const api_key = $apiKeyInput.val();
            
            if (!api_key || api_key.trim() === '') {
                setStatus($card, 'error', 'Bitte geben Sie einen gültigen API-Key ein');
                return;
            }
            
            // Format-Validierung
            if (!validateApiKeyFormat(provider, api_key)) {
                setStatus($card, 'error', 'API-Key Format ist ungültig');
                return;
            }
            
            // Rate Limiting
            const lastTest = $card.data('lastTest');
            const now = Date.now();
            if (lastTest && (now - lastTest) < 2000) {
                showToast('Bitte warten Sie 2 Sekunden zwischen Tests', 'warning');
                return;
            }
            $card.data('lastTest', now);
            
            testInProgress = true;
            setStatus($card, 'loading', 'Verbindung wird getestet...');
            
            if ($btn && $btn.length) {
                $btn.addClass('loading').prop('disabled', true);
                $btn.attr('aria-busy', 'true');
            }
            
            // Timeout mit verbesserter Behandlung
            const timeoutId = setTimeout(() => {
                testInProgress = false;
                retryCount++;
                setStatus($card, 'error', `Zeitüberschreitung bei der API-Verbindung (${CONFIG.API_TEST_TIMEOUT/1000}s)`);
                if ($btn && $btn.length) {
                    $btn.removeClass('loading').prop('disabled', false).removeAttr('aria-busy');
                }
            }, CONFIG.API_TEST_TIMEOUT);
            
            // AJAX-Request mit verbesserter Fehlerbehandlung
            $.ajax({
                url: ajaxConfig.url,
                type: 'POST',
                dataType: 'json',
                timeout: CONFIG.API_TEST_TIMEOUT - 1000, // 1s Puffer für Cleanup
                data: {
                    action: 'seo_ai_test_api',
                    nonce: ajaxConfig.nonce,
                    provider: sanitizeHtml(provider),
                    api_key: api_key // Wird serverseitig validiert
                },
                success: function(resp) {
                    clearTimeout(timeoutId);
                    testInProgress = false;
                    retryCount = 0; // Reset bei Erfolg
                    
                    if (resp && resp.success) {
                        const message = resp.data || `${provider.toUpperCase()} API erfolgreich verbunden`;
                        setStatus($card, 'success', message);
                        $card.addClass('has-key');
                        
                        // Accessibility: Screen Reader Feedback
                        announceToScreenReader(`${provider} API erfolgreich verbunden`);
                    } else {
                        const errorMsg = resp && resp.data ? resp.data : `${provider.toUpperCase()} API-Verbindung fehlgeschlagen`;
                        setStatus($card, 'error', errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    clearTimeout(timeoutId);
                    testInProgress = false;
                    
                    let errorMsg = 'Unbekannter Fehler bei der API-Verbindung';
                    
                    // Detaillierte Fehleranalyse
                    if (status === 'timeout') {
                        errorMsg = 'Zeitüberschreitung der Anfrage';
                    } else if (xhr.status === 403) {
                        errorMsg = 'Zugriff verweigert - Ungültiger API-Key oder Rate Limit erreicht';
                    } else if (xhr.status === 401) {
                        errorMsg = 'Authentifizierung fehlgeschlagen - API-Key prüfen';
                    } else if (xhr.status === 429) {
                        errorMsg = 'Rate Limit erreicht - Bitte später versuchen';
                    } else if (xhr.status >= 500) {
                        errorMsg = 'Server-Fehler - Bitte später versuchen';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMsg = xhr.responseJSON.data;
                    } else if (error) {
                        errorMsg = error;
                    }
                    
                    setStatus($card, 'error', `${provider.toUpperCase()}: ${errorMsg}`);
                    
                    // Retry-Logik für temporäre Fehler
                    if ((xhr.status >= 500 || status === 'timeout') && retryCount < CONFIG.MAX_RETRIES) {
                        retryCount++;
                        setTimeout(() => {
                            if ($card.length) {
                                testApi($card, null);
                            }
                        }, 2000 * retryCount); // Exponential backoff
                    }
                },
                complete: function() {
                    if ($btn && $btn.length) {
                        // Verzögerter Reset für bessere UX
                        setTimeout(() => {
                            $btn.removeClass('loading').prop('disabled', false).removeAttr('aria-busy');
                        }, 800);
                    }
                }
            });
        }
        
        /**
         * Screen Reader Announcements für Accessibility
         * @param {string} message - Nachricht
         */
        function announceToScreenReader(message) {
            const $announcer = $('#seo-ai-announcer');
            if ($announcer.length) {
                $announcer.text(message);
            } else {
                // Erstelle Announcer falls nicht vorhanden
                $('<div>', {
                    id: 'seo-ai-announcer',
                    'aria-live': 'polite',
                    'aria-atomic': 'true',
                    css: {
                        position: 'absolute',
                        left: '-10000px',
                        width: '1px',
                        height: '1px',
                        overflow: 'hidden'
                    }
                }).text(message).appendTo('body');
            }
        }
        
        // Event-Handler für API-Test-Buttons (verbesserter Event-Delegation)
        $plugin.on('click', '.api-card .btn', function(e) {
            const $btn = $(this);
            const $card = $btn.closest('.api-card');
            
            // Nur Test-Buttons, nicht Submit-Buttons
            if ($btn.attr('type') === 'submit' || $btn.hasClass('btn-submit')) {
                return true; // Normales Submit-Verhalten
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            // Debouncing für Doppelklick-Schutz
            if ($btn.data('testing')) {
                return false;
            }
            
            $btn.data('testing', true);
            setTimeout(() => $btn.removeData('testing'), 1000);
            
            testApi($card, $btn);
        });
        
        // Globale Funktionen für onclick-Handler (WordPress-kompatibel)
        window.testApiConnection = function(provider) {
            if (!provider) return;
            
            const $card = $plugin.find('#' + sanitizeHtml(provider) + '-card');
            if ($card.length) {
                const $btn = $card.find('.btn').not('[type="submit"]').first();
                testApi($card, $btn);
            }
        };
        
        window.testAllAPIs = function() {
            const $allTestBtn = $plugin.find('.btn-secondary').filter(function() {
                return $(this).text().includes('Alle APIs');
            });
            
            if ($allTestBtn.length) {
                $allTestBtn.addClass('loading').prop('disabled', true);
            }
            
            const providers = ['claude', 'openai', 'gemini'];
            let completed = 0;
            const startTime = Date.now();
            
            providers.forEach((provider, index) => {
                setTimeout(() => {
                    const $card = $plugin.find('#' + provider + '-card');
                    if (!$card.length) {
                        completed++;
                        return;
                    }
                    
                    const api_key = $card.find('input[type=password], input[name$="_api_key"]').val();
                    
                    if (api_key && api_key.trim() !== '') {
                        // Callback für Completion-Tracking
                        const originalComplete = testApi;
                        testApi($card, null);
                    }
                    
                    completed++;
                    
                    // Alle Tests abgeschlossen
                    if (completed === providers.length) {
                        setTimeout(() => {
                            if ($allTestBtn.length) {
                                $allTestBtn.removeClass('loading').prop('disabled', false);
                            }
                            const duration = ((Date.now() - startTime) / 1000).toFixed(1);
                            showToast(`Alle API-Tests abgeschlossen! (${duration}s)`, 'success');
                        }, 2000);
                    }
                }, index * 1200); // Etwas längere Verzögerung zwischen Tests
            });
        };
        
        // Automatische API-Tests beim Laden (optimiert)
        function initializeApiCards() {
            const $cards = $plugin.find('.api-card');
            let delay = 0;
            
            $cards.each(function() {
                const $card = $(this);
                const api_key = $card.find('input[type=password], input[name$="_api_key"]').val();
                
                if (api_key && api_key.length > 10) {
                    $card.addClass('has-key');
                    
                    // Gestaffelte automatische Tests für bessere Performance
                    setTimeout(() => {
                        if ($card.length && $plugin.length) {
                            testApi($card, null);
                        }
                    }, 1000 + delay);
                    
                    delay += 800; // 800ms zwischen automatischen Tests
                }
            });
        }
        
        // Formular-Submit-Handler (WordPress-optimiert)
        $plugin.on('submit', 'form', function(e) {
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"], input[type="submit"]').first();
            
            if ($submitBtn.length) {
                $submitBtn.addClass('loading').prop('disabled', true);
                
                // Text-Management
                const $textElement = $submitBtn.find('span').length ? $submitBtn.find('span').first() : $submitBtn;
                const originalText = $textElement.text();
                $textElement.text('Wird gespeichert...');
                
                // Reset nach WordPress-Submit
                setTimeout(() => {
                    // Re-teste APIs nach dem Speichern
                    $plugin.find('.api-card').each(function() {
                        const $card = $(this);
                        const api_key = $card.find('input[type=password], input[name$="_api_key"]').val();
                        if (api_key && api_key.length > 10) {
                            setTimeout(() => {
                                if ($card.length) {
                                    testApi($card, null);
                                }
                            }, Math.random() * 1500 + 500);
                        }
                    });
                    
                    // Button-Reset
                    setTimeout(() => {
                        if ($submitBtn.length) {
                            $submitBtn.removeClass('loading').prop('disabled', false);
                            $textElement.text(originalText);
                        }
                    }, 2500);
                }, 1000);
            }
        });
        
        // Verbesserte Tab-Navigation mit Accessibility
        $plugin.on('click', '.settings-nav li', function(e) {
            e.preventDefault();
            
            const $navItem = $(this);
            const targetSection = $navItem.data('section');
            
            if (!targetSection) {
                console.warn('SEO AI Master: No target section specified');
                return;
            }
            
            // Navigation aktualisieren
            $plugin.find('.settings-nav li').removeClass('active').attr('aria-selected', 'false');
            $navItem.addClass('active').attr('aria-selected', 'true');
            
            // Sections mit Animation aktualisieren
            const $sections = $plugin.find('.settings-section');
            const $targetSection = $plugin.find('#' + targetSection).first();
            
            if (!$targetSection.length) {
                console.warn('SEO AI Master: Target section not found:', targetSection);
                return;
            }
            
            $sections.removeClass('active').attr('aria-hidden', 'true');
            $targetSection.addClass('active').attr('aria-hidden', 'false');
            
            // Focus-Management für Accessibility
            $targetSection.find('h2, h3').first().focus();
            
            // URL-Update (WordPress-sicher)
            if (history.pushState && window.location) {
                try {
                    const currentParams = new URLSearchParams(window.location.search);
                    currentParams.set('section', targetSection);
                    const newUrl = window.location.pathname + '?' + currentParams.toString();
                    history.pushState({ section: targetSection }, '', newUrl);
                } catch (e) {
                    console.warn('SEO AI Master: URL update failed:', e);
                }
            }
        });
        
        // Debounced Input-Validierung für bessere Performance
        let inputTimeout;
        $plugin.on('input', '.api-card input[type=password], .api-card input[name$="_api_key"]', function() {
            const $input = $(this);
            const $card = $input.closest('.api-card');
            
            clearTimeout(inputTimeout);
            inputTimeout = setTimeout(() => {
                const value = $input.val().trim();
                
                if (value.length > 0) {
                    $card.addClass('has-key');
                    
                    // Format-Validierung
                    const provider = getProviderFromCard($card);
                    if (validateApiKeyFormat(provider, value)) {
                        $input.removeClass('error');
                        $card.find('.field-error').remove();
                    } else {
                        $input.addClass('error');
                        // Nicht zu aggressive Fehleranzeige
                    }
                } else {
                    $card.removeClass('has-key connected error');
                    const $status = $card.find('.api-status');
                    $status.removeClass('connected error loading')
                           .addClass('disconnected')
                           .text('Nicht verbunden')
                           .attr('aria-label', 'API nicht verbunden');
                }
            }, CONFIG.DEBOUNCE_DELAY);
        });
        
        // Toggle Switch Funktionalität (WordPress-optimiert)
        window.toggleSwitch = function(element, inputId) {
            try {
                const $element = $(element);
                const $input = inputId ? $('#' + sanitizeHtml(inputId)) : $element.closest('.api-toggle').find('input[type="checkbox"]');
                
                if (!$element.length || !$input.length) {
                    console.warn('SEO AI Master: Toggle elements not found');
                    return;
                }
                
                const isActive = $element.hasClass('active');
                
                if (isActive) {
                    $element.removeClass('active');
                    $input.prop('checked', false);
                } else {
                    $element.addClass('active');
                    $input.prop('checked', true);
                }
                
                // Accessibility
                $element.attr('aria-checked', !isActive);
                $input.attr('aria-checked', !isActive);
                
                // Feedback
                const $card = $element.closest('.api-card');
                if ($card.length) {
                    const provider = getProviderFromCard($card);
                    const status = isActive ? 'deaktiviert' : 'aktiviert';
                    showToast(`${provider.toUpperCase()} API ${status}`, 'info', 2000);
                }
            } catch (e) {
                console.error('SEO AI Master: Toggle error:', e);
            }
        };
        
        // Password Visibility Toggle (sicherheitsoptimiert)
        window.togglePasswordVisibility = function(inputId) {
            try {
                if (!inputId) return;
                
                const $input = $('#' + sanitizeHtml(inputId));
                const $icon = $input.siblings('.input-icon').first();
                
                if (!$input.length) return;
                
                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $icon.removeClass('fa-eye').addClass('fa-eye-slash');
                    $input.attr('aria-label', 'API-Key sichtbar - zum Verstecken klicken');
                } else {
                    $input.attr('type', 'password');
                    $icon.removeClass('fa-eye-slash').addClass('fa-eye');
                    $input.attr('aria-label', 'API-Key versteckt - zum Anzeigen klicken');
                }
            } catch (e) {
                console.error('SEO AI Master: Password visibility toggle error:', e);
            }
        };
        
        // URL Parameter für Section auswerten (WordPress-sicher)
        function handleUrlParams() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                const section = urlParams.get('section');
                if (section) {
                    const $targetNav = $plugin.find('.settings-nav li[data-section="' + sanitizeHtml(section) + '"]');
                    if ($targetNav.length) {
                        $targetNav.trigger('click');
                    }
                }
            } catch (e) {
                console.warn('SEO AI Master: URL params handling failed:', e);
            }
        }
        
        // Browser Back/Forward Support
        window.addEventListener('popstate', function(e) {
            if (e.state && e.state.section) {
                const $targetNav = $plugin.find('.settings-nav li[data-section="' + sanitizeHtml(e.state.section) + '"]');
                if ($targetNav.length) {
                    $targetNav.trigger('click');
                }
            }
        });
        
        // Globaler API-Status-Check (performance-optimiert)
        function checkGlobalApiStatus() {
            if (!ajaxConfig.nonce) return;
            
            $.ajax({
                url: ajaxConfig.url,
                method: 'POST',
                timeout: 15000,
                data: {
                    action: 'seo_ai_check_all_apis',
                    nonce: ajaxConfig.nonce
                },
                success: function(data) {
                    if (data && data.success && data.data) {
                        Object.keys(data.data).forEach(function(provider) {
                            const status = data.data[provider];
                            const $indicator = $('.api-indicator[data-provider="' + provider + '"]');
                            
                            if ($indicator.length && status) {
                                $indicator.removeClass('online offline testing')
                                         .addClass(status.success ? 'online' : 'offline')
                                         .attr('title', status.message || '')
                                         .attr('aria-label', `${provider} API: ${status.success ? 'online' : 'offline'}`);
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.warn('SEO AI Master: Global API status check failed:', error);
                }
            });
        }
        
        // Periodischer Status-Check mit besserer Performance
        function startStatusChecking() {
            // Initial check nach Laden
            setTimeout(checkGlobalApiStatus, 3000);
            
            // Periodischer Check (nur wenn Tab aktiv)
            statusCheckInterval = setInterval(() => {
                if (!document.hidden && $plugin.is(':visible')) {
                    checkGlobalApiStatus();
                }
            }, CONFIG.STATUS_CHECK_INTERVAL);
        }
        
        // Cleanup bei Page Unload
        $(window).on('beforeunload', function() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
            }
        });
        
        // Willkommens-Toast für neue Benutzer
        function showWelcomeToast() {
            const hasApiKeys = $plugin.find('.api-card.has-key').length > 0;
            const welcomeShown = localStorage.getItem('seo_ai_welcome_shown');
            
            if (!hasApiKeys && !welcomeShown) {
                setTimeout(() => {
                    showToast('Willkommen bei SEO AI Master! Bitte konfigurieren Sie Ihre API-Keys für optimale Performance.', 'info', 8000);
                    try {
                        localStorage.setItem('seo_ai_welcome_shown', '1');
                    } catch (e) {
                        // localStorage nicht verfügbar
                    }
                }, 1500);
            }
        }
        
        // Performance-Monitoring (Development-Modus)
        function logPerformance() {
            if (typeof performance !== 'undefined' && console.log) {
                const loadTime = performance.now();
                if (loadTime > 100) { // Nur bei langsamem Laden loggen
                    console.log('SEO AI Master Settings loaded in:', Math.round(loadTime), 'ms');
                }
            }
        }
        
        // Hauptinitialisierung
        function initialize() {
            try {
                initializeApiCards();
                handleUrlParams();
                startStatusChecking();
                showWelcomeToast();
                logPerformance();
                
                // Accessibility: Announce successful initialization
                announceToScreenReader('SEO AI Master Einstellungen geladen');
                
            } catch (e) {
                console.error('SEO AI Master: Initialization error:', e);
                showToast('Fehler beim Laden der Einstellungen. Seite neu laden empfohlen.', 'error', 10000);
            }
        }
        
        // Starte Initialisierung
        initialize();
    });
    
})(jQuery);