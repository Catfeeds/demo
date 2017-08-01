<?php

/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/3/14
 * Time: 11:50
 */
/*
 * 检测服务器是否支持getallheaders获取请求头，不支持就自定义
 */

if (!function_exists('getallheaders')) {

    function getallheaders() {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

}

function get_deviceid() {
    $request = getallheaders();
    return $request['Deviceid'];
}

/**
 * 发起HTTPS请求
 */
function send_phone_code($phone, $content,$sendTime = '') {
    $url = 'http://mb345.com:999/ws/BatchSend.aspx';
    if (is_array($phone))
        $phone = implode(',', $phone);
    if (empty($phone))
        return FALSE;
    $data = array(
        'CorpID' => 'LKSDK0003418',
        'Pwd' => 'XBX1q2w3e4r5t!@#dx',
        'Mobile' => $phone,
        'Content' => charset_encode($content,'gb2312','utf-8'),
        'Cell' => '',
        'SendTime' => $sendTime,
    );
     switch(curl($url,'post',$data)){
        case -1:
            $error = '账号未注册,请联系技术人员！';
            \Think\Log::write($error,'Err');
            break;
        case -2:
            $error = '其他错误,请联系技术人员！';
            \Think\Log::write($error,'Err');
            break;
        case -3:
            $error = '帐号或密码错误,请联系技术人员！';
            \Think\Log::write($error,'Err');
            break;
        case -5:
            $error = '余额不足，请充值！';
            \Think\Log::write($error,'Err');
            break;
        case -6:
            $error = '定时发送时间不是有效的时间格式,请联系技术人员！';
            \Think\Log::write($error,'Err');
            break;
        case -7:
            $error = '提交信息末尾未签名，请添加中文的企业签名【 】！';
            \Think\Log::write($error,'Err');
            break;
        case -8:
            $error = '发送内容需在1到300字之间';
            \Think\Log::write($error,'Err');
            break;
        case -9:
            $error = '发送号码为空';
            \Think\Log::write($error,'Err');
            break;
        case -10:
            $error = '定时时间不能小于系统当前时间,请联系技术人员！';
            \Think\Log::write($error,'Err');
            break;
        case -100:
            $error = '限制IP访问,请联系技术人员！';
            \Think\Log::write($error,'Err');
            break;
         default:
             return true;
     }
}

/**
 * 调用系统的API接口方法（静态方法）
 * api('User/getName','id=5'); 调用公共模块的User接口的getName方法
 * api('Manage/User/getName','id=5');  调用Admin模块的User接口
 * @param  string  $name 格式 [模块名]/接口名/方法名
 * @param  array|string  $vars 参数
 */
function api($name, $vars = array()) {
    $array = explode('/', $name);
    $method = array_pop($array);
    $classname = array_pop($array);
    $module = $array ? array_pop($array) : 'Common';
    $callback = $module . '\\Api\\' . $classname . 'Api::' . $method;
    if (is_string($vars)) {
        parse_str($vars, $vars);
    }
    return call_user_func_array($callback, $vars);
}

/**
 * 在数字0~9之间获取指定长度的随机数
 * @param length 随机数的长度
 */
function randomIntkeys($len = 6, $type) {
    switch ($type) {
        case 1 :
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
        case 2 :
            $chars = 'abcdefghijklmnopqrstuvwxyz';
            break;
        case 3 :
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            break;
        case 4 :
            $chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            break;
        case 5 :
            $chars = 'ABCDEFGHIJKMNPQRSTUVWXYZ23456789';
            break;
        case 6 :
            $chars = 'abcdefghijklmnopqrstuvwxyz23456789';
            break;
        default :
            $chars = str_repeat('0123456789', $len);
            break;
    }
    $tmpStr = str_shuffle($chars);
    $rand_start = rand($len + bcmod($len, 2) + 1, strlen($tmpStr)) - 1 - $len;
    $str = substr($tmpStr, $rand_start, $len);
    return $str;
}

/*
 * 动态保存config配置到setting.config.php文件中
 * $data 数组 
 */

function save_setting_config($data) {
    $file_paht = COMMON_PATH . C('SETTING_CONFIG_PATH');
    if (!file_exists($file_paht)) {
        $file = fopen($file_paht, 'r+');
        fclose($file);
    }
    $tmpArr = array_merge(C('setting'), $data);
    $tmpStr = "<?php return array(\n'setting' =>" . var_export($tmpArr, true) . ",\n);";
    if (file_put_contents($file_paht, $tmpStr))
        return TRUE;
    return FALSE;
}

/**
 * 根据用户ID获取用户名
 * @param  integer $uid 用户ID
 * @return string       用户名
 */
function get_username($uid = 0) {
    static $list;
    if (!($uid && is_numeric($uid))) { //获取当前登录用户名
        return session('user_auth.username');
    }

    /* 获取缓存数据 */
    if (empty($list)) {
        $list = S('sys_active_user_list');
    }

    /* 查找用户信息 */
    $key = "u{$uid}";
    if (isset($list[$key])) { //已缓存，直接使用
        $name = $list[$key];
    } else { //调用接口获取用户信息
        $User = new User\Api\UserApi();
        $info = $User->info($uid);
        if ($info && isset($info[1])) {
            $name = $list[$key] = $info[1];
            /* 缓存用户 */
            $count = count($list);
            $max = C('USER_MAX_CACHE');
            while ($count-- > $max) {
                array_shift($list);
            }
            S('sys_active_user_list', $list);
        } else {
            $name = '';
        }
    }
    return $name;
}

/**
 * 数据签名认证
 * @param  array  $data 被认证的数据
 * @return string       签名
 */
function data_auth_sign($data) {
    //数据类型检测
    if (!is_array($data)) {
        $data = (array) $data;
    }
    ksort($data); //排序
    $code = http_build_query($data); //url编码并生成query字符串
    $sign = sha1($code); //生成签名
    return $sign;
}


function create_user_log($action_type,$uid,$user_type){
    $Model = M('user_log');
    $Model_member = $user_type == 1 ? M('user_member') : M('guide_member');
    $mobile = $Model_member->getFieldById($uid,'mobile');
    $data = array(
        'user_id'   =>  $uid,
        'user_type' =>  $user_type,
        'action_ip' =>  get_client_ip(1),
        'action_type'   =>  $action_type,
        'create_time'   =>  NOW_TIME,
        'remark'    =>  $mobile.'在'.date('Y-m-d H:i:s',NOW_TIME).'进行了'.C('USER_ACTION_TYPE.'.$action_type).'操作！',
        'status'    =>  1,
    );
    $Model->add($data);
}

/*
 * 手机/邮箱验证码验证
 * $client_code 客户端号码信息（手机号/邮箱）
 * $verify_code 验证码
 */

function check_verify_code($clint_code, $verify_code) {
    if (empty($clint_code) || empty($verify_code))
        return FALSE;
    $Model_verify = M('verify_code');
    $mobile_regex = '/^1[34578]{1}\d{9}$/'; //手机正则匹配
    $email_regex = '\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*'; //邮箱正则匹配
    if (preg_match($mobile_regex, $clint_code)) {
        $client_type = 0;
    } elseif (preg_match($email_regex, $clint_code)) {
        $client_type = 1;
    } else {
        return FALSE;
    }
    $map = array(
        'client_id' => $clint_code,
        'verify_code' => $verify_code,
        'client_type' => $client_type,
        'send_time' => array('egt', NOW_TIME),
    );
    if ($Model_verify->where($map)->find())
        return TRUE;
    return FALSE;
}

/*
 * 验证码保存到数据库
 * $client_code 客户端号码信息（手机号/邮箱）
 * $verify_code 验证码
 * $expires_time 验证码有效期，单位秒
 */

function save_verify_code($client_code, $verify_code, $expires_time = 1800) {
    $Model_verify = M('verify_code');
    $mobile_regex = '/^1[34578]{1}\d{9}$/'; //手机正则匹配
    $email_regex = '\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*'; //邮箱正则匹配
    if (preg_match($mobile_regex, $client_code)) {
        $client_type = 0;
    } elseif (preg_match($email_regex, $client_code)) {
        $client_type = 1;
    } else {
        return FALSE;
    }
    $map = array(
        'client_id' => $client_code,
        'verify_code' => $verify_code,
        'send_time' => NOW_TIME + $expires_time,
        'client_type' => $client_type,
    );
    $Model_verify->where(array('client_id' => $client_code))->delete(); //删除就验证码信息
    if ($Model_verify->add($map))
        return TRUE;
}

/*
 * 获取setting.config.php配置文件中对应key的值
 * $arr为C函数获取的数组
 * $key为对应的键
 */

function get_config_key_value($arr, $key) {
    if (is_array($arr['value'])) {
        foreach ($arr['value'] as $k => $v) {
            if (strtoupper($k) === strtoupper($key)) {
                return $v;
            }
        }
    }
    return $arr['value'];
}

/**
 * @desc 根据两点间的经纬度计算距离 
 * @param float $lat 纬度值 
 * @param float $lon 经度值 
 */
function getDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6367000; //approximate radius of earth in meters 

    /*
      Convert these degrees to radians
      to work with the formula
     */

    $lat1 = ($lat1 * pi() ) / 180;
    $lng1 = ($lng1 * pi() ) / 180;

    $lat2 = ($lat2 * pi() ) / 180;
    $lng2 = ($lng2 * pi() ) / 180;

    /*
      Using the
      Haversine formula

      http://en.wikipedia.org/wiki/Haversine_formula

      calculate the distance
     */

    $calcLongitude = $lng2 - $lng1;
    $calcLatitude = $lat2 - $lat1;
    $stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
    $stepTwo = 2 * asin(min(1, sqrt($stepOne)));
    $calculatedDistance = $earthRadius * $stepTwo;

    return round($calculatedDistance);
}

