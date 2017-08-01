<?php

namespace Api_v3\Controller;

/**
 * @author vaio
 * @datetime 2016-4-5  11:52:15
 * @encoding UTF-8
 * @filename IndexController.class.php
 */
class IndexController extends ApiController
{
    /*
     * 发送验证码接口
     * 
     */

    public function send_verifyCode(){
        switch ($this->_method) {
            case 'get':
                $mobile = I('get.mobile');
                $check_register = I('get.check_register',0);
                $re = $this->send_verify_code($mobile, $length = 6, $mins_time = 30, $check_register);
                if ($re < 0)
                    $this->client_return(0, parent::send_verify_code_msg($re));
                $this->client_return(1, parent::send_verify_code_msg($re), array('vierfy_code' => $re)); //发送成功并返回发送的验证码
                break;
        }
    }

    /*
     * 景区列表
     */

    public function tourist_list(){
        switch ($this->_method) {
            case 'get':
                $Model = M('tourist_area');
                $map = array(
                    'status' => 1,
                );
                if (false !== $list = $Model->where($map)->field('tourist_code,tourist_name,lng_lat')->select()){
                    foreach($list as &$val){
                        $val['lng_lat'] = implode(',',unserialize($val['lng_lat']));
                    }
                    $this->client_return(1, '获取成功！', $list);
                }
                break;
        }
    }

    /**
     * 导游端上下线
     * @param uid 用户id
     * @param is_online 用户状态:0-下线，1在线空闲中
     */
    public function online(){
        switch ($this->_method) {
            case 'post':
                $uid = I('post.uid', 0);
                $lng = I('post.lng', 0);
                $lat = I('post.lat', 0);
                if (empty($uid) || !is_numeric($uid))
                    $this->client_return(0, '用户id不能为空！');
                if (empty($lng) || !is_numeric($lng))
                    $this->client_return(0, '经度不能为空！');
                if (empty($lat) || !is_numeric($lat))
                    $this->client_return(0, '纬度不能为空！');
                $Model = new \Think\Model();
                $Model_guide = M('guide_member_info');
                $data = array(
                    'lon' => $lng,
                    'lat' => $lat,
                );
                $online_status = $Model_guide->getFieldByUid($uid, 'is_online');
                if($online_status == 1){
                    $data['is_online'] = 0;
                    $Model_guide_online_log = M('guide_online_log');
                    $update = array(
                        'offline_time' => NOW_TIME,
                        'server_time_long' => array('exp', NOW_TIME . '-online_time'),
                    );
                    $map = array(
                        'guid'  =>  $uid,
                        'online_time'   =>  array('egt',strtotime(date('Y-m-d'))),
                        'offline_time'  =>  array('eq',0),
                        'server_time_long'  =>  array('eq',0),
                    );
                    if (false !== $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=innodb') && FALSE !== $Model->execute('alter table __GUIDE_ONLINE_LOG__ ENGINE=innodb')) {
                        $Model_guide->startTrans();
                        if (!$Model_guide->where(array('uid'=>$uid))->save($data) || !$Model_guide_online_log->where($map)->order('id desc')->limit(1)->save($update)) {
                            $Model->rollback();
                            //mysql数据库引擎修改
                            $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=myisam');
                            $Model->execute('alter table __GUIDE_ONLINE_LOG__ ENGINE=myisam');
                            $this->client_return(0, '操作失败！');
                        }
                        $Model->commit();
                        //mysql数据库引擎修改
                        $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=myisam');
                        $Model->execute('alter table __GUIDE_ONLINE_LOG__ ENGINE=myisam');
                        $this->client_return(1, '操作成功！', array('online_status' => $online_status == 1 ? 0 : 1));
                    }
                } elseif ($online_status == 0){
                    $data['is_online'] = 1;
                    $Model_guide_online_log = M('guide_online_log');
                    if (false !== $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=innodb') && FALSE !== $Model->execute('alter table __GUIDE_ONLINE_LOG__ ENGINE=innodb')) {
                        $Model_guide->startTrans();
                        if (!$Model_guide->where(array('uid'=>$uid))->save($data) || !$Model_guide_online_log->add(array('guid' => $uid, 'online_time' => NOW_TIME))) {
                            $Model->rollback();
                            //mysql数据库引擎修改
                            $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=myisam');
                            $Model->execute('alter table __GUIDE_ONLINE_LOG__ ENGINE=myisam');
                            $this->client_return(0, '操作失败！');
                        }
                        $Model->commit();
                        //mysql数据库引擎修改
                        $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=myisam');
                        $Model->execute('alter table __GUIDE_ONLINE_LOG__ ENGINE=myisam');
                        //获取导游累计在线时长
                        $server_time_long = parent::server_time_long($uid);
                        $this->client_return(1, '操作成功！', array('online_status' => $online_status == 1 ? 0 : 1,'server_time_long' => $server_time_long));
                    }
                }
                $this->client_return(0, '你正在服务中……');
                break;
        }
    }

