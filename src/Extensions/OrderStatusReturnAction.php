<?php

namespace App\Admin\Extensions;

use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OrderStatusReturnAction extends RowAction
{
    public $name = '退货';
    public function dialog(){

       $this->confirm('确认退货吗？');
    }
    public function handle(Model $model,Request $request){
        $model->update(['status'=>ORDER_RETURNED]);
        return $this->response()->success('退货成功')->refresh();
    }
}
