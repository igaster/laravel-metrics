<?php

namespace Igaster\LaravelMetrics\Tests\Unit;

use Igaster\LaravelMetrics\Models\ExampleModel;
use Igaster\LaravelMetrics\Tests\App\DummyModel;
use Igaster\LaravelMetrics\Tests\App\ExampleSamplesModel;
use Igaster\LaravelMetrics\Tests\TestCase;

class ExampleUnitTest extends TestCase
{

    // -----------------------------------------------
    //   Global Setup(Run Once)
    // -----------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Your Code here...
    }

    public static function tearDownAfterClass(): void
    {
        // Your Code here...
        parent::tearDownAfterClass();
    }

    // -----------------------------------------------
    //  Run before each Test
    // -----------------------------------------------

    public function setUp(): void
    {
        parent::setUp();
        // Your Code here...
    }

    public function tearDown(): void
    {
        // Your Code here...
        parent::tearDown();
    }

    // -----------------------------------------------
    //  Tests
    // -----------------------------------------------

    public function testPackageModel()
    {
        $model = ExampleModel::create([
            'key' => 'value',
        ]);

        $model->refresh();
        $this->assertEquals("value", $model->key);
    }

    public function testTestModel()
    {
        $model = DummyModel::create([
            'quantity' => 100,
        ]);

        $model->refresh();
        $this->assertEquals(100, $model->quantity);
    }
}
