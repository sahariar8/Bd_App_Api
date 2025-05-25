<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
     protected $guarded = [];
    //protected $fillable = ['viewed'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }


    public function orderProducts(){
        return $this->hasManyThrough(Product::class,OrderDetail::class,'order_id','id','id','product_id');
    }
}
