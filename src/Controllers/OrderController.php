<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\Actions\OrderStatusCancelAction;
use App\Admin\Extensions\Actions\OrderStatusRefundAction;
use App\Admin\Extensions\Actions\OrderStatusReturnAction;
use App\Admin\Extensions\Actions\OrderStatusSendAction;
use App\Admin\Extensions\Exporter\OrderExcelDemo;
use App\Admin\Extensions\Exporter\OrderExcelExporter;
use App\Admin\Extensions\Exporter\OrderExport;
use App\Admin\Extensions\Filter\CustomBetween;
use App\Admin\Extensions\Importer\OrderImport;
use App\Admin\Extensions\Tools\ExpressExporter;
use App\Facades\Logger;
use App\Models\Order;
use App\Http\Controllers\Controller;
use App\Models\RobinFiles;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use function foo\func;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Excel;
class OrderController extends Controller
{
    use ModelForm;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {

            $content->header('列表');
            $content->description('订单');

            $content->body($this->grid());
        });
    }


    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {

            $content->header('编辑');
            $content->description('订单');

            $content->body($this->form($id)->edit($id));
        });
    }
    public function destroy($id)
    {
        Order::where('id',$id)->update(['status'=>0]);
        return response()->json([
            'status'  => true,
            'message' => trans('admin.delete_succeeded'),
        ]);
    }


    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(function (Content $content) {

            $content->header('创建');
            $content->description('订单');

            $content->body($this->form());
        });
    }
    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(Order::class, function (Grid $grid) {
            if(!Admin::user()->isAdministrator()){
                $grid->model()->where('channel',Admin::user()->channel);
            }
            $grid->exporter(new OrderExcelExporter);
            $grid->disableCreateButton();
            $grid->model()->orderByDesc('order_no');
            $grid->tools(function($tools){
                $url=url('order/import',request()->all());
                $tools->append("<a href='$url'  class='btn btn-sm btn-twitter pull-right' style='margin-right:10px;background-color:#1e94af;'><i class='fa fa-upload'></i>批量发货</a>");
            });

            $grid->filter(function(Grid\Filter $filter) {
                $filter->disableIdFilter();
                $filter->equal('order_sn', '订单编号');
                $filter->equal('user.openid', 'OPENID');
                $filter->like('user.nickname', '用户名');
                $filter->between('total_price','订单金额');
                $filter->use(new CustomBetween('order','created_at','创建时间'))->datetime();
                $filter->in('status','状态')->checkbox(Order::$statusArr);
            });

            $grid->actions(function(Grid\Displayers\Actions $actions){
                $actions->disableView();
                switch ($actions->row->status){
                    case ORDER_COMMITED:
                        $actions->add(new OrderStatusCancelAction);break;
                    case ORDER_PAIED:
                        $actions->add(new OrderStatusRefundAction);
                        $actions->add(new OrderStatusSendAction);break;
                    case ORDER_RETURNED:
                        $actions->add(new OrderStatusRefundAction);break;
                    case ORDER_SENDED:
                        $actions->add(new OrderStatusReturnAction);break;
                }
            });
            $grid->column('id','订单ID')->sortable();
            if(Admin::user()->isAdministrator()){
                $grid->column('channel','渠道标识');
            }
            $grid->column('order_sn','订单编号');
            $grid->column('user.nickname','用户名');
            $grid->column('content','购买内容');
            $grid->column('total_price','订单金额')->filter('range')->totalRow(function ($amount) {
                return "<span class='text-danger text-bold'>合计：<i class='fa fa-yen'></i> {$amount}</span>";
            });
//            $grid->column('transaction_id','支付流水号');
            $grid->column('created_at','创建时间');
            $grid->column('status','状态')->filter(Order::$statusArr)->using(Order::$statusArr);
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id=null)
    {
        return Admin::form(Order::class, function (Form $form) use ($id) {
            $orderInfo=Order::with('product_list')->find($id);
            $form->display('id', '订单ID');
            $form->display('order_sn','订单编号');
            $form->display('user.nickname','用户名');
            $form->display('member_id','会员编号');
            $form->display('total_price','订单金额');
            $form->display('transaction_id','支付流水');
            $form->display('out_trade_no','商户订单号');
            $form->display('pay_time','支付时间');
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');
            $form->select('status', '状态')->options(Order::$statusArr)->readOnly();
            $form->html(function() use ($orderInfo){
                return view('admin.form.order.order_product_list',['products'=>$orderInfo->product_list]);
            },'订单商品');
            $form->divider('发票信息');
            $form->display('invoice_title', '抬头');
            $form->display('invoice_tax_number', '税号');
            $form->display('invoice_bank_name', '开户行');
            $form->display('invoice_bank_account', '账号');
            $form->display('invoice_tel', '企业电话');
            $form->display('invoice_company_address', '开票地址');
            $form->text('invoice_no', '发票号');
            $form->date('invoice_date', '开票日期');
            $form->divider('地址信息');
            $form->text('address_name','收货人')->rules('required');
            $form->text('address_mobile','手机')->rules('required');
            $form->text('address_province_name','省');
            $form->text('address_city_name','市');
            $form->text('address_district_name','区');
            $form->text('address_street', '详细地址')->rules('required');
            $form->display('receive_time', '收货时间');

            $form->divider('物流信息');
            $form->text('express_company','快递公司');
            $form->text('express_company_code','快递公司编号');
            $form->text('express_no','快递单号');
            $form->textarea('comment','备注');
            $form->display('send_time','发货时间');
        });
    }
    public function update($id)
    {

        $status=request()->get('status');

        return $this->form($id)->update($id);
    }




    public function import(Request $request ){
        $sheetDataArr=[];
        if($request->hasFile('excel_file')){
            $fileInfo=$request->file('excel_file');
            Excel::import(new OrderImport,$fileInfo);
            return  redirect('/admin/order');
        }
        $form=Admin::form(RobinFiles::class, function (Form $form)  use($sheetDataArr){
            $form->setAction('/admin/order/import');
            $form->file('excel_file', '订单Excel')->setWidth(3)->rules('required|mimes:xlsx')->help("导出的订单数据，填充完 物流公司【V】和 物流单号【W】之后再导入以进行批量发货操作");
        });
        return Admin::content(function (Content $content) use ($form) {
            $content->header('订单');
            $content->description('批量发货');
            $content->body($form);
        });
    }

    public function download_express_demo()
    {
        return (new OrderExcelDemo())->download('订单导入模板.xlsx');

    }
}

