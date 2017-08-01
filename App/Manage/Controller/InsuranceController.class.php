<?php
/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/7/28
 * Time: 15:28
 */

namespace Manage\Controller;


class InsuranceController extends AdminController{
    
    public function _initialize($group_title) {
        parent::_initialize($group_title);
        $this->group_title = '客服管理';
    }
    
    public function insurance_list(){
        $curr = I('get.page',1);
        $Model = M('order');
        $page_size = 15;
        $count = $Model->count();
        $list = $Model->page($curr,$page_size)->order('guide_confirm_order_time desc')->select();
        foreach($list as &$val){
            $lng_lat =   unserialize($val['lng_lat']);
            $val['lng_lat'] =  implode(',',$lng_lat);
        }
        $pages = intval(ceil($count / $page_size));
        $list = array(
            'list'  =>  $list,
            'pages' =>  $pages,
        );
        $this->list = $list;
        $this->meta_title = '保险购买列表';
        $this->display();
    }
}