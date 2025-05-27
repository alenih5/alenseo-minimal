<?php
if (!defined('ABSPATH')) exit;
// Beitrag laden
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
if (!$post_id || get_post_status($post_id) === false) {
    echo '<div class="notice notice-error"><p>' . esc_html__('Kein Beitrag ausgewählt.', 'seo-ai-master') . '</p></div>';
    return;
}
$content = get_post_field('post_content', $post_id);
$url     = get_permalink($post_id);
$title   = get_the_title($post_id);
?>
<div class="seo-ai-master-plugin">
    <!-- Linke Spalte: Editor -->
    <div class="editor-panel">
        <div class="editor-header">
            <h1 class="page-title">
                <i class="fas fa-magic"></i>
                AI Content Optimizer
            </h1>
            <div class="page-breadcrumb">
                <i class="fas fa-file-alt"></i>
                <span><?php echo esc_html($title); ?></span>
                <i class="fas fa-chevron-right"></i>
                <span>Optimierung</span>
            </div>
            <div class="content-selector">
                <select class="selector-dropdown" name="post_id">
                    <option value="<?php echo esc_attr($post_id); ?>"><?php echo esc_html($title); ?></option>
                    <!-- Weitere Beiträge dynamisch ergänzen -->
                </select>
                <div class="ai-provider-selector">
                    <button class="provider-btn active" type="button">Auto</button>
                    <button class="provider-btn" type="button">Claude</button>
                    <button class="provider-btn" type="button">GPT-4o</button>
                    <button class="provider-btn" type="button">Gemini</button>
                </div>
            </div>
        </div>
        <div class="editor-content">
            <div class="optimization-section">
                <h2 class="section-title"><i class="fas fa-tags"></i> Meta-Daten Optimierung</h2>
                <div class="form-group">
                    <div class="form-label">
                        <span>Meta Title</span>
                        <span class="char-counter" id="titleCounter">0 / 60</span>
                    </div>
                    <div class="input-with-ai">
                        <input type="text" class="form-input" id="metaTitle" value="" oninput="updateCharCounter('metaTitle', 'titleCounter', 60)">
                        <button class="ai-generate-btn" type="button" onclick="generateMetaTitle()"><i class="fas fa-robot"></i> AI Generieren</button>
                    </div>
                    <div class="ai-suggestions" id="titleSuggestions" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <div class="form-label">
                        <span>Meta Description</span>
                        <span class="char-counter" id="descCounter">0 / 160</span>
                    </div>
                    <div class="input-with-ai">
                        <textarea class="form-textarea" id="metaDescription" oninput="updateCharCounter('metaDescription', 'descCounter', 160)" placeholder="Meta Description eingeben..."></textarea>
                        <button class="ai-generate-btn" type="button" onclick="generateMetaDescription()"><i class="fas fa-robot"></i> AI Generieren</button>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-label"><span>Focus Keyword</span></div>
                    <div class="input-with-ai">
                        <input type="text" class="form-input" id="focusKeyword" value="">
                        <button class="ai-generate-btn" type="button" onclick="suggestKeywords()"><i class="fas fa-search"></i> Vorschlagen</button>
                    </div>
                </div>
            </div>
            <div class="optimization-section">
                <h2 class="section-title"><i class="fas fa-edit"></i> Content Optimierung</h2>
                <div class="form-group">
                    <div class="form-label">
                        <span>Optimierter Content</span>
                        <span class="char-counter">0 Wörter</span>
                    </div>
                    <textarea class="form-textarea content-textarea" id="contentText" placeholder="Content hier bearbeiten..."><?php echo esc_textarea($content); ?></textarea>
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                        <button class="ai-generate-btn" type="button" onclick="optimizeContent()"><i class="fas fa-magic"></i> Content optimieren</button>
                        <button class="ai-generate-btn" type="button" onclick="improveReadability()"><i class="fas fa-book-open"></i> Lesbarkeit verbessern</button>
                        <button class="ai-generate-btn" type="button" onclick="addInternalLinks()"><i class="fas fa-link"></i> Links vorschlagen</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="action-buttons" style="position: sticky; bottom: 0; background: rgba(255,255,255,0.1); backdrop-filter: blur(20px); border-top: 1px solid rgba(255,255,255,0.2); padding: 1.5rem; display: flex; gap: 1rem; justify-content: space-between;">
            <div style="display: flex; gap: 1rem;">
                <button class="btn btn-secondary" type="button"><i class="fas fa-undo"></i> Zurücksetzen</button>
                <button class="btn btn-secondary" type="button"><i class="fas fa-eye"></i> Vorschau</button>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button class="btn btn-primary" type="button"><i class="fas fa-save"></i> Entwurf speichern</button>
                <button class="btn btn-success" type="button"><i class="fas fa-rocket"></i> Optimierung anwenden</button>
            </div>
        </div>
    </div>
    <!-- Rechte Spalte: Preview -->
    <div class="preview-panel" style="flex:1; background: rgba(255,255,255,0.1); backdrop-filter: blur(20px); display: flex; flex-direction: column; overflow: hidden;">
        <div class="preview-header" style="padding: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05);">
            <h2 class="preview-title"><i class="fas fa-eye"></i> Live Preview</h2>
            <div class="preview-tabs" style="display: flex; gap: 0.5rem;">
                <button class="preview-tab active" type="button" onclick="switchPreview('serp')">SERP Vorschau</button>
                <button class="preview-tab" type="button" onclick="switchPreview('seo')">SEO Analyse</button>
                <button class="preview-tab" type="button" onclick="switchPreview('readability')">Lesbarkeit</button>
                <button class="preview-tab" type="button" onclick="switchPreview('mobile')">Mobile</button>
            </div>
        </div>
        <div class="preview-content" style="flex:1; padding: 1.5rem; overflow-y: auto;">
            <div class="serp-preview" id="serpPreview">
                <div class="serp-title" id="serpTitle">Meta Title Vorschau</div>
                <div class="serp-url"><?php echo esc_html($url); ?></div>
                <div class="serp-description" id="serpDescription">Meta Description Vorschau</div>
            </div>
            <!-- Hier können weitere Analyse- und Vorschau-Elemente ergänzt werden -->
        </div>
    </div>
