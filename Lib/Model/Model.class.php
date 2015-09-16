<?php
/**
 * User: Zoa Chou
 * Date: 15/8/4
 */

namespace WAFPHP\Lib\Model;
use WAFPHP\WAFPHP;

class Model{
    private $config = null;// 当前配置信息
    private $Model = null;// 当前使用Model实例
    private static $instance = null;
    private $session = null;// 当前请求客户端session

    /*
     * 初始化Model
     */
    private function __construct($modelType,$modelConfig){
        //根据配置初始化Model
        $modelClass = WAFPHP::MODEL_CLASS.ucfirst(strtolower($modelType));
        if(class_exists($modelClass)){
            // 合并系统配置与默认配置，防止因配置缺失导致系统无法正常运行
            if($modelConfig && is_array($modelConfig)){
                $this->config = array_merge($modelConfig,call_user_func(array($modelClass,'getDefaultConfig')));
            }else{
                $this->config = call_user_func(array($modelClass,'getDefaultConfig'));
            }

            $this->Model = call_user_func(array($modelClass,'getInstance'),$this->config);
        }else{
            throw ModelException('Can\'t load model:'.$modelClass);
        }

    }

    /*
     * 兼容5.3.3以下版本PHP
     */
    private function Model($modelType,$modelConfig){
        $this->__construct($modelType,$modelConfig);
    }

    /*
     * 单例模式防止被clone
     */
    private function __clone(){
        throw ModelException('The model can\'t be cloned');
    }

    /*
     * 使用单例模式调用
     */
    public static function getInstance($modelType,$modelConfig){
        if(!self::$instance instanceof self){
            self::$instance = new self($modelType,$modelConfig);
        }
        return self::$instance;
    }

    /*
     * 系统自有session操作
     */
    public function session($key,$value=''){
        if($value === null){
            // 当value为null时删除session中保存的对应key值
            return $this->Model->delSessionValue($this->session,$key);
        }elseif(!$value){
            // 当value为空时返回session对应key中保存的值
            return $this->Model->getSessionValue($this->session,$key);
        }else{
            // 当value存在时保存session对应key值
            return $this->Model->addSessionValue($this->session,$key,$value);
        }
    }

    /*
     * 获取session ID
     */
    public function getSession($client){
        $session = isset($client['cookie'][WAFPHP::SESSION_NAME]) ? $client['cookie'][WAFPHP::SESSION_NAME] : null;

        if($session && $this->Model->sessionExists($session,$client['ip'])){
            // session心跳
            $this->Model->sessionBreath($session,WAFPHP::SESSION_LIFETIME);
        }else{
            $session = $this->proclaimSession($client['ip']);
            setcookie(WAFPHP::SESSION_NAME,$session,null,'/',null,null,true);
        }

        return $this->session = $session;
    }

    /*
     * 颁布系统自有session
     * Notice:session与IP绑定，IP更换后session失效
     */
    private function proclaimSession($ip){
        // 生成随机session值
        $sessionID = md5(microtime(true).mt_rand());
        //保存session到Model中
        $this->Model->proclaimSession($sessionID,$ip,WAFPHP::SESSION_LIFETIME);

        return $sessionID;
    }

    /*
     * 根据客户端信息返回是否为黑名单用户
     */
    public function isBlack($client,$prefix=''){
        $isBlack = false;
        $isBlack = $this->Model->isBlackIP($client['ip'],$prefix);
        return $isBlack;
    }

    /*
     * 根据客户端信息返回是否为白名单用户
     */
    public function isWhite($client,$prefix=''){
        $isWhite = false;
        $isWhite = $this->Model->isWhiteSession($client['session'],$client['ip'],$prefix);
        return $isWhite;
    }

    /*
     * 增加指定IP到黑名单
     */
    public function addBlackList($ip,$lifeTime,$prefix=''){
        return $this->Model->addBlackIP($ip,$lifeTime,$prefix);
    }

    /*
     * 增加指定session到白名单
     */
    public function addWhiteList($session,$ip,$lifeTime,$prefix=''){
        return $this->Model->addWhiteSession($session,$ip,$lifeTime,$prefix);
    }

    /*
     * 删除指定IP黑名单用户
     */
    public function delBlackList($client,$prefix=''){
        return $this->Model->delBlackIP($client['ip'],$prefix);
    }

    /*
     * 删除指定IP白名单用户
     */
    public function delWhiteList($client,$prefix=''){
        return $this->Model->delWhiteSession($client['session'],$prefix);
    }

    /*
     * 通用获取指定key方法
     */
    public function get($key,$prefix){
        if(!$key || !$prefix){
            return false;
        }
        return $this->Model->get($key,$prefix);
    }

    /*
     * 通用设置指定key方法
     */
    public function set($key,$value,$lifetime,$prefix){
        if(!$key || !$value || !$lifetime || !$prefix){
            return false;
        }
        return $this->Model->set($key,$value,$lifetime,$prefix);
    }

    /*
     * 通用删除指定key方法
     */
    public function del($key,$prefix){
        if(!$key || !$prefix){
            return false;
        }
        return $this->Model->del($key,$prefix);
    }

    /*
     * 获取错误信息
     */
    public function getError(){
        return $this->Model->getError();
    }

    /*
     * 获取当前配置
     */
    public function getCurrentConfig(){
        return $this->config;
    }

    /*
     * 结束时清理资源句柄
     */
    public function quit(){
        return $this->Model->quit();
    }
}

class ModelException extends \Exception{}