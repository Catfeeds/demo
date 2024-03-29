<?php

namespace Api_v1\Controller;

use Think\Controller\RestController;

class ApiController extends RestController {

    protected $allowMethod = array('get', 'post', 'put'); // REST允许的请求类型列表
    protected $allowType = array('html', 'xml', 'json'); // REST允许请求的资源类型列表
    public $client;//客户端标识   1-用户端；2-导游端
    public $deviceid;
    private $sign;//签名字符串


    private $noCheck = array(
        'alipay_notify',
        'version_update',
        'wxpay_notify',
    );

    protected function _initialize() {
        parent::_initialize();
        //自动执行方法
        $Auto = new \Common\Controller\AutoController();
        $Auto->auto_run();
        $request = getallheaders();
        $this->deviceid = $deviceid = $request['Deviceid'];
        $this->client = $request['Client'];
        $this->sign = $request['Token'];
        if(!in_array(strtolower(ACTION_NAME),$this->noCheck)){
            if (!isset($deviceid) || empty($deviceid))
                $this->client_return(213, '请求头参数deviceid不能为空！');
            if(!isset($this->client) || empty($this->client))
                $this->client_return (214, '请求头参数client不能为空！');
            //$this->check_token();
        }
    }

    public function get_token() {
        $deviceid = $this->deviceid;
        switch ($this->_method) {
            case 'get':
                break;
            case 'post':
                $tmpArr = array($this->deviceid,$this->client,'_sign',$time = NOW_TIME,$random = randomIntkeys(6,4));
                sort($tmpArr);
                $tmpStr = implode(',', $tmpArr);
                $token = sha1($tmpStr);
                //创建redis对象
                $tokenCache = S(array('type' => 'redis', 'expire' => 7200));
                $key = 'token';
                $tokenCache->$key[$deviceid] = array(
                    'time'  =>  $time,
                    'random'    =>  $random,
                );
                $this->client_return(200, 'token获取成功', array('token'=>$token));
                break;
        }
    }

    public function setting_config() {
        $setting = array(
            'qq' => '346093707',
            'nickname' => 'Glory',
            'name' => 'test',
        );
        dump(save_setting_config($setting));
    }

    private function check_token(){
        $deviceid = $this->deviceid;
        if(ACTION_NAME !== 'get_token'){
            //创建redis对象
            $tokenCache = S(array('type' => 'redis', 'expire' => 7200,));
            $key = 'token';
            $_token = $tokenCache->$key[$deviceid];
            if($_token === FALSE)
                $this->client_return(211, 'token失效');
            $tmpArr = array($this->deviceid, $this->client,'_sign',$_token['time'],$_token['random']);
            sort($tmpArr);
            $tmpStr = implode(',', $tmpArr);
            $token = sha1($tmpStr);
            if($this->sign !== $token)
                $this->client_return(212, '签名验证失败，非法访问！');
        }
    }

    /*
     * 短信验证码发送函数
     * $mobile 手机号
     * $length 短信发送内容
     * $mins_time 验证码有效期,单位分钟
     * $check_regester 是否验证手机号注册
     */

    protected function send_verify_code($mobile, $length = 6, $mins_time = 5, $check_register = 0) {
        //手机号验证
        if (\Think\Model::regex($mobile, '/^1[34578]{1}\d{9}$/') !== TRUE)//手机号验证
            return -1; //手机号码格式不正确

            /*
             * 手机号是否注册验证
             */
        if ($check_register == 1) {
            if($this->client == 1){
                $Model_member = M('user_member');
            }elseif($this->client == 2){
                $Model_member = M('guide_member');
            }
            if(!$count = $Model_member->where(array('mobile' => $mobile))->count())
                return -2; //手机号未注册
        }elseif ($check_register == 2){
            if($this->client == 1){
                $Model_member = M('user_member');
            }elseif($this->client == 2){
                $Model_member = M('guide_member');
            }
            if($count = $Model_member->where(array('mobile' => $mobile))->count())
                return -4; //手机号已经注册
        }
        $ramnumber = randomIntkeys($length);
        $content = '亲！您的短信验证码是：' . $ramnumber . ',有效期为' . $mins_time . '分钟，请勿泄露；了解更多关注“途途导由”微信公众号。';
        if (send_phone_code($mobile, $content) === TRUE) {
            if (save_verify_code($mobile, $ramnumber, $mins_time * 60) === TRUE)
                return $ramnumber;
        }
        return -3;
    }

    //短信验证码发送接口报错msg
    protected function send_verify_code_msg($msg_code) {
        switch ($msg_code) {
            case -1: $error = '请输入正确的手机号!';
                break;
            case -2: $error = '手机号未注册!';
                break;
            case -3: $error = '验证码发送失败!';
                break;
            case -4: $error = '手机号已注册，请直接登录!';
                break;
            default : $error = '验证码发送成功';
        }
        return $error;
    }

    /*
     * APP访问回调返回对应数据
     * $code int 错误代码
     * $msg string 错误信息
     * $data 返回数据
     */

    protected function client_return($code, $msg, $data = null) {
        $response = array(
            'code' => $code,
            'msg' => $msg,
            'data' => empty($data) ? array() : $data,
        );
        $this->response($response, $this->_type != null ? $this->_type : 'json');
    }

    /*
     * jpush初始化
     * $type 为客户端类型    0-用户端设备推送      1-导游端设备推送
     */

