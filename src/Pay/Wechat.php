<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2019-09-06 09:50:30
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2022-02-26 15:10:39
 */
namespace fengkui\Pay;

use Exception;
use RuntimeException;
use fengkui\Supports\Http;

/**
 * Wechat 微信支付
 * 新版（V3）接口（更新中）
 */
class Wechat
{
    const AUTH_TAG_LENGTH_BYTE = 16;
    // 是否是服务商
    private static $facilitator = false;

    // 新版相关接口
    // GET 获取平台证书列表
    private static $certificatesUrl = 'https://api.mch.weixin.qq.com/v3/certificates';
    // 统一下订单管理
    private static $transactionsUrl = 'https://api.mch.weixin.qq.com/v3/pay/transactions/';
    // 统一下订单管理（服务商）
    private static $partnerTransactionsUrl = 'https://api.mch.weixin.qq.com/v3/pay/partner/transactions/';
    // 申请退款
    private static $refundUrl = 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds';
    // 商家转账到零钱
    private static $batchesUrl = 'https://api.mch.weixin.qq.com/v3/transfer/batches';
    // 请求分账
    private static $profitSharingUrl = 'https://api.mch.weixin.qq.com/v3/profitsharing';
    // 静默授权，获取code
    private static $authorizeUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize';
    // 通过code获取access_token以及openid
    private static $accessTokenUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token';

