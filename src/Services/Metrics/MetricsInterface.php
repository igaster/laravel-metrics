<?php

namespace Igaster\LaravelMetrics\Services\Metrics;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Models\Metric;

interface MetricsInterface
{
    /**
     * Register a list of Metrics that this class will provide.
     * Returns an array of Metric
     */
    public function registerMetrics(): array;

    /**
     * Return an array of Samples that have a timestamp >= $from (inclusive) and < $until (exclusive)
     * It will be called once for each $metric, every time that it's shortest sampling period has been completed (ie  every minute/day etc)
     */
    public function sample(Metric $metric, Carbon $from, Carbon $until): array;
}