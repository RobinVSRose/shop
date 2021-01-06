<?php

namespace Ejoy\Shop\Models;

use App\Facades\Log;
use App\Facades\Logger;
use App\Models\AdminModel;
use App\Models\User;
use EasyWeChat\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends AdminModel
{
    public static $statusArr=['0'=>'删除','1'=>'未付款','2'=>'已付款','3'=>'已发货','4'=>'已取消','5'=>'已收货','6'=>'已退货','7'=>'已退款'];
    protected $table="order";
    public $fillable=['channel','prepay_id','content','order_sn','member_id','address_name','address_mobile','address_province_name','address_city_name','address_district_name','address_street',
                        'invoice_title','invoice_tax_number','invoice_bank_name','invoice_bank_account','invoice_tel','invoice_company_address','invoice_no','invoice_date',
                        'user_coupon_id','total_price','total_bitcoin','pay_time','send_time','receive_time','transaction_id','out_trade_no','comment','express_company',
                        'express_company_code','express_no','send_msg_flag','status','origin_price','wechat_address_json','wechat_invoice_json','refund_reason','openid','order_no','pay_type'];
    public function products(){
        return $this->belongsToMany(Product::class,'order_product','order_id','product_id')
            ->withPivot(['order_id','product_id','product_num','product_attr_id','product_attr_name','product_attr_value']);
    }
    public function user(){
        return $this->belongsTo(User::class,'member_id','member_id');
    }
    public function product_list(){
        return $this->hasMany(OrderProduct::class,'order_id','id');
    }
    public function getWechatInvoiceJsonAttribute($json){
        return json_decode($json,true);
    }




    public static function signOrder($unifyData){//微信统一下单，生成支付参数。
        $wechatPayApp=Factory::payment(config('curl.mini'));
        $return=$wechatPayApp->order->unify($unifyData);//统一下单
        // return=( [return_code] => SUCCESS [return_msg] => OK [appid] => wx6bc6a5c73e979ffd [mch_id] => 1401801902 [nonce_str] => N3qWWipL3jqV4Rj2 [sign] => 5773EF09149A877FF715E0B364C0CB67 [result_code] => SUCCESS [prepay_id] => wx21171004238680edbdbfee041826545000 [trade_type] => JSAPI )

        if(empty($return['prepay_id'])){
            Log::error('统一下单接口数据异常：',$return);
            response_json($return,'FAIL','微信参数签名错误');
        }
        $signData=$wechatPayApp->jssdk->bridgeConfig($return['prepay_id'],false);//支付参数签名
        $signData['prepay_id']=$return['prepay_id'];
        return $signData;
        //$rs={"appId":"wx6bc6a5c73e979ffd","timeStamp":"1566379559","nonceStr":"5d5d0e27242b2","package":"prepay_id=wx21172559118207d74e4886eb1692199500","signType":"MD5","paySign":"F604518DE33B0F055509733DC6A46925"}
    }

    public static function calculateOrder($attrIdNumArr){
        $member_id=$GLOBALS['userInfo']['userInfo']['member_id'];
        $expressCostArr=[0.00];
        $return=['total_price'=>0,'origin_price'=>0,'order'=>[]];
        $attrIdArr=array_keys($attrIdNumArr);
        $productArr=ProductAttr::with('product')->whereIn('id',$attrIdArr)->get()->toArray();
        if(count($attrIdNumArr)!=count($productArr))
            response_json(null,'SHOPCARTUPDATEERROR');
        foreach($productArr as $k=>$attr){
            if(empty($attr['product']))
                response_json(null,'1002','产品信息不存在');
            $expressCostArr[]=intval($attr['product']['express_cost']);
            $leftNum=$attr['num']-$attr['saled_num'];
            if($leftNum<$attrIdNumArr[$attr['id']])//查询该产品库存数量是否足够
                response_json(null,'NOMOREPRODUCT');
            $return['origin_price']+=intval($attr['price']*$attrIdNumArr[$attr['id']]*100)/100;//原价累加
            $return['order']['product'][]=['member_id'=>$member_id,'product_id'=>$attr['product']['id'],'product_attr_name'=>$attr['name'],
                'product_attr_id'=>$attr['id'],'product_attr_value'=>$attr['value'],'product_price'=>$attr['price'],'product_name'=>$attr['product']['name'],
                'product_num'=>$attrIdNumArr[$attr['id']],'product_inner_price'=>$attr['inner_price'],'deal_price'=>$attr['price'],'thumb'=>json_encode($attr['product']['thumb'])];
            $return['order']['content'][]=$attr['product']['name']."*".$attrIdNumArr[$attr['id']];
        }
        $expressCost=max($expressCostArr);
        $return['total_price']=intval(($return['origin_price']+$expressCost)*100)/100;
        $return['express_cost']=$expressCost>0?$expressCost."元":"包邮";
        return $return;
    }

    public function updateOrder($streamData,$errorMsg=""){

        if(empty($errorMsg)){//若支付回调的数据无异常，则更新订单状态&发送呢服务通知
            DB::transaction(function() use($streamData){//事物会自动提交
                $now=date("Y-m-d H:i:s");
                $orderNo=Order::query()->max('order_no');
                $this->update(['pay_time'=>$now,'status'=>ORDER_PAIED,'out_trade_no'=>(!empty($streamData['out_trade_no'])?$streamData['out_trade_no']:''),
                    'transaction_id'=>(!empty($streamData['transaction_id'])?$streamData['transaction_id']:''),'order_no'=>$orderNo+1,'pay_type'=>'商城']);
            },2);//死锁时重试

//            $data=[
//                'touser'=>$streamData['openid'],
//                'template_id'=>'N6VgBbRkXdJeT2_3daKwC3QEwkEyLClsqLU8boTgCHA',
//                'page'=>'pages/orderDetail/orderDetail?order_id='.$this->id,
//                'form_id'=>$this->prepay_id,
//                'data'=>[
//                    'keyword1'=>$this->order_sn,
//                    'keyword2'=>$this->content,
//                    'keyword3'=>$this->total_price,
//                    'keyword4'=>'已支付',
//                    'keyword5'=>$this->address_name,
//                ]
//            ];
//            WechatTemplate::sendTemplateMsg($data);
        }
        PayLog::insert(['member_id'=>$this->member_id,'order_id'=>$this->id,'order_sn'=>$this->order_sn,'out_trade_no'=>(!empty($streamData['out_trade_no'])?$streamData['out_trade_no']:''),
                        'transaction_id'=>(!empty($streamData['transaction_id'])?$streamData['transaction_id']:''),'status'=>(!empty($errorMsg)?0:1),'comment'=>'微信支付:'.(!empty($errorMsg)?$errorMsg:'成功'),
                        'cash_fee'=>(!empty($streamData['cash_fee'])?$streamData['cash_fee']/100:0),'coupon_fee'=>(!empty($streamData['coupon_fee'])?$streamData['coupon_fee']/100:0),
                        'total_fee'=>(!empty($streamData['total_fee'])?$streamData['total_fee']/100:0),'attach'=>$streamData['attach']]);
        return true;
    }


    public static function collection()
    {
        $statusArr=Order::$statusArr;
        $inputData=request()->all();
        $query=Order::with('product_list')->leftJoin('user_bases','user_bases.member_id','=','order.member_id')
            ->leftJoin('user_binds','order.member_id','=','user_binds.member_id')->leftJoin('areas as province','order.address_province_id','=','province.id')
            ->leftJoin('areas as city','order.address_city_id','=','city.id')->leftJoin('areas as district','order.address_district_id','=','district.id')
            ->where('order.id','<',3000);


        if(!empty($inputData['bind']['openid']))
            $query->where('user_binds.openid',$inputData['bind']['openid']);
        if(!empty($inputData['base']['name']))
            $query->where('user_bases.name','like',"%{$inputData['base']['name']}%");
        if(!empty($inputData['user_base']['mobile']))
            $query->where('user_bases.mobile','like',"%{$inputData['user_base']['mobile']}%");
        if(!empty($inputData['status']))
            $query->where('order.status',$inputData['status']);

        if(!empty($inputData['created_at']['start']))
            $query->where('order.created_at','>=',$inputData['created_at']['start']);
        if(!empty($inputData['created_at']['end']))
            $query->where('order.created_at','<=',$inputData['created_at']['end']);
        $data=$query->get(['order.id','order.order_sn','order.member_id','user_bases.name','order.user_coupon_id','order.total_price','order.total_bitcoin','user_binds.openid','order.created_at',
            'order.pay_time','order.status','order.transaction_id','order.out_trade_no','order.express_company','order.express_site','order.express_no',
            'order.address_name','order.address_mobile','province.area_name as province_name','city.area_name as city_name','district.area_name as district_name','order.address_street','order.pay_time']);
        $collections=new \Illuminate\Database\Eloquent\Collection();
        foreach($data as $k=>&$v){
            $v->status=$statusArr[$v->status];
            $collections->add($v);
            foreach($v->product_list as $product){
                $collections->add(['','',
                    $product->product_id,$product->product_name,$product->product_num,$product->real_exchange_price,$product->real_exchange_bitcoin,$product->product_exchange_price,$product->product_exchange_bitcoin.""]);
            }
            unset($v->product_list);
        }
        return $collections;
    }

    function changeOrderStatus($id){
        $status=request()->get('status');
        if(!empty($id) && ($status==ORDER_SENDED || $status==ORDER_PAYBACKED || $status==ORDER_CANCELED)){//退款|发货提醒
            $userBindInfo=UserBind::where('member_id',$this->member_id)->first();
            if(empty($this) || empty($userBindInfo)){
                return ['status'=>false,'message'=>'未找到用户或订单数据！'];
            }
            if($status==ORDER_PAYBACKED) {//退款 提醒
                $dataArr = [
                    'orderProductPrice' => $this->total_price,
                    'orderProductName' => $this->content,
                    'orderName' => $this->order_sn
                ];
                $result = Wechat::send_wechat_template_msg(time(), $userBindInfo->openid, 5, $dataArr, WECHAT_URL_ORDER . $this->id, null, [], 'template_message_job');
                if ($result) {
                    Log::info('订单退款 模板消息提醒', [$result], 'admin');
                }
                //todo 商户平台退款接入
                return ['status'=>true,'message'=>'退款成功'];
            }else if($status==ORDER_SENDED){//发货提醒
                $dataArr = [
                    'wechat_name' => '',
                    'keyword1' => $this->order_sn,
                    'keyword2' => $this->express_company,
                    'keyword3' => $this->express_no
                ];
                $result = Wechat::send_wechat_template_msg(time(), $userBindInfo->openid, 11, $dataArr, WECHAT_URL_ORDER.$this->id, null, [], 'template_message_job');
                return ['status'=>true,'message'=>'发货成功'];
            }else if($status==ORDER_CANCELED){
                return ['status'=>true,'message'=>'订单已取消'];
            }
        }
        return ['status'=>true,'message'=>'成功！'];

    }

    public function sendSubscribeMsg(){//发货成功通知
        $sendTemplateId=config($this->channel.'.sended_msg');
        if(empty($this->openid) || empty($sendTemplateId))
            return ['errcode'=>1,'errmsg'=>'OPENID或模板ID为空'];
        $data=[
            'touser'=>$this->openid,
            'template_id'=>$sendTemplateId,
            'page'=>'pages/orderDetail/orderDetail?order_id='.$this->id,
            'data'=>[
                'thing1'=>['value'=>$this->content?(strlen($this->content)>20?substr($this->content,0,16)."...":$this->content):" "],
                'thing3'=>['value'=>$this->express_company??' '],
                'character_string4'=>['value'=>$this->express_no??' '],
                'thing2'=>['value'=>' '],
            ]
        ];
        return WechatTemplate::sendTemplateMsg($data,$this->channel);
    }



}
