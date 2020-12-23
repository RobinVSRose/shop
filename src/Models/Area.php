<?php
/**
 * Created by PhpStorm.
 * User: idove
 * Date: 2018/5/21
 * Time: 18:27
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use DB;
class Area extends Model
{

    public function get_sub_areas($parent_id)
    {
        return DB::table('areas')->where('parent_id', $parent_id)->get();
    }

    public function get_all(){
        return DB::table('areas')->get();
    }

    public function get_recursive(array $area_id_arr = []){
        $query = DB::table('areas');
        if (!empty($area_id_arr)) {
            $query = $query->whereIn('id', $area_id_arr);
        }
        $areas = $query->get();
        foreach($areas as $area) {
            $area = (array)$area;
            //如果当前数据父ID等于$parent_id则添加到数组里
            if($area['parent_id'] == 0) {
                $result[$area['id']] = $area;
                $index[$area['id']] =& $result[$area['id']];
            }else {
                $index[$area['parent_id']]['list'][$area['id']] = $area;
                $index[$area['id']] =& $index[$area['parent_id']]['list'][$area['id']];
            }
        }
        $data = [];
        foreach ($result as $row) {
            if (!empty($row['list'])) {
                $rs = array_values($row['list']);
                unset($row['list']);
                $sub = [];
                if (is_array($rs)) {
                    foreach ($rs as $k => $v) {
                        if (!empty($v['list'])) {
                            $rsv = array_values($v['list']);
                            $v['children'] = $rsv;
                            unset($v['list']);
                            $sub[] = $v;
                        }
                    }
                    $row['children'] = $sub;
                }
                $data[] = $row;
            }
        }
        return $data;
    }

    public static function joint_area($provinceId, $cityId, $districtId) {
        $area_id_arr = [];
        if (!empty($provinceId)){
            if (!in_array($provinceId, config('curl.DIRECT_CITY_ID'))) {
                $area_id_arr[] = $provinceId;
            }
        }
        if (!empty($cityId)){
            $area_id_arr[] = $cityId;
        }
        if (!empty($districtId)){
            $area_id_arr[] = $cityId;
        }
        $areas = DB::table('areas')
            ->whereIn('id', $area_id_arr)->orderBy('id')->get();
        $address = '';
        foreach ($areas as $area) {
            $address .= empty($area->area_name)?'':$area->area_name;
        }
        return $address;
    }
    public static function getSubArea($areaId){
        return self::where('parent_id',$areaId)->where('status',1)->get();
    }

    /**
     * @param $areaId 父级ID
     * @param bool $formatFlag 是否格式化返回数据
     * @return array
     */
    public static function getSubAreaList($areaId,$formatFlag=true){
        $areaList=self::where('parent_id',$areaId)->where('status',1)->get();
        if(!$formatFlag)
            return $areaList;
        $areas=[];
        foreach($areaList as $area){
            $areas[$area->id]=$area->area_name;
        }
        return $areas;
    }


}