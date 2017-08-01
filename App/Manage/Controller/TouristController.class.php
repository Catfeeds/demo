<?php
/**
 * Created by PhpStorm.
 * User: vaio
 * Date: 2016/6/24
 * Time: 11:21
 */

namespace Manage\Controller;


use User\Api\UserApi;

class TouristController extends AdminController{
    public function _initialize($group_title) {
        parent::_initialize($group_title);
        $this->group_title = '景区管理';
    }

    public function add_tourist(){
        if(IS_POST){
            $tourist_name = I('post.tourist_name');
            $hours_price = I('post.hours_price');
            $times_price = I('post.times_price');
            $lng_lat = I('post.lng_lat');
            if(empty($tourist_name))
                $this->error('景区名称不能为空！',true);
            if(empty($hours_price))
                $this->error('景区讲解员按小时计费价格不能为空！',true);
            if(empty($times_price))
                $this->error('景区讲解员按次计费价格不能为空！',true);
            if(empty($lng_lat))
                $this->error('景区所在位置经纬度不能为空！',true);
            $temp = explode(',',$lng_lat);
            unset($lng_lat);
            $lng_lat['lng'] = $temp[0];
            $lng_lat['lat'] = $temp[1];
            $data = array(
                'tourist_name'  =>  $tourist_name,
                'area_code' =>  randomIntkeys(8,6),
                'hours_price'   =>  $hours_price,
                'times_price'   =>  $times_price,
                'lng_lat'   =>  serialize($lng_lat),
                'add_time'  =>  NOW_TIME,
            );
            $Model = M('tourist_area');
            if($id = $Model->add($data)){
                $Model->where(array('id'=>$id))->setField('tourist_code',$id);
                $this->success('添加成功！',true);
            }
            $this->error('添加失败！',true);
        }
        $this->meta_title = '添加景区';
        $this->display();
    }

    /*
     * 景区列表
     */
    public function tourist_list(){
        $curr = I('get.page',1);
        $Model = M('tourist_area');
        $page_size = 15;
        $count = $Model->count();
        $list = $Model->page($curr,$page_size)->order('add_time desc')->select();
        foreach($list as &$val){
            $lng_lat =   unserialize($val['lng_lat']);
            $val['lng_lat'] =  implode(',',$lng_lat);
        }
        $pages = intval(ceil($count / $page_size));
        $list = array(
            'list'  =>  $list,
            'pages' =>  $pages,
        );
        $this->list = $list;
        $this->meta_title = '景区列表';
        $this->display();
    }

    /*
     * 导入景区讲解员数据
     */
    public function import_guide(){
        if(IS_POST){
            $id = I('get.id');
            if(empty($id))
                $this->error('景区id为空！','',true);
            if(empty($_FILES['excel']))
                $this->error('请上传讲解员资料文件！','',true);
            //修改默认访问超时时间
            $default_time = ini_get('max_execution_time');
            ini_set('max_execution_time', '0');
            //获取景区编号
            $tourist_code = M('tourist_area')->getFieldById($id,'tourist_code');
            $config = array(
                'maxSize' => 10 * 1024 * 1024, //上传限制3M
                'rootPath' => './Uploads/',
                'savePath' => '',
                'saveName' => array('uniqid', ''),
                'exts' => array('xls', 'xlsx'),
                'autoSub' => true,
                'subName' => 'Tourist',
            );
            $upload = new \Think\Upload($config);// 实例化上传类
            // 上传文件
            if($info   =   $upload->uploadOne($_FILES['excel'])){
                $file = '.'.substr($config['rootPath'], 1) . $info['savepath'] . $info['savename'];
                if(is_array($excel_data = $this->read_excel($file,$tourist_code)))
                    $re = $this->import_data($excel_data,$id);
                $response = array(
                    'info'  =>  '导入成功',
                    'url'   =>  '',
                    'status'    =>  1,
                    'data'  =>  $re,
                );
                ini_set('max_execution_time', $default_time);
                $this->ajaxReturn($response,'json');
            }
            ini_set('max_execution_time', $default_time);
            $this->error($upload->getError(),'',true);
        }
        $this->meta_title = '导入讲解员';
        $this->display();
    }


