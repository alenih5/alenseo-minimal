<?php
if (!defined('ABSPATH')) exit;
$options = get_option('seo_ai_settings', []);
?>

<!-- Plugin Container hinzufügen -->
<div class="seo-ai-master-plugin">
    <div id="seo-ai-settings-page">
        <!-- Settings Sidebar -->
        <aside class="settings-sidebar">
            <h2 class="settings-title">
                <i class="fas fa-cogs"></i>
                Einstellungen
            </h2>
            
            <ul class="settings-nav">
                <li class="active" data-section="api-settings">
                    <i class="fas fa-key"></i>
                    API Konfiguration
                </li>
                <li data-section="general-settings">
                    <i class="fas fa-sliders-h"></i>
                    Allgemeine Einstellungen
                </li>
                <li data-section="automation-settings">
                    <i class="fas fa-magic"></i>
                    Automation Regeln
                    <span class="update-badge">Neu</span>
                </li>
                <li data-section="performance-settings">
                    <i class="fas fa-tachometer-alt"></i>
                    Performance
                </li>
                <li data-section="backup-settings">
                    <i class="fas fa-shield-alt"></i>
                    Backup & Restore
                </li>
                <li data-section="support-settings">
                    <i class="fas fa-life-ring"></i>
                    Support & Diagnostics
                </li>
            </ul>
        </aside>

        <!-- Hauptinhalt: Nur noch das Einstellungsformular und die Sections -->
        <main class="settings-content">
            <!-- API Configuration Section -->
            <section class="settings-section active" id="api-settings">
                <h2 class="section-title"><i class="fas fa-key"></i>API Konfiguration</h2>
                <p class="section-description">Konfigurieren Sie Ihre AI-Provider API-Keys für optimale Leistung und Redundanz. Das Plugin verwendet automatisch den besten verfügbaren Provider basierend auf Verfügbarkeit und Kosten.</p>
                
                <form method="post" action="" id="seo-ai-api-form">
                    <?php wp_nonce_field('seo_ai_save_settings', 'seo_ai_nonce'); ?>
                    
                    <div class="api-provider-cards">
                        <!-- Claude API -->
                        <div class="api-card <?php echo !empty($options['claude_api_key']) ? 'has-key' : ''; ?>" id="claude-card">
                            <div class="api-card-header">
                                <h3 class="api-provider claude"><i class="fas fa-brain"></i>Claude 3.5 Sonnet (Anthropic)</h3>
                                <span class="api-badge primary">Primär</span>
                            </div>
                            
                            <div class="api-card-body">
                                <label class="form-label">API Key</label>
                                <p class="form-help">Ihren API-Key finden Sie in der Anthropic Console unter "API Keys".</p>
                                <div class="input-group">
                                    <input type="password" class="form-input api-key-input" name="claude_api_key" id="claude_api_key" placeholder="sk-ant-api03-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" value="<?php echo esc_attr($options['claude_api_key'] ?? ''); ?>">
                                    <i class="input-icon fas fa-eye" onclick="togglePasswordVisibility('claude_api_key')"></i>
                                </div>
                                
                                <div class="api-status disconnected" id="claude-status">
                                    Nicht verbunden
                                </div>
                                
                                <button type="button" class="btn" onclick="testApiConnection('claude')">
                                    <i class="fas fa-plug"></i>
                                    <span>Verbindung testen</span>
                                </button>
                                
                                <label class="form-label">Priorität</label>
                                <select class="form-select" name="claude_priority">
                                    <option value="1" <?php selected(($options['claude_priority'] ?? '1'),'1'); ?>>Höchste (1)</option>
                                    <option value="2" <?php selected(($options['claude_priority'] ?? '2'),'2'); ?>>Hoch (2)</option>
                                    <option value="3" <?php selected(($options['claude_priority'] ?? '3'),'3'); ?>>Normal (3)</option>
                                    <option value="4" <?php selected(($options['claude_priority'] ?? '4'),'4'); ?>>Niedrig (4)</option>
                                </select>
                                
                                <div class="api-toggle">
                                    <input type="checkbox" id="claude_enabled" name="claude_enabled" <?php checked(!empty($options['claude_enabled'])); ?>>
                                    <div class="toggle-switch<?php echo !empty($options['claude_enabled']) ? ' active' : ''; ?>" onclick="toggleSwitch(this, 'claude_enabled')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <label for="claude_enabled" class="checkbox-label">API aktiviert</label>
                                </div>
                            </div>
                        </div>

                        <!-- OpenAI API -->
                        <div class="api-card <?php echo !empty($options['openai_api_key']) ? 'has-key' : ''; ?>" id="openai-card">
                            <div class="api-card-header">
                                <h3 class="api-provider openai"><i class="fas fa-robot"></i>GPT-4o & GPT-4 (OpenAI)</h3>
                                <span class="api-badge fallback">Fallback</span>
                            </div>
                            
                            <div class="api-card-body">
                                <label class="form-label">API Key</label>
                                <p class="form-help">Erstellen Sie einen API-Key in Ihrem OpenAI Dashboard.</p>
                                <div class="input-group">
                                    <input type="password" class="form-input api-key-input" name="openai_api_key" id="openai_api_key" placeholder="sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" value="<?php echo esc_attr($options['openai_api_key'] ?? ''); ?>">
                                    <i class="input-icon fas fa-eye" onclick="togglePasswordVisibility('openai_api_key')"></i>
                                </div>
                                
                                <div class="api-status disconnected" id="openai-status">
                                    Nicht verbunden
                                </div>
                                
                                <button type="button" class="btn" onclick="testApiConnection('openai')">
                                    <i class="fas fa-plug"></i>
                                    <span>Verbindung testen</span>
                                </button>
                                
                                <label class="form-label">Bevorzugtes Modell</label>
                                <select class="form-select" name="openai_model">
                                    <option value="gpt-4o-2024-05-13" <?php selected(($options['openai_model'] ?? 'gpt-4o-2024-05-13'),'gpt-4o-2024-05-13'); ?>>GPT-4o (Neuestes)</option>
                                    <option value="gpt-4-turbo-2024-04-09" <?php selected(($options['openai_model'] ?? '') ,'gpt-4-turbo-2024-04-09'); ?>>GPT-4 Turbo</option>
                                    <option value="gpt-4" <?php selected(($options['openai_model'] ?? '') ,'gpt-4'); ?>>GPT-4</option>
                                </select>
                                
                                <div class="api-toggle">
                                    <input type="checkbox" id="openai_enabled" name="openai_enabled" <?php checked(!empty($options['openai_enabled'])); ?>>
                                    <div class="toggle-switch<?php echo !empty($options['openai_enabled']) ? ' active' : ''; ?>" onclick="toggleSwitch(this, 'openai_enabled')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <label for="openai_enabled" class="checkbox-label">API aktiviert</label>
                                </div>
                            </div>
                        </div>

                        <!-- Gemini API -->
                        <div class="api-card <?php echo !empty($options['gemini_api_key']) ? 'has-key' : ''; ?>" id="gemini-card">
                            <div class="api-card-header">
                                <h3 class="api-provider gemini"><i class="fas fa-gem"></i>Gemini Pro (Google)</h3>
                                <span class="api-badge">Kosteneffizient</span>
                            </div>
                            
                            <div class="api-card-body">
                                <label class="form-label">API Key</label>
                                <p class="form-help">Generieren Sie einen API-Key in der Google Cloud Console für Generative AI.</p>
                                <div class="input-group">
                                    <input type="password" class="form-input api-key-input" name="gemini_api_key" id="gemini_api_key" placeholder="AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" value="<?php echo esc_attr($options['gemini_api_key'] ?? ''); ?>">
                                    <i class="input-icon fas fa-eye" onclick="togglePasswordVisibility('gemini_api_key')"></i>
                                </div>
                                
                                <div class="api-status disconnected" id="gemini-status">
                                    Nicht verbunden
                                </div>
                                
                                <button type="button" class="btn" onclick="testApiConnection('gemini')">
                                    <i class="fas fa-plug"></i>
                                    <span>Verbindung testen</span>
                                </button>
                                
                                <div class="api-toggle">
                                    <input type="checkbox" id="gemini_enabled" name="gemini_enabled" <?php checked(!empty($options['gemini_enabled'])); ?>>
                                    <div class="toggle-switch<?php echo !empty($options['gemini_enabled']) ? ' active' : ''; ?>" onclick="toggleSwitch(this, 'gemini_enabled')">
                                        <div class="toggle-slider"></div>
                                    </div>
                                    <label for="gemini_enabled" class="checkbox-label">API aktiviert</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Usage Limits -->
                    <div class="api-card">
                        <div class="api-card-header">
                            <h3 class="api-provider"><i class="fas fa-chart-line"></i>Usage Limits & Kosten</h3>
                        </div>
                        
                        <div class="api-card-body">
                            <label class="form-label">Maximale monatliche Kosten</label>
                            <p class="form-help">Plugin stoppt AI-Generierung automatisch wenn Limit erreicht wird.</p>
                            <input type="number" class="form-input" name="monthly_limit" value="<?php echo esc_attr($options['monthly_limit'] ?? '100'); ?>" min="10" step="10">
                            <span style="color: rgba(255,255,255,0.7); font-size: 0.8rem; margin-top: 0.25rem; display: block;">USD pro Monat</span>
                            
                            <div class="progress-container">
                                <div class="progress-label">
                                    <span>Aktueller Verbrauch</span>
                                    <span>$89.50 / $100.00</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 89.5%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save"></i>
                            <span>Einstellungen speichern</span>
                        </button>
                        <button class="btn btn-secondary" type="button" onclick="testAllAPIs()">
                            <i class="fas fa-check-circle"></i>
                            <span>Alle APIs testen</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Weitere Sections (Platzhalter) -->
            <section class="settings-section" id="general-settings">
                <h2><i class="fas fa-sliders-h"></i> Allgemeine Einstellungen</h2>
                <p>Hier werden die allgemeinen Einstellungen angezeigt...</p>
            </section>
            
            <section class="settings-section" id="automation-settings">
                <h2><i class="fas fa-magic"></i> Automation Regeln</h2>
                <p>Hier werden die Automation-Regeln konfiguriert...</p>
            </section>
            
            <section class="settings-section" id="performance-settings">
                <h2><i class="fas fa-tachometer-alt"></i> Performance</h2>
                <p>Hier werden die Performance-Einstellungen verwaltet...</p>
            </section>
            
            <section class="settings-section" id="backup-settings">
                <h2><i class="fas fa-shield-alt"></i> Backup & Restore</h2>
                <p>Hier können Backups erstellt und wiederhergestellt werden...</p>
            </section>
            
            <section class="settings-section" id="support-settings">
                <h2><i class="fas fa-life-ring"></i> Support & Diagnostics</h2>
                <p>Hier finden Sie Support-Informationen und Diagnose-Tools...</p>
            </section>
        </main>
    </div>
