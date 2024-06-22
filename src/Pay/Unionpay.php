<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2024-05-12 17:20:18
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2024-06-14 14:11:00
 */
namespace fengkui\Pay;

use Exception;
use RuntimeException;
use fengkui\Supports\Http;

/**
 * 银联支付（更新中）
 */
class Unionpay
{

    //沙盒地址
    private static $sandurl = 'https://gateway.test.95516.com/gateway/api';
    //正式地址
    private static $apiurl  = 'https://gateway.95516.com/gateway/api';
    //网关地址
    private static $gateway;

    private static $config = array(
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
    );

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递相关配置]
     */
    public function __construct($config=NULL){
        $config && self::$config = array_merge(self::$config, $config);
        self::$gateway = !empty(self::$config['is_sandbox']) ? self::$sandurl : self::$apiurl; //请求地址，判断是否使用沙箱，默认不使用
    }


    public static function unifiedOrder($order, $type=false)
    {
        // 获取配置项
        $config = self::$config;

        if (isset($order['total_amount'])) { // 请求参数(修改原始键名)
            $order['orderDesc'] = $order['body']; unset($order['body']); // 描述
            $order['orderId'] = (string)$order['order_sn']; unset($order['order_sn']); //商户订单号
            $order['txnAmt'] = $order['total_amount']; unset($order['total_amount']); //交易金额，单位分
        }

        // 订单失效时间
        if (!empty($params['time_expire'])) {
            preg_match('/[年\/-]/', $order['time_expire']) && $order['time_expire'] = strtotime($order['time_expire']);
            $time = $order['time_expire'] > time() ? $order['time_expire'] : $order['time_expire'] + time();
            $params['payTimeout'] = date('YmdHis', $time);
            unset($order['time_expire']);
        }

        //请求参数
        $params = array(
            'signMethod'    => '01', // 签名方式
            'version'       => '5.1.0', // 版本号
            'encoding'      => 'UTF-8', // 编码方式
            'merId'         => $config['mchid'], // 商户代码
            'accessType'    => '0', // 接入类型
            'currencyCode'  => '156', // 交易币种
            'backUrl'       => self::$config['notify_url'], // 后台通知地址

            'certId'        => self::getCertId(self::$config['sign_path'], self::$config['sign_pwd']), //证书ID
            'txnTime'       => date('YmdHis'), // 订单发送时间
        );

        $params = $type ? $order : array_merge($params, $order);

        if ($params['accessType'] == 1 && (empty($params['acqInsCode']))) {
            throw new \Exception("[ acqInsCode ] 接入类型为收单机构接入时，收单机构代码 需上送");
        }
        if ($params['accessType'] == 2 && (empty($params['subMerId']) || empty($params['subMerName']) || empty($params['subMerAbbr']))) {
            throw new \Exception("[ subMerId|subMerName|subMerAbbr ] 接入类型为收单机构接入时，二级商户代码、名称、简称 需上送");
        }

        // dump($params);die;
        $params["signature"] = self::makeSign($params);
        return $params;
    }

    // 在线网关支付
    public static function web($order){
        $order['bizType'] = '000201'; // 产品类型
        $order['txnType'] = '01'; // 交易类型 ，01：消费
        $order['txnSubType'] = '01'; // 交易子类型， 01：自助消费
        $order['channelType'] = '07'; // 渠道类型 07：PC,平板  08：手机
        $order['frontUrl'] = self::$config['return_url']; // 前台通知地址

        $params = self::unifiedOrder($order);
        $result = self::buildRequestForm(self::$gateway . '/frontTransReq.do', $params);
        return $result;
    }

    // wap支付
    public static function wap($order){
        $order['bizType'] = '000201'; // 产品类型
        $order['txnType'] = '01'; // 交易类型 ，01：消费
        $order['txnSubType'] = '01'; // 交易子类型， 01：自助消费
        $order['channelType'] = '08'; // 渠道类型 07：PC,平板  08：手机
        $order['frontUrl'] = self::$config['return_url']; // 前台通知地址

        $params = self::unifiedOrder($order);
        $result = self::buildRequestForm(self::$gateway . '/frontTransReq.do', $params);
        return $result;
    }

    /**
     * [scan 二维码支付]
     * @param  [array]  $order [支付订单信息]
     * @param  boolean  $type  [是否为预支付  true 是，false 否（默认）]
     * @return [type]          [description]
     */
    public static function scan($order, $type=false){
        $order['bizType'] = '000000'; // 产品类型
        $order['txnType'] = $type ? '02' : '01'; // 交易类型 ，01：消费  02 预支付
        $order['txnSubType'] = $type ? '05' : '07'; // 交易子类型，  07: 申请消费二维码  05：申请预授权二维码
        $order['channelType'] = '08'; // 渠道类型 07：PC,平板  08：手机

        $params = self::unifiedOrder($order);
        $response = Http::post(self::$gateway . '/queryTrans.do', $params);
        parse_str($response, $result);
        unset($result['signPubKeyCert']);
        unset($result['signature']);
        return $result;
    }

    /**
     * [query 查询订单]
     * @param  [type]  $orderId [订单编号]
     * @return [type]           [description]
     */
    public static function query($order) {
        if(empty($order['order_sn']) || empty($order['txn_time'])){
            die("订单数组信息缺失！");
        }
        $order = [
            'orderId' => $order['order_sn'],
            'txnTime' => $order['txn_time'], // 订单支付时间
        ];

        $order['bizType'] = '000000'; // 产品类型
        $order['channelType'] = '08'; // 交易子类
        $order['txnType'] = '00'; // 交易类型
        $order['txnSubType'] = '00'; // 交易子类

        $params = self::unifiedOrder($order);
        $response = Http::post(self::$gateway . '/queryTrans.do', $params);
        parse_str($response, $result);
        unset($result['signPubKeyCert']);
        unset($result['signature']);
        return $result;
    }

    /**
     * [refund 订单退款/交易撤销]
     * @param  [type]  $order [退款信息]
     * @param  boolean $type  [是否为交易撤销  true 交易撤销，false 退款（默认）]
     * @return [type]         [description]
     */
    public static function refund($order, $type=false) {
        $config = self::$config;
        if(empty($order['refund_sn']) || empty($order['query_id'])){
            die("订单数组信息缺失！");
        }

        $order = [
            'orderId'   => $order['refund_sn'], // 退款单号
            'txnAmt'    => $order['refund_amount'], // 退款金额
            'origQryId' => $order['query_id'],  // 原交易查询流水号
        ];

        $order['bizType'] = '000000'; // 产品类型
        $order['channelType'] = '07'; // 交易子类
        $order['txnType'] = $type ? '31' : '04'; // 交易类型
        $order['txnSubType'] = '00'; // 交易子类

        $params = self::unifiedOrder($order);
        $response = Http::post(self::$gateway . '/backTransReq.do', $params);
        parse_str($response, $result);
        unset($result['signPubKeyCert']);
        unset($result['signature']);
        return $result;
    }

    // 银联异步通知
    public static function notify($response = null){
        $config = self::$config;
        $response = $response ?: $_POST;
        $result = is_array($response) ? $response : json_decode($response, true);
        $signature = $result['signature'] ?? '';

        // 不参与签名
        unset($result['signature']);
        $rst = self::verifySign($result, $signature);
        if(!$rst)
            return false;
        return $result;
    }

    /**
     * [makeSign 生成签名]
     * @param  [type] $data [加密数据]
     * @return [type]       [description]
     */
    public static function makeSign($params)
    {
        $config = self::$config;
        // 拼接生成签名所需的字符串
        ksort($params);
        $params_str = urldecode(http_build_query($params));
        $result = false;

        if($params['signMethod'] == '01') {
            $private_key = self::getSignPrivateKey();
            // 转换成key=val&串
            if($params['version'] == '5.0.0'){
                $params_sha1x16 = sha1($params_str, FALSE );
                // 签名
                $result = openssl_sign($params_sha1x16, $signature, $private_key, OPENSSL_ALGO_SHA1);
            } else if($params['version'] == '5.1.0'){
                //sha256签名摘要
                $params_sha256x16 = hash('sha256',$params_str);
                // 签名
                $result = openssl_sign($params_sha256x16, $signature, $private_key, 'sha256');
            }
        } else if($params['signMethod']=='11') {
            if (!checkEmpty($config['secure_key'])) {
                $params_before_sha256 = hash('sha256', $config['secure_key']);
                $params_before_sha256 = $params_str.'&'.$params_before_sha256;
                $params_after_sha256 = hash('sha256', $params_before_sha256);
                $signature = base64_decode($params_after_sha256);
                $result = true;
            }
        } else if($params['signMethod']=='12') {
            throw new \Exception("[ 404 ] signMethod=12未实现");
        } else {
            throw new \Exception("[ 404 ] signMethod不正确");
        }   

        if (!$result)
            throw new \Exception("[ 404 ] >>>>>签名失败<<<<<<<");

        $signature = base64_encode($signature);
        return $signature;
    }

    // 验签函数
    protected static function verifySign($params, $signature)
    {
        $config = self::$config;
        $signature = base64_decode($signature);
        // 拼接生成签名所需的字符串
        ksort($params);
        $params_str = urldecode(http_build_query($params));
        $isSuccess = false;

        if($params['signMethod']=='01')
        {
            $public_key = self::getVerifyPublicKey($params); // 公钥
            if($params['version']=='5.0.0'){
                $params_sha1x16 = sha1($params_str, FALSE);
                $isSuccess = openssl_verify($params_sha1x16, $signature, $public_key, OPENSSL_ALGO_SHA1);
            } else if($params['version']=='5.1.0'){
                $params_sha256x16 = hash('sha256', $params_str);
                $isSuccess = openssl_verify($params_sha256x16, $signature, $public_key, "sha256" );
            }
        } else if($params['signMethod']=='11') {
            if (!checkEmpty($config['secure_key'])) {
                $params_before_sha256 = hash('sha256', $config['secure_key']);
                $params_before_sha256 = $params_str.'&'.$params_before_sha256;
                $params_after_sha256 = hash('sha256',$params_before_sha256);
                $isSuccess = $params_after_sha256 == $signature_str;
            }
        } else if($params['signMethod']=='12') {
            throw new \Exception("[ 404 ] sm3没实现");
        } else {
            throw new \Exception("[ 404 ] signMethod不正确");
        }      
        return $isSuccess == 1 ? true : false;;
    }

    // 获取证书ID(SN)
    private static function getCertId($path, $pwd=false)
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (strtolower($extension) == 'pfx') {
            $pkcs12certdata = file_get_contents($path);
            openssl_pkcs12_read($pkcs12certdata, $certs, $pwd);
            $x509data = $certs['cert'];
        } else {
            $x509data = file_get_contents($path);
        }
        openssl_x509_read($x509data);
        $certdata = openssl_x509_parse($x509data);
        return $certdata['serialNumber'];
    }
    
    // 取签名证书私钥
    private static function getSignPrivateKey() 
    { 
        $pkcs12 = file_get_contents(self::$config['sign_path']); 
        openssl_pkcs12_read($pkcs12, $certs, self::$config['sign_pwd']); 
        return $certs['pkey']; 
    } 
    
    // 验证并获取签名证书
    private static function getVerifyPublicKey($params)
    { 
        $config = self::$config;

        if($params['version']=='5.0.0'){
            //先判断配置的验签证书是否银联返回指定的证书是否一致
            if(self::getCertId(self::$config['verify_path']) != $params['certId']) {
                throw new \Exception('Verify sign cert is incorrect');
            }
            $public_key = @file_get_contents(self::$config['verify_path']);
        } else if($params['version']=='5.1.0'){
            $public_key = $params['signPubKeyCert'] ?: @file_get_contents($config['verify_path']);
        }

        if (empty($public_key) || !in_array($params['version'], ['5.0.0', '5.1.0']))
            throw new \Exception("[ 404 ] validate signPubKeyCert by rootCert failed with error");

        openssl_x509_read($public_key);
        $certInfo = openssl_x509_parse($public_key);

        if ($certInfo['validFrom_time_t'] > time() || $certInfo['validTo_time_t']  < time()) {
            throw new \Exception("[ 404 ] >>>>>证书已到期失效<<<<<<<");
        }
        $acpArry = array(
            $_SERVER ['DOCUMENT_ROOT'] . trim($config['acp_root'], '.'),
            $_SERVER ['DOCUMENT_ROOT'] . trim($config['acp_middle'], '.')
        );
        $result = openssl_x509_checkpurpose($public_key, X509_PURPOSE_ANY, $acpArry);
        if($result !== TRUE)
            throw new \Exception("[ 404 ] validate signPubKeyCert by rootCert failed with error");
        return $public_key;
    }


    // 校验$value是否非空
    protected static function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }

    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $url 请求地址
     * @param $params 请求参数数组
     * @return 提交表单HTML文本
     */
    protected static function buildRequestForm($url, $params) {

        $sHtml = "<form  id='pay_form' name='pay_form' action='".$url."' method='POST'>";
        foreach($params as $key=>$val){
            if (false === self::checkEmpty($val)) {
                $val = str_replace("'","&apos;",$val);
                $sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
            }
        }
        //submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='ok' style='display:none;''></form>";
        $sHtml = $sHtml."<script>document.forms['pay_form'].submit();</script>";

        return $sHtml;
    }

}