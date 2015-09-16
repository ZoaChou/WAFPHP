<?php
/**
 * User: Zoa Chou
 * Date: 15/8/17
 */

namespace WAFPHP\Lib\Model;


interface ModelInterface{
    /*
     * Model初始化
     */
    public static function getInstance($config);

    /*
     * Model默认配置，将会与用户配置覆盖合并，以用户配置为主
     */
    public static function getDefaultConfig();

    /*
     * 检查session是否有效
     */
    public function sessionExists($session,$clientIP);

    /*
     * session心跳
     */
    public function sessionBreath($session,$lifetime);

    /*
     * 颁布session
     */
    public function proclaimSession($session,$ip,$lifetime);

    /*
     * 添加自有session
     */
    public function addSessionValue($session,$key,$value);

    /*
     * 删除自有session
     */
    public function delSessionValue($session,$key);

    /*
     * 获取自有session储存的值
     */
    public function getSessionValue($session,$key);

    /*
     * 根据客户端信息返回是否为黑名单用户
     * Notice:prefix为空时使用全局黑名单列表
     */
    public function isBlackIP($ip,$prefix='');

    /*
     * 根据客户端信息返回是否为白名单用户
     * Notice:prefix为空时使用全局白名单列表
     */
    public function isWhiteSession($session,$clientIP,$prefix='');

    /*
     * 增加指定IP到黑名单
     * Notice:prefix为空时添加到全局黑名单列表
     */
    public function addBlackIP($ip,$lifeTime,$prefix='');

    /*
     * 增加指定session到白名单
     * Notice:prefix为空时添加到全局白名单列表
     */
    public function addWhiteSession($session,$ip,$lifeTime,$prefix='');

    /*
     * 删除指定IP黑名单用户
     * Notice:prefix为空时删除全局黑名单列表中指定IP
     */
    public function delBlackIP($ip,$prefix='');

    /*
     * 删除指定session白名单用户
     * Notice:prefix为空时删除白黑名单列表中指定session
     */
    public function delWhiteSession($session,$prefix='');

    /*
     * 通用获取指定key方法
     */
    public function get($key,$prefix);

    /*
     * 通用设置指定key方法
     */
    public function set($key,$value,$lifetime,$prefix);

    /*
     * 通用删除指定key方法
     */
    public function del($key,$prefix);

    /*
     * 获取最后的错误信息
     */
    public function getError();

    /*
     * 结束时清理资源句柄
     */
    public function quit();
}