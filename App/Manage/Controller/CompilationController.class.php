<?php
/**
 * Created by PhpStorm.
 * User: wt
 * Date: 2016/8/10
 * Time: 9:57
 */
namespace Manage\Controller;
class CompilationController extends AdminController{
    public function _initialize(){
        parent::_initialize();
        $this->group_title = '文本列表';
    }

    /**
     * 文章列表
     */
    public function text_list(){
        $now_page = I('get.page',1);
        $page_size = 15;
        $model = M('docment');
        $count = $model->count();
        $list =$model->order('add_time desc')->page($now_page,$page_size)->select();
        foreach($list as &$val){
            $val['add_time'] = date('Y-m-d H:i:s',$val['add_time']);
            if($val['update_time'] == '0'){
                $val['update_time'] = '未修改';
            }else{
                $val['update_time'] = date('Y-m-d H:i:s',$val['update_time']);
            }
        }
        $pages = intval(ceil($count/$page_size));
        $info = array(
            'list'=>$list,
            'pages'=>$pages,
            'get'=>$_GET
        );
        if($info){
            $this->assign('list',$info);
            $this->meta_title = '文章列表';
            $this->display();
        }

    }
    /**
     * 查看详情
     */
    public function text_show(){
        if(IS_GET){
            $id = I('get.id');
            $list = M('docment')->find($id);
            if($list){
                $this->assign('list',$list);
            }
            $this->meta_title = '文章详情';
            $this->display();
        }
    }

    /**
     * 添加文章
     */
    public function text_compilation(){
        if(IS_POST){
            $title = I('title');
            $is_del = I('del');
            $is_close = I('close');
            $content = I('editor');
            $time = time();
            $use_key = I('use_key');
            $arr = array(
                'use_key'   =>$use_key,
                'title'     => $title,
                'is_del'    => $is_del,
                'is_close'  => $is_close,
                'content'   => $content,
                'add_time'  => $time,
            );
           // var_dump($content);die;
            if(empty($arr['title']))
                $this->error('标题不能为空！');
            if(empty($arr['is_del']) && !is_numeric($arr['is_del']))
                $this->error('是否删除不能为空！');
            if(empty($arr['is_close']) && !is_numeric($arr['is_close']))
                $this->error('是否关闭不能为空！');
            if(empty($arr['content']))
                $this->error('内容不能为空！');
            $result = M('docment')->add($arr);
            if($result){
                $this->success('添加成功');
            }
        }
        $this->meta_title = '添加文章';
        $this->display();
    }
    /**
     * 修改
     */
    public function update(){
        if(IS_POST){
            $title = I('title');
            $is_del = I('del');
            $is_close = I('close');
            $content = I('editor');
            $use_key = I('use_key');
            $id = I('id');
            $time = time();
            $arr = array(
                'title'     => $title,
                'is_del'    => $is_del,
                'is_close'  => $is_close,
                'content'   => $content,
                'update_time'  => $time,
                'use_key'   => $use_key,
            );
            if(empty($arr['title']))
                $this->error('标题不能为空！');
            if(empty($arr['is_del']) && !is_numeric($arr['is_del']))
                $this->error('是否删除不能为空！');
            if(empty($arr['is_close']) && !is_numeric($arr['is_close']))
                $this->error('是否关闭不能为空！');
            if(empty($arr['content']))
                $this->error('内容不能为空！');
            $result = M('docment as d')->where('d.id ='.$id)->save($arr);
            //var_dump($result);die;
            if($result){
                $this->success('修改成功');
            }
        }
    }

    /**
     * 改变状态
     */
    public function changeStatus(){
       if(IS_GET){
           $id = I('id');
           $method = I('method');
           $arr = array(
               'id' => $id,
               'status' =>$method,
           );
           $result = M('docment as d')->field('d.is_close')->where($id)->find();
           if($result['is_close']==1){
               $model = M('docment')->save($arr);
               if($model){
                   $this->success('操作成功','',true);
               }else{
                   $this->error('操作失败','',true);
               }
           }else{
               $this->error('此文章不能关闭','',true);
           }
       }
    }

    /**
     * 删除
     */
    public function del(){
        if(IS_GET){
            $id = I('get.id');
            $is_del = I('is_del');
            if($is_del == 0){
                $this->error('此文章不能删除','',true);
            }
            $model = M('docment')->delete($id);
            if($model){
                $this->success('删除成功！','',true);
            }else{
                $this->error('删除失败！','',true);
            }
        }
    }
}