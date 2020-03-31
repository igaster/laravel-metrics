<?php

namespace Igaster\LaravelMetrics\Models;

use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class MetricValue extends Model
{
    protected $table = 'metric_values';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'from' => 'datetime:Y-m-d H:i:s',
        'until' => 'datetime:Y-m-d H:i:s',
        'count' => 'integer',
        'value' => 'double',
    ];

    // ----------------------------------------------
    //  Events
    // ----------------------------------------------

    public static function boot() {
        parent::boot();

        static::creating(function (self $item) {
            $item->calculateUntil();
        });

        static::updating(function (self $item) {
            $item->calculateUntil();
        });
    }

    public function calculateUntil()
    {
        $this->until = SegmentLevel::endsAt($this->level, $this->from);
    }

    // ----------------------------------------------
    //  Relationships
    // ----------------------------------------------

    public function name(): BelongsTo
    {
        return $this->belongsTo(Metric::class, 'metric_id');
    }

    // ----------------------------------------------
    //  Methods
    // ----------------------------------------------

}