<?php
namespace Api_v3\Model;

/**
 * @author vaio
 * @datetime 2016-5-11  15:58:31
 * @encoding UTF-8 
 * @filename PayModel.class.php
 */

class PayModel extends \Think\Model{

    private $pay_channel;
    private $version;
    
    public function __construct($pay_channel) {
        $this->version = explode('_',MODULE_NAME)[1];
        $this->pay_channel = strtolower($pay_channel);
    }
    
    public function request_pay($order_number, $product_name, $pay_money, $body = '') {
        //构造要请求的参数数组
        switch($this->pay_channel){
            case 'alipay':
                vendor('Alipay.alipay', VENDOR_PATH, '.config.php');
                $alipay_config = alipay_config($this->version);
                $parameter = array(
                    "service" => $alipay_config['service'],
                    "partner" => $alipay_config['partner'],
                    "payment_type" => $alipay_config['payment_type'],
                    "notify_url" => $alipay_config['notify_url'],
                    "out_trade_no" => $order_number,
                    "subject" => $product_name,
                    "total_fee" => $pay_money,
                    "body" => $body,
                    'seller_id' => $alipay_config['partner'], //收款人
                    "_input_charset" => trim(strtolower($alipay_config['input_charset']))
                );
                //建立请求
                vendor('Alipay.lib.alipay_submit', VENDOR_PATH, '.class.php');
                $alipaySubmit = new \AlipaySubmit($alipay_config);
                return $alipaySubmit->buildRequestParaToString($parameter);
                break;
            case 'wxpay':
                Vendor("Wxpay.WxPayPubHelper");
                $wxpay_config = wxpay_config($this->version);
                $UnifiedOrder = new \UnifiedOrder_pub($this->version);
                $UnifiedOrder->setParameter('body',$product_name);
                $UnifiedOrder->setParameter('out_trade_no',$order_number);
                $UnifiedOrder->setParameter('total_fee',intval(bcmul($pay_money,100,0)));
                $UnifiedOrder->setParameter('notify_url',$wxpay_config['notify_url']);
                $UnifiedOrder->setParameter('trade_type',$wxpay_config['trade_type']);
                if($prepayId = $UnifiedOrder->getPrepayId()){
                    $Wxpay_client = new \Wxpay_client_pub($this->version);
                    $Wxpay_client->setParameter('appid',$wxpay_config['appid']);
                    $Wxpay_client->setParameter('partnerid',$wxpay_config['mch_id']);
                    $Wxpay_client->setParameter('prepayid',$prepayId);
                    $Wxpay_client->setParameter('package','Sign=WXPay');
                    $Common_util_pub = new \Common_util_pub($this->version);
                    $Wxpay_client->setParameter('noncestr',$Common_util_pub->createNoncestr());
                    $Wxpay_client->setParameter('timestamp',NOW_TIME);
                    $Wxpay_client->setParameter('sign',$Wxpay_client->getSign($Wxpay_client->parameters));
                    return $Wxpay_client->parameters;
                }
                break;
        }
    }
    
    
    public function pay_notify($request){
        switch ($this->pay_channel){
            case 'alipay':
                $pay_money = $request['total_fee'];//支付金额
                $request_pay_no = $request['out_trade_no'];//与第三方请求交易号
                $response_pay_no = $request['trade_no'];//与第三方返回交易号
                $pay_time = strtotime($request['gmt_payment']);//支付时间
                $pay_account = $request['buyer_email'];//支付账户
                $pay_channel = 1;
                break;
            case 'wxpay':
                $pay_money = bcdiv($request['total_fee'],100,2);//支付金额
                $request_pay_no = $request['out_trade_no'];//与第三方请求交易号
                $response_pay_no = $request['transaction_id'];//与第三方返回交易号
                $pay_time = strtotime($request['time_end']);//支付时间
                $pay_account = $request['bank_type'];//支付账户
                $pay_channel = 2;
                break;
        }

        $Model_pay = M('pay');
        $Model_order = M('order');
        $Model_amount = M('amount');
        $Model_guide_info = M('guide_member_info');
        $map_pay = array(
            'request_pay_no'    =>  $request_pay_no,
            'is_pay'    =>  0,
            'pay_channel'   => $pay_channel,//1.支付宝支付；2.微信支付
        );
        if($pay_info = $Model_pay->where($map_pay)->field('order_number')->find()){
            $map_order = array(
                'order_number'  =>  $pay_info['order_number'],
                'pay_status'    =>  0,
            );
            /*
             * 平台管理费、提成计算规则：（服务费-减免费）*管理费/提成收取比例
             */
            if($order_info = $Model_order->where($map_order)->field('yd_id,server_status,guid,order_money,rebate_money,derate_money,tip_money,pay_money')->find()){
                $guide_type = M('order_info')->getFieldByOrder_number($pay_info['order_number'],'guide_type');//获取导游类型
                if($order_info['server_status'] == 2){//取消费收取不扣
                    $service_manage_fee = 0;
                }elseif ($order_info['yd_id'] == -1 && $order_info['server_status'] == 6){//云游易导预约下单不扣平台管理费
                    $data = array(
                        'pay_time'  =>  $pay_time,
                        'pay_type'  =>  $pay_channel,
                        'pay_status'    =>  1,
                    );
                    if(false === M('order')->where($map_order)->save($data))
                        return false;
                    //创建云游已到电子行程单
                    $this->createOrder($pay_info['order_number']);
                    return true;
                }else{//正常流程支付并且需扣除平台管理费
                    $base = bcsub($order_info['order_money'],$order_info['derate_money'],2);//平台管理费/提成金额基数
                    $service_manage_fee = bcmul($base,get_config_key_value(C('setting.SERVICE_MANAGE_FEE')),2);//正式生产环境使用
                    if($guide_type == 2){
                        /*
                         * 获取领路人的直属上级并给其提成
                         */
                        $parent_id = M('invite')->getFieldByInvited_id($order_info['guid'],'invite_id');
                        $bonus = bcmul($base,get_config_key_value(C('setting.PARENT_BONUS')),2);
                    }
                }
                //订单收益金额
                $guide_in_amount = bcsub(bcadd($pay_money,$order_info['rebate_money'],2),$service_manage_fee,2);

                $map_guide_info = array(
                    'uid'=>$order_info['guid']
                );
                $data_pay   =   array(
                    'response_pay_no'   =>  $response_pay_no,
                    'is_pay'    =>  1,
                    'pay_time'  =>  $pay_time,
                    'pay_account'   =>  $pay_account,
                );

                $data_order = array(
                    'pay_time'  =>  $pay_time,
                    'pay_type'  =>  $pay_channel,
                    'pay_status'    =>  $order_info['server_status'] == 2 ? 2 : 1,
                );
                $amount_deal = array(
                    'uid'   =>  $order_info['guid'],
                    'order_number'  =>  $request_pay_no,
                    'child_id'  =>  0,
                    'deal_type' =>  1,//交易类型；1-进账；2-出账;
                    'deal_time' =>  NOW_TIME,
                    'deal_amount'   =>  $pay_money,
                    'manage_amount'    =>  $service_manage_fee,
                    'profit_amount' =>  $guide_in_amount,
                );
                $data_amount = array($amount_deal);
                $data_guide_info = array(
                    'history_total_amount'  =>  array('exp','history_total_amount+'.$guide_in_amount),
                    'now_total_amount'  =>  array('exp','now_total_amount+'.$guide_in_amount),
                    'in_amount' =>  array('exp','in_amount+'.$guide_in_amount),
                    'action_amount'    =>   array('exp','action_amount+'.$guide_in_amount),
                );
                if($guide_type == 2 && isset($bonus) && !empty($bonus) && !empty($parent_id)){
                    //处理返利数据
                    $data_parent_info = array(
                        'history_total_amount'  =>  array('exp','history_total_amount+'.$bonus),
                        'now_total_amount'  =>  array('exp','now_total_amount+'.$bonus),
                        'in_amount' =>  array('exp','in_amount+'.$bonus),
                        'action_amount'    =>   array('exp','action_amount+'.$bonus),
                    );
                    $amount_derate = array(
                        'uid'   =>  $parent_id,
                        'order_number'  =>  $request_pay_no,
                        'child_id'  =>  $order_info['guid'],
                        'deal_type' =>  1,//交易类型；1-进账；2-出账;
                        'deal_time' =>  NOW_TIME,
                        'deal_amount'   =>  $bonus,
                        'manage_amount'    =>  0,
                        'profit_amount' =>  $bonus,
                    );
                    $data_amount = array($amount_deal,$amount_derate);
                }
                if(date('w') == 3 || date('w') == 2){
                    //创建redis对象
                    $redisCache = S(array('type' => 'redis', 'expire' => 0, 'prefix' => 'guide_amount'));
                    if(isset($data_guide_info)){
                        unset($data_guide_info['action_amount']);
                        /*
                         * 生成账户金额缓存
                         */
                        $uid = $order_info['guid'];//导游uid
                        //获取当前导游当前的金额缓存
                        $temp = $redisCache->$uid;
                        $cache = $temp !== false ? $temp : array();
                        array_push($cache,$guide_in_amount);
                        $redisCache->$uid = $cache;//生成redis缓存
                    }
                    if(isset($data_parent_info)){
                        unset($data_parent_info['action_amount']);
                        /*
                         * 生成账户金额缓存
                         */
                        //现获取当前导游当前的金额缓存
                        $temp = $redisCache->$parent_id;
                        $cache = $temp !== false ? $temp : array();
                        array_push($cache,$guide_in_amount);
                        $redisCache->$parent_id = $cache;//生成redis缓存
                    }
                    $redis = S(array('type' => 'redis', 'expire' => 0, 'prefix' => 'ide'));
                    $ide = '_ide';
                    $redis->$ide = 1;
                }

                $data_parent_bool = isset($data_parent_info) ? false : true;
                //数据表引擎修改
                $Model = new \Think\Model();
                if (FALSE !== $Model->execute('alter table __ORDER__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __PAY__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __AMOUNT__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=InnoDB')) {
                    $Model_pay->startTrans(); //开启事务
                    if (false === $Model_pay->lock(true)->where($map_pay)->save($data_pay) || false === $Model_order->lock(true)->where($map_order)->save($data_order) || !$Model_amount->addAll($data_amount)
                        || false === $Model_guide_info->lock(true)->where($map_guide_info)->save($data_guide_info) || $data_parent_bool === $Model_guide_info->lock(true)->where(array('uid'=>$parent_id))->save($data_parent_info)) {
                        $Model_pay->rollback(); //回滚
                        //mysql数据库引擎修改
                        $Model->execute('alter table __ORDER__ ENGINE=myisam');
                        $Model->execute('alter table __PAY__ ENGINE=myisam');
                        $Model->execute('alter table __AMOUNT__ ENGINE=myisam');
                        $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=myisam');
                        return false;
                    }
                    $Model_pay->commit(); //事务提交
                    //mysql数据库引擎修改
                    $Model->execute('alter table __ORDER__ ENGINE=myisam');
                    $Model->execute('alter table __PAY__ ENGINE=myisam');
                    $Model->execute('alter table __AMOUNT__ ENGINE=myisam');
                    $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=myisam');
                    return true;
                }
                return -1;
            }
            return true;
        }
        return true;
    }

