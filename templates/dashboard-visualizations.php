<?php
/**
 * Template für die Dashboard-Visualisierungen
 * Enthält alle Diagramme und interaktiven Elemente
 */
?>

<div class="alenseo-dashboard-visualizations">
    <!-- Filter-Bereich -->
    <div class="alenseo-filters">
        <div class="filter-group">
            <label for="status-filter"><?php _e('Status', 'alenseo'); ?></label>
            <select id="status-filter" class="filter-select">
                <option value=""><?php _e('Alle', 'alenseo'); ?></option>
                <option value="optimized"><?php _e('Optimiert', 'alenseo'); ?></option>
                <option value="to-improve"><?php _e('Zu verbessern', 'alenseo'); ?></option>
                <option value="no-keywords"><?php _e('Ohne Keywords', 'alenseo'); ?></option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="score-filter"><?php _e('Score', 'alenseo'); ?></label>
            <select id="score-filter" class="filter-select">
                <option value=""><?php _e('Alle', 'alenseo'); ?></option>
                <option value="0-50"><?php _e('0-50', 'alenseo'); ?></option>
                <option value="51-70"><?php _e('51-70', 'alenseo'); ?></option>
                <option value="71-100"><?php _e('71-100', 'alenseo'); ?></option>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="date-filter"><?php _e('Zeitraum', 'alenseo'); ?></label>
            <select id="date-filter" class="filter-select">
                <option value=""><?php _e('Alle', 'alenseo'); ?></option>
                <option value="today"><?php _e('Heute', 'alenseo'); ?></option>
                <option value="week"><?php _e('Letzte Woche', 'alenseo'); ?></option>
                <option value="month"><?php _e('Letzter Monat', 'alenseo'); ?></option>
                <option value="year"><?php _e('Letztes Jahr', 'alenseo'); ?></option>
            </select>
        </div>
    </div>

    <!-- Hauptvisualisierungen -->
    <div class="alenseo-visualizations-grid">
        <!-- Status-Verteilung -->
        <div class="visualization-box">
            <h3><?php _e('Status-Verteilung', 'alenseo'); ?></h3>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
            <div class="chart-legend">
                <span class="legend-item optimized"><?php _e('Optimiert', 'alenseo'); ?></span>
                <span class="legend-item to-improve"><?php _e('Zu verbessern', 'alenseo'); ?></span>
                <span class="legend-item no-keywords"><?php _e('Ohne Keywords', 'alenseo'); ?></span>
            </div>
        </div>

        <!-- Performance-Chart -->
        <div class="visualization-box">
            <h3><?php _e('Performance-Trend', 'alenseo'); ?></h3>
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Keyword-Verteilung -->
        <div class="visualization-box">
            <h3><?php _e('Keyword-Verteilung', 'alenseo'); ?></h3>
            <div class="chart-container">
                <canvas id="keywordDistributionChart"></canvas>
            </div>
        </div>

        <!-- Content-Heatmap -->
        <div class="visualization-box">
            <h3><?php _e('Content-Heatmap', 'alenseo'); ?></h3>
            <div class="heatmap-container">
                <div id="contentHeatmap"></div>
            </div>
        </div>

        <!-- Keyword-Wolke -->
        <div class="visualization-box">
            <h3><?php _e('Keyword-Wolke', 'alenseo'); ?></h3>
            <div class="wordcloud-container">
                <canvas id="keywordCloud"></canvas>
            </div>
        </div>

        <!-- SEO-Score-Trend -->
        <div class="visualization-box">
            <h3><?php _e('SEO-Score-Trend', 'alenseo'); ?></h3>
            <div class="chart-container">
                <canvas id="seoTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Interaktive Tabelle -->
    <div class="alenseo-content-table-container">
        <table class="content-table">
            <thead>
                <tr>
                    <th class="sort-header" data-column="title" data-direction="asc">
                        <?php _e('Titel', 'alenseo'); ?>
                    </th>
                    <th class="sort-header" data-column="status" data-direction="asc">
                        <?php _e('Status', 'alenseo'); ?>
                    </th>
                    <th class="sort-header" data-column="score" data-direction="desc">
                        <?php _e('Score', 'alenseo'); ?>
                    </th>
                    <th class="sort-header" data-column="date" data-direction="desc">
                        <?php _e('Letzte Analyse', 'alenseo'); ?>
                    </th>
                    <th><?php _e('Aktionen', 'alenseo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Hier werden die Beiträge dynamisch eingefügt
                ?>
            </tbody>
        </table>
    </div>

    <!-- Benachrichtigungen -->
    <div class="alenseo-notices"></div>
</div>

<style>
.alenseo-dashboard-visualizations {
    padding: 20px;
}

.alenseo-filters {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    padding: 15px;
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filter-group {
    flex: 1;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.filter-select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.alenseo-visualizations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.visualization-box {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.visualization-box h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: #23282d;
}

.chart-container {
    position: relative;
    height: 250px;
}

.heatmap-container,
.wordcloud-container {
    height: 300px;
    position: relative;
}

.chart-legend {
    margin-top: 15px;
    text-align: center;
}

.legend-item {
    display: inline-block;
    margin: 0 10px;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
}

.legend-item.optimized {
    background: #46b450;
    color: #fff;
}

.legend-item.to-improve {
    background: #ffb900;
    color: #fff;
}

.legend-item.no-keywords {
    background: #dc3232;
    color: #fff;
}

.alenseo-content-table-container {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow-x: auto;
}

.content-table {
    width: 100%;
    border-collapse: collapse;
}

.content-table th,
.content-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.sort-header {
    cursor: pointer;
    position: relative;
}

.sort-header:after {
    content: '↕';
    margin-left: 5px;
    opacity: 0.3;
}

.sort-header[data-direction="asc"]:after {
    content: '↑';
    opacity: 1;
}

.sort-header[data-direction="desc"]:after {
    content: '↓';
    opacity: 1;
}

.alenseo-notices {
    position: fixed;
    top: 32px;
    right: 20px;
    z-index: 9999;
}

.notice {
    margin: 5px 0;
    padding: 10px 15px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.notice-success {
    background: #46b450;
    color: #fff;
}

.notice-error {
    background: #dc3232;
    color: #fff;
}
</style> 