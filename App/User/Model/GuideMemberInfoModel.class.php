<?php

namespace User\Model;

/**
 * @author vaio
 * @datetime 2016-3-30  16:48:23
 * @encoding UTF-8
 * @filename GuideMemberModel.class.php
 */
class GuideMemberInfoModel extends \Think\Model {
    /*
     * 自动验证
     * 
     */

    protected $_validate = array(
        array('realname', '2,16', -6, self::EXISTS_VALIDATE, 'length'),
        array('phone', '/^1[34578]{1}\d{9}$/', -19, self::EXISTS_VALIDATE, 'regex'), //手机格式不正确 TODO:
        array('email', 'email', -20, self::EXISTS_VALIDATE, 'regex'), //手机格式不正确 TODO:
        array('idcard', '/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}([0-9]|X)$/', -7, self::VALUE_VALIDATE, 'regex'), //身份证号不正确
        array('sex', array(0, 1, 2), -8, self::MUST_VALIDATE, 'in'), // 判断性别是否正确
        array('guide_type', array(1, 2, 3), -9, self::MUST_VALIDATE, 'in'), // 判断性别是否正确
        array('guide_type', 'check_tourist_code', -21, self::EXISTS_VALIDATE, 'callback'), //手机格式不正确 TODO:
        array('guide_card_type', array(0, 1), -10, self::MUST_VALIDATE, 'in'), // 判断性别是否正确
        array('server_language', array(0, 1, 2), -11, self::MUST_VALIDATE, 'in'), // 判断性别是否正确
        array('now_address', 'require', -12,), // 判断性别是否正确
        array('invite_code', '8', -16, self::VALUE_VALIDATE, 'length'),
        array('invite_code', 'check_invite_code', -17, self::VALUE_VALIDATE, 'callback'),
        array('invite_code', 'check_invite_type', -22, self::VALUE_VALIDATE, 'callback'),
        //array('invite_code', 'check_invite_num', -18, self::VALUE_VALIDATE, 'callback',self::MODEL_INSERT),
    );


    /*
     * 自动完成
     */
    protected $_auto = array(
        array('is_auth', 0, self::MODEL_BOTH), //默认注册为未实名认证
        array('reg_time', NOW_TIME, self::MODEL_BOTH),
        array('reg_ip', 'get_client_ip', self::MODEL_BOTH, 'function', 1),
        array('head_image', 'save_head_image', self::MODEL_BOTH, 'callback',),
        array('idcard', 'aes_idcard', self::MODEL_BOTH, 'callback'),
        array('tourist_id','check_area_code',self::MODEL_BOTH,'callback'),
        array('status', 'getStatus', self::MODEL_BOTH, 'callback'),
    );

    /**
     * 根据配置指定用户状态
     * @return integer 用户状态
     */
    protected function getStatus() {
        return true; //TODO: 暂不限制，下一个版本完善
    }

    /*
     * 身份证号加密存储
     */

    protected function aes_idcard($idcard) {
        vendor('AES.Aes');
        $AES = new \MCrypt();
        return $AES->encrypt($idcard);
    }

    /*
     * 检测邀请码是否有效
     */
    protected function check_invite_code($invite_code) {
        if (!empty($invite_code)) {//邀请码不为空的时候
            if (M('guide_member_info')->where(array('invite_code' => $invite_code))->getField('uid'))
                return TRUE;
            return FALSE;
        }
    }

    /*
     * 检测当前用户注册类型是否符合邀请码规则
     */
    protected function check_invite_type(){
        $guide_type = I('guide_type',0);
        if(!empty($guide_type)){
            return $guide_type == 2 ? true : false;
        }
    }

    /*
     * 检测邀请者的邀请限制是否超标
     */
    protected function check_invite_num($invite_code) {
        if (!empty($invite_code)) {//邀请码不为空的时候
            if ($invite_id = M('guide_member_info')->where(array('invite_code' => $invite_code))->getField('uid')) {
                $Model_invite = M('invite');
                if ($Model_invite->where(array('invite_id' => $invite_id, 'invited_id' => exp('is not null')))->count() < get_config_key_value(C('setting.INVITE_NUMBER')))
                    return TRUE;
                return FALSE;
            }
        }
    }

    protected function check_tourist_code($guide_type){
        $area_code = I('post.area_code',0);
        $tourist_code = I('post.tourist_code',0);
        if($guide_type == 1){
            if(empty($area_code) || empty($tourist_code))
                return false;
            if(!empty($area_code) && !empty($tourist_code)){
                $map = array(
                    'tourist_code'    =>  $tourist_code,
                    'area_code' =>  $area_code,
                    'status'    =>  1,
                );
                $info = M('tourist_area')->where($map)->getField('id');
                return $info ? true : false;
            }
            return false;
        }
        return true;
    }

