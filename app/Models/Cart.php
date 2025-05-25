<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
      protected $fillable = ['is_free', 'child_id', 'parent_id', 'address_id', 'price', 'tax', 'shipping_cost', 'discount', 'coupon_code', 'coupon_applied', 'quantity', 'user_id', 'owner_id', 'product_id', 'variation', 'temp_user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
