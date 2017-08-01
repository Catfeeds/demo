<?php
/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/9/5
 * Time: 15:46
 */

namespace Api_v2\Model;


use Think\Model\ViewModel;

class OrderUserInfoViewModel extends ViewModel{

    public $viewFields = array(
        'order' =>  array('_as'=>'o','order_number','server_status','pay_status','order_time','cancel_time','order_money','rebate_money','derate_money','tip_money','pay_money','pay_type','is_updated'),
        'order_info'    =>  array('_as'=>'oi','_type'=>'left','_on'=>'o.order_number=oi.order_number','realname','phone','server_start_time','server_end_time','server_price','guide_type','charging_type','end_addr'),
        'comment'   =>array('_as'=>'c','_type'=>'left','_on'=>'o.order_number=c.order_number','content','star','tag'),
        'user_member_info'  =>  array('_as'=>'ui','_type'=>'left','_on'=>'o.uuid=ui.uid','nickname','phone'=>'mp','head_image'),
    );
}