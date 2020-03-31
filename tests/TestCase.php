<?php

namespace Igaster\LaravelMetrics\Tests;

use Carbon\Carbon;
use Dotenv\Dotenv;
use Faker\Factory;
use Igaster\LaravelMetrics\PackageServiceProvider;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Constraint\IsInstanceOf;
use ReflectionClass;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------
    //  Global Setup (Run once)
    // -----------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->load();
        }

        // ...
    }

    public static function tearDownAfterClass(): void
    {
        // ...
        parent::tearDownAfterClass();
    }


    // -----------------------------------------------
    //  Test Setup (Runs before each test)
    // -----------------------------------------------

    public function setUp(): void
    {
        parent::setUp();

        if (DB::connection() instanceof SQLiteConnection) {
            DB::statement(DB::raw('PRAGMA foreign_keys=on'));
        }

        $this->loadMigrationsFrom(__DIR__ . '/App/migrations');

        //...
    }

    public function tearDown(): void
    {
        //...

        parent::tearDown();
    }

    // -----------------------------------------------
    //   Laravel Configuration
    // -----------------------------------------------

    /*
    * Manually set configuration for testing
    * Note: Usually configuration is loaded from phpunit.xml or .env file
    */
    protected function getEnvironmentSetUp($app)
    {
        $config = $app['config'];

        $config->set('key', 'value');
    }

    // -----------------------------------------------
    //   Service Providers & Facades
    // -----------------------------------------------

    protected function getPackageProviders($app)
    {
        return [
            PackageServiceProvider::class,
            // Intervention\Image\ImageServiceProvider::class,
        ];
    }


    protected function getPackageAliases($app)
    {
        return [
            // 'Image' => Intervention\Image\Facades\Photo::class,
        ];
    }

    // -----------------------------------------------
    //   Assert Helpers
    // -----------------------------------------------

    /*
     * Assert that all items of an array are instances of a certain type
     */
    protected function assertArrayOfType(string $type, array $array = [])
    {
        foreach ($array as $item) {
            static::assertThat($item, new IsInstanceOf($type));
        }
    }

    /*
     * Call a private/protected method from an object.
     * ie imagine calling a private method:
     *      $result = $myObject->privateMethodName($arg1, $arg2)
     * Now this is possible with:
     *      $result = $this->callPrivateMethod($myObject, 'privateMethodName', $arg1, $arg2);
    */
    protected function callPrivateMethod($object, string $method, ...$params)
    {
        $class = new ReflectionClass($object);
        $method = $class->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $params);
    }

    protected function generateRandomDate(Carbon $from = null, Carbon $until = null): Carbon
    {
        $from = $from ?: Carbon::now()->startOfDay();
        $until = $until ?: Carbon::now()->endOfDay();

        $faker = Factory::create();

        return Carbon::instance($faker->dateTimeBetween($from, $until));
    }

    /*
     *  Manipulate time.
    */
    protected function timeFreeze(Carbon $timeStamp)
    {
        $timeStamp = $timeStamp ?: Carbon::now();

        Carbon::setTestNow($timeStamp);
    }

    protected function timeAddSeconds($count)
    {
        Carbon::setTestNow(Carbon::now()->addSeconds($count));
    }

    protected function timeAddMinutes($count)
    {
        Carbon::setTestNow(Carbon::now()->addMinutes($count));
    }

    protected function timeAddHours($count)
    {
        Carbon::setTestNow(Carbon::now()->addHours($count));
    }

    protected function timeAddDays($count)
    {
        Carbon::setTestNow(Carbon::now()->addDays($count));
    }


}
