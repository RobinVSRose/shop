<?php

namespace Ejoy\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttr extends Model
{
    protected $table='product_attribute';
    protected $fillable=['product_id','name','value','num','price','inner_price'];

    public function product(){
        return $this->belongsTo(Product::class);
    }
}
