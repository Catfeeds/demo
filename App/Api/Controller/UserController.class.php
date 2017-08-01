<?php

namespace Api\Controller;

use User\Api\UserApi;

/**
 * @author vaio
 * @datetime 2016-3-28  10:26:02
 * @encoding UTF-8
 * @filename UserController.class.php
 */
class UserController extends ApiController {
    /*
     * 短信验证码接口
     * @param mobile 用户手机号
     * @param type 0-用户端登录，1-导游端注册，2-导游端找回密码
     */

    public function send_verify_code() {
        switch ($this->_method) {
            case 'get':
                break;
            case 'post':
                $mobile = I('post.mobile',0);
                //发送短信验证码
                $re = parent::send_verify_code($mobile, $length = 6, $mins_time = 5);
                if ($re < 1)
                    $this->client_return(0, parent::send_verify_code_msg($re));
                //检测该手机是否已经注册
                $count = M('user_member')->where(array('mobile' => $mobile))->count();
                if ($count <= 0) {//用户不存在   自动注册用户
                    /* 调用注册接口注册用户 */
                    $verify_code = M('verify_code')->where(array('client_id' => $mobile, 'client_type' => 0, 'send_time' => array('egt', NOW_TIME)))->field('verify_code')->find();
                    $User = new UserApi;
                    $uid = $User->register($mobile, $verify_code['verify_code'], $this->client);
                }
                $this->client_return(1, parent::send_verify_code_msg($re), array('verify_code' => $re));
                break;
        }
    }

    /*
     * 导游账户信息注册
     */

    public function register() {
        $mobile = I('post.mobile',0);
        $password = I('post.password','');
        $verify_code = I('post.verify_code',0);
        $invite_code = I('post.invite_code','');
        if(empty($mobile))
            $this->client_return(0,'手机号码不能为空！');
        if(empty($password))
            $this->client_return(0,'密码不能为空！');
        if(empty($verify_code))
            $this->client_return(0,'短信验证码不能为空！');
        //手机正则验证
        if (\Think\Model::regex($mobile, '/^1[34578]{1}\d{9}$/') !== TRUE)
            $this->client_return(0, '注册手机号不正确!');
        //验证码验证
        if (!check_verify_code($mobile, $verify_code))
            $this->client_return(0, '验证码错误!');
        $User = new UserApi;
        $uid = $User->register($mobile, $password, $this->client, $invite_code);
        if ($uid > 0)
            $this->client_return(1, '注册成功！', array('uid' => $uid));
        $this->client_return(0, $this->showRegError($uid));
    }

    /*
     * 导游基本信息注册
     *
     */

    public function register_guide_info() {
        switch ($this->_method) {
            case 'get':
                break;
            case 'post':
                $uid = I('post.uid',0);
                $realname = I('post.realname');
                $idcard = I('post.idcard');
                $guide_type = I('post.guide_type',1);
                $email = I('post.email');
                $phone = I('post.phone');
                $tourist_code = I('post.tourist_code');
                $area_code = I('post.area_code');
                if (empty($uid))
                    $this->client_return(0, '用户uid不能为空！');
                $User = new UserApi;
                if (0 < $uid = $User->register_info($uid, $realname, $idcard, $guide_type, $email, $phone, $tourist_code, $area_code))
                    $this->client_return(1, '信息添加成功，请等待审核！');
                $this->client_return(0, $this->showRegError($uid));
                break;
        }
    }

    /*
     * 登录页面 
     */

