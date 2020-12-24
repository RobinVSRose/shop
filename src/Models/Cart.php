<?php

namespace Ejoy\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $table="user_cart";
    public function product(){
        return $this->hasOne(Product::class,'id','product_id');
    }
    public function attr(){
        return $this->hasOne(ProductAttr::class,'id','attr_id');
    }
}
