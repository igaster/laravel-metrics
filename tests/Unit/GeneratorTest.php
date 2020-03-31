<?php

namespace Igaster\LaravelMetrics\Tests\Unit;

use Carbon\Carbon;
use DateTime;
use Igaster\LaravelMetrics\Models\ExampleModel;
use Igaster\LaravelMetrics\Tests\App\DummyModel;
use Igaster\LaravelMetrics\Tests\App\GenerateMetricData;
use Igaster\LaravelMetrics\Tests\TestCase;

class GeneratorTest extends TestCase
{

    public function testSamplesGenerator()
    {
        $generator = new GenerateMetricData();

        $result = $generator->generate(10);

        $this->assertEquals(10, count($result));

        $this->assertArrayOfType(Carbon::class, $result);
    }

}