    protected function init_push($type = 0) {
        ini_set("display_errors", "On");
        error_reporting(E_ALL | E_STRICT);
        Vendor('Jpush.JPush');

        /*
         * 测试版jpush账户密匙
         */
        /*$app_key = $type == 1 ? '94e066aff2f20957da1dc375' : '2be990a1f3b0164aaa2021e3';
        $master_secret = $type == 1 ? '5056178fb02afe698e363b5b' : 'ed05f37efc48c83f44e1b801';*/
        /*
         * 正式版jpush账户密匙
         */
        $app_key = $type == 1 ? 'db78c40a0b47e1f2f5dbea9d' : 'e1ff03cc411815c72bcca3f7';
        $master_secret = $type == 1 ? 'bb0e9df506015943d93a7d51' : 'caf08a7b70fcc3adb221ef49';
        // 初始化
        return new \JPush($app_key, $master_secret);
    }

    /*
     * 用户未完成信息
     */

    protected function check_user_unfinished($uid,$user_type = 1) {
        //初始化数组
        $respones = array(
            'data_type' => '',
            'unpay' => '',
            'going' => '',
            'uncomment' => '',
        );
        $Model_order = M('order as o');

        if ($user_type == 1) {
            //获取用户是否有未支付订单
            $map_unpay = array(
                'o.guide_confirm_order_time' => array('exp', 'IS NOT NULL'),
                'oi.server_type' => 0,
                'o.server_status' => array('in',array(2,6)),
                'o.pay_status' => 0,
                'o.uuid' => $uid,
            );
            $unpay = $Model_order
                    ->join('LEFT JOIN __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                    ->where($map_unpay)
                    ->field('o.order_number,oi.guide_type,oi.server_type')
                    ->lock(true)
                    ->find();
            if ($unpay) {
                $respones['unpay'] = $unpay;
                $respones['data_type'] = 'unpay';
            }

            //获取用户未评论订单
            $map_comment = array(
                'o.guide_confirm_order_time' => array('exp', 'IS NOT NULL'),
                'o.server_status' => 6,
                'o.pay_status' => 1,
                'o.uuid' => $uid,
            );
            $uncomment = $Model_order
                    ->join('LEFT JOIN __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                    ->where($map_comment)
                    ->field('o.order_number,oi.guide_type,oi.server_type')
                    ->find();
            if ($uncomment) {
                $respones['uncomment'] = $uncomment;
                $respones['data_type'] = 'uncomment';
            }
        }

        //获取用户是否有进行中的订单
        $map_going = array(
            'o.guide_confirm_order_time' => array('exp', 'IS NOT NULL'),
            'o.server_status' => array('in', array(1,3,4,7)),
            'o.pay_status' => 0,
            'oi.server_type' => 0,
        );
        $user_type != 1 ? $map_going['o.guid'] = $uid : $map_going['o.uuid'] = $uid;
        $going = $Model_order
                ->join('LEFT JOIN __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                ->where($map_going)
                ->field('o.order_number,o.server_status,o.pay_status,oi.guide_type,oi.server_type')
                ->lock(true)
                ->find();
        if ($going) {
            $going['order_status']  =  R('Order/order_status',array($going['server_status'],$going['pay_status']));
            //$going['order_status']  =  order_status($going['server_status'],$going['pay_status']);
            unset($going['server_status'],$going['pay_status']);
            $respones['going'] = $going;
            $respones['data_type'] = 'going';
        }

        return $respones;
    }


    /*
     * 获取身份
     * $type 导游类型：1-导游；2-伴游；3-土著
     */

    protected function get_guide_type_string($type = 1) {
        switch ($type) {
            case 1:
                $guide_type_string = '讲解员';
                break;
            case 2:
                $guide_type_string = '领路人';
                break;
        }
        return $guide_type_string;
    }


    /*
     * 获取导游在线时长
     */
    protected function server_time_long($uid){
        $Model_guide_online_log = M('guide_online_log');
        //获取当日开始时间戳
        $start_time = strtotime(date('Y-m-d'));
        //获取当日结束时间戳
        $end_time = strtotime(date('Y-m-d',strtotime('+1 day'))) -1;
        $map = array(
            'guid'  =>  $uid,
            'online_time'   =>  array(array('egt',$start_time),array('elt',$end_time)),
            'offline_time'  =>  array(array('egt',$start_time),array('elt',$end_time)),
        );
        $server_time_long = $Model_guide_online_log->where($map)->sum('server_time_long');
        return $server_time_long ? $server_time_long : 0;
    }

    /*
     * 上传头像
     */
    protected function upload_head_image(){
        if (isset($_FILES['head_image']) && is_array($_FILES['head_image'])) {
            $config = array(
                'maxSize' => 3 * 1024 * 1024, //上传限制3M
                'rootPath' => './Uploads/',
                'savePath' => '',
                'saveName' => array('uniqid', ''),
                'exts' => array('jpg', 'gif', 'png', 'jpeg'),
                'autoSub' => true,
                'subName' => 'User/head_image',
            );
            $upload = new \Think\Upload($config); // 实例化上传类
            $info = $upload->uploadOne($_FILES['head_image']);
            if ($info){
                //生成缩略图
                $path = '.'.substr($config['rootPath'], 1) . $info['savepath'] . $info['savename']; //在模板里的url路径
                $image = new \Think\Image();
                $image->open($path);
                $file_path = explode('/',trim($path,'.'));
                $file_path[count($file_path)-1] = '120_'.$file_path[count($file_path)-1];
                // 按照原图的比例生成一个最大为120*120的缩略图
                if(is_object($image->thumb(120, 120)->save('.'.implode('/',$file_path))))
                    return implode('/',$file_path);
            }
            return false;
        }
    }
}
