<?php


namespace Igaster\LaravelMetrics\Tests\App;


use Carbon\Carbon;
use Igaster\LaravelMetrics\Models\Metric;
use Igaster\LaravelMetrics\Services\Metrics\MetricsInterface;
use Igaster\LaravelMetrics\Services\Metrics\Sample;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;

class ExampleSamplesProvider implements MetricsInterface
{
    public function registerMetrics(): array
    {
        return [
            Metric::factory('slug-1', [
                SegmentLevel::HOUR,
                SegmentLevel::DAY,
                SegmentLevel::MONTH,
            ], ['size']),

            Metric::factory('slug-2', [
                SegmentLevel::DAY,
                SegmentLevel::MONTH,
            ]),
        ];
    }

    public function sample(Metric $metric, Carbon $from, Carbon $until): array
    {
        if ($metric->slug == 'slug-1') {

            return [
                new Sample(1, ['size' => 'large']),
                new Sample(2, ['size' => 'small']),
                new Sample(3, ['size' => 'small']),
            ];

        } elseif ($metric->slug == 'slug-2') {

            return [
                new Sample(10),
            ];

        }

        return [];
    }
}