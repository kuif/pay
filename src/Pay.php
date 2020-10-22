<?php
/**
 * @Author: [FENG] <1161634940@qq.com>
 * @Date:   2020-10-13T17:50:31+08:00
 * @Last Modified by:   [FENG] <1161634940@qq.com>
 * @Last Modified time: 2020-10-22T18:35:29+08:00
 */
namespace fengkui;

use Exception;

/**
 * 小程序基类
 */
class Pay
{
    /**
     * $config 相关配置
     */
    protected static $config = [];

    /**
     * [__construct 构造函数]
     * @param [type] $config [传递小程序相关配置]
     */
    public function __construct(array $config=[]){
        $config && self::$config = $config;
    }

    /**
     * [__callStatic 模式方法（当我们调用一个不存在的静态方法时,会自动调用 __callStatic()）]
     * @param  [type] $method [方法名]
     * @param  [type] $params [方法参数]
     * @return [type]         [description]
     */
    public static function __callStatic($method, $params)
    {
        $app = new self(...$params);
        return $app->create($method);
    }

    /**
     * [create 实例化命名空间]
     * @param  [type] $method [description]
     * @return [type]         [description]
     */
    protected static function create($method)
    {
        $method = ucfirst(strtolower($method));
        $className = __CLASS__ . '\\' . $method;
        if (!class_exists($className)) { // 当类不存在是自动加载
            spl_autoload_register(function($method){
                $filename = dirname(__FILE__) . DIRECTORY_SEPARATOR . basename (__CLASS__) . '/' . $method . '.php';
                if (is_readable($filename)) {
                    require $filename;
                }
            }, true, true);
            $className = $method;
        }


        if (class_exists($className)) {
            return new $className(self::$config);
        } else {
            throw new Exception("ClassName [{$className}] Not Exists");
        }
    }

}