    /*
     * 获取周边xx公里范围内的景区讲解员/领路人
     */

    public function guide_list(){
        switch ($this->_method) {
            case "get":
                $server_addr_lnglat = I('get.server_addr_lnglat');
                $guide_type = I('get.guide_type');
                if (empty($guide_type))
                    $this->client_return(0, '导游类型不正确！');
                $server_addr_lnglat = explode(',', $server_addr_lnglat);
                if (empty($server_addr_lnglat) || !is_array($server_addr_lnglat))
                    $this->client_return(0, '目的地不能为空！');

                //获取指定经纬度5公里范围内的经纬度范围
                $tmpArr = SqurePoint($server_addr_lnglat[0], $server_addr_lnglat[1], get_config_key_value(C('setting.MAX_GUIDE_FIND_KM')));
                $map = array(
                    'gi.lon' => array('between', array($tmpArr['minlon'], $tmpArr['maxlon'])),
                    'gi.lat' => array('between', array($tmpArr['minlat'], $tmpArr['maxlat'])),
                    'gi.status' => 1,
                    'gi.guide_type' => $guide_type,
                    'gi.is_auth' => 1,
                    'gi.is_online' => 1,
                    'gi.push_id' => array('exp', 'is not null'),
                );
                $field = 'gi.uid,gi.lon,gi.lat,gi.head_image,gi.realname,gi.server_times,gi.stars';
                if ($guide_type == 1) {
                    $map['ta.status'] = 1;
                    $join = 'left join __TOURIST_AREA__ as ta on ta.id = gi.tourist_id';
                    $field = 'gi.uid,gi.lon,gi.lat,gi.head_image,gi.realname,gi.server_times,gi.stars,ta.id as tourist_id,ta.tourist_code,ta.tourist_name,ta.hours_price,ta.times_price,ta.lng_lat';
                }

                $Model_guide_member_info = M('guide_member_info as gi');

                $guide_list = $Model_guide_member_info
                    ->join($join)
                    ->where($map)
                    ->field($field)
                    ->select();

                foreach ($guide_list as &$val) {
                    $val['head_image'] = get_pic_url() . $val['head_image'];
                    if ($guide_type == 1) {
                        $new_arr[$val['tourist_id']]['tourist_id'] = $val['tourist_id'];
                        $new_arr[$val['tourist_id']]['tourist_name'] = $val['tourist_name'];
                        $new_arr[$val['tourist_id']]['tourist_code'] = $val['tourist_code'];
                        $new_arr[$val['tourist_id']]['hours_price'] = $val['hours_price'];
                        $new_arr[$val['tourist_id']]['times_price'] = $val['times_price'];
                        $new_arr[$val['tourist_id']]['lng_lat'] = implode(',',unserialize($val['lng_lat']));
                        $tourist_id = $val['tourist_id'];
                        unset($val['tourist_id'], $val['tourist_name'], $val['tourist_code'], $val['hours_price'], $val['times_price'],$val['lng_lat']);
                        $new_arr[$tourist_id]['_guide_info'][] = $val;
                    }
                    if ($guide_type == 2)
                        $val['server_price'] = get_config_key_value(C('setting.LLR_INSTANT_PRICE'));
                }
                $guide_group_list = $guide_type == 1 ? $new_arr : $guide_list;
                if (!$guide_group_list)
                    $this->client_return(0, '没有为你匹配到' . $this->get_guide_type_string($guide_type) . '！');
                $this->client_return(1, '周围' . $this->get_guide_type_string($guide_type) . '获取成功!', array_merge($guide_group_list));
                break;
        }
    }