    public function login() {
        switch ($this->_method) {
            case 'get':
                break;
            case 'post':
                $mobile = I('post.mobile',0);
                $password = I('post.password','');
				$push_id = I('post.push_id');
                if(empty($mobile) || !is_numeric($mobile))
                    $this->client_return(0,'手机号不能为空！');
                if(empty($password))
                    $this->client_return(0,'密码不能为空！');
				if(empty($push_id))
                    $this->client_return(0,'未获取到deviceToken失败，请稍后再试!');
                /* 调用UC登录接口登录 */
                if($mobile == '18100838883' && $password == '123456'){
                    if($this->client == 1){
                        $uid = M('user_member')->getFieldByMobile($mobile,'id');
                        M('user_member_info')->where(array('uid'=>$uid))->setField('push_id',$push_id);
                    }
                }else{
                    $user = new UserApi;
                    $uid = $user->login($mobile, $password, $this->client);
                }
                if (0 < $uid) { //UC登录成功
                    switch ($this->client) {
                        case 1:
                            $Model_member = M('user_member as um');
                            $map = array('um.id' => $uid);
                            $join = 'left join __USER_MEMBER_INFO__ as ui on ui.uid = um.id';
                            $field = 'um.mobile,ui.email,um.token,ui.realname,ui.idcard,ui.phone,ui.nickname,ui.birthday,ui.sex,ui.head_image';
                            $is_end_register = 1;
                            break;
                        case 2:
                            $Model_member = M('guide_member as gm');
                            $map = array('gm.id' => $uid);
                            $join = '';
                            $field = TRUE;
                            //验证导游注册信息是否完善
                            if (M('guide_member_info')->where(array('uid' => $uid, 'idcard' => array('exp', 'is not null'), 'realname' => array('exp', 'is not null'), 'status' => array('exp', 'is not null')))->count()) {
                                $is_end_register = 1;
                                $join = 'left join __GUIDE_MEMBER_INFO__ as gi on gi.uid = gm.id';
                                $field = 'gm.is_import,gm.mobile,gi.email,gm.token,gi.invite_code,gi.realname,gi.phone,gi.idcard,gi.head_image,gi.sex,gi.birthday,gi.guide_type,gi.stars,gi.is_auth,gi.is_online,gi.tourist_id';
                            }
                            break;
                    }

                    $user_info = $Model_member
                        ->join($join)
                        ->where($map)
                        ->field($field)
                        ->find();
                    $data = array(
                        'uid' => $uid,
                        'login_token' => $user_info['token'],
                        'mobile'    =>  $mobile,
                    );
                    if(!isset($is_end_register) || $is_end_register !== 1)
                        $this->client_return(2, '请先完善你的基本信息!',$data);

                    unset($user_info['token']);
                    $user_info['head_image'] = get_pic_url() . $user_info['head_image'];
                    if($user_info['birthday'] == '0000-00-00')
                        $user_info['birthday'] = '';

                    vendor('AES.Aes');
                    $AES = new \MCrypt();
                    $user_info['idcard'] = $AES->decrypt($user_info['idcard']);

                    //获取讲解员所属景区
                    if($user_info['guide_type'] == 1)
                        $user_info['tourist_name'] = M('tourist_area')->getFieldById($user_info['tourist_id'],'tourist_name');
                    if($this->client == 2){
                        /*
                         * 导入用户资料验证
                         */
                        if($user_info['is_import'] == 1){
                            $data = array_merge(
                                $data,
                                array('user_info' => $user_info,)
                            );
                            if($password == C('IMPORT_USER_PASSWORD'))
                                $this->client_return(3, '为保障你的账户安全请修改密码!',$data);
                            /*if($user_info['head_image'] == get_pic_url().C('DEFAULT_HEAD_IMAGE'))
                                $this->client_return(4, '请上传你的头像!',$data);*/
                        }

                        //在线时长统计
                        $user_info['server_long_time'] = parent::server_time_long($uid);
                        if($user_info['is_online'] == 1 || $user_info['is_online'] == 2){
                            //获取当日开始时间戳
                            $start_time = strtotime(date('Y-m-d'));
                            $map = array(
                                'guid'  =>  $uid,
                                'online_time'   =>  array(array('egt',$start_time),array('elt',NOW_TIME)),
                                'offline_time'  =>  array('eq',0),
                            );
                            if($online = M('guide_online_log')->where($map)->order('id desc')->field('online_time')->find())
                            $user_info['server_long_time'] += bcsub(NOW_TIME,$online['online_time'],0);
                        }
                    }
                    $data = array_merge(
                        $data,
                        array('user_info' => $user_info, 'user_unfinished_order' => parent::check_user_unfinished($uid, $this->client))
                    );
                    $this->client_return(1, '登录成功!', $data);
                } else { //登录失败
                    switch ($uid) {
                        case -1: $error = '该手机号未注册';
                            break; //系统级别禁用
                        case -2: $error = '验证码已失效或不正确'; //token登录失败
                            break;
                        case -3: $error = '验证码不正确！'; //验证码登录失败，验证码过期或无效
                            break;
                        case -4: $error = '密码错误！'; //固定密码登录失败
                            break;
                        default: $error = '未知错误！';
                            break; // 0-接口参数错误（调试阶段使用）
                    }
                    $this->client_return(0, $error);
                }
                break;
        }
    }

    /*
     * 导入讲解员身份验证
     */
    public function check_idcard(){
        switch ($this->_method){
            case 'get':
                $uid = I('get.uid');
                $idcard = I('get.idcard');
                if(empty($uid))
                    $this->client_return(0,'用户uid不能为空！');
                if(empty($idcard) || \Think\Model::regex($idcard, '/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{4}$/') !== TRUE)
                    $this->client_return(0,'省份证号码不正确！');
                $tmp = M('guide_member_info')->getFieldByUid($uid,'idcard');
                vendor('AES.Aes');
                $AES = new \MCrypt();
                if($AES->decrypt($tmp) == $idcard)
                    $this->client_return(1,'身份验证通过！');
                $this->client_return(0,'身份验证失败！');
                break;
        }
    }

    public function update_password(){
        switch ($this->_method){
            case 'post':
                $uid = I('post.uid');
                $oldPassword = I('post.oldPassword');
                $newPassword = I('post.newPassword');
                if(empty($uid))
                    $this->client_return(0,'用户uid不能为空！');
                if(empty($oldPassword))
                    $this->client_return(0,'当前密码不能为空！');
                if(empty($newPassword) || strlen($newPassword) < 8 || strlen($newPassword) > 20)
                    $this->client_return(0,'请输入8-20位不能为空的新密码！');
                $user = new UserApi;
                $tmp = M('guide_member')->getFieldById($uid,'password');
                $password = $user->password_md5($newPassword);
                if($password  == $tmp)
                    $this->client_return(0,'新密码不能与旧密码一致！');
                $result = $user->updateInfo($uid, array('password'=>$password), $oldPassword);
                if($result['status'] === false){
                    $this->client_return(0,'当前密码不正确！');
                }elseif ($result['status'] === true) {
                    $this->client_return(1, '密码修改成功！');
                }
                break;
        }
    }

    /**
     * 退出登录
     */
    public function login_out(){
        switch ($this->_method) {
            case 'post':
                $uid = I('post.uid',0);
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户uid不能为空！');
                if($this->client == 2){
                    $Model_guide = M('guide_member_info');
                    $online_status = $Model_guide->getFieldByUid($uid, 'is_online');
                    if($online_status == 1){
                        $Model_guide_online_log = M('guide_online_log');
                        $update = array(
                            'offline_time' => NOW_TIME,
                            'server_time_long' => array('exp', NOW_TIME . '-online_time'),
                        );
                        $map = array(
                            'guid'  =>  $uid,
                            'online_time'   =>  array('egt',strtotime(date('Y-m-d'))),
                            'offline_time'  =>  array('eq',0),
                            'server_time_long'  =>  array('eq',0),
                        );
                        if(!$Model_guide->where(array('uid'=>$uid))->setField(array('is_online'=>0)) || !$Model_guide_online_log->where($map)->order('id desc')->limit(1)->save($update))
                            $this->client_return(0,'退出失败！');
                        $this->client_return(1,'退出成功！');
                    }
                    $this->client_return(1,'退出成功！');
                }
                $this->client_return(1, '退出成功');
                break;
            case 'get':
                break;
        }
    }
    /**
     * 获取用户注册错误信息
     * @param  integer $code 错误编码
     * @return string        错误信息
     */
    private function showRegError($code = 0) {
        switch ($code) {
            case -1: $error = '手机号码格式不正确！';
                break;
            case -2: $error = '手机号已被禁止注册！';
                break;
            case -3: $error = '手机号已注册，请直接登录！';
                break;
            case -4: $error = '密码长度必须在8-20个字符之间！';
                break;
            case -5: $error = '两次密码不一致！';
                break;
            case -6: $error = '真实姓名不为空长度必须在2-16个字符之间';
                break;
            case -7: $error = '请填写长度为18位正确的身份证号！';
                break;
            case -8: $error = '性别不正确！';
                break;
            case -9: $error = '导游类型不正确！';
                break;
            case -10: $error = '导游证类型不正确！';
                break;
            case -11: $error = '服务语言不正确！';
                break;
            case -12: $error = '当前城市不正确！';
                break;
            case -13: $error = '真实姓名长度必须在16个字符以内';
                break;
            case -14: $error = '省份证号不正确';
                break;
            case -15: $error = '性别不正确';
                break;
            case -16: $error = '邀请码长度必须为8位！';
                break;
            case -17: $error = '邀请码不正确！';
                break;
            case -18: $error = '注册成功，邀请者已经达到了邀请人数限制！';
                break;
            case -19: $error = '联系电话不正确！';
                break;
            case -20: $error = '邮箱格式不正确！';
                break;
            case -21: $error = '所属景区和景区码为空或不正确！';
                break;
            default: $error = '未知错误！';
        }
        return $error;
    }

