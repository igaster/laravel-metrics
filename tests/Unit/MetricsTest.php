<?php

namespace Igaster\LaravelMetrics\Tests\Unit;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Models\Metric;
use Igaster\LaravelMetrics\Services\Metrics\MetricSampler;
use Igaster\LaravelMetrics\Services\Metrics\Segments\Segment;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use Igaster\LaravelMetrics\Tests\App\ExampleSamplesProvider;
use Igaster\LaravelMetrics\Tests\TestCase;
use Igaster\LaravelMetrics\Services\Metrics\Sample;
use Illuminate\Database\SQLiteConnection;

class MetricsTest extends TestCase
{

    // -----------------------------------------------
    //  Test Setup (Runs before each test)
    // -----------------------------------------------

    /** @var Metric */
    private $metric;

    public function setUp(): void
    {
        parent::setUp();

        $this->metric = Metric::factory('test-slug', [
            SegmentLevel::HOUR,
            SegmentLevel::MONTH,
            SegmentLevel::DAY,
        ],[
            'size',
            'color',
        ]);
    }

    // -----------------------------------------------
    //  TestS
    // -----------------------------------------------

    public function testUntilIsCalculatedOnSave()
    {
        $timestamp = Carbon::now()->startOfDay();

        $metric = Metric::create([
            'slug' => 'xxx',
            'levels' => [],
            'partitions' => [],
        ]);

        $value = $metric->values()->create([
            'from' => Carbon::parse('2020-01-01 01:00:00'),
            'level' => SegmentLevel::HOUR,
        ]);

        $this->assertEquals(Carbon::parse('2020-01-01 02:00:00'), $value->until);

        $value->update([
            'level' => SegmentLevel::DAY,
        ]);

        $this->assertEquals(Carbon::parse('2020-01-02 00:00:00'), $value->until);

        $value->update([
            'from' => Carbon::parse('2020-01-02 10:00:00'),
        ]);

        $this->assertEquals(Carbon::parse('2020-01-03 00:00:00'), $value->until);
    }

    public function testMetricFactoryStoresNewMetric()
    {
        Metric::factory('xxx');

        $this->assertDatabaseHas('metrics',[
            'slug' => 'xxx',
        ]);
    }

    public function testMetricFactoryLoadsStoredModel()
    {
        $metric = Metric::factory('test-slug');

        $this->assertEquals([
            'color',
            'size',
        ], $metric->partitions);
    }

    public function testMetricFactoryWillNotUpdateLevelsAndPartitions()
    {
        $metric = Metric::factory('test-slug', [
            SegmentLevel::YEAR,
        ],[
            'UPDATES',
            'ARE_NOT',
            'ALLOWED',
        ]);

        $this->assertEquals([
            'color',
            'size',
        ], $metric->partitions);

        $this->assertEquals([
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
            SegmentLevel::MONTH,
        ], $metric->levels);
    }


    public function testMetricLevelsAreAlwaysSorted()
    {
        $metric = Metric::factory('dummy', [
            SegmentLevel::HOUR,
            SegmentLevel::MONTH,
            SegmentLevel::DAY,
        ], []);

        $this->assertEquals([
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
            SegmentLevel::MONTH,
        ], $metric->levels);
    }

    public function testMetricPartitionsAreAlwaysSorted()
    {
        $metric = Metric::factory('dummy', null, [
            'aaa',
            'ccc',
            'bbb',
        ]);

        $this->assertEquals([
            'aaa',
            'bbb',
            'ccc',
        ], $metric->partitions);
    }

    public function testMetricCreateSegment()
    {
        $from = Carbon::parse("2020-01-02 00:00:00");

        $segment = $this->metric->createSegment($from);

        $this->assertEquals(Carbon::parse("2020-01-02 00:00:00"), $segment->from);
        $this->assertEquals(Carbon::parse("2020-01-02 01:00:00"), $segment->until);

        $segment = $this->metric->createSegment($from, SegmentLevel::DAY);

        $this->assertEquals(Carbon::parse("2020-01-02 00:00:00"), $segment->from);
        $this->assertEquals(Carbon::parse("2020-01-03 00:00:00"), $segment->until);
    }

