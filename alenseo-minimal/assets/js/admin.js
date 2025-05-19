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
});
