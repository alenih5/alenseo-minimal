<?php

namespace Alenseo;

// Füge die WordPress-Bootstrap-Datei hinzu
require_once dirname(__DIR__) . '/wp-tests-config.php';

use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class RestApiTest extends TestCase
{
    public function testGenerateKeywordsEndpoint()
    {
        $request = new WP_REST_Request('POST', '/alenseo/v1/generate-keywords');
        $request->set_param('prompt', 'test prompt');

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status(), 'The REST API should return a 200 status code.');

        $data = $response->get_data();
        $this->assertArrayHasKey('keywords', $data, 'The response should contain a keywords key.');
    }
}
