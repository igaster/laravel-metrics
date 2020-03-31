<?php

namespace Igaster\LaravelMetrics\Tests\App;

use Igaster\LaravelMetrics\Models\Metric;
use Igaster\LaravelMetrics\Services\Metrics\HasMetricsTrait;
use Igaster\LaravelMetrics\Services\Metrics\MetricsInterface;
use Igaster\LaravelMetrics\Services\Metrics\Sample;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExampleSamplesModel extends Model implements MetricsInterface
{
    use HasMetricsTrait;

    protected $table = 'test_table';

    protected $guarded = [];

    private $samplesTimestampColumn = 'created_at'; // This is the default. Declaration can be omitted

    public function registerMetrics(): array
    {
        return [
            Metric::factory('slug-1', [
                SegmentLevel::HOUR,
                SegmentLevel::DAY,
            ], [
                'color'
            ]),
        ];
    }

    /**
     * You can customize the query that will create the segments.
     * You don't have filter items based on their timestamp for current time slot.
     * These will be automatically selected based on the "created_at" value
     */
    public function getSamplesQuery(Metric $metric): Builder
    {
        return self::query()
            ->select(['quantity','color']);
    }

    /**
     * Transforms current eloquent model to a Sample
     */
    public function makeSample(): Sample
    {
        return new Sample(
            $this->quantity,
            [
                'color' => $this->color,
            ]
        );
    }
}
