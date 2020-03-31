<?php

namespace Igaster\LaravelMetrics\Tests\Unit;

use Igaster\LaravelMetrics\Services\Metrics\Helpers\Combinations;
use Igaster\LaravelMetrics\Tests\TestCase;

class CombinationsTest extends TestCase
{

    public function testGetCombinationsWithCardinality()
    {
        $combination = new Combinations(['a', 'b', 'c']);

        $this->assertEquals([
            ['a'],
            ['b'],
            ['c'],
        ], $combination->getCombinationsWithCardinality(1));

        $this->assertEquals([
            ['a', 'b'],
            ['a', 'c'],
            ['b', 'c'],
        ], $combination->getCombinationsWithCardinality(2));

        $this->assertEquals([
            ['a', 'b', 'c'],
        ], $combination->getCombinationsWithCardinality(3));

        $this->assertEquals([
            ['a', 'b', 'c'],
        ], $combination->getCombinationsWithCardinality(99));
    }

    public function testGetCombinations()
    {
        $combination = new Combinations(['a', 'b', 'c']);

        $this->assertEquals([
            ['a'],
            ['b'],
            ['c'],
        ], $combination->getCombinations(1));

        $this->assertEquals([
            ['a'],
            ['b'],
            ['c'],
            ['a', 'b'],
            ['a', 'c'],
            ['b', 'c'],
        ], $combination->getCombinations(2));

        $this->assertEquals([
            ['a'],
            ['b'],
            ['c'],
            ['a', 'b'],
            ['a', 'c'],
            ['b', 'c'],
            ['a', 'b', 'c'],
        ], $combination->getCombinations(3));

        $this->assertEquals([
            ['a'],
            ['b'],
            ['c'],
            ['a', 'b'],
            ['a', 'c'],
            ['b', 'c'],
            ['a', 'b', 'c'],
        ], $combination->getCombinations(99));

    }
}
