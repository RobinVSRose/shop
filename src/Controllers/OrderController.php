<?php

namespace Ejoy\Shop\Controllers;

use App\Facades\Log;
use Ejoy\Shop\Models\Express;
use App\Models\FormCollection;
use App\Models\AdminMini;
use Ejoy\Shop\Models\Order;
use Ejoy\Shop\Models\OrderProduct;
use Ejoy\Shop\Models\Product;
use App\Models\WechatTemplate;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Support\XML;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Validator;
use Illuminate\Support\Facades\DB;
class OrderController extends Controller
{
    public function test(){

        $templateId='N9nETkM9V6jOFVxyGOz8QnIZy98dt4SY-Crr-1hrib0';
        $data=[
            'wechat_name'=>'Robin',
            'keyword1'=>'12321313232321',
            'keyword2'=>'西来抱枕*1',
            'keyword3'=>12.3,
            'keyword4'=>'已支付',
            'keyword5'=>'罗彬',
        ];
        $return=WechatTemplate::sendTemplateMsg($templateId,'oaVMQ0Q8vF_9jW7UySf6gftnI7uk',$data);
    }
    /**
     * @param Request $request
     * 计算商品价格
     */
    public function calculate(Request $request){
        $validator = Validator::make($request->all(), [
            'attr'=>'required|array',
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $result=Order::calculateOrder($request->attr,$request->user_coupon_id);
        unset($result['order']);

        response_json($result);
    }

    /**
     * @param Request $request
     * 提交订单
     */
    public function commit(Request $request){
        $validator = Validator::make($request->all(), [
            'attr'=>'required_without:order_id|array',
            'wechat_address_json'=>'required_without:order_id',
            'total_price'=>'required_without:order_id',
            'order_id'=>'integer',
//            'comment'=>'string'
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $channel=$request->get('channel','mini');
        $miniConfig=AdminMini::query()->where('channel',$channel)->first()->toArray();
        if(empty($miniConfig))
            response_json(null,'CHANNEL_ERROR');
        $productIdArr=$request->get('attr',[]);

        $address=json_decode($request->wechat_address_json,JSON_UNESCAPED_UNICODE);
        if(empty($address))
            response_json(null,'ADDRESSNOTFOUND');
        if(!empty($request->order_id)){//若支付已存在的订单
            $orderModel=Order::where('status',ORDER_COMMITED)->find($request->order_id);
            if(empty($orderModel->id))
                response_json(null,'ORDERNOTFOUND');
        }else{//否则创建订单
            $result=Order::calculateOrder($productIdArr,$request->user_coupon_id);
            Log::info('订单金额比对：',[floatval($result['total_price']),floatval($request->total_price)]);
            if(bccomp(floatval($result['total_price']), floatval($request->total_price),2)!=0)
                response_json(null,'ORDERPRICEERROR');
            $orderModel=new Order();
            $orderContent=!empty($result['order']['content'])?implode(",",$result['order']['content']):"";
            $orderModel->fill([
                'openid'=>$request->openid,'member_id'=>$GLOBALS['userInfo']['userInfo']['member_id'],'order_sn'=>generateUniqueStr('OTN'),
                'address_name'=>$address['userName'],'address_mobile'=>$address['telNumber'],'address_street'=>$address['detailInfo'],'channel'=>$channel,
                'address_province_name'=>$address['provinceName'],'address_city_name'=>$address['cityName'],'address_district_name'=>$address['countyName'],
                'content'=>$orderContent,'total_price'=>$result['total_price'],'wechat_address_json'=>$request->wechat_address_json,'wechat_invoice_json'=>$request->wechat_invoice_json
            ]);
            if(!empty($request->wechat_invoice_json)){//发票信息
                $invoice=json_decode($request->wechat_invoice_json,JSON_UNESCAPED_UNICODE);
                $orderModel->invoice_title=$invoice['title'];
                $orderModel->invoice_tax_number=$invoice['taxNumber'];
                $orderModel->invoice_bank_name=$invoice['bankName'];
                $orderModel->invoice_bank_account=$invoice['bankAccount'];
                $orderModel->invoice_tel=$invoice['telephone'];
                $orderModel->invoice_company_address=$invoice['companyAddress'];
            }
            //{"bankAccount":"120909282210301","bankName":"招商银行股份有限公司广州市体育东路支行","companyAddress":"广州市海珠区新港中路397号自编72号(商业街F5-1)","errMsg":"chooseInvoiceTitle:ok","taxNumber":"91440101327598294H","telephone":"020-81167888","title":"广州腾讯科技有限公司","type":"0"}


        }
        $orderModel->comment=!empty($request->comment)?$orderModel->comment."\n".$request->comment:$orderModel->comment;
        //------------------------------微信支付：统一下单&支付参数签名--------------------
        //---------------------------------微信支付：结束----------------------------
        DB::beginTransaction();
        $flag=true;
        $flag=$flag && $orderModel->save();

        //---------------------------------清除购物车指定的商品---------------------------------
        if(empty($request->order_id)){//若是提交新订单，则修改购物车和库存数量
            Product::updateCartAndStorage($GLOBALS['userInfo']['userInfo']['member_id'],$productIdArr);
            foreach($result['order']['product'] as &$product){
                $product['order_id']=$orderModel->id;
            }
            $flag=$flag && OrderProduct::insert($result['order']['product']);
        }
        if($flag){
            DB::commit();

            response_json(['orderInfo'=>$orderModel->toArray()]);
        }else{
            DB::rollback();
            response_json(null,'FAIL','订单提交失败');
        }
    }

    /**
     * @param Request $request
     * 支付回调
     */
    public function notify(Request $request){
        $message = XML::parse(strval($request->getContent()));
        Log::info('微信支付回调数据：',[$message],'wechat_pay');
        $miniConfig=AdminMini::query()->where('app_id',$message['appid'])->first()->toArray();
        $wechatPayApp=Factory::payment($miniConfig);
        return $wechatPayApp->handlePaidNotify(function($streamData,$fail){
            $errorMsg='';
            if(empty($streamData['attach']))
                Log::info("===========未检测到attach字段（自定义订单类型）", [], 'wechat_pay');
            if($streamData['attach']=="1"){
                $orderInfo=Order::where('order_sn',$streamData['out_trade_no'])->where('status',ORDER_COMMITED)->first();
            }else if($streamData['attach']=="2"){
                $orderInfo=ActivityOrder::where('order_sn',$streamData['out_trade_no'])->where('status',ORDER_COMMITED)->first();
            }else{
                $errorMsg="未知的订单类型【1-商城订单，2-活动订单】";
            }
            if(empty($orderInfo))
                $errorMsg="未找到订单-".$streamData['out_trade_no'];
            if($orderInfo->total_price*100!=$streamData['total_fee'])  //匹配订单金额与  微信支付交易金额是否相等
                $errorMsg="订单金额有误";
            Log::info("===========$errorMsg", [$orderInfo], 'wechat_pay');
            $orderInfo->updateOrder($streamData,$errorMsg);
            return true;
        });
    }

    /**
     * @param Request $request
     * 订单列表
     */
    public function orderList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status'=>'integer'
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $orderQuery=Order::with('product_list')->where('member_id',$GLOBALS['userInfo']['userInfo']['member_id']);
        if(!empty($request->status))
            $orderQuery->where('status',$request->status);
        $allOrder=$orderQuery->orderByDesc('id')->get(['id','total_price','order_sn','status','created_at','wechat_address_json'])->toArray();
        foreach($allOrder as &$v){
            foreach($v['product_list'] as $k=>&$product){
                $product['price_comment']=$product['product_price']."元";
                Product::formatProductDetail($product);
            }
            $v['products']=$v['product_list'];//兼容小程序老版本
            unset($v['product_list']);//兼容小程序老版本
        }
        response_json($allOrder);
    }
    public function orderStatistics(Request $request){
        $orderList=Order::where('member_id',$GLOBALS['userInfo']['userInfo']['member_id'])->where('status','>',0)->get(['id','status']);
        $return=['order_num'=>count($orderList),'commited_num'=>0,'paid_num'=>0,'sended_num'=>0];
        foreach($orderList as $k=>$v){
            if($v['status']==ORDER_COMMITED)
                $return['commited_num']++;
            if($v['status']==ORDER_PAIED)
                $return['paid_num']++;
            if($v['status']==ORDER_SENDED)
                $return['sended_num']++;
        }
        response_json($return);
    }

    /**
     * @param Request $request
     * 订单详情
     */
    public function orderDetail(Request $request){
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',//订单ID
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $orderInfo=Order::with('product_list')->where('member_id',$GLOBALS['userInfo']['userInfo']['member_id'])->where('id',$request->order_id)->first();
        if(empty($orderInfo))
            response_json(null,"ORDERNOTFOUND");
        $orderInfo=$orderInfo->toArray();
        foreach($orderInfo['product_list'] as &$product){
            Product::formatProductDetail($product);
        }
        $orderInfo['products']=$orderInfo['product_list'];
        unset($orderInfo['product_list']);
        $orderInfo['total_price_comment']=$orderInfo['total_price']."元";
        response_json($orderInfo);
    }

    /**
     * @param Request $request
     * 取消订单
     */
    public function cancelOrder(Request $request){
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',//订单ID
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $orderInfo=Order::where('member_id',$GLOBALS['userInfo']['userInfo']['member_id'])->where('status',ORDER_COMMITED)->where('id',$request->order_id)->first();
        if(empty($orderInfo))
            response_json(null,'CANCELEONLYCOMMITED');
        $flag=$orderInfo->update(['status'=>ORDER_CANCELED]);
        $str=$flag?"SUCCESS":"FAIL";
        response_json($orderInfo,$str);
    }

    /**
     * @param Request $request
     * 确认收货
     */
    public function confirmOrder(Request $request){
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',//订单ID
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $orderInfo=Order::where('member_id',$GLOBALS['userInfo']['userInfo']['member_id'])->where('status',ORDER_SENDED)->where('id',$request->order_id)->first();
        if(empty($orderInfo))
            response_json(null,'ORDERHAVENTSEND');
        $flag=$orderInfo->update(['status'=>ORDER_RECEIVED]);
        $str=$flag?"SUCCESS":"FAIL";
        response_json($orderInfo,$str);
    }
    public function expressInfo1(Request $request){//快递鸟物流信息查询
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',//订单ID
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $orderInfo=Order::where('member_id',$GLOBALS['userInfo']['userInfo']['member_id'])->findOrFail($request->order_id);
        if(empty($orderInfo->express_no)){
            response_json(['Success'=>false,'message'=>'订单尚未派送，请耐心等待']);
        }
        $data=['OrderCode'=>$orderInfo->order_sn,'ShipperCode'=>$orderInfo->express_company_code,'LogisticCode'=>$orderInfo->express_no];
        $return=Express::getExpressInfo($data);
        Log::info('--物流信息--',[$return]);
        exit_json(['code'=>2000,'data'=>json_decode($return),'express_company'=>$orderInfo->express_company]);
    }
    public function expressInfo(Request $request){//快递100物流接口查询
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',//订单ID
        ]);
        if ($validator->fails()) {
            response_json(null, 'VALIDATORERROR', $validator->errors()->first());
        }
        $orderInfo=Order::where('member_id',$GLOBALS['userInfo']['userInfo']['member_id'])->findOrFail($request->order_id);
        if(empty($orderInfo->express_no)){
            response_json(['Success'=>false,'message'=>'订单尚未派送，请耐心等待']);
        }
        $return=express_query($orderInfo->express_no);
        if(!empty($return) && $return['message']=='ok'){
            $return['code']=2000;
            $return['express_company']=$orderInfo->express_company;
            $return['order_sn']=$orderInfo->order_sn;
            $return['address_name']=$orderInfo->address_name;
            $return['address_mobile']=$orderInfo->address_mobile;
            $return['address_street']=$orderInfo->address_province_name."-".$orderInfo->address_city_name."-".$orderInfo->address_district_name." ".$orderInfo->address_street;
            $expressStatusArr=config('state.express_status');
            $return['status_desc']=!empty($expressStatusArr[$return['state']])?$expressStatusArr[$return['state']]:"状态未知";
            Log::info('--物流信息--',[$return]);
            exit_json($return);
        }
        response_json(null,'NONEEXPRESSINFO');
    }

}
