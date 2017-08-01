<?php
/**
 * Created by PhpStorm.
 * User: wt
 * Date: 2016/7/28
 * Time: 14:31
 */
namespace Manage\Controller;
class FinanceController extends AdminController{

    public function _initialize(){
        parent::_initialize();
        $this->group_title = '财务管理';
    }

    /**
     * 入账列表
     */
    public function finance_show(){
        $tourist_id = I('get.tourist_id');
        $now_page = I('get.page', 1);
        $page_size = 20;
        $guide_type = I('get.guide_type');
        //$is_auth = I('get.is_auth');
        $key = I('get.key');
        $time = time();
        $time_type = I('time_type');
        if(!empty($key)){
            $where = array(
                '_logic' => 'or',
                'gm.mobile' =>  array('like','%'.$key.'%'),
                'o.order_number'  =>  array('like','%'.$key.'%'),
            );
            $map['_complex'] = $where;
        }
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
        if(!empty($tourist_id))
            $map['gmi.tourist_id'] = $tourist_id;
        if(!empty($guide_type) && in_array($guide_type,array('1','2')))
            $map['gmi.guide_type'] = $guide_type;
        if(!empty($start_time)){
            $map['a.deal_time'] = array('egt',strtotime($start_time));
        }
        if(!empty($end_time)){
            $map['a.deal_time'] = array('elt',strtotime($end_time));
        }
        if(!empty($start_time) && !empty($end_time)){
            $map['a.deal_time'] = array(
                array('egt',strtotime($start_time)),
                array('elt',strtotime($end_time)),
            );
        }
        $map['a.deal_type'] = 1;
        $map['py.is_pay'] = 1;
        $Model = M('amount as a');
        $count = $Model
            ->join('__PAY__  as py on py.request_pay_no = a.order_number')
            ->join('__ORDER__ as o on o.order_number = py.order_number')
            ->join('__USER_MEMBER__ as um on um.id = o.uuid')
            ->join('__GUIDE_MEMBER__ as gm on gm.id = a.uid')
            ->join('__GUIDE_MEMBER_INFO__ as gmi on a.uid = gmi.uid')
            /*->join('__TOURIST_AREA__ as ta on ta.id = gmi.tourist_id')*/
            ->where($map)
            ->count();
        $list = $Model
            ->join('__PAY__  as py on py.request_pay_no = a.order_number')
            ->join('__ORDER__ as o on o.order_number = py.order_number')
            ->join('__USER_MEMBER__ as um on um.id = o.uuid')
            ->join('__GUIDE_MEMBER__ as gm on gm.id = a.uid')
            ->join('__GUIDE_MEMBER_INFO__ as gmi on a.uid = gmi.uid')
            ->where($map)
            ->field('a.deal_time,a.child_id,a.profit_amount,a.manage_amount,py.request_pay_no,gmi.tourist_id,um.mobile as phone,o.guid as uid,gm.mobile,o.rebate_money,o.derate_money,o.pay_money,o.rebate_money,o.tip_money,o.order_money,gmi.realname,gmi.guide_type,o.order_number,py.pay_time,py.is_pay,py.pay_channel')
            ->order('py.pay_time desc')
            ->page($now_page, $page_size)
            ->select();
        foreach($list as &$val){
            $amount_info = $this->get_amount_info($val['uid'],$val['deal_time']);
            $val['total_money'] = $amount_info['total_money'];
            $val['pay_time']    = date('Y-m-d H:i:s',$val['pay_time']);
            $tourist_name = $this->tourist_area($val['tourist_id']);
            $val['tourist_name'] = $tourist_name['tourist_name'];
            $val['one_money'] = $val['profit_amount'];
            $val['proportion'] = (bcdiv($val['manage_amount'],$val['order_money']-$val['derate_money'],2)*100).'%';
            if( $val['tourist_name']){
                $val['tourist_names'] = $val['tourist_name'];
            }else{
                $val['tourist_names'] = '-';
            }
            if($val['child_id'] != 0){
                $val['rebate'] = $val['profit_amount'];
                $val['one_money'] = '-';
                $val['proportion'] = '';
                $val['pay_money'] = $val['rebate'];
                $val['tip_money'] = '-';
                $val['order_money'] = '-';
                $val['one_money'] = $val['rebate'];
                $val['manage_amount'] = '-';
                $val['rebate_money'] = '-';
                $val['proportion'] = '-';
                $val['derate_money'] = '-';
                $val['proportion'] = '-';
            }else{
                $val['rebate'] = '-';
            }
        }
         if(!empty($tourist_id)){
           $tourist_name = M('tourist_area as ta')->field('ta.tourist_name')->where('ta.id ='.$tourist_id)->find();
           if($tourist_name){
               $tourists_name = 0;
           }
       }
        $result = M('tourist_area as ta')->field('ta.tourist_name,ta.id')->select();
        $pages = intval(ceil($count / $page_size));
        $info = array(
            'list'  =>  $list,
            'pages' =>  $pages,
            'get'   =>  $_GET,
        );
        if($info){
            $this->assign("result",$result);
            $this->assign("tourists_name",$tourists_name);
            $this->assign("tourist_name",$tourist_name);
            $this->assign("time_type",$time_type);
            $this->assign("list",$info);
            $this->meta_title = '财务入账列表';
            $this->display();
        }
    }