    protected function check_area_code(){
        $area_code = I('post.area_code',0);
        $guide_type = I('post.guide_type',0);
        $tourist_code = I('post.tourist_code',0);
        if($guide_type == 1){
            if(!empty($area_code) && !empty($tourist_code)){
                $map = array(
                    'tourist_code'    =>  $tourist_code,
                    'area_code' =>  $area_code,
                    'status'    =>  1,
                );
                $info = M('tourist_area')->where($map)->getField('id');
                return $info ? $info : 0;
            }
            return 0;
        }
        return 0;
    }

    /*
     * 导游信息表注册
     */
    public function register($uid) {
        $api = new \User\Api\UserApi();
        $info = $api->guideMember_Model->info($uid);
        $idcard = I('post.idcard',0);
        $email = I('post.email','');
        $data = array(
            'uid' => $uid,
            'nickname' => $info['mobile'],
            'birthday' => empty($idcard) ? 0 : substr($idcard, 6, 8),
            'sex'  =>  empty($idcard) ? 2 : substr($idcard, (strlen($idcard) == 18 ? -2 : -1), 1) % 2 === 0 ? 1 : 0,
        );
        if ($guide_info = $this->upload_guide_info()) {
            $data = array_merge($data, $guide_info);
        }
        $invite_code = $_POST['invite_code'];
        unset($_POST['invite_code']);
        if ($this->create(array_merge($data, $_POST))) {
            if ($this->where(array('uid' => $uid))->count() ? $this->where(array('uid' => $uid))->save() : $this->add()){
                //邀请码绑定
                if (!empty($invite_code))
                    $this->invite_code_bind($uid,$invite_code);
                //更新用户邮箱
                if(!empty($email)){
                    if(false !== $api->guideMember_Model->updateUserFields($uid,array('email'=>$email))){
                        return TRUE;
                    }
                }
                return true;
            }
            return false;
        }
        return $this->getError();
    }

    /*
     * 邀请码绑定
     */
    protected function invite_code_bind($uid,$invite_code){
        if ($invite_id = M('guide_member_info')->where(array('invite_code' => $invite_code))->getField('uid')) {
            $Model_invite = M('invite');
            if($temp = $Model_invite->where(array('invited_id' => $uid))->field('id')->find()){
                $invite = array(
                    'invite_id' => $invite_id,
                    'invite_time' => NOW_TIME,
                );
                $Model_invite->where(array('id'=>$temp['id']))->save($invite);
            }else{
                $invite = array(
                    'invite_id' => $invite_id,
                    'invited_id' => $uid,
                    'invite_time' => NOW_TIME,
                );
                $Model_invite->add($invite);
            }
        }
        return;
    }

    /**
     * 登录指定用户
     * @param  integer $uid 用户ID
     * @return boolean      ture-登录成功，false-登录失败
     */
    public function login($uid) {
        /* 检测是否在当前应用注册 */
        $user = $this->where(array('uid' => $uid))->field(true)->find();
        if (!$user) { //未注册
            /* 获取用户账户信息 */
            $api = new \User\Api\UserApi();
            $info = $api->guideMember_Model->info($uid);
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

    private function upload_guide_info() {
        if (isset($_FILES['idcard_front']) || isset($_FILES['idcard_back']) ||  isset($_FILES['guide_idcard'])) {
            $config = array(
                'maxSize' => 3 * 1024 * 1024, //上传限制3M
                'rootPath' => './Uploads/',
                'savePath' => '',
                'saveName' => array('uniqid', ''),
                'exts' => array('jpg', 'gif', 'png', 'jpeg'),
                'autoSub' => true,
                'subName' => 'User/guide_info',
            );
            $upload = new \Think\Upload($config); // 实例化上传类
            $guide_info_file = $_FILES;
            unset($guide_info_file['head_image']);
            if ($info = $upload->upload($guide_info_file))
                foreach ($info as $key => $file) {
                    $file_path[$key] = substr($config['rootPath'], 1) . $file['savepath'] . $file['savename']; //在模板里的url路径
                }
        }
        return $file_path;
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
            if ($info){
                //生成缩略图
                $path = '.'.substr($config['rootPath'], 1) . $info['savepath'] . $info['savename']; //在模板里的url路径
                $image = new \Think\Image();
                $image->open($path);
                $file_path = explode('/',trim($path,'.'));
                $file_path[count($file_path)-1] = '120_'.$file_path[count($file_path)-1];
                // 按照原图的比例生成一个最大为120*120的缩略图
                if(is_object($image->thumb(120, 120)->save('.'.implode('/',$file_path))))
                    return implode('/',$file_path);
            }

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
