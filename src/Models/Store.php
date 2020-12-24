<?php

namespace Ejoy\Shop\Models;

use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Traits\AdminBuilder;
use Encore\Admin\Traits\ModelTree;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use AdminBuilder, ModelTree {
        ModelTree::boot as treeBoot;
    }
    protected $table='stuff_store';
    public static $categoryIdArr=[];
    public $timestamps=false;
    public function __construct(array $attributes=[])
    {
        parent::__construct($attributes);
        $this->setTitleColumn('name');
        $this->setOrderColumn('order_num');
    }

    public static function getGodData($storeId){
        if(empty($storeId))
            return [];
        $storeList=[];
        $storeObjList=Store::all()->toArray();
        foreach($storeObjList as $k=>$v){
            $storeList[$v['id']]=$v;
        }
        $parent=$storeList[$storeId];
        while(!empty($parent['parent_id'])){
            $parent=$storeList[$parent['parent_id']];
        }
        return $parent;
    }
    public static function getParentList($storeId){
        $storeList=[];
        $storeObjList=Store::all()->toArray();
        foreach($storeObjList as $k=>$v){
            $storeList[$v['id']]=$v;
        }
        while(!empty($storeList[$storeId])){
            $storeId=$storeList[$storeId]['parent_id'];
            $storeList[]=$storeList[$storeId]['name'];
        }
        return $storeList;
    }

    public static function checkAndInsert($data){
        $parentData=['id'=>0,'name'=>'','store_id'=>null,'store_name'=>''];
        for($i=0;$i<count($data);$i++){
            if(empty($data[$i]) || $data[$i]=="/")
                break;
            $parentData=self::updateOrInsertStore($data[$i],$parentData['id']);
        }
        return $parentData;
    }

    public static function updateOrInsertStore($name,$parentId=0){
        $storeInfo=Store::query()->where('name',$name)->where('parent_id',$parentId)->first();
        if(empty($storeInfo)){
            $parent=self::getGodData($parentId);
            $data=['name'=>$name,'parent_id'=>$parentId,'store_id'=>(!empty($parent['id'])?$parent['id']:null),'store_name'=>(!empty($parent['name'])?$parent['name']:"")];
            $data['id']=Store::query()->insertGetId($data);
            if(empty($parentId)){
                Store::query()->where('id',$data['id'])->update(['store_id'=>$data['id'],'store_name'=>$name]);
                $data['store_id']=$data['id'];
                $data['store_name']=$name;
            }
            return $data;
        }
        return $storeInfo->toArray();
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

    public function store_user_lk(){
        return $this->belongsToMany(Administrator::class,'stuff_store_user_lk','stuff_store_id','system_user_id');
    }


}
