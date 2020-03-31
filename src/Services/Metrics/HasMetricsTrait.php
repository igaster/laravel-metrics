<?php

namespace Igaster\LaravelMetrics\Services\Metrics;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Models\Metric;
use Illuminate\Database\Eloquent\Builder;

/**
 * This trait can be optionally added to a model that implements the MetricsInterface
 * It will help creating the query to select valid items, and provide a simple interface to create the Samples
 * It assumes that current model has a "created_at" column which stores the event timestamp.
 * Column name can be configured to any other column by declaring a $samplesTimestampColumn property
 */
trait HasMetricsTrait {

    /**
     * You can customize the query that will get the sampled items.
     * You don't have filter items based on their timestamp for current time slot.
     * These will be automatically selected based on the "created_at" value
     */
    public abstract function getSamplesQuery(Metric $metric): Builder;

    /**
     * Transforms current eloquent model to a Sample
     */
    public abstract function makeSample(): Sample;

    /**
     * Implementation of sample() method, defined at MetricsInterface
     */
    public function sample(Metric $metric, Carbon $from, Carbon $until): array
    {
        $timestamp = isset($this->samplesTimestampColumn) ? $this->samplesTimestampColumn : 'created_at';

        $samples = [];

        $this->getSamplesQuery($metric)
            ->where($timestamp, '>=', $from)
            ->where($timestamp, '<', $until)
            ->chunk(100, function ($items) use (&$samples) {
                foreach ($items as $item) {
                    $samples[] = $item->makeSample();
                }
            });

        return $samples;
    }
}