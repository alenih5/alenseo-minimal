<?php

namespace Alenseo;

use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class SeoReportApiTest extends TestCase
{
    public function testSeoReportEndpoint()
    {
        $request = new WP_REST_Request('GET', '/alenseo/v1/seo-report');

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status(), 'The REST API should return a 200 status code.');

        $data = $response->get_data();
        $this->assertArrayHasKey('seo_score', $data, 'The response should contain an seo_score key.');
        $this->assertArrayHasKey('top_keywords', $data, 'The response should contain a top_keywords key.');
        $this->assertArrayHasKey('page_speed', $data, 'The response should contain a page_speed key.');
    }
}
