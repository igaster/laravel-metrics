<?php

namespace Igaster\LaravelMetrics\Tests\Unit;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Models\Metric;
use Igaster\LaravelMetrics\Services\Metrics\MetricSampler;
use Igaster\LaravelMetrics\Services\Metrics\Segments\Segment;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use Igaster\LaravelMetrics\Tests\App\ExampleSamplesModel;
use Igaster\LaravelMetrics\Tests\App\ExampleSamplesProvider;
use Igaster\LaravelMetrics\Tests\TestCase;
use Igaster\LaravelMetrics\Services\Metrics\Sample;
use Illuminate\Database\SQLiteConnection;
use Mockery\Mock;
use Mockery\MockInterface;

class SamplerTest extends TestCase
{
    // -----------------------------------------------
    //  Tests
    // -----------------------------------------------

    public function testCallClassSampleMethod()
    {
        $samplesProvider = $this->mock(ExampleSamplesProvider::class)->makePartial();

        $samplesProvider->shouldReceive('sample')
            ->times(3);

        $sampler = new MetricSampler($samplesProvider);

        // Sample called for 2 metrics (hour + day)
        $sampler->sample(Carbon::parse('2020-01-01 00:00:00'));

        // Sample called for 1st metric only (hour)
        $sampler->sample(Carbon::parse('2020-01-01 01:00:00'));

        // Sample not called
        $sampler->sample(Carbon::parse('2020-01-01 01:02:04'));
    }


    public function testCallClassSampleMethodUpdatesLastSampleTimestampOnEachMetric()
    {
        $samplesProvider = new ExampleSamplesProvider();

        $sampler = new MetricSampler($samplesProvider);

        // Sample called for 2 metrics (hour + day)
        $sampler->sample(Carbon::parse('2020-01-01 00:00:00'));
        $this->assertEquals(Carbon::parse('2020-01-01 01:00:00'),Metric::get('slug-1')->last_sample);
        $this->assertEquals(Carbon::parse('2020-01-02 00:00:00'),Metric::get('slug-2')->last_sample);

        // Sample called for 1st metric only (hour)
        $sampler->sample(Carbon::parse('2020-01-01 01:00:00'));
        $this->assertEquals(Carbon::parse('2020-01-01 02:00:00'),Metric::get('slug-1')->last_sample);

    }


    public function testCallClassSampleMethodUntil()
    {
        // Add previous Samples until 2020-01-01 00:00:00
        $samplesProvider = new ExampleSamplesProvider();

        $sampler = new MetricSampler($samplesProvider);

        $sampler->sample(Carbon::parse('2019-12-31 00:00:00'));
        $sampler->sample(Carbon::parse('2019-12-31 23:00:00'));

        // Mock
        $samplesProvider = $this->mock(ExampleSamplesProvider::class)->makePartial();

        $sampler = new MetricSampler($samplesProvider);

        // Samples: [metric1 = 24 x hours] +  [metric2 = 1 x day] = 25 samples
        $samplesProvider->shouldReceive('sample')
            ->times(24 + 1);

        $sampler->sampleUntil(Carbon::parse('2020-01-02 00:00:00'));
    }

    public function testSamplingFromAClass()
    {
        $samplesProvider = new ExampleSamplesProvider();

        $sampler = new MetricSampler($samplesProvider);

        $sampler->sample(Carbon::parse('2020-01-01 00:00:00'));

        $sampler->sample(Carbon::parse('2020-01-01 01:00:00'));

        $this->assertEquals(10, Metric::get('slug-1')->value(
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-01 02:00:00'),
            ['size' => 'small']
        ));

        $this->assertEquals(6, Metric::get('slug-1')->count(
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-01 02:00:00')
        ));

        $this->assertEquals(1, Metric::get('slug-2')->count(
            Carbon::parse('2020-01-01'),
            Carbon::parse('2020-01-02')
        ));
    }

    public function testSamplingUntilFromAClass()
    {
        $samplesProvider = new ExampleSamplesProvider();

        $sampler = new MetricSampler($samplesProvider);

        // Assume an old sample
        $sampler->sample(Carbon::parse('2020-01-01 00:00:00'));

        // Sample until a future time (4 days + 10 hours total)
        $sampler->sampleUntil(Carbon::parse('2020-01-05 10:00:00'));

        // hours
        $this->assertEquals(3 * (24 * 4 + 10),  Metric::get('slug-1')->count(
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-05 10:00:00')
        ));

        // days
        $this->assertEquals(1*4,  Metric::get('slug-2')->count(
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-05 10:00:00')
        ));
    }

    public function testSampleUntilForFirstTimeSetMetricLastTimestamp()
    {
        $samplesProvider = new ExampleSamplesProvider();

        $sampler = new MetricSampler($samplesProvider);

        $sampler->sampleUntil(Carbon::parse('2020-01-02 10:00:00'));

        // hours
        $this->assertEquals(Carbon::parse('2020-01-02 10:00:00'),  Metric::get('slug-1')->last_sample);

        // days
        $this->assertEquals(Carbon::parse('2020-01-02 00:00:00'),  Metric::get('slug-2')->last_sample);
    }

    public function testSampleUntilForMultipleTimes()
    {
        $samplesProvider = new ExampleSamplesProvider();

        $sampler = new MetricSampler($samplesProvider);

        // 1st time. Nothing to sample
        $sampler->sampleUntil(Carbon::parse('2020-01-01 00:00:00'));

        // 2nd time. 1 day
        $sampler->sampleUntil(Carbon::parse('2020-01-02 00:00:00'));

        // hours
        $this->assertEquals(3 * 24,  Metric::get('slug-1')->count(
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-02 00:00:00')
        ));

        // days
        $this->assertEquals(1,  Metric::get('slug-2')->count(
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-02 00:00:00')
        ));
    }

    public function testSamplingFromAModel()
    {
        // Setup sampler

        $samplesModel = new ExampleSamplesModel();

        // $sampler = new MetricSampler($samplesModel);

        $sampler = new MetricSampler(ExampleSamplesModel::class);

        // Seed some data
        $this->timeFreeze(Carbon::parse('2020-01-01 00:00:00'));

        // 50 hours = 2 days + 2hours
        for($i = 0; $i < 50; $i++) {
            ExampleSamplesModel::create([
                'quantity' => 10,
                'color' => $i % 2 ?  'red' : 'green',
            ]);

            $this->timeAddHours(1);
        }

        // Assume an old sample
        $sampler->sample(Carbon::parse('2020-01-01 00:00:00'));

        // Sample until a future time (3 days)
        $sampler->sampleUntil(Carbon::parse('2020-01-04 00:00:00'));

        $this->assertEquals(50 * 10,  Metric::get('slug-1')->value(
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-04 00:00:00')
        ));

        $this->assertEquals(6,  Metric::get('slug-1')->count(
            Carbon::parse('2020-01-01 00:00:00'),
            Carbon::parse('2020-01-01 12:00:00'), [
                'color' => 'red'
            ]
        ));

    }

}
