<?php

namespace App\Admin\Extensions;

use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OrderStatusRefundAction extends RowAction
{
    public $name = '退款';
    public function form(){
        $this->text('refund_reason','退款原因')->rules('required');
    }
    public function handle(Model $model,Request $request){
        //todo easywechat退款操作
        $refundReason=$request->get('refund_reason');
        $model->update(['status'=>ORDER_PAYBACKED,'refund_reason'=>$refundReason]);
        return $this->response()->success('退款成功')->refresh();
    }
}
