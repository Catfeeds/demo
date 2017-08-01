<?php

// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace User\Model;

use Think\Model;

/**
 * 会员模型
 */
class GuideMemberModel extends Model {

    /**
     * 数据表前缀
     * @var string
     */
    protected $tablePrefix = UC_TABLE_PREFIX;

    /**
     * 数据库连接
     * @var string
     */
    protected $connection = UC_DB_DSN;

    /* 用户模型自动验证 */
    protected $_validate = array(
        /* 验证手机号码 */
        array('mobile', '/^1[34578]{1}\d{9}$/', -1, self::MUST_VALIDATE, 'regex',), //手机格式不正确 TODO:
        array('mobile', 'checkDenyMobile', -2, self::MUST_VALIDATE, 'callback',), //手机禁止注册
        array('mobile', '', -3, self::MUST_VALIDATE, 'unique',self::MODEL_INSERT), //手机号被占用
        /* 验证密码 */
        array('password', '8,20', -4, self::EXISTS_VALIDATE, 'length'), //密码长度不合法
        array('repassword', 'password', -5, self::EXISTS_VALIDATE, 'confirm'),
    );

    /* 用户模型自动完成 */
    protected $_auto = array(
        array('reg_time', NOW_TIME, self::MODEL_INSERT),
        array('reg_ip', 'get_client_ip', self::MODEL_INSERT, 'function', 1),
        array('password', 'think_ucenter_md5', self::MODEL_INSERT, 'function', PASSWORD_MD5_KEY),
        array('update_time', NOW_TIME),
        array('status', 'getStatus', self::MODEL_BOTH, 'callback'),
    );

    /**
     * 检测用户名是不是被禁止注册
     * @param  string $username 用户名
     * @return boolean          ture - 未禁用，false - 禁止注册
     */
    protected function checkDenyMember($username) {
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /**
     * 检测邮箱是不是被禁止注册
     * @param  string $email 邮箱
     * @return boolean       ture - 未禁用，false - 禁止注册
     */
    protected function checkDenyEmail($email) {
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /**
     * 检测手机是不是被禁止注册
     * @param  string $mobile 手机
     * @return boolean        ture - 未禁用，false - 禁止注册
     */
    protected function checkDenyMobile($mobile) {
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /**
     * 根据配置指定用户状态
     * @return integer 用户状态
     */
    protected function getStatus() {
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /*
     * 注册固定密码登录用户
     * @param  string $username 用户名
     * @param  string $password 用户密码
     * @param  string $email    用户邮箱
     * @param  string $mobile   用户手机号码
     * @return integer          注册成功-用户信息，注册失败-错误编号
     */

    public function register($mobile, $password) {
        $data = array(
            'username' => $mobile,
            'email' => '',
            'password' => $password,
            'mobile' => $mobile,
        );

        //验证手机
        if (empty($data['mobile']))
            unset($data['mobile']);
        if (empty($data['email']))
            unset($data['email']);

        /* 添加用户 */
        if ($this->create(array_merge($data, $_POST))) {
            $uid = $this->add();
            return $uid ? $uid : 0;
        } else {
            return $this->getError(); //错误详情见自动验证注释
        }
    }

    /**
     * 固定密码登录
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     * @param  integer $type     用户名类型 （1-用户名，2-邮箱，3-手机，4-UID）
     * @return integer           登录成功-用户ID，登录失败-错误编号
     */
    public function login($username, $password, $type = 1) {
        $map = array();
        switch ($type) {
            case 1:
                $map['username'] = $username;
                break;
            case 2:
                $map['email'] = $username;
                break;
            case 3:
                $map['mobile'] = $username;
                break;
            case 4:
                $map['id'] = $username;
                break;
            default:
                return 0; //参数错误
        }
        /* 获取用户数据 */
        $user = $this->where($map)->find();
        if (is_array($user) && $user['status']) {
            /* 验证用户密码 */
            if (think_ucenter_md5($password, PASSWORD_MD5_KEY) === $user['password']) {
                $this->updateLogin($user['id']); //更新用户登录信息
                return $user['id']; //登录成功，返回用户ID
            } elseif ($user['token'] === $password) {
                $this->updateLogin($user['id']); //更新用户登录信息
                return $user['id']; //登录成功，返回用户ID
            }
            return -4; //密码错误或token验证失败
        } else {
            return -1; //用户不存在或被禁用
        }
    }

    /**
     * 获取用户信息
     * @param  string  $uid         用户ID或用手机号
     * @param  boolean $is_username 是否使用用户名查询
     * @return array                用户信息
     */
    public function info($uid, $is_mobile = false) {
        $map = array();
        if ($is_mobile) { //通过用户名获取
            $map['mobile'] = $uid;
        } else {
            $map['id'] = $uid;
        }

        $user = $this->where($map)->field('id,username,email,mobile,status')->find();
        if (is_array($user) && $user['status'] = 1) {
            unset($user['status']);
            return $user;
        } else {
            return -1; //用户不存在或被禁用
        }
    }

    /**
     * 检测用户信息
     * @param  string  $field  用户名
     * @param  integer $type   用户名类型 1-用户名，2-用户邮箱，3-用户电话
     * @return integer         错误编号
     */
    public function checkField($field, $type = 1) {
        $data = array();
        switch ($type) {
            case 1:
                $data['username'] = $field;
                break;
            case 2:
                $data['email'] = $field;
                break;
            case 3:
                $data['mobile'] = $field;
                break;
            default:
                return 0; //参数错误
        }

        return $this->create($data) ? 1 : $this->getError();
    }

    /**
     * 更新用户登录信息
     * @param  integer $uid 用户ID
     */
    protected function updateLogin($uid) {
        $data = array(
            'id' => $uid,
            'last_login_time' => NOW_TIME,
            'last_login_ip' => get_client_ip(1),
            'token' => md5(randomIntkeys(6) . NOW_TIME),
        );
        $this->save($data);
    }

    /**
     * 更新用户信息
     * @param int $uid 用户id
     * @param string $password 密码，用来验证
     * @param array $data 修改的字段数组
     * @return true 修改成功，false 修改失败
     * @author huajie <banhuajie@163.com>
     */
    public function updateUserFields($uid, $data, $password = '') {
        if (empty($uid) || empty($data)) {
            $this->error = '参数错误！';
            return false;
        }
        //更新前检查用户密码
        if(!empty($password)){
            if (!$this->verifyUser($uid, $password)) {
                $this->error = '验证出错：密码不正确！';
                return false;
            }
        }

        //更新用户信息
        if ($data) {
            return $this->where(array('id' => $uid))->save($data);
        }
        return false;
    }

    /**
     * 验证用户密码
     * @param int $uid 用户id
     * @param string $password_in 密码
     * @return true 验证成功，false 验证失败
     * @author huajie <banhuajie@163.com>
     */
    protected function verifyUser($uid, $password_in) {
        $password = $this->getFieldById($uid, 'password');
        if (think_ucenter_md5($password_in, PASSWORD_MD5_KEY) === $password) {
            return true;
        }
        return false;
    }

    /**
     * 找回密码
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     */
    public function reset_password($mobile, $password) {
        $password = think_ucenter_md5($password, PASSWORD_MD5_KEY);
        $uid = $this->where(array('mobile'=>$mobile))->setField('password', $password);
        return $uid;
    }

}
