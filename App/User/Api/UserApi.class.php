<?php

// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace User\Api;

use User\Api\Api;

class UserApi extends Api {

    /**
     * 构造方法，实例化操作模型
     */
    protected function _init() {
        $this->userMember_Model = new \User\Model\UserMemberModel(); //普通用户账户模型
        $this->userMemberInfo_Model = new \User\Model\UserMemberInfoModel(); //普通用户基本信息模型
        $this->guideMember_Model = new \User\Model\GuideMemberModel(); //导游账户模型
        $this->guideMemberInfo_Model = new \User\Model\GuideMemberInfoModel(); //导游基本信息模型
    }

    /**
     * 导游用户注册
     * @param  string $password 用户密码
     * @param  string $mobile   用户手机号码
     * @return integer          注册成功-用户信息，注册失败-错误编号
     */
    public function register($mobile, $password,$user_type = 1,$invite_code = '') {
        switch ($user_type) {
            case 1:
                $uid = $this->userMember_Model->register($mobile, $password);
                if ($uid > 0)
                    $this->userMemberInfo_Model->register($mobile);
                break;
            case 2:
                $uid = $this->guideMember_Model->register($mobile, $password,$invite_code);
                break;
        }
        return $uid;
    }
    
    /*
     * 导游信息表注册
     */
    public function register_info($uid) {
        if ($uid > 0)
            return $this->guideMemberInfo_Model->register($uid); //导游特有信息注册
    }

    /**
     * 用户登录认证
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     * @param  integer $type     用户名类型 （1-用户名，2-邮箱，3-手机，4-UID）
     * @return integer           登录成功-用户ID，登录失败-错误编号
     */
    public function login($username, $password, $user_type = 1) {
        switch ($user_type) {
            case 1:
                $uid = $this->userMember_Model->login($username, $password, 3);
                if ($uid > 0)
                    $this->userMemberInfo_Model->login($uid);
                return $uid;
                break;
            case 2:
                $uid = $this->guideMember_Model->login($username, $password, 3);
                if ($uid > 0)
                    $this->guideMemberInfo_Model->login($uid);
                return $uid;
                break;
        }
    }

    /**
     * 导游端忘记密码
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     */
    public function reset_password($mobile, $password) {
        return $this->guideMember_Model->reset_password($mobile, $password);
    }
    

    /**
     * 导游和总后台管理员用户登录
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     * @param  integer $type     用户名类型 （1-用户名，2-邮箱，3-手机，4-UID）
     * @return integer           登录成功-用户ID，登录失败-错误编号
     */
    public function guide_login($username, $password, $type = 1) {
        return $this->userMember_Model->_login($username, $password, $type);
    }

    /**
     * 获取用户信息
     * @param  string  $uid         用户ID或用户名
     * @param  boolean $is_username 是否使用用户名查询
     * @return array                用户信息
     */
    public function info($uid, $is_username = false) {
        return $this->userMember_Model->info($uid, $is_username);
    }

    /**
     * 检测用户名
     * @param  string  $field  用户名
     * @return integer         错误编号
     */
    public function checkUsername($username) {
        return $this->userMember_Model->checkField($username, 1);
    }

    /**
     * 检测邮箱
     * @param  string  $email  邮箱
     * @return integer         错误编号
     */
    public function checkEmail($email) {
        return $this->userMember_Model->checkField($email, 2);
    }

    /**
     * 检测手机
     * @param  string  $mobile  手机
     * @return integer         错误编号
     */
    public function checkMobile($mobile) {
        return $this->userMember_Model->checkField($mobile, 3);
    }

    /**
     * 更新用户信息
     * @param int $uid 用户id
     * @param string $password 密码，用来验证
     * @param array $data 修改的字段数组
     * @return true 修改成功，false 修改失败
     * @author huajie <banhuajie@163.com>
     */
    public function updateInfo($uid, $password, $data) {
        if ($this->guideMember_Model->updateUserFields($uid, $password, $data) !== false) {
            $return['status'] = true;
        } else {
            $return['status'] = false;
            $return['info'] = $this->guideMember_Model->getError();
        }
        return $return;
    }

    /*
     * 返回字符串md5加密后的字符串
     */
    public function password_md5($password){
        return think_ucenter_md5($password, PASSWORD_MD5_KEY);
    }
}
