[![Laravel](https://img.shields.io/badge/Laravel-orange.svg)](http://laravel.com)
[![License](http://img.shields.io/badge/license-MIT-brightgreen.svg)](https://tldrlegal.com/license/mit-license)
[![Downloads](https://img.shields.io/packagist/dt/igaster/laravel-metrics.svg)](https://packagist.org/packages/igaster/laravel-metrics)
[![Build Status](https://img.shields.io/travis/igaster/laravel-metrics.svg)](https://travis-ci.org/igaster/laravel-metrics)
[![Codecov](https://img.shields.io/codecov/c/github/igaster/laravel-metrics.svg)](https://codecov.io/github/igaster/laravel-metrics)

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

### `registerMetrics()` example:

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

### `sample()` example:

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

You may **optionally** use the `HasMetricsTrait` in your models you want to sample. This trait automates the eloquent query, and provides a convenient interface to transform your models to samples. This is an example:

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
            ->where('status','=','published') // Add your business logic...
            ->select([
                'quantity', // It is a good practice to get only the columns that are required in makeSample()
                'color' 
            ]);
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

# Getting the samples

### 1) Create a "Sampler" object:
 
 - Each sampler is attached to a "Samples Provider" which will be probed
 - You can create the Sampler either from an object, or from a Model classname:

```php
// a) Create a sampler from an object that implements MetricsInterface

$samplesProvider = new ExampleSamplesProvider(); 

$sampler = new MetricSampler($samplesProvider);

// b) Create a sampler a Model that implements MetricsInterface (It doesn't have to be an object instance)

$sampler = new MetricSampler(SomeModel::class);
```

### 2) Get and process samples for some time-slots. These requirements must be met:

- A time-slot must be completed in order to get valid results (ie you can get samples from last hour, but not from current hour)
- Time is linear: Time-slots must be processed in sequential order. Samples within a time-slot can be fetched in any order because they are processed as a batch.

```php
// Sample a single timeslot that starts at a timestamp.
// If a metric doesn't have a time-slot that starts at current timestamp, it will be skipped.
$sampler->sample($timestamp);

// Sample for a period of time
$sampler->samplePeriod($from, $until);

// Continue sampling since last sample was taken, and stop at some timestamp.
// If this is the 1st time that a metric is processed then current timestamp is initialized as starting time
// Only metrics that have a whole time-slot completed since last execution will be executed.
// $until doesn't have to match with the end of a time-slot. The end of the latest time-slot for each metric will be calculated and used. 
// You should design your system to call this method in intervals
$sampler->sampleUntil($until);
```

# Querying Metrics

### Count/Sum

Get count/sum of samples within a period. Partitions can be specified

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
### Get by hour/day/month etc

```php
// Available methods:
$metric->getByMinute($from, $until);
$metric->getByHour($from, $until);
$metric->getByDay($from, $until);
$metric->getByMonth($from, $until);
$metric->getByYear($from, $until);
```

Example:
```php

Metric::get('slug-1')->getByDay(
    Carbon::parse('2020-01-01 00:00:00'),
    Carbon::parse('2020-01-02 10:00:00')
);

//  Result is a collection for every day:
//  [
//      [
//          "from" => "2020-01-01 00:00:00",
//          "until" => "2020-01-02 00:00:00",
//          "count" => 72,
//          "value" => 144.0,
//      ],
//      [
//          "from" => "2020-01-02 00:00:00",
//          "until" => "2020-01-03 00:00:00",
//          "count" => 72,
//          "value" => 144.0,
//      ],
//     ...
//  ];
```
