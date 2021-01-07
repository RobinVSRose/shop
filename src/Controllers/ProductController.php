<?php

namespace Ejoy\Shop\Controllers;


use Ejoy\Shop\Models\Product;
use Ejoy\Shop\Models\ProductCategory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Validator;
use DB;
class ProductController extends Controller
{
    /**
     * 获取商品分类树状列表
     */
    public function categoryList(Request $request){
        $channel=$request->get('channel','mini');
        $categoryArr=ProductCategory::where('status',1)->where('channel',$channel)->get(['id as value','name as label','parent_id','thumb'])->toArray();
        foreach($categoryArr as &$c){
            $c['thumb']=Storage::url($c['thumb']);
        }
        $arr=self::getTree($categoryArr,'value','parent_id');
        $arr=array_values($arr);
        response_json($arr);
    }
    /**
     * getTree无限递归函数
     * @param $data
     * @param $field_id
     * @param $field_pid
     * @param int $pid
     * @return array
     * @fillable 变量，保存子集数据
     */
    public static function getTree($data, $field_id, $field_pid, $pid = 0) {
        $arr = array();
        foreach ($data as $k=>$v) {
            if ($v[$field_pid] == $pid) {
                $arr[$k] = $v;
                $tmpData=self::getTree($data, $field_id, $field_pid, $v[$field_id]);
                if(!empty($tmpData)){
                    $arr[$k]['children'] =array_values($tmpData);
                }

            }
        }
        return $arr;
    }

    /**
     * @param Request $request
     * 获取分类下的所有产品，支持所有分类筛选
     */
    public function productList(Request $request){
        $validator = Validator::make($request->all(), [
            'category_id'   =>'Integer',//分类ID
            'product_name'=>'String',
            'page'=>'integer',
            'channel'=>'required'
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $pageSize=!empty($request->page_size)?$request->page_size:15;
        $categoryId=!empty($request->category_id)?$request->category_id:0;
        $categoryIdArr=ProductCategory::getChildrenIdArr([$categoryId]);//获取所选分类id下的所有分类ID
        $query=Product::with('attr')->select(['id','name','thumb','price','exchange_price',])
            ->whereIn('category_id',$categoryIdArr)
            ->where('status',1)
            ->where('is_sale',1)
            ->where('channel',$request->channel);
        if(!empty($request->product_name))
            $query->where('name','like',"%".$request->product_name."%");

        $productList = $query->orderBy('id','desc')->paginate($pageSize)->toArray();
        foreach($productList['data'] as &$v){
            Product::formatProductDetail($v);
        }
        response_json($productList);
    }

    /**
     * @param $id
     * @param Request $request
     * 产品详情接口
     */
    public function productDetail(Request $request){
        $validator = Validator::make($request->all(), [
            'product_id'   =>'Integer',
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $productInfo=Product::with('category:id,name')->with('attr')->findOrFail($request->product_id)->toArray();
        Product::formatProductDetail($productInfo);
        response_json($productInfo);
    }
}

