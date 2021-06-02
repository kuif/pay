<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2020-05-13 17:02:49
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2021-06-02T15:07:04+08:00
 */
namespace fengkui\Pay;

use Yansongda\Pay\Pay;

/**
 * Bytedance 字节跳动支付
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
    private static $createOrderUrl = 'https://developer.toutiao.com/api/apps/ecpay/saas/add_sub_merchant';

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
