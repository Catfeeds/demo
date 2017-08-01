<?php
return array(
	//'配置项'=>'配置值'

    /* 默认设定 */
    'DEFAULT_APP'           => '@',     // 默认项目名称，@表示当前项目
	'__APP__'	=>	'',
    //'MODULE_ALLOW_LIST'    =>    array('Home','Test','User'),
    'DEFAULT_MODULE'        => 'Api', // 默认模块名称
    //'URL_MODULE_MAP'       =>    array('test'=>'admin'),
    'DEFAULT_ACTION'        => 'index', // 默认操作名称
    'DEFAULT_CHARSET'       => 'utf-8', // 默认输出编码
    'DEFAULT_TIMEZONE'      => 'PRC',    // 默认时区
    'DEFAULT_AJAX_RETURN'   => 'JSON',  // 默认AJAX 数据返回格式,可选JSON XML ...
    'DEFAULT_THEME'    => '',    // 默认模板主题名称
    'DEFAULT_LANG'          => 'zh-cn', // 默认语言

    /* Cookie设置 */
    'COOKIE_EXPIRE'         => 3600,    // Coodie有效期
    'COOKIE_DOMAIN'         => '',      // Cookie有效域名
    'COOKIE_PATH'           => '/',     // Cookie路径
    'COOKIE_PREFIX'         => '',      // Cookie前缀 避免冲突


    /* 数据库设置 */
    'DB_TYPE'               => 'mysql',     // 数据库类型
    //'DB_HOST'               => '127.0.0.1', // 服务器数据库地址
    'DB_HOST'               => '192.168.1.27', // 本地数据库地址
    'DB_NAME'               => 'tutu',          // 数据库名
    'DB_USER'               => 'root',      // 用户名
    'DB_PWD'                => 'XBX_2016DRXBX',          // 本地数据库密码
    //'DB_PWD'                => 'root',          // 本地数据库密码
    'DB_PORT'               => 3306,        // 端口
    'DB_PREFIX'             => 'yy_',    // 数据库表前缀
    'DB_SUFFIX'             => '',          // 数据库表后缀
    'DB_FIELDTYPE_CHECK'    => false,       // 是否进行字段类型检查
    'DB_FIELDS_CACHE'       => true,        // 启用字段缓存
    'DB_CHARSET'            => 'utf8',      // 数据库编码默认采用utf8
    'DB_DEPLOY_TYPE'        => 0, // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
    'DB_RW_SEPARATE'        => false,       // 数据库读写是否分离 主从式有效
    
    //sql缓存
    'DB_SQL_BUILD_CACHE' => true,//开启sql缓存
    'DB_SQL_BUILD_QUEUE' => 'xcache',//sql缓存类型
    'DB_SQL_BUILD_LENGTH' => 20, // SQL缓存的队列长度

    //Redis缓存配置
    /* 数据缓存设置 */
    'DATA_CACHE_TIME'       => 7200,      // 数据缓存有效期 0表示永久缓存
    'DATA_CACHE_COMPRESS'   => false,   // 数据缓存是否压缩缓存
    'DATA_CACHE_CHECK'      => false,   // 数据缓存是否校验缓存
    'DATA_CACHE_PREFIX'     => '',     // 缓存前缀
    'DATA_CACHE_TYPE'       => 'File',  // 数据缓存类型,
    'DATA_CACHE_PATH'       =>  TEMP_PATH,// 缓存路径设置 (仅对File方式缓存有效)
    'DATA_CACHE_KEY'        =>  'yueyou',//缓存key加密
    /*Redis设置*/  
    'REDIS_HOST'            => '127.0.0.1', //主机  
    'REDIS_PORT'            => '6379', //端口  
    'REDIS_CTYPE'           => 1, //连接类型 1:普通连接 2:长连接  
    'REDIS_TIMEOUT'         => 0, //连接超时时间(S) 0:永不超时 

    /* 错误设置 */
    'ERROR_MESSAGE' => '您浏览的页面暂时发生了错误！请稍后再试～',//错误显示信息,非调试模式有效
    'ERROR_PAGE'    => '',    // 错误定向页面

    /* 静态缓存设置 */
    'HTML_CACHE_ON'            => false,   // 默认关闭静态缓存
    'HTML_CACHE_TIME'        => 60,      // 静态缓存有效期
    'HTML_READ_TYPE'        => 0,       // 静态缓存读取方式 0 readfile 1 redirect
    'HTML_FILE_SUFFIX'      => '.shtml',// 默认静态文件后缀

    /* 语言设置 */
    'LANG_SWITCH_ON'        => false,   // 默认关闭多语言包功能
    'LANG_AUTO_DETECT'      => true,   // 自动侦测语言 开启多语言功能后有效

    /* 日志设置 */
    'LOG_EXCEPTION_RECORD'  => true,    // 是否记录异常信息日志(默认为开启状态)
    'LOG_RECORD'            => false,   // 默认不记录日志
    'LOG_FILE_SIZE'         => 2097152,    // 日志文件大小限制
    'LOG_RECORD_LEVEL'      => array('EMERG','ALERT','CRIT','ERR'),// 允许记录的日志级别
    'LOG_PATH'  =>  '/Error',

    /* 分页设置 */
    'PAGE_ROLLPAGE'         => 5,      // 分页显示页数
    'PAGE_LISTROWS'         => 20,     // 分页每页显示记录数

    /* SESSION设置 */
    'SESSION_AUTO_START'    => true,    // 是否自动开启Session
    // 内置SESSION类可用参数
    //'SESSION_NAME'          => '',      // Session名称
    //'SESSION_PATH'          => '',      // Session保存路径
    //'SESSION_CALLBACK'      => '',      // Session 对象反序列化时候的回调函数

    /* 运行时间设置 */
    'SHOW_RUN_TIME'            => false,   // 运行时间显示
    'SHOW_ADV_TIME'            => false,   // 显示详细的运行时间
    'SHOW_DB_TIMES'            => false,   // 显示数据库查询和写入次数
    'SHOW_CACHE_TIMES'        => false,   // 显示缓存操作次数
    'SHOW_USE_MEM'            => false,   // 显示内存开销
    'SHOW_PAGE_TRACE'        => false,   // 显示页面Trace信息 由Trace文件定义和Action操作赋值
    'SHOW_ERROR_MSG'        => true,    // 显示错误信息

    /* 表单令牌验证 */
    'TOKEN_ON'              => false,     // 开启令牌验证
    'TOKEN_NAME'            => '__hash__',    // 令牌验证的表单隐藏字段名称
    'TOKEN_TYPE'            => 'md5',   // 令牌验证哈希规则

    /* URL设置 */
    'URL_CASE_INSENSITIVE'  => false,   // URL地址是否不区分大小写
    'URL_ROUTER_ON'         => false,   // 是否开启URL路由
    'URL_ROUTE_RULES'       => array(), // 默认路由规则，注：分组配置无法替代
    //'URL_DISPATCH_ON'       => true,    // 是否启用Dispatcher，不再生效
    'URL_MODEL'      => 2,       // URL访问模式,可选参数0、1、2、3,代表以下四种模式：
    // 0 (普通模式); 1 (PATHINFO 模式); 2 (REWRITE  模式); 3 (兼容模式)  默认为PATHINFO 模式，提供最好的用户体验和SEO支持
    'URL_PATHINFO_MODEL'    => 2,       // PATHINFO 模式,使用数字1、2、3代表以下三种模式:
    // 1 普通模式(参数没有顺序,例如/m/module/a/action/id/1);
    // 2 智能模式(系统默认使用的模式，可自动识别模块和操作/module/action/id/1/ 或者 /module,action,id,1/...);
    // 3 兼容模式(通过一个GET变量将PATHINFO传递给dispather，默认为s index.php?s=/module/action/id/1)
    'URL_PATHINFO_DEPR'     => '/',    // PATHINFO模式下，各参数之间的分割符号
    'URL_HTML_SUFFIX'       => '',  // URL伪静态后缀设置
    //'URL_AUTO_REDIRECT'     => true, // 自动重定向到规范的URL 不再生效
    

    'LOAD_EXT_CONFIG'   =>  'setting.config',//加载config文件夹下其他配置文件名
    'SETTING_CONFIG_PATH'   =>  'Conf/setting.config.php',//动态config配置文件路径
    
    
    //用户默认头像
    'DEFAULT_HEAD_IMAGE'    =>  '/Uploads/default_head.png',
    
    //百度API  AK值
    'BAIDU_API_AK'  =>  '53HR4lOn7XFX9UHiAOOt7iIPLxOBT0D2',
    
    //加解密密匙
    'DATA_CRYPT_KEY'    =>  'xbx_yueyou',
    
    /*//支付宝配置
    'ALIPAY' => array(
        'PID' => '2088221734808355',
        'NOTIFY_URL' => "tutu.xbx121.com/Api/Pay/alipay_notify",
    ),

    //微信支付配置
    'WXPAY' => array(
        'APPID' => 'wx26cf10d12ab4aa2c',
        'MCH_ID'    =>  '1339520001',
        'KEY'   =>  'b3af38147aa7a0a41da11433937c202a',
        'NOTIFY_URL' => "tutu.xbx121.com/Api/Pay/wxpay_notify",
    ),*/

    /* jpush推送配置 */
    //beta版
    'BETA_JPUSH'	=>	array(
        'APPKEY'	=>	array(
            'SERVICE'	=>	'94e066aff2f20957da1dc375',
            'CLIENT'	=>	'2be990a1f3b0164aaa2021e3',
        ),
        'MASTER_SECRET'	=>	array(
            'SERVICE'	=>	'5056178fb02afe698e363b5b',
            'CLIENT'	=>	'ed05f37efc48c83f44e1b801',
        ),
    ),

    //正式版
    'JPUSH'	=>	array(
        'APPKEY'	=>	array(
            'SERVICE'	=>	'db78c40a0b47e1f2f5dbea9d',
            'CLIENT'	=>	'e1ff03cc411815c72bcca3f7',
        ),
        'MASTER_SECRET'	=>	array(
            'SERVICE'	=>	'bb0e9df506015943d93a7d51',
            'CLIENT'	=>	'caf08a7b70fcc3adb221ef49',
        ),
    ),

    /*
     * 中信银行银企直联配置
     */
    'CNCB'=>array(
        'USERNAME'=>'suman-zl',
        'BANKCARD'=>'8111001014100152051',
        'WITHDRAW_IP'   =>  'http://173.28.183.156:6789',
    ),

    /*
     * 用户操作类型
     */
    'USER_ACTION_TYPE'  =>  array(
        'online'  =>  '上线',
        'offline'  =>  '下线',
    ),

    /**
     * 导入用户的初始密码
     */
    'IMPORT_USER_PASSWORD'  =>  88888888,


    //云游易导商户信息配置
    'YYYD_CONFIG'   =>  array(
        //'DOMAIN'    =>  'http://gerry.center.palmyou.com/bang-guide-api/',
        'DOMAIN'    =>  'http://test.api.bdy.palmyou.com/',
        'ID'    =>  'tutu-001',
        'KEY'   =>  '3ed94525-81de-4da9-b19f-dff8b8eae4da',
    ),
);  