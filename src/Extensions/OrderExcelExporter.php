<?php

namespace Ejoy\Shop\Extensions;

use Encore\Admin\Grid\Exporter;
use Encore\Admin\Grid\Exporters\AbstractExporter;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use DB;

class OrderExcelExporter extends AbstractExporter implements FromArray,WithColumnFormatting,ShouldAutoSize
{
    use Exportable;
    protected $fileName = '订单.xlsx';

//    protected $columns = [
//        'id'                    => 'ID',
//        'name'                  => '姓名',
//        'status'                => '状态',
//        'profile.homepage'      => '主页',
//    ];

    CONST HEAD=[['序号','订单ID','订单编号','微信支付流水号','会员编号','会员名称','销售日期',
        '销售单号','商品ID','商品名称','规格型号','数量','单价','金额','订单状态','收款日期','收款方式',
        '公账到账日期','公账到账金额','公账到账手续费',
        '发货仓库','快递方式','快递单号','快递日期','快递费金额','收件人','收件地址',
        '电话','是否开票','发票号','发票抬头','税号','备注']];
    public function export()
    {
        $this->download($this->fileName)->prepare(request())->send();
    }
    public function columnFormats(): array
    {
        return ['E'=>NumberFormat::FORMAT_NUMBER,'W'=>NumberFormat::FORMAT_NUMBER];
    }

    public function array(): array
    {
        $orderStatusArr=config('state.order.status');
        $query=$this->grid->model();
//        $query->getQueryBuilder()->getQuery()->cloneWithout(['orders']);//清除ID排序
        $query->select(['order.order_no','order.id','order.order_sn','order.transaction_id','order.member_id','user.nickname',
            'order.pay_time','order.order_no as sell_date','op.product_id','op.product_name',DB::raw("CONCAT(t_op.product_attr_name,':',t_op.product_attr_value) as product_attr"),
            'op.product_num','op.deal_price','order.total_price','order.status','order.pay_time as company_money_time','order.pay_type',
            DB::raw('"" as company_money_date'),DB::raw('"" as company_money'),DB::raw('"" as company_commission_money'),DB::raw('"" as send_from_store'),
            'order.express_company','order.express_no','order.send_time',DB::raw('"" as express_money'),'order.address_name',
            DB::raw("CONCAT(`t_order`.`address_province_name`,`t_order`.`address_city_name`,`t_order`.`address_district_name`,`t_order`.`address_street`)"),
            'order.address_mobile',DB::raw('"" as invoice_flag'),'order.invoice_no','order.invoice_title','order.invoice_tax_number','comment'])
            ->leftJoin('user','user.member_id','=','order.member_id')
//            ->leftJoin('user_binds','user_binds.member_id','=','order.member_id')
            ->leftJoin('order_product as op','op.order_id','=','order.id')
            ->orderBy('order_no');
        $tmpOrderId=0;
        $dataArr=$this->getData();
        $a=self::HEAD;
        foreach($dataArr as $k=>$v){
            if($v['status']==ORDER_PAYBACKED)
                $v['total_price']="-".$v['total_price'];

            $v['status']=!empty($orderStatusArr[$v['status']])?$orderStatusArr[$v['status']]:"";
            $v['invoice_flag']=!empty($v['invoice_tax_number'])?"是":"否";
            if($tmpOrderId==$v['id']){
                foreach($v as $k1=>&$v1){
                    if(in_array($k1,['product_id','product_name','product_attr','product_num'])){
                        continue;
                    }
                    $v1='';
                };
            }
            $tmpOrderId=$v['id'];

            $a[]=$v;
        }
        return $a;
    }
    public function withScope($scope)
    {
        if ($scope == Exporter::SCOPE_ALL) {
            return $this;
        }

        list($scope, $args) = explode(':', $scope);

        if ($scope == Exporter::SCOPE_CURRENT_PAGE) {
            $this->grid->model()->usePaginate(true);
            $this->page = $args ?: 1;
        }

        if ($scope == Exporter::SCOPE_SELECTED_ROWS) {
            $selected = explode(',', $args);
            $this->grid->model()->whereIn('order.id', $selected);
        }
        return $this;
    }
}
