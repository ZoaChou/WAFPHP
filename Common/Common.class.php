<?php
/**
 * User: Zoa Chou
 * Date: 15/7/30
 */

namespace WAFPHP\Common;
use WAFPHP\WAFPHP;


class Common{
    /*
     * 系统内部使用的define，此方法会将名称强制转换成大写
     * @param $name string define的名称
     * @param $value string define的值
     */
    public static function setDefine($name,$value){
        $name = strtoupper($name);
        defined(WAF_PREFIX.$name) or define(WAF_PREFIX.$name,$value);
    }

    /*
     * 系统内部使用获取全局常量方法，此方法会将名称强制转换成大写
     * @param $name string define的名称
     * @return string/null 如果存在则返回define的值，不存在则返回null
     */
    public static function getDefine($name){
        $name = strtoupper($name);
        if(defined(WAF_PREFIX.$name)){
            return constant(WAF_PREFIX.$name);
        }else{
            return null;
        }
    }

    /*
     * 系统内部定义global的方法
     * @param $name string global的名称
     * @param $value string global的值
     */
    public static function setGlobal($name,$value){
        $GLOBALS[WAF_PREFIX.$name] = $value;
    }

    /*
     * 系统内部使用获取global值的方法
     * @param $name string global的名称
     * @return string/false 如果存在则返回global的值，不存在则返回false
     */
    public static function getGlobal($name){
        if(isset($GLOBALS[WAF_PREFIX.$name])){
            return $GLOBALS[WAF_PREFIX.$name];
        }else{
            return false;
        }
    }

    /*
     * 以当前系统目录分隔符组装路径
     */
    public static function getPath($path){
        //如果当前路径末位已经包含改系统目录分隔符则直接返回
        if(substr($path,-1,1) == DIRECTORY_SEPARATOR){
            return $path;
        }else{
            return $path.DIRECTORY_SEPARATOR;
        }
    }