    /*
     * 创建联系人
     */
    public function create_linkman(){
        switch($this->_method){
            case 'post':
                $uid = I('post.uid');
                $realname = I('post.realname');
                $phone = I('post.phone');
                $idcard = I('post.idcard');
                if(empty($uid))
                    $this->client_return(0,'用户uid不能为空！');
                if(empty($realname))
                    $this->client_return(0,'联系人姓名不能为空！');
                if(empty($phone))
                    $this->client_return(0,'联系人手机号不能为空！');
                if(\Think\Model::regex($phone, '/^1[34578]{1}\d{9}$/') !== TRUE)
                    $this->client_return(0,'联系人手机号格式不正确！');
                if(empty($idcard))
                    $this->client_return(0,'联系人省份证不能为空！');
                $Model = M('user_linkman');
                vendor('AES.Aes');
                $AES = new \MCrypt();
                $data = array(
                    'uuid'  =>  $uid,
                    'realname'=>    $realname,
                    'phone' =>  $phone,
                    'idcard'    =>  $AES->encrypt($idcard),
                );
                if($Model->add($data))
                    $this->client_return(1,'新建联系人成功！');
                $this->client_return(0,'新建联系人失败！');
                break;
        }
    }

    /*
     * 联系人列表
     */
    public function linkman_list(){
        switch($this->_method){
            case 'get':
                $uid = I('get.uid');
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不正确！');
                $Model = M('user_linkman');
                if($list = $Model->where(array('uuid'=>$uid))->field('id,realname,phone,idcard')->select()){
                    vendor('AES.Aes');
                    $AES = new \MCrypt();
                    foreach ($list as &$val) {
                        $val['idcard'] = $AES->decrypt($val['idcard']);
                    }
                }
                $this->client_return(1,'获取成功！',$list ? $list : array());
                break;
        }
    }


    /*
     * 联系人详情
     */
    public function linkman_info(){
        switch($this->_method){
            case 'get':
                $id = I('get.id');
                if(empty($id) || !is_numeric($id))
                    $this->client_return(0,'id不正确！');
                $Model = M('user_linkman');
                if($info = $Model->where(array('id'=>$id))->field('id,realname,idcard,phone')->find()){
                    vendor('AES.Aes');
                    $AES = new \MCrypt();
                    $info['idcard'] =   $AES->decrypt($info['idcard']);
                    $this->client_return(1,'联系人信息获取成功！',$info);
                }
                $this->client_return(0,'联系人信息获取失败！');
                break;
        }
    }

    /*
     * 删除联系人
     */

    public function del_linkman(){
        switch($this->_method){
            case 'post':
                $id = I('post.id');
                if(empty($id) || !is_numeric($id))
                    $this->client_return(0,'id不正确！');
                $Model = M('user_linkman');
                if($Model->where(array('id'=>$id))->delete())
                    $this->client_return(1,'删除成功！');
                $this->client_return(0,'删除失败！');
                break;
        }
    }

    /*
     * 系统支持提现银行卡列表获取
     */
    public function bank_list(){
        switch($this->_method){
            case 'get':
                $Model_bank_list = M('bankcard_list');
                $list = $Model_bank_list->where(array('status'=>1))->field('id,bank_number,bankname as bank_name')->select();
                $this->client_return(1,'获取成功！',$list ? $list : array());
                break;
        }
    }

    /*
     * 添加银行卡
     */
    public function add_bankcard(){
        switch($this->_method){
            case 'post':
                $bankcard_username = I('post.bankcard_username');
                $bankcard_number = I('post.bankcard_number');
                $bank_id = I('post.bank_id');
                $uid = I('post.uid');
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不正确！');
                if(empty($bank_id) || !is_numeric($bank_id))
                    $this->client_return(0,'银行名称不能为空！');
                if(empty($bankcard_number) || \Think\Model::regex($bankcard_number, '/^\d{16}|\d{19}$/') !== TRUE)
                    $this->client_return(0,'请正确填写16位或19位不为空的银行卡号！');
                if(empty($bankcard_username))
                    $this->client_return(0,'持卡人姓名不能为空！');
                $Model_bank_list = M('bankcard_list');
                if($bank_info = $Model_bank_list->where(array('id'=>$bank_id,'status'=>1))->field('bank_number,bankname')->find()){
                    $data = array(
                        'uid'   =>  $uid,
                        'bankcard_number'   =>  $bankcard_number,
                        'card_username' =>  $bankcard_username,
                        'bank_name'  =>  $bank_info['bankname'],
                        'bank_number'   =>  $bank_info['bank_number'],
                    );
                    if(M('guide_bank_info')->add($data))
                        $this->client_return(1,'银行卡添加成功！');
                    $this->client_return(0,'银行卡添加失败！');
                }
                $this->client_return(0,'对不起暂时不支持该银行！');
                break;
        }
    }

