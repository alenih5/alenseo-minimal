/**
 * Admin.js für das Alenseo SEO Plugin
 */

jQuery(document).ready(function($) {
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
    
    // Score-Kreis Animation
    $('.alenseo-stat-circle').each(function() {
        var percentage = $(this).data('percentage') || 0;
        $(this).css('--percentage', percentage);
    });
    
    // Tooltips
    $('.alenseo-tooltip').hover(
        function() {
            var tooltipText = $(this).data('tooltip');
            $('<div class="alenseo-tooltip-popup">' + tooltipText + '</div>').appendTo('body').css({
                top: $(this).offset().top - 30,
                left: $(this).offset().left + $(this).width() / 2
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
    
    // API-Status speichern
    if ($('#alenseo-api-status').length && $('#alenseo-api-status').hasClass('success')) {
        localStorage.setItem('alenseo_api_status', 'active');
    }
    
    // Für die Filterung in Tabellen
    $('.alenseo-filter-dropdown').on('change', function() {
        var filterValue = $(this).val();
        var filterTarget = $(this).data('filter-target');
        
        if (filterValue === '') {
            // Alle anzeigen
            $(filterTarget).show();
        } else {
            // Filtern
            $(filterTarget).hide();
            $(filterTarget + '[data-' + $(this).data('filter-attr') + '="' + filterValue + '"]').show();
        }
    });
    
    // Für Suchfeld in Tabellen
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
    
    // Für Bulk-Aktionen in Tabellen
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
        
        // Aktion ausführen
        if (action === 'delete' && !confirm('Möchtest du ' + selectedItems.length + ' Einträge wirklich löschen?')) {
            return;
        }
        
        // Form abschicken
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
    
    // Sortierbare Tabellen
    if ($.fn.DataTable) {
        $('.alenseo-datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/German.json"
            }
        });
    }
    
    // Bearbeitbare Bereiche umschalten
    $('.alenseo-toggle-edit').on('click', function() {
        var targetId = $(this).data('target');
        $('#' + targetId + '-display').toggle();
        $('#' + targetId + '-edit').toggle();
    });
    
    // Bearbeitbares Feld abbrechen
    $('.alenseo-edit-cancel').on('click', function() {
        var targetId = $(this).data('target');
        $('#' + targetId + '-edit').hide();
        $('#' + targetId + '-display').show();
    });
    
    // Async-Fokus-Keyword-Speicherung
    $('#alenseo_focus_keyword').on('blur', function() {
        var keywordField = $(this);
        var keyword = keywordField.val();
        var postId = $('#post_ID').val();
        
        // Nur speichern, wenn geändert
        if (keywordField.data('original-value') === keyword) {
            return;
        }
        
        // AJAX-Anfrage zum Speichern
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_save_focus_keyword',
                post_id: postId,
                keyword: keyword,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Originalen Wert aktualisieren
                    keywordField.data('original-value', keyword);
                    
                    // Visuelles Feedback
                    keywordField.css('border-color', '#46b450');
                    setTimeout(function() {
                        keywordField.css('border-color', '');
                    }, 1500);
                }
            }
        });
    });
    
    // Test der Claude API
    $('#alenseo-test-api-key').on('click', function(e) {
        e.preventDefault();
        
        var apiKey = $('#claude_api_key').val();
        var testButton = $(this);
        var statusContainer = $('#alenseo-api-status-container');
        
        if (!apiKey) {
            statusContainer.html('<div class="notice notice-error inline"><p>' + 
                                 'Bitte geben Sie einen API-Schlüssel ein.' + '</p></div>');
            return;
        }
        
        // Button-Status während des Tests
        testButton.prop('disabled', true);
        testButton.text('Teste API...');
        statusContainer.html('<div class="notice notice-info inline"><p>API wird getestet...</p></div>');
        
        // AJAX-Anfrage
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'alenseo_test_claude_api',
                api_key: apiKey,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                testButton.prop('disabled', false);
                testButton.text('API testen');
                
                if (response.success) {
                    statusContainer.html('<div class="notice notice-success inline"><p>' + 
                                        'API-Test erfolgreich! ' + response.data.message + '</p></div>');
                    
                    // Status speichern
                    localStorage.setItem('alenseo_api_status', 'active');
                } else {
                    var errorMessage = response.data && response.data.message ? response.data.message : 'Unbekannter Fehler beim API-Test.';
                    statusContainer.html('<div class="notice notice-error inline"><p>' + 
                                         'API-Test fehlgeschlagen: ' + errorMessage + '</p></div>');
                }
            },
            error: function() {
                testButton.prop('disabled', false);
                testButton.text('API testen');
                statusContainer.html('<div class="notice notice-error inline"><p>' + 
                                     'Fehler bei der Verbindung zum Server. Bitte versuchen Sie es erneut.' + '</p></div>');
            }
        });
    });
      // API-Test-Funktion
    $('#alenseo-api-test').on('click', function(e) {
        e.preventDefault();
        
        // API-Key holen
        var apiKey = $('#claude_api_key').val();
        var model = $('#claude_model').val();
        
        if (!apiKey) {
            alert('Bitte geben Sie einen API-Schlüssel ein.');
            return;
        }
        
        // API-Test-Status auf "Wird getestet..." setzen
        $('#api-test-result').html('<span class="loading">API wird getestet...</span>');
        
        // AJAX-Request für API-Test
        $.ajax({
            url: alenseoAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_test_api_key',
                nonce: alenseoAdminData.nonce,
                api_key: apiKey,
                model: model
            },
            success: function(response) {
                if (response.success) {
                    $('#api-test-result').html('<span class="success">' + response.data.message + '</span>');
                } else {
                    $('#api-test-result').html('<span class="error">' + (response.data.message || 'Fehler beim API-Test.') + '</span>');
                }
            },
            error: function() {
                $('#api-test-result').html('<span class="error">Kommunikationsfehler beim API-Test.</span>');
            }
        });
    });
    
    // Claude API Textgenerierung
    $('#generate-text-button').on('click', function(e) {
        e.preventDefault();

        var prompt = $('#text-prompt').val();
        if (!prompt) {
            alert('Bitte geben Sie einen Prompt ein.');
            return;
        }

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'alenseo_claude_generate_text',
                nonce: alenseo_ajax.nonce,
                prompt: prompt
            },
            success: function(response) {
                if (response.success) {
                    $('#generated-text').text(response.data.text);
                } else {
                    alert('Fehler: ' + response.data.message);
                }
            },
            error: function() {
                alert('Ein Fehler ist aufgetreten.');
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
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

    // Add tooltips to elements with data-tooltip attribute
    const tooltipElements = document.querySelectorAll('[data-tooltip]');

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

    // Interactive tutorial logic
    const tutorialSteps = [
        {
            element: '#menu-dashboard',
            message: 'Hier finden Sie das Dashboard mit allen wichtigen Informationen.'
        },
        {
            element: '#menu-settings',
            message: 'Hier können Sie die Plugin-Einstellungen anpassen.'
        }
    ];

    let currentStep = 0;

    function showTutorialStep(step) {
        const stepData = tutorialSteps[step];
        const element = document.querySelector(stepData.element);

        if (element) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tutorial-tooltip';
            tooltip.innerText = stepData.message;
            document.body.appendChild(tooltip);

            const rect = element.getBoundingClientRect();
            tooltip.style.left = `${rect.left + window.scrollX}px`;
            tooltip.style.top = `${rect.top + window.scrollY - tooltip.offsetHeight}px`;

            element.addEventListener('click', () => {
                tooltip.remove();
                currentStep++;
                if (currentStep < tutorialSteps.length) {
                    showTutorialStep(currentStep);
                }
            });
        }
    }

    // Start the tutorial
    if (tutorialSteps.length > 0) {
        showTutorialStep(currentStep);
    }
});
