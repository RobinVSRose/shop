<?php

namespace Ejoy\Shop\Extensions;

use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;

class OrderStatusCancelAction extends RowAction
{
    public $name = '取消';
    public function dialog()
    {
       $this->confirm('确定取消订单吗？');
    }
    public function handle(Model $model){
        $model->update(['status'=>ORDER_CANCELED]);
        return $this->response()->success('取消订单成功')->refresh();
    }
}
