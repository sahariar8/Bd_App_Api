<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FilterValue extends Model
{
    protected $fillable = ['value', 'slug', 'image', 'is_active', 'is_offer'];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function setSlugAttribute($value)
    {
        // $this->attributes['slug'] = str()::slug(str()::lower($value), '-');
        $this->attributes['slug'] = str($value)->lower()->slug('-');
    }

    public function filter()
    {
        return $this->belongsTo(Filter::class);
    }
}
