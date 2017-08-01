<?php
/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/9/5
 * Time: 14:47
 */

namespace Api_v2\Model;


use Think\Model\ViewModel;

class OrderGuideInfoViewModel extends ViewModel{
    public $viewFields = array(
        'order' =>  array('_as'=>'o','order_number','guid','server_status','pay_status','order_time','cancel_time','order_money','rebate_money','derate_money','tip_money','pay_money','pay_type','is_updated'),
        'order_info'    =>  array('_as'=>'oi','_type'=>'left','_on'=>'o.order_number=oi.order_number','server_start_time','server_end_time','server_price','guide_type','charging_type','end_addr'),
        'comment'   =>array('_as'=>'c','_type'=>'left','_on'=>'o.order_number=c.order_number','content','star','tag'),
        'guide_member_info' =>  array('_as'=>'gi','_type'=>'left','_on'=>'o.guid=gi.uid','realname','phone','head_image','stars','idcard'),
    );
}