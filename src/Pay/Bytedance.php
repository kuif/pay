<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2020-05-13 17:02:49
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2021-06-10T10:21:11+08:00
 */
namespace fengkui\Pay;

use fengkui\Supports\Http;

/**
 * Bytedance 字节跳动支付
 * 小程序担保支付（V1）
 */
class Bytedance
{
    // 服务端预下单
    private static $createOrderUrl = 'https://developer.toutiao.com/api/apps/ecpay/v1/create_order';
    // 订单查询
    private static $queryOrderUrl = 'https://developer.toutiao.com/api/apps/ecpay/v1/query_order';
    // 退款
    private static $createRefundUrl = 'https://developer.toutiao.com/api/apps/ecpay/v1/create_refund';
    // 查询退款
    private static $queryRefundUrl = 'https://developer.toutiao.com/api/apps/ecpay/v1/query_refund';
    // 分账请求
    private static $settleUrl = 'https://developer.toutiao.com/api/apps/ecpay/v1/settle';
    // 查询分账
    private static $querySettleUrl = 'https://developer.toutiao.com/api/apps/ecpay/v1/query_settle';
    // 服务商进件
    private static $addMerchantUrl = 'https://developer.toutiao.com/api/apps/ecpay/saas/add_merchant';
    // 分账方进件
    private static $addSubMerchantUrl = 'https://developer.toutiao.com/api/apps/ecpay/saas/add_sub_merchant';

