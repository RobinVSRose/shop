<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Page extends AdminModel
{
    protected $table='page';
    public $timestamps=false;
    protected $fillable=['channel','title','json','status'];
//    public function getJsonAttribute($value){
//        return json_decode($value, true);
//    }



    public static function getPageData($channel){
        $key=$channel."_page_index";
        if(!redis_exists($key)){
            $pageModel=Page::query()->where('channel',$channel)->first();
            if(!empty($pageModel->json))
                redis_set($key,$pageModel->json);
        }

        $pageData=redis_get($key);
        foreach($pageData as $key=>&$value){
            if(is_array($value)){
                foreach($value as $k=>&$v){
                    if(!empty($v['image']) && strpos($v['image'],'http')===false){
                        $v['image']=Storage::url($v['image']);
                    }
                }
            }
        }
        return $pageData;
    }
}
