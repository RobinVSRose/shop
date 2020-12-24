<?php

namespace Ejoy\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $table="user_address";
    protected $fillable=['id','member_id','name','mobile','province_id','province_name','city_id','city_name','district_id','district_name','street','status'];
    public function formatAreaString(){
        $provinceInfo=Area::find($this->province_id);
        $cityInfo=Area::find($this->city_id);
        $districtInfo=Area::find($this->district_id);
        $this->province_name=!empty($provinceInfo->area_name)?$provinceInfo->area_name:"";
        $this->city_name=!empty($cityInfo->area_name)?$cityInfo->area_name:"";
        $this->district_name=!empty($districtInfo->area_name)?$districtInfo->area_name:"";
        $this->full_address_name=$this->province_name." ".$this->city_name." ".$this->district_name." ".$this->street;
    }
    public static function formatAddressToString(&$addressIdArr){
        $return=['province_name'=>'','city_name'=>'','district_name'=>'','full_address_name'=>''];
        if(!empty($addressIdArr['province_id'])){
            $provinceInfo=Area::find($addressIdArr['province_id']);
            $addressIdArr['province_name']=!empty($provinceInfo->area_name)?$provinceInfo->area_name:"";
        }
        if(!empty($addressIdArr['city_id'])){
            $cityInfo=Area::find($addressIdArr['city_id']);
            $addressIdArr['city_name']=!empty($cityInfo->area_name)?$cityInfo->area_name:"";
        }
        if(!empty($addressIdArr['district_id'])){
            $districtInfo=Area::find($addressIdArr['district_id']);
            $addressIdArr['district_name']=!empty($districtInfo->area_name)?$districtInfo->area_name:"";
        }
        $addressIdArr['full_address_name']= $addressIdArr['province_name']." ". $addressIdArr['city_name']." ". $addressIdArr['district_name']." ". (!empty($addressIdArr['street'])?$addressIdArr['street']:"");
    }
}
