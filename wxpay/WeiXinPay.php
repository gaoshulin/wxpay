<?php
/**
 * Created by PhpStorm.
 * User: 高树林
 * Date: 2019/7/27
 * Time: 12:05
 */

/**
 * 微信支付类
 */
class WeiXinPay
{
    private $private_key;//32位的密钥，在商户平台上设置的

    function __construct($private_key)
    {
        $this->private_key = $private_key;
    }

    //微信支付接口
    public function pay($unifiedorder)
    {
        $unifiedorder['nonce_str'] = $this->createNoncestr();

        $unifiedorder['sign'] = $this->getSign($unifiedorder);

        $xmlData = $this->arrayToXml($unifiedorder);

        $xml_result = $this->postXmlCurl($xmlData, 'https://api.mch.weixin.qq.com/pay/unifiedorder', 60);

        $result = $this->xmlToArray($xml_result);

        if ($result['return_code'] == 'FAIL') {

            return array('code' => 'error', 'msg' => $result['return_msg']);
        }

        if ($result['result_code'] == 'FAIL') {

            return array('code' => 'error', 'msg' => $result['err_code'] . ':' . $result['err_code_des']);
        }

        $parameters = array(
            'appId' => $result['appid'],//小程序ID
            'timeStamp' => '' . time() . '',//时间戳 字符串类型
            'nonceStr' => $this->createNoncestr(),//随机串
            'package' => 'prepay_id=' . $result['prepay_id'],//数据包
            'signType' => 'MD5'//签名方式
        );
        $parameters['paySign'] = $this->getSign($parameters);

        return $parameters;
    }


    //数组转字符串方法
    protected function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    protected function xmlToArray($xml)
    {
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }

    //发送xml请求方法
    private static function postXmlCurl($xml, $url, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }


    /*
     * 对要发送到微信统一下单接口的数据进行签名
     */
    protected function getSign($Obj)
    {
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $this->private_key;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }

    /*
     *排序并格式化参数方法，签名时需要使用
     */
    protected function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            //$buff .= strtolower($k) . "=" . $v . "&";
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }

    /*
     * 生成随机字符串方法
     */
    protected function createNoncestr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

}