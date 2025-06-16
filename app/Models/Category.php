<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{

    use HasTranslations;

    public $translatable = ['name'];
     protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('alphabetical', implementation: function (Builder $builder) {
            $builder->orderBy('name', 'asc');
        });
    }

    public function subCategories()
    {
        return $this->hasMany(SubCategory::class);
    }

    public function subSubCategories()
    {
        return $this->hasMany(SubSubCategory::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeHierachicalCategoryMenu($query)
    {
        $query->select('id', 'parent_id', 'name', 'slug', 'icon', 'banner', 'featured','level','description')
            ->selectRaw('"category" as type')
            ->where('parent_id', 0)
            ->with(['sub_categories' => function ($sub) {
                $sub->select('id', 'name', 'slug', 'parent_id', 'icon', 'banner', 'featured','level','description')->selectRaw('"category" as type');
            }]);
    }

    public function sub_categories()
    {
        return $this->hasMany(static::class, 'parent_id', 'id')->with(['sub_categories' => function ($sub) {
            $sub->select('id', 'name', 'slug', 'parent_id', 'icon', 'banner', 'featured','level','description')->selectRaw('"category" as type');
        }])
            ->withOut('category_translations')->orderBy('id', 'asc');
    }
}
