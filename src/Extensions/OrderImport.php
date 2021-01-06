<?php
/**
 * Created by robin@2019/8/30 16:06
 */

namespace Ejoy\Shop\Extensions;


use App\Models\ExpressConfig;
use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use App\Facades\Log;

class OrderImport implements ToCollection,WithStartRow
{
    public function startRow(): int
    {
        return 2;
    }
    public function collection(Collection $rows)
    {
        foreach($rows as $row){
            if(!empty($row[1]) && !empty($row[22])){
                $orderModel=Order::query()->find($row[1]);
                $express_no=$orderModel->express_no;
                $expressConfigInfo=ExpressConfig::where('company_name',$row[21])->first();
                if(empty($expressConfigInfo->company_code))
                    Log::info("订单ID为".$row['1']."的快递公司不存在");
                $orderModel->update(['express_company'=>$row[21],'express_company_code'=>$expressConfigInfo->company_code,'express_no'=>$row[22],'send_time'=>date("Y-m-d H:i:s"),'status'=>ORDER_SENDED]);
                if(empty($express_no) && !empty($orderModel->express_no)){//若导入新的物流单号数据，则发送订阅消息
                    $orderModel->sendSubscribeMsg();
                }
            }
            return true;
        }

    }

}
