[![Laravel](https://img.shields.io/badge/Laravel-orange.svg)](http://laravel.com)
[![License](http://img.shields.io/badge/license-MIT-brightgreen.svg)](https://tldrlegal.com/license/mit-license)
[![Downloads](https://img.shields.io/packagist/dt/igaster/GITHUB_ADDRESS.svg)](https://packagist.org/packages/igaster/GITHUB_ADDRESS)
[![Build Status](https://img.shields.io/travis/igaster/GITHUB_ADDRESS.svg)](https://travis-ci.org/igaster/GITHUB_ADDRESS)
[![Codecov](https://img.shields.io/codecov/c/github/igaster/GITHUB_ADDRESS.svg)](https://codecov.io/github/igaster/GITHUB_ADDRESS)

# Introduction

# Configuration

# Samples provider

This is a class that it is responsible for configuring a Metric, and providing samples

```php
use Carbon\Carbon;
use Igaster\LaravelMetrics\Models\Metric;
use Igaster\LaravelMetrics\Services\Metrics\MetricsInterface;
use Igaster\LaravelMetrics\Services\Metrics\Sample;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;

class ExampleSamplesProvider implements MetricsInterface
{
    /**
     * Register a list of Metrics that this class will provide.
     * Returns an array of Metric
     */
    public function registerMetrics(): array 
    {
        // ...
    }

    /**
     * Return an array of Samples that have a timestamp >= $from (inclusive) and < $until (exclusive)
     * It will be called once for each $metric, every time that it's shortest sampling period has been completed (ie  every minute/day etc)
     */
    public function sample(Metric $metric, Carbon $from): array
    {
        // ...
    }
```

#### Sample `registerMetrics()` implementation:

This method configure any number of Metrics

```php
public function registerMetrics(): array 
{
    return [
        Metric::factory('metric-slug', [
            SegmentLevel::HOUR,             // Aggregation levels
            SegmentLevel::DAY,
            SegmentLevel::MONTH,
        ], [
            'size',                         // Partition names (keys)
            'color',
        ]),
    ];
}
```

#### Sample `sample()` implementation:

This method will be executed at the end of the lowest sampling segment for every metric (ie every hour/day etc). It should return an array of Samples that are created from each event that occurred during this period

```php
public function sample(Metric $metric, Carbon $from, Carbon $until): array
{
    return [
        new Sample(4, [ // Each sample may have a value
            'size' => 'large',
            'color' => 'blue',
        ]),
        new Sample(5, [
            'size' => 'large',
            // a partition can be skipped. Means "any value"
        ]),
        new Sample(6, [
            'size' => 'large',
            'color' => null, // Null is a normal value. it is not the same with skipping it (=any value) 
        ]),
        // ...
    ];
}
```

# Sample an Eloquent model

```php
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

    /** @var string $samplesTimestampColumn Sets column that will be treated as a timestamp */
    private $samplesTimestampColumn = 'created_at'; // This is the default. Declaration can be omitted

    /**
     * Register a list of Metrics that this class will provide.
     * Returns an array of Metric
     */
    public function registerMetrics(): array
    {
        return [
            Metric::factory('metric-slug', [
                SegmentLevel::HOUR,
                SegmentLevel::DAY,
            ], [
                'color'
            ]),
        ];
    }

    /**
     * You can customize the query that will get the sampled items.
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
```

# Querying Metrics

```php
// Get count of events that occurred between two timestamps
// and have size=small, and color=red
Metric::get('metric-slug')->count(
    Carbon::parse('2020-01-01 00:00:00'),
    Carbon::parse('2020-01-01 02:00:00'),
    [
        'size' => 'small',
        'color' => 'red'
    ]
));

// Get total (sum) of events values that occurred between two timestamps
// and have size=small, and color=ANY (includes all color values)
Metric::get('metric-slug')->value(
    Carbon::parse('2020-01-01 00:00:00'),
    Carbon::parse('2020-01-01 02:00:00'),
    [
        'size' => 'small',
    ]
));

// Get count of events that occurred between two timestamps
// Any partition
Metric::get('metric-slug')->count(
    Carbon::parse('2020-01-01 00:00:00'),
    Carbon::parse('2020-01-01 02:00:00')
));
``` 