    /**
     * 出账列表
     */
    public function finance_out(){
        $now_page = I('get.page', 1);
        $page_size = 20;
        $guide_type = I('get.guide_type');
        $is_withdraw = I('get.is_withdraw');
        $key = I('get.key');
        $start_time = I('get.start_time');
        $end_time = I('get.end_time');
        $time = time();
        $time_type = I('time_type');
        if(!empty($key)){
            $where = array(
                '_logic' => 'or',
                'gmi.nickname' =>  array('like','%'.$key.'%'),
                'gmi.realname'  =>  array('like','%'.$key.'%'),
            );
            $map['_complex'] = $where;
        }
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
        if(!empty($guide_type) && in_array($guide_type,array('1','2')))
            $map['gmi.guide_type'] = $guide_type;
        if(!empty($is_withdraw) && in_array($is_withdraw,array('-1','0','1','2')))
            $map['gw.is_withdraw'] = $is_withdraw;
        if(!empty($start_time))
            $map['gw.request_time'] = array('egt',strtotime($start_time));
        if(!empty($end_time))
            $map['gw.request_time'] = array('elt',strtotime($end_time));
        if(!empty($start_time) && !empty($end_time))
            $map['gw.request_time'] = array(
                array('egt',strtotime($start_time)),
                array('elt',strtotime($end_time)),

            );
        $Model = M('guide_member_info as gmi');
        $count = $Model
            ->join('__GUIDE_WITHDRAW__ as gw on gw.uid = gmi.uid')
            ->join('__GUIDE_BANK_INFO__ as gbi on gbi.uid = gmi.uid')
            ->where($map)
            ->count();
        $list = $Model
            ->field('gmi.uid,gmi.phone,gw.bankcard_username,gmi.realname,gmi.guide_type,gbi.bank_name,gmi.now_total_amount,gw.order_number,gw.bankcard_number,gw.bank_number,gw.request_time,gw.is_withdraw,gw.withdraw_amount')
            ->join('__GUIDE_WITHDRAW__  as gw on gw.uid = gmi.uid')
            ->join('__GUIDE_BANK_INFO__ as gbi on gbi.uid = gmi.uid')
            ->where($map)
            ->order('gw.request_time desc')
            ->page($now_page, $page_size)
            ->select();
        foreach($list as &$val){
            $val['request_time']    = date('Y-m-d H:i:s',$val['request_time']);
        }
        $pages = intval(ceil($count / $page_size));
        $info = array(
            'list'  =>  $list,
            'pages' =>  $pages,
            'get'   =>  $_GET,
        );
        if($info){
            $this->assign("time_type",$time_type);
            $this->assign("list",$info);
            $this->meta_title = '财务出账列表';
            $this->display();
        }

    }