    private function read_excel($file,$tourist_code){
        //获取景区
        vendor('PHPExcel.PHPExcel', VENDOR_PATH, '.php');
        $fileType = \PHPExcel_IOFactory::identify($file); //文件名自动判断文件类型
        $objReader = \PHPExcel_IOFactory::createReader($fileType);
        $objPHPExcel = $objReader->load($file);
        $currentSheet = $objPHPExcel->getActiveSheet();//获取到钱表格对象
        $highestRow = $currentSheet->getHighestRow();//获取总共有多少行数据
        $highestColumn = $currentSheet->getHighestColumn();//获取总共有多少列数据
        //图片处理
        $root_path = './Uploads/Tourist/'.$tourist_code.'/';
        $all_image= $currentSheet->getDrawingCollection();  //获取文档中所有图片
        foreach ($all_image as $k => $drawing) {    //文档中图处理方法
            $image = $drawing->getImageResource();
            $filename=$drawing->getIndexedFilename();
            $XY=$drawing->getCoordinates();
            //把图片存起来
            if(!is_dir($root_path))
                mkdir($root_path,0777,true);//创建目录
            imagepng($image,$image_path = $root_path.$filename);
            $image = new \Think\Image();
            $image->open($image_path);
            $image_path= explode('/',trim($image_path,'.'));
            $image_path[count($image_path)-1] = '120_'.$image_path[count($image_path)-1];
            // 按照原图的比例生成一个最大为120*120的缩略图
            if(is_object($image->thumb(120, 120)->save('.'.implode('/',$image_path)))){
                //把图片的单元格的值设置为图片名称
                $cell = $currentSheet->getCell($XY);
                $cell->setValue(implode('/',$image_path));
            }
        }
        //表格数据处理
        for($i=2;$i<=$highestRow;$i++)
        {
            $data[] = array(
                'realname'    =>  $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(1,$i)->getValue(),
                'idcard'    =>  $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(2,$i)->getValue(),
                'mobile'    =>  $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(3,$i)->getValue(),
                'head_image'    =>  $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(4,$i)->getValue(),
                'guide_idcard'    =>  $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(5,$i)->getValue(),
                'idcard_back'    =>  $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(6,$i)->getValue(),
                'idcard_front'    =>  $objPHPExcel->getActiveSheet()->getCellByColumnAndRow(7,$i)->getValue(),
            );
        }
        foreach ($data as $key => $val){
            if(empty($val['realname']) || empty($val['idcard']) || empty($val['mobile']) || empty($val['head_image']))
                unset($data[$key]);
        }
        unlink($file);
        return array_merge($data);
    }
    /*
     * excel表格数据插入数据库
     */
    private function import_data($data,$tourist_id){
        $Model_guide_member = M('guide_member');
        $Model_guide_member_info = M('guide_member_info');
        $registered_mobile= $Model_guide_member->field('mobile')->select();
        $registered_mobile = array_column($registered_mobile,'mobile');
        $user = new UserApi();
        $password = $user->password_md5(C('IMPORT_USER_PASSWORD'));
        vendor('AES.Aes');
        $AES = new \MCrypt();
        $times = 0;
        foreach ($data as $val){
            if(in_array($val['mobile'],$registered_mobile)){
                //删除图片
                $head_image = explode('/',$val['head_image']);
                $head_image[count($head_image)-1] = substr($head_image[count($head_image)-1],4);
                $head_image = '.'.implode('/',$head_image);
                $guide_idcard = explode('/',$val['guide_idcard']);
                $guide_idcard[count($guide_idcard)-1] = substr($guide_idcard[count($guide_idcard)-1],4);
                $guide_idcard = '.'.implode('/',$guide_idcard);
                $idcard_back = explode('/',$val['idcard_back']);
                $idcard_back[count($idcard_back)-1] = substr($idcard_back[count($idcard_back)-1],4);
                $idcard_back = '.'.implode('/',$idcard_back);
                $idcard_front = explode('/',$val['idcard_front']);
                $idcard_front[count($idcard_front)-1] = substr($idcard_front[count($idcard_front)-1],4);
                $idcard_front = '.'.implode('/',$idcard_front);
                unlink($head_image);
                unlink($guide_idcard);
                unlink($idcard_back);
                unlink($idcard_front);
                unlink('.'.$val['head_image']);
                unlink('.'.$val['guide_idcard']);
                unlink('.'.$val['idcard_back']);
                unlink('.'.$val['idcard_front']);
                unset($head_image,$guide_idcard,$idcard_back,$idcard_front);
                continue;
            }

            $data_member = array(
                'username'  =>  $val['mobile'],
                'password'  =>  $password,
                'email' =>  '',
                'mobile'    =>  $val['mobile'],
                'reg_time'  =>  NOW_TIME,
                'reg_ip'    =>  get_client_ip(1),
                'update_time'   =>  NOW_TIME,
                'is_import' =>  1,
                'status'    =>  1,
            );
            $data_member_info = array(
                'nickname'  =>  $val['mobile'],
                'realname'  =>  $val['realname'],
                'phone'  =>  $val['mobile'],
                'email'  =>  '',
                'idcard'  =>  $AES->encrypt($val['idcard']),
                'head_image'  =>  $val['head_image'],
                'guide_idcard'  =>  $val['guide_idcard'],
                'idcard_back'  =>  $val['idcard_back'],
                'idcard_front'  =>  $val['idcard_front'],
                'birthday' => empty($val['idcard']) ? 0 : substr($val['idcard'], 6, 8),
                'sex'  =>  empty($val['idcard']) ? 2 : substr($val['idcard'], (strlen($val['idcard']) == 18 ? -2 : -1), 1) % 2 === 0 ? 1 : 0,
                'reg_time'  =>  NOW_TIME,
                'guide_type'    =>  1,
                'stars' =>  '10',
                'is_auth'   =>  1,
                'is_online' =>  0,
                'tourist_id'    =>  $tourist_id,
                'status'    =>  1,
            );
            if(!$id = $Model_guide_member->add($data_member))
                continue;
            $data_member_info['uid']    =   $id;
            $Model_guide_member_info->add($data_member_info);
            usleep(1000);
            $times += 1;
        }
        return array(
            'count' =>  count($data),
            'success'   =>  $times,
        );
    }
    
    public function del_tourist(){
        $id = (array)I('get.id', 0);
        if (empty($id) || !isset($id))
            $this->error('你们选择任何可操作的数据！', '', TRUE);
        $Model = M('tourist_area');
        $map = array(
            'id' => array('in', implode(',', $id)),
        );
        if ($Model->where($map)->delete())
            $this->success('删除成功！', '', true);
        $this->error('删除失败！', '', true);
    }
    

    public function changeStatus($method=null){
        if ( empty($_REQUEST['id']) )
            $this->error('请选择要操作的数据!');
        switch ( strtolower($method) ){
            case 'close':
                $this->forbid('tourist_area');
                break;
            case 'open':
                $this->resume('tourist_area');
                break;
            case 'del':
                $this->delete('tourist_area');
                break;
            default:
                $this->error($method.'参数非法');
        }
    }
}