<?php

// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace Manage\Controller;

use Manage\Model\AuthRuleModel;
use Think\Auth;
use Think\Controller;

/**
 * 后台首页控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
class AdminController extends Controller {

    protected function _initialize() {
        $this->group_title = $this->group_menu();

        // 获取当前用户ID
        define('UID',is_login());
        if( !UID ){// 还没登录 跳转到登录页面
            $this->redirect('Public/login');
        }
        /* 读取数据库中的配置 */
        $config =   S('DB_CONFIG_DATA');
        if(!$config){
            $config	=	D('Config')->lists();
            S('DB_CONFIG_DATA',$config);
        }
        C($config); //添加配置

        // 是否是超级管理员
        define('IS_ROOT',   is_administrator());
        if(!IS_ROOT && C('ADMIN_ALLOW_IP')){
            // 检查IP地址访问
            if(!in_array(get_client_ip(),explode(',',C('ADMIN_ALLOW_IP')))){
                $this->error('403:禁止访问');
            }
        }
        // 检测访问权限
        $access =   $this->accessControl();
        if ( $access === false ) {
            $this->error('403:禁止访问');
        }elseif( $access === null ){
            $dynamic        =   $this->checkDynamic();//检测分类栏目有关的各项动态权限
            if( $dynamic === null ){
                //检测非动态权限
                $rule  = strtolower(MODULE_NAME.'/'.CONTROLLER_NAME.'/'.ACTION_NAME);
                if ( !$this->checkRule($rule,array('in','1,2')) ){
                    if($rule === 'manage/index/index'){
                        D('ManageMember')->logout();
                        session('[destroy]');
                        $this->error('未授权访问！',U('Public/login'));
                    }

                    if(IS_AJAX)
                        $this->error('未授权访问!');
                    die(
                    '<script>parent.layer.alert("未授权访问", {
                        time: 0, //不自动关闭
                        icon: 4,
                        btn: [\'确定\'],
                        yes: function (index) {
                            parent.layer.close(index);
                            window.parent.location.reload();//刷新父窗体
                            parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前窗体
                        },
                    });</script>'
                    );
                }
            }elseif( $dynamic === false ){
                if(IS_AJAX)
                    $this->error('未授权访问!');
                die(
                '<script>parent.layer.alert("未授权访问", {
                        time: 0, //不自动关闭
                        icon: 4,
                        btn: [\'确定\'],
                        yes: function (index) {
                            parent.layer.close(index);
                            window.parent.location.reload();//刷新父窗体
                            parent.layer.close(parent.layer.getFrameIndex(window.name));//关闭当前窗体
                        },
                    });</script>'
                );
            }
        }
        $this->assign('__MENU__', $this->getMenus());
    }


    /**
     * 权限检测
     * @param string  $rule    检测的规则
     * @param string  $mode    check模式
     * @return boolean
     * @author 朱亚杰  <xcoolcc@gmail.com>
     */
    final protected function checkRule($rule, $type=AuthRuleModel::RULE_URL, $mode='url'){
        if(IS_ROOT){
            return true;//管理员允许访问任何页面
        }
        static $Auth    =   null;
        if (!$Auth) {
            $Auth       =   new \Think\Auth();
        }
        if(!$Auth->check($rule,UID,$type,$mode)){
            return false;
        }
        return true;
    }


    /**
     * 检测是否是需要动态判断的权限
     * @return boolean|null
     *      返回true则表示当前访问有权限
     *      返回false则表示当前访问无权限
     *      返回null，则会进入checkRule根据节点授权判断权限
     *
     * @author 朱亚杰  <xcoolcc@gmail.com>
     */
    protected function checkDynamic(){
        if(IS_ROOT){
            return true;//管理员允许访问任何页面
        }
        return null;//不明,需checkRule
    }


    /**
     * action访问控制,在 **登陆成功** 后执行的第一项权限检测任务
     *
     * @return boolean|null  返回值必须使用 `===` 进行判断
     *
     *   返回 **false**, 不允许任何人访问(超管除外)
     *   返回 **true**, 允许任何管理员访问,无需执行节点权限检测
     *   返回 **null**, 需要继续执行节点权限检测决定是否允许访问
     * @author 朱亚杰  <xcoolcc@gmail.com>
     */
    final protected function accessControl(){
        if(IS_ROOT){
            return true;//管理员允许访问任何页面
        }
        $allow = C('ALLOW_VISIT');
        $deny  = C('DENY_VISIT');
        $check = strtolower(CONTROLLER_NAME.'/'.ACTION_NAME);
        if ( !empty($deny)  && in_array_case($check,$deny) ) {
            return false;//非超管禁止访问deny中的方法
        }
        if ( !empty($allow) && in_array_case($check,$allow) ) {
            return true;
        }
        return null;//需要检测节点权限
    }

    /**
     * 对数据表中的单行或多行记录执行修改 GET参数id为数字或逗号分隔的数字
     *
     * @param string $model 模型名称,供M函数使用的参数
     * @param array  $data  修改的数据
     * @param array  $where 查询时的where()方法的参数
     * @param array  $msg   执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     *
     * @author 朱亚杰  <zhuyajie@topthink.net>
     */
    final protected function editRow ( $model ,$data, $where , $msg , $is_return){
        $id    = array_unique((array)I('id',0));
        $id    = is_array($id) ? implode(',',$id) : $id;
        $where = array_merge( array('id' => array('in', $id )) ,(array)$where );
        $msg   = array_merge( array( 'success'=>'操作成功！', 'error'=>'操作失败！', 'url'=>'' ,'ajax'=>IS_AJAX) , (array)$msg );
        if( M($model)->where($where)->save($data)!==false ) {
            if($is_return)
                return true;
            $this->success($msg['success'],$msg['url'],$msg['ajax']);
        }else{
            if($is_return)
                return false;
            $this->error($msg['error'],$msg['url'],$msg['ajax']);
        }
    }


    /**
     * 禁用条目
     * @param string $model 模型名称,供D函数使用的参数
     * @param array  $where 查询时的 where()方法的参数
     * @param array  $msg   执行正确和错误的消息,可以设置四个元素 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     *
     * @author 朱亚杰  <zhuyajie@topthink.net>
     */
    protected function forbid ( $model , $where = array() , $msg = array( 'success'=>'状态禁用成功！', 'error'=>'状态禁用失败！') , $is_return = false){
        $data    =  array('status' => 0);
        if($is_return)
            return $this->editRow( $model , $data, $where, $msg ,$is_return);
        $this->editRow( $model , $data, $where, $msg ,$is_return);
    }

    /**
     * 恢复条目
     * @param string $model 模型名称,供D函数使用的参数
     * @param array  $where 查询时的where()方法的参数
     * @param array  $msg   执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     *
     * @author 朱亚杰  <zhuyajie@topthink.net>
     */
    protected function resume (  $model , $where = array() , $msg = array( 'success'=>'状态恢复成功！', 'error'=>'状态恢复失败！') , $is_return = false){
        $data    =  array('status' => 1);
        if($is_return)
            return $this->editRow( $model , $data, $where, $msg ,$is_return);
        $this->editRow( $model , $data, $where, $msg ,$is_return);
    }

    /**
     * 还原条目
     * @param string $model 模型名称,供D函数使用的参数
     * @param array  $where 查询时的where()方法的参数
     * @param array  $msg   执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     * @author huajie  <banhuajie@163.com>
     */
    protected function restore (  $model , $where = array() , $msg = array( 'success'=>'状态还原成功！', 'error'=>'状态还原失败！')){
        $data    = array('status' => 1);
        $where   = array_merge(array('status' => -1),$where);
        $this->editRow(   $model , $data, $where, $msg);
    }

    /**
     * 条目假删除
     * @param string $model 模型名称,供D函数使用的参数
     * @param array  $where 查询时的where()方法的参数
     * @param array  $msg   执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     *
     * @author 朱亚杰  <zhuyajie@topthink.net>
     */
    protected function delete ( $model , $where = array() , $msg = array( 'success'=>'删除成功！', 'error'=>'删除失败！')) {
        $data['status']         =   -1;
        $data['update_time']    =   NOW_TIME;
        $this->editRow(   $model , $data, $where, $msg);
    }

    /**
     * 条目假删除
     * @param string $model 模型名称,供D函数使用的参数
     * @param array  $where 查询时的where()方法的参数
     * @param array  $msg   执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     *
     * @author 朱亚杰  <zhuyajie@topthink.net>
     */
    protected function del ( $model , $where = array() , $msg = array( 'success'=>'删除成功！', 'error'=>'删除失败！')) {
        $id    = array_unique((array)I('id',0));
        $id    = is_array($id) ? implode(',',$id) : $id;
        $where = array_merge( array('id' => array('in', $id )) ,(array)$where );
        if( M($model)->where($where)->delete()!==false )
            $this->success($msg['success'],$msg['url'],$msg['ajax']);
        $this->error($msg['error'],$msg['url'],$msg['ajax']);
    }


    /**
     * 条目真删除
     * @param string $model 模型名称,供D函数使用的参数
     * @param array  $where 查询时的where()方法的参数
     * @param array  $msg   执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     *
     * @author 朱亚杰  <zhuyajie@topthink.net>
     */
    protected function _delete ( $model , $where = array() , $msg = array( 'success'=>'删除成功！', 'error'=>'删除失败！')) {
        $id    = array_unique((array)I('id',0));
        $id    = is_array($id) ? implode(',',$id) : $id;
        $where = array_merge( array('id' => array('in', $id )) ,(array)$where );
        $msg   = array_merge( array( 'success'=>'操作成功！', 'error'=>'操作失败！', 'url'=>'' ,'ajax'=>IS_AJAX) , (array)$msg );
        if( M($model)->where($where)->delete()!==false ) {
            $this->success($msg['success'],$msg['url'],$msg['ajax']);
        }else{
            $this->error($msg['error'],$msg['url'],$msg['ajax']);
        }
        /*$this->editRow(   $model , $data, $where, $msg);*/
    }




    /**
     * 设置一条或者多条数据的状态
     */
    public function setStatus($Model=CONTROLLER_NAME){

        $ids    =   I('request.ids');
        $status =   I('request.status');
        if(empty($ids)){
            $this->error('请选择要操作的数据');
        }

        $map['id'] = array('in',$ids);
        switch ($status){
            case -1 :
                $this->delete($Model, $map, array('success'=>'删除成功','error'=>'删除失败'));
                break;
            case 0  :
                $this->forbid($Model, $map, array('success'=>'禁用成功','error'=>'禁用失败'));
                break;
            case 1  :
                $this->resume($Model, $map, array('success'=>'启用成功','error'=>'启用失败'));
                break;
            default :
                $this->error('参数错误');
                break;
        }
    }

    /**
     * 获取控制器菜单数组,二级菜单元素位于一级菜单的'_child'元素中
     * @author 朱亚杰  <xcoolcc@gmail.com>
     */
    final public function getMenus($controller=CONTROLLER_NAME){
        // $menus  =   session('ADMIN_MENU_LIST'.$controller);
        //dump($controller);
        //dump(session('ADMIN_MENU_LIST'.$controller));die;
        if(empty($menus)){
            // 获取主菜单
            $where['pid']   =   0;
            $where['hide']  =   0;
            if(!C('DEVELOP_MODE')){ // 是否开发者模式
                $where['is_dev']    =   0;
            }
            $menus  =   M('Menu')->where($where)->order('sort asc')->select();

            foreach ($menus as $key => &$item) {
                if (!is_array($item) || empty($item['title']) || empty($item['url']) ) {
                    $this->error('控制器基类$menus属性元素配置有误');
                }
                $item['url'] = count(explode('/',$item['url'])) == 2 ? MODULE_NAME.'/'.$item['url'] : $item['url'];
                // 判断主菜单权限
                if ( !IS_ROOT && !$this->checkRule($item['url'],AuthRuleModel::RULE_MAIN,null) ) {
                    unset($menus[$key]);
                    continue;//继续循环
                }

                //获取二级分类的合法url
                $where          =   array();
                $where['pid']   =   $item['id'];
                $where['hide']  =   0;
                if(!C('DEVELOP_MODE')){ // 是否开发者模式
                    $where['is_dev']    =   0;
                }
                $second_urls = M('Menu')->where($where)->getField('id,url');
                if(!IS_ROOT){
                    // 检测菜单权限
                    $to_check_urls = array();
                    foreach ($second_urls as $key=>$to_check_url) {
                        $rule = count(explode('/',$to_check_url)) == 2 ? MODULE_NAME.'/'.$to_check_url : $to_check_url;

                        if($this->checkRule($rule, AuthRuleModel::RULE_URL,null))
                            $to_check_urls[] = $to_check_url;
                    }
                }

                if(isset($to_check_urls)){
                    if(empty($to_check_urls)){
                        // 没有任何权限
                        continue;
                    }else{
                        $map['url'] = array('in', $to_check_urls);
                    }
                }
                $map['pid'] =   $item['id'];
                $map['hide']    =   0;
                if(!C('DEVELOP_MODE')){ // 是否开发者模式
                    $map['is_dev']  =   0;
                }
                $menuList = M('Menu')->where($map)->field('id,pid,title,url,tip')->order('sort asc')->select();
                //高亮主菜单
                $current = M('Menu')->where("url like '%{$controller}/".ACTION_NAME."%'")->field('id')->find();
                foreach ($menuList as &$v){
                    if($current['id'] == $v['id'])
                        $v['class']='current';
                }
                // 获取当前主菜单的子菜单项
                $item['child'] = list_to_tree($menuList, 'id', 'pid', 'operater', $item['id']);

                if($menus['child'] === array()){
                    //$this->error('主菜单下缺少子菜单，请去系统=》后台菜单管理里添加');
                }
            }
            // session('ADMIN_MENU_LIST'.$controller,$menus);
        }
        return $menus;
    }

    /**
     * 返回后台节点数据
     * @param boolean $tree    是否返回多维数组结构(生成菜单时用到),为false返回一维数组(生成权限节点时用到)
     * @retrun array
     *
     * 注意,返回的主菜单节点数组中有'controller'元素,以供区分子节点和主节点
     *
     * @author 朱亚杰 <xcoolcc@gmail.com>
     */
    final protected function returnNodes($tree = true){
        static $tree_nodes = array();
        if ( $tree && !empty($tree_nodes[(int)$tree]) ) {
            return $tree_nodes[$tree];
        }
        if((int)$tree){
            $list = M('Menu')->field('id,pid,title,url,tip,hide')->order('sort asc')->select();
            foreach ($list as $key => $value) {
                if( stripos($value['url'],MODULE_NAME)!==0 ){
                    $list[$key]['url'] = MODULE_NAME.'/'.$value['url'];
                }
            }
            $nodes = list_to_tree($list,$pk='id',$pid='pid',$child='operator',$root=0);
            foreach ($nodes as $key => $value) {
                if(!empty($value['operator'])){
                    $nodes[$key]['child'] = $value['operator'];
                    unset($nodes[$key]['operator']);
                }
            }
        }else{
            $nodes = M('Menu')->field('title,url,tip,pid,id')->order('sort asc')->select();
            foreach ($nodes as $key => $value) {
                if( stripos($value['url'],MODULE_NAME)!==0 ){
                    $nodes[$key]['url'] = MODULE_NAME.'/'.$value['url'];
                }
            }
        }
        $tree_nodes[(int)$tree]   = $nodes;
        return $nodes;
    }


    /**
     * 通用分页列表数据集获取方法
     *
     *  可以通过url参数传递where条件,例如:  index.html?name=asdfasdfasdfddds
     *  可以通过url空值排序字段和方式,例如: index.html?_field=id&_order=asc
     *  可以通过url参数r指定每页数据条数,例如: index.html?r=5
     *
     * @param sting|Model  $model   模型名或模型实例
     * @param array        $where   where查询条件(优先级: $where>$_REQUEST>模型设定)
     * @param array|string $order   排序条件,传入null时使用sql默认排序或模型属性(优先级最高);
     *                              请求参数中如果指定了_order和_field则据此排序(优先级第二);
     *                              否则使用$order参数(如果$order参数,且模型也没有设定过order,则取主键降序);
     *
     * @param array        $base    基本的查询条件
     * @param boolean      $field   单表模型用不到该参数,要用在多表join时为field()方法指定参数
     * @author 朱亚杰 <xcoolcc@gmail.com>
     *
     * @return array|false
     * 返回数据集
     */
    protected function lists ($model,$where=array(),$order='',$base = array('status'=>array('egt',0)),$field=true){
        $options    =   array();
        $REQUEST    =   (array)I('request.');
        if(is_string($model)){
            $model  =   M($model);
        }

        $OPT        =   new \ReflectionProperty($model,'options');
        $OPT->setAccessible(true);

        $pk         =   $model->getPk();
        if($order===null){
            //order置空
        }else if ( isset($REQUEST['_order']) && isset($REQUEST['_field']) && in_array(strtolower($REQUEST['_order']),array('desc','asc')) ) {
            $options['order'] = '`'.$REQUEST['_field'].'` '.$REQUEST['_order'];
        }elseif( $order==='' && empty($options['order']) && !empty($pk) ){
            $options['order'] = $pk.' desc';
        }elseif($order){
            $options['order'] = $order;
        }
        unset($REQUEST['_order'],$REQUEST['_field']);

        $options['where'] = array_filter(array_merge( (array)$base, /*$REQUEST,*/ (array)$where ),function($val){
            if($val===''||$val===null){
                return false;
            }else{
                return true;
            }
        });
        if( empty($options['where'])){
            unset($options['where']);
        }
        $options      =   array_merge( (array)$OPT->getValue($model), $options );
        $total        =   $model->where($options['where'])->count();

        if( isset($REQUEST['r']) ){
            $listRows = (int)$REQUEST['r'];
        }else{
            $listRows = C('LIST_ROWS') > 0 ? C('LIST_ROWS') : 10;
        }
        $page = new \Think\Page($total, $listRows, $REQUEST);
        if($total>$listRows){
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
        }
        $p =$page->show();
        $this->assign('_page', $p? $p: '');
        $this->assign('_total',$total);
        $options['limit'] = $page->firstRow.','.$page->listRows;

        $model->setProperty('options',$options);
        
        return $model->field($field)->select();
    }

    /*
     * jpush初始化
     * $type 为客户端类型    0-用户端设备推送      1-导游端设备推送
     */

    protected function init_push($type = 0) {
        ini_set("display_errors", "On");
        error_reporting(E_ALL | E_STRICT);
        Vendor('Jpush.JPush');

        $app_key = $type == 1 ? 'b00b092e63c1bdeff180d3b6' : 'ad074df747564da9e85d9d25';
        $master_secret = $type == 1 ? '80dd202693f29e0bf9848d5a' : 'f34bda49fadd19e8e2fa4adc';

        // 初始化
        return new \JPush($app_key, $master_secret);
    }
    
    /*
     * excel表格导出
     */
    protected function export_excel($expTitle,$expCellName,$expTableData,$start_time,$end_time){
        $xlsTitle = iconv('utf-8', 'gb2312', $expTitle);//文件名称
        $fileName = $expTitle;
        $cellNum = count($expCellName);
        $dataNum = count($expTableData);
        $datetime = !empty($start_time)?$start_time.'--'.$end_time:'--'.'所有';
        vendor('PHPExcel.PHPExcel', VENDOR_PATH, '.php');
        $objPHPExcel = new \PHPExcel();
        $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');

        $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(18)->setBold(false);
        $objPHPExcel->getActiveSheet()->mergeCells('A1:'.$cellName[$cellNum-1].'1');//合并单元格
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.' '.$datetime);//设置表格标题
        $objPHPExcel->getActiveSheet()->getStyle('A1:'.$cellName[$cellNum-1].'1')->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);//设置表格标题边框线
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//设置表格标题居中
        $objPHPExcel->getActiveSheet()->getStyle('A2:'.$cellName[$cellNum-1].'2')->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);//设置表格头边框线
        $objPHPExcel->getActiveSheet()->getStyle('A2:'.$cellName[$cellNum-1].'2')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//设置表格头居中
        for($i=0;$i<$cellNum;$i++){
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'2', $expCellName[$i][1]);
            $objPHPExcel->getActiveSheet()->getStyle($cellName[$i].'2')->getFont()->setSize(12)->setBold(true);//设置表格标题字体大小及字体加粗
            $objPHPExcel->getActiveSheet()->getColumnDimension($cellName[$i])->setWidth(20);//设置单元格统一宽度为20
        }
        for($i=0;$i<$dataNum;$i++){
            for($j=0;$j<$cellNum;$j++){
                $cellValue = $expTableData[$i][$expCellName[$j][0]];
                $objPHPExcel->getActiveSheet(0)->setCellValueExplicit($cellName[$j].($i+3), $cellValue,\PHPExcel_Cell_DataType::TYPE_STRING);//设置值得同时设置为string类型
                $objPHPExcel->getActiveSheet()->getStyle($cellName[$j].($i+3))->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//设置居中
                $objPHPExcel->getActiveSheet()->getStyle('A'.($i+3).':'.($cellName[$cellNum-1]).($i+3))->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);//设置单元格边框线
            }
        }
        header('pragma:public');
        header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
        header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    /*
     * 总后台菜单分组
     */

    private function group_menu() {
        return array(
            'user' => array(
                'group_title' => '用户管理',//一级分组名称
                'group_menu' => array(//二级分组
                    array(
                        'url' => U('User/tourist_list'),//二级分组url
                        'name' => '用户列表',//耳机分组名称
                    ),
                    array(
                        'url' => U('User/guide_list'),
                        'name' => '导游列表',
                    ),
                    array(
                        'url' => U('User/order_list?server_type=0'),
                        'name' => '即时订单',
                    ),
                    array(
                        'url' => U('User/order_list?server_type=1'),
                        'name' => '预约订单',
                    ),
                ),
            ),
            'tourist'   =>  array(
                'group_title'   =>  '景区管理',
                'group_menu'    =>  array(
                    array(
                        'url'   =>  U('Tourist/tourist_list'),
                        'name'  =>  '景区列表',
                    )
                ),
            ),
            'service'   =>  array(
                'group_title'   =>  '客服管理',
                'group_menu'    =>  array(
                    array(
                        'url'   =>  U('Insurance/insurance_list'),
                        'name'   =>  '保险购买管理',
                    ),
                ),
            ),
            'coupon'    =>  array(
                'group_title'   =>  '优惠券管理',
                'group_menu'    =>  array(
                    array(
                        'url'   =>  U('Coupon/coupon_list'),
                        'name'  =>  '优惠券列表',
                    ),
                ),
            ),
            'system' => array(
                'group_title' => '系统管理',
                'group_menu' => array(
                    array(
                        'url' => U('Menu/index'),
                        'name' => '菜单管理',
                    ),
                ),
            ),
            'finance' => array(
                'group_title' => '财务管理',
                'group_menu' => array(
                    array(
                        'url' => U('Finance/finance_show'),
                        'name' => '入账列表',
                    ),
                    array(
                        'url' => U('Finance/finance_out'),
                        'name' => '出账列表',
                    ),
                ),
            ),
            'manage'    =>  array(
                'group_title'   =>  '管理员管理',
                'group_menu'    =>  array(
                    array(
                        'url' => U('Manage/manage_list'),
                        'name' => '管理员列表',
                    ),
                    array(
                        'url' => U('AuthManager/index'),
                        'name' => '分组管理',
                    ),
                ),
            ),
            'Edition'    =>  array(
                'group_title'   =>  '版本控制',
                'group_menu'    =>  array(
                    array(
                        'url' => U('Edition/edition_list'),
                        'name' => '版本更新',
                    ),
                ),
            ),
            'Compilation'    =>  array(
                'group_title'   =>  '文本管理',
                'group_menu'    =>  array(
                    array(
                        'url' => U('Compilation/text_list'),
                        'name' => '文本列表',
                    ),
                ),
            ),
        );
    }

}