    /**
     * 导出
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    public function export_excel(){
        $tourist_id = I('get.tourist_id');
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
        if(!empty($tourist_id))
            $map['gmi.tourist_id'] = $tourist_id;
        if(!empty($start_time))
            $map['py.pay_time'] = array('egt',strtotime($start_time));
        if(!empty($end_time))
            $map['py.pay_time'] = array('elt',strtotime($end_time));
        if(!empty($start_time) && !empty($end_time))
            $map['py.pay_time'] = array(
                array('egt',strtotime($start_time)),
                array('elt',strtotime($end_time)),
            );
        $Model = M('amount as a');
        $map['a.deal_type'] = 1;
        $map['py.is_pay'] = 1;
        //查询数据得到$OrdersData二维数组
        $OrdersData = $Model
            ->join('__PAY__  as py on py.request_pay_no = a.order_number')
            ->join('__ORDER__ as o on o.order_number = py.order_number')
            ->join('__USER_MEMBER__ as um on um.id = o.uuid')
            ->join('__GUIDE_MEMBER__ as gm on gm.id = a.uid')
            ->join('__GUIDE_MEMBER_INFO__ as gmi on a.uid = gmi.uid')
            ->where($map)
            ->field('a.deal_time,a.child_id,o.derate_money,a.profit_amount,a.manage_amount,py.request_pay_no,gmi.tourist_id,um.mobile as phone,o.guid as uid,gm.mobile,o.rebate_money,o.pay_money,o.rebate_money,o.tip_money,o.order_money,gmi.realname,gmi.guide_type,o.order_number,py.pay_time,py.is_pay,py.pay_channel')
            ->order('py.pay_time desc')
            ->select();

        foreach($OrdersData as &$val){
            switch($val['guide_type']){
                case 1:
                    $val['guide_type'] = "讲解员";
                    break;
                case 2:
                    $val['guide_type'] = "领路人";
                    break;
            }
            switch($val['pay_channel']){
                case 1:
                    $val['pay_channel'] = "支付宝";
                    break;
                case 2:
                    $val['pay_channel'] = "微信";
                    break;
            }
                $amount_info = $this->get_amount_info($val['uid'],$val['deal_time']);
                $val['total_money'] = $amount_info['total_money'];
                $val['pay_time']    = date('Y-m-d H:i:s',$val['pay_time']);
                $tourist_name = $this->tourist_area($val['tourist_id']);
                $val['tourist_name'] = $tourist_name['tourist_name'];
                $val['one_money'] = $val['profit_amount'];
                $val['proportion'] = (bcdiv($val['manage_amount'],$val['order_money']-$val['derate_money'],2)*100).'%';
                if( $val['tourist_name']){
                    $val['tourist_names'] = $val['tourist_name'];
                }else{
                    $val['tourist_names'] = '-';
                }
                if($val['child_id'] != 0){
                    $val['rebate'] = $val['profit_amount'];
                    $val['one_money'] = '-';
                    $val['proportion'] = '';
                    $val['pay_money'] = $val['rebate'];
                    $val['tip_money'] = '-';
                    $val['order_money'] = '-';
                    $val['one_money'] = $val['rebate'];
                    $val['manage_amount'] = '-';
                    $val['rebate_money'] = '-';
                    $val['derate_money'] = '-';
                    $val['proportion'] = '-';
                }else{
                    $val['rebate'] = '-';
                }
        }
        //dump($OrdersData);die;
        $cellName = array(
            array('order_number','订单号'),
            array('request_pay_no','请求交易号'),
            array('phone','用户账号'),
            array('guide_type','服务类型'),
            array('realname','导游姓名'),
            array('mobile','导游账号'),
            array('tourist_names','风景区'),
            array('pay_channel','支付渠道'),
            array('pay_time','支付时间'),
            array('pay_money','总收入'),
            array('tip_money','小费'),
            array('derate_money','减免'),
            array('order_money','计价收入'),
            array('one_money','个人收入'),
            array('proportion','平台收费比例'),
            array('manage_amount','平台收入'),
            array('rebate_money','优惠卷'),
            array('rebate','返利'),
            array('total_money','账户余额'),

        );
        parent::export_excel('入账记录',$cellName,$OrdersData,$start_time,$end_time);
    }
    /**
     * 出账Excel导出
     */
    public function export(){
        $tourist_id = I('get.tourist_id');
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
        if(!empty($tourist_id))
            $map['gmi.tourist_id'] = $tourist_id;
        if(!empty($start_time))
            $map['gw.request_time'] = array('egt',strtotime($start_time));
        if(!empty($end_time))
            $map['gw.request_time'] = array('elt',strtotime($end_time));
        if(!empty($start_time) && !empty($end_time))
            $map['gw.request_time'] = array(
                array('egt',strtotime($start_time)),
                array('elt',strtotime($end_time)),
            );
        $Model = M('guide_member_info as gmi');
        $OrdersData = $Model
            ->field('gmi.uid,gmi.phone,gw.bankcard_username,gmi.realname,gmi.guide_type,gbi.bank_name,gmi.now_total_amount,gw.order_number,gw.bankcard_number,gw.bank_number,gw.request_time,gw.is_withdraw,gw.withdraw_amount')
            ->join('__GUIDE_WITHDRAW__  as gw on gw.uid = gmi.uid')
            ->join('__GUIDE_BANK_INFO__ as gbi on gbi.uid = gmi.uid')
            ->where($map)
            ->order('gw.request_time desc')
            ->select();
        foreach($OrdersData as &$val){
            $val['request_time']    = date('Y-m-d H:i:s',$val['request_time']);
            switch($val['is_withdraw']){
                case -1:
                    $val['is_withdraw'] ="提现失败";
                    break;
                case 0:
                    $val['is_withdraw'] ="提交申请";
                    break;
                case 1:
                    $val['is_withdraw'] ="向银行发起转账";
                    break;
                case 2:
                    $val['is_withdraw'] ="提现成功";
                    break;
            }
        }
        //dump($OrdersData);die;
        $cellName = array(
            array('order_number','第三方转账交易号'),
            array('bank_number','银行联行号'),
            array('phone','用户账号'),
            array('bankcard_username','持卡人姓名'),
            array('request_time','提现成功时间'),
            array('withdraw_amount','提现金额'),
            array('bank_name','提现方式(银行)'),
            array('bankcard_number','提现银行卡号'),
            array('now_total_amount','用户余额'),
            array('is_withdraw','提现状态'),
        );
        parent::export_excel('出账记录',$cellName,$OrdersData,$start_time,$end_time);
    }
    /**
     * 入账详情
     */
    public function finance_uid(){
        if (IS_GET) {
            $order_number = I('order_number');
            $detail = M('order as o')

                ->join('left join __GUIDE_MEMBER_INFO__ as gmi on gmi.uid=o.guid')
                ->join('left join __ORDER_INFO__ as oi on oi.order_number=o.order_number')
                ->field('gmi.head_image,oi.start_addr,oi.end_addr,oi.server_type,oi.server_price,o.order_time,o.guide_confirm_order_time,gmi.realname,gmi.history_total_amount,gmi.now_total_amount,o.order_number,gmi.in_amount,gmi.out_amount,o.order_money,o.rebate_money,o.tip_money,o.pay_money')
                ->where('o.order_number=' . $order_number)
                ->find();
              /*  dump($detail);die;*/
            if ($detail) {
                $detail['head_image'] = get_pic_url() . $detail['head_image'];
                $detail['order_time'] = date('Y-m-d H:i:s',$detail['order_time']);
                $detail['guide_confirm_order_time'] = date('Y-m-d H:i:s',$detail['guide_confirm_order_time']);
            }
           /* print($detail['head_image']);*/
            $this->assign('detail', $detail);
            $this->meta_title = '订单详情';
            $this->display();
        }
    }

