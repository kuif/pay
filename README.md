<h1 align="center">Xcx</h1>

[![Latest Stable Version](http://poser.pugx.org/fengkui/pay/v)](https://packagist.org/packages/fengkui/pay) [![Total Downloads](http://poser.pugx.org/fengkui/pay/downloads)](https://packagist.org/packages/fengkui/pay) [![Latest Unstable Version](http://poser.pugx.org/fengkui/pay/v/unstable)](https://packagist.org/packages/fengkui/pay) [![License](http://poser.pugx.org/fengkui/pay/license)](https://packagist.org/packages/fengkui/pay)

开发了多次支付，每次都要翻文档、找之前的项目复制过来，费时费事，为了便于支付的开发，干脆自己去造轮子，整合支付（微信、QQ、百度、字节跳动）相关开发。

**！！请先熟悉 相关支付 说明文档！！请具有基本的 debug 能力！！**

欢迎 Star，欢迎 PR！

## 特点
- 丰富的扩展，支持微信、百度、字节跳动
- 符合 PSR 标准，方便的与你的框架集成
- 文件结构清晰，每个类单独封装扩展，便于单独使用

## 运行环境
- PHP 7.0+
- composer

## 支持的支付
### 1、微信（Wechat）

|  method  |  描述  |
| :-------: | :-------:   |
|  js  |  JSAPI下单  |
|  app  |  APP支付  |
|  h5  |  H5支付  |
|  scan  |  Navicat支付  |
|  xcx  |  小程序支付  |
|  query  |  查询订单  |
|  close  |  关闭订单  |
|  refund  |  申请退款  |
|  notify  |  支付结果通知  |

### 2、百度（Baidu）

|  method  |  描述  |
| :-------: | :-------:   |
|  xcx  |  小程序支付  |
|  refund  |  申请退款  |
|  notify  |  支付结果通知  |

### 3、字节跳动（Bytedance）

|  method  |  描述  |
| :-------: | :-------:   |
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
    'xcxid'         => '', // 小程序appid
    'appid'         => '', // 微信支付appid
    'mchid'         => '', // 微信支付 mch_id 商户收款账号
    'key'           => '', // 微信支付 apiV3key（尽量包含大小写字母，否则验签不通过）
    'appsecret'     => '', // 公众帐号 secert (公众号支付获取openid使用)

    'notify_url'    => '', // 接收支付状态的连接  改成自己的回调地址
    'redirect_url'  => '', // 公众号支付，调起支付页面

    'serial_no'     => '', // 证书序列号
    'cert_client'   => './cert/apiclient_cert.pem', // 证书（退款，红包时使用）
    'cert_key'      => './cert/apiclient_key.pem', // 商户私钥（Api安全中下载）
    'public_key'    => './cert/public_key.pem', // 平台公钥（调动证书列表，自动生成）
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
    'notify_url'    => '', // 支付回调地址
    'thirdparty_id' => '', // 第三方平台服务商 id，非服务商模式留空
];
```

## 使用说明

### 单独使用
```php
$xcx = new \fengkui\Pay\Wechat($wechatConfig); // 微信
$xcx = new \fengkui\Pay\Baidu($baiduConfig); // 百度
$xcx = new \fengkui\Pay\Bytedance($bytedanceConfig); // 字节跳动
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

/**
 * 通用支付
 */
class Payment
{

}
```

## 赏一杯咖啡吧
<center class="half">
    <img src="https://fengkui.net/uploads/images/ali.jpg" width="200px"/><img src="https://fengkui.net/uploads/images/wechat.png" width="200px"/>
</center>

## LICENSE
MIT