    public function testCreateSegmentFromTimestamp()
    {
        $timestamp = Carbon::parse("2020-01-02 00:10:00");

        $segment = $this->metric->createSegment($timestamp);

        $this->assertEquals(Carbon::parse("2020-01-02 00:00:00"), $segment->from);
        $this->assertEquals(Carbon::parse("2020-01-02 01:00:00"), $segment->until);

        $segment = $this->metric->createSegment(Carbon::parse('2020-01-01 23:00:00'),SegmentLevel::DAY);

        $this->assertEquals(Carbon::parse("2020-01-01 00:00:00"), $segment->from);
        $this->assertEquals(Carbon::parse("2020-01-02 00:00:00"), $segment->until);
    }

    public function testMetricSetsLastSampleTimestampWhenANewSegmentIsAppended()
    {
        $this->metric->createSegment(Carbon::parse('2020-01-01 01:30:00'));

        $this->assertEquals(Carbon::parse('2020-01-01 02:00:00'), $this->metric->last_sample);

        $this->metric->createSegment(Carbon::parse('2020-01-10 00:00:00'), SegmentLevel::MONTH);

        $this->assertEquals(Carbon::parse('2020-02-01 00:00:00'), $this->metric->last_sample);

    }


    public function testMetricValue()
    {
        $this->metric = Metric::where('slug', 'test-slug')->first();

        $segment = $this->metric->createSegment(Carbon::parse('2020-01-01 00:00:00'));

        $this->metric->addSamples([
            new Sample(5),
            new Sample(10),
        ]);

        $this->assertEquals(2, $segment->count());
        $this->assertEquals(15, $segment->value());
    }

    public function testMetricStoresSegment()
    {
        $segment = $this->metric->createSegment(Carbon::parse('2020-01-01 00:00:00'));

        $this->metric->addSamples([
            new Sample(5),
            new Sample(10),
        ]);

        $this->metric->saveCascading();

        $segment2 = Segment::load('test-slug', $segment->from, SegmentLevel::HOUR);

        $this->assertEquals(2, $segment2->count());
        $this->assertEquals(15, $segment2->value());
    }

    public function testMetricHandlesMultipleSegments()
    {
        // 1st Segment
        $segment1 = $this->metric->createSegment(Carbon::parse('2020-01-01 00:00:00'));

        $this->metric->addSamples([
            new Sample(5),
            new Sample(10),
        ]);

        $this->assertEquals(15, $segment1->value());

        // 2nd Segment
        $segment2 = $this->metric->createSegment(Carbon::parse('2020-01-01 01:00:00'));

        $this->metric->addSamples([
            new Sample(20),
            new Sample(30),
        ]);

        $this->assertEquals(50, $segment2->value());
    }

    public function testSegmentPartitionSlugs()
    {
        $metric = Metric::factory('my-segment', [SegmentLevel::DAY], ['a', 'b', 'c']);

        $this->assertEquals('1.2.3', $this->getPartitionSlug($metric, [
            'a' => '1',
            'b' => '2',
            'c' => '3'
        ]));

        $this->assertEquals('2.1.3', $this->getPartitionSlug($metric, [
            'b' => '1',
            'a' => '2',
            'c' => '3'
        ]), 'Partition keys should be sorted alphabetical');

        $this->assertEquals('1.*.3', $this->getPartitionSlug($metric, [
            'a' => '1',
            'c' => '3'
        ]), 'A partition key can be sorted omitted');

        $this->assertEquals('1.*.3', $this->getPartitionSlug($metric, [
            'c' => '3',
            'a' => '1'
        ]));

        $this->assertEquals('slugi-fy.*.me-baby', $this->getPartitionSlug($metric, [
            'a' => ' Slugi fy ',
            'c' => 'me Baby!'
        ]),'Partition values are converted to slugs');

        $this->assertEquals('1.314.100', $this->getPartitionSlug($metric, [
            'a' => 1,
            'b' => 3.14,
            'c' => 100
        ]),'Partition values can be numbers');

        $this->assertEquals('1.0.*', $this->getPartitionSlug($metric, [
            'a' => true,
            'b' => false,
        ]),'Partition values can be boolean');

        $this->assertEquals('*.[NULL].*', $this->getPartitionSlug($metric, [
            'b' => null
        ]),'Partition values can be null');

        $this->assertEquals('*..*', $this->getPartitionSlug($metric, [
            'b' => ''
        ]),'Partition values can be empty string');
    }

    private function getPartitionSlug(Metric $metric, array $partitions)
    {
        return $this->callPrivateMethod($metric, 'getPartitionSlug', $partitions);
    }

}
