<h1 align="center">Pay</h1>

[![Latest Stable Version](http://poser.pugx.org/fengkui/pay/v)](https://packagist.org/packages/fengkui/pay) [![Total Downloads](http://poser.pugx.org/fengkui/pay/downloads)](https://packagist.org/packages/fengkui/pay) [![Latest Unstable Version](http://poser.pugx.org/fengkui/pay/v/unstable)](https://packagist.org/packages/fengkui/pay) [![License](http://poser.pugx.org/fengkui/pay/license)](https://packagist.org/packages/fengkui/pay)

开发了多次支付，每次都要翻文档、找之前的项目复制过来，费时费事，为了便于支付的开发，  
干脆自己去造了一个简单轮子，整合支付（微信、支付宝、银联、百度、字节跳动）相关开发。

**！！请先熟悉 相关支付 说明文档！！请具有基本的 debug 能力！！**

欢迎 Star，欢迎 PR！

## 特点
- 丰富的扩展，支持微信（商户直连和服务商）、支付宝、银联、百度、字节跳动
- 集成沙箱模式（支付宝、银联），便于开发者调试
- 符合 PSR 标准，方便的与你的框架集成
- 单文件结构清晰、简单，每个类单独封装扩展，便于单独使用

## 运行环境
- PHP 7.0+
- composer

## 使用文档
- [https://docs.fengkui.net/pay/](https://docs.fengkui.net/pay/)

## 支持的支付
### 1、微信（Wechat）

|  method  |  描述  |
|  ------  |  ------  |
|  js  |  JSAPI下单  |
|  app  |  APP支付  |
|  h5  |  H5支付  |
|  scan  |  Navicat支付  |
|  xcx  |  小程序支付  |
|  query  |  查询订单  |
|  close  |  关闭订单  |
|  notify  |  支付结果通知  |
|  refund  |  申请退款  |
|  refundQuery  |  查询退款  |
|  transfer  |  商家转账  |
|  transferQuery  |  查询商家转账  |
|  transferCancel  |  撤销商家转账  |
|  profitSharing  |  请求分账  |
|  profitsharingUnfreeze  |  解冻剩余资金  |
|  profitsharingQuery  |  查询分账结果/查询分账剩余金额  |
|  profitsharingReturn  |  请求分账回退  |
|  receiversAdd  |  添加分账接收方  |
|  receiversDelete  |  删除分账接收方  |

### 2、支付宝（Alipay）

|  method  |  描述  |
|  ------  |  ------  |
|  web  |  电脑网页支付  |
|  wap  |  手机网站支付  |
|  xcx  |  小程序支付  |
|  face  |  发起当面付  |
|  app  |  app支付（JSAPI）  |
|  query  |  查询订单  |
|  close  |  关闭订单  |
|  notify  |  支付宝异步通知  |
|  refund  |  订单退款  |
|  refundQuery  |  查询订单退款  |
|  transfer  |  转账到支付宝账户  |
|  transQuery  | 查询转账到支付宝  |
|  relationBind  |  分账关系绑定与解绑  |
|  relationQuery  |  查询分账关系  |
|  settle  |  统一收单交易结算接口  |
|  settleQuery  |  交易分账查询接口  |
|  onsettleQuery  |  分账比例查询 && 分账剩余金额查询  |
|  getToken  |  获取access_token和user_id  |
|  doGetUserInfo  |  获取会员信息  |

### 3、银联（Union）

|  method  |  描述  |
|  ------  |  ------  |
|  web  |  电脑在线网关支付  |
|  wap  |  手机网页支付  |
|  query  |  查询订单  |
|  notify  |  银联异步通知  |
|  refund  |  订单退款/交易撤销  |

### 4、百度（Baidu）

|  method  |  描述  |
|  ------  |  ------  |
|  xcx  |  小程序支付  |
|  refund  |  申请退款  |
|  notify  |  支付结果通知  |

### 5、字节跳动（Bytedance）

|  method  |  描述  |
|  ------  |  ------  |
|  createOrder  |  下单支付  |
|  queryOrder  |  订单查询  |
|  notifyOrder  |  订单回调验证  |
|  createRefund  |  订单退款  |
|  queryRefund  |  退款查询  |
|  settle  |  分账请求  |
|  querySettle  |  分账查询  |


## 安装
```shell
composer require fengkui/pay
```

## 完善相关配置
```php
# 微信支付配置
$wechatConfig = [
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
    'serial_no'     => '', // 商户API证书序列号（可不传，默认根据证书直接获取）
    'cert_client'   => './cert/apiclient_cert.pem', // 证书（退款，红包时使用）
    'cert_key'      => './cert/apiclient_key.pem', // 商户API证书私钥（Api安全中下载）

    'public_key_id' => '', // 平台证书序列号或支付公钥ID
    // （支付公钥ID请带：PUB_KEY_ID_ 前缀，默认根据证书直接获取，不带前缀）
    'public_key'    => './cert/public_key.pem', // 平台证书或支付公钥（Api安全中下载）
    // （微信支付新申请的，已不支持平台证书，老版调用证书列表，自动生成平台证书，注意目录权限）
];
# 支付宝支付配置
$alipayConfig = [
    'app_id'        => '', // 开发者的应用ID
    'public_key'    => '', // 支付宝公钥，一行字符串
    'private_key'   => '', // 开发者私钥去头去尾去回车，一行字符串

    'notify_url'    => '', // 异步接收支付状态
    'return_url'    => '', // 同步接收支付状态
    'sign_type'     => 'RSA2', // 生成签名字符串所使用的签名算法类型，目前支持RSA2和RSA，默认使用RSA2
    'is_sandbox'    => false, // 是否使用沙箱调试，true使用沙箱，false不使用，默认false不使用
];
# 银联支付配置
$unionConfig = [
    'mchid'         => '', // 商户号
    'sign_pwd'      => '', //商户私钥证书密码
    'sign_path'     => './cert/acp_test_sign.pfx', //商户私钥证书（签名使用）5.1.0
    // 'sign_path'     => './cert/700000000000001_acp.pfx', //签名证书路径5.0.0
    'verify_path'   => './cert/verify_sign_acp.cer', //银联公钥证书（商户验签使用） 
    'acp_root'      => './cert/acp_test_root.cer', //根证书 
    'acp_middle'    => './cert/acp_test_middle.cer', //中级证书 

    'notify_url'    => '', // 异步接收支付状态
    'return_url'    => '', // 同步接收支付状态
    'is_sandbox'    => false, // 是否使用沙箱调试，true使用沙箱，false不使用，默认false不使用
];
# 百度支付配置
$baiduConfig = [
    'deal_id'       => '', // 百度收银台的财务结算凭证
    'app_key'       => '', // 表示应用身份的唯一ID
    'private_key'   => '', // 私钥原始字符串
    'public_key'    => '', // 平台公钥
    'notify_url'    => '', // 支付回调地址
];
# 字节跳动支付配置
$bytedanceConfig = [
    'app_id'        => '', // App ID
    'salt'          => '', // 支付密钥值
    'token'         => '', // 回调验签的Token
    'notify_url'    => '', // 支付回调地址
    'thirdparty_id' => '', // 第三方平台服务商 id，非服务商模式留空
];
```

## 使用说明

### 单独使用
```php
$pay = new \fengkui\Pay\Wechat($wechatConfig); // 微信
$pay = new \fengkui\Pay\Alipay($alipayConfig); // 支付宝
$pay = new \fengkui\Pay\Unionpay($unionConfig); // 银联
$pay = new \fengkui\Pay\Baidu($baiduConfig); // 百度
$pay = new \fengkui\Pay\Bytedance($bytedanceConfig); // 字节跳动
```

### 公共使用
```php
<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2021-06-01T14:55:21+08:00
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2021-06-15 15:39:01
 */
require_once('./vendor/autoload.php');

// 通用支付
class Payment
{
    // 支付类实例化
    protected static $pay = '';
    // 支付类型
    protected static $type = '';
    // 支付相关配置
    protected static $config = [];

    /**
     * [_initialize 构造函数(获取支付类型与初始化配置)]
     * @return [type] [description]
     */
    public function _initialize()
    {
        self::$type = $_GET['type'] ?? 'alipay';
        self::config();
    }

    /**
     * [config 获取配置]
     * @param  string $type [description]
     * @return [type]       [description]
     */
    protected static function config($type='')
    {
        $type = $type ?: self::$type;

        // 相关配置
        $alipayConfig = [];

        if (in_array($type, ['wechat', 'baidu', 'bytedance', 'alipay', 'union'])) {
            $config = $type . "Config";
            self::$config = $config;
        } else {
            die('当前类型配置不存在');
        }

        $type && self::$pay =(new \fengkui\Pay())::$type(self::$config);
    }

    // 支付方法
    public function pay()
    {
        $order = [
            'body'      => 'subject-测试', // 商品描述
            'order_sn'  => time(), // 商户订单号
            'total_amount' => 0.01, // 订单金额
        ];
        $result = self::$pay->web($order); // 直接跳转链接
        echo $result;
    }

}
```

## 一起喝可乐
<div style="text-align:center">
    <img src="https://raw.githubusercontent.com/kuif/common/master/images/support.jpg" style="width:500px"/>
</div>

**请备注一起喝可乐，以便感谢支持**

## LICENSE
MIT
