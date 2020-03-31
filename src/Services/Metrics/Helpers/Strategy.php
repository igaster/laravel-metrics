<?php

namespace Igaster\LaravelMetrics\Services\Metrics\Helpers;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;

class Strategy
{
    private $levels = [];

    public function __construct(array $levels)
    {
        sort($levels);
        $this->levels = $levels;
    }

    public static function create(array $levels = []): self
    {
        return new self($levels);
    }

    public function calculateRanges(Carbon $from, Carbon $until)
    {
        $ranges = [];
        foreach ($this->levels as $level)
        {
            $range = $this->getMaxRange($level, $from, $until);
            if($range) {
                $ranges[$level] = $range;
            }
        }

        $result = [];
        $cursor = reset($ranges)[0];
        $cursor_level = array_keys($ranges)[0];
        foreach ($ranges as $level => $range) {
            if($cursor < $range[0]) {
                $result[] = new Range($cursor, $range[0], $cursor_level);
                $cursor=$range[0];
            }
            $cursor_level = $level;
        }
        foreach (array_reverse($ranges, true) as $level => $range) {
            if($cursor<$range[1]) {
                $result[] = new Range($cursor, $range[1], $level);
                $cursor=$range[1];
            }
        }

        return $result;
    }

    public function getMaxRange(int $level, Carbon $from, Carbon $until)
    {
        $unit = SegmentLevel::carbonUnit($level);

        $start = SegmentLevel::startsAt($level, $from);
        if($start != $from) {
            $start->addUnit($unit);
        }

        $end = SegmentLevel::endsAt($level, $until);
        if($end != $until) {
            $end->subUnit($unit);
        }

        if($start >= $end) {
            return false;
        }

        return [$start, $end];
    }

}