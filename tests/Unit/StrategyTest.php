<?php

namespace Igaster\LaravelMetrics\Tests\Unit;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Services\Metrics\Helpers\Range;
use Igaster\LaravelMetrics\Services\Metrics\Helpers\Strategy;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use Igaster\LaravelMetrics\Tests\TestCase;

class StrategyTest extends TestCase
{
    public function testMaxRange()
    {
        $this->assertEquals([
            Carbon::parse('2020-01-10 00:00:00'),
            Carbon::parse('2020-01-20 00:00:00'),
        ], Strategy::create()->getMaxRange(
            SegmentLevel::DAY,
            Carbon::parse('2020-01-10 00:00:00'),
            Carbon::parse('2020-01-20 00:00:00')
        ));

        $this->assertEquals([
            Carbon::parse('2020-01-11 00:00:00'),
            Carbon::parse('2020-01-20 00:00:00'),
        ], Strategy::create()->getMaxRange(
            SegmentLevel::DAY,
            Carbon::parse('2020-01-10 05:00:00'),
            Carbon::parse('2020-01-20 06:00:00')
        ));

        $this->assertEquals(false, Strategy::create()->getMaxRange(
            SegmentLevel::DAY,
            Carbon::parse('2020-01-10 05:00:00'),
            Carbon::parse('2020-01-10 06:00:00')
        ));

        $this->assertEquals([
            Carbon::parse('2020-02-01 00:00:00'),
            Carbon::parse('2020-03-01 00:00:00'),
        ], Strategy::create()->getMaxRange(
            SegmentLevel::MONTH,
            Carbon::parse('2020-01-10 05:00:00'),
            Carbon::parse('2020-03-20 06:00:00')
        ));
    }

    public function testBreakRange()
    {
        $ranges= array_map(function (Range $range) {
            return $range->toString();
        }, Strategy::create([
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
        ])->calculateRanges(
            Carbon::parse('2020-01-05 04:50:00'),
            Carbon::parse('2020-01-10 20:10:00')
        ));

        $this->assertEquals([
            "2020-01-05 05:00:00 | 2020-01-06 00:00:00 | hour",
            "2020-01-06 00:00:00 | 2020-01-10 00:00:00 | day",
            "2020-01-10 00:00:00 | 2020-01-10 20:00:00 | hour",
        ], $ranges);
    }

    public function testBreakRangeSkipsMiddle()
    {
        $ranges= array_map(function (Range $range) {
            return $range->toString();
        }, Strategy::create([
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
            SegmentLevel::MONTH,
        ])->calculateRanges(
            Carbon::parse('2020-01-31 05:00:00'),
            Carbon::parse('2020-03-01 10:00:00')
        ));

        $this->assertEquals([
            "2020-01-31 05:00:00 | 2020-02-01 00:00:00 | hour",
            "2020-02-01 00:00:00 | 2020-03-01 00:00:00 | month",
            "2020-03-01 00:00:00 | 2020-03-01 10:00:00 | hour",
        ], $ranges);
    }

    public function testBreakRangeSkipsFirst()
    {
        $ranges= array_map(function (Range $range) {
            return $range->toString();
        }, Strategy::create([
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
            SegmentLevel::MONTH,
        ])->calculateRanges(
            Carbon::parse('2020-01-10 00:00:00'),
            Carbon::parse('2020-03-10 00:00:00')
        ));

        $this->assertEquals([
            "2020-01-10 00:00:00 | 2020-02-01 00:00:00 | day",
            "2020-02-01 00:00:00 | 2020-03-01 00:00:00 | month",
            "2020-03-01 00:00:00 | 2020-03-10 00:00:00 | day",
        ], $ranges);
    }

    public function testBreakRangeSkipsLast()
    {
        $ranges= array_map(function (Range $range) {
            return $range->toString();
        }, Strategy::create([
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
            SegmentLevel::MONTH,
        ])->calculateRanges(
            Carbon::parse('2020-01-01 05:00:00'),
            Carbon::parse('2020-01-01 23:00:00')
        ));

        $this->assertEquals([
            "2020-01-01 05:00:00 | 2020-01-01 23:00:00 | hour",
        ], $ranges);
    }

    public function testBreakRangeFull()
    {
        $ranges= array_map(function (Range $range) {
            return $range->toString();
        }, Strategy::create([
            SegmentLevel::MINUTE,
            SegmentLevel::HOUR,
            SegmentLevel::DAY,
            SegmentLevel::MONTH,
            SegmentLevel::YEAR,
        ])->calculateRanges(
            Carbon::parse('2020-01-05 04:10:00'),
            Carbon::parse('2030-06-15 11:30:00')
        ));

        $this->assertEquals([
            "2020-01-05 04:10:00 | 2020-01-05 05:00:00 | minute",
            "2020-01-05 05:00:00 | 2020-01-06 00:00:00 | hour",
            "2020-01-06 00:00:00 | 2020-02-01 00:00:00 | day",
            "2020-02-01 00:00:00 | 2021-01-01 00:00:00 | month",
            "2021-01-01 00:00:00 | 2030-01-01 00:00:00 | year",
            "2030-01-01 00:00:00 | 2030-06-01 00:00:00 | month",
            "2030-06-01 00:00:00 | 2030-06-15 00:00:00 | day",
            "2030-06-15 00:00:00 | 2030-06-15 11:00:00 | hour",
            "2030-06-15 11:00:00 | 2030-06-15 11:30:00 | minute",
        ], $ranges);
    }

    public function testLevelsCanBeOmitted()
    {
        $ranges= array_map(function (Range $range) {
            return $range->toString();
        }, Strategy::create([
            SegmentLevel::MINUTE,
            SegmentLevel::DAY,
        ])->calculateRanges(
            Carbon::parse('2020-01-05 04:10:00'),
            Carbon::parse('2030-06-15 11:30:00')
        ));

        $this->assertEquals([
            "2020-01-05 04:10:00 | 2020-01-06 00:00:00 | minute",
            "2020-01-06 00:00:00 | 2030-06-15 00:00:00 | day",
            "2030-06-15 00:00:00 | 2030-06-15 11:30:00 | minute",
        ], $ranges);
    }
}