    /*
     * 导游列表筛选获取
     */
    public function guidews_list(){
        switch ($this->_method){
            case 'get':
                $bgndate = I('bgndate');
                $days = I('days');
                $sex = I('sex',0);
                $star = I('star');
                $lag = I('language') == 1 ? '普通话' : '';//语言
                $fee = I('fee','');
                $currentpage = I('currentpage',1);
                $age = I('age','asc');
                $price = I('price','desc');
                $stars = I('stars','desc');
                if(empty($bgndate))
                    $this->client_return(0,'预约出游时间不能为空！');
                if(empty($days))
                    $this->client_return(0,'预约出游天数不能为空！');
                $enddate = date("Y-m-d",strtotime($days-1 ." day",strtotime($bgndate)));
                switch ($sex){
                    case 1: $sex = '男'; break;
                    case 2: $sex = '女'; break;
                    default:    $sex = '';
                }
                $info = array(
                    'bgndate'   =>  $bgndate,
                    'enddate'   =>  $enddate,
                    'sex'   =>  $sex,
                    'language'  =>  $lag,
                    'star'   =>  $star,
                    'fee'   =>  $fee,
                    'city'  =>  '成都',
                    'currentpage'   =>  $currentpage,
                );
                $path = 'guidews/list';
                $result = $this->getHttpResponse($path,'post',$info);
                $sort = array(
                    'age'   => $age,
                    'stars'   => $stars,
                    'dayprice'   => $price,
                );
                $data = $result['info']['data'];
                $GLOBALS['sort'] =& $sort;#申明超全局变量
                unset($sort);
                uasort($data,function ($a,$b){
                    global $sort;
                    foreach($sort as $key => $val){
                        if($a[$key] == $b[$key]){
                            continue;
                        }
                        return (($val == 'desc')?-1:1) * (($a[$key] < $b[$key]) ? -1 : 1);
                    }
                });
                unset($GLOBALS['sort']);
                $result['info']['data'] = array_merge($data);
                switch ($result['result']){
                    case 'success':
                        $this->client_return(1,'获取成功!',$result['info']);
                        break;
                    case 'failure':
                        $this->client_return(0,'获取导游资源失败!',$result);
                        break;
                    default:
                        $this->client_return(0,'服务器开小差去了;请稍后重试！');
                }
                break;
            default:$this->client_return(0,'HTTP request type error!');
        }
    }


    /**
     * 用户端目的地城市列表 （暂时只有四川城市）
     */
    public function end_addr(){
        if (IS_GET) {
            $result = M('city')->field('id,name,pinyin')->where('pid=510000')->select();
            if ($result) {
                $this->client_return(1, '获取成功', $result);
            } else {
                $this->client_return(0, '获取失败');
            }
        }
    }

    /**
     * 用户端提交评价
     */
    public function upload_comment(){
        switch ($this->_method) {
            case 'get':
                break;
            case 'post':
                $order_number = I('post.order_number');
                $content = I('post.content', '');
                $star = I('post.star');
                $tag = I('post.tag');
                $Model_order = M('order as o');
                $map = array(
                    'o.order_number' => $order_number,
                    'o.server_status' => 6,
                    'o.pay_status' => 1,
                );
                $order_info = $Model_order
                    ->join('left join __GUIDE_MEMBER_INFO__ as gi on gi.uid = o.guid')
                    ->where($map)
                    ->field('gi.tag_times,o.uuid,o.guid,gi.stars')
                    ->find();
                if ($order_info) {
                    $data = array(
                        'uuid' => $order_info['uuid'],
                        'guid' => $order_info['guid'],
                        'order_number' => $order_number,
                        'star' => $star,
                        'content' => $content,
                        'comment_time' => NOW_TIME,
                        'tag' => $tag ? serialize($tag = explode(',', trim($tag,','))) : '',
                    );
                    if(empty($content))
                        unset($data['content']);
                    if($re = M('comment')->add($data)){
                        $tmp_arr = $tag_times = unserialize($order_info['tag_times']);
                        foreach ($tag as $val) {
                            if (!empty($tmp_arr)) {
                                foreach ($tmp_arr as $k => $v) {
                                    if (!array_key_exists($val, $tmp_arr)) {
                                        $tag_times[$val] = 1;
                                    } else if (array_key_exists($val, $tmp_arr) && $val == $k) {
                                        $tag_times[$k] += 1;
                                    }
                                }
                            } else {
                                $tag_times[$val] = 1;
                            }
                        }
                        $stars = M('comment')->where(array('guid' => $order_info['guid']))->avg('star');
                        $guide_member_data = array(
                            'stars' => round($stars, 1),
                        );
                        if(count($tag) > 1 && !empty($tag[0]))
                            $guide_member_data['tag_times'] = serialize($tag_times);
                        if(false !== M('guide_member_info')->where(array('uid' => $order_info['guid']))->save($guide_member_data) && $Model_order->where($map)->setField(array('pay_status' => 2)))
                            $this->client_return(1, '评论成功！');
                        M('comment')->where(array('id'=>$re))->delete();
                        $this->client_return(0, '评论失败！');
                    }
                    $this->client_return(0, '评论失败！');
                }
                $this->client_return(0, '评论失败！');
                break;
        }
    }

