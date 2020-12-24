<?php

namespace Ejoy\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    protected $table="order_product";
    public function getThumbAttribute($thumbs)
    {
        return json_decode($thumbs, true);
    }
}
