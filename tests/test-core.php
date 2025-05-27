<?php
/**
 * Tests für die Core-Klasse
 */
class CoreTest extends WP_UnitTestCase {
    public function test_get_instance_singleton() {
        $instance1 = \SEOAI\Core::get_instance();
        $instance2 = \SEOAI\Core::get_instance();
        $this->assertInstanceOf(\SEOAI\Core::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    public function test_activation_creates_tables() {
        // Simuliere Aktivierung
        \SEOAI\Core::activate();
        global $wpdb;
        $table = $wpdb->prefix . 'seo_ai_data';
        $this->assertEquals($table, $wpdb->get_var("SHOW TABLES LIKE '{$table}'"));
    }
} 