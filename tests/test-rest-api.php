<?php
/**
 * Tests für REST API Endpunkte
 */
class RestApiTest extends WP_UnitTestCase {
    public function test_analyze_post_endpoint() {
        $post_id = $this->factory->post->create();
        $request = new \WP_REST_Request('POST', '/seo-ai/v1/analyze-post');
        $request->set_param('post_id', $post_id);
        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_generate_meta_endpoint() {
        $post_id = $this->factory->post->create();
        $request = new \WP_REST_Request('POST', '/seo-ai/v1/generate-meta');
        $request->set_param('post_id', $post_id);
        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('description', $data);
    }

    public function test_optimize_content_endpoint() {
        $post_id = $this->factory->post->create(['post_content'=>'Test Content']);
        $request = new \WP_REST_Request('POST', '/seo-ai/v1/optimize-content');
        $request->set_param('post_id', $post_id);
        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('content', $data);
    }

    public function test_dashboard_stats_endpoint() {
        $request = new \WP_REST_Request('GET', '/seo-ai/v1/dashboard-stats');
        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('analyzed', $data);
        $this->assertArrayHasKey('not_analyzed', $data);
    }
} 