    // 支付相关配置
    private static $config = array(
        'app_id'    => '111111111', // App ID
        'salt'      => '222222222', // 支付密钥值
        'notify_url' => '', // 支付回调地址
        'thirdparty_id' => '', // 第三方平台服务商 id，非服务商模式留空
    );

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递支付相关配置]
     */
    public function __construct($config=NULL){
        $config && self::$config = $config;
    }

    /**
     * [createOrder 下单支付]
     * @param  [type] $order [description]
     * @return [type]        [description]
     * $order = array(
     *      'body'         => '', // 产品描述
     *      'total_amount' => '', // 订单金额（分）
     *      'order_sn'     => '', // 订单编号
     * );
     */
    public static function createOrder($order)
    {
        $config = self::$config;
        $params = [
            'app_id'        => $config['app_id'], // 是 小程序 AppID
            'out_order_no'  => (string)$order['order_sn'], // 是 开发者侧的订单号, 同一小程序下不可重复
            'total_amount'  => $order['total_amount'], // 是 支付价格; 接口中参数支付金额单位为[分]
            'subject'       => $order['body'], // 是 商品描述; 长度限制 128 字节，不超过 42 个汉字
            'body'          => $order['body'], // 是 商品详情
            'valid_time'    => 3600 * 2, // 是 订单过期时间(秒); 最小 15 分钟，最大两天
            // 'sign'          => '', // 是 开发者对核心字段签名, 签名方式见文档附录, 防止传输过程中出现意外
            // 'cp_extra'      => '', // 否 开发者自定义字段，回调原样回传
            // 'notify_url'    => $config['notify_url'], // 否 商户自定义回调地址
            // 'thirdparty_id' => '', // 否 第三方平台服务商 id，非服务商模式留空
            'disable_msg'   => 1, // 否 是否屏蔽担保支付的推送消息，1-屏蔽 0-非屏蔽，接入 POI 必传
            // 'msg_page'      => '', // 否 担保支付消息跳转页
            // 'store_uid'     => '', // 否 多门店模式下，门店 uid
        ];
        !empty($order['cp_extra']) && $params['cp_extra'] = $order['cp_extra'];
        !empty($config['notify_url']) && $params['notify_url'] = $config['notify_url'];
        !empty($config['thirdparty_id']) && $params['thirdparty_id'] = $config['thirdparty_id'];
        if (!empty($config['msg_page'])) {
            $params['disable_msg'] = 0;
            $params['msg_page'] = $config['msg_page'];
        }

        $params['sign'] = self::makeSign($params);
        // dump($params);die;
        $url = self::$createOrderUrl;
        $response = Http::post($url, json_encode($params));
        $result = json_decode($response, true);
        return $result;
    }

    /**
     * [queryOrder 订单查询]
     * @param  [type] $orderSn [开发者侧的订单号, 不可重复]
     * @return [type]          [description]
     */
    public static function queryOrder($orderSn)
    {
        $config = self::$config;
        $params = [
            'app_id' => $config['app_id'], // 小程序 AppID
            'out_order_no' => (string)$orderSn, // 开发者侧的订单号, 不可重复
            // 'sign' => '', // 开发者对核心字段签名, 签名方式见文档, 防止传输过程中出现意外
            // 'thirdparty_id' => '', // 服务商模式接入必传	第三方平台服务商 id，非服务商模式留空
        ];

        !empty($config['thirdparty_id']) && $params['thirdparty_id'] = $config['thirdparty_id'];
        $params['sign'] = self::makeSign($params);

        $url = self::$queryOrderUrl;
        $response = Http::post($url, json_encode($params));
        $result = json_decode($response, true);
        return $result;
    }

    /**
     * [notifyOrder 订单回调验证]
     * @return [array] [返回数组格式的notify数据]
     */
    public static function notifyOrder()
    {
        $data = $_POST; // 获取回调数据
        $config = self::$config;
        if (!$data || empty($data['msg']))
            die('暂无回调信息');

        $result = json_decode($data['msg'], true); // 进行签名验证
        // 判断签名是否正确  判断支付状态
        if ($result && $data['type']=='payment') {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * [createRefund 订单退款]
     * @param  [type] $order [订单相关信息]
     * @return [type]        [description]
     * $order = array(
     *      'order_sn'     => '', // 订单编号
     *      'refund_sn'    => '', // 退款编号
     *      'total_amount' => '', // 订单金额（分）
     *      'body'         => '', // 退款原因
     * );
     */
    public static function createRefund($order)
    {
        $config = self::$config;
        $params = [
            'app_id'        => $config['app_id'], // 是	小程序 id
            'out_order_no'  => (string)$order['order_sn'], // 是	商户分配订单号，标识进行退款的订单
            'out_refund_no' => (string)$order['refund_sn'], // 是	商户分配退款号
            'refund_amount' => $order['total_amount'], // 是	退款金额，单位[分]
            'reason'        => $order['body'] ?? '用户申请退款', // 是	退款理由，长度上限 100
            // 'cp_extra'      => '', // 否	开发者自定义字段，回调原样回传
            // 'notify_url'    => '', // 否	商户自定义回调地址
            // 'sign'          => '', // 是	开发者对核心字段签名, 签名方式见文档, 防止传输过程中出现意外
            // 'thirdparty_id' => '', // 否，服务商模式接入必传	第三方平台服务商 id，非服务商模式留空
            'disable_msg'   => 1, // 否	是否屏蔽担保支付消息，1-屏蔽
            // 'msg_page'      => '', // 否	担保支付消息跳转页
            // 'all_settle'    => '', // 否	是否为分账后退款，1-分账后退款；0-分账前退款。分账后退款会扣减可提现金额，请保证余额充足
        ];

        !empty($order['cp_extra']) && $params['cp_extra'] = $order['cp_extra'];
        !empty($order['all_settle']) && $params['all_settle'] = $order['all_settle'];
        !empty($config['thirdparty_id']) && $params['thirdparty_id'] = $config['thirdparty_id'];
        if (!empty($config['msg_page'])) {
            $params['disable_msg'] = 0;
            $params['msg_page'] = $config['msg_page'];
        }

        $params['sign'] = self::makeSign($params);

        $url = self::$queryOrderUrl;
        $response = Http::post($url, json_encode($params));
        $result = json_decode($response, true);
        return $result;
    }

    /**
     * [queryRefund 退款查询]
     * @param  [type] $refundSn [开发者侧的订单号, 不可重复]
     * @return [type]           [description]
     */
    public static function queryRefund($refundSn)
    {
        $config = self::$config;
        $params = [
            'app_id' => $config['app_id'], // 小程序 AppID
            'out_refund_no' => $refundSn, // 开发者侧的退款号
            // 'sign' => '', // 开发者对核心字段签名, 签名方式见文档, 防止传输过程中出现意外
            // 'thirdparty_id' => '', // 服务商模式接入必传	第三方平台服务商 id，非服务商模式留空
        ];

        !empty($config['thirdparty_id']) && $params['thirdparty_id'] = $config['thirdparty_id'];
        $params['sign'] = self::makeSign($params);

        $url = self::$queryRefundUrl;
        $response = Http::post($url, json_encode($params));
        $result = json_decode($response, true);
        return $result;
    }

    /**
     * [notifyRefund 退款回调验证]
     * @return [array] [返回数组格式的notify数据]
     */
    public static function notifyRefund()
    {
        $data = $_POST; // 获取回调数据
        $config = self::$config;
        if (!$data || empty($data['status']))
            die('暂无回调信息');

        $result = json_decode($data['msg'], true); // 进行签名验证
        // 判断签名是否正确  判断支付状态
        if ($result && $data['status']!='FAIL') {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * [settle 分账请求]
     * @param  [type] $order [分账信息]
     * @return [type]        [description]
     * $order = array(
     *      'body'         => '', // 产品描述
     *      'total_amount' => '', // 订单金额（分）
     *      'order_sn'     => '', // 订单编号
     * );
     */
    public static function settle($order)
    {
        $config = self::$config;
        $params = [
            'app_id'        => $config['app_id'], // 是 小程序 AppID
            'out_order_no'  => (string)$order['order_sn'], // 是 商户分配订单号，标识进行结算的订单
            'out_settle_no' => (string)$order['settle_sn'], // 是 开发者侧的结算号, 不可重复
            'settle_desc'   => $order['body'], // 是	结算描述，长度限制 80 个字符
            // 'cp_extra'      => '', // 否	开发者自定义字段，回调原样回传
            // 'notify_url'    => '', // 否	商户自定义回调地址
            // 'sign'          => '', // 是	开发者对核心字段签名, 签名方式见文档, 防止传输过程中出现意外
            // 'thirdparty_id' => '', // 否，服务商模式接入必传	第三方平台服务商 id，非服务商模式留空
            // 'settle_params' => '', // 否，其他分账方信息，分账分配参数 SettleParameter 数组序列化后生成的 json 格式字符串
        ];

        !empty($order['cp_extra']) && $params['cp_extra'] = $order['cp_extra'];
        !empty($order['settle_params']) && $params['settle_params'] = $order['settle_params'];
        !empty($config['thirdparty_id']) && $params['thirdparty_id'] = $config['thirdparty_id'];
        $params['sign'] = self::makeSign($params);

        $url = self::$settleUrl;
        $response = Http::post($url, json_encode($params));
        $result = json_decode($response, true);
        return $result;
    }

    /**
     * [querySettle 分账查询]
     * @param  [type] $settleSn [开发者侧的订单号, 不可重复]
     * @return [type]          [description]
     */
    public static function querySettle($settleSn)
    {
        $config = self::$config;
        $params = [
            'app_id' => $config['app_id'], // 小程序 AppID
            'out_settle_no' => $settleSn, // 开发者侧的分账号
            // 'sign' => '', // 开发者对核心字段签名, 签名方式见文档, 防止传输过程中出现意外
            // 'thirdparty_id' => '', // 服务商模式接入必传	第三方平台服务商 id，非服务商模式留空
        ];

        !empty($config['thirdparty_id']) && $params['thirdparty_id'] = $config['thirdparty_id'];
        $params['sign'] = self::makeSign($params);

        $url = self::$querySettleUrl;
        $response = Http::post($url, json_encode($params));
        $result = json_decode($response, true);
        return $result;
    }

    /**
     * [notifySettle 分账回调验证]
     * @return [array] [返回数组格式的notify数据]
     */
    public static function notifySettle()
    {
        $data = $_POST; // 获取回调数据
        $config = self::$config;
        if (!$data || empty($data['status']))
            die('暂无回调信息');

        $result = json_decode($data['msg'], true); // 进行签名验证
        // 判断签名是否正确  判断支付状态
        if ($result && $data['status']!='FAIL') {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * [addMerchant 服务商进件]
     * @param [type]  $accessToken [授权码兑换接口调用凭证]
     * @param [type]  $componentId [小程序第三方平台应用]
     * @param integer $urlType     [链接类型：1-进件页面 2-账户余额页]
     */
    public static function addMerchant($accessToken, $componentId, $urlType=1)
    {
        $params = [
            'component_access_token' => $accessToken, // 是	授权码兑换接口调用凭证
            'thirdparty_component_id' => $componentId, // 是	小程序第三方平台应用 id
            'url_type' => $urlType, // 是	链接类型：1-进件页面 2-账户余额页
        ];

        $url = self::$addMerchantUrl;
        $response = Http::post($url, json_encode($params));
        $result = json_decode($response, true);
        return $result;
    }

    /**
     * [addSubMerchant 分账方进件]
     * @param [type]  $thirdpartyId [小程序第三方平台应用]
     * @param [type]  $merchantId   [商户 id，用于接入方自行标识并管理进件方。由服务商自行分配管理]
     * @param integer $urlType      [链接类型：1-进件页面 2-账户余额页]
     */
    public static function addSubMerchant($thirdpartyId, $merchantId, $urlType=1)
    {
        $params = [
            'thirdparty_id' => $thirdpartyId, // 是	小程序第三方平台应用 id
            'sub_merchant_id' => $merchantId, // 是	商户 id，用于接入方自行标识并管理进件方。由服务商自行分配管理
            'url_type' => $urlType, // 是	链接类型：1-进件页面 2-账户余额页
            // 'sign' => '', // 开发者对核心字段签名, 签名方式见文档, 防止传输过程中出现意外
        ];

        $params['sign'] = self::makeSign($params);

        $url = self::$addSubMerchantUrl;
        $response = Http::post($url, json_encode($params));
        $result = json_decode($response, true);
        return $result;
    }

    /**
     * [success 通知状态]
     */
    public static function success()
    {
        $array = ['err_no'=>0, 'err_tips'=>'success'];
        die(json_encode($array));
    }

    /**
     * [makeSign 生成秘钥]
     * @param  [type] $data [加密数据]
     * @return [type]       [description]
     */
    public static function makeSign($data) {
        $config = self::$config;
        $rList = array();
        foreach($data as $k => $v) {
            if ($k == "other_settle_params" || $k == "app_id" || $k == "sign" || $k == "thirdparty_id")
                continue;
            $value = trim(strval($v));
            $len = strlen($value);
            if ($len > 1 && substr($value, 0,1)=="\"" && substr($value,$len, $len-1)=="\"")
                $value = substr($value,1, $len-1);
            $value = trim($value);
            if ($value == "" || $value == "null")
                continue;
            array_push($rList, $value);
        }
        array_push($rList, $config['salt']);
        sort($rList, 2);
        return md5(implode('&', $rList));
    }

}
