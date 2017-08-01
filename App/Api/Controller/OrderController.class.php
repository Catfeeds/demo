<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Api\Controller;

/**
 * Description of OrderController
 *
 * @author VAIO
 */
class OrderController extends ApiController {


    /*
     * 生成订单
     * @param $server_addr_lnglat   30.55275643667700，104.07450759593000，key    纬度，经度，地理位置关键词
     */
    public function create_order(){
        switch($this->_method){
            case 'get':
                break;
            case 'post':
                $uid = I('post.uid');
                $server_addr_lnglat = I('post.server_addr_lnglat');
                $server_addr_lnglat = explode(',', $server_addr_lnglat);
                $guide_type = I('post.guide_type');
                $realname = I('post.realname');
                $idcard = I('post.idcard');
                $tourist_id = I('post.tourist_id');
                $phone = I('post.phone');
                $charging_type  = I('post.charging_type');
                $linkman_id = I('post.linkman_id');
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不能为空！');
                if (empty($server_addr_lnglat) || !is_array($server_addr_lnglat))
                    $this->client_return(0, '目的地不能为空！');
                if(empty($guide_type))
                    $this->client_return(0,'导游类型不能为空！');
                if($guide_type == 1){
                    if(empty($phone) || \Think\Model::regex($phone, '/^1[34578]{1}\d{9}$/') !== TRUE)
                        $this->client_return(0,'请填写11位不为空的有效联系手机号！');
                    if(empty($tourist_id))
                        $this->client_return(0,'景区id不能为空！');
                    if(empty($realname))
                        $this->client_return(0,'称呼不能为空！');
                    if(empty($charging_type))
                        $this->client_return(0,'计费类型不正确！');
                }elseif($guide_type == 2){
                    if(empty($linkman_id))
                        $this->client_return(0,'联系人信息不能为空！');
                }
                //验证用户是否有待处理订单存在
                $user_unfinish = $this->check_user_unfinished($uid, $this->client);
                if (!empty($user_unfinish['data_type']) && (!empty($user_unfinish['going']) || !empty($user_unfinish['unpay']) || !empty($user_unfinish['uncomment']))) {
                    switch ($user_unfinish['data_type']) {
                        case 'going':
                            $this->client_return(-1, '你有正在进行中的订单', $user_unfinish);
                            break;
                        case 'unpay':
                            $this->client_return(-1, '你有待支付的订单', $user_unfinish);
                            break;
                        case 'uncomment':
                            $this->client_return(-1, '你有待评论的订单', $user_unfinish);
                            break;
                    }
                }
                //根据经纬度获取省市信息
                $return_server_addr_info = get_log_lat_addr_info($server_addr_lnglat[0], $server_addr_lnglat[1]);

                //保存用户经纬度
                M('user_member_info')->where(array('uid'=>$uid))->save(array('lon'=>$server_addr_lnglat[1],'lat'=>$server_addr_lnglat[0]));

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
                if($guide_type == 1){
                    $map['ta.id']  =   $tourist_id;
                    $map['ta.status']   =   1;
                    $join = 'left join __TOURIST_AREA__ as ta on ta.id = gi.tourist_id';
                    $field = 'gi.uid,gi.deviceid,gi.push_id,ta.hours_price,ta.times_price';
                }

                $Model_guide_member_info = M('guide_member_info as gi');

                $guide_list = $Model_guide_member_info->join($join)->where($map)->lock(TRUE)->field($field)->select();
                foreach ($guide_list as $kg => $vg) {
                    //验证导游是否有订单正在进行中
                    $guide_unfinish = $this->check_user_unfinished($vg['uid'], 2);
                    if (!empty($guide_unfinish['data_type']) && !empty($guide_unfinish['going']))
                        unset($guide_list[$kg]);
                }
                if (!$guide_push_id_arr = array_filter(array_column($guide_list, 'push_id')))
                    $this->client_return(0, '对不起，系统没有为你匹配到' . $this->get_guide_type_string($guide_type) . '!');

                //服务价格处理
                $server_price = get_config_key_value(C('setting.LLR_INSTANT_PRICE'));
                if($guide_type == 1){
                    $server_price = $charging_type == 1 ? $guide_list[0]['hours_price'] : $guide_list[0]['times_price'];
                }
                //生成订单
                $Model_order = M('order');
                $order = array(
                    'uuid' => $uid,
                    'order_number' => $order_number = create_order_no(),
                    'order_time' => NOW_TIME,
                    'server_status' => 0,
                    'pay_status' => 0,
                    'guid' =>  0,
                );
                vendor('AES.Aes');
                $AES = new \MCrypt();
                $order_info = array(
                    'order_number' => $order_number,
                    'start_addr' => $return_server_addr_info['formatted_address'] . $server_addr_lnglat[2],
                    'end_addr' => $return_server_addr_info['formatted_address'] . $server_addr_lnglat[2],
                    'server_start_time' => 0,
                    'server_end_time' => 0,
                    'server_type' => 0,
                    'guide_type' => $guide_type,
                    'server_price' => $server_price,
                    'realname'  =>  $realname,
                    'charging_type' =>  $charging_type,
                    'phone' =>  $phone,
                    'idcard'    =>  $AES->encrypt($idcard),
                );
                if($guide_type == 2){
                    if(!$linkman_info = M('user_linkman')->where(array('uid'=>$uid,'id'=>$linkman_id))->field('realname,phone,idcard')->find())
                        $this->client_return(0,'联系人信息有误，请正确填写真实的信息！');
                    $order_info['phone']    =   $linkman_info['phone'];
                    $order_info['realname']    =   $linkman_info['realname'];
                    $order_info['idcard']    =   $linkman_info['idcard'];
                }

                //数据表引擎修改
                $Model = new \Think\Model();
                if (FALSE !== $Model->execute('alter table __ORDER__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __ORDER_INFO__ ENGINE=InnoDB')) {
                    $Model_order->startTrans(); //开启事务
                    $Model_order_info = M('order_info');
                    if (!$Model_order->lock(true)->add($order) || !$Model_order_info->lock(true)->add($order_info)) {
                        $Model_order->rollback(); //回滚
                        //mysql数据库引擎修改
                        $Model->execute('alter table __ORDER__ ENGINE=myisam');
                        $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');
                        $this->client_return(0, '下单失败！');
                    }

                    //修改默认访问超时时间
                    $default_time = ini_get('max_execution_time');
                    ini_set('max_execution_time', '0');

                    //推送导游
                    $push_alert = '你有新的订单!';
                    $user_info =M('user_member_info')->where(array('uid'=>$uid))->field('head_image,lon,lat')->find();
                    $push_data = array(
                        'order_info' => array(
                            'order_number' => $order_number,
                            'nickname'  =>  $realname,
                            'head_image'    =>  get_pic_url().$user_info['head_image'],
                            'server_addr'  =>  $return_server_addr_info['formatted_address'] . $server_addr_lnglat[2],
                            'user_lnglat'   =>  $user_info['lon'].','.$user_info['lat'],
                            'phone' =>  $phone,
                        ),
                        'server_type' => 100);
                    $result = $this->init_push($type = 1)->push()
                        ->setOptions(null, null, null, true,null)
                        ->setPlatform(array('android', 'ios'))
                        ->addRegistrationId($guide_push_id_arr)
                        ->setMessage('', '', '', $push_data)
                        ->addAndroidNotification($push_alert, '', 0, $push_data)
                        ->addIosNotification($push_alert, '', '+1', true, '', $push_data)
                        ->send();
                    if (is_object($result->data)) {
                        //发送成功就生成订单缓存信息
                        $redisCache = S(array('type' => 'redis', 'expire' => 0, 'prefix' => 'guid_push_order'));
                        $guid_arr = array_column($guide_list, 'uid');
                        foreach ($guid_arr as $key => $val) {
                            //获取当前用户之前推送的订单信息
                            $old_push_order = $redisCache->$val;
                            $now_push_order = array($order_number => $guide_list);
                            if ($old_push_order) {
                                foreach ($old_push_order as $k => $v) {
                                    foreach ($now_push_order as $k1 => $v1) {
                                        if ($k == $k1) {
                                            $new_push_order[$k] = $v1;
                                        } else {
                                            $new_push_order[$k] = $v;
                                            $new_push_order[$k1] = $v1;
                                        }
                                    }
                                }
                            } else {
                                $new_push_order = $now_push_order;
                            }
                            $redisCache->$val = $new_push_order; //生成新的用户缓存
                            //unset($redisCache->$val);
                        }

                        $Model_order->commit(); //事务提交  订单生成成功
                        //mysql数据库引擎修改
                        $Model->execute('alter table __ORDER__ ENGINE=myisam');
                        $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');
                        ini_set('max_execution_time',$default_time);

                        $this->client_return(1, '下单成功，正在为你寻找' . $this->get_guide_type_string($guide_type) . '!', array('order_number' => $order_number));
                    }
                    ini_set('max_execution_time',$default_time);
                }
                break;
        }
    }

