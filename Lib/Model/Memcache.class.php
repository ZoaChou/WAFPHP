<?php
/**
 * User: Zoa Chou
 * Date: 15/8/4
 */

namespace WAFPHP\Lib\Model;


class Memcache implements ModelInterface{
    const MEMCACHE_PREFIX = 'WAFPHP-';
    const WHITE_LIST_PREFIX = 'white:';
    const BLACK_LIST_PREFIX = 'black:';
    const SESSION_LIST_PREFIX = 'session:';
    protected $config = null;
    private $Memcache = null;
    protected $error = null;
    private static $instance = null;

    private function __construct($config){
        $this->Memcache = new \Memcache;
        $this->connect($config);
    }

    private function Memcache($config){
        $this->__construct($config);
    }

    private function __clone(){
        throw ModelException('The Memcache Model can\'t be cloned');
    }

    /*
     * 连接memcache
     */
    private function connect($config){
        $this->config = $config;

        // 长连接模式
        if($this->config['pconnect']){
            $this->Memcache->pconnect(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout']
            );
        }else{
            // 短连接模式
            $this->Memcache->connect(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout']
            );
        }
    }

    /*
     * Model初始化
     */
    public static function getInstance($config){
        if(!self::$instance instanceof self){
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /*
     * Model默认配置，将会与用户配置覆盖合并，以用户配置为主
     */
    public static function getDefaultConfig(){
        return array(
            'host' => '127.0.0.1',
            'port' => '11211',
            'timeout' => '5',
            'pconnect' => true,
            'lifetime' => 3*60,
        );
    }

    /*
     * 检查session是否有效
     */
    public function sessionExists($session,$clientIP){
        // 先检测session是否存在
        $session = $this->_get(self::SESSION_LIST_PREFIX.$session);
        if(!$session){
            return false;
        }

        // 获取session对应IP检查该session是否有效
        $ip = $session['ip'];

        if($ip == $clientIP){
            return true;
        }else{
            $this->error = 'Client IP change';
            return false;
        }
    }

    /*
     * session心跳
     */
    public function sessionBreath($session,$lifetime){
        $value = $this->_get(self::SESSION_LIST_PREFIX.$session);
        // 刷新session有效期便于后期修改session值时记录存活时间
        $lifetime += time();
        $value['__lifetime'] = $lifetime;
        if($this->_replace(self::SESSION_LIST_PREFIX.$session, $value, $lifetime)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 颁布session
     */
    public function proclaimSession($session,$ip,$lifetime){
        $value['ip'] = $ip;
        // 记录session有效期便于后期修改session值时记录存活时间
        $lifetime += time();
        $value['__lifetime'] = $lifetime;
        if($this->_set(self::SESSION_LIST_PREFIX.$session,$value,$lifetime)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 添加自有session
     */
    public function addSessionValue($session,$key,$value){
        // 先检测session是否存在
        $sessionValue = $this->_get(self::SESSION_LIST_PREFIX.$session);
        if(!$session){
            return false;
        }

        $sessionValue[$key] = $value;
        $lifetime = $sessionValue['__lifetime'];

        if($this->_replace(self::SESSION_LIST_PREFIX.$session, $sessionValue, $lifetime)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 删除自有session
     */
    public function delSessionValue($session,$key){
        // 先检测session是否存在
        $sessionValue = $this->_get(self::SESSION_LIST_PREFIX.$session);
        if(!$session){
            return false;
        }

        // 如果存在则删除该值
        if(isset($sessionValue[$key])){
            unset($sessionValue[$key]);
        }

        $lifetime = $sessionValue['__lifetime'];

        if($this->_replace(self::SESSION_LIST_PREFIX.$session, $sessionValue, $lifetime)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 获取自有session储存的值
     */
    public function getSessionValue($session,$key){
        // 先检测session是否存在
        $sessionValue = $this->_get(self::SESSION_LIST_PREFIX.$session);
        if(!$session){
            return false;
        }
        // 如果存在则删除该值
        if(isset($sessionValue[$key])){
            return $sessionValue[$key];
        }else{
            return false;
        }
    }

    /*
     * 根据客户端信息返回是否为黑名单用户
     * Notice:prefix为空时使用全局黑名单列表
     */
    public function isBlackIP($ip,$prefix=''){
        !$prefix && $prefix = self::BLACK_LIST_PREFIX;
        if($this->_get($prefix.$ip)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 根据客户端信息返回是否为白名单用户
     * Notice:prefix为空时使用全局白名单列表
     */
    public function isWhiteSession($session,$clientIP,$prefix=''){
        !$prefix && $prefix = self::WHITE_LIST_PREFIX;
        $ip = $this->_get($prefix.$session);

        if(!$ip){
            return false;
        }

        if($ip == $clientIP){
            return true;
        }else{
            $this->error = 'client IP change';
            return false;
        }
    }

    /*
     * 增加指定IP到黑名单
     * Notice:prefix为空时添加到全局黑名单列表
     */
    public function addBlackIP($ip,$lifeTime,$prefix=''){
        !$prefix && $prefix = self::BLACK_LIST_PREFIX;
        if($this->_set($prefix.$ip,true,$lifeTime)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 增加指定session到白名单
     * Notice:prefix为空时添加到全局白名单列表
     */
    public function addWhiteSession($session,$ip,$lifeTime,$prefix=''){
        !$prefix && $prefix = self::WHITE_LIST_PREFIX;
        if($this->_set($prefix.$session,$ip,$lifeTime)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 删除指定IP黑名单用户
     * Notice:prefix为空时删除全局黑名单列表中指定IP
     */
    public function delBlackIP($ip,$prefix=''){
        !$prefix && $prefix = self::BLACK_LIST_PREFIX;
        if($this->_delete($prefix.$ip)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 删除指定session白名单用户
     * Notice:prefix为空时删除白黑名单列表中指定session
     */
    public function delWhiteSession($session,$prefix=''){
        !$prefix && $prefix = self::BLACK_LIST_PREFIX;
        if($this->_delete($prefix.$session)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 通用获取指定key方法
     */
    public function get($key,$prefix){
        return $this->_get($prefix.$key);
    }

    /*
     * 通用设置指定key方法
     */
    public function set($key,$value,$lifetime,$prefix){
        return $this->_set($prefix.$key,$value,$lifetime);
    }

    /*
     * 通用删除指定key方法
     */
    public function del($key,$prefix){
        return $this->_delete($prefix.$key);
    }

    private function _get($key){
        if(!$key){
            $this->error = 'Memcache get error:key must need';
            return false;
        }
        $value = $this->Memcache->get(self::MEMCACHE_PREFIX.$key);
        if($value === false){
            $this->error = 'Memcache get error:key not exist';
            return false;
        }
        $value = is_null(json_decode($value)) ? $value :json_decode($value,true);
        return $value;
    }

    private function _set($key,$var,$expire){
        if(!$key){
            $this->error = 'Memcache set error:key must need';
            return false;
        }
        if(is_array($var)){
            $var = json_encode($var);
        }
        $res = $this->Memcache->set(self::MEMCACHE_PREFIX.$key,$var,MEMCACHE_COMPRESSED,$expire);

        if($res){
            return true;
        }else{
            $this->error = 'Memcache set error.';
            return false;
        }
    }

    private function _replace($key,$var,$expire){
        if(!$key){
            $this->error = 'Memcache set error:key must need';
            return false;
        }
        if(is_array($var)){
            $var = json_encode($var);
        }

        $res = $this->Memcache->replace(self::MEMCACHE_PREFIX.$key,$var,MEMCACHE_COMPRESSED,$expire);
        if($res){
            return true;
        }else{
            $this->error = 'Memcache replace error: key not exist.';
            return false;
        }
    }

    private function _delete($key){
        if(!$key){
            $this->error = 'Memcache set error:key must need';
            return false;
        }

        if($this->Memcache->delete(self::MEMCACHE_PREFIX.$key)){
            return true;
        }else{
            $this->error = 'Memcache delete error:key not exist';
            return false;
        }
    }

    /*
     * 获取最后的错误信息
     */
    public function getError(){
        return $this->error;
    }

    /*
     * 结束时清理资源句柄
     */
    public function quit(){
        if($this->Memcache && $this->Memcache->close()){
            return true;
        }else{
            $this->error = 'Memcache close error';
            return false;
        }
    }
}