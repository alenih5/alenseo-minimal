<?php

// Path to the WordPress codebase you'd like to test.
define( 'ABSPATH', dirname( __FILE__ ) . '/wordpress/' );

// Test database settings.
define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'WP_TESTS_TABLE_PREFIX', 'wptests_' );

// Allow WordPress to be installed.
define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );
define( 'WP_DEBUG', true );

// Path to the WordPress tests library.
if ( ! defined( 'WP_TESTS_DIR' ) ) {
    define( 'WP_TESTS_DIR', '/tmp/wordpress-tests-lib' );
}

require_once WP_TESTS_DIR . '/includes/functions.php';

// Load the plugin.
function _manually_load_plugin() {
    require dirname( __FILE__ ) . '/alenseo-minimal.php';
}

// Hook the plugin loading function.
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require WP_TESTS_DIR . '/includes/bootstrap.php';
