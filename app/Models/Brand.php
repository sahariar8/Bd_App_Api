<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('alphabetical', function (Builder $builder) {
            $builder->orderBy('name', 'asc');
        });
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function featuredProducts()
    {
        return $this->hasMany(Product::class)
            ->where('published', 1)
            ->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('
            combo_type,
            thumbnail_img,
            (CASE WHEN (discount_end_date > NOW()) THEN unit_price - discount ELSE unit_price END) AS net_price,
            (CASE WHEN (SUM(qty_available) > 0) THEN 1 ELSE 0 END) AS in_stock,
            discount_type,
            products.id,
            name,
            slug,
            unit_price,
            discount,
            discount_start_date,
            discount_end_date,
            SUM(qty_available) as qty_available,
            brand_id,
            product_new_from,
            product_new_to
        ')
            ->groupBy('products.id')
            ->orderByDesc('in_stock')
            ->orderByDesc('num_of_sale')
            ->take(6);
    }
}
