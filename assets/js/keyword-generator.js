/**
 * Alenseo SEO Dashboard mit Keyword-Generator
 */

jQuery(document).ready(function($) {
    // Keyword-Generator
    const keywordButtons = document.querySelectorAll('.alenseo-keyword-generate');
    keywordButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Verhindert, dass der Klick die Zeile anklickt
            const pageId = this.dataset.id;
            generateKeywords(pageId, this);
        });
    });

    // Event-Listener für Keyword speichern
    const keywordSaveButtons = document.querySelectorAll('.alenseo-keyword-save');
    keywordSaveButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Verhindert, dass der Klick die Zeile anklickt
            const pageId = this.dataset.id;
            const keywordInput = document.querySelector(`.alenseo-keyword-input[data-id="${pageId}"]`);
            if (keywordInput) {
                const keyword = keywordInput.value.trim();
                saveKeyword(pageId, keyword);
            }
        });
    });

    // Event-Listener für Enter-Taste in Keyword-Eingabefeld
    const keywordInputs = document.querySelectorAll('.alenseo-keyword-input');
    keywordInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            e.stopPropagation(); // Verhindert, dass die Eingabe die Zeile anklickt
            if (e.key === 'Enter') {
                const pageId = this.dataset.id;
                const keyword = this.value.trim();
                saveKeyword(pageId, keyword);
            }
        });
        // Verhindert, dass Klick auf Input die Zeile anklickt
        input.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });

    /**
     * Keywords generieren
     */
    function generateKeywords(pageId, button) {
        const prompt = `Generiere Keywords für die Seite mit der ID: ${pageId}`;

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'alenseo_claude_generate_text',
                nonce: alenseo_ajax.nonce,
                prompt: prompt
            },
            beforeSend: function() {
                $(button).text('Generieren...').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    const keywords = response.data.text;
                    $(`#keywords-${pageId}`).val(keywords);
                } else {
                    alert('Fehler: ' + response.data.message);
                }
            },
            error: function() {
                alert('Ein Fehler ist aufgetreten.');
            },
            complete: function() {
                $(button).text('Generieren').prop('disabled', false);
            }
        });
    }

    /**
     * Keyword-Vorschläge rendern
     */
    function renderKeywordSuggestions(container, keywords, pageId) {
        // Header mit Schließen-Button
        let html = `
            <div class="alenseo-keyword-suggestions-header">
                <h3>Keyword-Vorschläge</h3>
                <button class="alenseo-keyword-suggestions-close" data-id="${pageId}">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="alenseo-keyword-suggestions-list">
        `;

        // Keyword-Vorschläge
        keywords.forEach((keyword, index) => {
            html += `
                <div class="alenseo-keyword-suggestion">
                    <div class="alenseo-keyword-suggestion-content">
                        ${keyword.keyword}
                    </div>
                    <div class="alenseo-keyword-suggestion-info">
                        <div class="alenseo-keyword-suggestion-score" title="Relevanz">
                            <span class="dashicons dashicons-chart-bar"></span>
                            ${keyword.score || 'N/A'}
                        </div>
                        <button class="alenseo-keyword-suggestion-select" data-id="${pageId}" data-keyword="${keyword.keyword}" title="Keyword auswählen">
                            <span class="dashicons dashicons-yes"></span>
                            Auswählen
                        </button>
                    </div>
                </div>
            `;
        });

        html += `
            </div>
            <div class="alenseo-keyword-suggestions-footer">
                <button class="alenseo-keyword-suggestions-close" data-id="${pageId}">Schließen</button>
            </div>
        `;

        // HTML in Container einfügen
        container.innerHTML = html;

        // Event-Listener für Schließen-Buttons
        const closeButtons = container.querySelectorAll('.alenseo-keyword-suggestions-close');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                container.remove();
            });
        });

        // Event-Listener für Auswählen-Buttons
        const selectButtons = container.querySelectorAll('.alenseo-keyword-suggestion-select');
        selectButtons.forEach(button => {
            button.addEventListener('click', function() {
                const pageId = this.dataset.id;
                const keyword = this.dataset.keyword;
                
                // Keyword in Input eintragen
                const keywordInput = document.querySelector(`.alenseo-keyword-input[data-id="${pageId}"]`);
                if (keywordInput) {
                    keywordInput.value = keyword;
                    
                    // Keyword speichern
                    saveKeyword(pageId, keyword);
                    
                    // Container schließen
                    container.remove();
                }
            });
        });
    }

    /**
     * Keyword speichern
     */
    function saveKeyword(pageId, keyword) {
        // Button und Input finden
        const saveButton = document.querySelector(`.alenseo-keyword-save[data-id="${pageId}"]`);
        const keywordInput = document.querySelector(`.alenseo-keyword-input[data-id="${pageId}"]`);
        
        if (!saveButton || !keywordInput) return;
        
        // Button deaktivieren und Ladeanimation anzeigen
        saveButton.disabled = true;
        saveButton.innerHTML = '<span class="dashicons dashicons-update spin"></span>';
        keywordInput.disabled = true;
        
        // AJAX-Anfrage senden
        $.ajax({
            url: alenseoData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'alenseo_save_keyword',
                post_id: pageId,
                keyword: keyword,
                nonce: alenseoData.nonce
            },
            success: function(response) {
                // Button und Input zurücksetzen
                saveButton.disabled = false;
                saveButton.innerHTML = '<span class="dashicons dashicons-saved"></span>';
                keywordInput.disabled = false;
                
                if (response.success) {
                    // Erfolgsmeldung anzeigen
                    keywordInput.style.borderColor = '#46b450';
                    setTimeout(() => {
                        keywordInput.style.borderColor = '';
                    }, 1500);
                    
                    // Update the keyword display in the table
                    const keywordDisplay = document.querySelector(`.alenseo-page-keyword[data-id="${pageId}"]`);
                    if (keywordDisplay) {
                        keywordDisplay.innerHTML = `<span class="dashicons dashicons-tag"></span> ${keyword}`;
                    }
                    
                    // Optional: Nach kurzer Zeit die Seite neu laden
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Fehlermeldung anzeigen
                    alert(response.data ? response.data.message || 'Fehler beim Speichern des Keywords.' : 'Fehler beim Speichern des Keywords.');
                    keywordInput.style.borderColor = '#dc3232';
                    setTimeout(() => {
                        keywordInput.style.borderColor = '';
                    }, 1500);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error saving keyword:', error);
                
                // Button und Input zurücksetzen
                saveButton.disabled = false;
                saveButton.innerHTML = '<span class="dashicons dashicons-saved"></span>';
                keywordInput.disabled = false;
                
                // Fehlermeldung anzeigen
                alert('Fehler beim Speichern des Keywords. Bitte versuchen Sie es später erneut.');
            }
        });
    }
});