    /*
     * 下单预约云游易导导游
     */
    private function createOrder($order_number){
        //获取已存储的订单信息
        $order_info = M('order_info')->where(array('order_number'=>$order_number))->field('guidecode,realname,phone,numbers,server_start_time,server_end_time,memo,charging_type')->find();
        if($order_info){
            $info = array(
                'guidecode'  =>  $order_info['guidecode'],
                'contactname'   =>  $order_info['realname'],
                'contactmobile' =>  $order_info['phone'],
                'iqty'  =>  $order_info['numbers'],
                'bgndate'   =>  date('Y-m-d H:i:s',$order_info['server_start_time']),
                'enddate'   =>  date('Y-m-d H:i:s',$order_info['server_end_time']),
                'city'  =>  '成都',
                'memo'  =>  $order_info['memo'],
                'balancetype'   =>  $order_info['charging_type'] == 3 ? 10 : 20,
                'outorderno'    =>  $order_number,
            );
            $domain = C('YYYD_CONFIG.DOMAIN');
            $cid = C('YYYD_CONFIG.ID');
            $key = C('YYYD_CONFIG.KEY');
            $now_date = date('Y-m-d H:i:s');
            $param = json_encode($info);
            //生成签名数据
            $sign = strtolower(md5($cid.$key.$now_date.$param));
            $data = array(
                'cid'   =>  $cid,
                'sign'  =>  $sign,
                'time'  =>  $now_date,
                'info'  =>  $param,
            );
            $path = 'guidews/createInvite';
            $url = $domain.$path;
            $result = json_decode(curl($url, 'post', $data),true);
            switch ($result['result']){
                case 'success':
                    $order = array(
                        'yd_id' =>  $result['info']['Inviteid'],
                        'server_status' =>  0,
                    );
                    M('order')->where(array('order_number'=>$order_number))->save($order);
                    break;
                case 'failure':
                    //导游已经被预约走了，退款
                    break;
                default:
                    $this->client_return(0,'服务器开小差去了;请稍后重试！');
            }
        }
    }
}
