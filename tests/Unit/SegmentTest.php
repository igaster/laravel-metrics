<?php

namespace Igaster\LaravelMetrics\Tests\Unit;

use Carbon\Carbon;
use DateTime;
use Faker\Factory;
use Igaster\LaravelMetrics\Models\ExampleModel;
use Igaster\LaravelMetrics\Models\Metric;
use Igaster\LaravelMetrics\Services\Metrics\Segments\Combinations;
use Igaster\LaravelMetrics\Services\Metrics\Segments\Segment;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use Igaster\LaravelMetrics\Tests\App\DummyModel;
use Igaster\LaravelMetrics\Tests\App\GenerateMetricData;
use Igaster\LaravelMetrics\Tests\TestCase;
use Igaster\LaravelMetrics\Services\Metrics\Sample;
use ReflectionClass;

class SegmentTest extends TestCase
{
    public function testSegmentCountAndValue()
    {
        $segment = Segment::create('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY, [
            'size',
            'color',
        ]);

        $segment->addSample(new Sample(5, [
            'size' => 'small',
            'color' => 'blue',
        ]));

        $segment->addSample(new Sample(10, [
            'color' => 'blue',
            'size' => 'small',
        ]));

        $segment->addSample(new Sample(20, [
            'size' => 'small',
            'color' => 'red',
        ]));

        // Count
        $this->assertEquals(3, $segment->count());

        $this->assertEquals(3, $segment->count(['size' => 'small']));
        $this->assertEquals(0, $segment->count(['size' => 'INVALID']));
        $this->assertEquals(0, $segment->count(['INVALID' => 'small']));
        $this->assertEquals(2, $segment->count(['color' => 'blue']));
        $this->assertEquals(1, $segment->count(['color' => 'red']));

        $this->assertEquals(2, $segment->count(['size' => 'small', 'color' => 'blue']));
        $this->assertEquals(0, $segment->count(['size' => 'small', 'color' => 'INVALID']));
        $this->assertEquals(0, $segment->count(['size' => 'small', 'INVALID' => 'blue']));

        $this->assertEquals(0, $segment->count(['size' => 'small', 'color' => 'blue', 'XXX' => 'XXX']));

        // Value
        $this->assertEquals(35, $segment->value());

        $this->assertEquals(35, $segment->value(['size' => 'small']));
        $this->assertEquals(0, $segment->value(['size' => 'INVALID']));
        $this->assertEquals(0, $segment->value(['INVALID' => 'small']));
        $this->assertEquals(15, $segment->value(['color' => 'blue']));
        $this->assertEquals(20, $segment->value(['color' => 'red']));

        $this->assertEquals(15, $segment->value(['size' => 'small', 'color' => 'blue']));
        $this->assertEquals(0, $segment->value(['size' => 'small', 'color' => 'INVALID']));
        $this->assertEquals(0, $segment->value(['size' => 'small', 'INVALID' => 'blue']));

