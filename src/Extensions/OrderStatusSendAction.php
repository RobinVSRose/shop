<?php

namespace Ejoy\Shop\Extensions;

use App\Models\FormCollection;
use App\Models\WechatTemplate;
use Encore\Admin\Actions\RowAction;
use Encore\Admin\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OrderStatusSendAction extends RowAction
{
    public $name = '发货';
    public function form(){
        $row=$this->row;
        $this->text('','姓名')->width('50%')->default($row->address_name)->disable();
        $this->text('','手机')->width('50%')->default($row->address_mobile)->disable();
        $this->text('','地区')->width('50%')->default($row->address_province_name." - ".$row->address_city_name." - ".$row->address_district_name)->disable();
        $this->text('','详细地址')->default($row->address_street)->disable();

        $this->text('express_company','快递公司')->default(KUAIDI100_DEFAULT_NAME);
        $this->hidden('express_company_code','快递编号')->rules('required')->default(KUAIDI100_DEFAULT_CODE);
        $this->text('express_no','物流单号')->default($row->express_no)->placeholder('物流单号');
        $this->textarea('comment','备注')->default($row->comment);
    }
    public function handle(Model $model,Request $request){
        $model->update(['comment'=>$request->comment,'send_time'=>date("Y-m-d H:i:s"),'status'=>ORDER_SENDED,'express_company'=>$request->express_company,'express_no'=>$request->express_no,'express_company_code'=>$request->express_company_code]);
        $return=$model->sendSubscribeMsg();
        if(!empty($return['errcode']))
            return $this->response()->info($return['errmsg'])->refresh();
        return $this->response()->success('发货成功,已发送服务通知给用户')->refresh();
    }
}
