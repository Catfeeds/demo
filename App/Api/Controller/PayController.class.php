<?php

namespace Api\Controller;
use Think\Controller;

/**
 * @author vaio
 * @datetime 2016-4-25  10:32:10
 * @encoding UTF-8 
 * @filename PayController.class.php 
 */
class PayController extends ApiController{

    protected $pay_channel;

    public function request_pay($pay_channel, $order_number) {
        switch ($this->_method) {
            case 'post':
                if(!isset($order_number))
                    $order_number = I('post.order_number');
                if(!isset($pay_channel))
                    $pay_channel = I('post.pay_channel');
                if (empty($order_number))
                    $this->client_return(0, '订单号不能为空！');
                switch (strtolower($pay_channel)) {
                    case 'alipay':
                        $this->pay_channel = 1;
                        $order_info = self::pay_init($order_number);
                        $pay = new \Api\Model\PayModel($pay_channel);
                        $result = $pay->request_pay($order_info['order_number'], $order_info['product_name'], $order_info['pay_money']);
                        //$result = $pay->request_pay($order_info['order_number'], $order_info['product_name'], 0.01);
                        $response['sign'] = $result;
                        break;
                    case 'wxpay':
                        $this->pay_channel = 2;
                        $order_info = self::pay_init($order_number);
                        $pay = new \Api\Model\PayModel($pay_channel);
                        $response = $pay->request_pay($order_info['order_number'], $order_info['product_name'], $order_info['pay_money']);
                        break;
                    default :
                        $this->client_return(0, '支付渠道为空或不正确！');
                        break;
                }
                $this->client_return(1, '获取成功！', $response);
                break;
        }
    }

    public function alipay_notify(){
        //获取当前API版本
        $version = explode('_',MODULE_NAME)[1];
        switch ($this->_method){
            case 'post':
                vendor('Alipay.alipay', VENDOR_PATH, '.config.php');
                vendor('Alipay.lib.alipay_notify', VENDOR_PATH, '.class.php');
                $alipay_config = alipay_config($version);
                //计算得出通知验证结果
                $alipayNotify = new \AlipayNotify($alipay_config);
                $verify_result = $alipayNotify->verifyNotify();
                if($verify_result && $_POST['trade_status'] == 'TRADE_SUCCESS') {//验证成功
                    $pay = new \Api\Model\PayModel('alipay');
                    if(true === $pay->pay_notify($_POST))
                        echo "success";		//请不要修改或删除
                    else
                        echo "fail";
                }
                echo "fail";
                break;
        }
    }

    public function wxpay_notify(){
        //获取当前API版本
        $version = explode('_',MODULE_NAME)[1];
        switch ($this->_method){
            case 'post':
                Vendor("Wxpay.WxPayPubHelper");
                $xml = file_get_contents('php://input');
                $Wxpay_server = new \Wxpay_server_pub($version);
                $Wxpay_server->saveData($xml);
                $bool = $Wxpay_server->checkSign();
                if($bool === true){//
                    $request = $Wxpay_server->getData();//微信支付异步回调数据
                    $pay = new \Api\Model\PayModel('wxpay');
                    if($pay->pay_notify($request) === true){
                        $Wxpay_server->setReturnParameter('return_code','SUCCESS');
                        $Wxpay_server->setReturnParameter('return_msg','支付成功！');
                    }else{
                        $Wxpay_server->setReturnParameter('return_code','FAIL');
                        $Wxpay_server->setReturnParameter('return_msg','签名失败');
                    }
                }elseif($bool === false){
                    $Wxpay_server->setReturnParameter('return_code','FAIL');
                    $Wxpay_server->setReturnParameter('return_msg','签名失败');
                }
                $Wxpay_server->returnXml();
                break;
        }
    }

    private function pay_init($order_number) {
        $Model_order = M('order as o');
        $map = array(
            'o.order_number' => $order_number,
            'o.pay_status' => 0,
            'o.server_status' => array('in', '2,6'),
        );
        $order_info = $Model_order
                ->join('left join __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                ->field('oi.server_type,oi.guide_type,o.pay_money,o.server_status,o.pay_status,o.guid')
                ->where($map)
                ->find();
        if ($order_info['pay_money']) {
            $data = array(
                'order_number' => $order_number,
                'request_pay_no' => $pay_order_no = create_order_no(),
                'pay_channel'   =>  $this->pay_channel,
                'is_pay' => 0,
            );
            if (M('pay')->lock(true)->add($data)) {
                $guide_type_string = $this->get_guide_type_string($order_info['guide_type']);
                $guide_name = M('guide_member_info')->getFieldByUid($order_info['guid'],'realname');
                return array(
                    'product_name' => '途途导由-'.$guide_name.$guide_type_string,
                    'pay_money' => $order_info['pay_money'],
                    'order_number' => $pay_order_no,
                );
            }
            $this->client(0, '支付请求失败！');
        }
        $this->client(0, '订单号有误！');
    }
}

