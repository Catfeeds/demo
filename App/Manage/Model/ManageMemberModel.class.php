<?php

/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/8/1
 * Time: 17:05
 */
namespace Manage\Model;
class ManageMemberModel extends \Think\Model{

    protected $_validate = array(

        /* 验证用户名 */
        array('nickname', '2,10', -1, self::EXISTS_VALIDATE, 'length'),
        array('nickname', '', -2, self::EXISTS_VALIDATE, 'unique'), //用户名被占用

        /* 验证密码 */
        array('password', '6,20', -3, self::EXISTS_VALIDATE, 'length'), //密码长度不合法
    );

    /* 用户模型自动完成 */
    protected $_auto = array(
        array('password', 'think_auth_md5', self::MODEL_BOTH, 'function', UC_AUTH_KEY),
        array('reg_time', NOW_TIME, self::MODEL_INSERT),
        array('reg_ip', 'get_client_ip', self::MODEL_INSERT, 'function', 1),
        //array('update_time', NOW_TIME),
        array('status', 'getStatus', self::MODEL_BOTH, 'callback'),
    );


    public function lists($status = 1, $order = 'uid DESC', $field = true){
        $map = array('status' => $status);
        return $this->field($field)->where($map)->order($order)->select();
    }


    /**
     * 用户登录认证
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     * @param  integer $type     用户名类型 （1-用户名，2-邮箱，3-手机，4-UID）
     * @return integer           登录成功-用户ID，登录失败-错误编号
     */
    public function login($username, $password, $type = 1){
        $map = array();
        switch ($type) {
            case 1:
                $map['nickname'] = $username;
                break;
            case 2:
                $map['email'] = $username;
                break;
            case 3:
                $map['mobile'] = $username;
                break;
            case 4:
                $map['uid'] = $username;
                break;
            default:
                return 0; //参数错误
        }

        /* 获取用户数据 */
        $Member = $this->where($map)->find();

        if(is_array($Member) && $Member['status']){
            /* 验证用户密码 */
            if( $Member['password'] === think_auth_md5($password, UC_AUTH_KEY) ){
                //登录成功
                $uid = $Member['uid'];

                //记录行为日志
                action_log('user_login', 'member', $uid, $uid);

                // 更新登录信息
                $this->autoLogin($Member);

                return $uid ; //登录成功，返回用户UID
            } else {
                return -2; //密码错误
            }
        } else {
            return -1; //用户不存在或被禁用
        }
    }


    /**
     * 注销当前用户
     * @return void
     */
    public function logout(){
        session('user_auth', null);
        session('user_auth_sign', null);
    }

    /**
     * 自动登录用户
     * @param  integer $user 用户信息数组
     */
    private function autoLogin($user){
        /* 更新登录信息 */
        $data = array(
            'uid'             => $user['uid'],
            'login'           => array('exp', '`login`+1'),
            'last_login_time' => NOW_TIME,
            'last_login_ip'   => get_client_ip(1),
        );
        $this->save($data);

        /* 记录登录SESSION和COOKIES */
        $auth = array(
            'uid'             => $user['uid'],
            'username'        => $user['nickname'],
            'last_login_time' => $user['last_login_time'],
        );

        session('user_auth', $auth);
        session('user_auth_sign', data_auth_sign($auth));

    }

    public function getNickName($uid){
        return $this->where(array('uid'=>(int)$uid))->getField('nickname');
    }


    /**
     * 注册一个新用户
     * @param  string $username 用户名
     * @param  string $password 用户密码
     * @param  string $email    用户邮箱
     * @param  string $mobile   用户手机号码
     * @return integer          注册成功-用户信息，注册失败-错误编号
     */
    public function register($username, $password, $email, $mobile){
        $data = array(
            'nickname' => $username,
            'password' => $password,
            'email'    => $email,
            'mobile'   => $mobile,
        );

        //验证手机
        if(empty($data['mobile'])) unset($data['mobile']);
        if(empty($data['email'])) unset($data['email']);

        /* 添加用户 */
        if($this->create($data)){
            $uid = $this->add();
            return $uid ? $uid : 0; //0-未知错误，大于0-注册成功
        }
        return $this->getError(); //错误详情见自动验证注释
    }

    /**
     * 检测用户名是不是被禁止注册
     * @param  string $nickname 用户名
     * @return boolean          ture - 未禁用，false - 禁止注册
     */
    protected function checkDenyMember($nickname){
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /**
     * 检测邮箱是不是被禁止注册
     * @param  string $email 邮箱
     * @return boolean       ture - 未禁用，false - 禁止注册
     */
    protected function checkDenyEmail($email){
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /**
     * 检测手机是不是被禁止注册
     * @param  string $mobile 手机
     * @return boolean        ture - 未禁用，false - 禁止注册
     */
    protected function checkDenyMobile($mobile){
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /**
     * 根据配置指定用户状态
     * @return integer 用户状态
     */
    protected function getStatus(){
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /**
     * 验证用户密码
     * @param int $uid 用户id
     * @param string $password_in 密码
     * @return true 验证成功，false 验证失败
     * @author huajie <banhuajie@163.com>
     */
    public function verifyUser($uid, $password_in){
        $password = $this->where(array('uid'=>$uid))->getField('password');
        if(think_auth_md5($password_in, UC_AUTH_KEY) === $password){
            return true;
        }
        return false;
    }

    /**
     * 更新用户密码
     * @param  string $uid  用户名
     * @param  string $password 用户密码
     * @return integer          更新成功-用户信息，更新失败-错误编号
     */
    public function updatePassword($uid, $password_in, $repassword){
        $password = $this->where(array('uid'=>$uid))->getField('password');
        if(think_auth_md5($password_in, UC_AUTH_KEY) === $password){
            $data   =   $this->create(array('uid'=>$uid,'password'=>$repassword) );
            $res = $this->where(array('uid'=>$uid))->save($data);
            return true;
        }else{
            return  false ;
        }
    }
}