<?php
/**
 * User: Zoa Chou
 * Date: 15/8/3
 */

namespace WAFPHP\Lib\Waf;


interface WafInterface{
    /*
     * 默认配置
     */
    public static function getDefaultConfig();

    /*
     * 获取错误信息
     */
    public function getError();

    /*
     * 运行脚本检测
     */
    public function run($config,$client);
}