<?php
/**
 * User: Zoa Chou
 * Date: 15/7/30
 */
namespace WAFPHP;
use WAFPHP\Common\Common;
use WAFPHP\Lib\Model\ModelException;


// 防护系统前缀，避免与其他原程序定义变量冲突
defined('WAF_PREFIX') or define('WAF_PREFIX','WAF_');
// 当前系统所在文件路径
defined('WAF_ROOT') or define('WAF_ROOT',__DIR__.DIRECTORY_SEPARATOR);
// 当前系统配置文件
defined(WAF_PREFIX.'CONFIG') or define(WAF_PREFIX.'CONFIG','config.default');
// 当前系统开始执行时间
defined(WAF_PREFIX.'NOW') or define(WAF_PREFIX.'NOW',microtime(true));
// 当前系统开始执行内存占用大小
defined(WAF_PREFIX.'RAM') or define(WAF_PREFIX.'RAM',memory_get_usage());


class WAFPHP{
    /* 系统运行所需数据定义 */
    const WAF_VERSION = '0.1';// 版本信息
    const WAF_EXT = '.class.php';// 类文件后缀
    const SESSION_NAME = 'WAFPHP_SESSION_ID';// 系统session ID名称
    const SESSION_LIFETIME = 600;// 系统session的存活时间，存活时间内访问将会刷新存活时间，此时间应长于白名单存活时间
    static public $modelClass = null;// Model 模块命名空间
    static public $wafClass = null;// Waf 模块命名空间
    static public $configPath = null;// 配置文件目录
    static public $runtimePath = null;//  Runtime目录
    static private $instance = null;// 当前系统静态示例
    static protected $init = null;// 资源初始化标识，当资源初始化后系统运行结束将自动释放所占资源
    static protected $quit = null;// 系统退出标识
    public $Model = null;// Model object，供子模块调用
    public $Log = null;// Log object，供子模块调用
    private $config = null;// 系统运行配置
    private $resultMsg = null;// 结果的详细信息
    private $registerShutdown = null;// register_shutdown_function的callback钩子，用于系统退出后及时清除callback钩子

    /* 系统运行所需数据定义 */

    /* 客户端数据定义 */
    protected $client = null;// 客户端请求信息
    /* 客户端数据定义 */

    /*
     * 系统初始化
     */
    private function __construct($config=''){
        //兼容PHP5.3，动态定义环境变量
        self::defineEnv();
        // 确保即使异常退出也能执行quit方法
        $this->registerShutdown = new UnRegisterCallback(array($this, "quit"));
        register_shutdown_function(array($this->registerShutdown, "register"));
        // 自动加载类
        spl_autoload_register(array($this,'autoLoad'));
        // 获取当前客户端请求信息
        $this->client = self::getCurrentClient();
        // 设置初始化标识
        self::$init = true;
        // 装载系统运行配置，并根据配置执行检测
        $this->setup($config);
    }

    /*
     * 兼容PHP5.3，动态定义环境变量
     */
    private static function defineEnv(){
        if(self::$modelClass === null){
            self::$modelClass = __NAMESPACE__.'\\Lib\\Model\\';
            self::$wafClass = __NAMESPACE__.'\\Lib\\Waf\\';
            self::$configPath = WAF_ROOT.'Conf'.DIRECTORY_SEPARATOR;
            self::$runtimePath = WAF_ROOT.'Runtime'.DIRECTORY_SEPARATOR;
        }
    }

    /*
     * 兼容5.3.3以下版本PHP
     */
    private function WAFPHP(){
        $this->__construct();
    }

    /*
     * 单例模式，防止被克隆
     */
    private function __clone(){
        die('WAFPHP can\'t be cloned');
    }

