<?php
return array(
	//'配置项'=>'配置值'
    //支付宝配置
    'ALIPAY_V1' => array(
        'PID' => '2088221734808355',
        'NOTIFY_URL' => "tutu.xbx121.com/Api_v1/Pay/alipay_notify",
    ),

    //微信支付配置
    'WXPAY_V1' => array(
        'APPID' => 'wx26cf10d12ab4aa2c',
        'MCH_ID'    =>  '1339520001',
        'KEY'   =>  'b3af38147aa7a0a41da11433937c202a',
        'NOTIFY_URL' => "tutu.xbx121.com/Api_v1/Pay/wxpay_notify",
    ),
);