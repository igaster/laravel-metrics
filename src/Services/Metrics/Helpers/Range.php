<?php


namespace Igaster\LaravelMetrics\Services\Metrics\Helpers;


use Carbon\Carbon;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;

class Range
{
    public $level;
    public $from;
    public $until;

    public function __construct(Carbon $from, Carbon $until, int $level)
    {
        $this->level = $level;
        $this->from = $from;
        $this->until = $until;
    }

    public function toString()
    {
        return $this->from->toDateTimeString().' | '.$this->until->toDateTimeString().' | '.SegmentLevel::carbonUnit($this->level);
    }
}