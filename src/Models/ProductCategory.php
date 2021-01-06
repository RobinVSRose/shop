<?php

namespace Ejoy\Shop\Models;

use App\Admin\Extensions\CustomModelTree;
use Encore\Admin\Traits\AdminBuilder;
use Illuminate\Database\Eloquent\Model;
use App\Models\AdminModel;
class ProductCategory extends AdminModel
{
    use CustomModelTree,AdminBuilder;
    protected $table="product_category";
    public static $categoryIdArr=[];
    protected $fillable=['level','channel'];
    public function __construct(array $attributes=[])
    {
        parent::__construct($attributes);
//        $this->setKeyType("string");
//        $this->setKeyName("area_code");
//        $this->setParentColumn('parent_code');
        $this->setTitleColumn('name');
        $this->setOrderColumn('order_num');
    }
    public function options(){
        return $this->hasMany(ProductCategory::class,'parent_id','id');
    }
    public static function getChildrenIdArr ($categoryIdArr)
    {
        self::$categoryIdArr=array_merge(self::$categoryIdArr,$categoryIdArr);
        $nextCategoryList=self::whereIn('parent_id',$categoryIdArr)->where('status',1)->get();
        if($nextCategoryList->isNotEmpty()){
            $tempCategoryIdArr=[];
            foreach($nextCategoryList as $k=>$v){
                $tempCategoryIdArr[]=$v->id;
            }
            self::getChildrenIdArr($tempCategoryIdArr);
        }
        return self::$categoryIdArr;
    }
    public static function formatTree($channelArr){
        $categoryListArr=ProductCategory::query()->whereIn('channel',$channelArr)->get(['id','name','parent_id'])->toArray();
        $categoryTreeArr=[];
        $a=formatTreeList($categoryListArr);
        foreach($a as $k=>$v){
            $categoryTreeArr[$v['id']]=$v['name'];
        }
        return $categoryTreeArr;
    }



}
