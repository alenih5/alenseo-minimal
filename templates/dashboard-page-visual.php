<?php
/**
 * Modernes Visual Dashboard-Template für Alenseo SEO
 *
 * Zeigt eine grafische Übersicht aller Seiten und Beiträge mit SEO-Status an
 * 
 * @link       https://www.imponi.ch
 * @since      1.0.0
 *
 * @package    Alenseo
 * @subpackage Alenseo/templates
 */

// Direkter Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// API-Konfiguration prüfen
$settings = get_option('alenseo_settings', []);
$claude_api_key = isset($settings['claude_api_key']) ? $settings['claude_api_key'] : '';
$openai_api_key = isset($settings['openai_api_key']) ? $settings['openai_api_key'] : '';
$api_configured = !empty($claude_api_key) || !empty($openai_api_key);

// Basisstatistiken
$total_posts = wp_count_posts(['post', 'page']);
$total_published = $total_posts->publish ?? 0;

// Globale Dashboard-Instanz für Helper-Funktionen sicherstellen
global $alenseo_dashboard;
if (!isset($alenseo_dashboard) || !is_a($alenseo_dashboard, 'Alenseo_Dashboard')) {
    if (class_exists('Alenseo_Dashboard')) {
        $alenseo_dashboard = new Alenseo_Dashboard();
    }
}

// Übersichtsdaten abrufen - mit Fallback
$overview_data = [];
if ($alenseo_dashboard && method_exists($alenseo_dashboard, 'get_overview_data')) {
    $overview_data = $alenseo_dashboard->get_overview_data();
}

$score = isset($overview_data['average_score']) ? (int)$overview_data['average_score'] : 0;
$health = isset($overview_data['average_health']) ? (int)$overview_data['average_health'] : 0;
$last_audit = date_i18n('d.m.Y'); // Platzhalter, ggf. dynamisch ersetzen
?>

