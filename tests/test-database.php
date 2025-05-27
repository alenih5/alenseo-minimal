<?php
/**
 * Tests für die Database-Klasse
 */
class DatabaseTest extends WP_UnitTestCase {
    public function test_singleton_instance() {
        $db1 = \SEOAI\Database::get_instance();
        $db2 = \SEOAI\Database::get_instance();
        $this->assertInstanceOf(\SEOAI\Database::class, $db1);
        $this->assertSame($db1, $db2);
    }

    public function test_save_and_get_seo_data() {
        $db = \SEOAI\Database::get_instance();
        $post_id = $this->factory->post->create();
        $sample = ['test' => 'value', 'analyzed_at' => current_time('mysql')];
        $db->save_seo_data($post_id, $sample);
        $data = $db->get_seo_data($post_id);
        $this->assertEquals($sample, $data);
    }
} 