        $this->assertEquals(0, $segment->count(['size' => 'small', 'color' => 'blue', 'XXX' => 'XXX']));
    }

    public function testSegmentAddMultipleSamples()
    {
        $segment = Segment::create('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY, [
            'size',
            'color',
        ]);

        $segment->addSamples( [
            new Sample(5, [
                'size' => 'small',
                'color' => 'blue',
            ]),

            new Sample(10, [
                'color' => 'blue',
                'size' => 'small',
            ]),
        ]);

        $this->assertEquals(2, $segment->count());
        $this->assertEquals(15, $segment->value());
    }

    public function testSegmentMayHaveNoPartitions()
    {
        $segment = Segment::create('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY);

        $segment->addSamples([
            new Sample(5),
            new Sample(10),
        ]);

        $this->assertEquals(2, $segment->count());
        $this->assertEquals(15, $segment->value());
    }

    public function testSegmentPartitionCanBeNull()
    {
        $segment = Segment::create('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY, [
            'size',
            'color',
            'NOT_USED'
        ]);

        $segment->addSample(new Sample(5, [
            'size' => 'small',
            'color' => 'blue',
        ]));

        $segment->addSample(new Sample(5, [
            'size' => 'small',
            'color' => 'red',
            'NOT_USED' => null,
        ]));

        $this->assertEquals(2, $segment->count(['size' => 'small']));
        $this->assertEquals(1, $segment->count(['color' => 'red']));
        $this->assertEquals(0, $segment->count(['NOT_USED' => 'DUMMY']));
        $this->assertEquals(2, $segment->count(['NOT_USED' => null]));
    }

    public function testSegmentPartitionCanBeModelInstance()
    {
        $segment = Segment::create('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY, [
            'model',
            'color'
        ]);

        $model1 = DummyModel::create(['id' => 10]);
        $model2 = DummyModel::create(['id' => 11]);

        $segment->addSample(new Sample(5, [
            'model' => $model1,
            'color' => 'red',
        ]));

        $segment->addSample(new Sample(10, [
            'model' => $model1,
            'color' => 'blue',
        ]));

        $segment->addSample(new Sample(20, [
            'model' => $model2,
            'color' => 'blue',
        ]));

        $this->assertEquals(2, $segment->count(['model' => $model1]));
        $this->assertEquals(1, $segment->count(['model' => $model2]));
        $this->assertEquals(3, $segment->count());

        $this->assertEquals(10, $segment->value(['color' => 'blue', 'model' => $model1]));
        $this->assertEquals(30, $segment->value(['color' => 'blue']));
    }

    public function testSamplePartitionsCanBeArrayOfValues()
    {
        $segment = Segment::create('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY, [
            'size',
            'color',
        ]);

        $segment->addSamples( [
            new Sample(5, ['blue', 'small']),

            new Sample(10, ['red']),


            new Sample(20, [
                'color' => 'red',
                'size' => 'small',
            ]),
        ]);

        $this->assertEquals(35, $segment->value());
        $this->assertEquals(25, $segment->value(['size' => 'small']));
        $this->assertEquals(30, $segment->value(['color' => 'red']));
    }

    // ToDo: Can we fix this? Respect original order?
    public function testSamplePartitionsCanBeArrayOfValuesInAlphabeticPartitionOrder()
    {
        $segment = Segment::create('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY, [
            'bb',
            'aa',
            'cc',
        ]);

        $segment->addSamples([
            new Sample(1, ['value-a', 'value-b', 'value-c']),
            new Sample(2, [null, 'value-b2', 'value-c']),
        ]);

        $this->assertEquals(1, $segment->value(['aa' => 'value-a']));
        $this->assertEquals(1, $segment->value(['bb' => 'value-b']));
        $this->assertEquals(2, $segment->value(['bb' => 'value-b2']));
        $this->assertEquals(3, $segment->value(['cc' => 'value-c']));
    }

    public function testSamplePartitionsCanBeNonArray()
    {
        $segment = Segment::create('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY, [
            'color',
            'size'
        ]);

        $segment->addSamples( [
            new Sample(5, 'red'),
            new Sample(10, 'blue'),

            new Sample(20, [
                'color' => 'red',
                'size' => 'large'
            ]),
        ]);

        $this->assertEquals(35, $segment->value());
        $this->assertEquals(25, $segment->value(['color' => 'red']));
        $this->assertEquals(20, $segment->value(['size' => 'large']));
    }


    public function testSegmentSaveAndLoad()
    {
        // Create 1st Segment
        $segment1 = Segment::create('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY, [
            'size',
            'color',
        ]);

        $segment1->addSample(new Sample(5, [
            'size' => 'small',
            'color' => 'blue',
        ]));

        $segment1->addSample(new Sample(10, [
            'size' => 'small',
            'color' => 'red',
        ]));

        // Create 2nd Segment
        $segment2 = Segment::create('other-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY);

        $segment2->addSample(new Sample(20, null));

        $segment2->addSample(new Sample(30, null));

        // Store
        $segment1->save();

        $segment2->save();

        // Load 1st Segment
        $segment3 = Segment::load('my-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY);

        $this->assertEquals($segment1->count, $segment3->count);

        $this->assertEquals($segment1->value, $segment3->value);

        // Load 2nd Segment
        $segment4 = Segment::load('other-segment', Carbon::now()->startOfDay(), SegmentLevel::DAY);

        $this->assertEquals($segment2->count, $segment4->count);

        $this->assertEquals($segment2->value, $segment4->value);
    }
}
