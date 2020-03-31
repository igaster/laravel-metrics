<?php


namespace Igaster\LaravelMetrics\Tests\App;

use Carbon\Carbon;
use Faker\Factory;

class GenerateMetricData
{
    public function generate($count = 100, Carbon $from = null, Carbon $until = null): array
    {
        $from = $from ?: Carbon::now()->startOfDay();
        $until = $until ?: Carbon::now()->endOfDay();

        $faker = Factory::create();

        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $result[] = Carbon::instance($faker->dateTimeBetween($from, $until));
        }

        return $result;
    }

}