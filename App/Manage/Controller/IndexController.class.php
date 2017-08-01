<?php
namespace Manage\Controller;


class IndexController extends AdminController{
    
    public function _initialize(){
        parent::_initialize();
        //$this->group_title = '123';
    }
    
    
    public function index(){
        $this->display();
    }

    //配置列表
    public function conf_list(){
        $conf = C('setting');
        $this->_conf = $conf;
        $this->display();
    }
    
    public function conf_edit(){
        $this->display();
    }

    public function test(){
        $data = array(
            'history_total_amount'  =>  0,
            'now_total_amount'  =>  0,
            'action_amount'  =>  0,
            'in_amount'  =>  0,
            'out_amount'  =>  0,
        );
        if(M('guide_member_info')->where(true)->save($data)){
            $user = M('guide_member_info')->where(array('realname'=>array('exp','IS NOT NULL')))->field('uid')->select();
            switch (date('w')){
                case 2: $rule = 'this Tuesday';
                    break;
                case 3: $rule = 'last Tuesday';
                    break;
                default:
                    $rule = 'next Tuesday';
            }
            $Model_amount = M('amount');
            $Model_withdraw = M('guide_withdraw');
            foreach ($user as $val){
                $node_time = strtotime(date('Y-m-d',strtotime($rule)));
                $map_amount = array(
                    'deal_type' =>  1,
                    'uid'   =>  $val['uid'],
                );
                $map_withdraw = array(
                    'uid'   =>  $val['uid'],
                    'is_withdraw'   =>  array('neq','-1'),
                );
                if(!in_array(date('w'),array('2','3'))){
                    $map_amount['deal_time']    =  array('lt',$node_time);
                    $map_withdraw['create_time']    =  array('lt',$node_time);
                }

                $total_profit = $Model_amount->where($map_amount)->sum('profit_amount');
                $total_withdraw = $Model_withdraw->where($map_withdraw)->sum('withdraw_amount');

                $data_member = array(
                    'history_total_amount'  =>  bcadd($total_profit,$total_withdraw,2),
                    'now_total_amount'  =>  bcsub($total_profit,$total_withdraw,2),
                    'action_amount'  =>  bcsub($total_profit,$total_withdraw,2),
                    'in_amount'  =>  $total_profit,
                    'out_amount'  =>  $total_withdraw,
                );
                M('guide_member_info')->where(array('uid'=>$val['uid']))->save($data_member);
                usleep(100);
            }
            die('操作完成！');
        }
        die('cuowu!');
    }
}