    /*
     * 获取客户端真实IP
     * @author 王滔
     */
    public static function getClientIp(){
        $ip = $_SERVER['REMOTE_ADDR'];

        if(!preg_match('#^(10|172\.16|192\.168\.)#', $ip)) {
            //不是一个内网地址,则直接使用,因为在反向代理架构下,这是一个内网地址
            return $ip;
        }else if (getenv("HTTP_X_REAL_IP")) {
            //nginx代理获取到用户真实ip,发在这个环境变量上
            $ip = getenv("HTTP_X_REAL_IP");
        } else if (getenv("HTTP_X_FORWARDED_FOR")) {
            //没有,则使用这个
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        } else if ($_SERVER['REMOTE_ADDR']) {
            //非反向代理架构中,REMOTE_ADDR这个值将会是客户端的ip。如果是反向代理架构，将会是服务器的ip
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        //HTTP_CLIENT_IP攻击者可以伪造一个这样的头部信息，导致获取的是攻击者随意设置的ip地址。所以不必使用
        return $ip;
    }

    /*
     * 检测IP是否在IP列表中
     */
    public static function checkIP($ip,$ipList){
        if(!$ipList || !is_array($ipList)){
            return null;
        }

        unset($value);
        foreach($ipList as $value){
            // IP段检测
            if(strpos($value,'-')){
                if(self::ipScope($ip,$value)){
                    return true;
                }
            }else{
                //单IP检测
                if($value == $ip){
                    return true;
                }
            }
        }

        return false;
    }

    /*
     * 检测IP是否在给定的范围内
     */
    public static function ipScope($ip,$scope){
        $scopeArr = explode('-',$scope);
        $ipStart = $scopeArr[0];
        $ipEnd = $scopeArr[1];
        $ipStartArr = explode('.',$ipStart);
        $ipEndArr = explode('.',$ipEnd);
        $ipArr = explode('.',$ip);

        // A-D段检测
        for($i = 0; $i < 4 ;$i++){
            $ipTmpStart = $ipStartArr[$i];
            $ipTmpEnd = $ipEndArr[$i];
            $ipTmp = $ipArr[$i];
            if($ipTmpStart != $ipTmpEnd){
                $max = max($ipTmpStart,$ipTmpEnd);
                $min = min($ipTmpStart,$ipTmpEnd);
                if($ipTmp >=$min && $ipTmp <= $max){
                    return true;
                }
            }else{
                if($ipTmp != $ipTmpStart){
                    return false;
                }
            }
        }

        return false;
    }

    /*
     * 检测UA是否在列表中
     */
    public static function checkUA($ua,$uaList,$ignoreCase = true,$dnsReverse = false,$ip = ''){
        if(!$uaList || !is_array($uaList)){
            return null;
        }

        unset($key);
        unset($value);
        foreach($uaList as $key => $value){
            //忽略大小写
            if($ignoreCase){
                $ua = strtolower($ua);
                $key = strtolower($key);
                $value = strtolower($value);
            }

            // 不需要DNS反向解析
            if(!$dnsReverse){
                if(preg_match('/'.$value.'/',$ua)){
                    return true;
                }
            }else{
                if(preg_match('/'.$key.'/',$ua)){
                    // DNS反向解析
                    $host = gethostbyaddr($ip);
                    if($ignoreCase){
                        $host = strtolower($host);
                    }
                    // 匹配DNS反向解析的结果是否正确
                    if(preg_match('/'.$value.'/',$host)){
                        return true;
                    }
                }
            }

        }

        return false;
    }

    /*
     * 来自ThinkPHP的优化版var_dump，方便调试
     */
    public static function dump($var, $echo=true, $label=null, $strict=true) {
        $label = ($label === null) ? '' : rtrim($label) . ' ';
        if (!$strict) {
            if (ini_get('html_errors')) {
                $output = print_r($var, true);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            } else {
                $output = $label . print_r($var, true);
            }
        } else {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();
            if (!extension_loaded('xdebug')) {
                $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
                $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
            }
        }
        if ($echo) {
            echo($output);
            return null;
        }else
            return $output;
    }

    /*
     * 获取配置数据
     * Notice: 会自动将配置名转换成大写
     */
    public static function getConfig($name,$config){
        $name = strtoupper($name);
        return isset($config[$name]) ? $config[$name] : null;
    }

    /*
     * 检测当前请求是否为post
     */
    public static function isPost(){
        return ($_SERVER['REQUEST_METHOD'] == 'POST') ? true : false;
    }

    /*
     * 检测当前请求是否为ajax
     */
    public static function isAjax($ajaxFlag=''){
        if($ajaxFlag){
            $isAjax = (
                (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
                || !empty($_GET[$ajaxFlag])
                || !empty($_POST[$ajaxFlag])
            ) ? true : false;
        }else{
            $isAjax = (
                isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
            ) ? true : false;
        }

        return $isAjax;
    }

    /*
     * 系统自有session操作通用方法
     */
    public static function session($key,$value=''){
        return self::M()->session($key,$value);
    }

    /*
     * 系统通用Model实例
     */
    public static function M(){
        $wafPHP = WAFPHP::getInstance();
        return $wafPHP->Model;
    }

    /*
     * 系统通用Log实例
     */
    public static function Log(){
        $wafPHP = WAFPHP::getInstance();
        return $wafPHP->Log;
    }

    /*
     * 转换字节单位输出
     */
    public static function convertUnit($size){
        $unit=array('b','kb','mb','gb','tb','pb');
        $unitSize = @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
        return $unitSize;
    }

    /*
     * 返回当前运行时间
     */
    public static function click(){
        return microtime(true)-self::getDefine('now');
    }

    /*
     * 返回当前所耗内存
     */
    public static function RAMClick(){
        return memory_get_usage()-self::getDefine('ram');
    }

    /*
     * ajax返回信息
     */
    public static function ajaxReturn($data){
        // 返回JSON数据格式到客户端 包含状态信息
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($data));
    }
}