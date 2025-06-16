<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Laravel\Scout\Searchable;

class Product extends Model
{

     
    protected $fillable = ['variations', 'num_of_sale'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('published', function (Builder $builder) {
            $builder->where('published', 1);
        });
    }
    protected static function booted()
    {
        static::updated(function ($product) {
            Http::get('https://beautybooth.com.bd/api/revalidate?url=/product/'.$product->slug);
            
        });
        static::saved(function ($product) {
            Http::get('https://beautybooth.com.bd/api/revalidate?url=/product/'.$product->slug);
            
        });
    }
    public function getNameAttribute($value)
    {
        if (request()->lang) {
            $lang = request()->lang;
            $trans = $this->product_translations->where('lang', $lang)->first();
            if ($trans) {
                return  $this->product_translations->where('lang', $lang)->first()->name;
            }
        }
        return $value;
    }
    public function getDescriptionAttribute($value)
    {
        if (request()->lang) {
            $lang = request()->lang;
            $trans = $this->product_translations->where('lang', $lang)->first();
            if ($trans) {
                return  $this->product_translations->where('lang', $lang)->first()->description;
            } else {
                return "";
            }
        }
        return $value;
    }
    public function isOffer()
    {
        return   strtotime(date('d-m-Y H:i:s')) >= $this->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $this->discount_end_date && $this->discount;
    }
    public function product_translations()
    {
        return $this->hasMany(ProductTranslation::class);
    }
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories')->select('categories.id', 'categories.name', 'categories.parent_id','level')->orderBy('level');
    }
    public function getTranslation($field = '', $lang = false)
    {

        $product_translations = $this->product_translations->where('lang', $lang)->first();
        return $product_translations != null ? $product_translations->$field : $this->$field;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function product_categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories', 'category_id');
        // return $this->belongsToMany(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    # For combo products
    public function isProductCombo()
    {
        return !is_null($this->combo_type);
    }
    public function combo_packs()
    {
        return $this->belongsToMany(static::class, 'product_bundles', 'parent_product_id', 'bundled_product_id')->withPivot('bundle_threshold');
    }
    public function free_gifts()
    {
        return $this->belongsToMany(static::class, 'free_gifts', 'product_id', 'gift_product_id')->withPivot('min_qty');
    }
    public function variants()
    {
        return $this->hasMany(static::class, 'variant_code', 'variant_code');
    }
    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }
    public function pendingWaitList()
    {
        return $this->hasMany(WaitList::class)->where('status', 'pending');
    }

    public function taxes()
    {
        return $this->hasMany(ProductTax::class);
    }

    public function flash_deal_product()
    {
        return $this->hasOne(FlashDealProduct::class);
    }
}
