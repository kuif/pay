<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2020-05-13 17:02:49
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2021-06-08T10:45:11+08:00
 */
namespace fengkui\Pay;

use fengkui\Supports\Http;

/**
 * Bytedance 字节跳动支付
 * 小程序担保支付
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
        'mch_id'    => '', // 商户号
        'app_id'    => '', // App ID
        'secret'    => '', // 支付secret
        'notify_url' => '', // 支付回调地址
    );

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递支付相关配置]
     */
    public function __construct($config=NULL){
        $config && self::$config = $config;
    }

    /**
     * [xcxPay ]
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
            'app_id'        => $config['app_id'], // 小程序 AppID
            'out_order_no'  => $order['order_sn'], // 开发者侧的订单号, 同一小程序下不可重复
            'total_amount'  => $order['total_amount'], // 支付价格; 接口中参数支付金额单位为[分]
            'subject'       => $order['body'], // 商品描述; 长度限制 128 字节，不超过 42 个汉字
            'body'          => $order['body'], // 商品详情
            'valid_time'    => $order['body'], // 订单过期时间(秒); 最小 15 分钟，最大两天
            // 'sign'          => '', // 开发者对核心字段签名, 签名方式见文档附录, 防止传输过程中出现意外
            // 'cp_extra'      => '', // 开发者自定义字段，回调原样回传
            'notify_url'    => $config['notify_url'], // 商户自定义回调地址
            // 'thirdparty_id' => '', // 第三方平台服务商 id，非服务商模式留空
            'disable_msg'   => 1, // 是否屏蔽担保支付的推送消息，1-屏蔽 0-非屏蔽，接入 POI 必传
            // 'msg_page'      => '', // 担保支付消息跳转页
            // 'store_uid'     => '', // 多门店模式下，门店 uid
        ];
        !empty($order['cp_extra']) && $params['cp_extra'] = $order['cp_extra'];
        !empty($config['thirdparty_id']) && $params['thirdparty_id'] = $config['thirdparty_id'];
        if (!empty($config['msg_page'])) {
            $params['disable_msg'] = 0;
            $params['msg_page'] = $config['msg_page'];
        }

        $params['sign'] = self::makeSign($params);

        $url = self::$createOrderUrl;
        $response = Http::post($url, $params);
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
     * [makeSign 生成秘钥]
     * @param  [type] $data [加密数据]
     * @return [type]       [description]
     */
    public static function makeSign($data) {
        $rList = array();
        foreach($data as $k = >$v) {
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
        array_push($rList, "your_payment_salt");
        sort($rList, 2);
        return md5(implode('&', $rList));
    }

}
