<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Filter extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected static function booted()
    {
        /**
         * Adding active global scope
         */
        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('is_active', true);
        });
    }

    public function setSlugAttribute($value)
    {
        // $this->attributes['slug'] = Str::slug(Str::lower($value), '-');
        $this->attributes['slug'] = str($value)->lower()->slug('-');
    }

    public function values()
    {
        return $this->hasMany(FilterValue::class);
    }

    /**
     * Filters Allowed for categories
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'filter_categories_pivot', 'filter_id', 'category_id');
    }
}
