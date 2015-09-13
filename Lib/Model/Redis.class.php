<?php
/**
 * User: Zoa Chou
 * Date: 15/8/4
 */

namespace WAFPHP\Lib\Model;


class Redis implements ModelInterface{
    const REDIS_PREFIX = 'WAFPHP-';
    const WHITE_LIST_PREFIX = 'white:';
    const BLACK_LIST_PREFIX = 'black:';
    const SESSION_LIST_PREFIX = 'session:';
    protected $config = null;
    private $Redis = null;
    protected $error = null;
    private static $instance = null;

    /*
     * Redis默认配置，将会与用户配置覆盖合并，以用户配置为主
     */
    public static function getDefaultConfig(){
        return array(
            'host' => '127.0.0.1',
            'port' => '6379',
            'timeout' => '5',
            'password' => '',
            'pconnect' => true,
            'lifetime' => 3*60,
        );
    }

    /*
     * Redis实例化
     */
    private function __construct($config){
        $this->Redis = new \Redis;
        $this->connect($config);
    }

    /*
     * 兼容5.3.3以下版本PHP
     */
    private function Redis($config){
        $this->__construct($config);
    }

    /*
     * 单例模式防止被clone
     */
    private function __clone(){
        throw ModelException('The Redis Model can\'t be cloned');
    }

