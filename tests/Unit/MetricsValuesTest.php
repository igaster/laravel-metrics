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

class MetricsValuesTest extends TestCase
{
    // -----------------------------------------------
    //  Test Setup (Runs before each test)
    // -----------------------------------------------

    public function setUp(): void
    {
        parent::setUp();

        $metric = Metric::factory('test-slug', [
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
        ],[
            'color',
            'size'
        ]);
    }

    private function seedSet_1()
    {
        $metric = Metric::factory('test-slug');

        // 1st timestamp
        $metric->createSegment(Carbon::parse('2020-01-01 01:00:00'), SegmentLevel::HOUR)
            ->addSamples([
                new Sample(1, 'blue'),
                new Sample(2, 'red'),
            ])->save();

        // 2nd timestamp is continuous with previous
        $metric->createSegment(Carbon::parse('2020-01-01 02:00:00'), SegmentLevel::HOUR)
            ->addSamples([
                new Sample(1, 'blue'),
                new Sample(2, 'red'),
            ])->save();

        // 3nd timestamp is not continuous with previous
        $metric->createSegment(Carbon::parse('2020-01-01 10:00:00'), SegmentLevel::HOUR)
            ->addSamples([
                new Sample(1, 'blue'),
                new Sample(2, 'red'),
            ])->save();

        return $metric;
    }

    private function seedSet_2()
    {
        $metric = Metric::factory('test-slug');

        $timestamp = Carbon::parse('2019-11-21 00:00:00');

        for($i = 0 ; $i <= (10+366+10); $i++) {
            $metric->createSegment($timestamp, SegmentLevel::HOUR)
                ->addSamples([
                    new Sample(1, 'blue'),
                    new Sample(2, 'red'),
                ])->save();

            $timestamp->addDay();
        }

        return $metric;
    }
    // -----------------------------------------------
    //  Tests
    // -----------------------------------------------

    public function testGetValueForDateRangeAndLevel()
    {
        $metric = $this->seedSet_1();

        $this->assertEquals(3, $this->callPrivateMethod($metric, 'getForLevel',
            'value',
            SegmentLevel::HOUR,
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-02 00:00:00'),
            'blue'
        ), 'Range is a superset of samples');

        $this->assertEquals(9, $this->callPrivateMethod($metric, 'getForLevel',
            'value',
            SegmentLevel::HOUR,
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-02 00:00:00'),
            []
        ), 'Range is a superset of samples: All Partitions');

        $this->assertEquals(0, $this->callPrivateMethod($metric, 'getForLevel',
            'value',
            SegmentLevel::HOUR,
            Carbon::parse('2020-01-02 00:00:00'),
            Carbon::parse('2020-01-02 00:00:00'),
            'blue'
        ), 'Range is zero');

        $this->assertEquals(0, $this->callPrivateMethod($metric, 'getForLevel',
            'value',
            SegmentLevel::HOUR,
            Carbon::parse('2020-12-01 00:00:00'),
            Carbon::parse('2020-12-31 00:00:00'),
            'blue'
        ), 'Range doesnt intersect with samples');

        $this->assertEquals(1, $this->callPrivateMethod($metric, 'getForLevel',
            'value',
            SegmentLevel::HOUR,
            Carbon::parse('2020-01-01 02:00:00'),
            Carbon::parse('2020-01-01 03:00:00'),
            'blue'
        ), 'Range is a subset of samples.');
    }

    public function testGetCountForDateRangeAndLevel()
    {
        $metric = $this->seedSet_1();

        $this->assertEquals(3, $this->callPrivateMethod($metric, 'getForLevel',
            'count',
            SegmentLevel::HOUR,
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-02 00:00:00'),
            'blue'
        ),'Range is a superset of samples: Count');

        $this->assertEquals(6, $this->callPrivateMethod($metric, 'getForLevel',
            'count',
            SegmentLevel::HOUR,
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-02 00:00:00'),
            []
        ),'Range is a superset of samples: No partitions');
    }

    public function testGetValueUsingMultipleLevels()
    {
        $metric = Metric::factory('test-slug', [
            SegmentLevel::DAY,
            SegmentLevel::MONTH,
            SegmentLevel::YEAR,
        ],[
            'color'
        ]);

        $from = Carbon::parse('2019-11-21 00:00:00');
        $timestamp = $from->clone();

        for($i = 0 ; $i < (10+31+366+31+10); $i++) { // = 448 days
            $metric->createSegment($timestamp, SegmentLevel::DAY)
                ->addSample(new Sample(10,['color' => 'red']))
                ->save();
            $timestamp->addDay();
        }

        $this->assertEquals(448, $metric->count($from, $timestamp));

        $this->assertEquals(4480, $metric->value($from, $timestamp));

        $this->assertEquals(448, $metric->count($from, $timestamp,'red'));

        $this->assertEquals(0, $metric->count($from, $timestamp,'blue'));

        $this->assertEquals(20, $metric->count(
            $from->clone()->addDays(50),
            $from->clone()->addDays(70)),
            'Period is a subset / Spans for Days');

        $this->assertEquals(100, $metric->count(
            $from->clone()->addDays(50),
            $from->clone()->addDays(150)),
            'Period is a subset / Spans for Days+Months');

        $this->assertEquals(448, $metric->count($from->clone()->subDays(10), $timestamp),'Period starts in the past (days)');

        $this->assertEquals(448, $metric->count($from->clone()->subMonths(4), $timestamp),'Period starts in the past (months)');

        $this->assertEquals(448, $metric->count($from->clone()->subYears(4), $timestamp),'Period starts in the past (years)');

        $this->assertEquals(448, $metric->count($from, $timestamp->clone()->addDays(3)),'Period ends in the future (days)');

        // ToDo: Constrain the upper limit to the last sample for current metric. Use cache!
        // $this->assertEquals(448, $metric->count($from, $timestamp->clone()->addMonth()),'Period ends in the future (months)');
        // $this->assertEquals(448, $metric->count($from, $timestamp->clone()->addYear()),'Period ends in the future (year)');
    }

}
