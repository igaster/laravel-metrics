<?php

namespace Igaster\LaravelMetrics\Services\Metrics\Segments;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Models\Metric;
use Igaster\LaravelMetrics\Models\MetricValue;
use Igaster\LaravelMetrics\Services\Metrics\Sample;
use Igaster\LaravelMetrics\Services\Metrics\Helpers\Combinations;
use Illuminate\Support\Facades\DB;

class Segment
{
    const MAX_PARTITIONS_CARDINALITY = 2;

    public $level; // SegmentType::XXX

    public $from = null;

    public $until = null;

    public $value = [];

    public $count = [];

    /** @var Metric  */
    private $metric;

    public function __construct(Metric $metric, Carbon $from, int $level)
    {
        if(!SegmentLevel::isValid($level)) {
            throw new \Exception("[$level] is not a valid SegmentType enumeration");
        }

        $this->metric = $metric;

        $this->level = $level;

        $this->from = SegmentLevel::startsAt($level, $from);

        $this->until = SegmentLevel::endsAt($level, $from);
    }

    public function getPartitions()
    {
        return $this->metric->partitions;
    }

    public static function create(string $slug, Carbon $from, int $level, $partitions = [])
    {
        $metric = Metric::factory($slug, [$level], $partitions);

        return $metric->createSegment($from);
    }

    public function addSample(Sample $sample): self
    {
        if (!$this->belongsToSegment($sample->timestamp)) {
            return $this;
        }

        $partitions =  $this->normalizePartitions($sample->partitions);

        $keys = $this->getPartitionKeys($partitions);

        foreach ($keys as $key) {
            $this->count[$key] = ($this->count[$key] ?? 0) + 1;
            $this->value[$key] = ($this->value[$key] ?? 0) + $sample->value;
        }

        return $this;
    }

    public function addSamples(array $samples): self
    {
        foreach ($samples as $sample) {
            $this->addSample($sample);
        }

        return $this;
    }

    private function normalizePartitions(array $partitions)
    {
        if(!$this->isAssociative($partitions)) {

            $data = [];
            foreach($this->metric->partitions as $key) {
                if(count($partitions) == 0) {
                    break;
                }
                $data[$key] = array_shift($partitions);
            }

            return $data;
        } else {
            return $partitions;
        }
    }

    public function count(array $partitions = []): int
    {
        $slug = $this->metric->getPartitionSlug($partitions);
        return $this->count[$slug] ?? 0;
    }

    public function value(array $partitions = []): float
    {
        $slug = $this->metric->getPartitionSlug($partitions);

        return $this->value[$slug] ?? 0;
    }

    public function save()
    {
        $this->metric->saveCascading($this);
    }

    public static function load(string $slug, Carbon $from, int $level, $partitions = []): Segment
    {
        /** @var Metric $metric */
        $metric = Metric::where([
            'slug' => $slug,
        ])->first();

        $segment = $metric->createSegment($from, $level);

        $segmentValues =  MetricValue::where([
            'metric_id' => $metric->id,
            'from' => $from,
            'level' => $level,
        ])->get();

        foreach ($segmentValues as $segmentValue) {
            $segment->count[$segmentValue->partition_key] = $segmentValue->count;
            $segment->value[$segmentValue->partition_key] = $segmentValue->value;
        }

        return $segment;
    }

    public function loadValuesFromLevel(int $level)
    {
        $values = $this->metric->values()
            ->where('from','>=', $this->from)
            ->where('until','<=', $this->until)
            ->where('level', $level)
            ->groupBy('partition_key')
            ->select([
                'partition_key',
                DB::raw('sum("value") as total_value'),
                DB::raw('sum("count") as total_count'),
            ])
            ->get();

        foreach($values as $value) {
            $this->value[$value->partition_key] = $value->total_value;
            $this->count[$value->partition_key] = $value->total_count;
        }
    }

    private function belongsToSegment(Carbon $timestamp = null): bool
    {
        return true; // ToDo: Check that it belongs to range [$from, $until) / or null
    }

    private function isAssociative(array $array)
    {
        //        if (array() === $array) {
        //            return false;
        //        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function getPartitionKeys(array $partitions): array
    {
        $combinations = $this->getPartitionCombinations(self::MAX_PARTITIONS_CARDINALITY);
        $result = [];

        foreach ($combinations as $combination) {

            $values = array_map(function () {
                return '*';
            }, array_flip($this->getPartitions()));

            foreach ($combination as $key) {
                $values[$key] = $partitions[$key] ?? null;
            }

            $result[] = $this->metric->getPartitionSlug($values);
        }

        return $result;
    }

    private function getPartitionCombinations($max_cardinality): array
    {
        $combinationService = new Combinations($this->getPartitions());

        $combinations = $combinationService->getCombinations($max_cardinality);

        // $combinations = $combinationService->getCombinationsWithCardinality($max_cardinality);

        $combinations= array_merge([[]], $combinations); // Allow Empty Set as a valid combination

        return $combinations;
    }

}