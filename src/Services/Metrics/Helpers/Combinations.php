<?php

namespace Igaster\LaravelMetrics\Services\Metrics\Helpers;

class Combinations
{
    private $data = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getCombinations($max_cardinality): array
    {
        $combinations = [];

        for ($i = 1; $i <= min($max_cardinality, count($this->data)); $i++) {
            $combinations = array_merge($combinations, $this->getCombinationsWithCardinality($i));
        }

        return $combinations;
    }


    public function getCombinationsWithCardinality(int $cardinality, $temp = [], $index1 = 0, $index2 = 0, &$result = [])
    {
        if ($index1 == min($cardinality, count($this->data) )) {
            $result[] = $temp;
            return $result;
        }

        if ($index2 >= count($this->data)) {
            return $result;
        }

        // a) take current
        $temp[$index1] = $this->data[$index2];
        $this->getCombinationsWithCardinality($cardinality, $temp, $index1 + 1, $index2 + 1, $result);

        // b) skip current (replace it with next item. Note that index1 is not changed)
        $this->getCombinationsWithCardinality($cardinality, $temp, $index1,  $index2 + 1, $result);

        return $result;
    }

}