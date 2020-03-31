<?php

namespace Igaster\LaravelMetrics\Services\Metrics\Segments;

use Carbon\Carbon;

class SegmentLevel
{
    const MINUTE = 1;
    const HOUR = 2;
    const DAY = 3;
    const MONTH = 4;
    const YEAR = 5;

    static $types = [
        self::MINUTE,
        self::HOUR,
        self::DAY,
        self::MONTH,
        self::YEAR,
    ];

    private static $carbonUnits = [
        self::MINUTE => 'minute',
        self::HOUR => 'hour',
        self::DAY => 'day',
        self::MONTH => 'month',
        self::YEAR => 'year',
    ];


    static function startsAt(int $type, Carbon $start): Carbon
    {
        return $start->clone()->startOf(self::carbonUnit($type));
    }

    static function endsAt(int $type, Carbon $start): Carbon
    {
        $unit = self::carbonUnit($type);
        return $start->clone()->startOf($unit)->addUnit($unit);
    }

    static function carbonUnit(int $type)
    {
        if(!self::isValid($type)) {
            throw new \Exception("[$type] is not a valid SegmentType enumeration");
        }

        return self::$carbonUnits[$type];
    }

    static function isValid($value)
    {
        return in_array($value, self::$types);
    }

}