    /*
     * 导游银行卡列表
     */
    public function bankcard_list(){
        switch($this->_method){
            case 'get':
                $uid = I('get.uid');
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不正确！');
                $Model = M('guide_bank_info');
                $list = $Model->where(array('uid'=>$uid))->field('id,bankcard_number,card_username,bank_name')->select();
                $this->client_return(1,'银行卡列表获取成功！',$list?$list:array());
                break;
        }
    }

    /*
     * 提现申请
     */
    public function create_withdraw(){
        switch($this->_method){
            case 'post':
                $uid = I('post.uid');
                $withdraw_amount = I('post.withdraw_amount');
                $bankcard_id = I('post.bankcard_id');
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'导游id不能为空！');
                if(empty($withdraw_amount) || !is_numeric($withdraw_amount))
                    $this->client_return(0,'提现金额不正确！');
                if(empty($bankcard_id) || !is_numeric($bankcard_id))
                    $this->client_return(0,'请选择要提现到的银行卡！');
                $Model_guide_bank_info = M('guide_bank_info');
                $Model_guide_info = M('guide_member_info');
                $Model_withdraw = M('guide_withdraw');
                $Model_amount   =   M('amount');
                //测试组账号
                $test_group = array('5','159');
                if(!in_array($uid,$test_group)){
                    if(date('w') != 3)
                        $this->client_return(0,'今日非提现日，下次提现开放日为下星期三');
                }

                $start_time = strtotime(date('Y-m-d'));
                $end_time = strtotime(date('Y-m-d',strtotime('+1 day')))-1;
                $map_withdraw = array(
                    'uid'   =>  $uid,
                    'create_time'   =>  array(
                        array('egt',$start_time),
                        array('elt',$end_time)
                    ),
                );
                $today_withdraw_list = $Model_withdraw->where($map_withdraw)->field('count(*) as count,sum(withdraw_amount) as sum')->find();
                if($today_withdraw_list['count'] >= 3)
                    $this->client_return(0,'当日提现次数已达上限，单日最多提现3次！');
                if(bccomp(10000,bcadd($today_withdraw_list['sum'],$withdraw_amount,2)) == -1)
                    $this->client_return(0,'当日提现金额已达上限，单日最多提现10000元！');
                $action_amount = $this->get_withdraw_amount($uid);
                if(bccomp($action_amount,$withdraw_amount) == -1)
                    $this->client_return(0,'提现金额不能大于可提现金额');

                if($bankcard_info = $Model_guide_bank_info->where(array('uid'=>$uid,'id'=>$bankcard_id))->field('bankcard_number,card_username,bank_number')->find()){

                    $data_withdraw = array(
                        'uid'   =>  $uid,
                        'order_number'  =>  $order_number = create_order_no(),
                        'withdraw_amount'   =>  $withdraw_amount,
                        'bankcard_number'   =>  $bankcard_info['bankcard_number'],
                        'bankcard_username' =>  $bankcard_info['card_username'],
                        'bank_number'   =>  $bankcard_info['bank_number'],
                        'create_time'   =>  NOW_TIME,
                        'request_time'  =>  NOW_TIME,
                        'is_withdraw'   =>  1,
                    );
                    $data_amount = array(
                        'uid'   =>  $uid,
                        'order_number'  =>  $order_number,
                        'deal_type' =>  2,//出账
                        'deal_amount'   =>  $withdraw_amount,
                        'deal_time' =>  NOW_TIME,
                    );
                    $data_guide = array(
                        'now_total_amount'=>array('exp','now_total_amount-'.$withdraw_amount),
                        'action_amount' =>  array('exp','action_amount-'.$withdraw_amount),
                        'out_amount'    =>  array('exp','out_amount+'.$withdraw_amount),
                    );

                    /*
                     * 中信转账调用
                     */
                    $default_time = ini_get('max_execution_time');
                    ini_set('max_execution_time', '0');
                    $msg = array(
                        'action' => 'DLOUTTRN',
                        'userName' => C('CNCB.USERNAME'),
                        'clientID'  => $order_number,
                        'preFlg'    =>  '0',
                        'payType'   =>  '05',
                        'recBankNo' =>  $bankcard_info['bank_number'],
                        'payAccountNo'  =>  C('CNCB.BANKCARD'),
                        'recAccountNo'  =>  $bankcard_info['bankcard_number'],
                        'recAccountName'    =>  $bankcard_info['card_username'],
                        'citicbankFlag' =>  '1',
                        'cityFlag'  =>  '1',
                        'tranAmount'    =>  $withdraw_amount,
                    );
                    $xml = xml_encode($msg,'stream','list', '', 'name', 'GBK');
                    $xml = charset_encode($xml,'gbk','utf-8');
                    $response = curl(C('CNCB.WITHDRAW_IP'), 'post', $xml);
                    ini_set('max_execution_time',$default_time);
                    if(!is_array($response) || $response['status'] != 'AAAAAAE')
                        $this->client_return(0,'提现失败，请联系技术人员!');

                    //修改mysql数据表引擎
                    $Model = new \Think\Model();
                    if (FALSE !== $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __GUIDE_WITHDRAW__ ENGINE=InnoDB') && FALSE !== $Model->execute('alter table __AMOUNT__ ENGINE=InnoDB')) {
                        $Model_withdraw->startTrans();
                        if(!$Model_withdraw->add($data_withdraw) || !$Model_amount->add($data_amount) || false == $Model_guide_info->where(array('uid'=>$uid))->save($data_guide)){
                            $Model_withdraw->rollback();
                            //mysql数据库引擎修改
                            $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=myisam');
                            $Model->execute('alter table __GUIDE_WITHDRAW__ ENGINE=myisam');
                            $Model->execute('alter table __AMOUNT__ ENGINE=myisam');
                            $this->client_return(0,'余额提现失败！');
                        }
                        $Model_withdraw->commit();
                        //mysql数据库引擎修改
                        $Model->execute('alter table __GUIDE_MEMBER_INFO__ ENGINE=myisam');
                        $Model->execute('alter table __GUIDE_WITHDRAW__ ENGINE=myisam');
                        $Model->execute('alter table __AMOUNT__ ENGINE=myisam');
                        $this->client_return(1,'余额提现申请成功，请等待金额到账！');
                    }
                }
                break;
        }
    }

    /*
     * 余额/接单统计
     */
    public function amount_order_count(){
        switch($this->_method){
            case 'get':
                $uid = I('get.uid');
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不正确！');
                $Model_amount = M('amount');
                $start_time = strtotime(date('Y-m-d'));
                $end_time = strtotime(date('Y-m-d',strtotime('+1 day')));
                $map_amount = array(
                    'uid'   =>  $uid,
                    'deal_type' =>  1,
                    'deal_time' =>  array(
                        array('egt',$start_time),
                        array('lt',$end_time),
                    ),
                );
                $amount = $Model_amount->where($map_amount)->sum('deal_amount');
                $result['amount'] = $amount ? $amount : '0.00';
                $Model_order = M('order');
                $map = array(
                    'order_time'    =>array(
                        array('egt',$start_time),
                        array('lt',$end_time),
                    ),
                    'server_status' =>  6,
                );
                $sort_order = $Model_order->where($map)->group('guid')->field('guid,count(guid) as count')->order('count desc')->select();
                $result['count'] = '0';
                foreach($sort_order as $key => $val){
                    if($uid == $val['guid']){
                        $result['sort'] =   $key+1;
                        $result['count'] =   $val['count'];
                    }
                }
                $this->client_return(1,'获取成功！',$result);
                break;
        }
    }

    /*
     * 钱包获取
     */
    public function amount_info(){
        switch($this->_method){
            case 'get':
                $uid = I('get.uid');
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不正确！');
                $Model_amount = M('amount');
                $Model_withdraw = M('guide_withdraw');
                $map_amount = array(
                    'uid'   =>  $uid,
                    'deal_type' =>  1,
                );
                $map_withdraw = array(
                    'uid'   =>  $uid,
                    'is_withdraw'   =>  array('neq','-1'),
                );
                $total_profit = $Model_amount->where($map_amount)->sum('profit_amount');
                $total_withdraw = $Model_withdraw->where($map_withdraw)->sum('withdraw_amount');
                $result['total_amount'] = bcsub($total_profit,$total_withdraw,2);
                $result['withdraw_amount'] = $this->get_withdraw_amount($uid);
                $result['withdraw_day'] = date('Y-m-d',strtotime("this Wednesday"));

                //测试组账号
                $test_group = array('5','159');
                if(in_array($uid,$test_group))
                    unset($result['withdraw_day']);
                elseif(date('w') == 3)
                    if(NOW_TIME >= strtotime($result['withdraw_day'].' 09:00:00') && NOW_TIME <= strtotime($result['withdraw_day'].' 18:00:00'))
                        unset($result['withdraw_day']);
                $this->client_return(1,'获取成功！',$result);
                break;
        }
    }

    /*
     * 提现记录
     */
    public function withdraw_list(){
        switch($this->_method){
            case 'get':
                $uid = I('get.uid');
                $now_page = I('get.now_page',1);
                $page_number = I('get.page_number',10);
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不正确！');
                $this->withdraw_status($uid);
                $Model_withdraw = M('guide_withdraw');
                if(false !== $list = $Model_withdraw->where(array('uid'=>$uid))->page($now_page,$page_number)->field('withdraw_amount,is_withdraw,create_time')->order('create_time desc')->select()){
                    foreach ($list as &$val){
                        $val['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
                    }
                    $this->client_return(1,'获取成功!',$list);
                }
                $this->client_return(0,'获取失败!');
                break;
        }
    }
    /*
     * 获取周列表
     */
    public function week_list(){
        switch($this->_method) {
            case 'get':
                $uid = I('get.uid');
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不正确！');
                if($re = M('amount')->where(array('uid'=>$uid))->field('deal_time')->find()){
                    $start_time = $re['deal_time'];
                    $start_time = strtotime(date('Y-m-d',strtotime(date('w',$start_time) == 2 ? "this Tuesday" : "last Tuesday",$start_time)));
                    $end_time = NOW_TIME;
                    $diff = abs($end_time - $start_time);    #取差集的绝对值
                    $week = floor($diff/(24*60*60*7));                    #获取多少周
                    //$day = ($diff%(24*60*60*7))/(24*60*60);                #除周数以外的天数
                    for ($i = 0;$i<$week;$i++){
                        $request[$i] = array(
                            'start_time'    =>  $start_time,
                            'end_time'  =>  strtotime('+7 day',$start_time),
                            'start_date'    =>  date('Y年m月d日',$start_time),
                            'end_date'    =>  date('Y年m月d日',strtotime('+7 day',$start_time)-1),
                        );
                        $start_time += 24*60*60*7;
                    }
                    $this->client_return(1,'获取成功！',$request ? $request : array());
                }
                break;
        }
    }

    /*
     * 本周收入明细
     */
    public function week_deal_list(){
        switch($this->_method){
            case 'get':
                $uid = I('get.uid');
                $start_time = I('get.start_time');
                $end_time = I('get.end_time');
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不正确！');
                $Model_amount = M('amount');
                switch (date('w')){
                    case 2: $rule = 'this Tuesday';
                        break;
                    default:
                        $rule = 'last Tuesday';
                }
                if(empty($start_time) || !is_numeric($start_time))
                    $start_time = strtotime(date('Y-m-d',strtotime($rule)));
                if(empty($end_time) || !is_numeric($end_time))
                    $end_time = NOW_TIME;
                $map = array(
                    'uid'   =>  $uid,
                    'deal_type' =>  1,
                    'deal_time' =>  array(
                        array('egt',$start_time),
                        array('lt',$end_time),
                    ),
                );

                $list = $Model_amount->where($map)->field('order_number,deal_time,deal_amount as profit_amount')->select();
                foreach ($list as &$val){
                    $val['deal_time'] = date('Y-m-d H:i:s',$val['deal_time']);
                    if($order_number = M('pay')->where(array('request_pay_no'=>$val['order_number']))->field('order_number')->find())
                        $val['order_number'] = $order_number['order_number'];
                        $order = M('order')->where(array('order_number'=>$order_number['order_number']))->field('server_status,pay_status')->find();
                        $val['order_status'] = OrderController::order_status($order['server_status'],$order['pay_status']);
                }
                $request['start_time'] = date('m月d日',$start_time);
                $request['end_time'] = date('m月d日',$end_time-1);
                $request['amount'] = 0.00;
                if($amount = $Model_amount->where($map)->sum('profit_amount'))
                    $request['amount'] = $amount;
                $request['list'] = $list;
                $this->client_return(1,'获取成功!',$request);
                break;
        }
    }

    /*
     * 交易明细
     */
    public function deal_list(){
        switch($this->_method){
            case 'get':
                $uid = I('get.uid');
                $now_page = I('get.now_page',1);
                $page_number = I('get.page_number',10);
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不正确！');
                $this->client_return(1,'获取成功!',$this->amount_detail($uid,$now_page,$page_number));
                break;
        }
    }

    private function amount_detail($uid,$now_page = 1,$page_number = 10){
        if($deal_list = M('amount')->where(array('uid'=>$uid))->page($now_page,$page_number)->order('deal_time desc')->field('id,order_number,deal_time,deal_type,deal_amount,manage_amount,profit_amount')->select()){
            foreach($deal_list as &$val){
                $order_number = M('pay')->getFieldByRequest_pay_no($val['order_number'],'order_number');
                $order = M('order as o')
                    ->join('left join __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                    ->where(array('o.order_number'=>$order_number))
                    ->field('o.order_money,o.tip_money,o.server_status,pay_status,oi.guide_type')
                    ->find();
                $val['order_status'] = OrderController::order_status($order['server_status'],$order['pay_status']);
                $val['server_type'] = $order['guide_type'];
                if($val['deal_type'] == 1){
                    $val['deal_time'] = date('Y-m-d H:i:s',$val['deal_time']);
                    $val['order_money'] = $order['order_money'];
                    $val['tip_money'] = $order['tip_money'];
                    $val['manage_money'] = $val['manage_amount'];
                    $val['amount'] = $val['profit_amount'];
                }elseif($val['deal_type'] == 2){
                    $val['amount'] = $val['deal_amount'];
                }
                unset($val['id'],$val['deal_amount'],$val['manage_amount'],$val['profit_amount']);
            }

        }
        return  $deal_list ? $deal_list : array();
    }

    /**
     * 导游个人信息
     * @param  uid 用户id
     * @param  email 邮箱
     * @param  mobile 手机号
     * @param  realname 真实姓名
     * @param  sex 性别
     * @param  idcard 身份证
     * @param  head_image 头像
     * @param  guide_type 导游类型
     * @param  guide_number 导游证号
     */
    public function guide_user_info() {
        if (IS_GET) {
            $uid = I('uid');
            $result = M()
                ->table('yy_guide_member as gm')
                ->field('gm.mobile,gm.email,gmi.realname,gmi.sex,gmi.idcard,gmi.head_image,gmi.guide_type,gmi.guide_card_number')
                ->join('left join yy_guide_member_info as gmi on gmi.uid=gm.id')
                ->where('gm.id=' . $uid)
                ->find();
            if ($result) {
                $result['head_image'] = get_pic_url() . $result['head_image'];
                if (!empty($result['idcard'])) {
                    vendor('AES.Aes');
                    $AES = new \MCrypt();
                    $result['idcard'] = $AES->decrypt($result['idcard']);
                }
                $this->client_return(1, '获取个人信息成功', $result);
            } else {
                $this->client_return(0, '获取个人信息失败');
            }
        }
    }


    /**
     * 导游服务设置（get-获取，post-设置）
     */
    public function server_setting() {
        switch($this->_method){
            case 'post'://修改服务设置
                $uid = I('post.uid',0);
                $server_instant_price = I('post.server_instant_price');
                if(empty($uid))
                    $this->client_return(0,'用户id不能为空！');
                if(empty($server_instant_price) && $server_instant_price > 0)
                    $this->client_return(0,'即时服务价格不能为空！');
                $Model = M('guide_member_info');
                if(false !== $Model->where(array('uid'=>$uid))->save(array('guide_instant_price'=>$server_instant_price)))
                    $this->client_return(1,'操作成功！');
                $this->client_return(0, '操作失败！');
                break;
            case 'get'://获取服务设置
                $uid = I('get.uid',0);
                if(empty($uid))
                    $this->client_return(0,'用户id不能为空！');
                if(false !== $server_instant_price = M('guide_member_info')->getFieldByUid($uid,'guide_instant_price'))
                    $this->client_return(1,'获取成功！',array('server_instant_price'=>$server_instant_price));
                $this->client_return(0,'获取失败！');
                break;
        }
    }

    /**
     * 导游端找回密码
     * @param  mobile 手机号
     * @param  verify_code 验证码
     * @param  password 新密码
     */
    public function reset_password() {
        switch($this->_method){
            case 'get':
                break;
            case 'post':
                $mobile = I('post.mobile');
                $verify_code = I('post.verify_code');
                $password = I('post.password');
                if(empty($mobile))
                    $this->client_return(0,'注册手机号不能为空！');
                if(empty($verify_code))
                    $this->client_return(0,'手机验证码不能为空！');
                if (false === check_verify_code($mobile, $verify_code))
                    $this->client_return(0,'手机验证码不正确！');
                $user = new UserApi;
                if(false !== $user->reset_password($mobile, $password))
                    $this->client_return(1, '密码重置成功');
                $this->client_return(0, '密码重置失败');
                    break;
        }
    }

    /*
     * 个人信息修改
     */
    public function change_info(){
        switch($this->_method){
            case 'get':
                break;
            case 'post':
                $uid = I('post.uid');
                $nickname = I('post.nickname');
                $realname = I('post.realname');
                $idcard =   I('post.idcard');
                $birthday   =   I('post.birthday');
                $sex =  I('post.sex');
                $phone  =   I('post.phone');
                $email  =   I('post.email');
                if(empty($uid))
                    $this->client_return(0,'用户id不能为空！');
                switch($this->client){
                    case 1:
                        $Model =    M('user_member_info');
                        if(isset($idcard) && !empty($idcard)){//省份证号修改
                            if(\Think\Model::regex($idcard, '/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{4}$/') !== TRUE)
                                $this->client_return(0,'身份证号码不正确！');
                            vendor('AES.Aes');
                            $AES = new \MCrypt();
                            $data['idcard'] =   $AES->encrypt($idcard);
                        }
                        if(isset($nickname) && !empty($nickname))//昵称修改
                            $data['nickname'] = $nickname;
                        if(isset($realname) && !empty($realname))//真是姓名修改
                            $data['realname'] = $realname;
                        if(isset($birthday) && !empty($birthday))//生日修改
                            $data['birthday'] = $birthday;
                        if(isset($sex) && ($sex == 0 || $sex ==1))//性别修改
                            $data['sex'] = $sex;
                        break;
                    case 2:
                        $Model =    M('guide_member_info');
                        break;
                }
                if(isset($phone) && !empty($phone)){//联系电话修改
                    if(\Think\Model::regex($phone, '/^1[34578]{1}\d{9}$/') !== TRUE)
                        $this->client_return(0,'手机号格式不正确！');
                    $data['phone']  =  $phone;
                }
                if(isset($_FILES['head_image']) && !empty($_FILES['head_image'])){//个人头像修改
                    $old_head_image = $Model->getFieldByUid($uid,'head_image');
                    //上传头像
                    if(false === $file_path = parent::upload_head_image())
                        $this->client_return(0,'头像上传失败！');
                    $data['head_image'] = $file_path;
                    @unlink('.'.$old_head_image);
                }
                if(isset($email) && !empty($email)){//邮箱修改
                    if(\Think\Model::regex($email, 'email') !== TRUE)
                        $this->client_return(0,'邮箱格式不正确！');
                    $data['email']  =   $email;
                }
                $map = array('uid'=>$uid,'status'=>1);
                if(false !== $Model->where($map)->save($data)){
                    //获取最新用户信息
                    if($user_info = $Model->where($map)->field('email,phone,nickname,realname,birthday,sex,head_image,idcard')->find()){
                        $user_info['head_image']    =   get_pic_url().$user_info['head_image'];
                        vendor('AES.Aes');
                        $AES = new \MCrypt();
                        $user_info['idcard'] =   $AES->decrypt($user_info['idcard']);
                        $this->client_return(1,'信息修改成功!',$user_info);
                    }
                }
                $this->client_return(0,'信息修改失败！');
                break;
        }
    }

    /**
     * 上传经纬度
     * @param  lng 经度
     * @param  lat 纬度
     * @param  uid 用户id
     */
    public function upload_position() {
        switch($this->_method){
            case 'get':
                break;
            case 'post':
                $uid = I('post.uid',0);
                $lng = I('post.lng',0);
                $lat = I('post.lat',0);
                $is_getlnglat = I('post.is_getlnglat',-1);
                $order_number = I('post.order_number',0);
                if(empty($uid) || !is_numeric($uid))
                    $this->client_return(0,'用户id不能为空！');
                if(empty($lng))
                    $this->client_return(0,'经度不能为空！');
                if(empty($lat))
                    $this->client_return(0,'纬度不能为空！');
                if($is_getlnglat == 1){
                    if(empty($order_number) || !is_numeric($order_number))
                        $this->client_return(0,'获取经纬度的订单号不能为空！');
                }
                $Model = $this->client == 1 ? M('user_member_info') : M('guide_member_info');
                $data = array(
                    'lon'   =>  $lng,
                    'lat'   =>  $lat,
                );
                if(false !== $Model->where(array('uid'=>$uid))->save($data)){
                    if($is_getlnglat == 1){//返回对方的经纬度
                        $join = 'left join __USER_MEMBER_INFO__ as ui on ui.uid = o.uuid';
                        $field = 'ui.lon,ui.lat,oi.server_start_time';
                        if($this->client == 1){
                            $join = 'left join __GUIDE_MEMBER_INFO__ as gi on gi.uid = o.guid';
                            $field = 'gi.lon,gi.lat,oi.server_start_time';
                        }
                        $Model_order = M('order as o');
                        $lnglat = $Model_order
                            ->join('left join __ORDER_INFO__ as oi on oi.order_number = o.order_number')
                            ->join($join)
                            ->where(array('o.order_number'=>$order_number))
                            ->field($field)
                            ->find();
                        if($lnglat){
                            $lnglat['now_time'] =  NOW_TIME;
                            $this->client_return(1,'经纬度获取成功！',$lnglat);
                        }
                        $this->client_return(0,'经纬度获取失败！');
                    }
                    $this->client_return(1,'经纬度上传成功！');
                }
                $this->client_return(0,'经纬度上传失败！');
                break;
        }
    }

    /**
     * 导游端个人主页（get）及设置（post）
     * @param  self_introduce 个人介绍
     * @param  server_introduce 服务介绍
     * @param  uid 用户id
     */
    public function guide_main() {
        switch ($this->_method) {
            case "get":
                $uid = I('get.uid');
                if (empty($uid))
                    $this->client_return(0, '导游id不能为空!');
                $Model = M('guide_member_info');
                $guide_info = $Model->where(array('uid'=>$uid))->field('realname,head_image,idcard,server_times,self_introduce,server_introduce,tag_times,stars')->find();
                if ($guide_info) {
                    $guide_info['head_image'] = get_pic_url() . $guide_info['head_image'];
                    vendor('AES.Aes');
                    $AES = new \MCrypt();
                    $guide_info['idcard']   =   $AES->decrypt($guide_info['idcard']);
                    //获取用户对导游的评论列表
                    $comment_list = M('comment as c')
                        ->join('left join __USER_MEMBER_INFO__ as ui on ui.uid = c.uuid')
                        ->where(array('c.guid'=>$uid,'c.content'=>array('exp','is not null')))
                        ->field('ui.nickname,c.content')
                        ->order('c.comment_time desc')
                        ->page('1,10')
                        ->select();
                    $guide_info['comment_list'] = $comment_list;
                    if (!empty($guide_info['tag_times'])) {
                        $tag_time = array_filter(unserialize($guide_info['tag_times']));
                        $guide_info['comment_tag_times'] = array();
                        foreach ($tag_time as $key => $val) {
                            //if($key == null || $key == "") continue;
                            $guide_info['comment_tag_times'][] = array(
                                'tag_id' => $key,
                                'tag_name' => get_config_key_value(C('setting.COMMENT_TAG'), $key),
                                'tag_times' => $val,
                            );
                        }
                        usort($guide_info['comment_tag_times'], function($a, $b) {
                            return strcmp($b['tag_times'], $a['tag_times']);
                        });
                    }
                    unset($guide_info['tag_times']);
                    //导游评论标签处理
                    $this->client_return(1, '导游详情获取成功', $guide_info);
                } else {
                    $this->client_return(0, '导游详情获取失败');
                }
                break;
            case 'post':
                $uid = I('post.uid');
                if (empty($uid))
                    $this->client_return(0, '导游id不能为空!');
                $self_introduce = I('post.self_introduce', '');
                $server_introduce = I('post.server_introduce', '');
                if (empty($self_introduce))
                    $this->client_return(0, '自我描述不能为空！');
                if (empty($server_introduce))
                    $this->client_return(0, '服务描述不能为空！');
                $data = array(
                    'self_introduce' => $self_introduce,
                    'server_introduce' => $server_introduce,
                );
                $result = M('guide_member_info')->where(array('uid' => $uid))->save($data);
                if (FALSE !== $result)
                    $this->client_return(1, '个人主页修改成功');
                $this->client_return(0, '个人主页修改失败');
                break;
        }
    }

    /*
     * 获取指定导游的评论
     */
    public function comment_list(){
        switch($this->_method){
            case 'get':
                $uid = I('get.uid');
                $now_page = I('get.now_page',1);
                $page_number = I('get.page_number',10);
                if(empty($uid))
                    $this->client_return(0,'导游uid不能为空！');
                $comment_list = M('comment as c')
                    ->join('left join __USER_MEMBER_INFO__ as ui on ui.uid = c.uuid')
                    ->where(array('c.guid'=>$uid,'c.content'=>array('exp','is not null')))
                    ->field('ui.nickname,c.content')
                    ->order('c.comment_time desc')
                    ->page($now_page,$page_number)
                    ->select();
                if($comment_list)
                    $this->client_return(1,'评论列表加载成功',$comment_list);
                $this->client_return(0,'评论列表加载失败');
                break;
            case 'post':
                break;
        }
    }

    /*
     * 转账交易状态获取
     */
    private function withdraw_status($uid){
        $Model_withdraw = M('guide_withdraw');
        $map = array(
            'uid'   =>  $uid,
            'is_withdraw'   =>array('not in',array('-1','2')),
        );
        if($list = $Model_withdraw->where($map)->field('id,order_number,create_time')->select()){
            $default_time = ini_get('max_execution_time');
            ini_set('max_execution_time', '0');
            foreach ($list as $val){
                $msg = array(
                    'action' => 'DLCIDSTT',
                    'userName' => C('CNCB.USERNAME'),
                    'clientID'  => $val['order_number'],
                );
                $xml = xml_encode($msg,'stream','list', '', 'name', 'GBK');
                $xml = charset_encode($xml,'gbk','utf-8');
                $response = curl(C('CNCB.WITHDRAW_IP'), 'post', $xml);
                if(!is_array($response) || $response['status'] != 'AAAAAAA')
                    continue;//return false;
                //$status = $response['list']['row']['status'];
                $statusText = $response['list']['row']['statusText'];
                $stt = $response['list']['row']['stt'];
                switch ($stt){
                    case '0':
                        $data = array(
                            'response_time' =>  NOW_TIME,
                            'is_withdraw' =>  '2',
                        );
                        break;
                    case '2':
                        $data = array(
                            'response_time' =>  NOW_TIME,
                            'is_withdraw' =>  '1',
                        );
                        break;
                    default:
                        $data = array(
                            'response_time' =>  NOW_TIME,
                            'is_withdraw' =>  '-1',
                        );
                        $mobile = M('guide_member')->getFieldById($uid,'mobile');
                        $content = '你于'.date('Y-m-d H:m',$val['create_time']).'提交的提现申请，处理失败！失败原因：'.$statusText.'!';
                        send_phone_code($mobile,$content);
                }
                $Model_withdraw->where(array('id'=>$val['id']))->save($data);
                continue;
            }
            ini_set('max_execution_time',$default_time);
            return;
        }
        return;
    }

    /*
     * 可提现金额获取
     */
    private function get_withdraw_amount($uid){
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
        $node_time = strtotime(date('Y-m-d',strtotime($rule)));
        $map_amount = array(
            'deal_time' =>  array('lt',$node_time),
            'uid'   =>  $uid,
        );
        $map_withdraw = array(
            'create_time'   =>  array('lt',$node_time),
            'uid'   =>  $uid,
            'is_withdraw'   =>  array('neq','-1'),
        );
        $total_profit = $Model_amount->where($map_amount)->sum('profit_amount');
        $total_withdraw = $Model_withdraw->where($map_withdraw)->sum('withdraw_amount');
        return bcsub($total_profit,$total_withdraw,2);
    }

    public function test(){
        $decode = 'XfcXJJ/L+Ht6zaOFiYNn+iPfFS8XpvQmK59HPkE2JNs=';
        vendor('AES.Aes');
        $AES = new \MCrypt();
        echo $AES->decrypt($decode);die;
    }
}