<div class="wrap alenseo-dashboard-wrap">
    <h1><span class="dashicons dashicons-dashboard"></span> <?php _e('Alenseo SEO Dashboard', 'alenseo'); ?></h1>
    
    <!-- Notices Container -->
    <div class="alenseo-notices"></div>
    
    <!-- API Status Section -->
    <div class="alenseo-stat-card">
        <h3><?php _e('API-Status', 'alenseo'); ?></h3>
        <div class="api-status-loading" style="display: none;">
            <span class="alenseo-loading"></span>
            <span class="alenseo-loading-text"><?php _e('Lade Status...', 'alenseo'); ?></span>
        </div>
        <div class="api-status-result" style="display: none;"></div>
        
        <?php if (!$api_configured): ?>
        <div class="notice notice-warning inline">
            <p><?php _e('Keine API konfiguriert. Bitte konfigurieren Sie mindestens eine API in den Einstellungen.', 'alenseo'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=alenseo-settings'); ?>" class="button button-primary"><?php _e('Zu den Einstellungen', 'alenseo'); ?></a></p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Dashboard Statistics -->
    <div class="alenseo-dashboard-stats">
        <div class="alenseo-stat-card stat-total-posts">
            <h3><?php _e('Gesamte Posts/Seiten', 'alenseo'); ?></h3>
            <div class="stat-number"><?php echo $total_published; ?></div>
            <div class="stat-description"><?php _e('Veröffentlichte Inhalte', 'alenseo'); ?></div>
        </div>
        
        <div class="alenseo-stat-card stat-analyzed-posts">
            <h3><?php _e('Analysierte Posts', 'alenseo'); ?></h3>
            <div class="stat-number">0</div>
            <div class="stat-description"><?php _e('Mit SEO-Analyse', 'alenseo'); ?></div>
        </div>
        
        <div class="alenseo-stat-card stat-avg-score">
            <h3><?php _e('Durchschnittlicher Score', 'alenseo'); ?></h3>
            <div class="stat-number">0</div>
            <div class="stat-description"><?php _e('SEO-Bewertung', 'alenseo'); ?></div>
        </div>
        
        <div class="alenseo-stat-card stat-optimization-rate">
            <h3><?php _e('Optimierungsrate', 'alenseo'); ?></h3>
            <div class="stat-number">0%</div>
            <div class="stat-description"><?php _e('Analysierte Inhalte', 'alenseo'); ?></div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="alenseo-stat-card">
        <h3><?php _e('Letzte Aktivitäten', 'alenseo'); ?></h3>
        <div class="recent-activity-list">
            <p class="alenseo-loading-text"><?php _e('Lade Aktivitäten...', 'alenseo'); ?></p>
        </div>
    </div>
?>

<div class="alenseo-dashboard-modern">
  <div class="alenseo-dashboard-header">
    <button class="alenseo-btn-primary"><?php _e('Analyze Website', 'alenseo'); ?></button>
    <span class="alenseo-last-audit"><?php _e('Last Audit:', 'alenseo'); ?> <?php echo esc_html($last_audit); ?></span>
  </div>
  <div class="alenseo-dashboard-cards">
    <div class="alenseo-card">
      <canvas id="scoreChart" width="120" height="120"></canvas>
      <div class="alenseo-card-label"><?php _e('Average Score', 'alenseo'); ?></div>
    </div>
    <div class="alenseo-card">
      <canvas id="healthChart" width="120" height="120"></canvas>
      <div class="alenseo-card-label"><?php _e('Health', 'alenseo'); ?></div>
    </div>
    <div class="alenseo-card alenseo-card-wide">
      <canvas id="trendChart" height="120"></canvas>
      <div class="alenseo-card-label"><?php _e('Score & Health Trends', 'alenseo'); ?></div>
    </div>
  </div>
  <div class="alenseo-dashboard-alerts">
    <div class="alenseo-alerts-col">
      <div class="alenseo-alert-title alenseo-alert-error"><?php _e('Errors', 'alenseo'); ?> (<?php echo count($errors); ?>)</div>
      <?php foreach ($errors as $err): ?>
        <div class="alenseo-alert alenseo-alert-error">
          <a href="<?php echo esc_url($err['link']); ?>"><strong><?php echo esc_html($err['count']); ?> <?php _e('Pages', 'alenseo'); ?></strong> <?php echo esc_html($err['text']); ?></a>
        </div>
      <?php endforeach; ?>
      <a href="#" class="alenseo-alert-viewall"><?php _e('View All', 'alenseo'); ?> &raquo;</a>
    </div>
    <div class="alenseo-alerts-col">
      <div class="alenseo-alert-title alenseo-alert-warning"><?php _e('Warnings', 'alenseo'); ?> (<?php echo count($warnings); ?>)</div>
      <?php foreach ($warnings as $warn): ?>
        <div class="alenseo-alert alenseo-alert-warning">
          <a href="<?php echo esc_url($warn['link']); ?>"><strong><?php echo esc_html($warn['count']); ?> <?php _e('Posts', 'alenseo'); ?></strong> <?php echo esc_html($warn['text']); ?></a>
        </div>
      <?php endforeach; ?>
      <a href="#" class="alenseo-alert-viewall"><?php _e('View All', 'alenseo'); ?> &raquo;</a>
    </div>
  </div>
</div>

<!-- Chart.js einbinden -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Dashboard JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Score Chart
  new Chart(document.getElementById('scoreChart').getContext('2d'), {
    type: 'doughnut',
    data: {
      datasets: [{
        data: [<?php echo $score; ?>, <?php echo 100-$score; ?>],
        backgroundColor: ['#3498db', '#eaeaea'],
        borderWidth: 0
      }]
    },
    options: {
      cutout: '80%',
      plugins: { legend: { display: false } },
      responsive: false
    }
  });
  // Health Chart
  new Chart(document.getElementById('healthChart').getContext('2d'), {
    type: 'doughnut',
    data: {
      datasets: [{
        data: [<?php echo $health; ?>, <?php echo 100-$health; ?>],
        backgroundColor: ['#2ecc71', '#eaeaea'],
        borderWidth: 0
      }]
    },
    options: {
      cutout: '80%',
      plugins: { legend: { display: false } },
      responsive: false
    }
  });
  // Trend Chart (Dummy-Daten)
  new Chart(document.getElementById('trendChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: ['Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez', 'Jan', 'Feb', 'Mrz'],
      datasets: [
        {
          label: 'Score',
          data: [60, 65, 70, 68, 75, 80, 78, 82, 80],
          borderColor: '#3498db',
          backgroundColor: 'rgba(52,152,219,0.1)',
          tension: 0.4,
          fill: true
        },
        {
          label: 'Health',
          data: [70, 72, 75, 77, 80, 85, 83, 88, 88],
          borderColor: '#2ecc71',
          backgroundColor: 'rgba(46,204,113,0.1)',
          tension: 0.4,
          fill: true
        }
      ]
    },
    options: {
      plugins: { legend: { position: 'top' } },
      responsive: true,
      scales: { y: { min: 0, max: 100 } }
    }
  });
});
</script>