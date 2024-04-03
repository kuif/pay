<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2022-02-26T19:25:26+08:00
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2024-04-03 09:26:13
 */
namespace fengkui\Pay;

use Exception;
use RuntimeException;
use fengkui\Supports\Http;

/**
 * Alipay 支付宝支付（更新中）
 */
class Alipay
{
    //沙盒地址
    private static $sandurl = 'https://openapi-sandbox.dl.alipaydev.com/gateway.do';
    //正式地址
    private static $apiurl  = 'https://openapi.alipay.com/gateway.do';
    //网关地址（设置为公有，外部需要调用）
    private static $gateway;
    // 请求使用的编码格式
    private static $charset = 'utf-8';
    //  仅支持JSON
    private static $format='JSON';
    // 调用的接口版本
    private static $version = '1.0';
    // 商户生成签名字符串所使用的签名算法类型，目前支持RSA2和RSA，推荐使用RSA2
    private static $signType = 'RSA2';
    // 订单超时时间
    private static $timeout = '15m';

    private static $config = array(
        'app_id'        => '', // 开发者的应用ID
        'xcx_id'        => '', // 小程序 appid
        'public_key'    => '', // 支付宝公钥，一行字符串
        'private_key'   => '', // 开发者私钥去头去尾去回车，一行字符串

        'notify_url'    => '', // 异步接收支付状态
        'return_url'    => '', // 同步接收支付状态
        'sign_type'     => 'RSA2', // 生成签名字符串所使用的签名算法类型，目前支持RSA2和RSA，默认使用RSA2
        'is_sandbox'    => false, // 是否使用沙箱调试，true使用沙箱，false不使用，默认false不使用
    );

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递相关配置]
     */
    public function __construct($config=NULL){
        $config && self::$config = array_merge(self::$config, $config);
        isset(self::$config['sign_type']) && self::$signType = self::$config['sign_type'];
        self::$gateway = !empty(self::$config['is_sandbox']) ? self::$sandurl : self::$apiurl; //请求地址，判断是否使用沙箱，默认不使用
    }

    public static function unifiedOrder($order, $params, $type=false)
    {
        // 获取配置项
        $config = self::$config;
        //请求参数
        $requestParams = array(
            'out_trade_no' => !empty($order['order_sn']) ? (string)$order['order_sn'] : '', //唯一标识，订单编号（必须）
            // 'product_code' => $order['product_code'],
            'total_amount' => $order['total_amount'] ?? '', //付款金额，单位:元
            'subject'      => $order['body'] ?? '购买商品',  //订单标题
            "timeout_express" => self::$timeout, //该笔订单允许的最晚付款时间，逾期将关闭交易。取值范围：1m～15d。m-分钟，h-小时，d-天
        );

        // 订单失效时间
        if (!empty($order['time_expire'])) {
            preg_match('/[年\/-]/', $order['time_expire']) && $order['time_expire'] = strtotime($order['time_expire']);
            $time = $order['time_expire'] > time() ? $order['time_expire'] : $order['time_expire'] + time();
            $requestParams['time_expire'] = date('Y-m-d H:i:s', $time);
            unset($order['time_expire']);
        }

        $requestParams = $type ? $order : array_merge($requestParams, $order);
        //公共参数
        $commonParams = array(
            'app_id'    => $config['app_id'],
            // 'method'    => $params['method'], // 接口名称
            'format'    => self::$format,
            'return_url' => $config['return_url'], //同步通知地址
            'charset'   => self::$charset,
            'sign_type' => self::$signType,
            'timestamp' => date('Y-m-d H:i:s'),
            'version'   => self::$version,
            'notify_url' => $config['notify_url'], //异步通知地址
            'biz_content' => json_encode($requestParams, JSON_UNESCAPED_UNICODE),
        );
        $commonParams = array_merge($commonParams, $params);
        // dump($commonParams);die;
        $commonParams["sign"] = self::makeSign($commonParams);
        return $commonParams;
    }

    // 电脑网页支付
    public static function web($order){
        $order['product_code'] = 'FAST_INSTANT_TRADE_PAY'; // 销售产品码，与支付宝签约的产品码名称。注：目前电脑支付场景下仅支持FAST_INSTANT_TRADE_PAY
        $params['method'] = 'alipay.trade.page.pay'; // 接口名称

        $params = self::unifiedOrder($order, $params);
        $result = self::buildRequestForm(self::$gateway, $params);
        return $result;
    }

    // 发起手机网站支付
    public static function wap($order){
        $order['product_code'] = 'QUICK_WAP_WAY'; // 销售产品码，商家和支付宝签约的产品码。手机网站支付为：QUICK_WAP_WAY
        $params['method'] = 'alipay.trade.wap.pay'; // 接口名称

        $params = self::unifiedOrder($order, $params);
        $result = self::buildRequestForm(self::$gateway, $params);
        return $result;
    }

    // 发起当面付
    public static function face($order){
        $order['product_code'] = 'FACE_TO_FACE_PAYMENT';
        empty($order['scene']) && $order['scene'] = 'bar_code'; // 支付场景。bar_code(默认)：当面付条码支付场景； security_code：当面付刷脸支付场景，对应的auth_code为fp开头的刷脸标识串；
        $params['method'] = 'alipay.trade.precreate'; // 接口名称

        $params = self::unifiedOrder($order, $params);
        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_precreate_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    // app支付（JSAPI）
    public static function app($order){
        $order['product_code'] = 'QUICK_MSECURITY_PAY'; //销售产品码，商家和支付宝签约的产品码，APP支付为固定值QUICK_MSECURITY_PAY
        $params['method'] = 'alipay.trade.app.pay'; // 接口名称

        $result = self::unifiedOrder($order, $params);
        return http_build_query($result);
    }

    // JSAPI支付（小程序）
    public static function xcx($order){
        $config = self::$config;
        if(empty($order['order_sn']) || empty($order['total_amount']) || (empty($order['buyer_id']) && empty($order['buyer_open_id']))){
            die("订单数组信息缺失！");
        }
        $order['product_code'] = 'JSAPI_PAY'; //销售产品码，商家和支付宝签约的产品码，APP支付为固定值QUICK_MSECURITY_PAY
        $order['op_app_id'] = $config['xcx_id'];
        $params['app_id'] = $config['xcx_id']; // 替换app_id
        $params['method'] = 'alipay.trade.create'; // 接口名称

        $params = self::unifiedOrder($order, $params);
        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_create_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    /**
     * [query 查询订单]
     * @param  [type]  $orderSn [订单编号]
     * @param  boolean $type    [支付宝支付订单编号，是否是支付宝支付订单号]
     * @return [type]           [description]
     */
    public static function query($orderSn, $type = false) {
        $order = $type ? ['trade_no' => $orderSn] : ['out_trade_no' => $orderSn];
        $params['method'] = 'alipay.trade.query'; // 接口名称
        $params = self::unifiedOrder($order, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_query_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    /**
     * [close 关闭订单]
     * @param  [type]  $orderSn [订单编号]
     * @param  boolean $type    [支付宝支付订单编号，是否是支付宝支付订单号]
     * @return [type]           [description]
     */
    public static function close($orderSn, $type = false) {
        $order = $type ? ['trade_no' => $orderSn] : ['out_trade_no' => $orderSn];
        $params['method'] = 'alipay.trade.close'; // 接口名称
        $params = self::unifiedOrder($order, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_close_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    // 支付宝异步通知
    public static function notify($response = null){
        $config = self::$config;
        $response = $response ?: $_POST;
        $result = is_array($response) ? $response : json_decode($response, true);
        $sign = $result['sign'] ?? '';

        //不参与签名
        unset($result['sign']);
        unset($result['sign_type']);
        $rst = self::verifySign($result, $sign);
        if(!$rst)
            return false;
        return $result;
    }

    // 订单退款
    public static function refund($order)
    {
        $config = self::$config;
        if(empty($order['refund_sn']) || empty($order['refund_amount']) || (empty($order['order_sn']) && empty($order['trade_no']))){
            die("订单数组信息缺失！");
        }

        $refund['refund_amount'] = $order['refund_amount'];
        $refund['out_request_no'] = (string)$order['refund_sn'];

        empty($order['order_sn']) || $refund['out_trade_no'] = (string)$order['order_sn'];
        empty($order['trade_no']) || $refund['trade_no'] = $order['trade_no'];
        empty($order['reason']) || $refund['refund_reason'] = $order['reason'];

        $params['method'] = 'alipay.trade.refund';

        $params = self::unifiedOrder($refund, $params, true);
        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_refund_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    /**
     * [refundQuery 退款查询]
     */
    public static function refundQuery($order) {
        if(empty($order['refund_sn']) || (empty($order['order_sn']) && empty($order['trade_no']))){
            die("订单数组信息缺失！");
        }
        $refund['out_request_no'] = (string)$order['refund_sn'];

        empty($order['order_sn']) || $refund['out_trade_no'] = (string)$order['order_sn'];
        empty($order['trade_no']) || $refund['trade_no'] = $order['trade_no'];

        $params['method'] = 'alipay.trade.fastpay.refund.query'; // 接口名称
        $params = self::unifiedOrder($refund, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_fastpay_refund_query_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    // 转账到支付宝
    public static function transfer($order) {
        $order = array(
            'order_title'   => $order['body'],
            'out_biz_no'    => (string)$order['order_sn'],
            'trans_amount'  => $order['amount'],
            'biz_scene'     => 'DIRECT_TRANSFER',
            'product_code'  => 'TRANS_ACCOUNT_NO_PWD',
            'payee_info'    => $order['payee_info'],
        );

        $params['method'] = 'alipay.fund.trans.uni.transfer'; // 接口名称
        $params = self::unifiedOrder($order, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_fund_trans_uni_transfer_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    // 转账查询
    public static function transQuery($order) {
        if(empty($order['order_sn']) && empty($order['order_id']) && empty($order['pay_fund_order_id'])){
            die("订单数组信息缺失！");
        }

        empty($order['order_id']) || $transfer['order_id'] = (string)$order['order_id'];
        empty($order['pay_fund_order_id']) || $transfer['pay_fund_order_id'] = (string)$order['pay_fund_order_id'];
        if (!empty($order['order_sn'])) {
            $transfer['out_biz_no'] = (string)$order['order_sn'];

            $transfer['product_code'] = $order['product_code'] ?? 'TRANS_ACCOUNT_NO_PWD';
            $transfer['biz_scene'] = $order['biz_scene'] ?? 'DIRECT_TRANSFER';
        }

        $params['method'] = 'alipay.fund.trans.common.query'; // 接口名称
        $params = self::unifiedOrder($transfer, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_fund_trans_common_query_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    // 分账关系绑定与解绑
    public static function relationBind($account, $type = true) {
        $order['out_request_no'] = (string)$account['order_sn'];
        $order['receiver_list'] = $account['list']; // 分账接收方列表，单次传入最多20个
        $params['method'] = 'alipay.trade.royalty.relation.' . ($type ? 'bind' : 'unbind'); // 接口名称
        $params = self::unifiedOrder($order, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_royalty_relation_' . ($type ? 'bind' : 'unbind') . '_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    // 查询分账关系
    public static function relationQuery($orderSn) {
        $order = ['out_request_no' => $orderSn];

        $params['method'] = 'alipay.trade.royalty.relation.batchquery'; // 接口名称
        $params = self::unifiedOrder($order, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_royalty_relation_batchquery_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    // 统一收单交易结算接口
    public static function settle($order) {
        $settle = array(
            'out_request_no' => (string)$order['order_sn'],
            'trade_no'      => (string)$order['trade_no'],
            'royalty_parameters'   => $order['list'],
            'royalty_mode'  => 'async',
        );

        $params['method'] = 'alipay.trade.order.settle'; // 接口名称
        $params = self::unifiedOrder($settle, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_order_settle_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    // 交易分账查询接口
    public static function settleQuery($order = null) {
        if (!empty($order['order_sn']) && !empty($order['trade_no'])) {
            $settle['out_request_no'] = (string)$order['order_sn'];
            $settle['trade_no'] = (string)$order['trade_no'];
        } elseif (!empty($order['settle_no'])) {
            $settle['settle_no'] = (string)$order['settle_no'];
        } else {
            die('参数缺失');
        }
        $params['method'] = 'alipay.trade.order.settle.query'; // 接口名称
        $params = self::unifiedOrder($settle, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_order_settle_query_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    //  分账比例查询 && 分账剩余金额查询
    public static function onsettleQuery($orderSn, $type = false) {
        $order = $type ? ['out_request_no' => $orderSn] : ['trade_no' => $orderSn];
        $params['method'] = 'alipay.trade.' . ($type ? 'royalty.rate' : 'order.onsettle') . '.query'; // 接口名称
        $params = self::unifiedOrder($order, $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_trade_' . ($type ? 'royalty_rate' : 'order_onsettle') . '_query_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    // 获取会员信息
    public static function doGetUserInfo($token)
    {
        $params['method'] = 'alipay.user.info.share'; // 接口名称
        $params['auth_token'] = $token; // 接口名称
        $params = self::unifiedOrder([], $params, true);

        $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
        $result = json_decode($response, true);
        $result = $result['alipay_user_info_share_response'];
        if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
            throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
        }
        return $result;
    }

    /**
     * 获取access_token和user_id
     */
    public function getToken($type = true)
    {
        $config = self::$config;
        //通过code获得access_token和user_id
        if (isset($_GET['auth_code'])){
            //获取code码，以获取openid
            $params = array(
                'app_id'    => $config['app_id'],
                'method'    => 'alipay.system.oauth.token', // 接口名称
                'format'    => self::$format,
                'charset'   => self::$charset,
                'sign_type' => self::$signType,
                'timestamp' => date('Y-m-d H:i:s'),
                'version'   => self::$version,
                'grant_type' =>'authorization_code',
                'code'  => $_GET['auth_code'],
            );

            $params["sign"] = self::makeSign($params);
            $response = Http::post(self::$gateway . '?charset=' . self::$charset, $params);
            $result = json_decode($response, true);
            $result = $result['alipay_system_oauth_token_response'];
            if (isset($result['code']) && $result['code'] != 10000) { // 错误抛出异常
                throw new \Exception("[" . $result['code'] . "] " . $result['sub_code']. ' ' . $result['sub_msg']);
            }
            return $result;
        } else {
            //触发返回code码
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https://' : 'http://';
            $redirectUrl = urlencode($scheme.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']);
            $_SERVER['QUERY_STRING'] && $redirectUrl = $baseUrl.'?'.$_SERVER['QUERY_STRING'];
            $urlObj['app_id'] = $config['app_id'];
            $urlObj['scope'] = $type ? 'auth_base' : 'auth_user';
            $urlObj['redirect_uri'] = urldecode($redirectUrl);
            $bizString = http_build_query($urlObj);
            $url = 'https://openauth' . ($config['is_sandbox'] ? '-sandbox.dl.alipaydev' : '.alipay') . '.com/oauth2/publicAppAuthorize.htm?' . $bizString;
            Header("Location: $url");
            exit();
        }
    }

    protected static function getSignContent($params) {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === self::checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                $v = self::characet($v, self::$charset);
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }

    /**
     * 转换字符集编码
     */
    protected static function characet($data, $targetCharset) {
        if (!empty($data)) {
            $fileType = self::$charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }
     /**
      * 校验$value是否非空
      */
    protected static function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }

    // 生成签名
    protected static function makeSign($data) {
        $data = self::getSignContent($data);
        $priKey = self::$config['private_key'];
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');
        if (self::$signType == "RSA2") {
            //OPENSSL_ALGO_SHA256是php5.4.8以上版本才支持
            openssl_sign($data, $sign, $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }

    // 验签函数（用于查询支付宝数据）
    protected static function verifySign($data, $sign) {
        $public_key = self::$config['public_key'];
        $search = [
            "-----BEGIN PUBLIC KEY-----",
            "-----END PUBLIC KEY-----",
            "\n",
            "\r",
            "\r\n"
        ];
        $public_key = str_replace($search,"",$public_key);
        $public_key=$search[0] . PHP_EOL . wordwrap($public_key, 64, "\n", true) . PHP_EOL . $search[1];

        if (self::$signType == 'RSA') {
            $result = (bool)openssl_verify(self::getSignContent($data), base64_decode($sign), openssl_get_publickey($public_key));
        } elseif (self::$signType == 'RSA2') {
            $result = (bool)openssl_verify(self::getSignContent($data), base64_decode($sign), openssl_get_publickey($public_key), OPENSSL_ALGO_SHA256);
        }
        return $result;
    }

    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $url 请求地址
     * @param $params 请求参数数组
     * @return 提交表单HTML文本
     */
    protected static function buildRequestForm($url, $params) {

        $sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$url."?charset=".self::$charset."' method='POST'>";
        foreach($params as $key=>$val){
            if (false === self::checkEmpty($val)) {
                $val = str_replace("'","&apos;",$val);
                $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
            }
        }
        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='ok' style='display:none;''></form>";
        $sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";

        return $sHtml;
    }
}
