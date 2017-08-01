<?php
/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/10/11
 * Time: 15:27
 */

namespace Manage\Controller;


use Common\Controller\AutoController;

class CallbackController extends AutoController{

    /*
     * 云游易导确认订单回调接口
     */
    public function confirmOrder(){
        switch ($this->_method){
            case 'post':
                $cid = I('cid');
                $timestamp = I('time');
                $info = stripcslashes(I('info','','htmlspecialchars_decode'));
                $sign = I('sign');
                $verify_result = $this->verifyCallback($cid,$timestamp,$info,$sign);
                if($verify_result !== true){
                    $response = array(
                        'result'    =>  'failure',
                        'error_code'    =>  '4001',
                        'error_info'    =>  $this->showErrMsg($verify_result),
                    );
                }else{
                    if(parent::confirm_order(json_decode($info,true))){
                        $response = array(
                            'result'    =>  'success',
                        );
                    }else{
                        $response = array(
                            'result'    =>  'failure',
                            'error_code'    =>  '4001',
                            'error_info'    =>  'Order does not exist',
                        );
                    }
                }
                break;
            default:
                $response = array(
                    'result'    =>  'failure',
                    'error_code'    =>  '3001',
                    'error_info'    =>  'HTTP request type error!',
                );
        }
        die(json_encode($response));
    }


    /*
     * 云游易导确认订单回调接口
     */
    public function cancelOrder(){
        switch ($this->_method){
            case 'post':
                $cid = I('cid');
                $timestamp = I('time');
                $info = I('info','','htmlspecialchars_decode');
                $sign = I('sign');
                $verify_result = $this->verifyCallback($cid,$timestamp,$info,$sign);
                if(!$verify_result){
                    $response = array(
                        'result'    =>  'failure',
                        'error_code'    =>  '4001',
                        'error_info'    =>  $this->showErrMsg($verify_result),
                    );
                }else{
                    if(parent::cancel_order(json_decode($info,true))){
                        $response = array(
                            'result'    =>  'success',
                        );
                    }else{
                        $response = array(
                            'result'    =>  'failure',
                            'error_code'    =>  '4001',
                            'error_info'    =>  'Order does not exist！',
                        );
                    }
                }
                break;
            default:
                $response = array(
                    'result'    =>  'failure',
                    'error_code'    =>  '3001',
                    'error_info'    =>  'HTTP request type error!',
                );
        }
        die(json_encode($response));
    }


    /*
     *
     * @param $cid 商户ID；意指本平台在云游易导上的商户ID
     * @param $time 时间戳
     * @param $info  请求参数
     * 此方法仅用于验证云游易导的回调参数及签名是否正确
     */
    private function verifyCallback($cid,$time,$info,$sign){
        if($cid != C('YYYD_CONFIG.ID'))
            return -1;
        if(strtotime( date('Y-m-d H:i:s', strtotime($time)) ) !== strtotime($time))
            return -2;
        json_decode($info,true);
        if(!empty(json_last_error()))//info参数错误
            return 'json string error:'.json_last_error_msg();
        $strSign =  strtolower(md5($cid.C('YYYD_CONFIG.KEY').$time.$info));
        if($strSign != $sign)
            return -4;
        return true;
    }

    private function showErrMsg($err){
        switch ($err){
            case '-1':  $errMsg = 'info.cid error';  break;
            case '-2':  $errMsg = 'info.time error';  break;
            case '-4':  $errMsg = 'info.sign error';  break;
            default:
                $errMsg = $err;
        }
        return $errMsg;
    }
}