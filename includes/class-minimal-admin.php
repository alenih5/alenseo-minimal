<?php

class Minimal_Admin {

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'alenseo-batch-analyzer-filters',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/batch-analyzer-filters.js',
            array('jquery'),
            $this->version,
            true
        );
    }
} 