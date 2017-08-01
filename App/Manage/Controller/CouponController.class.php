<?php

namespace Manage\Controller;

/**
 * @author vaio
 * @datetime 2016-5-14  11:09:06
 * @encoding UTF-8 
 * @filename CouponController.class.php 
 */
class CouponController extends AdminController {

    public function _initialize($group_title) {
        parent::_initialize($group_title);
        $this->group_title = '优惠券管理';
    }

    public function add_coupon() {
        if (IS_POST) {
            $exp_start = I('post.exp_start', 0);
            if (empty($exp_start))
                $this->error('优惠券有效期-开始时间不能为空！', '', TRUE);
            $exp_end = I('post.exp_end', 0);
            if (empty($exp_end))
                $this->error('优惠券有效期-结束时间不能为空！', '', TRUE);
            $coupon_type = I('post.coupon_type', 0);
            if (empty($coupon_type))
                $this->error('优惠券类型不能为空！', '', TRUE);
            $coupon_rule = I('post.coupon_rule', 0);
            if (empty($coupon_rule))
                $this->error('优惠券规则不能为空！', '', TRUE);
            $guide_type = I('post.guide_type', 0);
            if (empty($guide_type))
                $this->error('导游类型不能为空！', '', TRUE);
            $server_type = I('post.server_type', 0);
            if (empty($server_type))
                $this->error('服务类型不能为空！', '', TRUE);
            $coupon_type = I('post.coupon_type', 0);
            if (empty($coupon_type))
                $this->error('优惠券类型不能为空！', '', TRUE);
            $max_coupon_money = I('post.max_coupon_money', 0);
            if ($this->create_coupon(strtotime($exp_start), strtotime($exp_end), $coupon_type, $coupon_rule, $guide_type, $server_type, $coupon_type, $max_coupon_money) === FALSE)
                $this->error('优惠券生成失败！', U('coupon_list'), TRUE);
            $this->success('优惠券生成成功！', U('coupon_list'), TRUE);
        }
        $this->meta_title = '生成优惠券';
        $this->display('create_coupon');
    }

    public function coupon_list() {
        $list = M('coupon')->select();
        $this->list = $list;
        $this->meta_title = '优惠券列表';
        $this->display();
    }

    public function create_coupon($exp_start, $exp_end, $coupon_type, $coupon_rule, $guide_type, $server_type, $coupon_type, $max_coupon_money = 0) {
        $data = array(
            'coupon_no' => $no = create_order_no(),
            'exp_start' => $exp_start,
            'exp_end' => $exp_end,
            'coupon_type' => $coupon_type,
            'server_type' => $server_type,
            'guide_type' => $guide_type,
            'max_coupon_money' => $max_coupon_money,
        );
        if ($coupon_type == 1)
            $data['sub_money'] = $coupon_rule;
        elseif ($coupon_type == 2)
            $data['discount'] = $coupon_rule;
        if (M('coupon')->add($data))
            return $no;
        return FALSE;
    }

    public function del_coupon() {
        $id = I('post.id', 0);
        if (empty($id) || !isset($id))
            $this->error('你们选择任何可操作的数据！', '', TRUE);
        $Model = M('coupon');
        $map = array(
            'id' => array('in', implode(',', $id)),
            'uid' => array('eq', '0'),
        );
        if ($Model->where($map)->delete())
            $this->success('删除成功！', '', true);
        $this->error('删除失败！', '', true);
    }

}