</div>
<script>
// Character counter
function updateCharCounter(inputId, counterId, maxLength) {
    const input = document.getElementById(inputId);
    const counter = document.getElementById(counterId);
    const currentLength = input.value.length;
    counter.textContent = `${currentLength} / ${maxLength}`;
    if (currentLength > maxLength) {
        counter.className = 'char-counter error';
    } else if (currentLength > maxLength * 0.9) {
        counter.className = 'char-counter warning';
    } else {
        counter.className = 'char-counter';
    }
    updateSERPPreview();
}
function updateSERPPreview() {
    const title = document.getElementById('metaTitle').value;
    const description = document.getElementById('metaDescription').value;
    document.getElementById('serpTitle').textContent = title || 'Meta Title Vorschau';
    document.getElementById('serpDescription').textContent = description || 'Meta Description Vorschau';
}
function generateMetaTitle() {
    const button = event.target.closest('.ai-generate-btn');
    const input = document.getElementById('metaTitle');
    const suggestions = document.getElementById('titleSuggestions');
    button.innerHTML = '<span class="ai-loading"><i class="fas fa-robot"></i> Generiere<div class="loading-dots"><div class="loading-dot"></div><div class="loading-dot"></div><div class="loading-dot"></div></div></span>';
    button.disabled = true;
    setTimeout(() => {
        button.innerHTML = '<i class="fas fa-robot"></i> AI Generieren';
        button.disabled = false;
        suggestions.style.display = 'block';
        button.style.background = 'linear-gradient(45deg, #10b981, #059669)';
        setTimeout(() => { button.style.background = 'linear-gradient(45deg, #667eea, #764ba2)'; }, 1000);
    }, 2500);
}
function generateMetaDescription() {
    const button = event.target.closest('.ai-generate-btn');
    const textarea = document.getElementById('metaDescription');
    button.innerHTML = '<span class="ai-loading"><i class="fas fa-robot"></i> Generiere<div class="loading-dots"><div class="loading-dot"></div><div class="loading-dot"></div><div class="loading-dot"></div></div></span>';
    button.disabled = true;
    setTimeout(() => {
        const aiDescription = 'Entdecken Sie WordPress SEO! Unser ultimativer Leitfaden 2024 zeigt Ihnen Schritt für Schritt, wie Sie Ihre Website für Google optimieren und mehr Traffic generieren.';
        textarea.value = aiDescription;
        updateCharCounter('metaDescription', 'descCounter', 160);
        button.innerHTML = '<i class="fas fa-robot"></i> AI Generieren';
        button.disabled = false;
        button.style.background = 'linear-gradient(45deg, #10b981, #059669)';
        setTimeout(() => { button.style.background = 'linear-gradient(45deg, #667eea, #764ba2)'; }, 1000);
    }, 3000);
}
function optimizeContent() { const button = event.target; button.innerHTML = '<span class="ai-loading"><i class="fas fa-magic"></i> Optimiere<div class="loading-dots"><div class="loading-dot"></div><div class="loading-dot"></div><div class="loading-dot"></div></div></span>'; button.disabled = true; setTimeout(() => { button.innerHTML = '<i class="fas fa-magic"></i> Content optimieren'; button.disabled = false; button.style.background = 'linear-gradient(45deg, #10b981, #059669)'; setTimeout(() => { button.style.background = 'linear-gradient(45deg, #667eea, #764ba2)'; }, 1000); }, 4000); }
function improveReadability() { /* Dummy-Funktion */ }
function addInternalLinks() { /* Dummy-Funktion */ }
function switchPreview(type) { document.querySelectorAll('.preview-tab').forEach(tab => { tab.classList.remove('active'); }); event.target.classList.add('active'); }
document.addEventListener('DOMContentLoaded', function() {
    updateCharCounter('metaTitle', 'titleCounter', 60);
    updateCharCounter('metaDescription', 'descCounter', 160);
    updateSERPPreview();
});
</script> 