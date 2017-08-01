<?php

namespace Common\Controller;
use Think\Controller\RestController;

/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/6/29
 * Time: 11:13
 */
class AutoController extends RestController{

    protected $allowMethod = array('get', 'post', 'put'); // REST允许的请求类型列表
    protected $allowType = array('html', 'xml', 'json'); // REST允许请求的资源类型列表
    protected $client;//客户端标识   1-用户端；2-导游端
    protected $deviceid; //设备标识
    protected $sign;//签名字符串

    protected function _initialize(){
        $this->auto_reset_online_time();
        $this->action_amount();
    }

    /*
     * 自动重置导游在线时长
     */
    private function auto_reset_online_time(){
        $start_time = strtotime(date("Y-m-d",strtotime("-1 day")));
        $end_time = strtotime(date('Y-m-d')) -1;
        if(NOW_TIME > $end_time){
            $Model_guide_member_info = M('guide_member_info');
            $online_user = $Model_guide_member_info->where(array('is_online'=>1,'status'=>1,'is_auth'=>1))->field('uid')->select();
            if(!$online_user)   return false;
            $Model_guide_online_log = M('guide_online_log');
            $map = array(
                'offline_time'  =>  array('eq',0),
                'server_time_long'   =>  array('eq',0),
                'online_time'   =>  array(array('egt',$start_time),array('elt',$end_time)),
                'is_online'  =>  1,
            );
            foreach($online_user as $val){
                $map['guid'] = $val['uid'];
                $online_info[] = $Model_guide_online_log->where($map)->order('id desc')->field('id,guid')->find();
            }
            if(!$online_info = array_filter($online_info))  return false;
            //修改mysql数据表引擎
            $Model = new \Think\Model();
            if (FALSE !== $Model->execute('alter table __GUIDE_ONLINE_LOG__ ENGINE=InnoDB')) {
                foreach($online_info as $value){
                    $Model_guide_online_log->startTrans();
                    $update = array(
                        'offline_time'=>$end_time,
                        'server_time_long' => array('exp', $end_time .'- online_time'),
                    );
                    if(false === $Model_guide_online_log->where(array('id'=>$value['id']))->save($update) || !$Model_guide_online_log->add(array('guid' => $value['guid'], 'online_time' => $end_time+1)))
                        $Model_guide_online_log->rollback();
                    $Model_guide_online_log->commit();
                }
            }
            //mysql数据库引擎修改
            $Model->execute('alter table __GUIDE_ONLINE_LOG__ ENGINE=myisam');
        }
    }
    
    /*
     * 提现金额自动处理
     */
    private function action_amount(){
        $redis = S(array('type' => 'redis', 'expire' => 0, 'prefix' =>'ide'));
        $ide = '_ide';
        $ide = $redis->$ide;
        if(date('w') != 3 && date('w') != 2 && isset($ide) && $ide == 1){
            /*
             * 取出用户金额缓存并入队列
             */
            $redisCache = S(array('type' => 'redis', 'expire' => 0, 'prefix' => $prefix = 'guide_amount'));
            $keys = $redisCache->keys($prefix.'*');
            $queue = array();
            foreach ($keys as $v){
                $uid = str_replace($prefix,"",$v);
                array_push($queue,array('uid'=>$uid,'sum'=>array_sum($redisCache->$uid)));//入队列
                unset($redisCache->$uid);
            }
            foreach ($queue as $val){
                M('guide_member_info')->where(array('uid'=>$val['uid']))->save(array('action_amount'=>array('exp','action_amount+'.$val['sum'])));
            }
            $ide = '_ide';
            $redis->$ide = 0;
        }
    }

    /*
     * 确认导游接单
     * 仅供云游易导对接使用
     */
    protected function confirm_order($param){
        $order_number = M('order')->getFieldByYd_id($param['inviteid'],'order_number');
        if($order_number){
            $Model = new \Think\Model();
            if (FALSE !== $Model->execute('alter table __ORDER__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __ORDER_INFO__ ENGINE=InnoDB')) {
                $Model_order = M('order');
                $Model_order_info = M('order_info');
                $Model_order->startTrans(); //开启事务
                $re1 = $Model_order->lock(true)->where(array('order_number'=>$order_number))->setField(array('guide_confirm_order_time'=>NOW_TIME,'server_status'=>1));
                $re2 = $Model_order_info->where(array('order_number'=>$order_number))->setField(array('gname'=>$param['name'],'gphone'=>$param['mobile']));
                if ($re1 === false || $re2 === false) {
                    $Model_order->rollback(); //回滚
                    //mysql数据库引擎修改
                    $Model->execute('alter table __ORDER__ ENGINE=myisam');
                    $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');
                    return false;
                }
                $Model_order->commit(); //事务提交
                //mysql数据库引擎修改
                $Model->execute('alter table __ORDER__ ENGINE=myisam');
                $Model->execute('alter table __ORDER_INFO__ ENGINE=myisam');
                return true;
            }
        }
        return false;
    }


    protected function cancel_order($param){
        $order_number = M('order')->getFieldByYd_id($param['inviteid'],'order_number');
        if($order_number){
            if(M('order')->where(array('order_number'=>$order_number))->setField(array('server_status'=>2,'cancel_time'=>NOW_TIME)))
                return true;
            return false;
        }
        return false;
    }
}