<?php

namespace Igaster\LaravelMetrics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class ExampleModel extends Model
{
    protected $table = 'package_table';

    protected $guarded = [];

    public $timestamps = false;

    // protected $appends = ['xxx'];      // Appends the getXxxAttribute() accessor value on serialization

    // protected $hidden = ['password']; // Hides attribute on serialization

    protected $casts = [
        //  integer, real, float, double, decimal:<digits>, string, boolean, object, array, collection, date, datetime, timestamp
        'xxx' => 'array',
        'yyy' => 'boolean',
        'zzz' => 'date',
    ];

    // ----------------------------------------------
    //  Scopes
    // ----------------------------------------------

    public function scopeXxx(Builder $query, $param)
    {
        return $query->where('column', 'value');
    }

    // ----------------------------------------------
    //  Mutators
    // ----------------------------------------------

    public function setXxxAttribute($value)
    {
        $this->attributes['xxx'] = $value;
    }

    public function getXxxAttribute()
    {
        return 'Some_Value';
    }

    // ----------------------------------------------
    //  Relationships
    // ----------------------------------------------

    public function relationshipBelongsTo(): BelongsTo
    {
        // I have a 'model_id' pointing to other Model
        return $this->belongsTo(Model::class, 'xxx_id');
    }

    public function relationshipHasOne(): HasOne
    {
        // Other Model has a 'xxx_id' pointing to me
        return $this->hasOne(Model::class, 'xxx_id');
    }

    public function relationshipHasMany(): HasMany
    {
        // Other Model has a 'xxx_id' pointing to me
        return $this->hasMany(Model::class, 'xxx_id');
    }

    public function relationshipBelongsToMany(): BelongsToMany
    {
        //Many to Many relationship through a joining_table:
        return $this->belongsToMany(OtherModel::class, 'pivot_table', 'this_model_id', 'other_model_id')
            ->using(PivotModel::class)     // Optional: Use a pivot table model
            ->as('rename_pivot_table')   // Rename Pivot Table. Access ot with '$model->rename_pivot_table'
            ->withPivot(['column1']);            // Include these fields from the pivot Table
    }


    // Polymorphic Relationship with a [Polymorphic] model that defines [xxx_type] & [xxx_id] keys. prefix = [xxx]
    public function relationshipMotphMany(): MorphMany
    {
        // One (me) to many Polymorphic models
        return $this->morphMany(PolymorphicModel::class, 'xxx');
    }

    public function relationshipMotphOne(): MorphOne
    {
        // One (me) to one Polymorphic model
        return $this->morphOne(PolymorphicModel::class, 'xxx');
    }

    // On the [Polymorphic] model:
    public function xxx(): MorphTo // Must be the same name with the prefix
    {
        return $this->morphTo();
    }

    // ----------------------------------------------
    //  Methods
    // ----------------------------------------------
}