    // 支付完整配置
    private static $config = array(
        'xcxid'         => '', // 小程序 appid
        'appid'         => '', // 微信支付 appid
        'mchid'         => '', // 微信支付 mch_id 商户收款账号
        'key'           => '', // 微信支付 apiV3key（尽量包含大小写字母，否则验签不通过，服务商模式使用服务商key）
        'appsecret'     => '', // 公众帐号 secert (公众号支付获取 code 和 openid 使用)

        'sp_appid'      => '', // 服务商应用 ID
        'sp_mchid'      => '', // 服务商户号

        'notify_url'    => '', // 接收支付状态的连接  改成自己的回调地址
        'redirect_url'  => '', // 公众号支付，调起支付页面

        // 服务商模式下，使用服务商证书
        'serial_no'     => '', // 证书序列号（可不传，默认根据证书直接获取）
        'cert_client'   => './cert/apiclient_cert.pem', // 证书（退款，红包时使用）
        'cert_key'      => './cert/apiclient_key.pem', // 商户私钥（Api安全中下载）
        'public_key'    => './cert/public_key.pem', // 平台公钥（调动证书列表，自动生成，注意目录权限）
    );

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递微信支付相关配置]
     */
    public function __construct($config=NULL, $referer=NULL){
        $config && self::$config = array_merge(self::$config, $config);
        if (self::$config['sp_appid'] && self::$config['sp_mchid']) {
            self::$facilitator = true; // 服务商模式
            self::$transactionsUrl = self::$partnerTransactionsUrl;
        }
    }

    /**
     * [unifiedOrder 统一下单]
     * @param  [type]  $order [订单信息（必须包含支付所需要的参数）]
     * @param  boolean $type  [区分是否是小程序，是则传 true]
     * @return [type]         [description]
     * $order = array(
     *      'body'         => '', // 产品描述
     *      'order_sn'     => '', // 订单编号
     *      'total_amount' => '', // 订单金额（分）
     * );
     */
    public static function unifiedOrder($order, $type=false)
    {
        $config = self::$config;
        // 获取配置项
        $params = array(
            // 'appid'         => $type ? $config['xcxid'] : $config['appid'], // 由微信生成的应用ID
            // 'mchid'         => $config['mchid'], // 直连商户的商户号
            'description'   => $order['body'], // 商品描述
            'out_trade_no'  => (string)$order['order_sn'], // 商户系统内部订单号
            'notify_url'    => $config['notify_url'], // 通知URL必须为直接可访问的URL
            'amount'        => ['total' => (int)$order['total_amount'], 'currency' => 'CNY'], // 订单金额信息
        );
        if (self::$facilitator) {
            $params['sp_appid'] = $config['sp_appid']; // 服务商应用ID
            $params['sp_mchid'] = $config['sp_mchid']; // 服务商户号
            $params['sub_appid'] = $type ? $config['xcxid'] : $config['appid']; // 子商户的应用ID
            $params['sub_mchid'] = $config['mchid']; // 子商户的商户号
            !empty($order['openid']) && $params['payer'] = ['sub_openid' => $order['openid']];
        } else {
            $params['appid'] = $type ? $config['xcxid'] : $config['appid']; // 由微信生成的应用ID
            $params['mchid'] = $config['mchid']; // 直连商户的商户号
            !empty($order['openid']) && $params['payer'] = ['openid' => $order['openid']];
        }

        !empty($params['payer']) && $params['scene_info'] = ['payer_client_ip' => self::get_ip()]; // IP地址
        !empty($order['attach']) && $params['attach'] = $order['attach']; // 附加数据
        !empty($order['settle_info']) && $params['settle_info'] = ['profit_sharing' => $order['settle_info'] ? true : false, ]; // 结算信息

        // 订单失效时间
        if (!empty($order['time_expire'])) {
            preg_match('/[年\/-]/', $order['time_expire']) && $order['time_expire'] = strtotime($order['time_expire']);
            $time = $order['time_expire'] > time() ? $order['time_expire'] : $order['time_expire'] + time();
            $params['time_expire'] = date(DATE_ATOM, $time);
        }

        if (in_array($order['type'], ['ios', 'android', 'wap'])) {
            $params['scene_info'] = ['payer_client_ip' => self::get_ip()];
            $params['scene_info']['h5_info'] = ['type' => $order['type']];
            $url = self::$transactionsUrl . 'h5'; // 拼接请求地址
        } else {
            $url = self::$transactionsUrl . strtolower($order['type']); // 拼接请求地址
        }
        isset($order['_url']) && $url = $order['_url'];

        // 获取post请求header头
        $header = self::createAuthorization($url, $params, 'POST');
        $response = Http::post($url, json_encode($params, JSON_UNESCAPED_UNICODE), $header);
        $result = json_decode($response, true);
        if (isset($result['code']) && isset($result['message'])) {
            throw new \Exception("[" . $result['code'] . "] " . $result['message']);
        }

        return $result;
    }

    /**
     * [query 查询订单]
     * @param  [type]  $orderSn [订单编号]
     * @param  boolean $type    [微信支付订单编号，是否是微信支付订单号]
     * @return [type]           [description]
     */
    public static function query($orderSn, $type = false)
    {
        $config = self::$config;
        $url = self::$transactionsUrl . ($type ? 'id/' : 'out-trade-no/') . $orderSn;

        if (self::$facilitator) {
            $params['sp_mchid'] = $config['sp_mchid'];
            $params['sub_mchid'] = $config['mchid'];
        } else {
            $params['mchid'] = $config['mchid'];
        }

        $header = self::createAuthorization($url, $params, 'GET');
        $response = Http::get($url, $params, $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [close 关闭订单]
     * @param  [type] $orderSn [微信支付订单编号]
     * @return [type]          [description]
     */
    public static function close($orderSn)
    {
        $config = self::$config;
        if (self::$facilitator) {
            $params['sp_mchid'] = $config['sp_mchid']; // 服务商户号
            $params['sub_mchid'] = $config['mchid']; // 子商户的商户号
        } else {
            $params['mchid'] = $config['mchid']; // 直连商户的商户号
        }
        $url = self::$transactionsUrl . 'out-trade-no/' . $orderSn . '/close';

        $header = self::createAuthorization($url, $params, 'POST');
        $response = Http::post($url, json_encode($params, JSON_UNESCAPED_UNICODE), $header);
        $result = json_decode($response, true);

        return true;
    }

    /**
     * [js 获取jssdk需要用到的数据]
     * @param  [type] $order [订单信息数组]
     * @return [type]        [description]
     */
    public static function js($order=[]){
        $config = self::$config;
        if (!is_array($order) || count($order) < 3)
            die("订单数组信息缺失！");
        if (count($order) == 4 && !empty($order['openid'])) {
            $data = self::xcx($order, false, false); // 获取支付相关信息(获取非小程序信息)
            return $data;
        }
        $code = !empty($order['code']) ? $order['code'] : ($_GET['code'] ?? '');
        $redirectUri = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . rtrim($_SERVER['REQUEST_URI'], '/') . '/'; // 重定向地址
        $params = ['appid' => $config['appid']];
        // 如果没有get参数没有code；则重定向去获取code；
        if (empty($code)) {
            $params['redirect_uri'] = $redirectUri; // 返回的url
            $params['response_type'] = 'code';
            $params['scope'] = 'snsapi_base';
            $params['state'] = $order['order_sn']; // 获取订单号

            $url = self::$authorizeUrl . '?'. http_build_query($params) .'#wechat_redirect';
        } else {
            $params['secret'] = $config['appsecret'];
            $params['code'] = $code;
            $params['grant_type'] = 'authorization_code';

            $response = Http::get(self::$accessTokenUrl, $params); // 进行GET请求
            $result = json_decode($response, true);
            $order['openid'] = $result['openid']; // 获取到的openid
            $data = self::xcx($order, false, false); // 获取支付相关信息(获取非小程序信息)

            if (!empty($order['code'])) {
                return $data;
            }
            $url = $config['redirect_url'] ?? $redirectUri;
            $url .= '?data=' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        header('Location: '. $url);
        die;
    }

    /**
     * [app 获取APP支付需要用到的数据]
     * @param  [type]  $order [订单信息数组]
     * @return [type]         [description]
     */
    public static function app($order=[], $log=false)
    {
        if(empty($order['order_sn']) || empty($order['total_amount']) || empty($order['body'])){
            die("订单数组信息缺失！");
        }
        $order['type'] = 'app'; // 获取订单类型，用户拼接请求地址
        $result = self::unifiedOrder($order, true);
        if (!empty($result['prepay_id'])) {
            $data = array (
                'appId'     => self::$config['appid'], // 微信开放平台审核通过的移动应用appid
                'timeStamp' => (string)time(),
                'nonceStr'  => self::get_rand_str(32, 0, 1), // 随机32位字符串
                'prepayid'  => $result['prepay_id'],
            );
            $data['paySign'] = self::makeSign($data);
            $data['partnerid'] = $config['mchid'];
            $data['package'] = 'Sign=WXPay';
            return $data; // 数据小程序客户端
        } else {
            return $log ? $result : false;
        }
    }

    /**
     * [h5 微信H5支付]
     * @param  [type] $order [订单信息数组]
     * @return [type]        [description]
     */
    public static function h5($order=[], $log=false)
    {
        $order['type'] = isset($order['type']) ? strtolower($order['type']) : 'wap';
        if(empty($order['order_sn']) || empty($order['total_amount']) || empty($order['body']) || !in_array($order['type'], ['ios', 'android', 'wap'])){
            die("订单数组信息缺失！");
        }
        $result = self::unifiedOrder($order);
        if (!empty($result['h5_url'])) {
            return $result['h5_url']; // 返回链接让用户点击跳转
        } else {
            return $log ? $result : false;
        }
    }

    /**
     * [xcx 获取jssdk需要用到的数据]
     * @param  [type]  $order [订单信息数组]
     * @param  boolean $log   [description]
     * @param  boolean $type  [区分是否是小程序，默认 true]
     * @return [type]         [description]
     */
    public static function xcx($order=[], $log=false, $type=true)
    {
        if(empty($order['order_sn']) || empty($order['total_amount']) || empty($order['body']) || empty($order['openid'])){
            die("订单数组信息缺失！");
        }
        $order['type'] = 'jsapi'; // 获取订单类型，用户拼接请求地址
        $config = self::$config;
        $result = self::unifiedOrder($order, $type);
        if (!empty($result['prepay_id'])) {
            $data = array (
                'appId'     => $type ? $config['xcxid'] : $config['appid'], // 由微信生成的应用ID
                'timeStamp' => (string)time(),
                'nonceStr'  => self::get_rand_str(32, 0, 1), // 随机32位字符串
                'package'   => 'prepay_id='.$result['prepay_id'],
            );
            $data['paySign'] = self::makeSign($data);
            $data['signType'] = 'RSA';
            return $data; // 数据小程序客户端
        } else {
            return $log ? $result : false;
        }
    }

    /**
     * [scan 微信扫码支付]
     * @param  [type] $order [订单信息数组]
     * @return [type]        [description]
     */
    public static function scan($order=[], $log=false)
    {
        if(empty($order['order_sn']) || empty($order['total_amount']) || empty($order['body'])){
            die("订单数组信息缺失！");
        }
        $order['type'] = 'native'; // Native支付
        $result = self::unifiedOrder($order);

        if (!empty($result['code_url'])) {
            return urldecode($result['code_url']); // 返回链接扫码跳转
        } else {
            return $log ? $result : false;
        }
    }

    /**
     * [notify 回调验证]
     * @return [array] [返回数组格式的notify数据]
     */
    public static function notify($is_verify = true)
    {
        $config = self::$config;
        $response = file_get_contents('php://input', 'r');
        if ($is_verify) { // 是否进行签名验证
            $server = $_SERVER;
            if (empty($response) || empty($server['HTTP_WECHATPAY_SIGNATURE']))
                return false;
            $body = [
                'timestamp' => $server['HTTP_WECHATPAY_TIMESTAMP'],
                'nonce' => $server['HTTP_WECHATPAY_NONCE'],
                'data' => $response,
            ];
            // 验证应答签名
            $verifySign = self::verifySign($body, trim($server['HTTP_WECHATPAY_SIGNATURE']), trim($server['HTTP_WECHATPAY_SERIAL']));
            if (!$verifySign)
                throw new \Exception("[ 401 ] SIGN_ERROR 签名错误");
        }
        $result = json_decode($response, true);
        if (empty($result) || $result['event_type'] != 'TRANSACTION.SUCCESS' || $result['summary'] != '支付成功') {
            return false;
        }
        // 加密信息
        $associatedData = $result['resource']['associated_data'];
        $nonceStr = $result['resource']['nonce'];
        $ciphertext = $result['resource']['ciphertext'];
        $data = $result['resource']['ciphertext'] = self::decryptToString($associatedData, $nonceStr, $ciphertext);

        return json_decode($data, true);
    }

    /**
     * [refund 微信支付退款]
     * @param  [type] $order [订单信息]
     * @param  [type] $type  [是否是小程序]
     */
    public static function refund($order)
    {
        $config = self::$config;
        if(empty($order['refund_sn']) || empty($order['refund_amount']) || (empty($order['order_sn']) && empty($order['transaction_id']))){
            die("订单数组信息缺失！");
        }
        $params = array(
            'out_refund_no' => (string)$order['refund_sn'], // 商户退款单号
            'funds_account' => 'AVAILABLE', // 退款资金来源
            'amount' => [
                    'refund' => $order['refund_amount'],
                    'currency' => 'CNY',
                ]
        );
        if (!empty($order['transaction_id'])) {
            $params['transaction_id'] = $order['transaction_id'];
            $orderDetail = self::query($order['transaction_id'], true);
        } else {
            $params['out_trade_no'] = $order['order_sn'];
            $orderDetail = self::query($order['order_sn']);
        }
        $params['amount']['total'] = $orderDetail['amount']['total'];
        empty($order['reason']) || $params['reason'] = $order['reason'];
        self::$facilitator && $params['sub_mchid'] = $config['mchid']; // 子商户的商户号

        $url = self::$refundUrl;
        $header = self::createAuthorization($url, $params, 'POST');
        $response = Http::post($url, json_encode($params, JSON_UNESCAPED_UNICODE), $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [queryRefund 查询退款]
     * @param  [type] $refundSn [退款单号]
     * @return [type]           [description]
     */
    public static function queryRefund($refundSn)
    {
        $config = self::$config;
        $url = self::$refundUrl . '/' . $refundSn;
        if (self::$facilitator) {
            $params['sub_mchid'] = $config['mchid']; // 子商户的商户号
        } else {
            $params = '';
        }

        $header = self::createAuthorization($url, $params, 'GET');
        $response = Http::get($url, $params, $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [transfer 付款至用户零钱]
     * @param  array  $order [订单相关信息]
     * @return [type]        [description]
     */
    public static function transfer($order = [])
    {
        $config = self::$config;
        if (empty($order['list']) && isset($order['openid']) && !empty($order['amount']))
            $order['list'] = [[ 'amount' => $order['amount'], 'openid' => $order['openid']]];
        if(empty($order['order_sn']) || empty($order['amount']) || empty($order['body']) || empty($order['list']))
            die("订单数组信息缺失！");
        $list = [];
        foreach ($order['list'] as $k => $v) {
            $detail = [];
            if (empty($v['amount']) || empty($v['openid']))
                die("请填写转账详细信息！");
            if ($v['amount'] >= 2000 && empty($v['name']))
                die("单笔金额大于两千，请填写用户姓名");

            $detail['out_detail_no'] = $v['order_sn'] ?? $order['order_sn'] . $k; // 商家明细单号
            $detail['transfer_amount'] = $v['amount']; // 转账金额
            $detail['transfer_remark'] = $v['remark'] ?? $order['body']; // 单条转账备注（微信用户会收到该备注）
            $detail['openid'] = $v['openid']; // 用户在直连商户应用下的用户标示
            !empty($v['name']) && $detail['user_name'] = self::getEncrypt($v['name']); // 收款用户姓名
            $list[] = $detail;
        }

        $params = array(
            'appid'         => $config['appid'] ?: $config['xcxid'], // 商户账号appid
            'out_batch_no'  => (string)$order['order_sn'], // 商户订单号
            'batch_name'    => $config['name'] ?? $order['body'], // 批次名称
            'batch_remark'  => $order['body'], // 批次备注
            'total_amount'  => $order['amount'], // 转账总金额
            'total_num'     => count($list), // 转账总金额
            'transfer_detail_list' => $list, // 付款备注
        );

        $url = self::$batchesUrl;
        $header = self::createAuthorization($url, $params, 'POST');
        $header[] = 'Wechatpay-Serial: ' . $config['serial_no'];
        $response = Http::post($url, json_encode($params, JSON_UNESCAPED_UNICODE), $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [queryTransfer 查询转账到零钱]
     * @param  [type]  $order [相关单号及批次查询]
     * @param  boolean $type  [是否为微信批次单号查询]
     * @return [type]         [description]
     */
    public static function queryTransfer($order, $type = false)
    {
        if (empty($order['order_sn']) || ($type && empty($order['detail_sn'])))
            die("转账单号缺失");

        if ($type) {
            $url = self::$batchesUrl . '/batch-id/' . $order['order_sn']; // 微信批次单号查询批次单API
            empty($order['detail_sn']) || $url .= '/details/detail-id/' . $order['detail_sn']; // 微信明细单号查询明细单API
        } else {
            $url = self::$batchesUrl . '/out-batch-no/' . $order['order_sn']; // 商家批次单号查询批次单API
            empty($order['detail_sn']) || $url .= '/details/out-detail-no/' . $order['detail_sn']; // 商家明细单号查询明细单API
        }

        $header = self::createAuthorization($url, $params = '', 'GET');
        $response = Http::get($url, $params, $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [profitSharing 请求分账]
     * @param  array  $order [description]
     * @return [type]        [description]
     */
    public static function profitSharing($order = [])
    {
        $config = self::$config;
        if (empty($order['list']) && isset($order['openid']) && !empty($order['amount']))
            $order['list'] = [['account' => $order['openid'], 'amount' => $order['amount']]];
        if(empty($order['transaction_id']) || (empty($order['order_sn']) && empty($order['list']))){
            die("订单数组信息缺失！");
        }
        $list = [];
        foreach ($order['list'] as $k => $v) {
            $detail = [];
            if (empty($v['account']) || empty($v['amount']))
                die("请填写分账详细信息！");

            // 分账接收方类型
            $detail['type'] = isset($v['type']) ? $v['type'] : (mb_strlen($v['account']) < 11 ? 'MERCHANT_ID' : (self::$facilitator ? 'PERSONAL_SUB_OPENID' : 'PERSONAL_OPENID'));
            $detail['account'] = $v['account']; // 分账接收方账号
            !empty($v['name']) && $detail['user_name'] = self::getEncrypt($v['name']); // 分账个人接收方姓名
            $detail['amount'] = $v['amount']; // 分账金额
            $detail['description'] = $v['remark'] ?? ($order['body'] ?? '商家发起分账'); // 分账描述

            $list[] = $detail;
        }

        $params = array(
            'transaction_id'  => $order['transaction_id'], // 微信订单号
            'out_order_no'  => (string)$order['order_sn'], // 商户分账单号
            'receivers' => $list, // 分账接收方列表
            'unfreeze_unsplit'  => $order['unfreeze'] ?? true, // 商户分账单号
        );

        if (self::$facilitator) {
            $params['appid'] = $config['sp_appid']; // 服务商应用ID
            $params['sub_appid'] = $config['appid'] ?: $config['xcxid']; // 子商户的应用ID
            $params['sub_mchid'] = $config['mchid']; // 子商户的商户号
        } else {
            $params['appid'] = $config['appid'] ?: $config['xcxid']; // 商户账号appid
        }

        $url = self::$profitSharingUrl . '/orders';
        $header = self::createAuthorization($url, $params, 'POST');
        $response = Http::post($url, json_encode($params, JSON_UNESCAPED_UNICODE), $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [queryTransfer 解冻剩余资金]
     * @param  [type]  $order [商户单号及微信单号]
     * @return [type]         [description]
     */
    public static function profitsharingUnfreeze($order=[])
    {
        if (empty($order['transaction_id']) || empty($order['order_sn']))
            die("转账单号缺失");

        $params['transaction_id'] = $order['transaction_id'];
        $params['out_order_no'] = $order['order_sn'];
        $params['description'] = $order['reason'] ?? '解冻全部剩余资金';
        self::$facilitator && $params['sub_mchid'] = self::$config['mchid']; // 子商户的商户号

        $url = self::$profitSharingUrl . '/orders/unfreeze';
        $header = self::createAuthorization($url, $params, 'POST');
        $response = Http::post($url, json_encode($params, JSON_UNESCAPED_UNICODE), $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [queryTransfer 查询分账/查询分账剩余金额]
     * @param  [type]  $order [分账单号等]
     * @return [type]         [description]
     */
    public static function queryProfitsharing($order = [])
    {
        if (is_array($order) && (empty($order['transaction_id']) || empty($order['order_sn']))) {
            die("支付单号缺失");

            $params['transaction_id'] = $order['transaction_id'];
            self::$facilitator && $params['sub_mchid'] = self::$config['mchid']; // 子商户的商户号
            $url = self::$profitSharingUrl . '/orders/' . $order['order_sn'];
        } else {
            $params = '';
            $url = self::$profitSharingUrl . '/transactions/' . $order . '/amounts';
        }

        $header = self::createAuthorization($url, $params, 'GET');
        $response = Http::get($url, $params, $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [profitsharingReturn 请求分账回退]
     * @param  [type] $account [分账接收方账号]
     * @param  string $type    [与分账方的关系类型]
     * @param  string $name    [分账个人接收方姓名]
     * @return [type]          [description]
     */
    public function profitsharingReturn($order = [])
    {
        $config = self::$config;

        if(empty($order['return_sn']) || empty($order['return_amount']) || (empty($order['order_sn']) && empty($order['order_id']))){
            die("订单数组信息缺失！");
        }
        $params = array(
            'out_return_no' => (string)$order['return_sn'], // 商户回退单号
            'return_mchid' => $order['return_mchid'], // 回退商户号
            'amount' => $order['return_amount'], // 回退金额
        );
        if (!empty($order['order_id'])) { // 微信分账单号
            $params['order_id'] = $order['order_id'];
        } else { // 商户分账单号
            $params['out_order_no'] = $order['order_sn'];
        }

        $params['description'] = $order['reason'] ?? '用户申请退款'; // 回退描述
        self::$facilitator && $params['sub_mchid'] = $config['mchid']; // 子商户的商户号

        $url = self::$profitSharingUrl . '/return-orders';
        $header = self::createAuthorization($url, $params, 'POST');
        $response = Http::post($url, json_encode($params, JSON_UNESCAPED_UNICODE), $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [receiversAdd 添加分账接收方]
     * @param  [type] $account [分账接收方账号]
     * @param  string $type    [与分账方的关系类型]
     * @param  string $name    [分账个人接收方姓名]
     * @return [type]          [description]
     */
    public function receiversAdd($account, $type='USER', $name='')
    {
        $config = self::$config;

        $params['account'] = $account; // 分账接收方账号
        $name && $params['user_name'] = self::getEncrypt($name); // 分账个人接收方姓名

        $params['type'] = mb_strlen($account) < 11 ? 'MERCHANT_ID' : 'PERSONAL_OPENID'; // 分账接收方类型

        // 与分账方的关系类型
        if (in_array($type, ['STORE', 'STAFF', 'STORE_OWNER', 'PARTNER', 'HEADQUARTER', 'BRAND', 'DISTRIBUTOR', 'USER', 'SUPPLIER'])) {
            $params['relation_type'] = $type;
        } else {
            $params['relation_type'] = 'CUSTOM';
            $params['custom_relation'] = $type;
        }

        if (self::$facilitator) {
            $params['type'] == 'PERSONAL_OPENID' && $params['type'] = 'PERSONAL_SUB_OPENID'; // 服务商跟换分账接收方类型
            $params['appid'] = $config['sp_appid']; // 服务商应用ID
            $params['sub_appid'] = $config['appid'] ?: $config['xcxid']; // 子商户的应用ID
            $params['sub_mchid'] = $config['mchid']; // 子商户的商户号
        } else {
            $params['appid'] = $config['appid'] ?: $config['xcxid']; // 商户账号appid
        }

        $url = self::$profitSharingUrl . '/receivers/add';
        $header = self::createAuthorization($url, $params, 'POST');
        $response = Http::post($url, json_encode($params, JSON_UNESCAPED_UNICODE), $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [receiversDelete 删除分账接收方]
     * @param  [type] $account [分账接收方账号]
     * @param  string $name    [分账个人接收方姓名]
     * @return [type]          [description]
     */
    public function receiversDelete($account, $name='')
    {
        $config = self::$config;

        $params['account'] = $account; // 分账接收方账号
        $name && $params['user_name'] = self::getEncrypt($name); // 分账个人接收方姓名

        $params['type'] = mb_strlen($account) < 11 ? 'MERCHANT_ID' : 'PERSONAL_OPENID'; // 分账接收方类型

        if (self::$facilitator) {
            $params['type'] == 'PERSONAL_OPENID' && $params['type'] = 'PERSONAL_SUB_OPENID'; // 服务商跟换分账接收方类型
            $params['appid'] = $config['sp_appid']; // 服务商应用ID
            $params['sub_appid'] = $config['appid'] ?: $config['xcxid']; // 子商户的应用ID
            $params['sub_mchid'] = $config['mchid']; // 子商户的商户号
        } else {
            $params['appid'] = $config['appid'] ?: $config['xcxid']; // 商户账号appid
        }

        $url = self::$profitSharingUrl . '/receivers/delete';
        $header = self::createAuthorization($url, $params, 'POST');
        $response = Http::post($url, json_encode($params, JSON_UNESCAPED_UNICODE), $header);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [success 通知支付状态]
     */
    public static function success()
    {
        $str = ['code'=>'SUCCESS', 'message'=>'成功'];
        die(json_encode($str, JSON_UNESCAPED_UNICODE));
    }

    /**
     * [createAuthorization 获取接口授权header头信息]
     * @param  [type] $url      [请求地址]
     * @param  array  $params   [请求参数]
     * @param  string $method   [请求方式]
     * @return [type]           [description]
     */
    // 生成v3 Authorization
    protected static function createAuthorization($url, $params=[], $method='POST'){
        $config = self::$config;
        // 商户号（服务商模式使用服务商商户号）
        $mchid = self::$facilitator ? $config['sp_mchid'] : $config['mchid'];
        // $mchid = $config['mchid'];
        // 证书序列号
        if (empty($config['serial_no'])) {
            $certFile = @file_get_contents($config['cert_client']);
            $certArr = openssl_x509_parse($certFile);
            $serial_no = $certArr['serialNumberHex'];
        } else {
            $serial_no = $config['serial_no'];
        }

        // 解析url地址
        $url_parts = parse_url($url);
        $url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : ""));
        if (strtolower($method) == 'get') {
            $query_string = ($params && is_array($params)) ? http_build_query($params) : $params;
            $url = $query_string ? $url . (stripos($url, "?") !== false ? "&" : "?") . $query_string : $url;
        }
        // 生成签名
        $body = [
            'method' => $method,
            'url'   => $url,
            'time'  => time(), // 当前时间戳
            'nonce' => self::get_rand_str(32, 0, 1), // 随机32位字符串
            'data'  => strtolower($method) == 'post' ? json_encode($params, JSON_UNESCAPED_UNICODE) : '', // POST请求时 需要 转JSON字符串
        ];
        $sign = self::makeSign($body);
        // Authorization 类型
        $schema = 'WECHATPAY2-SHA256-RSA2048';
        // 生成token
        $token = sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"', $mchid, $body['nonce'], $body['time'], $serial_no, $sign);

        $header = [
            'Content-Type:application/json',
            'Accept:application/json',
            'User-Agent:*/*',
            'Authorization: '.  $schema . ' ' . $token
        ];
        return $header;
    }

    /**
     * [makeSign 生成签名]
     * @param  [type] $data [加密数据]
     * @return [type]       [description]
     */
    public static function makeSign($data)
    {
        $config = self::$config;
        if (!in_array('sha256WithRSAEncryption', \openssl_get_md_methods(true))) {
            throw new \RuntimeException("当前PHP环境不支持SHA256withRSA");
        }
        // 拼接生成签名所需的字符串
        $message = '';
        foreach ($data as $value) {
            $message .= $value . "\n";
        }
        // 商户私钥
        $private_key = self::getPrivateKey($config['cert_key']);
        // 生成签名
        openssl_sign($message, $sign, $private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * [verifySign 验证签名]
     * @param  [type] $data   [description]
     * @param  [type] $sign   [description]
     * @param  [type] $serial [description]
     * @return [type]         [description]
     */
    public static function verifySign($data, $sign, $serial)
    {
        $config = self::$config;
        if (!in_array('sha256WithRSAEncryption', \openssl_get_md_methods(true))) {
            throw new \RuntimeException("当前PHP环境不支持SHA256withRSA");
        }
        $sign = \base64_decode($sign);
        // 拼接生成签名所需的字符串
        $message = '';
        foreach ($data as $value) {
            $message .= $value . "\n";
        }
        // 获取证书相关信息（平台公钥）
        $publicKey = self::certificates($serial);
        // 验证签名
        $recode = \openssl_verify($message, $sign, $publicKey, 'sha256WithRSAEncryption');
        return $recode == 1 ? true : false;
    }

    //获取私钥
    public static function getPrivateKey($filepath)
    {
        return openssl_pkey_get_private(file_get_contents($filepath));
    }

    //获取公钥
    public static function getPublicKey($filepath)
    {
        return openssl_pkey_get_public(file_get_contents($filepath));
    }

    /**
     * [certificates 获取证书]
     * @return [type] [description]
     */
    public static function certificates($serial)
    {
        $config = self::$config;

        $publicKey = @file_get_contents($config['public_key']);
        if ($publicKey) { // 判断证书是否存在
            $openssl = openssl_x509_parse($publicKey);
            if ($openssl['serialNumberHex'] == $serial && $openssl['validTo_time_t'] > time()) { // 是否是所需证书
                return $publicKey; // 返回证书信息
            }
        }

        $url = self::$certificatesUrl;
        $params = '';

        $header = self::createAuthorization($url, $params, 'GET');
        $response = Http::get($url, $params, $header);
        $result = json_decode($response, true);
        if (empty($result['data'])) {
            throw new \Exception("[" . $result['code'] . "] " . $result['message']);
        }
        foreach ($result['data'] as $key => $certificate) {
            if ($certificate['serial_no'] == $serial && strtotime($certificate['expire_time']) > time()) {
                $publicKey = self::decryptToString(
                    $certificate['encrypt_certificate']['associated_data'],
                    $certificate['encrypt_certificate']['nonce'],
                    $certificate['encrypt_certificate']['ciphertext']
                );

                if ($publicKey) { // 生成public_key证书文件
                    file_put_contents($config['public_key'], $publicKey);
                    return $publicKey; // 返回证书信息
                    break; // 终止循环
                } else {
                    throw new \Exception("[ 404 ] public_key 生成失败，加密字符串解析为空，请检查配置 key 是否匹配");
                }
            }
        }
    }

    /**
     * [getEncrypt 将字符串信息进行加密]
     * @param  [type] $str [description]
     * @return [type]      [description]
     */
    private function getEncrypt($str) {
        //$str是待加密字符串
        $config = self::$config;
        $publicKey = @file_get_contents($config['public_key']);
        $encrypted = '';
        if (openssl_public_encrypt($str, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING)) {
            //base64编码
            $sign = base64_encode($encrypted);
        } else {
            throw new Exception('encrypt failed');
        }
        return $sign;
    }

    /**
     * [decryptToString 证书和回调报文解密]
     * @param  [type] $associatedData [附加数据包（可能为空）]
     * @param  [type] $nonceStr       [加密使用的随机串初始化向量]
     * @param  [type] $ciphertext     [Base64编码后的密文]
     * @return [type]                 [description]
     */
    public static function decryptToString($associatedData, $nonceStr, $ciphertext)
    {
        $config = self::$config;
        $ciphertext = base64_decode($ciphertext);
        if (strlen($ciphertext) <= self::AUTH_TAG_LENGTH_BYTE) {
            return false;
        }

        // ext-sodium (default installed on >= PHP 7.2)
        if (function_exists('\sodium_crypto_aead_aes256gcm_is_available') &&
            \sodium_crypto_aead_aes256gcm_is_available()) {
            return \sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $config['key']);
        }

        // ext-libsodium (need install libsodium-php 1.x via pecl)
        if (function_exists('\Sodium\crypto_aead_aes256gcm_is_available') &&
            \Sodium\crypto_aead_aes256gcm_is_available()) {
            return \Sodium\crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $config['key']);
        }

        // openssl (PHP >= 7.1 support AEAD)
        if (PHP_VERSION_ID >= 70100 && in_array('aes-256-gcm', \openssl_get_cipher_methods())) {
            $ctext = substr($ciphertext, 0, -self::AUTH_TAG_LENGTH_BYTE);
            $authTag = substr($ciphertext, -self::AUTH_TAG_LENGTH_BYTE);

            return \openssl_decrypt($ctext, 'aes-256-gcm', $config['key'], \OPENSSL_RAW_DATA, $nonceStr,
                $authTag, $associatedData);
        }

        throw new \RuntimeException('AEAD_AES_256_GCM需要PHP 7.1以上或者安装libsodium-php');
    }

    /** fengkui.net
     * [get_rand_str 获取随机字符串]
     * @param  integer $randLength    [长度]
     * @param  integer $addtime       [是否加入当前时间戳]
     * @param  integer $includenumber [是否包含数字]
     * @return [type]                 [description]
     */
    public static function get_rand_str($randLength=6, $addtime=0, $includenumber=1)
    {
        if ($includenumber)
            $chars='abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQEST123456789';
        $chars='abcdefghijklmnopqrstuvwxyz';

        $len = strlen($chars);
        $randStr = '';
        for ($i=0; $i<$randLength; $i++){
            $randStr .= $chars[rand(0, $len-1)];
        }
        $tokenvalue = $randStr;
        $addtime && $tokenvalue = $randStr . time();
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