/*
 * 根据特定经纬度和一定范围获取经纬度范围
 * @param $lat -- 纬度
 * @param $lng -- 经度
 * @param $distince -- 距离范围 单位km
 */
define('EARTH_RADIUS', 6378.137); //地球半径

function SqurePoint($lat, $lng, $distince = 2) {
    $dlng = 2 * asin(sin($distince / (2 * EARTH_RADIUS)) / cos(deg2rad($lat)));
    $dlng = rad2deg($dlng);
    $dlat = ($distince / EARTH_RADIUS);
    $dlat = rad2deg($dlat);
    return array(
        'maxlat' => $lat + $dlat,
        'minlat' => $lat - $dlat,
        'maxlon' => $lng + $dlng,
        'minlon' => $lng - $dlng,
    );
}

/*
 * 如果php版本过低，不支持array_column方法就重构该方法
 */
if (!function_exists('array_column')) {

    function array_column($input, $columnKey, $indexKey) {
        $columnKeyIsNumber = (is_numeric($columnKey)) ? true : false;
        $indexKeyIsNull = (is_null($indexKey)) ? true : false;
        $indexKeyIsNumber = (is_numeric($indexKey)) ? true : false;
        $result = array();
        foreach ((array) $input as $key => $row) {
            if ($columnKeyIsNumber) {
                $tmp = array_slice($row, $columnKey, 1);
                $tmp = (is_array($tmp) && !empty($tmp)) ? current($tmp) : null;
            } else {
                $tmp = isset($row[$columnKey]) ? $row[$columnKey] : null;
            }
            if (!$indexKeyIsNull) {
                if ($indexKeyIsNumber) {
                    $key = array_slice($row, $indexKey, 1);
                    $key = (is_array($key) && !empty($key)) ? current($key) : null;
                    $key = is_null($key) ? 0 : $key;
                } else {
                    $key = isset($row[$indexKey]) ? $row[$indexKey] : 0;
                }
            }
            $result[$key] = $tmp;
        }
        return $result;
    }

}

