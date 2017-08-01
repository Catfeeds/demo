<?php
/**
 * Created by PhpStorm.
 * User: wt
 * Date: 2016/8/3
 * Time: 9:41
 */
namespace Manage\Controller;

use Think\Controller;

class EditionController extends AdminController{

    public function _initialize($group_title) {
        parent::_initialize($group_title);
        $this->group_title = '版本控制';
    }

    public function edition_list(){
        $platform = I('platform','andr');
        $now_page = I('get.page',1);
        $page_size = 15;
        $get_type = I('get.guide_type');
        $key = I('get.key');
        $start_time = I('get.start_time');
        $end_time = I('get.end_time');
        $end_time .='23:59:59';
        /*if(!$key){
            $where = array(
                '_logic' => 'or',
            );
            $map['_complex'] = $where;
        }*/
        if(!empty($get_type) && in_array($get_type,array('1','2')))
            $map['client'] = $get_type;
        if(!empty($start_time))
            $map['update_time'] = array('egt',strtotime($start_time));//大于等于
        if(!empty($end_time))
            $map['update_time'] = array('elt',strtotime($end_time));//小于等于
        if(!empty($start_time)&&!empty($end_time))
            $map['update_time'] = array(
                array('egt',strtotime($start_time)),
                array('elt',strtotime($end_time)),
            );
        $map['platform'] = $platform;
        $model = M('version');
        $count = $model->where($map)->count();
        $list = M('version')
            ->where($map)
            ->order('update_time desc')
            ->page($now_page, $page_size)
            ->select();
        foreach($list as &$val){
            $val['update_time'] = date('Y-m-d H:i:s',$val['update_time']);
        }
        $pages = intval(ceil($count/$page_size));
        $info = array(
            'list'=>$list,
            'pages'=>$pages,
            'get'=>$_GET
        );
        if($info){
            $this->assign('list',$info);
            $this->meta_title = '版本列表';
            $this->display();
        }
    }

    /**
     * 上传版本
     */
    public function edition_upload(){
        $id = I('id');
        $platform = I('platform');
        if(IS_POST){
            $arr['version_code'] = I('post.version_code');
            $arr['version_title'] = I('post.version_title');
            $arr['update_content'] = I('post.update_content');
            $arr['client'] = I('post.guide_type');
            $arr['update_time'] = time();
            $arr['platform'] = $platform;
            $arr['status']  =   1;
            if($arr['platform'] == 'ios')
                $arr['status']  =   I('post.status');

            if(empty($arr['version_code']))
                $this->error('版本编号不能为空！','',true);
            if(empty($arr['version_title']))
                $this->error('版本名称不能为空！','',true);
            if(empty($arr['update_content']))
                $this->error('更新内容不能为空！','',true);
            if(empty($arr['client']))
                $this->error('客户端类型不能为空！','',true);
            if(!empty($_FILES['apk']['name'])){
                $file_name = 'tutu_';
                switch($arr['client']){
                    case 1:
                        $client = 'user';
                        break;
                    case 2:
                        $client = 'service';
                        break;
                    default:
                        $this->error('客户端类型不正确！','',true);
                        break;
                }
                $file_name .= $client.'_'.$arr['version_code'];
                $arr['version_url'] = '/download/'.$file_name.'.apk';
                //上传
                $upload = new \Think\Upload();// 实例化上传类
                $upload->maxSize   =     100 * 1024 * 1024 ;// 设置附件上传大小100M
                $upload->exts      =     array('apk');// 设置附件上传类型
                $upload->replace    =   true;//存在同名文件是否是覆盖，默认为false
                $upload->saveName   =   $file_name;//保持文件本身的名字
                $upload->saveExt    =   'apk';//文件后缀
                $upload->autoSub  = false;//自动建立父文件夹
                 $upload->rootPath  =     './download/'; // 设置附件上传根目录
                $upload->savePath  =     ''; // 设置附件上传（子）目录
                // 上传文件
                if(!$info   =   $upload->upload())
                    $this->error($upload->getError());
            }
            $model = M('version');
            if(empty($id)){
                if($result = $model->add($arr))
                    $this->success('编辑成功！','',true);
                else
                    $this->error('编辑失败！','',true);
            }else {
                if(false !== $result = $model->where(array('id'=>$id))->save($arr))
                    $this->success('编辑成功！','',true);
                else
                    $this->error('编辑失败！','',true);
            }
        }
        if($info = M('version')->where(array('id'=>$id))->find())
            $this->info = $info;
        $this->platform = $platform;
        $this->id = $id;
        $this->meta_title = '版本上传';
        $this->display();
    }

    public function changeStatus($method=null){
        if ( empty($_REQUEST['id']) )
            $this->error('请选择要操作的数据!');
        switch ( strtolower($method) ){
            case 'close':
                $this->forbid('version');
                break;
            case 'open':
                $this->resume('version');
                break;
            case 'del':
                $this->delete('version');
                break;
            default:
                $this->error($method.'参数非法');
        }
    }
}

