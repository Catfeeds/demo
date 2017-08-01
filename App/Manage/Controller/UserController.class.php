<?php

namespace Manage\Controller;
use Api_v2\Controller\OrderController;
use Think\Model;

/**
 * @author vaio
 * @datetime 2016-4-6  10:30:50
 * @encoding UTF-8 
 * @filename UserController.class.php 
 */
class UserController extends AdminController {
    
    public function _initialize(){
        parent::_initialize();
        $this->group_title = '用户管理';
    }

    /**
     * 普通用户列表
     */
    public function tourist_list() {
        if (IS_GET) {
            $now_page = I('get.page', 1);
            $page_size = 15;
            $key = I('get.key');
            $start_time = I('get.start_time');
            $end_time = I('get.end_time');
            $end_time .= ' 23:59:59';
            if(!empty($key)){
                $where = array(
                    '_logic' => 'or',
                    'umi.nickname' =>  array('like','%'.$key.'%'),
                    'um.mobile'  =>  array('like','%'.$key.'%'),
                );
                $map['_complex'] = $where;
            }
            if(!empty($start_time))
                $map['um.reg_time'] = array('egt',strtotime($start_time));
            if(!empty($end_time))
                $map['um.reg_time'] = array('elt',strtotime($end_time));
            if(!empty($start_time) && !empty($end_time))
                $map['um.reg_time'] = array(
                    array('egt',strtotime($start_time)),
                    array('elt',strtotime($end_time)),
                );
            $list = M()
                    ->table('yy_user_member as um')
                    ->join('left join yy_user_member_info as umi on umi.uid=um.id')
                    ->field('umi.uid,um.mobile,umi.nickname,umi.reg_time')
                    ->order('umi.reg_time desc')
                    ->where($map)
                    ->page($now_page, $page_size)
                    ->select();
            $count = M('user_member as um')
                    ->join('left join yy_user_member_info as umi on umi.uid=um.id')
                    ->where($map)
                    ->count();
            $pages = intval(ceil($count / $page_size));
            foreach($list as &$val){
                $val['reg_time'] = date('Y-m-d H:i:s',$val['reg_time']);
            }
            $info = array(
                'list'  =>  $list,
                'pages' =>  $pages,
                'get'   =>  $_GET,
            );
           if($info){
               $this->assign('list', $info);
               $this->meta_title = '普通用户列表';
               $this->display();
           }
        }
    }
    /**
     * 普通用户详情
     */
    public function tourist_detail() {
        if (IS_GET) {
            $uid = I('uid');
            $detail = M()
                    ->table('yy_user_member as um')
                    ->join('left join yy_user_member_info as umi on umi.uid=um.id')
                    ->field('umi.uid,um.mobile,um.status,umi.nickname,umi.realname,umi.idcard,umi.head_image,umi.sex,umi.birthday')
                    ->where('um.id=' . $uid)
                    ->find();
            if ($detail) {
                $detail['head_image'] = get_pic_url() . $detail['head_image'];
                if (!empty($detail['idcard'])) {
                    vendor('AES.Aes');
                    $AES = new \MCrypt();
                    $detail['idcard'] = $AES->decrypt($detail['idcard']);
                }
            }
            $this->assign('detail', $detail);
            $this->meta_title = '普通用户详情';
            $this->display();
        }
    }

