<?php
// Bootstrap für WordPress-Tests
$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _load_seo_ai_master_plugin() {
    require dirname(__DIR__) . '/seo-ai-master.php';
}
tests_add_filter('muplugins_loaded', '_load_seo_ai_master_plugin');

require $_tests_dir . '/includes/bootstrap.php'; 