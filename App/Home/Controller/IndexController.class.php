<?php

namespace Home\Controller;
/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/8/26
 * Time: 12:33
 */
class IndexController extends \Think\Controller{

    public function download(){
        $map = array(
            'platform'  =>  'andr',
            'client'    =>  1,
            'status'    =>  1,
        );
        if($info = M('version')->where($map)->field('version_url')->find())
            redirect($info['version_url']);
    }
}