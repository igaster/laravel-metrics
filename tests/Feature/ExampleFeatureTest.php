<?php

namespace Igaster\LaravelMetrics\Tests\Feature;

use Igaster\LaravelMetrics\Tests\TestCase;

class ExampleFeatureTest extends TestCase
{

    public function testRequest()
    {
        $response = $this->json('GET', "/package/example");

        $response->assertOk()
            ->assertJson([
                'message' => 'success',
            ]);
    }

}
