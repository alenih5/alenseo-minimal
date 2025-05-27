<?php
if (!defined('ABSPATH')) exit;
?>
<div class="seo-ai-master-plugin">
    <main class="settings-content">
        <section class="content-section active" id="urls-section">
            <h1 class="section-title">
                <i class="fas fa-link"></i>
                Seiten optimieren
            </h1>
            <p class="section-description">
                Hier können Sie alle relevanten Seiten für die SEO-Optimierung verwalten, analysieren und priorisieren.
            </p>
            <div class="settings-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Seiten-Liste</h3>
                    <button class="btn btn-primary"><i class="fas fa-plus"></i> Neue Seite hinzufügen</button>
                </div>
                <table class="settings-table">
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Status</th>
                            <th>SEO-Score</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>/beispiel-seite</td>
                            <td><span class="card-badge success">Optimiert</span></td>
                            <td>92</td>
                            <td>
                                <button class="btn btn-secondary"><i class="fas fa-edit"></i> Bearbeiten</button>
                                <button class="btn btn-danger"><i class="fas fa-trash"></i> Löschen</button>
                            </td>
                        </tr>
                        <!-- Weitere Zeilen dynamisch ausgeben -->
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div> 