    public function guide_confirm_order() {
        switch ($this->_method) {
            case "get":
                break;
            case "post":
                $uid = I('post.uid');
                $order_number = I('post.order_number');
                $confirm = I('post.confirm');
                if (empty($uid) || !isset($uid))
                    $this->client_return(0, 'uid不能为空！');
                if (empty($order_number) || !isset($order_number))
                    $this->client_return(0, '订单号不能为空！');
                if (empty($confirm) || !isset($confirm))
                    $this->client_return(0, '操作类型不能为空！');

                $Model_order = M('order as o');
                $Model_order_info = M('order_info');
                //获取订单类型（即时/预约订单；导游/伴游/土著类型）
                $order_info = $Model_order
                    ->join('left join __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                    ->join('left join __GUIDE_MEMBER_INFO__ as gi on true')
                    ->where(array('o.order_number' => $order_number,'gi.uid'=>$uid,'gi.is_online'=>1))
                    ->field('o.uuid,o.guid,o.cancel_time,o.server_status,oi.server_start_time,oi.server_end_time,oi.server_type,oi.guide_type,oi.server_price')
                    ->find();
                //创建redis对象
                $redisCache = S(array('type' => 'redis', 'expire' => 0, 'prefix' => 'guid_push_order'));
                $old_push_cache = $redisCache->$uid; //获取推送给导游的订单列表

                //检测订单是否被关闭
                if($order_info['guid'] == 0 && $order_info['cancel_time'] > 0 && $order_info['server_status'] == 2){
                    //用户关闭订单，删除缓存
                    foreach ($old_push_cache[$order_number] as $val) {
                        $guid = $val['uid'];
                        $other_guide_old_cache = $redisCache->$guid;
                        unset($other_guide_old_cache[$order_number]);
                        $redisCache->$guid = $other_guide_old_cache;
                    }
                    $this->client_return('0','订单已经被关闭！');
                }


                //拒单
                if ($confirm != 1) {
                    unset($old_push_cache[$order_number]);
                    $redisCache->$uid = $old_push_cache;
                    //全部导游拒单时自动关闭订单
                    if(empty($old_push_cache)){
                        $close_order = array(
                            'cancel_time'   =>  NOW_TIME,
                            'server_status' =>  5,
                        );
                        $Model_order->where(array('order_number'=>$order_number))->lock(true)->save($close_order);
                    }
                    $this->client_return(1, '拒单成功！');
                }

                /*
                 * 接单操作
                 */
                foreach ($old_push_cache as $key => $value)
                    $keys[] = $key;

                if (in_array($order_number, $keys)) {
                    if ($order_info) {
                        //修改订单信息
                        $map = array(
                            'order_number' => $order_number,
                            'pay_status' => 0,
                            'server_status' => 0,
                        );
                        $data_order = array(
                            'server_status' => 1,
                            'guide_confirm_order_time' => NOW_TIME,
                            'guid'  =>$uid,
                        );
                        $data_order_info = array(
                            'server_price' => $order_info['server_price'],
                        );

                        //修改mysql数据表引擎
                        $Model = new \Think\Model();
                        if (FALSE !== $Model->execute('alter table __GUIDE_MEMBER__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __ORDER__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __ORDER_INFO__ ENGINE=InnoDB')) {
                            $Model_order->startTrans();
                            if (!$Model_order->where($map)->save($data_order) || FALSE === $Model_order_info->where(array('order_number' => $order_number))->save($data_order_info) || FALSE === M('guide_member_info')->where(array('uid' => $uid))->save(array('is_online'=>2))) {
                                $Model_order->rollback();
                                $this->client_return(0, '接单失败！');
                            }
                            $Model_order->commit();
                            //mysql数据库引擎修改
                            $Model->execute('alter table __GUIDE_MEMBER__ ENGINE=myisam');
                            $Model->execute('alter table __ORDER__ ENGINE=myisam');
                            $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');

                            //删除缓存
                            foreach ($old_push_cache[$order_number] as $val) {
                                $guid = $val['uid'];
                                $other_guide_old_cache = $redisCache->$guid;
                                unset($other_guide_old_cache[$order_number]);
                                $redisCache->$guid = $other_guide_old_cache;
                            }
                            unset($redisCache->$uid);
                            $this->client_return(1, '确认接单成功！', array('order_number' => $order_number));
                        }
                    }
                }
                $this->client_return(0, '/(ㄒoㄒ)/~~，订单被别人抢走啦！');
                break;
        }
    }


    /*
     * 用户端获取已下订单是否已被接受
     */
    public function get_order_status() {
        switch ($this->_method) {
            case "get":
                $order_number = I('get.order_number');
                if (empty($order_number) || !isset($order_number))
                    $this->client_return(0, '订单号不能为空！');
                $Model_order = M('order');
                $order_info = $Model_order->where(array('order_number' => $order_number,))->field('guid,cancel_time,guide_confirm_order_time,server_status')->find();
                if($order_info['guide_confirm_order_time'] != 0 && $order_info['cancel_time'] == 0 && $order_info['server_status'] == 1){
                    $this->client_return(1, '导游获取成功！', array('guid'=>$order_info['guid']));
                }elseif ($order_info['guide_confirm_order_time'] == 0 && $order_info['cancel_time'] != 0 && $order_info['server_status'] == 5) {
                    $this->client_return(-1, '周围没有导游接单！');
                }
                $this->client_return(0, '导游正在接单中！');
                break;
            case "post":
                break;
        }
    }

    /**
     * 根据订单号获取用户信息
     *
     */
    public function order_guide_user_info() {
        switch ($this->_method) {
            case 'get':
                $order_number = I('get.order_number');
                if (empty($order_number))
                    $this->client_return(0, '订单号不能为空！');
                $Model = M('order as o');
                switch ($this->client){
                    case 1://用户端获取导游信息
                        $info = $Model
                            ->join('left join __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                            ->join('left join __GUIDE_MEMBER_INFO__ as gi on gi.uid = o.guid')
                            ->where(array('o.order_number' => $order_number,'o.guid'=>array('exp','is not null')))
                            ->field('gi.realname,gi.phone,gi.head_image,gi.idcard,gi.lon,gi.lat,gi.stars,oi.server_start_time,oi.start_addr')
                            ->find();
                        break;
                    case 2://导游端获取用户信息
                        $info = $Model
                            ->join('left join __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                            ->join('left join __USER_MEMBER_INFO__ as ui on ui.uid = o.uuid')
                            ->where(array('o.order_number' => $order_number,'o.uuid'=>array('exp','is not null')))
                            ->field('oi.realname,oi.phone,oi.start_addr as server_addr,oi.server_start_time,ui.head_image,ui.lon,ui.lat,oi.start_addr as server_addr')
                            ->find();
                        break;
                }
                if($info){
                    vendor('AES.Aes');
                    $AES = new \MCrypt();
                    $info['head_image'] = get_pic_url() . $info['head_image'];
                    $info['idcard'] = $AES->decrypt($info['idcard']);
                    $this->client_return(1,'获取成功!',$info);
                }
                $this->client_return(0,'订单号有误！');
                break;
            case 'post':
                break;
        }
    }

    /*
     * 提交工单
     */

    public function submit_order(){
        switch ($this->_method){
            case 'get':
                break;
            case 'post':
                $order_number = I('post.order_number');
                if(empty($order_number))
                    $this->client_return(0,'订单号不能为空！');
                $Model_order = M('order as o');
                $map = array(
                    'o.order_number'    =>  $order_number,
                    'o.server_status'   =>  4,
                    'o.pay_status'  =>  0,
                    'o.order_money' =>  array('exp','is not null'),
                    'o.pay_money' =>  array('exp','is not null'),
                );
                $order_info = $Model_order
                    ->join('left join __USER_MEMBER_INFO__ as ui on ui.uid = o.uuid')
                    ->where($map)
                    ->field('ui.push_id,o.guid')
                    ->find();
                if($order_info){
                    //修改导游状态
                    M('guide_member_info')->where(array('uid' => $order_info['guid']))->save(array('is_online'=>1));
                    if(M('order')->where(array('order_number'=>$order_number))->save(array('server_status'=>6))){
                        //推送用户支付订单
                        $push_alert = '行程已结束，请付款！';
                        $push_data = array('order_number' => $order_number, 'server_type' => 201);
                        $result = $this->init_push()->push()
                            ->setOptions(null, null, null, true,null)
                            ->setPlatform(array('android', 'ios'))
                            ->addRegistrationId($order_info['push_id'])
                            ->setMessage('', '', '', $push_data)
                            ->addAndroidNotification($push_alert, '', 0, $push_data)
                            ->addIosNotification($push_alert, '', '+1', true)
                            ->send();
                        if (is_object($result->data))
                            $this->client_return(1,'工单提交成功!');
                    }
                    $this->client_return(0,'工单提交失败，请稍后重试！');
                }
                $this->client_return(0,'工单提交失败，请稍后重试！');
                break;
        }
    }


    /*
     * 用户取消呼叫
     */

    public function cancel_call(){
        switch($this->_method){
            case 'get':
                break;
            case 'post':
                $order_number   =   I('post.order_number');
                if (empty($order_number) || !is_numeric($order_number))
                    $this->client_return(0, '订单号为空或不正确');
                $Model_order = M('order as o');
                $map = array(
                    'o.order_number' => $order_number,
                    'o.guide_confirm_order_time'    =>  array('eq',0),
                    'o.guid'    =>  array('eq',0),
                );
                if($order_info = $Model_order->where($map)->count()){
                    $data = array(
                        'cancel_time'=>NOW_TIME,
                        'server_status'=>2,
                        'pay_status'=>2
                    );
                    if($Model_order->where($map)->save($data))
                        $this->client_return(1,'操作成功！');
                    $this->client_return(0,'操作失败！');
                }
                break;
        }
    }

    /**
     * 用户端取消订单
     */
    public function cancel_order() {
        switch ($this->_method) {
            case 'get':
                break;
            case 'post':
                $order_number   =   I('post.order_number');
                $action =   I('post.action',0);
                if (empty($order_number) || !is_numeric($order_number))
                    $this->client_return(0, '订单号为空或不正确');
                $Model_order = M('order as o');
                $order_info = $Model_order
                    ->join('left join __GUIDE_MEMBER_INFO__ as gi on gi.uid = o.guid')
                    ->join('left join __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                    ->field('o.guid,o.guide_confirm_order_time,o.server_status,o.cancel_time,gi.push_id,oi.server_start_time,oi.server_type')
                    ->where(array('o.order_number' => $order_number))
                    ->find();
                //检测订单是否已经开始
                if($order_info['server_status'] != 1)
                    $this->client_return(0,'服务已经开始，订单不能取消!');

                //取消订单

                if($action == 0){
                    $data_order = array(
                        'cancel_time' =>   $now_time =  NOW_TIME,
                    );
                    $cancel['is_pay'] = 0;
                    if (bccomp(bcsub($now_time, $order_info['guide_confirm_order_time'],0), bcmul(get_config_key_value(C('setting.DEFAULT_TIME_MINS')), 60, 0)) == 1)
                        $cancel['is_pay'] = 1;
                }elseif($action == 1){
                    $data_order = array(
                        'server_status' => 2,
                        'pay_status' => 2,
                    );
                    if (bccomp(bcsub($order_info['cancel_time'], $order_info['guide_confirm_order_time'],0), bcmul(get_config_key_value(C('setting.DEFAULT_TIME_MINS')), 60, 0)) == 1) {
                        $data_order['pay_status'] = 0;
                        $data_order['pay_money'] = get_config_key_value(C('setting.OVER_TIME_PRICE'));
                    }
                }


                //修改mysql数据表引擎
                $Model = new \Think\Model();
                if (FALSE !== $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __ORDER__ ENGINE=InnoDB')) {
                    $Model_order->startTrans();
                    if (false === $Model_order->where(array('order_number'=>$order_number,'server_status'=>1))->save($data_order) || false === M('guide_member_info')->where(array('uid'=>$order_info['guid']))->save(array('is_online' =>  1))) {
                        $Model_order->rollback();
                        $this->client_return(0, '订单取消失败！');
                    }
                    $Model_order->commit();
                    //mysql数据库引擎修改
                    $Model->execute('alter table __GUIDE_MEMBER__ ENGINE=myisam');
                    $Model->execute('alter table __ORDER__ ENGINE=myisam');
                    $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');

                    if($action == 1){
                        //推送用户支付订单
                        $push_alert = '用户已取消订单！';
                        $push_data = array('order_number' => $order_number, 'server_type' => 102);
                        $result = $this->init_push(1)->push()
                            ->setOptions(null, null, null, true,null)
                            ->setPlatform(array('android', 'ios'))
                            ->addRegistrationId(array($order_info['push_id']))
                            ->setMessage('', '', '', $push_data)
                            ->addAndroidNotification($push_alert, '', 0, $push_data)
                            ->addIosNotification($push_alert, '', '+1', true)
                            ->send();
                        if(is_object($result->data))
                            $this->client_return(1, '取消成功！');
                    }
                    $this->client_return(1,'订单取消信息获取成功！',$cancel);
                }
                $this->client_return(0, '取消失败！');
                break;
        }
    }

    /**
     * 订单列表
     */
    public function order_list() {
        switch ($this->_method){
            case 'get':
                $uid = I('get.uid');
                $now_page = I('get.now_page',1);
                $page_number = I('get.page_number',10);
                if (empty($uid))
                    $this->client_return(0, '用户uid不能为空!');
                $Model = M('order as o');
                switch ($this->client){
                    case 1:
                        $map = array('o.uuid'=>$uid,'o.guid'=>array('neq',0));break;
                    case 2:
                        $map =array('o.guid'=>$uid);break;
                }
                $order_list = $Model
                    ->join('left join __ORDER_INFO__ as oi on o.order_number=oi.order_number')
                    ->where($map)
                    ->field('o.order_number,o.pay_money,o.server_status,o.pay_status,o.order_time,oi.end_addr,oi.guide_type')
                    ->page($now_page, $page_number)
                    ->order('o.order_time desc')
                    ->select();
                if ($order_list) {
                    foreach ($order_list as &$val) {
                        $val['order_time'] = date('Y-m-d H:i', $val['order_time']);
                        $val['order_status'] = $this->order_status($val['server_status'], $val['pay_status']);
                        unset($val['server_status'], $val['pay_status']);
                    }
                    $this->client_return(1, '订单列表获取成功！', $order_list);
                }
                $this->client_return(0, '订单列表为空');
                break;
            case 'post':
                break;
        }
    }

    /**
     * 用户端端订单详情
     */
    public function order_info() {
        switch ($this->_method){
            case 'get':
                $order_number = I('get.order_number');
                if (empty($order_number))
                    $this->client_return(0, '订单号不能为空！');
                if (FALSE !== $order_info = $this->get_order_info($order_number)){
                    $this->client_return(1, '订单详情获取成功', $order_info);
                }
                $this->client_return(0, '订单详情获取失败');
                break;
            case 'post':
                break;
        }
    }


    /**
     * 支付页面
     */
    public function pay_info() {
        switch($this->_method){
            case 'get':
                $order_number = I('get.order_number');
                if(empty($order_number))
                    $this->client_return(0,'订单号不能为空！');
                $Model_order = M('order as o');
                $detail = $Model_order
                    ->field('gmi.realname,gmi.head_image,gmi.guide_type,um.mobile,o.order_time,o.order_money,o.rebate_money,o.pay_type,oi.server_start_time,oi.server_end_time,oi.server_type')
                    ->join('left join __ORDER_INFO__ as oi on oi.order_number=o.order_number')
                    ->join('left join __COMMENT__ as c on c.order_number=o.order_number')
                    ->join('left join __GUIDE_MEMBER_INFO__ as gmi on gmi.uid=o.guid')
                    ->where('o.order_number=' . $order_number)
                    ->find();
                if ($detail) {
                    $detail['head_image'] = get_pic_url() . $detail['head_image'];
                    $this->client_return(1, '支付信息获取成功', $detail);
                } else {
                    $this->client_return(0, '支付信息获取失败');
                }
                break;
            case 'post':
                break;
        }
    }


    /*
 * 修改订单信息
 * $order_number 订单号
 * $pay_channel 支付渠道
 * $tip_id 小费配置key  -1为空,不打赏
 * $coupon_id   优惠券  0默认匹配的优惠券   -1 为不使用任何优惠券
 */

    public function update_order() {
        switch ($this->_method) {
            case "get":
                break;
            case "post":
                $order_number = I('post.order_number');
                $tip_id = I('post.tip_id',-1);
                $coupon_id = I('post.coupon_id',0);
                if (empty($order_number) || !isset($order_number))
                    $this->client_return(0, '订单号不能为空！');
                $Model_order = M('order as o');
                $order_info = $Model_order->where(array('order_number' => $order_number))->field('server_status,pay_status,order_money')->find();
                if($order_info['server_status'] != 6 || $order_info['pay_status'] != 0)
                    $this->client_return(0,'非法操作！');
                if (bccomp($order_info['order_money'], 0) === 1) {
                    $data['tip_money'] = 0;
                    $data['pay_money'] =$order_info['order_money'];
                    if ($tip_id != -1 && is_numeric($tip_id)) {
                        $data['tip_money'] = get_config_key_value(C('setting.TIP_MONEY'), $tip_id);
                        $data['pay_money'] = bcadd($order_info['order_money'], $data['tip_money'], 2);
                    }

                    if ($coupon_id == -1) {
                        //不使用任何优惠券
                    } elseif ($coupon_id == 0) {
                        //使用默认优惠券
                    } elseif ($coupon_id > 0 && is_numeric($coupon_id)) {
                        //使用用户选择的优惠券
                    }

                    if (!empty($data)) {
                        if (FALSE !== $Model_order->where(array('order_number' => $order_number))->save($data)){
                            $info = $Model_order->where(array('order_number' => $order_number))->field('server_status,pay_status,pay_money,tip_money,rebate_money')->find();
                            $this->client_return(1,'订单信息修改成功！',$info);
                        }
                        $this->client_return(0, '订单信息更新失败！');
                    }
                }
                break;
        }
    }

    /**
     * 模拟支付接口（测试用）
     */
    public function confirm_pay() {
        switch($this->_method){
            case 'get':
                break;
            case 'post':
                $order_number = I('post.order_number');
                if(empty($order_number))
                    $this->client_return(0,'订单号不能为空！');
                $Model_order = M('order');
                if($order_info = $Model_order->where(array('order_number'=>$order_number))->field('server_status,pay_status')->find()){
                    if(($order_info['server_status'] != 6 && $order_info['server_status'] != 2) || $order_info['pay_status'] != 0)
                        $this->client_return(0,'非法操作！');
                    $map = array(
                        'order_number'=>$order_number,
                        'server_status'=> array('in','2,6'),
                        'pay_status'    =>  0,
                    );
                    if(false !== M('order')->where($map)->setField('pay_status', $order_info['server_status'] == 2 ? 2 : 1))
                        $this->client_return(1, '支付成功！');
                    $this->client_return(0, '支付失败！');
                }
                break;
        }
    }


    /*
     * 验证推送的订单是否有效
     */
    public function check_order_valid(){
        switch($this->_method){
            case 'get':
                $order_number = I('get.order_number');
                if(empty($order_number))
                    $this->client_return(0,'订单号不能为空');
                $Model = M('order');
                $order_time = $Model->getFieldByOrder_number($order_number,'order_time');
                $this->client_return(1,'',array('diff_time'=>NOW_TIME-$order_time));
                break;
            case 'post':
                break;
        }
    }



    private function get_order_info($order_number) {
        $Model = M('order as o');
        if ($this->client == 2) {
            $info = $Model
                ->field('oi.realname,oi.phone,ui.head_image,o.order_number,o.server_status,o.pay_status,o.order_time,o.cancel_time,o.order_money,o.rebate_money,o.tip_money,o.pay_money,o.pay_type,oi.server_start_time,oi.server_end_time,oi.server_price,oi.guide_type,oi.charging_type,oi.end_addr,c.content,c.star,c.tag')
                ->join('left join __ORDER_INFO__ as oi on o.order_number=oi.order_number')
                ->join('left join __COMMENT__ as c on c.order_number=o.order_number')
                ->join('left join __USER_MEMBER_INFO__ as ui on ui.uid=o.uuid')
                ->where(array('o.order_number' => $order_number))
                ->find();
        } elseif ($this->client == 1) {
            $info = $Model
                ->field('gi.realname,gi.phone,gi.head_image,gi.stars,gi.idcard,o.guid,o.order_number,o.server_status,o.pay_status,o.order_time,o.cancel_time,o.order_money,o.rebate_money,o.tip_money,o.pay_money,o.pay_type,oi.server_start_time,oi.server_end_time,oi.server_price,oi.guide_type,oi.charging_type,oi.end_addr,c.content,c.star,c.tag')
                //->field('gi.realname,gi.phone,gi.head_image,gi.guide_type,gi.stars,o.order_number,o.server_status,o.pay_status,o.order_money,o.rebate_money,o.tip_money,o.pay_money,o.pay_type,o.order_time,o.cancel_time,oi.server_start_time,oi.server_end_time,c.content,c.star,c.tag')
                ->join('left join __ORDER_INFO__ as oi on oi.order_number=o.order_number')
                ->join('left join __COMMENT__ as c on c.order_number=o.order_number')
                ->join('left join __GUIDE_MEMBER_INFO__ as gi on gi.uid=o.guid')
                ->where(array('o.order_number' => $order_number))
                ->find();
        }

        if ($info) {
            if(!empty($info['cancel_time']))
                $info['cancel_time'] = date('Y-m-d H:i:s', $info['cancel_time']);
            $info['order_time'] =   date('Y-m-d H:i:s',$info['order_time']);
            $info['head_image'] = get_pic_url() . $info['head_image'];
            //订单服务时间处理
            $diff_time = bcsub($info['server_end_time'], $info['server_start_time'],0);
            $info['server_time_long'] = gmstrftime('%H小时%M分钟%S',$diff_time).'秒';
            if($this->client == 1){
                vendor('AES.Aes');
                $AES = new \MCrypt();
                $info['idcard'] = $AES->decrypt($info['idcard']);
            }

            $tmp = explode(':',gmstrftime('%H:%M:%S',$diff_time));
            $server_time_long = $tmp[0];
            if ($server_time_long < 1) {
                $server_time_long += 1;
            } elseif ($server_time_long >= 1) {
                if ($tmp[1] > 10)
                    $server_time_long += 1;
            }
            if(($info['charging_type'] == 1 && $info['guide_type'] == 1) || $info['guide_type'] == 2){
                $info['charging_server_time_long'] = $server_time_long;
                $info['total_money']  =   $info['order_money'];
            }
            $info['server_start_time'] =   date('Y-m-d H:i:s',$info['server_start_time']);
            $info['server_end_time'] =   date('Y-m-d H:i:s',$info['server_end_time']);
            $info['order_status'] = $this->order_status( $info['server_status'], $info['pay_status']);
            unset($info['server_status'], $info['pay_status']);
            if (!empty($info['tag'])) {
                $info['tag'] = unserialize($info['tag']);
                foreach ($info['tag'] as &$val) {
                    $val = get_config_key_value(C('setting.COMMENT_TAG'), $val);
                }
            } else  $info['tag'] = array();
            return $info;
        }
        return FALSE;
    }


    public function order_status($server_status, $pay_status) {
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
        return $order_status;
    }
}