/*
 * curl模拟post请求
 */

function curl_post($url, $data = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $return = curl_exec($ch);
    curl_close($ch);
    return $return;
}

/*
 * 指定经纬度获取城市信息
 */

function get_log_lat_addr_info($lat, $lng) {
    $url_arr = array(
        'output' => 'json',
        'ak' => C('BAIDU_API_AK'),
        'location' => implode(',', array($lat, $lng)),
        'pois' => 0,
    );
    $url = 'http://api.map.baidu.com/geocoder/v2/?' . http_build_query($url_arr);
    $addrInfo = json_decode(curl_post($url), TRUE);
    if ($addrInfo['status'] === 0)
        return $addrInfo['result'];
}

/**
 * 获取当前页面完整URL地址
 */
function get_url() {
    $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
    $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
    $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
    return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
}

/**
 * 获取图片根路径
 */
function get_pic_url() {
    $url = get_url();
    $tmpStr = substr($url, 0, strpos($url, MODULE_NAME));
    $tmpArr = explode('/', $tmpStr);
    unset($tmpArr[count($tmpArr) - 1]);
    if (array_search('index.php', $tmpArr))
        unset($tmpArr[array_search('index.php', $tmpArr)]);
    return implode('/', $tmpArr);
}

/**
 * 数组分页函数 核心函数 array_slice
 * 用此函数之前要先将数据库里面的所有数据按一定的顺序查询出来存入数组中
 * $count  每页多少条数据
 * $page  当前第几页
 * $array  查询出来的所有数组
 * order 0 - 不变   1- 反序
 */
