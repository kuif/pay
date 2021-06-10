<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2019-09-06 09:50:30
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2021-06-10T18:56:59+08:00
 */
namespace fengkui\Pay;
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 定义时区
ini_set('date.timezone','Asia/Shanghai');

use fengkui\Supports\Http;

/**
 * Wechat 微信支付
 * 新版（V3）接口（更新中）
 */
class Wechat
{
    // 统一下单
    private static $unifiedOrderUrl = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
    // 查询订单
    private static $orderQueryUrl = 'https://api.mch.weixin.qq.com/pay/orderquery';
    // 关闭订单
    private static $closeOrderUrl = 'https://api.mch.weixin.qq.com/pay/closeorder';
    // 申请退款
    private static $refundUrl = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
    // 查询退款
    private static $refundQueryUrl = 'https://api.mch.weixin.qq.com/pay/refundquery';
    // 静默授权，获取code
    private static $authorizeUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize';
    // 通过code获取access_token以及openid
    private static $accessTokenUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token';

    // 新版相关接口
    // GET 获取平台证书列表
    private static $certificatesUrl = 'https://api.mch.weixin.qq.com/v3/certificates';
    // JSAPI下单
    private static $jsapiUrl = 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi';

    // 当前请求的 Host: 头部的内容
    private static $referer = '';
    // 支付完整配置
    private static $config = array(
        'xcxid'         => '', // 小程序appid
        'appid'         => '', // 微信支付appid
        'mchid'         => '', // 微信支付 mch_id 商户收款账号
        'key'           => '', // 微信支付 key
        'appsecret'     => '', // 公众帐号 secert (公众号支付获取openid使用)

        'notify_url'    => '', // 接收支付状态的连接  改成自己的回调地址
        'redirect_url'  => '', // 公众号支付，调起支付页面

        'serial_no'     => '', // 证书序列号
        'cert_client'   => './cert/apiclient_cert.pem', // 证书（退款，红包时使用）
        'cert_key'      => './cert/apiclient_key.pem', // 证书（退款，红包时使用）
    );

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递微信支付相关配置]
     */
    public function __construct($config=NULL, $referer=NULL){
        $config && self::$config = array_merge(self::$config, $config);
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
     *      'trade_type'    => '', // 类型：JSAPI--JSAPI支付（或小程序支付）、NATIVE--Native支付、APP--app支付，MWEB--H5支付
     * );
     */
    public static function unifiedOrder($order, $url)
    {
        $config = array_filter(self::$config);

        // 获取配置项
        $params = array(
            'appid'         => $config['xcxid'],
            'mchid'         => $config['mchid'],
            'scene_info'    => ['payer_client_ip' => self::get_ip()],
            'notify_url'    => $config['notify_url']
        );

        !empty($config['sub_mch_id']) && $params['sub_mch_id'] = $config['sub_mch_id'];

        $order['amount'] = ['total' => $order['total_amount']];
        unset($order['total_amount']);

        $params = array_merge($order, $params); // 合并配置数据和订单数据
        // $params['sign'] = self::makeSign($params); // 生成签名

        $header = self::createAuthorization($url, $params, 'POST'); // 定义content-type为xml,注意是数组

        $response = Http::post($url, json_encode($params), $header);
        $result = json_decode($response, true);

        // dump($result);die;
        return $result;
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
     *      'openid'        => '', // 用户openid
     * );
     */
    public static function xcxPay($order=NULL)
    {
        if(!is_array($order) || count($order) < 4){
            die("数组数据信息缺失！");
        }
        // $order['trade_type'] = 'JSAPI'; // 小程序支付

        $url = self::$jsapiUrl;
        $order['payer'] = ['openid' => $order['openid']];
        unset($order['openid']);

        $result = self::unifiedOrder($order, $url);
        if (!empty($result['prepay_id'])) {
            $data = array (
                'appId'     => self::$config['xcxid'],
                'timeStamp' => (string)time(),
                'nonceStr'  => self::get_rand_str(32, 0, 1), // 随机32位字符串
                'package'   => 'prepay_id='.$result['prepay_id'],
            );
            $data['paySign'] = self::makeSign($data);
            $data['signType'] = 'RSA';
            return $data; // 数据小程序客户端
        } else {
            if ($result['err_code_des'])
                die($result['err_code_des']);
            return false;
        }
    }

    /**
     * [qrcodePay 微信扫码支付]
     * @param  [type] $order [订单信息数组]
     * @return [type]        [description]
     * $order = array(
     *      'body'          => '', // 产品描述
     *      'total_fee'     => '', // 订单金额（分）
     *      'out_trade_no'  => '', // 订单编号
     * );
     */
    public static function qrcodePay($order=NULL)
    {
        if(!is_array($order) || count($order) < 3){
            die("数组数据信息缺失！");
        }
        $order['product_id'] = $order['out_trade_no'] ?? time(); // Native支付
        $order['trade_type'] = 'NATIVE'; // Native支付
        $result = self::unifiedOrder($order);
        $decodeurl = urldecode($result['code_url']);
        return $decodeurl;
    }

    /**
     * [jsPay 获取jssdk需要用到的数据]
     * @param  [type] $order [订单信息数组]
     * @return [type]        [description]
     * $order = array(
     *      'body'          => '', // 产品描述
     *      'total_fee'     => '', // 订单金额（分）
     *      'out_trade_no'  => '', // 订单编号
     * );
     */
    public static function jsPay($order=NULL, $code=NULL){
        $config=self::$config;
        if (!is_array($order) || count($order) < 3)
            die("数组数据信息缺失！");
        if (count($order) == 4) {
            $data = self::xcxPay($order, false); // 获取支付相关信息(获取非小程序信息)
            return $data;
        }
        $code = $_GET['code'] ?? '';
        $redirectUri = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . rtrim($_SERVER['REQUEST_URI'], '/') . '/'; // 重定向地址

        $params = ['appid' => $config['appid']];
        // 如果没有get参数没有code；则重定向去获取openid；
        if (empty($code)) {
            $params['redirect_uri'] = $redirectUri; // 返回的url
            $params['response_type'] = 'code';
            $params['scope'] = 'snsapi_base';
            $params['state'] = $order['out_trade_no']; // 获取订单号

            $url = self::$authorizeUrl . '?'. http_build_query($params) .'#wechat_redirect';
        } else {
            $params['secret'] = $config['appsecret'];
            $params['code'] = $code;
            $params['grant_type'] = 'authorization_code';

            $response = Http::get(self::$accessTokenUrl, $params); // 进行GET请求
            $result = json_decode($response, true);

            $order['openid'] = $result['openid']; // 获取到的openid
            $data = self::xcxPay($order, false); // 获取支付相关信息(获取非小程序信息)

            $url = $config['redirect_url'] ?? $redirectUri;
            $url .= '?data=' . json_encode($data);
        }
        header('Location: '. $url);
        die;
    }

    /**
     * [weixinH5 微信H5支付]
     * @param  [type] $order [订单信息数组]
     * @return [type]        [description]
     * $order = array(
     *      'body'          => '', // 产品描述
     *      'total_fee'     => '', // 订单金额（分）
     *      'out_trade_no'  => '', // 订单编号
     * );
     */
    public static function h5Pay($order=NULL)
    {
        if(!is_array($order) || count($order) < 3){
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
        $cert = [
            'cert' => $config['cert_client'],
            'key' => $config['cert_key'],
        ];
        $response = Http::post(self::$refundUrl, $xml, ['Content-type: text/xml'], $cert);
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
    //生成v3 Authorization
    protected static function createAuthorization($url, $data=[], $method='POST'){
        $config = self::$config;
        //商户号
        $mchid = $config['mchid'];
        // 证书序列号
        $serial_no = $config['serial_no'];

        // 解析url地址
        $url_parts = parse_url($url);
        //生成签名
        $body = [
            'method' => $method,
            'url'   => ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : "")),
            'time'  => time(), // 当前时间戳
            'nonce' => self::get_rand_str(32, 0, 1), // 随机32位字符串
            'data'  => (strtolower($method) == 'post' ? json_encode($data) : $data), // POST请求时 需要 转JSON字符串
        ];
        $sign = self::makeSign($body);
        //Authorization 类型
        $schema = 'WECHATPAY2-SHA256-RSA2048';
        //生成token
        $token = sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"', $mchid, $body['nonce'], $body['time'], $serial_no, $sign);

        $header = [
            'Content-Type:application/json',
            'Accept:application/json',
            'User-Agent:*/*',
            'Authorization: '.  $schema . ' ' . $token
        ];
        return $header;
    }

    public static function makeSign($data){
        $config = self::$config;
        if (!in_array('sha256WithRSAEncryption', \openssl_get_md_methods(true))) {
            throw new \RuntimeException("当前PHP环境不支持SHA256withRSA");
        }
        // 拼接生成签名所需的字符串
        $message = '';
        foreach ($data as $value) {
            $message .= $value . "\n";
        }
        //生成签名
        openssl_sign($message, $raw_sign, openssl_get_privatekey(file_get_contents($config['cert_key'])), 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);
        return $sign;
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
     * [get_ip 定义一个函数get_ip() 客户端IP]
     * @return [type] [description]
     */
    public static function get_ip()
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
