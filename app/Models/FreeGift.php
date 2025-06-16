<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreeGift extends Model
{
     public function brand()
    {
        return $this->belongsTo('App\Models\Brand');
    }
    //belongs to product
    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
    public function gift()
    {
        return $this->belongsTo('App\Models\Product', 'gift_product_id');
    }
}
