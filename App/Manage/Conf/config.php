<?php
return array(
	//'配置项'=>'配置值'
    /* 模板相关配置 */
    'TMPL_PARSE_STRING' => array(
        '__STATIC__' => __ROOT__ . '/Public/other',
        '__PUBLIC__' => __ROOT__ . '/Public/',
        '__IMG__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/images',
        '__CSS__'    => __ROOT__ . '/Public/' . MODULE_NAME . '/css',
        '__JS__'     => __ROOT__ . '/Public/' . MODULE_NAME . '/js',
        '__OTHER__'     => __ROOT__ . '/Public/' . MODULE_NAME . '/other',
    ),
	
	
    'USER_ADMINISTRATOR'    =>  1,

    /* SESSION 和 COOKIE 配置 */
    'SESSION_PREFIX' => 'tp_admin', //session前缀
    'COOKIE_PREFIX'  => 'tp_admin_', // Cookie前缀 避免冲突
    'VAR_SESSION_ID' => 'session_id',	//修复uploadify插件无法传递session_id的bug

    //'DEVELOP_MODE'  =>  true,   //后台菜单项开发模式可见

    'TMPL_ACTION_ERROR'     =>  MODULE_PATH.'View/Public/error.html', // 默认错误跳转对应的模板文件

    'URL_ROUTER_ON'   => false,  //开启路由
    'URL_MAP_RULES'=>array(
        'download' =>  'Index/download',
    ),
);