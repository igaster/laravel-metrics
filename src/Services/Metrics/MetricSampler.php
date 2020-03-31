<?php

namespace Igaster\LaravelMetrics\Services\Metrics;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Models\Metric;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use phpDocumentor\Reflection\Types\Mixed_;

class MetricSampler
{
    /** @var MetricsInterface  */
    private $samplesProvider;

    /**
     * @param MetricsInterface|string $samplesProvider An object or a class name that implements the MetricsInterface
     */
    public function __construct($samplesProvider)
    {
        if(is_string($samplesProvider)) {
            $this->setSamplesProvider(new $samplesProvider());
        } else {
            $this->setSamplesProvider($samplesProvider);
        }
    }

    public function sample(Carbon $timestamp_start)
    {
        /** @var Metric $metric */
        foreach ($this->getMetrics() as $metric) {

            if($timestamp_start == SegmentLevel::startsAt($metric->levels[0], $timestamp_start)) {

                $this->sampleMetric($metric, $timestamp_start);

            }
        }
    }


    public function samplePeriod(Carbon $from, Carbon $until)
    {
        /** @var Metric $metric */
        foreach ($this->getMetrics() as $metric) {

            if(!$metric->last_sample || $metric->last_sample < $from) {

                $metric->update([

                    'last_sample' => $from

                ]);
            }
        }

        $this->sampleUntil($until);
    }

    public function sampleUntil(Carbon $timestamp)
    {
        /** @var Metric $metric */
        foreach ($this->getMetrics() as $metric) {

            $until = SegmentLevel::startsAt($metric->levels[0], $timestamp);

            if(!$metric->last_sample) {

                $metric->update([

                    'last_sample' => $until

                ]);

            } else {

                while($metric->last_sample < $timestamp) {

                    $this->sampleMetric($metric, $metric->last_sample);

                }
            }
        }
    }

    private function setSamplesProvider(MetricsInterface $samplesProvider)
    {
        $this->samplesProvider = $samplesProvider;
    }

    private function getMetrics(): array
    {
        return $this->samplesProvider->registerMetrics();
    }

    private function sampleMetric(Metric $metric, Carbon $from)
    {
        $until = SegmentLevel::endsAt($metric->levels[0], $from);

        $samples = $this->samplesProvider->sample($metric, $from, $until);

        $metric->createSegment($from);

        $metric->addSamples($samples);

        $metric->saveCascading();
    }
}