</div>

<script>
// Navigation zwischen Sections
document.addEventListener('DOMContentLoaded', function() {
    const navItems = document.querySelectorAll('.seo-ai-master-plugin .settings-nav li');
    const sections = document.querySelectorAll('.seo-ai-master-plugin .settings-section');
    
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            const targetSection = this.getAttribute('data-section');
            
            // Remove active class from all nav items and sections
            navItems.forEach(nav => nav.classList.remove('active'));
            sections.forEach(section => section.classList.remove('active'));
            
            // Add active class to clicked nav item and corresponding section
            this.classList.add('active');
            document.getElementById(targetSection).classList.add('active');
        });
    });
});

// Toggle Switches
function toggleSwitch(element, inputId) {
    const input = document.getElementById(inputId);
    const isActive = element.classList.contains('active');
    
    if (isActive) {
        element.classList.remove('active');
        input.checked = false;
    } else {
        element.classList.add('active');
        input.checked = true;
    }
}

// Password Visibility Toggle
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling;
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// API Connection Test
function testApiConnection(provider) {
    const button = event.target.closest('.btn');
    const statusElement = document.getElementById(provider + '-status');
    const cardElement = document.getElementById(provider + '-card');
    
    // Set loading state
    button.classList.add('loading');
    button.disabled = true;
    statusElement.className = 'api-status loading';
    statusElement.textContent = 'Teste Verbindung...';
    
    // Simulate API test (replace with real AJAX call)
    setTimeout(() => {
        const success = Math.random() > 0.3; // 70% success rate for demo
        
        button.classList.remove('loading');
        button.disabled = false;
        
        if (success) {
            statusElement.className = 'api-status connected';
            statusElement.textContent = 'Verbunden';
            cardElement.classList.add('connected');
            cardElement.classList.remove('error');
            showToast('Erfolgreich verbunden mit ' + provider.toUpperCase(), 'success');
        } else {
            statusElement.className = 'api-status error';
            statusElement.textContent = 'Verbindungsfehler';
            cardElement.classList.add('error');
            cardElement.classList.remove('connected');
            showToast('Verbindung zu ' + provider.toUpperCase() + ' fehlgeschlagen', 'error');
        }
    }, 2000);
}

// Test All APIs
function testAllAPIs() {
    const button = event.target;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Teste alle APIs...';
    button.disabled = true;
    
    setTimeout(() => {
        button.innerHTML = '<i class="fas fa-check-circle"></i> Alle APIs testen';
        button.disabled = false;
        showToast('Alle API-Tests abgeschlossen!', 'success');
    }, 5000);
}

// Toast Notification System
function showToast(message, type = 'info', duration = 4000) {
    const iconMap = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `seo-ai-toast ${type}`;
    toast.innerHTML = `
        <i class="toast-icon ${iconMap[type]}"></i>
        <div class="toast-content">
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
        <div class="toast-progress"></div>
    `;
    
    document.querySelector('.seo-ai-master-plugin').appendChild(toast);
    
    // Show animation
    setTimeout(() => toast.classList.add('show'), 100);
    
    // Auto remove
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}
</script>