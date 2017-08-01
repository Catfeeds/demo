<?php
/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/7/19
 * Time: 16:09
 */

function wxpay_config($version) {
    if(!empty($version))
        $version = '_'.strtoupper($version);

    $wxpay_config['appid'] = C('WXPAY'.$version.'.APPID');

    $wxpay_config['mch_id'] = C('WXPAY'.$version.'.MCH_ID');
    
    $wxpay_config['key']    =   C('WXPAY'.$version.'.KEY');

    $wxpay_config['notify_url'] = C('WXPAY'.$version.'.NOTIFY_URL');

    $wxpay_config['trade_type'] = 'APP';

    $wxpay_config['curl_timeout']   =   60;

    $wxpay_config['sslcert_path']   =   VENDOR_PATH.'/Wxpay/cacert/apiclient_cert.pem';

    $wxpay_config['sslkey_path']   =   VENDOR_PATH.'/cacert/apiclient_key.pem';

    return $wxpay_config;
}