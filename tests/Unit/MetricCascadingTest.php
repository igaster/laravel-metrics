<?php

namespace Igaster\LaravelMetrics\Tests\Unit;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Models\Metric;
use Igaster\LaravelMetrics\Models\MetricValue;
use Igaster\LaravelMetrics\Services\Metrics\Segments\Segment;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use Igaster\LaravelMetrics\Tests\App\ExampleSamplesProvider;
use Igaster\LaravelMetrics\Tests\TestCase;
use Igaster\LaravelMetrics\Services\Metrics\Sample;
use Illuminate\Database\SQLiteConnection;

class MetricCascadingTest extends TestCase
{
    // -----------------------------------------------
    //  Tests
    // -----------------------------------------------

    public function testSaveCascadesToHigherLevel()
    {
        /** @var Metric $metric */
        $metric = Metric::factory('test-slug', [
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
        ], [
            'color',
        ]);

        $timestamp = Carbon::parse('2020-01-01 00:00:00');

        for ($i = 0; $i < 24; $i++) {
            $metric->createSegment(Carbon::parse($timestamp, SegmentLevel::HOUR))
                ->addSample(new Sample(10, 'red'))
                ->addSample(new Sample(20, 'blue'))
                ->save();
            $timestamp->addHour();
        }

        $this->assertDatabaseHas('metric_values', [
            'metric_id' => $metric->id,
            'from' => Carbon::parse('2020-01-01 00:00:00'),
            'until' => Carbon::parse('2020-01-02 00:00:00'),
            'level' => SegmentLevel::DAY,
        ]);

        $this->assertEquals(240,
            MetricValue::where([
                'metric_id' => $metric->id,
                'partition_key' => 'red',
                'from' => Carbon::parse('2020-01-01 00:00:00'),
                'until' => Carbon::parse('2020-01-02 00:00:00'),
                'level' => SegmentLevel::DAY,
            ])->first()->value);
    }


    public function testSaveCascadesThreeLevels()
    {
        /** @var Metric $metric */
        $metric = Metric::factory('test-slug', [
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
            SegmentLevel::MONTH,
        ]);

        $metric->createSegment(Carbon::parse(Carbon::parse('2020-01-31 23:00:00'), SegmentLevel::HOUR))
            ->addSample(new Sample(10, 'red'))
            ->addSample(new Sample(20, 'blue'))
            ->save();

        $this->assertDatabaseHas('metric_values', [
            'metric_id' => $metric->id,
            'from' => Carbon::parse('2020-01-31 23:00:00'),
            'until' => Carbon::parse('2020-02-01 00:00:00'),
            'level' => SegmentLevel::HOUR,
        ]);

        $this->assertDatabaseHas('metric_values', [
            'metric_id' => $metric->id,
            'from' => Carbon::parse('2020-01-31 00:00:00'),
            'until' => Carbon::parse('2020-02-01 00:00:00'),
            'level' => SegmentLevel::DAY,
        ]);
        $this->assertDatabaseHas('metric_values', [
            'metric_id' => $metric->id,
            'from' => Carbon::parse('2020-01-01 00:00:00'),
            'until' => Carbon::parse('2020-02-01 00:00:00'),
            'level' => SegmentLevel::MONTH,
        ]);
    }

    public function testLoadSegmentValuesFromLowerLevelSegments()
    {
        /** @var Metric $metric */
        $metric = Metric::factory('test-slug', [
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
        ], [
            'color',
        ]);

        $timestamp = Carbon::parse('2020-01-01 00:00:00');

        for ($i = 0; $i < 10; $i++) {
            $metric->createSegment(Carbon::parse($timestamp, SegmentLevel::HOUR))
                ->addSample(new Sample(10, 'red'))
                ->addSample(new Sample(20, 'blue'))
                ->save();
            $timestamp->addHour();
        }

        $segment = $metric->createSegment($timestamp, SegmentLevel::DAY);
        $segment->loadValuesFromLevel(SegmentLevel::HOUR);

        $this->assertEquals(100,  $segment->value(['color' => 'red']));
        $this->assertEquals(300,  $segment->value());

        $this->assertEquals(10,  $segment->count(['color' => 'red']));
        $this->assertEquals(20,  $segment->count());
    }



}
