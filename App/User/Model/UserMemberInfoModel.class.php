<?php

namespace User\Model;

/**
 * @author vaio
 * @datetime 2016-3-30  16:48:23
 * @encoding UTF-8 
 * @filename GuideMemberModel.class.php 
 */
class UserMemberInfoModel extends \Think\Model {
    /*
     * 自动验证
     * 
     */

    protected $_validate = array(
        array('sex', array(0, 1, 2), -15, self::VALUE_VALIDATE, 'in'), // 判断性别是否正确
        array('realname', '0,16', -13, self::VALUE_VALIDATE, 'length'), //真实姓名长度不正确
            //array('idcard', '/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{4}$/', -14, self::VALUE_VALIDATE, 'regex'), //身份证号不正确
            //array('guide_number', array(0, 1), '导游类型不正确！', self::MUST_VALIDATE, 'in'), // 判断性别是否正确
    );


    /*
     * 自动完成
     */
    protected $_auto = array(
        array('is_auth', 0, self::MODEL_INSERT), //默认注册为未实名认证
        array('reg_time', NOW_TIME, self::MODEL_INSERT),
        array('reg_ip', 'get_client_ip', self::MODEL_INSERT, 'function', 1),
        array('head_image', 'save_head_image', self::MODEL_INSERT, 'callback',),
        array('status', 'getStatus', self::MODEL_INSERT, 'callback'),
    );

    /**
     * 根据配置指定用户状态
     * @return integer 用户状态
     */
    protected function getStatus() {
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /*
     * 用户信息表注册
     */

    public function register($nickname = '', $realname = '', $sex = 2, $idcard = '') {
        vendor('AES.Aes');
        $AES = new \MCrypt();
        $data = array(
            'nickname' => '',
            'realname' => $realname,
            'sex' => $sex,
            'idcard' => $AES->encrypt($idcard),
            'birthday' => empty($idcard) ? 0000 - 00 - 00 : substr($idcard, 6, 8),
            'phone' =>  $nickname,
        );
        if ($this->create($data)) {
            if ($this->add())
                return TRUE; //注册成功
            return FALSE;
        }else {
            return $this->getError();
        }
    }

    /**
     * 登录指定用户
     * @param  integer $uid 用户ID
     * @return boolean      ture-登录成功，false-登录失败
     */
    public function login($uid) {
        /* 检测是否在当前应用注册 */
        $user = $this->field(true)->find($uid);
        if (!$user) { //未注册
            /* 获取用户账户信息 */
            $api = new \User\Api\UserApi();
            $info = $api->userMember_Model->info($uid);
            $user = $this->create(array('nickname' => $info['mobile'], 'status' => 1));
            $user['uid'] = $uid;
            if (!$this->add($user)) {
                $this->error = '前台用户信息注册失败，请重试！';
                return false;
            }
        } elseif (1 != $user['status']) {
            $this->error = '用户未激活或已禁用！'; //应用级别禁用
            return false;
        }

        /* 登录用户 */
        $this->autoLogin($user);

        //记录行为
        //action_log('user_login', $model, $uid, $uid);

        return true;
    }

    /*
     * 上传用户头像
     */

    protected function save_head_image() {
        if (isset($_FILES['head_image']) && is_array($_FILES['head_image'])) {
            $config = array(
                'maxSize' => 3 * 1024 * 1024, //上传限制3M
                'rootPath' => './Uploads/',
                'savePath' => '',
                'saveName' => array('uniqid', ''),
                'exts' => array('jpg', 'gif', 'png', 'jpeg'),
                'autoSub' => true,
                'subName' => 'User/head_image',
            );
            $upload = new \Think\Upload($config); // 实例化上传类
            $info = $upload->uploadOne($_FILES['head_image']);
            if ($info)
                return $file_path = substr($config['rootPath'], 1) . $info['savepath'] . $info['savename']; //在模板里的url路径
        }
        return C('DEFAULT_HEAD_IMAGE');
    }

    /**
     * 自动登录用户
     * @param  integer $user 用户信息数组
     */
    private function autoLogin($user) {
        /* 更新登录信息 */
        $data = array(
            'uid' => $user['uid'],
            'login_times' => array('exp', '`login_times`+1'),
            'last_login_time' => NOW_TIME,
            'last_login_ip' => get_client_ip(1),
            'deviceid' => get_deviceid(),
            'push_id' => I('post.push_id', ''),
        );
        $this->save($data);

        /* 记录登录SESSION和COOKIES */
        $auth = array(
            'uid' => $user['uid'],
            'username' => get_username($user['uid']),
            'last_login_time' => $user['last_login_time'],
        );

        session('user_auth', $auth);
        session('user_auth_sign', data_auth_sign($auth));
    }

}
