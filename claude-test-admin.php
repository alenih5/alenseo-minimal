<?php
// Admin-Menü für Claude-Test

add_action('admin_menu', function() {
    add_menu_page(
        'Claude Test',
        'Claude Test',
        'manage_options',
        'claude-test',
        'alenseo_render_claude_test_page',
        'dashicons-admin-generic'
    );
});

function alenseo_render_claude_test_page() {
    echo '<div class="wrap">';
    echo '<h1>Claude API Test</h1>';
    echo '<p>Hier kannst du die Claude API testen.</p>';
    echo '</div>';
}
