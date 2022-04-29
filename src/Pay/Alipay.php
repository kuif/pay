<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2022-02-26T19:25:26+08:00
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2022-02-26T19:26:24+08:00
 */
namespace fengkui\Pay;

use 
use fengkui\Supports\Http;

use RuntimeException;
use Exception;

/**
 * Alipay 支付宝支付（对接中）
 */
class Alipay
{
    // 调用的接口版本
    private static $apiVersion = '1.0';

    // 商户生成签名字符串所使用的签名算法类型，目前支持RSA2和RSA，推荐使用RSA2
    private static $signType = 'RSA2';

    // 请求使用的编码格式
    private static $charset='utf-8';

    // 	仅支持JSON
    private static $format='json';

    // 支付宝网关
    private static $gatewayUrl = 'https://openapi.alipay.com/gateway.do';
    // private static $token = 'https://api.weixin.qq.com/cgi-bin/token';

    // 换取授权访问令牌接口
    private static $tokenMethod = 'alipay.system.oauth.token';

    // 小程序发送模板消息接口
    private static $sendMethod = 'alipay.open.app.mini.templatemessage.send';

    // 小程序生成推广二维码接口
    private static $qrcodeMethod = 'alipay.open.app.qrcode.create';

    protected static $config = array(
        'app_id'        => '', // 支付宝分配给开发者的应用ID
        'public_key'    => '', // 请填写支付宝公钥，一行字符串
        'private_key'   => '', // 请填写开发者私钥去头去尾去回车，一行字符串

        'notify_url'    => '', // 异步接收支付状态 改成自己的回调地址
        'return_url'    => '', // 同步接收支付状态 改成自己的回调地址
    );

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递相关配置]
     */
    public function __construct($config=NULL){
        $config && self::$config = $config;
    }


}
