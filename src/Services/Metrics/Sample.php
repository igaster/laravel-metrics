<?php


namespace Igaster\LaravelMetrics\Services\Metrics;


use Carbon\Carbon;

class Sample
{
    public $timestamp;

    public $value = null;

    public $partitions = [];

    public function __construct($value = null, $partitions = [], Carbon $timestamp = null)
    {
        $this->timestamp = $timestamp;

        $this->value = $value;

        if($partitions) {

            $partitions = is_array($partitions) ? $partitions : [$partitions];

            $this->withPartitions($partitions);
        }
    }

    public function addPartition($name, $value)
    {
        $this->partitions[$name] = $value;
    }


    public function withPartitions(array $data=[])
    {
        $this->partitions = $data;

        return $this;
    }

}