    /*
     * 开始服务
     */
    public function start_server()
    {
        switch ($this->_method) {
            case "post":
                $order_number = I('post.order_number');
                if (empty($order_number) || !isset($order_number))
                    $this->client_return(0, '订单号不能为空！');
                $Model = new \Think\Model();
                $Model_order = M('order');
                //检测订单是被已经被取消
                $server_status = $Model_order->getFieldByOrder_number($order_number,'server_status');
                if($server_status == 2)
                    $this->client_return(0, '订单已经被取消！');
                elseif($server_status != 7 && $server_status != 2)
                    $this->client_return(0, '非发操作！');

                if (false !== $Model->execute('alter table __ORDER__ ENGINE=innodb') && FALSE !== $Model->execute('alter table __ORDER_INFO__ ENGINE=innodb')) {
                    $Model_order->startTrans();
                    $Model_order_info = M('order_info');
                    $map['order_number'] = $order_number;

                    $data_order_info = array(
                        'server_start_time' => NOW_TIME,
                        'real_start_server_time' => NOW_TIME,
                    );
                    $data_order = array(
                        'server_status' => 3,
                    );
                    if (!$Model_order_info->where(array_merge($map, array('server_type' => 0)))->save($data_order_info) || !$Model_order->where(array_merge($map, array('server_status' => 7, 'pay_status' => 0)))->save($data_order)) {
                        $Model_order->rollback();
                        //mysql数据库引擎修改
                        $Model->execute('alter table __ORDER__ ENGINE=myisam');
                        $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');
                        $this->client_return(0, '开始服务失败,请确认已接单后重试！');
                    }
                    $Model_order->commit();
                    //mysql数据库引擎修改
                    $Model->execute('alter table __ORDER__ ENGINE=myisam');
                    $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');
                    $this->client_return(1, '开始服务成功', array('server_start_time' => $data_order_info['server_start_time']));
                }
                break;
        }
    }