    /**
     * 游客订单列表
     */
    public function tourist_order_list() {
        if (IS_GET) {
            $uid = I('uid');
            $pageindex = I('p', 1);
            $pagesize = I('pagesize', 10);
            $result = M()
                    ->table('yy_order as o')
                    ->field('o.order_number,o.pay_money,o.server_status,o.pay_status,o.order_time,oi.end_addr,oi.server_type,gmi.realname,gmi.guide_type,gm.mobile')
                    ->join('left join yy_order_info as oi on o.order_number=oi.order_number')
                    ->join('left join yy_guide_member_info as gmi on gmi.uid=o.guid')
                    ->join('left join yy_guide_member as gm on gm.id=o.guid')
                    ->where('o.uuid=' . $uid)
                    ->page($pageindex, $pagesize)
                    ->select();
            if ($result) {
                foreach ($result as &$val) {
                    $val['order_time'] = date('m月d日 H:i', $val['order_time']);
                    $val['order_status'] = order_status($val['server_status'], $val['pay_status']);
                    unset($val['server_status'], $val['pay_status']);
                }
            }
//			print_r($result);die;
            $count = M('order')->where('uuid=' . $uid)->count();
            if ($count > $pagesize) {
                $page = new \Think\Page($count, $pagesize);
                $page->setConfig('theme', '%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
                $this->assign('_page', $page->show());
            }
            $this->assign('list', $result);
            $this->meta_title = '游客订单列表';
            $this->display();
        }
    }

    /**
     * 讲解员列表
     */
    public function guide_list() {
        $tourist_id = I('get.tourist_id');
        $now_page = I('get.page', 1);
        $page_size = 20;
        $is_auth = I('get.is_auth');
        $key = I('get.key');
        $is_online = I('get.is_online');
        $start_time = I('get.start_time');
        $end_time = I('get.end_time');
        $time = time();
        $time_type = I('time_type');
        switch($time_type){
            case 1:
                $end_time = date('Y-m-d',$time);
                $start_time = date('Y-m-d',$time);
                break;
            case 2:

                $end_time = date('Y-m-d',$time);
                $start_time = date('Y-m-d',strtotime('-7 day'));
                break;
            case 3:
                $start_time = date('Y-m-d',strtotime('-1 month'));
                $end_time = date('Y-m-d',$time);
                break;
            case 4:
                $start_time = date('Y-m-d',strtotime('-3 month'));
                $end_time = date('Y-m-d',$time);
                break;
            case -1:
                $start_time = I('get.start_time');
                $end_time = I('get.end_time');
                break;
        }
        if(!empty($key)){
            $where = array(
                '_logic' => 'or',
                'gm.mobile' =>  array('like','%'.$key.'%'),
                'gmi.realname'  =>  array('like','%'.$key.'%'),
            );
            $map['_complex'] = $where;
        }
        if(!empty($is_online) && in_array($is_online,array('0','1','2'))){
            $map['gmi.is_online'] = $is_online;
        }
        if(!empty($tourist_id)){
            $map['gmi.tourist_id'] = $tourist_id;
        }
        if(isset($is_auth) && is_numeric($is_auth) && in_array($is_auth,array('-1','0','1'))){
            $map['gmi.is_auth'] = $is_auth;
        }
        if(!empty($start_time))
            $map['gm.reg_time'] = array('egt',strtotime($start_time));
        if(!empty($end_time))
            $map['gm.reg_time'] = array('elt',strtotime($end_time));
        if(!empty($start_time) && !empty($end_time))
            $map['gm.reg_time'] = array(
                array('egt',strtotime($start_time)),
                array('elt',strtotime($end_time.' 23:59:59')),
            );
        $map['gmi.guide_type'] = 1;
        $list = M('guide_member as gm')
            ->join('__GUIDE_MEMBER_INFO__ as gmi on gm.id=gmi.uid')
            ->join('__TOURIST_AREA__ as ta on ta.id=gmi.tourist_id')
            ->field('gm.id as uid,ta.tourist_name,gm.status,gm.mobile,gmi.realname,gmi.lat,gmi.lon,gmi.guide_type,gmi.is_auth,gmi.is_online,gmi.now_total_amount,gmi.reg_time')
            ->where($map)
            ->order('gmi.reg_time desc')
            ->page($now_page, $page_size)
            ->select();
        foreach($list as &$val){
            $val['reg_time'] = date('Y-m-d H:i:s',$val['reg_time']);
        }
        $count = M('guide_member as gm')
            ->join('__GUIDE_MEMBER_INFO__ as gmi on gmi.uid=gm.id')
            ->join('__TOURIST_AREA__ as ta on ta.id=gmi.tourist_id')
            ->where($map)
            ->count();
        $pages = intval(ceil($count / $page_size));
        $list = array(
            'list'  =>  $list,
            'pages' =>  $pages,
            'get'   =>  $_GET,
        );
        $this->assign("time_type",$time_type);
        $this->assign('list', $list);
        $this->meta_title = '讲解员列表';
        $this->display();
    }
    /**
     * 领路人列表
     */
    public function guide_member_list(){
        $now_page = I('get.page', 1);
        $page_size = 20;
        $is_auth = I('get.is_auth');
        $key = I('get.key');
        $is_online = I('get.is_online');
        $start_time = I('get.start_time');
        $end_time = I('get.end_time');
        $time = time();
        $time_type = I('time_type');
        switch($time_type){
            case 1:
                $end_time = date('Y-m-d',$time);
                $start_time = date('Y-m-d',$time);
                break;
            case 2:
                $end_time = date('Y-m-d',$time);
                $start_time = date('Y-m-d',strtotime('-7 day'));
                break;
            case 3:
                $start_time = date('Y-m-d',strtotime('-1 month'));
                $end_time = date('Y-m-d',$time);
                break;
            case 4:
                $start_time = date('Y-m-d',strtotime('-3 month'));
                $end_time = date('Y-m-d',$time);
                break;
            case -1:
                $start_time = I('get.start_time');
                $end_time = I('get.end_time');
                break;
        }
        if(!empty($key)){
            $where = array(
                '_logic' => 'or',
                'gm.mobile' =>  array('like','%'.$key.'%'),
                'gmi.realname'  =>  array('like','%'.$key.'%'),
            );
            $map['_complex'] = $where;
        }
        if(!empty($is_online) && in_array($is_online,array('0','1','2'))){
            $map['gmi.is_online'] = $is_online;
        }
        if(!empty($tourist_id))
            $map['gmi.tourist_id'] = $tourist_id;
        if(isset($is_auth) && is_numeric($is_auth) && in_array($is_auth,array('-1','0','1'))){
            $map['gmi.is_auth'] = $is_auth;
        }
        if(!empty($start_time))
            $map['gm.reg_time'] = array('egt',strtotime($start_time));
        if(!empty($end_time))
            $map['gm.reg_time'] = array('elt',strtotime($end_time));
        if(!empty($start_time) && !empty($end_time))
            $map['gm.reg_time'] = array(
                array('egt',strtotime($start_time)),
                array('elt',strtotime($end_time.' 23:59:59')),
            );
        $map['gmi.guide_type'] = 2;
        $list = M('guide_member_info as gmi')
            ->join('__GUIDE_MEMBER__ as gm on gmi.uid=gm.id')
            ->field('gm.id as uid,gm.status,gm.mobile,gmi.realname,gmi.lat,gmi.lon,gmi.guide_type,gmi.is_auth,gmi.is_online,gmi.now_total_amount,gmi.reg_time')
            ->where($map)
            ->order('gmi.reg_time desc')
            ->page($now_page, $page_size)
            ->select();
        foreach($list as &$val){
            $val['reg_time'] = date('Y-m-d H:i:s',$val['reg_time']);
        }
        $count = M('guide_member as gm')->join('left join yy_guide_member_info as gmi on gmi.uid=gm.id')->where($map)->count();
        $pages = intval(ceil($count / $page_size));
        $list = array(
            'list'  =>  $list,
            'pages' =>  $pages,
            'get'   =>  $_GET,
        );
        $this->assign("time_type",$time_type);
        $this->assign('list', $list);
        $this->meta_title = '领路人列表';
        $this->display();
    }

    /**
     * 导游详情
     */
    public function guide_detail() {
        if (IS_GET) {
            $uid = I('uid');
            $is_auth = -1;
            $status = 1;
            $detail = M('guide_member_info as gmi')
                ->join('__GUIDE_MEMBER__ as gm on gm.id=gmi.uid')
                ->field('gm.id as uid,gm.mobile,gm.status,gmi.tourist_id,gmi.email,gmi.guide_idcard,gmi.idcard_back,gmi.idcard_front,gmi.realname,gmi.idcard,gmi.head_image,gmi.sex,gmi.birthday,gmi.guide_type,gmi.reg_ip,gmi.last_login_time,gmi.reg_time,gmi.stars,gmi.self_introduce,gmi.server_introduce,gmi.server_times,gmi.is_auth')
                ->where('gm.id ='.$uid)
                ->find();
            $list = M('guide_bank_info as gbi')->field('gbi.bankcard_number,gbi.bank_name')->where('gbi.uid = '.$uid)->select();
            if($list){
                $bank = 1;
            }
            if($detail['guide_type'] == 1){
                $model = M('tourist_area as ta')->field('ta.tourist_name')->where('ta.id ='.$detail['tourist_id'])->find();
            }
            if($detail['is_auth']!=0){
                $time_confirm = M('order_manage as om')->field('om.action_time')->where('om.link_id ='.$uid.' and om.action_type =2 and check_result = 1')->order('om.action_time desc')->find();
                if($time_confirm){
                    $time_confirm['action_time'] = date('Y-m-d H:i:s',$time_confirm['action_time']);
                }
            }
            if($is_auth){
                $result = M('order_manage as om')
                    ->join('__MANAGE_MEMBER__ as mm on mm.uid = om.action_manage_id')
                    ->field('mm.nickname,om.action_msg,om.action_time')
                    ->where('om.link_id ='.$uid.' and om.action_type =2 and check_result = -1')
                    ->select();
                if($result){
                    $false = 1;
                    foreach($result as &$val){
                        $val['action_time'] = date('Y-m-d H:i:s',$val['action_time']);
                    }
                }
            }
            if($status){
                $res = M('order_manage as om')
                    ->join('__MANAGE_MEMBER__ as mm on mm.uid = om.action_manage_id')
                    ->field('mm.nickname,om.action_msg,om.action_time')
                    ->where('om.link_id ='.$uid.' and om.action_type = 4')
                    ->select();
                if($res){
                    $true = 1;
                    foreach($res as &$val){
                        $val['action_time'] = date('Y-m-d H:i:s',$val['action_time']);
                    }
                }
            }
            if($detail){
                $detail['last_login_time'] = date('Y-m-d H:i:s',$detail['last_login_time']);
                $detail['reg_time'] = date('Y-m-d H:i:s',$detail['reg_time']);
                $detail['head_image'] = get_pic_url() . $detail['head_image'];
                $detail['guide_idcard'] = get_pic_url() . $detail['guide_idcard'];
                $detail['idcard_back'] = get_pic_url() . $detail['idcard_back'];
                $detail['idcard_front'] = get_pic_url() . $detail['idcard_front'];
            }
            if (!empty($detail['idcard'])) {
                vendor('AES.Aes');
                $AES = new \MCrypt();
                $detail['idcard'] = $AES->decrypt($detail['idcard']);
            }
            if ($detail['now_address'] > 0) {
                $map['id'] = $detail['now_address'];
                $detail['now_address'] = M('city')->where($map)->getField('name');
            }
            $this->assign('time_confirm', $time_confirm);
            $this->assign('bank', $bank);
            $this->assign('list', $list);
            $this->assign('true', $true);
            $this->assign('false', $false);
            $this->assign('res', $res);
            $this->assign('result', $result);
            $this->assign('model', $model);         
            $this->assign('detail', $detail);
            $this->meta_title = '导游详情';
            $this->display();
        }
    }
    /**
     * guide_bill
     * 导游账单
     */
    public function guide_bill(){
        if(IS_GET){
            $types = I('type');
            $uid = I('uid');
            $now_page = I('get.page', 1);
            $page_size = 12;
            $detail = M('guide_member as gm')
                ->join('__GUIDE_MEMBER_INFO__ as gmi on gmi.uid = gm.id')
                ->field('gmi.head_image,gmi.realname,gmi.guide_type,gmi.phone,gmi.server_times,gmi.now_total_amount,gmi.out_amount')
                ->where('gm.id ='.$uid)
                ->find();
            if(empty($types)){
                $type = 1;
            }else{
                $type = $types;
            }
            $model = M('amount as a');
            if($type == 1){
                $count = $model
                        ->join('__GUIDE_MEMBER_INFO__ as gmi on gmi.uid = a.uid')
                    ->join('__PAY__  as p on p.request_pay_no = a.order_number')
                    ->join('__ORDER__ as o on o.order_number = p.order_number')
                    ->where('a.uid ='.$uid)
                    ->count();
              /*  var_dump($count);*/
                $list = $model
                    ->join('__GUIDE_MEMBER_INFO__ as gmi on gmi.uid = a.uid')
                    ->join('__PAY__  as p on p.request_pay_no = a.order_number')
                    ->join('__ORDER__ as o on o.order_number = p.order_number')
                    ->field('o.order_number,gmi.now_total_amount,a.profit_amount,a.deal_time,o.tip_money,o.pay_money,o.order_money,o.rebate_money,o.derate_money,a.manage_amount')
                    ->order('a.deal_time desc')
                    ->page($now_page, $page_size)
                    ->where('a.uid ='.$uid)
                    ->select();
                foreach($list as &$val){
                    $val['deal_time']  = date('Y-m-d H:i:s',$val['deal_time']);
                    $val['proportion'] = (($val['manage_amount']/$val['order_money'])*100)."%";
                }
            }elseif($type == -1){
                $count = M('guide_withdraw as gw')
                    ->join('left join __GUIDE_MEMBER_INFO__  as gmi on gmi.uid = gw.uid')
                    ->where('gw.uid ='.$uid)
                    ->count();
                $list =M('guide_withdraw as gw')
                    ->join('left join __GUIDE_MEMBER_INFO__  as gmi on gmi.uid = gw.uid')
                    ->field('gw.order_number,gw.is_withdraw,gw.request_time as deal_time,gw.withdraw_amount,gmi.now_total_amount')
                    ->order('deal_time desc')
                    ->where('gw.uid ='.$uid)
                    ->page($now_page, $page_size)
                    ->select();
                foreach($list as &$val){
                    $val['deal_time']    = date('Y-m-d H:i:s',$val['deal_time']);
                    $val['order_money']    =  '-'.$val['withdraw_amount'];
                    $val['tip_money']    =  '-';
                    $val['derate_money']    =  '-';
                    $val['profit_amount']    =  $val['withdraw_amount'];
                    $val['rebate_money']    =  '-';
                    $val['manage_amount']    =  '-';
                    $val['proportion']    =  '-';
                    switch($val['is_withdraw']){
                        case -1:
                            $val['is_withdraw'] ='('."失败".')';
                            break;
                        case 0:
                            $val['is_withdraw'] ='('."提交申请".')';
                            break;
                        case 1:
                            $val['is_withdraw'] ='('."向银行发起转账".')';
                            break;
                        case 2:
                            $val['is_withdraw'] ='('."成功".')';
                            break;
                        default:
                            break;
                    }
                }
            }
            $pages = intval(ceil($count / $page_size));
            $info = array(
                'list'  =>  $list,
                'pages' =>  $pages,
                'get'   =>  $_GET,
            );
            if($info){
                $this->assign("list",$info);
                $this->assign('detail',$detail);
                $this->meta_title = '账单列表';
                $this->display();
            }
        }

}
    /**
     * 导游身份详情
     */
    public function guide_auth() {
        if (IS_GET) {
            $uid = I('uid');
            $detail = M()
                    ->table('yy_guide_member as gm')
                    ->join('left join yy_guide_member_info as gmi on gmi.uid=gm.id')
                    ->field('gm.id as uid,gmi.realname,gmi.idcard,gmi.idcard_front,gmi.idcard_back,gmi.guide_idcard,gmi.guide_type')
                    ->where('gm.id=' . $uid)
                    ->find();
            $detail['idcard_front'] = get_pic_url() . $detail['idcard_front'];
            $detail['idcard_back'] = get_pic_url() . $detail['idcard_back'];
            $detail['guide_card'] = get_pic_url() . $detail['guide_card'];
            $detail['guide_idcard'] = get_pic_url() . $detail['guide_idcard'];
            if (!empty($detail['idcard'])) {
                vendor('AES.Aes');
                $AES = new \MCrypt();
                $detail['idcard'] = $AES->decrypt($detail['idcard']);
            }
            $this->assign('detail', $detail);
            $this->meta_title = '导游身份详情';
            $this->display();
        }
    }
    /**
     * 禁止接单
     */
    public function status_guide(){
        $method = I('method');
        $check_result = I('check_result');
        $id = I('id');
        $arr = array(
            'method' =>$method,
            'check_result' =>$check_result,
            'id'    =>$id,
        );
        if($arr){
            $this->assign('result',$arr);
            $this->meta_title = '禁止接单原因';
            $this->display();
        }
    }
    /**
     * changeStatus_guide
     * 禁用导游
     */
        public function changeStatus_guide($method=null){
                $id = array_unique((array)I('id',0));
                $id = is_array($id) ? implode(',',$id) : $id;
                if ( empty($id) ) {
                    $this->error('请选择要操作的数据!');
                }
                 $check_result = I('check_result');
                if($check_result==-1){
                    $action_msg = I('action_msg');
                }else{
                    $action_msg = '启用';
                }
                $action_time = NOW_TIME;
                $action_type = 4;
                $link_id = $id;
                $action_manage_id = is_login();

                $arr = array(
                    'action_msg' =>$action_msg,
                    'action_time' =>$action_time,
                    'action_type' =>$action_type,
                    'link_id'   =>$link_id,
                    'action_manage_id'=>$action_manage_id,
                    'check_result'  =>$check_result,
                );
                $result = M('order_manage')->add($arr);
                if(!$result){
                    return false;
                }
                $map['uid'] =   array('in',$id);
                switch ( strtolower($method) ){
                    case 'close':
                        if($this->forbid('GuideMember', $map ,$msg = array( 'success'=>'状态禁用成功！', 'error'=>'状态禁用失败！') , true))
                            $this->forbid('GuideMemberInfo', array_merge(array('id'=>array('gt',0)),$map));
                        break;
                    case 'open':
                        if($this->resume('GuideMember', $map ,$msg = array( 'success'=>'状态恢复成功！', 'error'=>'状态恢复失败！') , true))
                            $this->resume('GuideMemberInfo', array_merge(array('id'=>array('gt',0)),$map));
                        break;
                    case 'del':
                        $this->delete('ManageMember', $map );
                        break;
                    case 'delete':
                        $this->del('ManageMember',$map);
                        break;
                    default:
                        $this->error('参数非法');
                }
        }
    /**
     * 导游订单列表
     */
    public function guide_order_list() {
        if (IS_GET) {
            $uid = I('uid');
            $pageindex = I('p', 1);
            $pagesize = I('pagesize', 10);
            $result = M()
                    ->table('yy_order as o')
                    ->field('o.order_number,o.pay_money,o.server_status,o.pay_status,o.order_time,oi.end_addr,oi.server_type,um.mobile')
                    ->join('left join yy_order_info as oi on o.order_number=oi.order_number')
                    ->join('left join yy_user_member as um on um.id=o.uuid')
                    ->where('o.guid=' . $uid)
                    ->page($pageindex, $pagesize)
                    ->select();
            if ($result) {
                foreach ($result as &$val) {
                    $val['order_time'] = date('m月d日 H:i', $val['order_time']);
                    $val['order_status'] = orderController::order_status($val['server_status'], $val['pay_status']);
                    unset($val['server_status'], $val['pay_status']);
                }
            }
            $count = M('order')->where('guid=' . $uid)->count();
            if ($count > $pagesize) {
                $page = new \Think\Page($count, $pagesize);
                $page->setConfig('theme', '%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
                $this->assign('_page', $page->show());
            }
            $this->assign('list', $result);
            $this->meta_title = '导游订单列表';
            $this->display();
        }
    }
    /**
     * Revised_price修改价格
     */
    public function revised_price(){
        $order_number = I('order_number');
        $model = M('order as o')->field('o.pay_money')->where('o.order_number ='.$order_number)->find();
        if($model){
                $this->assign('model',$model);
                $this->assign('number',$order_number);
                $this->check = array(
                    'msg'   =>  get_config_key_value(C('setting.DERATE_NOW_PASS_MSG')),
                );
                $this->meta_title = '减免价格';
                $this->display();
        }else{
            $this->error("系统出错");
        }
    }
    /**
     * update_money
     */
    public function update_money(){
        $action_msg = isset($_POST['msg']) ? get_config_key_value(C('setting.DERATE_NOW_PASS_MSG'),I('msg')) : I('other');
        $order_number = I('number');
        $pay_money = I('pay_money');
        $money = I('moneys');
        $moneys = I('money');
        $action_type = 1;
        $user_id = is_login();
        if($money !=$moneys){
            $this->error('两次价格输入不一致，请重新输入','',true);
        }
        if($moneys >$pay_money){
            $this->error('减免价格不正确，请重新输入','',true);
        }
        $arr = array(
            'action_time'=> NOW_TIME,
            'action_msg' => $action_msg,
            'link_id'    => $order_number,
            'action_type'=> $action_type,
            'action_manage_id'=>$user_id,
            'condition'  =>$moneys,
        );
        $model = M('order_manage')->add($arr);
        if($model){
            $this->success('提交成功，等待审核','',true);
        }else{
            $this->error('提交失败','',true);
        }
    }
    /**
     * 审核不通过
     */
    public function audit_not(){
        $link_id = I('link_id');
        $check_result = I('check_result');
        $pay_money = I('pay_money');
        $id = I('id');
        $condition = I('condition');
        $array = array(
            'link_id' =>$link_id,
            'check_result'=>$check_result,
            'pay_money'=>$pay_money,
            'id'    =>$id,
            'condition'=>$condition,
        );
        $this->assign('info',$array);
        $this->meta_title = '不通过原因';
        $this->display();
    }

    /**
     * 审核价格
     */
    public function audit_price(){
        if (IS_GET) {
            $now_page = I('get.page', 1);
            $page_size = I('pagesize', 15);
            $key = I('get.key');
            $start_time = I('get.start_time');
            $end_time = I('get.end_time');
            $end_time .= ' 23:59:59';
            if(!empty($key)){
                $map['om.link_id'] = $key;
            }
            if(!empty($start_time))
                $map['om.action_time'] = array('egt',strtotime($start_time));
            if(!empty($end_time))
                $map['om.action_time'] = array('elt',strtotime($end_time));
            if(!empty($start_time) && !empty($end_time))
                $map['om.action_time'] = array(
                    array('egt',strtotime($start_time)),
                    array('elt',strtotime($end_time)),
                );
            $map['om.action_type'] = 1;
            $map['om.check_result'] = 0;
           /* if(empty($map)){

            }else{
                $map = 'om.action_type =1 and om.check_result = 0';
            }*/
            $count = M('order_manage as om')
                    ->join('__ORDER__  as o on o.order_number = om.link_id')
                    ->join('__MANAGE_MEMBER__ as mm on mm.uid = om.action_manage_id')
                    ->where($map)
                    ->count();
            $list = M('order_manage as om')
                    ->join('__ORDER__  as o on o.order_number = om.link_id')
                    ->join('__MANAGE_MEMBER__ as mm on mm.uid = om.action_manage_id')
                    ->field('om.id,om.action_msg,om.condition,om.action_time,o.pay_money,om.link_id,mm.nickname')
                    ->where($map)
                    ->page($now_page,$page_size)
                    ->order('om.action_time desc')
                    ->select();
           foreach($list as &$val){
               $val['action_time'] = date('Y-m-d H:i:s',$val['action_time']);
           }
            $pages = intval(ceil($count / $page_size));
            $list = array(
                'list'  =>  $list,
                'pages' =>  $pages,
                'get'   =>  $_GET,
            );
                $this->assign('list', $list);
                $this->meta_title = '审核价格';
                $this->display();
        }
    }
    /**
     * 审核状态
     */
    public function audit_status(){
            $link_id = I('get.link_id');
            $check_result = I('get.check_result');
            $check_manage_id = is_login();
            $check_time = NOW_TIME;
            $pay_money = I('get.pay_money');
            $condition = I('post.condition');
            $check_msg = I('post.check_msg');
            $id = I('get.id');
            $derate_money = $pay_money - $condition;
            $array = array(
                'pay_money' => $condition,
                'derate_money' => $derate_money,
            );
            $action_type = 1;
            $arr = array(
                'check_result'  => $check_result,
                'check_manage_id'=> $check_manage_id,
                'check_time'    => $check_time,
                'check_msg' =>$check_msg,
            );
            if($check_result == 1){
                $result = M('order as o')->where('o.order_number ='.$link_id)->save($array);
                if(!$result){
                    $this->error('修改价格与初始价格一致','',true);
                }
            }
            $model = M('order_manage as om')->where('om.id ='.$id.' and om.action_type ='.$action_type)->save($arr);
            if($model){
                $this->success('审核修改成功','',true);
            }else{
                $this->error('审核修改失败','',true);
            }
    }
    /**
     * close order why
     */
    public function close_order_why(){
        $order_number = I('order_number');
        if($order_number){
            $this->assign('order_number',$order_number);
            $this->check = array(
                'msg'   =>  get_config_key_value(C('setting.CLOSE_NOW_PASS_MSG')),
            );
            $this->meta_title = '关闭订单原因';
            $this->display();
        }
    }

    /**
     * 关闭订单
     */
    public function close_order(){
        if(IS_POST){
            $order_number = I('post.order_number');
            $server_status = 8;
            $pay_status = 2;
            $is_updated = 1;
            $action_type = 3;
            $action_msg = isset($_POST['msg']) ? get_config_key_value(C('setting.CLOSE_NOW_PASS_MSG'),I('msg')) : I('other');
            $action_manage_id = is_login();
            $action_time = NOW_TIME;
            $array = array(
                'action_type' =>  $action_type,
                'action_manage_id'=>$action_manage_id,
                'link_id'   =>$order_number,
                'action_time'=>$action_time,
                'action_msg' => $action_msg,
            );
            $arr = array(
                'is_updated'  => $is_updated,
                'server_status'=>$server_status,
                'pay_status' =>$pay_status,
            );
            $model = M('order as o')->where('order_number='.$order_number)->save($arr);
            if($model){
                $result = M('order_manage')->add($array);
                if($result){
                    $this->success('关闭成功','',true);
                }else{
                    $this->error('关闭失败','',true);
                }
            }else{
                $this->error('关闭失败','',true);
            }
        }
    }
    /**
     * order_once
     */
    public function order_once(){
        if(IS_GET){
            $id = I('uid');
            $now_page = I('get.page', 1);
            $page_size = I('pagesize', 15);
            $count = M()
                ->table('__ORDER__ as o')
                ->join('left join __ORDER_INFO__ as oi on oi.order_number=o.order_number')
                ->join('left join __USER_MEMBER__ as um on um.id=o.uuid')
                ->join('left join __USER_MEMBER_INFO__ as umi on umi.uid=o.uuid')
                ->join('left join __GUIDE_MEMBER__ as gm on gm.id=o.guid')
                ->where('o.uuid=' . $id)
                ->count();
            $pages = intval(ceil($count / $page_size));
            $list = M()
                ->table('__ORDER__ as o')
                ->join('left join __ORDER_INFO__ as oi on oi.order_number=o.order_number')
                ->join('left join __USER_MEMBER__ as um on um.id=o.uuid')
                ->join('left join __USER_MEMBER_INFO__ as umi on umi.uid=o.uuid')
                ->join('left join __GUIDE_MEMBER__ as gm on gm.id=o.guid')
                ->field('o.order_money,o.pay_status,o.order_number,o.pay_money,o.server_status,o.pay_status,o.order_time,oi.end_addr,oi.server_type,um.mobile as user_mobile,umi.nickname,gm.mobile as guide_mobile')
                ->where('o.uuid=' . $id)
                ->page($now_page, $page_size)
                ->order('o.order_time desc')
                ->select();
            if ($list) {
                foreach ($list as $key => &$val) {
                    if (!empty($val['order_time'])) {
                        $val['order_time'] = date('Y-m-d H:i:s', $val['order_time']);
                        if($val['pay_money'] == 0.00){
                            $val['pay_money'] = '-';
                        }
                    }
                    $val['order_status'] = OrderController::order_status( $val['server_status'], $val['pay_status']);
                }
            }
            $list = array(
                'list'  =>  $list,
                'pages' =>  $pages,
                'get'   =>  $_GET,
            );
            if($list){
                $this->assign('list', $list);
                $this->meta_title = '个人订单列表';
                $this->display();
            }
        }
    }
    /**
     * 秒数转化时间
     */
    public function  Sec2Time($time){
    if(is_numeric($time)){
        $value = array(
            "years" => 0, "days" => 0, "hours" => 0,
            "minutes" => 0, "seconds" => 0,
        );
        if($time >= 31556926){
            $value["years"] = floor($time/31556926);
            $time = ($time%31556926);
        }
        if($time >= 86400){
            $value["days"] = floor($time/86400);
            $time = ($time%86400);
        }
        if($time >= 3600){
            $value["hours"] = floor($time/3600);
            $time = ($time%3600);
        }
        if($time >= 60){
            $value["minutes"] = floor($time/60);
            $time = ($time%60);
        }
        $value["seconds"] = floor($time);
        //return (array) $value;
        $t=$value["days"] ."天"." ". $value["hours"] ."小时". $value["minutes"] ."分钟".$value["seconds"].'秒';
        Return $t;

    }else{
        return (bool) FALSE;
    }
}
    /**
     * 订单详情
     * @param order_number订单号
     * @param user_type 用户类型：0-游客，1-导游，2-整体
     * @param server_type 服务类型：0-即时，1-预约
     */
    public function order_detail() {
        if (IS_GET) {
            $order_number = I('order_number');
            $user_type = I('user_type', 0);
            $now_time = time();
            $detail = $this->get_order_info($order_number, $user_type);
            if($detail['cancel_time']){
                $cancel =  $detail['pay_money'];
            }
            if($detail['guide_type'] == 1){
                $result = M('guide_member_info as gmi')
                    ->join('__TOURIST_AREA__ as ta on ta.id = gmi.tourist_id')
                    ->join('__ORDER__ as o on o.guid = gmi.uid')
                    ->field('ta.tourist_name')
                    ->where('o.order_number ='.$order_number)
                    ->find();
            }
            $tag = M('comment as c')->field('c.content,c.tag')->where('c.order_number ='.$order_number)->find();
            $msg = M('order_manage as om')->field('om.action_msg')->where('om.link_id ='.$order_number.' and action_type = 1')->order('om.action_time desc')->find();
            if($msg){
                $msg_is = 1;
            }
            if($detail['server_start_time'] && $detail['server_end_time']){
                $time = $detail['server_end_time'] - $detail['server_start_time'];
            }elseif($detail['server_start_time']  && empty($detail['server_end_time'])){
                $time = $now_time - $detail['server_start_time'];
            }else{
                $time = '-';
            }
           if($time){
               $server_time = $this->Sec2Time($time);
            }
            if($detail['guide_confirm_order_time']){
                $detail['guide_confirm_order_time'] = date('Y-m-d H:i:s',$detail['guide_confirm_order_time']);
            }
            if($detail['order_time']){
                $detail['order_time'] = date('Y-m-d H:i:s',$detail['order_time']);
            }
            if($detail['pay_time']){
                $detail['pay_time'] = date('Y-m-d H:i:s',$detail['pay_time']);
            }
            if($detail['server_start_time']){
                $detail['server_start_time'] = date('Y-m-d H:i:s',$detail['server_start_time']);
            }
            if($detail['server_end_time']){
                $detail['server_end_time'] = date('Y-m-d H:i:s',$detail['server_end_time']);
            }
            $this->assign('cancel', $cancel);
            $this->assign('msg', $msg);
            $this->assign('msg_is', $msg_is);
            $this->assign('detail', $detail);
            $this->assign('result', $result);
            $this->assign('server_time', $server_time);
            $this->meta_title = '订单详情';
            $this->display();
        }
    }

    /**
     * 实名认证
     */
    public function realname_confirm() {
        $uid = I('uid');
        $type = I('type'); //-1不通过，1通过
        $time = time();
        $user_id = is_login();
        $type_confirm = 2;
        switch (I('guide_type')){
            case 1: $guide_type='讲解员';  break;
            case 2: $guide_type='领路人';  break;
        }

        if($type == -1 && IS_GET){
            $this->meta_title = '资料审核';
            $this->check = array(
                'sms'   =>  array(
                    '/(ㄒoㄒ)/~~亲！你提交的'.$guide_type.'注册申请未通过审核！',
                    '为你带来不便请谅解，途途导由期待你的加入！'
                ),
                'msg'   =>  get_config_key_value(C('setting.CHECK_NOW_PASS_MSG')),
            );
            $this->display('confirm_why');
            return;
        }
        if($type == 1){
            $check_result = 1;
            $array = array(
                'action_manage_id'   =>$user_id,
                'link_id'       =>$uid,
                'action_type'   =>$type_confirm,
                'action_time'   =>$time,
                'check_result'  =>$check_result,
            );
            $model = M('order_manage')->add($array);
            if(!$model){
                return false;
            }
        }
        if($type == -1 && IS_POST){
            $check_result = -1;
            $msg = isset($_POST['msg']) ? get_config_key_value(C('setting.CHECK_NOW_PASS_MSG'),I('msg')) : I('other');
            $arr = array(
                'link_id'       =>$uid,
                'action_manage_id'   =>$user_id,
                'action_time'   =>NOW_TIME,
                'action_msg'    =>$msg,
                'action_type'   =>$type_confirm,
                'check_result'  =>$check_result,
            );
            $result = M('order_manage')->add($arr);
            if(!$result){
                return false;
            }
        }
        $content = '/(ㄒoㄒ)/~~亲！你提交的'.$guide_type.'注册申请未通过审核！'.$msg.'，为你带来不便请谅解，途途导由期待你的加入！';
        $result = M('guide_member_info')->where('uid=' . $uid)->setField('is_auth', $type);
        if ($result) {
            if ($type == 1) {
                $invite_code = randomIntkeys(8, 5);
                $map['invite_code'] = $invite_code;
                $count = M('guide_member_info')->where($map)->count();
                while ($count > 0) {
                    $invite_code = randomIntkeys(8, 5);
                    $where['invite_code'] = $invite_code;
                    $count = M('guide_member_info')->where($where)->count();
                }
                $res = M('guide_member_info')->where('uid=' . $uid)->setField('invite_code', $invite_code);
                $content = '^v^亲！您提交的'.$guide_type.'注册申请已通过审核！您可马上登录途途导由服务端开始接单啦！';
            }
            if($mobile = M('guide_member')->getFieldById($uid,'mobile'))
                send_phone_code($mobile,$content);
            $this->success('操作成功','',true);
        } else {
            $this->error('操作失败','',true);
        }
    }

    private function get_order_status($server_type = 0, $server_status, $pay_status) {
        //订单状态处理
        switch ($server_type) {
            case 0://即时服务
                if ($server_status == 0 && $pay_status == 0)//待处理订单
                    $order_status = 0;
                if ($server_status == 1 && $pay_status == 0)//已接单，未开始
                    $order_status = 1;
                if ($server_status == 3 && $pay_status == 0)//服务已开始
                    $order_status = 2;
                if ($server_status == 4 && $pay_status == 0)//服务已结束，未付款
                    $order_status = 3;
                if ($server_status == 4 && $pay_status == 1)//已支付，未评论
                    $order_status = 4;
                if ($server_status == 4 && $pay_status == 2)//订单已结束
                    $order_status = 5;
                if ($server_status == 2 && $pay_status == 0)//已取消，未支付
                    $order_status = 6;
                if ($server_status == 2 && $pay_status == 2)//已关闭（已取消并支付违约金）
                    $order_status = 7;
                break;
            case 1://预约服务
                if ($server_status == 0 && $pay_status == 0)//待支付
                    $order_status = 0;
                if ($server_status == 0 && $pay_status == 1)//待处理订单
                    $order_status = 1;
                if ($server_status == 1 && $pay_status == 1)//已接单，未开始
                    $order_status = 2;
                if ($server_status == 3 && $pay_status == 1)//服务已开始
                    $order_status = 3;
                if ($server_status == 4 && $pay_status == 1)//服务已结束,未评论
                    $order_status = 4;
                if ($server_status == 4 && $pay_status == 2)//已完成
                    $order_status = 5;
                if ($server_status == 2 && $pay_status == 1)//已取消，退款进行中
                    $order_status = 6;
                if ($server_status == 2 && $pay_status == 2)//已关闭（已取消并退款完成）
                    $order_status = 7;
                if ($server_status == 5 && $pay_status == 1)//已关闭（已取消并退款完成）
                    $order_status = 8;
                if ($server_status == 5 && $pay_status == 2)//已关闭（已取消并退款完成）
                    $order_status = 9;
                break;
        }
        return $order_status;
    }

    //获取订单详情
    private function get_order_info($order_number, $user_type = 0) {
        if ($user_type == 1) {
            $info = M()
                    ->table('yy_order as o')
                    ->field('o.guid as uid,o.derate_money,m.nickname,o.pay_time,o.guide_confirm_order_time,py.pay_account,m.head_image,py.pay_channel,oi.oi.guide_type,charging_type,um.mobile,o.order_number,o.server_status,o.pay_status,o.order_time,o.cancel_time,o.order_money,o.rebate_money,o.tip_money,o.pay_money,o.pay_type,oi.server_price,oi.server_start_time,oi.server_end_time,oi.start_addr,oi.end_addr,oi.server_type,c.content,c.star,c.tag')
                    ->join('left join yy_order_info as oi on o.order_number=oi.order_number')
                    ->join('left join yy_comment as c on c.order_number=o.order_number')
                    ->join('left join yy_user_member as um on um.id=o.uuid')
                    ->join('left join __PAY__ as py on py.order_number=o.order_number')
                    ->join('left join yy_user_member_info as m on m.uid=o.uuid')
                    ->where('o.order_number=' . $order_number)
                    ->find();
        } elseif ($user_type == 0) {
            $info = M()
                    ->table('yy_order as o')
                    ->field('gmi.realname,o.cancel_time,o.derate_money,gmi.stars,umi.nickname as um_nickname,umi.phone as um_phone,oi.end_addr,oi.start_addr,o.guide_confirm_order_time,py.pay_channel,py.pay_account,o.pay_time,oi.charging_type,gmi.head_image as guide_head_image,gmi.guide_type,gm.mobile,o.order_number,o.order_time,o.server_status,o.pay_status,o.order_money,o.rebate_money,oi.server_price,o.tip_money,o.pay_money,o.pay_type,o.cancel_time,oi.server_start_time,oi.server_end_time,oi.server_type as order_server_type,gmi.server_type,c.content,c.star,c.tag')
                    ->join('left join yy_order_info as oi on oi.order_number=o.order_number')
                    ->join('left join __USER_MEMBER_INFO__ as umi on umi.uid=o.uuid')
                    ->join('left join yy_comment as c on c.order_number=o.order_number')
                    ->join('left join yy_guide_member as gm on gm.id=o.guid')
                    ->join('left join __PAY__ as py on py.order_number=o.order_number')
                    ->join('left join yy_guide_member_info as gmi on gmi.uid=o.guid')
                    ->where('o.order_number=' . $order_number)
                    ->find();
        } elseif ($user_type == 2) {
            $info = M()
                    ->table('yy_order as o')
                    ->field('gmi.realname as guide_realname,gmi.stars,o.derate_money,oi.end_addr,oi.start_addr,o.guide_confirm_order_time,py.pay_channel,py.pay_account,o.pay_time,oi.charging_type,gmi.head_image as guide_head_image,gmi.guide_type,o.order_time,gm.mobile as guide_mobile,um.mobile as user_mobile,umi.nickname as user_realname,oi.server_price,umi.head_image as user_head_image,o.order_number,o.server_status,o.pay_status,o.order_money,o.rebate_money,o.tip_money,o.pay_money,o.pay_type,o.cancel_time,oi.server_start_time,oi.server_end_time,oi.server_type,c.content,c.star,c.tag')
                    ->join('left join yy_order_info as oi on oi.order_number=o.order_number')
                    ->join('left join yy_comment as c on c.order_number=o.order_number')
                    ->join('left join yy_guide_member as gm on gm.id=o.guid')
                    ->join('left join yy_guide_member_info as gmi on gmi.uid=o.guid')
                    ->join('left join yy_user_member as um on um.id=o.uuid')
                    ->join('left join __PAY__ as py on py.order_number=o.order_number')
                    ->join('left join yy_user_member_info as umi on umi.uid=o.uuid')
                    ->where('o.order_number=' . $order_number)
                    ->find();
            $info['guide_head_image'] = get_pic_url() . $info['guide_head_image'];
            $info['user_head_image'] = get_pic_url() . $info['user_head_image'];
        }
        if ($info) {
            //订单取消时间
            $cancel_time = $info['cancel_time'];
            if (!empty($cancel_time)) {
                $info['cancel_time'] = date('m月d日H:i', $cancel_time);
            }
            //订单服务时间处理
            $diff_time = bcsub($info['server_end_time'], $info['server_start_time']);
            $hours = bcdiv($diff_time, 3600);
            $mins = bcdiv(bcsub($diff_time, $hours * 3600), 60);
            $info['server_date'] = $hours . '小时' . $mins . '分';
            if ($info['server_type'])//预约服务
                $info['server_date'] = bcdiv(bcsub($info['server_end_time'], $info['server_start_time']), 3600 * 24) + 1;

            $info['head_image'] = get_pic_url() . $info['head_image'];
            $info['order_status'] = orderController::order_status($info['server_status'], $info['pay_status']);
            unset($info['server_status'], $info['pay_status']);
            if ($info['tag']) {
                $info['tag'] = unserialize($info['tag']);
                $info['tag'] = implode(',', $info['tag']);
            }
            $info['user_type'] = $user_type;
            return $info;
        }
        return FALSE;
    }

    public function order_list() {
        if (IS_GET) {
            $order_status = I('order_status');
            $now_page = I('get.page', 1);
            $page_size = I('pagesize', 20);
            $map['oi.server_type'] = 0;
            $guide_type = I('get.guide_type');
            //$is_auth = I('get.is_auth');
            $key = I('get.key');
            $start_time = I('get.start_time');
            $end_time = I('get.end_time');
            $time = time();
            $time_type = I('time_type');
            if(empty($time_type)){
                $time_type = 2;
            }
                switch($order_status){
                    case 0:
                        $server_status = 0;
                        $pay_status = 0;
                        break;
                    case 1:
                        $server_status = 1;
                        $pay_status = 0;
                        break;
                    case 2:
                        $server_status = 3;
                        $pay_status = 0;
                        break;
                    case 3:
                        $server_status = 4;
                        $pay_status = 0;
                        break;
                    case 4:
                        $server_status = 6;
                        $pay_status = 0;
                        break;
                    case 5:
                        $server_status = 6;
                        $pay_status = 1;
                        break;
                    case 6:
                        $server_status = 6;
                        $pay_status = 2;
                        break;
                    case 7:
                        $server_status = 2;
                        $pay_status = 0;
                        break;
                    case 8:
                        $server_status = 2;
                        $pay_status = 2;
                        break;
                    case 9:
                        $server_status = 7;
                        $pay_status = 0;
                        break;
                    case 10:
                        $server_status = 8;
                        $pay_status = 2;
                        break;
                }
            switch($time_type){
                case 1:
                    $end_time = date('Y-m-d',$time);
                    $start_time = date('Y-m-d',$time);
                    break;
                case 2:
                    $end_time = date('Y-m-d',$time);
                    $start_time = date('Y-m-d',strtotime('-7 day'));
                    break;
                case 3:
                    $start_time = date('Y-m-d',strtotime('-1 month'));
                    $end_time = date('Y-m-d',$time);
                    break;
                case 4:
                    $start_time = date('Y-m-d',strtotime('-3 month'));
                    $end_time = date('Y-m-d',$time);
                    break;
                case -1:
                    $start_time = I('get.start_time');
                    $end_time = I('get.end_time');
                    break;
            }
            if(!empty($key)){
               $map['_complex'] = array(
                   '_logic' => 'or',
                   'o.order_number' =>  array('like','%'.$key.'%'),
                   'um.mobile' =>  array('like','%'.$key.'%'),
               );
            }
           if(!empty($order_status)){
               $map['o.server_status'] = $server_status;
                $map['o.pay_status'] = $pay_status;
           }
            if(!empty($guide_type) && in_array($guide_type,array('1','2')))
                $map['oi.guide_type'] = $guide_type;
            if(!empty($start_time))
                $map['o.order_time'] = array('egt',strtotime($start_time));
            if(!empty($end_time))
                $map['o.order_time'] = array('elt',strtotime($end_time));
            if(!empty($start_time) && !empty($end_time))
                $map['o.order_time'] = array(
                    array('egt',strtotime($start_time)),
                    array('elt',strtotime($end_time)),
                );
            $count = M()
                    ->table('__ORDER__ as o')
                    ->join('left join __ORDER_INFO__ as oi on oi.order_number=o.order_number')
                    ->join('left join __USER_MEMBER__ as um on um.id=o.uuid')
                    ->join('left join __USER_MEMBER_INFO__ as umi on umi.uid=o.uuid')
                    ->join('left join __GUIDE_MEMBER__ as gm on gm.id=o.guid')
                    ->where($map)
                    ->count();
            $pages = intval(ceil($count / $page_size));
            $list = M()
                    ->table('__ORDER__ as o')
                    ->join('left join __ORDER_INFO__ as oi on oi.order_number=o.order_number')
                     ->join('left join __USER_MEMBER__ as um on um.id=o.uuid')
                    ->join('left join __USER_MEMBER_INFO__ as umi on umi.uid=o.uuid')
                    ->join('left join __GUIDE_MEMBER__ as gm on gm.id=o.guid')
                    ->field('o.pay_money,o.pay_status,o.order_number,oi.guide_type,oi.charging_type,o.pay_money,o.server_status,o.pay_status,o.order_time,oi.end_addr,oi.server_type,um.mobile as user_mobile,umi.nickname,gm.mobile as guide_mobile')
                    ->where($map)
                    ->page($now_page, $page_size)
                    ->order('o.order_time desc')
                    ->select();
            if ($list) {
                foreach ($list as $key => &$val) {
                    if (!empty($val['order_time'])) {
                        $val['order_time'] = date('Y-m-d H:i:s', $val['order_time']);
                        if($val['pay_money'] == 0.00){
                            $val['pay_money'] = '-';
                        }else{
                            $val['pay_money'] = '￥'.$val['pay_money'];
                        }
                    }
                    $val['order_status'] = OrderController::order_status( $val['server_status'], $val['pay_status']);
                }
            }
            $list = array(
                'list'  =>  $list,
                'pages' =>  $pages,
                'get'   =>  $_GET,
            );
          if($list){
              $this->assign("time_type",$time_type);
              $this->assign('list', $list);
              $this->meta_title = '订单列表';
              $this->display();
          }
        }
    }
    /**
     * 讲解员Excel导出
     */
    public function export_excel(){
        $is_auth = I('get.is_auth');
        $key = I('get.key');
        $is_online = I('get.is_online');
        $start_time = I('get.start_time');
        $end_time = I('get.end_time');
        $time = time();
        $time_type = I('time_type');
        if(empty($time_type)){
            $time_type = 2;
        }
        switch($time_type){
            case 1:
                $end_time = date('Y-m-d',$time);
                $start_time = date('Y-m-d',$time);
                break;
            case 2:
                $end_time = date('Y-m-d',$time);
                $start_time = date('Y-m-d',strtotime('-7 day'));
                break;
            case 3:
                $start_time = date('Y-m-d',strtotime('-1 month'));
                $end_time = date('Y-m-d',$time);
                break;
            case 4:
                $start_time = date('Y-m-d',strtotime('-3 month'));
                $end_time = date('Y-m-d',$time);
                break;
            case -1:
                $start_time = I('get.start_time');
                $end_time = I('get.end_time');
                break;
        }
        if(!empty($key)){
            $where = array(
                '_logic' => 'or',
                'gm.mobile' =>  array('like','%'.$key.'%'),
                'gmi.realname'  =>  array('like','%'.$key.'%'),
            );
            $map['_complex'] = $where;
        }
        if(!empty($is_online) && in_array($is_online,array('0','1','2'))){
            $map['gmi.is_online'] = $is_online;
        }
        if(isset($is_auth) && is_numeric($is_auth) && in_array($is_auth,array('-1','0','1'))){
            $map['gmi.is_auth'] = $is_auth;
        }else{
            $map['gmi.is_auth'] = array('neq','');
        }
        if(!empty($start_time))
            $map['gm.reg_time'] = array('egt',strtotime($start_time));
        if(!empty($end_time))
            $map['gm.reg_time'] = array('elt',strtotime($end_time));
        if(!empty($start_time) && !empty($end_time))
            $map['gm.reg_time'] = array(
                array('egt',strtotime($start_time)),
                array('elt',strtotime($end_time.' 23:59:59')),
            );
        $map['gmi.guide_type'] = 1;
        $OrdersData = M('guide_member_info as gmi')
            ->join('__GUIDE_MEMBER__ as gm on gmi.uid=gm.id')
            ->join('__TOURIST_AREA__ as ta on gmi.tourist_id=ta.id')
            ->field('gm.mobile,ta.tourist_name,gmi.realname,gmi.guide_type,gmi.now_total_amount,gmi.reg_time')
            ->where($map)
            ->order('gmi.reg_time desc')
            ->select();
        foreach($OrdersData as &$val){
            $val['reg_time'] = date('Y-m-d H:i:s',$val['reg_time']);
            if($val['guide_type']==1){
                $val['guide_type'] = "讲解员";
            }else{
                $val['guide_type'] = "领路人";
            }
        }
        vendor('PHPExcel.PHPExcel', VENDOR_PATH, '.php');

        // Create new PHPExcel object
        $objPHPExcel = new \PHPExcel();
        // Set properties
        $objPHPExcel->getProperties()->setCreator("ctos")
            ->setLastModifiedBy("ctos")
            ->setTitle("Office 2007 XLSX Test Document")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
            ->setKeywords("office 2007 openxml php")
            ->setCategory("Test result file");

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);

        //  sheet命名
        //$objPHPExcel->getActiveSheet()->setTitle('入账表');

        //合并cell
        $objPHPExcel->getActiveSheet()->mergeCells('A1:E1');
        $objPHPExcel->getActiveSheet()->setCellValue("A1",'讲解员表'.$start_time.'--'.$end_time);
        //set width
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(8);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);

        //设置行高度
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(30);

        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(30);

        //set font size bold
        $objPHPExcel->getActiveSheet()->getStyle('A1:E1')->getFont()->setSize(18);
        $objPHPExcel->getActiveSheet()->getStyle('A1:E1')->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getDefaultStyle()->getFont()->setSize(12);
        $objPHPExcel->getActiveSheet()->getStyle('A2:E2')->getFont()->setBold(true);

        $objPHPExcel->getActiveSheet()->getStyle('A1:E1')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A1:E1')->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);
        $objPHPExcel->getActiveSheet()->getStyle('A2:E2')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A2:E2')->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);

        //设置水平居中
        $objPHPExcel->getActiveSheet()->getStyle('A')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('B')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('C')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('D')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('E')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        // set table header content
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A2', '手机号')
            ->setCellValue('B2', '真实姓名')
            ->setCellValue('C2', '导游类型')
            ->setCellValue('D2', '账户余额')
            ->setCellValue('E2', '服务景区');

        //设置setActiveSheetIndex水平居中
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('A2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('B2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('C2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('D2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('E2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('A1')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        // Miscellaneous glyphs, UTF-8
        for($i=0;$i<count($OrdersData);$i++){
            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit('A'.($i+3), $OrdersData[$i]['mobile']);
            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit('B'.($i+3), $OrdersData[$i]['realname']);
            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit('C'.($i+3), $OrdersData[$i]['guide_type']);
            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit('D'.($i+3), $OrdersData[$i]['now_total_amount']);
            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit('E'.($i+3), $OrdersData[$i]['tourist_name']);
            $objPHPExcel->getActiveSheet()->getStyle('A'.($i+3).':E'.($i+3))->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A'.($i+3).':E'.($i+3))->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);
            $objPHPExcel->getActiveSheet()->getRowDimension($i+3)->setRowHeight(16);
        }

        // excel头参数
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="讲解员表('.date('Ymd-His').').xls"');  //日期为文件名后缀
        header('Cache-Control: max-age=0');

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');  //excel5为xls格式，excel2007为xlsx格式
        $objWriter->save('php://output');

    }
    /**
     * 领路人Excel导出
     */
    public function export_excels(){
        $is_auth = I('get.is_auth');
        $key = I('get.key');
        $is_online = I('get.is_online');
        $start_time = I('get.start_time');
        $end_time = I('get.end_time');
        $time = time();
        $time_type = I('time_type');
        if(empty($time_type)){
            $time_type = 2;
        }
        switch($time_type){
            case 1:
                $end_time = date('Y-m-d',$time);
                $start_time = date('Y-m-d',$time);
                break;
            case 2:
                $end_time = date('Y-m-d',$time);
                $start_time = date('Y-m-d',strtotime('-7 day'));
                break;
            case 3:
                $start_time = date('Y-m-d',strtotime('-1 month'));
                $end_time = date('Y-m-d',$time);
                break;
            case 4:
                $start_time = date('Y-m-d',strtotime('-3 month'));
                $end_time = date('Y-m-d',$time);
                break;
            case -1:
                $start_time = I('get.start_time');
                $end_time = I('get.end_time');
                break;
        }
        if(!empty($key)){
            $where = array(
                '_logic' => 'or',
                'gm.mobile' =>  array('like','%'.$key.'%'),
                'gmi.realname'  =>  array('like','%'.$key.'%'),
            );
            $map['_complex'] = $where;
        }
        if(!empty($is_online) && in_array($is_online,array('0','1','2'))){
            $map['gmi.is_online'] = $is_online;
        }
        if(isset($is_auth) && is_numeric($is_auth) && in_array($is_auth,array('-1','0','1'))){
            $map['gmi.is_auth'] = $is_auth;
        }else{
            $map['gmi.is_auth'] = array('neq','');
        }
        if(!empty($start_time))
            $map['gm.reg_time'] = array('egt',strtotime($start_time));
        if(!empty($end_time))
            $map['gm.reg_time'] = array('elt',strtotime($end_time));
        if(!empty($start_time) && !empty($end_time))
            $map['gm.reg_time'] = array(
                array('egt',strtotime($start_time)),
                array('elt',strtotime($end_time.' 23:59:59')),
            );
        $map['gmi.guide_type'] = 2;
        $OrdersData = M('guide_member as gm')
            ->join('left join yy_guide_member_info as gmi on gmi.uid=gm.id')
            ->field('gm.mobile,gmi.realname,gmi.guide_type,gmi.now_total_amount,gmi.reg_time')
            ->where($map)
            ->order('gmi.reg_time desc')
            ->select();
        foreach($OrdersData as &$val){
            $val['reg_time'] = date('Y-m-d H:i:s',$val['reg_time']);
            if($val['guide_type']==1){
                $val['guide_type'] = "讲解员";
            }else{
                $val['guide_type'] = "领路人";
            }
        }
        vendor('PHPExcel.PHPExcel', VENDOR_PATH, '.php');

        // Create new PHPExcel object
        $objPHPExcel = new \PHPExcel();
        // Set properties
        $objPHPExcel->getProperties()->setCreator("ctos")
            ->setLastModifiedBy("ctos")
            ->setTitle("Office 2007 XLSX Test Document")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
            ->setKeywords("office 2007 openxml php")
            ->setCategory("Test result file");

        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);

        //  sheet命名
        //$objPHPExcel->getActiveSheet()->setTitle('入账表');

        //合并cell
        $objPHPExcel->getActiveSheet()->mergeCells('A1:D1');
        $objPHPExcel->getActiveSheet()->setCellValue("A1",'领路人表'.$start_time.'--'.$end_time);
        //set width
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(8);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);

        //设置行高度
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(30);

        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(30);

        //set font size bold
        $objPHPExcel->getActiveSheet()->getStyle('A1:D1')->getFont()->setSize(18);
        $objPHPExcel->getActiveSheet()->getStyle('A1:D1')->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getDefaultStyle()->getFont()->setSize(12);
        $objPHPExcel->getActiveSheet()->getStyle('A2:D2')->getFont()->setBold(true);

        $objPHPExcel->getActiveSheet()->getStyle('A1:D1')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A1:D1')->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);
        $objPHPExcel->getActiveSheet()->getStyle('A2:D2')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A2:D2')->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);

        //设置水平居中
        $objPHPExcel->getActiveSheet()->getStyle('A')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('B')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('C')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('D')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        // set table header content
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A2', '手机号')
            ->setCellValue('B2', '真实姓名')
            ->setCellValue('C2', '导游类型')
            ->setCellValue('D2', '账户余额');


        //设置setActiveSheetIndex水平居中
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('A2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('B2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('C2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('D2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->setActiveSheetIndex(0)->getStyle('A1')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        // Miscellaneous glyphs, UTF-8
        for($i=0;$i<count($OrdersData);$i++){
            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit('A'.($i+3), $OrdersData[$i]['mobile']);
            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit('B'.($i+3), $OrdersData[$i]['realname']);
            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit('C'.($i+3), $OrdersData[$i]['guide_type']);
            $objPHPExcel->getActiveSheet(0)->setCellValueExplicit('D'.($i+3), $OrdersData[$i]['now_total_amount']);
            $objPHPExcel->getActiveSheet()->getStyle('A'.($i+3).':D'.($i+3))->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A'.($i+3).':D'.($i+3))->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);
            $objPHPExcel->getActiveSheet()->getRowDimension($i+3)->setRowHeight(16);
        }

        // excel头参数
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="领路人表('.date('Ymd-His').').xls"');  //日期为文件名后缀
        header('Cache-Control: max-age=0');

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');  //excel5为xls格式，excel2007为xlsx格式
        $objWriter->save('php://output');

    }
}