    /*
     * 系统实例化
     */
    public static function getInstance($config=''){
        if(!self::$instance instanceof self){
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /*
     * 自动加载类方法
     */
    private function autoLoad($class){
        $classArr = explode('\\',$class);
        // 如果根命名空间与当前相同则去除根命名空间目录
        if($classArr[0] == __NAMESPACE__){
            unset($classArr[0]);
        }
        // 构造适合当前系统的类库绝对路径路径
        $classStr = implode(DIRECTORY_SEPARATOR,$classArr);

        require_once WAF_ROOT.$classStr.self::WAF_EXT;
    }

    /*
     * 装载系统运行配置
     */
    private function setup($config=''){
        // 生成Runtime目录
        if(!is_dir(self::$runtimePath) && !mkdir(self::$runtimePath,0755)){
            die('Failed to create runtime folders in '.self::$runtimePath);
        }
        // 如果传递config则把该config作为本次系统运行配置
        if(!$config || !is_array($config)){
            $this->config = self::getCurrentConfig();
        }else{
            $this->config = $config;
        }
        // 以系统配置启用日志
        $this->Log = $this->setupLog(Common::getConfig('debug_level',$this->config));
        // 以系统配置启用Model
        $this->Model = $this->setupModel($this->config);
    }

    /*
     * 获取系统配置指定的指定model实例
     */
    private function setupModel($config=''){
        // 根据配置实例化Model对应模块
        $modelStartTime = Common::click();
        $modelStartRAM = Common::RAMClick();
        $modelClass = self::$modelClass.'Model';
        try{
            $Model = call_user_func(
                array($modelClass,'getInstance'),
                Common::getConfig('model_type',$config),
                Common::getConfig('model_config',$config)
            );
        }catch (ModelException $e){
            $this->Log->error(__METHOD__,'Model throw exception '.$e->getMessage());
            throw new WAFPHPException($e->getMessage());
        }
        // 系统开启时获取有效session
        if(Common::getConfig('waf_on',$this->config)){
            $this->client['session'] = $Model->getSession($this->client);
        }
        $modelEndTime = Common::click();
        $modelEndRAM = Common::RAMClick();
        // 开始记录系统运行信息
        $this->Log->debug(__METHOD__,
            'WAFPHP start at '.date('Y-m-d H:i:s',Common::getDefine('now')).
            ',session '.$this->client['session']
        );
        $this->Log->debug(__METHOD__,sprintf('Model loading use time %fs,RAM %s',($modelEndTime-$modelStartTime),Common::convertUnit($modelEndRAM-$modelStartRAM)));
        return $Model;
    }

    private static function setupLog($debugLevel){
        $Log = Lib\Log\Log::getInstance($debugLevel);
        return $Log;
    }

    /*
     * 开始执行检测
     */
    public function runCheck(){
        $checkResult = $this->run();
        $this->quit($checkResult);
        return $checkResult;
    }

    /*
     * 获取检测结果详细信息
     */
    public function getResultMsg(){
        return $this->resultMsg;
    }

    /*
     * 检测当前客户端请求是否合法
     */
    private function run(){
        // 关闭脚本检测则直接返回
        if(!Common::getConfig('waf_on',$this->config)){
            return true;
        }

        // 黑名单检测，中靶直接返回结果
        if($this->blackCheck()){
            return false;
        }

        // 白名单检测，中靶直接返回结果
        if($this->whiteCheck()){
            return true;
        }

        // 脚本检测
        $wafStartTime = Common::click();
        $wafStartRAM = Common::RAMClick();
        $wafClass = self::$wafClass.'Waf';
        $Waf = new $wafClass();
        // 添加开启的检测脚本进待执行队列
        foreach(Common::getConfig('script_list',$this->config) as $key => $value) {
            // 排除未开启的检测脚本
            if(!$value){
                continue;
            }
            $scriptName = self::$wafClass.ucfirst(strtolower($key));
            $scriptConfig = Common::getConfig('WAF_'.$key.'_CONFIG',$this->config);
            $Waf->addScript($scriptName,$scriptConfig);
        }
        // 执行脚本
        $scriptResult = $Waf->run($this->client);
        if(!$scriptResult){
            $this->resultMsg = $Waf->getError();
        }
        $wafEndTime = Common::click();
        $wafEndRAM = Common::RAMClick();
        $this->Log->debug(__METHOD__,sprintf('Script detection running use time %fs,RAM %s',($wafEndTime-$wafStartTime),Common::convertUnit(($wafEndRAM-$wafStartRAM))));
        return $scriptResult;
    }

    /*
     * 获取当前系统配置文件配置
     */
    public static function getCurrentConfig(){
        //避免未初始化前无法获取配置
        self::defineEnv();
        return require_once self::$configPath.constant(WAF_PREFIX.'CONFIG').'.php';
    }

    /*
     * 获取当前请求客户端信息
     */
    private static function getCurrentClient(){
        // 获取客户端请求信息
        $client['ip'] = Common::getClientIp();// 客户端IP
        $client['get'] = $_GET;// 客户端GET请求数据
        $client['post'] = $_POST;// 客户端POST数据
        $client['cookie'] = $_COOKIE;// 客户端COOKIE数据
        $client['server'] = $_SERVER["HTTP_HOST"];// 所请求host
        $client['ua'] = $_SERVER['HTTP_USER_AGENT'];// 客户端UA
        $client['uriHash'] = md5($client['server'].$_SERVER['SCRIPT_NAME']);// 请求uri的MD5校验
        return $client;
    }

    /*
     * 检查当前客户端是否为黑名单
     */
    public function blackCheck($client = ''){
        $client = $client ? $client : $this->client;

        // 先检查全局黑名单设置
        $blackIP = Common::getConfig('black_ip',$this->config);
        $blackUA = Common::getConfig('black_ua',$this->config);

        // IP黑名单
        if(Common::checkIP($client['ip'],$blackIP)){
            $this->resultMsg = 'Black IP list';
            $this->Log->info(__METHOD__,'The client ip['.$client['ip'].'] was in black IP list');
            return true;
        }
        // UA黑名单
        if(Common::checkUA($client['ua'],$blackUA,
            Common::getConfig('black_ua_ignore_case',$this->config)
        )){
            $this->resultMsg = 'Black UA list';
            $this->Log->info(__METHOD__,'The client ua['.$client['ua'].'] was in black ua list');
            return true;
        }

        // 实时黑名单
        if($this->Model->isBlack($client)){
            $this->resultMsg = 'Client in temporary black list';
            $this->Log->info(__METHOD__,'The client ip['.$client['ip'].'] was in temporary black list');
            return true;
        }else{
            return false;
        }

    }

    /*
     * 检查当前客户端是否为白名单
     */
    public function whiteCheck($client = ''){
        $client = $client ? $client : $this->client;

        // 先检查全局白名单设置
        $whiteIP = Common::getConfig('white_ip',$this->config);
        $whiteUA = Common::getConfig('white_ua',$this->config);
        // IP白名单
        if(Common::checkIP($client['ip'],$whiteIP)){
            $this->resultMsg = 'White IP list';
            $this->Log->info(__METHOD__,'The client ip['.$client['ip'].'] was in white IP list');
            return true;
        }
        // UA白名单
        if(Common::checkUA($client['ua'],$whiteUA,
            Common::getConfig('white_ua_ignore_case',$this->config),
            Common::getConfig('white_ua_dns_reverse',$this->config),
            $client['ip']
        )){
            $this->resultMsg = 'White UA list';
            $this->Log->info(__METHOD__,'The client ua['.$client['ua'].'] was in white ua list');
            return true;
        }

        // 实时白名单
        if($this->Model->isWhite($client)){
            $this->resultMsg = 'Client in temporary white list';
            $this->Log->info(__METHOD__,'The client session['.$client['session'].'] was in temporary white list');
            return true;
        }else{
            return false;
        }
    }

    /*
     * 添加黑名单
     */
    public function addBlackList($ip=''){
        $ip = $ip ? $ip : $this->client['ip'];
        return $this->Model->addBlackList($ip,Common::getConfig('black_list_lifetime',$this->config));
    }

    /*
     * 添加白名单
     */
    public function addWhiteList($session='',$ip=''){
        $ip = $ip ? $ip : $this->client['ip'];
        $session = $session ? $session : $this->client['session'];
        return $this->Model->addWhiteList($session,$ip,Common::getConfig('white_list_lifetime',$this->config));
    }

    /*
     * 清理系统所占资源，退出系统
     */
    public function quit($checkResult=false){
        $this->Log->debug(__METHOD__,sprintf('system running use time %fs,RAM %s',Common::click(),Common::convertUnit(Common::RAMClick())));
        $resultMsg = $this->resultMsg;
        if(self::$init !== null && self::$quit === null){
            self::$quit = true;
            spl_autoload_unregister(array($this,'autoLoad'));
            // 开启debug时将系统debug信息写入log
            if(Common::getConfig('debug_level',$this->config)){
                $this->Log->writeLog();
            }

            // 清理Model所占资源
            if(is_object($this->Model)){
                $this->Model->quit();
            }

            // 注销register_shutdown_function，避免影响其他程序
            $this->registerShutdown->unRegister();
        }

    }

}


class WAFPHPException extends \Exception{}


/*
 * 注销callback类型的系统调用类，规避PHP无法注销callback问题
 */
class UnRegisterCallback{
    private $callback;// 储存的callback

    /*
     * 储存callback
     */
    public function __construct($callback){
        if(is_callable($callback)) {
            $this->callback = $callback;
        }
        else {
            throw new WAFPHPException("Not a Callback");
        }
    }

    /*
     * callback钩子，当钩子被注销后callback失效
     */
    public function register(){
        if($this->callback == false){
            return false;
        }

        // 调用callback
        $callback = $this->callback;
        call_user_func($callback);
        return true;
    }

    /*
     * 注销callback钩子
     */
    public function unRegister(){
        $this->callback = false;
        return true;
    }
}