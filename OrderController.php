<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/7/10
 * Time: 18:40
 */

namespace app\order\controller;

use think\Controller;

//订单管理
class OrderController extends Controller
{
    //TODO 用户申请退款 核实订单、退款
    public function refund()
    {
        $id = input('id', 0, 'intval');
        $info = db('order')->where('id', $id)->find();

        //退款流程 调用微信退款api
        vendor('wxpay.WeiXinRefund');

        //退款接口数据
        $refund = [
            'appid' => '',//APPID
            'mch_id' => '',//商户号
            'out_trade_no' => $info['order_id'],//商户订单号
            'out_refund_no' => date('YmdHis').mt_rand(1000,9999),//退款订单号
            'total_fee' => $info['price'],//总金额
            'refund_fee' => $info['price'],//退款金额
        ];

        $wx_refund = new \WinXinRefund('KEY');

        $result = $wx_refund->refund($refund);

        /**
         * 返回状态码 return_code  SUCCESS/FAIL  此字段是通信标识，表示接口层的请求结果，并非退款状态。
         * 返回信息     return_msg   当return_code为FAIL时返回信息为错误原因
         */
        if ($result['return_code'] == 'FAIL') {
            $this->error($result['return_msg']);
        }

        /**
         * 业务结果    result_code SUCCESS/FAIL  SUCCESS退款申请接收成功，结果通过退款查询接口查询;  FAIL 提交业务失败
         * 错误代码    err_code
         * 错误代码描述    err_code_des
         */
        if ($result['result_code'] == 'FAIL') {

            $this->error($result['err_code'] . ':' . $result['err_code_des']);
        }

        $res = db('order')->where('id', $id)->update(['status' => 2]);
        if ($res) {
            //相关操作.......

            $this->success('退款成功');

        } else {

            $this->error('退款失败');
        }

    }

}
