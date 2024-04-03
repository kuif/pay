<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2020-09-27T16:28:31+08:00
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2021-06-15T16:53:07+08:00
 */
namespace fengkui\Pay;

use fengkui\Supports\Http;

/**
 * Baidu 百度支付
 */
class Baidu
{
    // 统一订单管理URL
    private static $paymentUrl = 'https://openapi.baidu.com/rest/2.0/smartapp/pay/paymentservice/';

    // 支付相关配置
    private static $config = array(
        'deal_id'       => '', // 百度收银台的财务结算凭证
        'app_key'       => '', // 表示应用身份的唯一ID
        'private_key'   => '', // 私钥原始字符串
        'public_key'    => '', // 平台公钥
        'notify_url'    => '', // 支付回调地址
    );

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递支付相关配置]
     */
    public function __construct($config=NULL){
        $config && self::$config = array_merge(self::$config, $config);
    }

    /**
     * [xcxPay 百度小程序支付]
     * @param  [type]  $order [订单信息数组]
     * @return [type]         [description]
     * $order = array(
     *      'body'          => '', // 产品描述
     *      'total_amount'  => '', // 订单金额（分）
     *      'order_sn'      => '', // 订单编号
     * );
     */
    public static function xcx($order)
    {
        if(!is_array($order) || count($order) < 3)
            die("数组数据信息缺失！");

        $config = self::$config;
        $requestParamsArr = array(
            'appKey'    => $config['app_key'],
            'dealId'    => $config['deal_id'],
            'tpOrderId' => $order['order_sn'],
            'totalAmount' => $order['total_amount'],
        );
        $rsaSign = self::makeSign($requestParamsArr, $config['private_key']);  // 声称百度支付签名
        $bizInfo = array(
            'tpData' => array(
                "appKey"        => $config['app_key'],
                "dealId"        => $config['deal_id'],
                "tpOrderId"     => $order['order_sn'],
                "rsaSign"       => $rsaSign,
                "totalAmount"   => $order['total_amount'],
                "returnData"    => '',
                "displayData"   => array(
                    "cashierTopBlock" => array(
                        array(
                            [ "leftCol" => "订单名称", "rightCol"   => $order['body'] ],
                            [ "leftCol" => "数量", "rightCol" => "1" ],
                            [ "leftCol" => "订单金额", "rightCol"   => $order['total_amount'] ]
                        )
                    )
                ),
                "dealTitle"     => $order['body'],
                "dealSubTitle"  => $order['body'],
                "dealThumbView" => "https://b.bdstatic.com/searchbox/icms/searchbox/img/swan-logo.png",
            ),
            "orderDetailData"   => ''
        );

        $bdOrder = array(
            'dealId'        => $config['deal_id'],
            'appKey'        => $config['app_key'],
            'totalAmount'   => $order['total_amount'],
            'tpOrderId'     => $order['order_sn'],
            'dealTitle'     => $order['body'],
            'signFieldsRange' => 1,
            'rsaSign'       => $rsaSign,
            'bizInfo'       => json_encode($bizInfo, JSON_UNESCAPED_UNICODE),
        );
        return $bdOrder;
    }

    /**
     * [find 查询订单]
     * @param  [type] $orderSn     [开发者订单]
     * @param  [type] $accessToken [access_token]
     * @return [type]              [description]
     */
    public static function find($orderSn, $accessToken)
    {
        $config = self::$config;
        $url = self::$paymentUrl . 'findByTpOrderId';
        $params = [
            'access_token'  => $accessToken, // 获取开发者服务权限说明
            'tpOrderId' => $orderSn, // 开发者订单
            'pmAppKey'  => $config['app_key'], // 调起百度收银台的支付服务
        ];
        $response = Http::get($url, $params);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [cancel 关闭订单]
     * @param  [type] $orderSn     [开发者订单]
     * @param  [type] $accessToken [access_token]
     * @return [type]              [description]
     */
    public static function cancel($orderSn, $accessToken)
    {
        $config = self::$config;
        $url = self::$paymentUrl . 'cancelOrder';
        $params = [
            'access_token'  => $accessToken, // 获取开发者服务权限说明
            'tpOrderId' => $orderSn, // 开发者订单
            'pmAppKey'  => $config['app_key'], // 调起百度收银台的支付服务
        ];
        $response = Http::get($url, $params);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [refund baidu支付退款]
     * @param  [type] $order [订单信息]
     * @param  [type] $type  [退款类型]
     * $order = array(
     *      'order_sn'      => '', // 订单编号
     *      'refund_sn'     => '', // 退款编号
     *      'refund_amount' => '', // 退款金额（分）
     *      'body'          => '', // 退款原因
     *      'access_token'  => '', // 获取开发者服务权限说明
     *      'order_id'      => '', // 百度收银台订单 ID
     *      'user_id'       => '', // 百度收银台用户 id
     * );
     */
    public static function refund($order=[], $type=1)
    {
        $config = self::$config;

        $params = array(
            'access_token'      => $order['access_token'], // 获取开发者服务权限说明
            // 'applyRefundMoney'  => $order['refund_amount'], // 退款金额，单位：分。
            'bizRefundBatchId'  => $order['refund_sn'], // 开发者退款批次
            'isSkipAudit'       => 1, // 是否跳过审核，不需要百度请求开发者退款审核请传 1，默认为0； 0：不跳过开发者业务方审核；1：跳过开发者业务方审核。
            'orderId'           => $order['order_id'], // 百度收银台订单 ID
            'refundReason'      => $order['reason'], // 退款原因
            'refundType'        => $type, // 退款类型 1：用户发起退款；2：开发者业务方客服退款；3：开发者服务异常退款。
            'tpOrderId'         => $order['order_sn'], // 开发者订单 ID
            'userId'            => $order['user_id'], // 百度收银台用户 id
            'pmAppKey'          => $config['app_key'], // 调起百度收银台的支付服务
        );
        !empty($order['refund_amount']) && $params['applyRefundMoney'] = $order['refund_amount'];

        $url = self::$paymentUrl . 'applyOrderRefund';
        $response = Http::post($url, $params);
        $result = json_decode($response, true);
        // // 显示错误信息
        // if ($result['msg']!='success') {
        //     return false;
        //     // die($result['msg']);
        // }
        return $result;
    }

    /**
     * [findRefund 查询退款订单]
     * @param  [type] $orderSn     [开发者订单]
     * @param  [type] $accessToken [access_token]
     * @return [type]              [description]
     */
    public static function findRefund($orderSn, $userId, $accessToken)
    {
        $config = self::$config;
        $url = self::$paymentUrl . 'findOrderRefund';
        $params = [
            'access_token'  => $accessToken, // 获取开发者服务权限说明
            'tpOrderId' => $orderSn, // 开发者订单
            'userId'    => $userId, // 百度收银台用户 ID
            'pmAppKey'  => $config['app_key'], // 调起百度收银台的支付服务
        ];
        $response = Http::get($url, $params);
        $result = json_decode($response, true);

        return $result;
    }

    /**
     * [notify 回调验证]
     * @return [array] [返回数组格式的notify数据]
     */
    public static function notify()
    {
        $data = $_POST; // 获取xml
        $config = self::$config;
        if (!$data || empty($data['rsaSign']))
            die('暂无回调信息');

        $result = self::verifySign($data, $config['public_key']); // 进行签名验证
        // 判断签名是否正确  判断支付状态
        if ($result && $data['status']==2) {
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
        $array = ['errno'=>0, 'msg'=>'success', 'data'=> ['isConsumed'=>2] ];
        die(json_encode($array));
    }

    /**
     * [error 通知支付状态]
     */
    public static function error()
    {
        $array = ['errno'=>0, 'msg'=>'success', 'data'=> ['isErrorOrder'=>1, 'isConsumed'=>2] ];
        die(json_encode($array));
    }

    /**
     * [makeSign 使用私钥生成签名字符串]
     * @param  array  $assocArr     [入参数组]
     * @param  [type] $rsaPriKeyStr [私钥原始字符串，不含PEM格式前后缀]
     * @return [type]               [签名结果字符串]
     */
    public static function makeSign(array $assocArr, $rsaPriKeyStr)
    {
        $sign = '';
        if (empty($rsaPriKeyStr) || empty($assocArr)) {
            return $sign;
        }
        if (!function_exists('openssl_pkey_get_private') || !function_exists('openssl_sign')) {
            throw new Exception("openssl扩展不存在");
        }
        $rsaPriKeyPem = self::convertRSAKeyStr2Pem($rsaPriKeyStr, 1);
        $priKey = openssl_pkey_get_private($rsaPriKeyPem);
        if (isset($assocArr['sign'])) {
            unset($assocArr['sign']);
        }
        ksort($assocArr); // 参数按字典顺序排序
        $parts = array();
        foreach ($assocArr as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        $str = implode('&', $parts);
        openssl_sign($str, $sign, $priKey);
        openssl_free_key($priKey);

        return base64_encode($sign);
    }

    /**
     * [verifySign 使用公钥校验签名]
     * @param  array  $assocArr     [入参数据，签名属性名固定为rsaSign]
     * @param  [type] $rsaPubKeyStr [公钥原始字符串，不含PEM格式前后缀]
     * @return [type]               [验签通过|false 验签不通过]
     */
    public static function verifySign(array $assocArr, $rsaPubKeyStr)
    {
        if (!isset($assocArr['rsaSign']) || empty($assocArr) || empty($rsaPubKeyStr)) {
            return false;
        }
        if (!function_exists('openssl_pkey_get_public') || !function_exists('openssl_verify')) {
            throw new Exception("openssl扩展不存在");
        }

        $sign = $assocArr['rsaSign'];
        unset($assocArr['rsaSign']);
        if (empty($assocArr)) {
            return false;
        }
        ksort($assocArr); // 参数按字典顺序排序
        $parts = array();
        foreach ($assocArr as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        $str = implode('&', $parts);
        $sign = base64_decode($sign);
        $rsaPubKeyPem = self::convertRSAKeyStr2Pem($rsaPubKeyStr);
        $pubKey = openssl_pkey_get_public($rsaPubKeyPem);
        $result = (bool)openssl_verify($str, $sign, $pubKey);
        openssl_free_key($pubKey);

        return $result;
    }

    /**
     * [convertRSAKeyStr2Pem 将密钥由字符串（不换行）转为PEM格式]
     * @param  [type]  $rsaKeyStr [原始密钥字符串]
     * @param  integer $keyType   [0 公钥|1 私钥，默认0]
     * @return [type]             [PEM格式密钥]
     */
    public static function convertRSAKeyStr2Pem($rsaKeyStr, $keyType = 0)
    {
        $pemWidth = 64;
        $rsaKeyPem = '';

        $begin = '-----BEGIN ';
        $end = '-----END ';
        $key = ' KEY-----';
        $type = $keyType ? 'RSA PRIVATE' : 'PUBLIC';

        $keyPrefix = $begin . $type . $key;
        $keySuffix = $end . $type . $key;

        $rsaKeyPem .= $keyPrefix . "\n";
        $rsaKeyPem .= wordwrap($rsaKeyStr, $pemWidth, "\n", true) . "\n";
        $rsaKeyPem .= $keySuffix;

        if (!function_exists('openssl_pkey_get_public') || !function_exists('openssl_pkey_get_private')) {
            return false;
        }
        if ($keyType == 0 && false == openssl_pkey_get_public($rsaKeyPem)) {
            return false;
        }
        if ($keyType == 1 && false == openssl_pkey_get_private($rsaKeyPem)) {
            return false;
        }

        return $rsaKeyPem;
    }

}