    /*
     * 使用单例模式调用
     */
    public static function getInstance($config){
        if(!self::$instance instanceof self){
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /*
     * 连接Redis
     */
    private function connect($config){
        $this->config = $config;

        // 长连接模式
        if($this->config['pconnect']){
            $this->Redis->pconnect(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout']
            );
        }else{
            // 短连接模式
            $this->Redis->connect(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout']
            );
        }

        // 如果配置中包含密码则尝试密码授权
        if($this->config['password']){
            if(!$this->Redis->auth($this->config['password'])){
                throw new ModelException('Redis auth failure');
            }
        }
        // 设置key的前缀，避免与其他程序key冲突
        if(!$this->Redis->setOption(\Redis::OPT_PREFIX, self::REDIS_PREFIX)){
            throw new ModelException('Redis prefix failure');
        }

        // 连接Redis测试
        try{
            $this->Redis->ping();
        }catch (\RedisException $e){
            throw new ModelException($e->getMessage());
        }

    }

    /*
     * 检查session是否有效
     */
    public function sessionExists($session,$clientIP){
        // 先检测session是否存在
        if(!$this->_exists(self::SESSION_LIST_PREFIX.$session)){
            return false;
        }

        // 获取session对应IP检查该session是否有效
        $ip = $this->_hGet(self::SESSION_LIST_PREFIX.$session,'ip');
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
        if($this->_expire(self::SESSION_LIST_PREFIX.$session,$lifetime)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 颁布session
     */
    public function proclaimSession($session,$ip,$lifetime){
        if($this->_hMSet(self::SESSION_LIST_PREFIX.$session,array('ip'=>$ip),$lifetime)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 根据客户端信息返回是否为黑名单用户
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
     * 获取IP黑名单列表，无分页，直接返回全部
     */
    public function getBlackIPList($perPage,$page,$prefix=''){
        !$prefix && $prefix = self::BLACK_LIST_PREFIX;
        $allBlackIP = $this->_keys($prefix);

        if($allBlackIP){
            return $this->removePrefix($allBlackIP,self::REDIS_PREFIX.$prefix);
        }else{
            return false;
        }
    }

    /*
     * 获取session白名单列表，无分页，直接返回全部
     */
    public function getWhiteSessionList($perPage,$page,$prefix=''){
        !$prefix && $prefix = self::WHITE_LIST_PREFIX;
        $allWhiteSession = $this->_keys($prefix);

        if($allWhiteSession){
            return $this->removePrefix($allWhiteSession,self::REDIS_PREFIX.$prefix);
        }else{
            return false;
        }
    }

    /*
     * 获取session中储存的指定key值
     */
    public function getSessionValue($session,$key){
        $prefix = self::SESSION_LIST_PREFIX;
        return $this->_hGet($prefix.$session,$key);
    }

    /*
     * 向session储存的指定key值
     */
    public function addSessionValue($session,$key,$value){
        $prefix = self::SESSION_LIST_PREFIX;
        return $this->_hSet($prefix.$session,$key,$value);
    }

    /*
     * 删除session中存储的指定key值
     */
    public function delSessionValue($session,$key){
        $prefix = self::SESSION_LIST_PREFIX;
        return $this->_hDel($prefix.$session,$key);
    }

    /*
     * 增加指定IP到黑名单
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
     */
    public function delBlackIP($ip,$prefix=''){
        !$prefix && $prefix = self::BLACK_LIST_PREFIX;
        if($this->_del($prefix.$ip)){
            return true;
        }else{
            return false;
        }

    }

    /*
     * 删除指定IP白名单用户
     */
    public function delWhiteSession($session,$prefix=''){
        !$prefix && $prefix = self::BLACK_LIST_PREFIX;
        if($this->_del($prefix.$session)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 清空黑名单
     */
    public function clearBlackIP($prefix=''){
        !$prefix && $prefix = self::BLACK_LIST_PREFIX;
        $allBlackKeys = $this->getBlackIPList(0,0,$prefix);
        // 黑名单为空时直接返回
        if(!$allBlackKeys){
            return true;
        }

        if($this->_del($allBlackKeys)){
            return true;
        }else{
            return false;
        }

    }

    /*
     * 清空白名单
     */
    public function clearWhiteSession($prefix=''){
        !$prefix && $prefix = self::BLACK_LIST_PREFIX;
        $allWhiteKeys = $this->getWhiteSessionList(0,0,$prefix);
        // 白名单为空时直接返回
        if(!$allWhiteKeys){
            return true;
        }

        if($this->_del($allWhiteKeys)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 通用获取指定key方法
     */
    public function get($key,$prefix){
        if(!$this->_exists($prefix.$key)){
            return false;
        }
        $value = $this->_get($prefix.$key);
        $value = is_null(json_decode($value)) ? $value :json_decode($value,true);
        return $value;
    }

    /*
     * 通用设置指定key方法
     */
    public function set($key,$value,$lifetime,$prefix){
        $value = is_array($value) ? json_encode($value) : $value;
        if($this->_set($prefix.$key,$value,$lifetime)){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 通用删除指定key方法
     */
    public function del($key,$prefix){
        if($this->_del($prefix.$key)){
            return true;
        }else{
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
        if($this->Redis && $this->Redis->close()){
            return true;
        }else{
            $this->error = 'Redis close error';
            return false;
        }

    }

    /*
     * 获取value
     */
    private function _get($key){
        if(!$key){
            $this->error = 'Redis get error:key must need.';
            return false;
        }

        $value = $this->Redis->get($key);
        if($value){
            return $value;
        }else{
            $this->error = $this->Redis->getLastError();
            return false;
        }
    }

    /*
     * hash 获取对应key的指定hashKey值
     */
    private function _hGet($key,$hashKey){
        if(!$key){
            $this->error = 'Redis hGet error:key must need.';
            return false;
        }
        if(!$hashKey){
            $this->error = 'Redis hGet error:hashKey must need.';
            return false;
        }

        $value = $this->Redis->hGet($key,$hashKey);
        if($value){
            return $value;
        }else{
            $this->error = $this->Redis->getLastError();
            return false;
        }
    }

    /*
     * hash 获取对应key的所有值
     */
    private function _hGetAll($key){
        if(!$key){
            $this->error = 'Redis hGetAll error:key must need.';
            return false;
        }

        $value = $this->Redis->hGetAll($key);
        if($value){
            return $value;
        }else{
            $this->error = $this->Redis->getLastError();
            return false;
        }
    }

    /*
     * 存储key=>value
     */
    private function _set($key,$value,$lifetime = null){
        if(!$key){
            $this->error = 'Redis set error:key must need';
            return false;
        }

        if($lifetime !==null && !$lifetime){
            $lifetime = $this->config['lifetime'];
        }

        if($this->Redis->set($key,$value,$lifetime)){
            return true;
        }else{
            $this->error = $this->Redis->getLastError();
            return false;
        }
    }

    /*
     * hash 针对指定hashKey的set操作
     */
    private function _hSet($key,$hashKey,$value){
        if(!$key){
            $this->error = 'Redis hSet error:key must need';
            return false;
        }
        if(!$hashKey){
            $this->error = 'Redis hSet error:hashKey must need';
            return false;
        }

        if($this->Redis->hSet($key,$hashKey,$value)){
            return true;
        }else{
            $this->error = $this->Redis->getLastError();
            return false;
        }
    }

    /*
     * hash 针对key的set操作
     */
    private function _hMSet($key,$value,$lifetime = null){
        $setRes = $this->Redis->hMset($key,$value);
        if($setRes){
            $this->_expire($key,$lifetime);
        }else{
            $this->error = $this->Redis->getLastError();
            return false;
        }

        return true;
    }

    /*
     * 更新key的存活时间
     */
    private function _expire($key,$lifetime){
        if(!$key){
            $this->error = 'Redis expire error:key must need';
            return false;
        }

        if(!$lifetime){
            $lifetime = $this->config['lifetime'];
        }

        $res = $this->Redis->expire($key,$lifetime);

        if($res > 0){
            return true;
        }else {
            $this->error = $this->Redis->getLastError();
            return false;
        }
    }

    /*
     * 删除key
     */
    private function _del($keys){
        if(!$keys){
            $this->error = 'Redis delete error:key must need';
            return false;
        }

        // 单独删除
        if(!is_array($keys)){
            if($this->Redis->del($keys)){
                return true;
            }else{
                $this->error = $this->Redis->getLastError();
                return false;
            }

        }else{
            // 批量删除
            if(call_user_func_array(array($this->Redis,'del'),$keys)){
                return true;
            }else{
                $this->error = $this->Redis->getLastError();
                return false;
            }

        }
    }

    /*
     * hash 删除指定key的对应hashKey值
     */
    private function _hDel($key,$hashKey){
        if(!$key){
            $this->error = 'Redis hDel error:key must need';
            return false;
        }
        if(!$hashKey){
            $this->error = 'Redis hDel error:hashKey must need';
            return false;
        }

        if($this->Redis->hDel($key,$hashKey)){
            return true;
        }else{
            $this->error = $this->Redis->getLastError();
            return false;
        }
    }

    /*
     * 获取当前系统所有key
     */
    private function _keys($name){
        $allKeys = $this->Redis->keys($name.'*');
        if($allKeys){
            return $allKeys;
        }else{
            $this->error = 'Redis keys empty';
            return false;
        }

    }

    /*
     * 检测key是否存在
     */
    private function _exists($key){
        return $this->Redis->exists($key);
    }

    /*
     * 移除列表中的前缀
     */
    private function removePrefix($data,$prefix = self::REDIS_PREFIX){
        if(!$data){
            return false;
        }

        if(!is_array($data)){
            if(strpos($data,$prefix) === 0){
                $data = substr($data,strlen($prefix));
            }
        }else{
            unset($key);
            unset($value);
            foreach($data as $key=>$value){
                if(strpos($value,$prefix) === 0){
                    $data[$key] = substr($value,strlen($prefix));
                }
            }
        }

        return $data;
    }
}