function array_page($list, $now_page, $count = 10, $sort = null,$group=null) {
    global $countpage; #定全局变量
    $page = (empty($now_page)) ? 1 : $now_page; #判断当前页面是否为空 如果为空就表示为第一页面 
    $start = ($page - 1) * $count; #计算每次分页的开始位置
    //排序
    if(!empty($sort)){
        $GLOBALS['sort'] =& $sort;#申明超全局变量
        unset($sort);
        uasort($list,function ($a,$b){
            global $sort;
            foreach($sort as $key => $val){
                if($a[$key] == $b[$key]){
                    return 0;
                }
                return (($val == 'desc')?-1:1) * (($a[$key] < $b[$key]) ? -1 : 1);
            }
        });
    }
    $totals = count($list);
    //分组
    if(!empty($group)){
        $tem = array();
        foreach ($list as $val) {
            $tem[$val[$group]][] = $val;
        }
        unset($list);
        $list = $tem;
    }
    $countpage = ceil($totals / $count); #计算总页面数
    $page_data = array();
    $page_data = array_slice($list, $start, $count,$group ? true:false);
    unset($GLOBALS['sort']);
    //die;
    return $page_data; #返回查询数据
}

/*
 * 生成订单号
 */

function create_order_no() {
    $arr = str_split(substr(uniqid(), 7, 13), 1);
    $tmpArr = array_map('ord', $arr); //返回$arr数组的ASCII值
    $str = implode(NULL, $tmpArr);
    return date('Ymd') . substr($str, 0, 8);
}

/*
 * $url curl地址
 * $curl_type   curl请求类型 post/get/delete/put
 * $data    curl请求参数    可空；默认array()
 * $headers
 */

function curl($url, $curl_type, $data = array(), $headers = array()) {
    $_headers = array();
    if(!empty($headers)){
        foreach ($headers as $key=>$val){
            $_headers[] = $key.':'.$val;
        }
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);//让cURL自己判断使用哪个版本
    curl_setopt($curl, CURLOPT_MUTE, 1);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 120);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $_headers );
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

    switch (strtoupper($curl_type)) {
        case 'GET':
            if (!empty($data))
                $url .= '?'.http_build_query($data);
            break;
        case 'POST':
            curl_setopt($curl, CURLOPT_POST, TRUE);
            if (!empty($data))
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case 'DELETE':
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if (!empty($data))
                $url .= '?'.http_build_query($data);
            break;
        case 'PUT':
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (!empty($data))
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        default:
            curl_close($curl);
            die('HTTP request mode does not exist!');
    }
    curl_setopt($curl, CURLOPT_URL, $url );
    $response = curl_exec($curl);
    $err = curl_errno( $curl );
    $info  = curl_getinfo( $curl );
    curl_close($curl);
    //字符串是否为xml,是就解析解析成数组，否则返回原数据
    if($err)    return $err;
    return xml_parser($response) !== FALSE ? xml_parser($response) : $response;
}