    /**
     * 个人订单
     */
    public function finance_once(){
        if (IS_GET) {
            $uid = I('uid');
            var_dump(123);die;
            $pageindex = I('p', 1);
            $pagesize = I('pagesize', 10);
            $result = M('order as o')
                ->field('o.order_number,o.pay_money,o.server_status,o.pay_status,o.order_time,oi.end_addr,oi.server_type,um.mobile')
                ->join('left join yy_order_info as oi on o.order_number=oi.order_number')
                ->join('left join yy_user_member as um on um.id=o.uuid')
                ->where('o.guid=' . $uid)
                ->page($pageindex, $pagesize)
                ->select();
            if ($result) {
                foreach ($result as &$val) {
                    $val['order_time'] = date('m月d日 H:i', $val['order_time']);
                    $val['order_status'] = $this->get_order_status($val['server_type'], $val['server_status'], $val['pay_status']);
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
     * 出账详情
     */
    public function out_details(){
        if (IS_GET) {
            $order_number = I('order_number');
            $detail = M('guide_withdraw as gw')
                ->join('left join __GUIDE_MEMBER_INFO__ as gmi on gmi.uid=gw.uid')
                ->field('gmi.head_image,gmi.realname,gmi.history_total_amount,gmi.now_total_amount,gw.order_number,gmi.in_amount,gmi.out_amount,gw.withdraw_amount,gw.create_time,gw.request_time,gw.response_time,gw.bankcard_username')
                ->where('gw.order_number=' . $order_number)
                ->find();
            if ($detail) {
                $detail['create_time'] = date('Y-m-d H:i:s',$detail['create_time']);
                $detail['request_time'] = date('Y-m-d H:i:s',$detail['request_time']);
                $detail['response_time'] = date('Y-m-d H:i:s',$detail['response_time']);
                $detail['head_image'] = get_pic_url() . $detail['head_image'];
            }
            $this->assign('detail', $detail);
            $this->meta_title = '出账详情';
            $this->display();
        }
    }
    /**
     * 查看讲解员景区
     */
    public function tourist_area($tourist_id){
        $tourist_name = M('tourist_area as ta')->field('tourist_name')->where('ta.id ='.$tourist_id)->find();
        if($tourist_name){
            return $tourist_name;
        }
    }

    /*
     * 获取用户账户余额及可提现金额
     */
    private function get_amount_info($uid,$time_node){
        $map_in = array(
            'uid'   =>  $uid,
            'deal_type' =>  1,
            'deal_time' =>  array('elt',$time_node),
        );
        $map_out = array(
            'uid'   =>  $uid,
            'deal_type' =>  2,
            'deal_time' =>  array('elt',$time_node),
        );
        $inSum = M('amount')->where($map_in)->sum('profit_amount');
        $outSum = M('amount')->where($map_out)->sum('profit_amount');
        $total_money = bcsub($inSum,$outSum,2);
        return array('total_money'=>$total_money);
    }
}