    /*
     * 结束服务
     */
    public function end_server(){
        switch ($this->_method) {
            case "post":
                $order_number = I('post.order_number');
                if (empty($order_number) || !isset($order_number))
                    $this->client_return(0, '订单号不能为空！');

                $Model_order = M('order as o');
                $order_info = $Model_order
                    ->join('left join __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                    ->join('left join __USER_MEMBER_INFO__ as ui on ui.uid = o.uuid')
                    ->where(array('o.order_number' => $order_number, 'oi.server_type' => 0, 'o.server_status' => 3, 'o.pay_status' => 0))
                    ->field('oi.server_price,oi.server_start_time,oi.server_type,oi.guide_type,oi.charging_type,o.guid')
                    ->find();
                if ($order_info) {
                    $Model = new \Think\Model();
                    if (false !== $Model->execute('alter table __ORDER__ ENGINE=innodb') && FALSE !== $Model->execute('alter table __ORDER_INFO__ ENGINE=innodb')) {
                        $Model_order->startTrans();
                        $Model_order_info = M('order_info');

                        //订单金额计算
                        $now_time = NOW_TIME;
                        $diff = bcsub($now_time, $order_info['server_start_time'], 0); //服务时间（秒）
                        $hours = floor(bcdiv($diff,3600));
                        $diff = bcsub($diff,bcmul($hours,3600));
                        $mins = floor(bcdiv($diff,60));
                        if($hours < 1){
                            $hours += 1;
                        }elseif ($hours >= 1){
                            if ($mins > 15)
                                $hours += 1;
                        }

                        $order_money = bcmul($hours, $order_info['server_price'], 2);
                        if ($order_info['guide_type'] == 1 && $order_info['charging_type'] == 2) {//讲解员按次计费
                            $order_money = $order_info['server_price'];
                        }

                        $map['order_number'] = $order_number;
                        $data_order_info = array(
                            'server_end_time' => $now_time,
                            'real_end_server_time' => $now_time,
                        );
                        $data_order = array(
                            'server_status' => 4,
                            'order_money' => $order_money,
                            'pay_money' => $order_money,
                        );
                        if (!$Model_order_info->where(array_merge($map))->save($data_order_info) || !$Model_order->where(array_merge($map, array('server_status' => 3)))->save($data_order)) {
                            $Model_order->rollback();
                            //mysql数据库引擎修改
                            $Model->execute('alter table __ORDER__ ENGINE=myisam');
                            $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');
                            $this->client_return(0, '结束服务失败！');
                        }

                        M('guide_member_info')->where(array('uid' => $order_info['guid']))->setInc('server_times', 1);

                        $Model_order->commit(); //事务提交
                        //mysql数据库引擎修改
                        $Model->execute('alter table __ORDER__ ENGINE=myisam');
                        $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');
                        $this->client_return(1, '结束服务成功');
                    }
                }
                $this->client_return(0, '订单号有误！');
                break;
        }
    }

    /**
     * 问题反馈
     */
    public function upload_feedback()
    {
        switch($this->_method){
            case 'get':
                break;
            case 'post':
                $data['uid'] = I('post.uid');
                $data['content'] = I('post.content');
                $data['client'] = $this->client;
                $data['addtime'] = NOW_TIME;
                if(M('feedback')->add($data))
                    $this->client_return(1, '反馈成功');
                $this->client_return(0, '反馈失败');
                break;
        }
    }

    /*
     * 获取导游评价标签
     */

    public function get_comment_tag(){
        switch ($this->_method) {
            case 'get':
                $tag = '';
                foreach(get_config_key_value(C('setting.COMMENT_TAG')) as $key=> $val){
                    if($key<10) $tag['good'][] = array(
                        'key'   =>  $key,
                        'val'   =>  $val
                    );
                    else    $tag['bad'][] = array(
                        'key'   =>  $key,
                        'val'   =>  $val
                    );
                }
                $this->client_return(1, '获取成功！', $tag);
                break;
        }
    }

    /*
     * 获取打赏配置
     */

    public function get_tip()
    {
        switch ($this->_method) {
            case 'get':
                $this->client_return(1, '获取成功！', array('conf' => get_config_key_value(C('setting.TIP_MONEY'))));
                break;
        }
    }

    /*
     * 提现银行机构支持列表
     */
    public function bank_list(){
        switch($this->_method){
            case 'get':
                $msg = array(
                    'action'    =>  'DLOBKQRY',
                    'userName'    =>  C('CNCB.USERNAME'),
                );
                $xml = xml_encode($msg, 'stream', 'list', '', 'name', 'GBK', 'userDataList');
                $response = curl('http://173.28.183.156:7444/', 'post', $xml);
                if($response['status'] == 'AAAAAAA')
                    $this->client_return(1,'获取成功！',$response['list']['row']);
                $this->client_return(0,'服务器异常，请联系管理员！');
                break;
        }
    }

    /**
     * 版本更新
     */
    public function version_update(){
        switch ($this->_method){
            case 'get':
                $platform = I('get.platform','');
                if(empty($platform) || !in_array($platform,array('andr','ios')))
                    $this->client_return(0,'检查版本更新错误');
                $map = array(
                    'client'    =>  $this->client,
                    'platform'  =>  $platform,
                    'status'    =>  1,
                );
                if($result = M('version')->field('version_code,version_title,update_content,version_url')->order('version_code desc')->where($map)->find()){
                    $result['must_update'] = false;
                    $handler = opendir('./'.APP_PATH);
                    while (($filename = readdir($handler)) !== false) {
                        if ($filename != "." && $filename != ".." && strpos($filename, 'Api')===0) {
                            $groups[] = $filename ;
                        }
                    }
                    closedir($handler);
                    if(array_search(MODULE_NAME,$groups) === 0 && count($groups) >=5)
                        $result['must_update'] = true;
					$result['version_url'] = get_pic_url().$result['version_url'];
					$this->client_return(1, '获取成功！', $result);
                }
                $this->client_return(0, '获取失败！');
                break;
        }
    }

    public function array_m(){
        $arr1 = array('id'  =>  1,  'name'  =>'梁彬城');
        $arr2 = array_merge_recursive(array($arr1,$arr1));
        dump($arr2);die;
    }

}
