<?php
/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/8/1
 * Time: 15:05
 */

namespace Manage\Controller;


use Think\Controller;
use User\Api\UserApi;

class PublicController extends Controller{

    public function _initialize(){
        $this->group_title = '管理员中心';
    }

    /**
     * 后台用户登录
     */
    public function login($username = null, $password = null, $verify = null){
        if(IS_POST){
            // 检测验证码
            if(!check_verify($verify)){
                $this->error('验证码输入错误！');
            }

            //调用 Member 模型的 login 方法，验证用户名、密码
            $Member = D('ManageMember');
            $uid = $Member->login($username, $password);
            if(0 < $uid){ // 登录成功，$uid 为登录的 UID
                //跳转到登录前页面
                $this->success('登录成功！', U(''),true);
            } else { //登录失败
                switch($uid) {
                    case -1: $error = '用户不存在或被禁用！'; break; //系统级别禁用
                    case -2: $error = '密码错误！'; break;
                    default: $error = '未知错误！'; break; // 0-接口参数错误（调试阶段使用）
                }
                $this->error($error,'',true);
            }
        } else {
            if(is_login()){
                $this->redirect('Index/index');
            }else{
                /* 读取数据库中的配置 */
                $config	=	S('DB_CONFIG_DATA');
                if(!$config){
                    $config	=	D('Config')->lists();
                    S('DB_CONFIG_DATA',$config);
                }
                C($config); //添加配置

                $this->display();
            }
        }
    }

    //退出登录 ,清除 session
    public function logout(){
        if(is_login()){
            D('ManageMember')->logout();
            session('[destroy]');
            $this->success('退出成功！', U('login'));
        } else {
            $this->redirect('login');
        }
    }

    //生成 验证码
    public function verify(){
        $config =    array(
            'fontSize'    =>    16,    // 验证码字体大小
            'length'      =>    3,     // 验证码位数
            'useCurve'  =>  false,  //关闭验证码混淆线
            'codeSet'   =>  '123456789',//设置验证码字符集
        );
        $verify = new \Think\Verify($config);
        $verify->entry(1);
    }


    /**
     * 修改昵称
     */
    public function updateNickname(){
        $uid = is_login();
        if(IS_POST){
            //获取参数
            $nickname = I('post.nickname');
            $password = I('post.password');
            empty($nickname) && $this->error('请输入昵称');
            empty($password) && $this->error('请输入密码');

            //验证原密码是否正确
            $re = self::verifyUser($uid,$password);
            if($re !== true)
                $this->error($re);

            if(M('ManageMember')->where(array('uid'=>$uid))->setField('nickname',$nickname)){
                $user               =   session('user_auth');
                $user['username']   =  $nickname;
                session('user_auth', $user);
                session('user_auth_sign', data_auth_sign($user));
                $this->success('修改昵称成功！');
            }
            $this->error('修改昵称失败！');
        }
        $nickname = M('ManageMember')->getFieldByUid($uid, 'nickname');
        $this->assign('nickname', $nickname);
        $this->meta_title = '修改昵称';
        $this->display();
    }

    /**
     * 修改密码
     */
    public function updatePassword(){
        if(IS_POST){
            $uid = is_login();
            //获取参数
            $old   =   I('post.old');
            empty($old) && $this->error('请输入原密码');
            $data['password'] = I('post.password');
            empty($data['password']) && $this->error('请输入新密码');
            $repassword = I('post.repassword');
            empty($repassword) && $this->error('请输入确认密码');

            if($data['password'] !== $repassword){
                $this->error('您输入的新密码与确认密码不一致');
            }

            //验证原密码是否正确
            $re = self::verifyUser($uid,$old);
            if($re !== true)
                $this->error($re);

            $newPassword = \User\Api\UserApi::password_md5($repassword);
            if(M('ManageMember')->where(array('uid'=>$uid))->setField('password',$newPassword))
                $this->success('修改密码成功！');
            $this->error('密码修改失败!');

        }
        $this->meta_title = '修改密码';
        $this->display();
    }

    /*
     * 验证用户密码是否正确
     */
    private function verifyUser($uid,$old){
        $password = M('ManageMember')->getFieldByUid($uid, 'password');
        if($password !== \User\Api\UserApi::password_md5($old))
            return '验证出错：密码不正确！';
        return true;
    }
}