<?php

namespace Ejoy\Shop\Controllers;

use Ejoy\Shop\Models\Cart;
use Ejoy\Shop\Models\Order;
use Ejoy\Shop\Models\Product;
use Ejoy\Shop\Models\ProductAttr;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use DB;
class CartController extends Controller
{
    /**
     * @param Request $request
     * 添加商品到购物车
     */
    public function addCart(Request $request){
        $validator = Validator::make($request->all(), [
            'attr_id'=>'required|integer',//
            'num'=>'integer'
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $attrInfo=ProductAttr::query()->where('id',$request->attr_id)->first();
        $num=!empty($request->num)?$request->num:1;
        if (!Cart::where(['member_id'=>$GLOBALS['userInfo']['userInfo']['member_id'],'attr_id'=>$request->attr_id])->exists()) {
            Cart::insert(['member_id'=>$GLOBALS['userInfo']['userInfo']['member_id'],'attr_id'=>$request->attr_id,'num'=>$num,
                'product_id'=>$attrInfo->product_id,'attr_value'=>$attrInfo->value,'attr_name'=>$attrInfo->name,'attr_price'=>$attrInfo->price]);
        }else{
            Cart::where(['member_id'=>$GLOBALS['userInfo']['userInfo']['member_id'],'attr_id'=>$request->attr_id])
                ->update(['num'=>DB::raw('num+'.$num)]);
        }
        response_json(null);
    }

    /**
     * @param Request $request
     * 修改购物车商品数据
     */
    public function updateCart(Request $request){
        $validator = Validator::make($request->all(), [
            'attr'=>'array',//['{productId}'=>{num},'{productId}'=>{num}]
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $cartDataArr=[];
        if(empty($request->attr))//若购物车有数据提交，则判断产品是否都有效
            response_json(null,'PRODUCTDATANOTFOUND');

        $result=Order::calculateOrder($request->attr);//判断库存，商品有效性并计算价格
        foreach($result['order']['product'] as $product){
            if($product['product_num']>0){
                $cartDataArr[]=['product_id'=>$product['product_id'],'num'=>$product['product_num'],'member_id'=>$GLOBALS['userInfo']['userInfo']['member_id'],
                    'attr_id'=>$product['product_attr_id'],'attr_name'=>$product['product_attr_name'],'attr_value'=>$product['product_attr_value'],'attr_price'=>$product['product_price']];
            }
        }
        unset($result['order']);
        DB::transaction(function () use ($request,$cartDataArr){
            Cart::where('member_id',$GLOBALS['userInfo']['userInfo']['member_id'])->delete();
            if(!empty($cartDataArr))
                Cart::insert($cartDataArr);
        });
        response_json($result,'SUCCESS');
    }

    /**
     * @param Request $request
     * 购物车商品列表
     */
    public function cartList(Request $request){
        $cartList=Cart::with('product')->where('member_id',$GLOBALS['userInfo']['userInfo']['member_id'])->get()->toArray();
        foreach($cartList as $k=>&$cart){
            if(!empty($cart['product'])){
                Product::formatProductDetail($cart['product']);
                $cart['is_check']=false;
            }else{
                unset($cartList[$k]);
            }
        }
        $cartList=array_values($cartList);
        response_json($cartList);
    }

}
