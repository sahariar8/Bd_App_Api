<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;
    public function campaign_products()
    {
        return $this->hasMany(CampaignProduct::class);
    }
}
