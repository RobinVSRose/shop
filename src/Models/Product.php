<?php

namespace Ejoy\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Input;

class Product extends Model
{
    protected $table = "product";

    public function attr(){
        return $this->hasMany(ProductAttr::class,'product_id','id');
    }
    public function category(){
        return $this->hasOne(ProductCategory::class,'id','category_id');
    }
    public function setRecommendIdsAttribute($ids)
    {
        if (is_array($ids)) {
            $this->attributes['recommend_ids'] = json_encode($ids);
        }
    }

    public function getRecommendIdsAttribute($ids)
    {
        return json_decode($ids, true);
    }
    public function setThumbAttribute($images)
    {
        if(!is_array($images))
            $images=[$images];
        $this->attributes['thumb'] = json_encode($images);
    }

    public function getThumbAttribute($images)
    {
        return json_decode($images, true);
    }

    public static function countAmount($productIdNumArr){//检测商品是否有效，并计算商品金额with 用户卡券ID
        if(!is_array($productIdNumArr))
            return config('status.PRODUCTDATANOTFOUND');//未检测到商品数据
        $productIdArr=array_keys($productIdNumArr);
        $productArr=self::whereIn('id',$productIdArr)->where('status',1)->get(['id','exchange_price','exchange_bitcoin','total','total_sale'])->toArray();
        if(count($productIdNumArr)!=count($productArr))
            response_json(null,'SHOPCARTUPDATEERROR');
        $return=['total_price'=>0,'total_bitcoin'=>0];
        foreach($productArr as $k=>$product){
            if($product['total']>=0 && $product['total']<$product['total_sale'])
                response_json(null,'NOMOREPRODUCT');
            $return['total_price']+=$product['exchange_price']*$productIdNumArr[$product['id']];
            $return['total_bitcoin']+=$product['exchange_bitcoin']*$productIdNumArr[$product['id']];
        }

        return $return;
    }
    public static function getFirstThumb($thumbArr=[]){
        foreach($thumbArr as $thumb){
            if(strpos($thumb,".mp4")===false){
                return OSS_PIC_URL."/".$thumb;break;
            }
        }
    }
    public static function formatProductDetail(&$product){
        if(!empty($product['attr'])){
            $priceArr=[];$attrList=[];
            foreach($product['attr'] as $attr){
                $attrList[$attr['name']][]=$attr;
                if(!in_array($attr['price'],$priceArr))
                    $priceArr[]=$attr['price'];
            }
            $product['attr']=$attrList;
            $product['price_comment']=count($priceArr)>1?min($priceArr)." ~ ".max($priceArr):$priceArr[0];
        }
        if(!empty($product['product_price'])){
            $product['price']=$product['product_price']."元";
            $product['price_comment']=$product['product_price']."元";
            $product['exchange_price_comment']=$product['product_price']."元";
        }

        if(!empty($product['recommend_ids'])){
            $productList=Product::query()->whereIn('id',$product['recommend_ids'])->get(['id','name','thumb'])->toArray();
            foreach($productList as $v){
                $v['thumb']=self::getFirstThumb($v['thumb']);
                $product['recommend_list'][]=$v;
            }

        }
        $product['thumb_list']=[];
        if(is_array($product['thumb'])){
            foreach ($product['thumb'] as $k=>&$v){
                $fileType=strpos($v,".mp4")===false?"image":"video";
                $v=OSS_PIC_URL."/".$v;
                $product['thumb_list'][]=['url'=>$v,'type'=>$fileType];
                if(empty($product['first_thumb']) && $fileType=="image")//产品首个封面图
                    $product['first_thumb']=$v;
            }
        }
        $product['thumb_list']=array_values($product['thumb_list']);
        if(!empty($product['jump_mini_text']))
            $product['jump_mini_text']=OSS_PIC_URL."/".$product['jump_mini_text'];
    }
    public static function updateCartAndStorage($member_id,$productIdArr){
        $cartList=Cart::where('member_id',$member_id)->whereIn('attr_id',array_keys($productIdArr))->get();
        $productArr=ProductAttr::whereIn('id',array_keys($productIdArr))->get();
        foreach($productArr as $k=>$productAttrModel){
            $productAttrModel->increment('saled_num',$productIdArr[$productAttrModel->id]);
            Product::query()->where('id',$productAttrModel->product_id)->increment('total_sale',$productIdArr[$productAttrModel->id]);
        }
        foreach($cartList as $k=>$cart){
            if($cart->num>$productIdArr[$cart->attr_id]){
                $cart->num=$cart->num-$productIdArr[$cart->attr_id];
                $cart->save();
            }else{
                $cart->delete();
            }
        }
    }
    public function formatSimpleProductDetail(){
        $thumb=self::getFirstThumb($this->thumb);
        return ['id'=>$this->id,'image'=>$thumb,'name'=>$this->name];
    }
}
