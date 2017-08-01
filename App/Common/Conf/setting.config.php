<?php

return array(
    'setting' => array(
        'qq' => array(
            'name' => 'qq账号',
            'value' => '346093707'
        ),
        'MAX_GUIDE_FIND_KM' => array(
            'name' => '导游搜索范围（千米）',
            'value' => 2,
        ),
        'LLR_INSTANT_PRICE' => array(
            'name' => '领路人即时服务价格（XXX/小时）',
            'value' => 39,
        ),
        'TUZHU_NOW_PRICE' => array(
            'name' => '导游即时服务价格（XXX/小时）',
            'value' => 30,
        ),
        'OVER_TIME_PRICE' => array(
            'name' => '取消订单超时违约金',
            'value' => 10,
        ),
        'COMMENT_TAG' => array(
            'name' => '用户评论标签列表',
            'value' => array(
                0=>'服务态度优质', 1=>'讲解清晰', 2=>'专业知识强', 3=>'性格开朗',4=>'敬业,活地图',5=>'文化底蕴深厚',
                10=>'服务态度一般',11=> '专业知识需加强', 12=>'景区不熟',13=>'讲解无趣',
                ),
        ),
        'DEFAULT_TIME_MINS' => array(
            'name' => '超时XX分钟开始计费',
            'value' => 5,
        ),
        'TIP_MONEY' => array(
            'name' => '打赏配置',
            'value' => array(20, 30, 50, 80),
        ),
        'INVITE_NUMBER' =>  array(
            'name'  =>  '邀请人数限制',
            'value' =>  10,
        ),
        'SERVICE_MANAGE_FEE'    =>      array(
            'name'  =>      '平台服务管理费',
            'value'     =>      0.15,//即15%
        ),
        'PARENT_BONUS'    =>      array(
            'name'  =>      '领路人提成比例',
            'value'     =>      0.03,//即3%
        ),
        'CHECK_NOW_PASS_MSG'    =>      array(
            'name'  =>      '审核未通过原因',
            'value'     =>      array(
                '填写的身份证号码和上传身份证号码不一致!',
                '手持身份证照片中部分信息被手遮挡！',
                '上传的头像不清晰！',
                '上传的手持身份证照片不清晰！',
                '上传的身份证背面信息不清晰！',
                '上传的身份证正面信息不清晰！',
            ),
        ),
        'CLOSE_NOW_PASS_MSG'    =>      array(
            'name'  =>      '关闭原因',
            'value'     =>      array(
                '错误订单!',
                '导游未到身边开始服务！',
            ),
        ),
        'DERATE_NOW_PASS_MSG'    =>      array(
            'name'  =>      '减免原因',
            'value'     =>      array(
                '错误订单!',
                '导游未到身边开始服务！',
            ),
        ),
    ),
);
