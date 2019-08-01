<?php
/**
 * Created by PhpStorm.
 * User: later
 * Date: 2019/7/18
 * Time: 10:06
 */

namespace app\api\controller;

use think\Controller;

/**
 * 微信支付接口
 */
class WxpayController extends Controller
{
    /**
     * @title 统一下单接口
     */
    public function wxpay()
    {
        //一系列订单状态判断 金额、订单状态，是否符合支付条件


        //统一下单参数构造
        $unifiedorder = array(
            'appid' => 'xxxxxx',//小程序id
            'mch_id' => 'xxxxxx',//商户id
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],//终端ip
            'notify_url' => 'http://www.xxx.xxx/api/order/receivesuc', //统一下单成功回调地址
            'out_trade_no' => 'xxxxxx',//订单号
            'total_fee' => 'xxx', //总金额 单位分
            'openid' => 'xxxxxx',//用户openid
            'trade_type' => 'JSAPI',//交易类型
            'body' => 'test', //商品信息
        );

        vendor("wxpay.WeiXinPay");

        $wxpay = new \WeiXinPay('KEY');//商户32位 key  商户后台设置pai秘钥

        $data = $wxpay->pay($unifiedorder);

        if (isset($data['code']) && $data['code'] == 'error') {

            return json(['code' => 0, 'msg' => $data['msg']]);

        } else {

            return json(['code' => 1, 'msg' => 'success', 'data' => $data]);
        }

    }


    /**
     * 微信支付完成，回调地址url方法
     */
    public function receivesuc()
    {
        //商户key
        $KEY = 'xxxxxx';

        //接收微信支付成功回调数据
        $post = file_get_contents("php://input");

        //微信支付成功，返回回调地址url的数据：XML转数组Array
        $post_data = $this->xml_to_array($post);


        /* 微信官方提醒：
         *  商户系统对于支付结果通知的内容一定要做【签名验证】,
         *  并校验返回的【订单金额是否与商户侧的订单金额】一致，
         *  防止数据泄漏导致出现“假通知”，造成资金损失。
        */

        $postSign = $post_data['sign'];

        //这里很重要哦，一定要将返回的sign剔除掉
        unset($post_data['sign']);

        ksort($post_data);// 对数据进行排序

        //这里也一定要加上key，不然签名就错了
        $str = $this->ToUrlParams($post_data) . '&key=' . $KEY;

        //再次生成签名，与$postSign比较
        $user_sign = strtoupper(md5($str));


        //查询数据库订单信息  作对比
        $where['order_id'] = $post_data['out_trade_no'];
        $order = db('order')->where($where)->find();


        //分别判断返回状态码、返回签名sign、返回订单总金额，三者同时为真，订单交易成功，状态修改为已支付
        if ($post_data['return_code'] == 'SUCCESS' && $postSign == $user_sign && $order['price'] * 100 == $post_data['total_fee']) {
            /*
            * 首先判断，订单是否已经更新为ok，因为微信会总共发送10次回调确认
            * 其次，订单已经为ok的，直接返回SUCCESS
            * 最后，订单没有为ok的，更新状态为ok，返回SUCCESS
            */
            if ($order['status'] == 'ok') {//已支付状态

                $this->return_success();

            } else {

                if (db('order')->where($where)->update(['status' => 'ok'])) {

                    $this->return_success();
                }
            }

        } else {

            echo '微信支付失败';
        }

    }

    /*
     * 给微信发送确认订单金额和签名正确，SUCCESS信息
     */
    private function return_success()
    {
        $return['return_code'] = 'SUCCESS';
        $return['return_msg'] = 'OK';
        $xml_post = '<xml>
                    <return_code>' . $return['return_code'] . '</return_code>
                    <return_msg>' . $return['return_msg'] . '</return_msg>
                    </xml>';
        echo $xml_post;

        exit;
    }

    /**
     * 输出xml字符（数组转换成xml）
     * @param $params 参数名称
     * return string 返回组装的xml
     **/
    public function array_to_xml($params)
    {
        if (!is_array($params) || count($params) <= 0) {
            return false;
        }
        $xml = "<xml>";
        foreach ($params as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     * return array
     */
    public function xml_to_array($xml)
    {
        if (!$xml) {
            return false;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }


    /**
     * 将参数拼接为url: key=value&key=value
     * @param $params
     * @return string
     */
    public function ToUrlParams($params)
    {
        $string = '';
        if (!empty($params)) {
            $array = array();
            foreach ($params as $key => $value) {
                $array[] = $key . '=' . $value;
            }
            $string = implode("&", $array);
        }
        return $string;
    }


    /**
     * 生成签名, $KEY就是支付key
     * @return 签名
     */
    public function MakeSign($params, $KEY)
    {
        //签名步骤一：按字典序排序数组参数
        ksort($params);

        //参数进行拼接key=value&k=v
        $string = $this->ToUrlParams($params);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $KEY;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

}
