<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2019-09-06 09:50:30
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2020-10-23T14:21:38+08:00
 */
namespace fengkui\Pay;
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 定义时区
ini_set('date.timezone','Asia/Shanghai');

use fengkui\Supports\Http;

/**
 * Wechat 微信支付
 */
class Wechat
{
    // 当前请求的 Host: 头部的内容
    private static $referer = '';
    // 支付完整配置
    private static $config = array(
        'appid'         => '', // 微信支付appid
        'xcxappid'      => '', // 微信小程序appid
        'mch_id'        => '', // 微信支付 mch_id 商户收款账号
        'key'           => '', // 微信支付key
        'appsecret'     => '', // 公众帐号secert(公众号支付专用)
        'notify_url'    => '', // 接收支付状态的连接  改成自己的回调地址
        'redirect_uri'  => '', // 公众号支付时，没有code，获取openid使用
        'cert_client'   => './cert/apiclient_cert.pem', // 证书（退款，红包时使用）
        'cert_key'      => './cert/apiclient_key.pem', // 证书（退款，红包时使用）
    );

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递微信支付相关配置]
     */
    public function __construct($config=NULL, $referer=NULL){
        $config && self::$config = $config;
        self::$referer = $referer ? $referer : $_SERVER['HTTP_HOST'];
    }

    /**
     * [unifiedOrder 统一下单]
     * @param  [type]  $order [订单信息（必须包含支付所需要的参数）]
     * @param  boolean $type  [区分是否是小程序，是则传 true]
     * @return [type]         [description]
     * $order = array(
     *      'body'          => '', // 产品描述
     *      'total_fee'     => '', // 订单金额（分）
     *      'out_trade_no'  => '', // 订单编号
     *      'product_id'    => '', // 产品id
     *      'trade_type'    => '', // 类型：JSAPI--JSAPI支付（或小程序支付）、NATIVE--Native支付、APP--app支付，MWEB--H5支付
     * );
     */
    public static function unifiedOrder($order, $type=NULL)
    {
        $config = array_filter(self::$config);
        // 获取配置项
        $params = array(
            'appid'             => empty($type) ? $config['appid'] : $config['xcxappid'],
            'mch_id'            => $config['mch_id'],
            'nonce_str'         => 'test',
            'spbill_create_ip'  => self::get_iP(),
            'notify_url'        => $config['notify_url']
        );
        $data = array_merge($order, $params); // 合并配置数据和订单数据
        $sign = self::makeSign($data); // 生成签名
        $data['sign'] = $sign;
        $xml = self::array_to_xml($data);
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';//接收xml数据的文件
        $header[] = "Content-type: text/xml";//定义content-type为xml,注意是数组
        $ci = curl_init ($url);
        curl_setopt($ci, CURLOPT_URL, $url);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false); // 兼容本地没有指定curl.cainfo路径的错误
        curl_setopt($ci, CURLOPT_REFERER, self::$referer); //设置 referer
        curl_setopt($ci, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ci, CURLOPT_POST, 1);
        curl_setopt($ci, CURLOPT_POSTFIELDS, $xml);
        $response = curl_exec($ci);
        if(curl_errno($ci)){
            die(curl_error($ci)); // 显示报错信息；终止继续执行
        }
        curl_close($ci);
        $result = self::xml_to_array($response);
        if ($result['return_code']=='FAIL')
            die($result['return_msg']); // 显示错误信息
        if ($result['result_code']=='FAIL')
            die($result['err_code_des']); // 显示错误信息

        $result['sign'] = $sign;
        $result['nonce_str'] = 'test';
        return $result;
    }

    /**
     * [qrcodePay 微信扫码支付]
     * @param  [type] $order [订单信息数组]
     * @return [type]        [description]
     * $order = array(
     *      'body'          => '', // 产品描述
     *      'total_fee'     => '', // 订单金额（分）
     *      'out_trade_no'  => '', // 订单编号
     *      'product_id'    => '', // 产品id（可用订单编号）
     * );
     */
    public static function qrcodePay($order=NULL)
    {
        if(!is_array($order) || count($order) < 4){
            die("数组数据信息缺失！");
        }
        $order['trade_type'] = 'NATIVE'; // Native支付
        $result = self::unifiedOrder($order);
        $decodeurl = urldecode($result['code_url']);
        return $decodeurl;
        // qrcode($decodeurl);
        // qrcodeWithPicture($decodeurl);
    }

    /**
     * [jsPay 获取jssdk需要用到的数据]
     * @param  [type] $order [订单信息数组]
     * @return [type]        [description]
     * $order = array(
     *      'body'          => '', // 产品描述
     *      'total_fee'     => '', // 订单金额（分）
     *      'out_trade_no'  => '', // 订单编号
     *      'product_id'    => '', // 产品id（可用订单编号）
     * );
     */
    public static function jsPay($order=NULL,$code=NULL){
        $config=self::$config;
        if (!is_array($order) || count($order) < 4)
            die("数组数据信息缺失！");
        if (count($order) == 5) {
            $data = self::xcxPay($order, false); // 获取支付相关信息(获取非小程序信息)
            return $data;
        }
        empty($code) && $code = $_GET['code'];
        $params = ['appid' => $config['appid']];
        // 如果没有get参数没有code；则重定向去获取openid；
        if (empty($code)) {
            $params['redirect_uri'] = urlencode($config['redirect_uri']); // 返回的url
            $params['response_type'] = 'code';
            $params['scope'] = 'snsapi_base';
            $params['state'] = $order['out_trade_no']; // 获取订单号

            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?'. http_build_query($params) .'#wechat_redirect';
            header('Location: '.$url);
        } else {
            $params['secret'] = $config['appsecret'];
            $params['code'] = $code;
            $params['grant_type'] = 'authorization_code';

            // 通过code获取access_token
            $url = 'https://api.weixin.qq.com/sns/oauth2/access_token';
            $response = Http::get($url, $params); // 进行GET请求

            $result = json_decode($response, true);
            $order['openid'] = $result['openid']; // 获取到的openid
            $data = self::xcxPay($order, false); // 获取支付相关信息(获取非小程序信息)
            return $data;
        }
    }

    /**
     * [xcxPay 获取jssdk需要用到的数据]
     * @param  [type]  $order [订单信息数组]
     * @param  boolean $type  [区分是否是小程序，默认 true]
     * @return [type]         [description]
     * $order = array(
     *      'body'          => '', // 产品描述
     *      'total_fee'     => '', // 订单金额（分）
     *      'out_trade_no'  => '', // 订单编号
     *      'product_id'    => '', // 产品id（可用订单编号）
     *      'openid'        => '', // 用户openid
     * );
     */
    public static function xcxPay($order=NULL,$type=true)
    {
        if(!is_array($order) || count($order) < 5){
            die("数组数据信息缺失！");
        }
        $order['trade_type'] = 'JSAPI'; // 小程序支付
        $result = self::unifiedOrder($order,$type);
        if ($result['return_code']=='SUCCESS' && $result['result_code']=='SUCCESS') {
            $data = array (
                'appId'     => $type ? self::$config['xcxappid'] : self::$config['appid'],
                'timeStamp' => (string)time(),
                'nonceStr'  => self::get_rand_str(32, 0, 1), // 随机32位字符串
                'package'   => 'prepay_id='.$result['prepay_id'],
                'signType'  => 'MD5', // 加密方式
            );
            $data['paySign'] = self::makeSign($data);
            return $data; // 数据小程序客户端
        } else {
            if ($result['err_code_des'])
                die($result['err_code_des']);
            return false;
        }
    }

    /**
     * [weixinH5 微信H5支付]
     * @param  [type] $order [订单信息数组]
     * @return [type]        [description]
     * $order = array(
     *      'body'          => '', // 产品描述
     *      'total_fee'     => '', // 订单金额（分）
     *      'out_trade_no'  => '', // 订单编号
     *      'product_id'    => '', // 产品id（可用订单编号）
     * );
     */
    public static function h5Pay($order=NULL)
    {
        if(!is_array($order) || count($order) < 4){
            die("数组数据信息缺失！");
        }
        $order['trade_type'] = 'MWEB'; // H5支付
        $result = self::unifiedOrder($order);

        if ($result['return_code']=='SUCCESS' && $result['result_code']=='SUCCESS')
            return $result['mweb_url']; // 返回链接让用户点击跳转
        if ($result['err_code_des'])
            die($result['err_code_des']);
        return false;
    }

    /**
     * [refund 微信支付退款]
     * @param  [type] $order [订单信息]
     * @param  [type] $type  [是否是小程序]
     * $order = array(
     *      'body'          => '', // 退款原因
     *      'total_fee'     => '', // 退款金额（分）
     *      'out_trade_no'  => '', // 订单编号
     *      'transaction_id'=> '', // 微信订单号
     * );
     */
    public static function refund($order, $type=NULL)
    {
        $config = self::$config;
        $data = array(
            'appid'         => empty($type) ? $config['appid'] : $config['xcxappid'] ,
            'mch_id'        => $config['mch_id'],
            'nonce_str'     => 'test',
            'total_fee'     => $order['total_fee'],         //订单金额     单位 转为分
            'refund_fee'    => $order['total_fee'],         //退款金额 单位 转为分
            'sign_type'     => 'MD5',                       //签名类型 支持HMAC-SHA256和MD5，默认为MD5
            'transaction_id'=> $order['transaction_id'],    //微信订单号
            'out_trade_no'  => $order['out_trade_no'],      //商户订单号
            'out_refund_no' => $order['out_trade_no'],      //商户退款单号
            'refund_desc'   => $order['body'],              //退款原因（选填）
        );
        // $unified['sign'] = self::makeSign($unified, $config['KEY']);
        $sign = self::makeSign($data);
        $data['sign'] = $sign;
        $xml = self::array_to_xml($data);
        $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';//接收xml数据的文件
        $cert = [
            'cert' => $config['cert_client'],
            'key' => $config['cert_key'],
        ];
        $response = Http::post($url, $xml, ['Content-type: text/xml'], $cert);
        $result = self::xml_to_array($response);
        // 显示错误信息
        if ($result['return_code']=='FAIL') {
            die($result['return_msg']);
        }
        $result['sign'] = $sign;
        $result['nonce_str'] = 'test';
        return $result;
    }

    /**
     * [notify 回调验证]
     * @return [array] [返回数组格式的notify数据]
     */
    public static function notify()
    {
        $xml = file_get_contents('php://input', 'r'); // 获取xml
        if (!$xml)
            die('暂无回调信息');
        $data = self::xml_to_array($xml); // 转成php数组
        $data_sign = $data['sign']; // 保存原sign
        unset($data['sign']); // sign不参与签名
        $sign = self::makeSign($data);
        // 判断签名是否正确  判断支付状态
        if ($sign===$data_sign && $data['return_code']=='SUCCESS' && $data['result_code']=='SUCCESS') {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * [success 通知支付状态]
     */
    public static function success()
    {
        $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        die($str);
    }

    /**
     * [error 通知支付状态]
     */
    public static function error()
    {
        $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        die($str);
    }

    /**
     * [makeSign 生成签名]
     * 本方法不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function makeSign($data)
    {
        // 去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入key
        $config = self::$config;
        $string_sign_temp = $string_a."&key=".$config['key'];
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($sign);
        return $result;
    }

    /**
     * [xml_to_array 将xml转为array]
     * @param  [type] $xml [xml字符串]
     * @return [type]      [转换得到的数组]
     */
    public static function xml_to_array($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }

    /**
     * [array_to_xml 输出xml字符]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public static function array_to_xml($data)
    {
        if(!is_array($data) || count($data) <= 0){
            die("数组数据异常！");
        }
        $xml = "<xml>";
        foreach ($data as $key=>$val){
            if (is_numeric($val)){
                $xml .= "<".$key.">".$val."</".$key.">";
            }else{
                $xml .= "<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /** fengkui.net
     * [get_rand_str 获取随机字符串]
     * @param  integer $randLength    [长度]
     * @param  integer $addtime       [是否加入当前时间戳]
     * @param  integer $includenumber [是否包含数字]
     * @return [type]                 [description]
     */
    public static function get_rand_str($randLength=6,$addtime=1,$includenumber=0)
    {
        if ($includenumber)
            $chars='abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQEST123456789';
        $chars='abcdefghijklmnopqrstuvwxyz';

        $len=strlen($chars);
        $randStr='';
        for ($i=0;$i<$randLength;$i++){
            $randStr .= $chars[rand(0,$len-1)];
        }
        $tokenvalue = $randStr;
        $addtime && $tokenvalue=$randStr.time();
        return $tokenvalue;
    }

    /** fengkui.net
     * [get_iP 定义一个函数get_iP() 客户端IP]
     * @return [type] [description]
     */
    public static function get_iP()
    {
        if (getenv("HTTP_CLIENT_IP"))
            $ip = getenv("HTTP_CLIENT_IP");
        else if(getenv("HTTP_X_FORWARDED_FOR"))
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        else if(getenv("REMOTE_ADDR"))
            $ip = getenv("REMOTE_ADDR");
        else $ip = "Unknow";

        if(preg_match('/^((?:(?:25[0-5]|2[0-4]\d|((1\d{2})|([1-9]?\d)))\.){3}(?:25[0-5]|2[0-4]\d|((1\d{2})|([1 -9]?\d))))$/', $ip))
            return $ip;
        else
            return '';
    }
}