/**
 * 解析XML格式的字符串
 *
 * @param string $str
 * @return 解析正确就返回解析结果,否则返回false,说明字符串不是XML格式
 */
function xml_parser($str) {
    $xml_parser = xml_parser_create();
    if (!xml_parse($xml_parser, $str, true)) {
        xml_parser_free($xml_parser);
        return false;
    } else {
        return (json_decode(json_encode(simplexml_load_string($str)), true));
    }
}

/**
 * 实现多种字符编码方式
 * @param $input 需要编码的字符串
 * @param $_output_charset 输出的编码格式
 * @param $_input_charset 输入的编码格式
 * return 编码后的字符串
 */
function charset_encode($input, $_output_charset, $_input_charset) {
    $output = "";
    if (!isset($_output_charset))
        $_output_charset = $_input_charset;
    if ($_input_charset == $_output_charset || $input == null) {
        $output = $input;
    } elseif (function_exists("mb_convert_encoding")) {
        $output = mb_convert_encoding($input, $_output_charset, $_input_charset);
    } elseif (function_exists("iconv")) {
        $output = iconv($_input_charset, $_output_charset, $input);
    } else
        die("sorry, you have no libs support for charset change.");
    return $output;
}

/*
 * 二维数组排序根据某个值排序
 */
function list_sort_by($list, $field, $sortby = 'asc'){
    if (is_array($list)) {
        $refer = $resultSet = array();
        foreach ($list as $i => $data) {
            $refer[$i] = &$data[$field];
        }
        switch ($sortby) {
            case 'asc': // 正向排序
                asort($refer);
                break;
            case 'desc': // 逆向排序
                arsort($refer);
                break;
            case 'nat': // 自然排序
                natcasesort($refer);
                break;
        }
        foreach ($refer as $key => $val) {
            $resultSet[] = &$list[$key];
        }
        return $resultSet;
    }
    return false;
}

/*
 * AES加解密
 * @param $data 需要被处理的原始数据
 * @param $option 加解密选项，1：加密；2-解密
 * @param $key 加解密密匙
 */
function Aes_encrypt_decrypt($data,$option,$key){
    vendor('AES.Aes');
    $AES = new \MCrypt();
    switch ($option){
        case 1:
            return $AES->encrypt($data,$key);
            break;
        case 2:
            return $AES->decrypt($data,$key);
            break;
        default:
            return false;
            break;
    }
}

/*
 * 订单状态
 */
function order_status($server_status, $pay_status) {
    //订单状态处理
    if ($server_status == 0 && $pay_status == 0)//待处理订单
        $order_status = 0;
    if ($server_status == 1 && $pay_status == 0)//已接单，未开始
        $order_status = 1;
    if ($server_status == 3 && $pay_status == 0)//服务已开始
        $order_status = 2;
    if ($server_status == 4 && $pay_status == 0)//服务已结束，待提交工单
        $order_status = 3;
    if ($server_status == 6 && $pay_status == 0)//工单已提交，待支付
        $order_status = 4;
    if ($server_status == 6 && $pay_status == 1)//已支付，未评论
        $order_status = 5;
    if ($server_status == 6 && $pay_status == 2)//订单已结束
        $order_status = 6;
    if ($server_status == 2 && $pay_status == 0)//已取消，未支付
        $order_status = 7;
    if ($server_status == 2 && $pay_status == 2)//已关闭（已取消并支付违约金）
        $order_status = 8;
    if ($server_status == 7 && $pay_status == 0)//导游已到达用户附近，未开始服务之前
        $order_status = 9;
    return $order_status;
}

?>
