<?php

namespace Igaster\LaravelMetrics\Models;

use Carbon\Carbon;
use Igaster\LaravelMetrics\Services\Metrics\Helpers\Range;
use Igaster\LaravelMetrics\Services\Metrics\Helpers\Strategy;
use Igaster\LaravelMetrics\Services\Metrics\Sample;
use Igaster\LaravelMetrics\Services\Metrics\Segments\Segment;
use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Metric extends Model
{
    protected $table = 'metrics';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'levels' => 'json',
        'partitions' => 'json',
        'last_sample' => 'datetime',
    ];

    /** @var Segment  */
    private $segment = null;

    // ----------------------------------------------
    //  Events
    // ----------------------------------------------

    public static function boot() {
        parent::boot();

        static::creating(function (self $item) {
            $item->sortElements();
        });

        static::updating(function (self $item) {
            $item->sortElements();
        });
    }

    public function sortElements()
    {
        $levels = $this->levels;

        sort($levels);

        $this->levels = $levels;

        $partitions = $this->partitions;

        usort($partitions, 'strnatcasecmp');

        $this->partitions = $partitions;
    }

    // ----------------------------------------------
    //  Relationships
    // ----------------------------------------------

    public function values(): HasMany
    {
        return $this->hasMany(MetricValue::class, 'metric_id');
    }

    // ----------------------------------------------
    //  Methods
    // ----------------------------------------------

    public static function factory(string $slug, $levels = [], $partitions = []): self
    {
        return Metric::firstOrcreate([
            'slug' => $slug,
        ],[
            'levels' =>  $levels ?: [SegmentLevel::DAY],
            'partitions' => $partitions,
        ]);
    }

    public static function get(string $slug): self
    {
        return Metric::where([
            'slug' => $slug,
        ])->firstOrFail();
    }

    public function createSegment(Carbon $from, int $level = null) : Segment
    {
        $level = $level ?: $this->levels[0];

        $this->segment = new Segment($this, $from, $level);

        $this->update([
            'last_sample' => $this->segment->until,
        ]);

        return $this->segment;
    }

    public function saveCascading(Segment $segment = null)
    {
        $segment = $segment ?: $this->segment;
        $this->saveSegment($segment);

        // cascade to higher levels
        $current_level = $segment->level;
        $index = array_search($current_level, $this->levels);

        if(isset($this->levels[$index+1])) {
            $next_level = $this->levels[$index+1];

            if(SegmentLevel::endsAt($next_level, $segment->from)->eq($segment->until)) {
                $next_level_segment = $this->createSegment($segment->from, $next_level);
                $next_level_segment->loadValuesFromLevel($current_level);
                $this->saveCascading($next_level_segment);
            }
        }
    }

    private function saveSegment(Segment $segment)
    {
        foreach ($segment->count as $key => $value) {
            $this->values()->create([
                'from' => $segment->from,
                // 'until' => $segment->until, // This will be calculated
                'level' => $segment->level,
                'partition_key' => $key,
                'count' => $segment->count[$key],
                'value' => $segment->value[$key],
            ]);
        }
    }

    public function addSample(Sample $sample)
    {
        $this->segment->addSample($sample);
    }

    public function addSamples(array $samples)
    {
        $this->segment->addSamples($samples);
    }

    public function getPartitionSlug(array $partitions)
    {
        // fill empty partitions with '*' values
        $partitions = array_merge(array_map(function () {
            return '*';
        }, array_flip($this->partitions)), $partitions);

        return collect($partitions)->map(function ($item) {
            return $this->getSlug($item);
        })->implode('.');
    }

    private function getSlug($value)
    {
        if(is_bool($value)) {
            return $value ? '1' : '0';
        }

        if($value instanceof Model) {
            return $value->id;
        }

        if($value === null) {
            return '[NULL]';
        }

        if($value == '*') {
            return '*';
        }

        return Str::slug($value);
    }

    public function count(Carbon $from = null, Carbon $until = null, $partitions = []): float
    {
        return $this->getForLevelCascading('count', $from, $until, $partitions);
    }

    public function value(Carbon $from = null, Carbon $until = null, $partitions = []): float
    {
        return $this->getForLevelCascading('value', $from, $until, $partitions);
    }

    public function getByMinute(Carbon $from = null, Carbon $until = null, $partitions = []): Collection
    {
        return $this->getByLevel(SegmentLevel::MINUTE, $from, $until, $partitions);
    }

    public function getByHour(Carbon $from = null, Carbon $until = null, $partitions = []): Collection
    {
        return $this->getByLevel(SegmentLevel::HOUR, $from, $until, $partitions);
    }

    public function getByDay(Carbon $from = null, Carbon $until = null, $partitions = []): Collection
    {
        return $this->getByLevel(SegmentLevel::DAY, $from, $until, $partitions);
    }

    public function getByMonth(Carbon $from = null, Carbon $until = null, $partitions = []): Collection
    {
        return $this->getByLevel(SegmentLevel::MONTH, $from, $until, $partitions);
    }

    public function getByYear(Carbon $from = null, Carbon $until = null, $partitions = []): Collection
    {
        return $this->getByLevel(SegmentLevel::YEAR, $from, $until, $partitions);
    }

    public function getByLevel(int $level, Carbon $from = null, Carbon $until = null, $partitions = []): Collection
    {
        $partitions = is_array($partitions) ? $partitions : [ $this->partitions[0] => $partitions];

        $partitionKey = $this->getPartitionSlug($partitions);

        return Metric::values()
            ->where('from','>=', $from)
            ->where('until','<=', $until)
            ->where([
                'partition_key' => $partitionKey,
                'level' =>  $level,
            ])->get([
                'from', 'until', 'count', 'value'
            ]);
    }

    private function getForLevelCascading(string $what, Carbon $from = null, Carbon $until = null, $partitions = []): float
    {
        $ranges = Strategy::create($this->levels)->calculateRanges($from, $until);

        $value = 0;
        /** @var Range $range */
        foreach ($ranges as $range)
        {
            $value += $this->getForLevel($what, $range->level, $range->from, $range->until, $partitions);
        }

        return $value;
    }

    private function getForLevel(string $what, int $level, Carbon $from = null, Carbon $until = null, $partitions = []): float
    {
        $partitions = is_array($partitions) ? $partitions : [ $this->partitions[0] => $partitions];

        $partitionKey = $this->getPartitionSlug($partitions);

        return Metric::values()
            ->where('from','>=', $from)
            ->where('until','<=', $until)
            ->where([
                'partition_key' => $partitionKey,
                'level' =>  $level,
            ])->sum($what);
    }
}