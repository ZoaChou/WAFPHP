<?php
/**
 * User: Zoa Chou
 * Date: 15/7/30
 */

namespace WAFPHP\Lib\Log;
use WAFPHP\WAFPHP;



class Log{
    const DEBUG_LEVEL = array(
        'CLOSE' => 0,
        'DEBUG' => 1,
        'INFO' => 2,
        'WARN' => 3,
        'ERROR' => 4,
    );
    const LOG_TEMPLATE = '[%s %s/%s]: %s';
    private static $instance = null;
    private $logPath = null;
    private $debugLevel = null;
    private $logMsg = null;

    private function __construct($debugLevel=''){
        $constLevel = self::DEBUG_LEVEL;
        if(isset($constLevel[$debugLevel])){
            $this->debugLevel = $constLevel[$debugLevel];
        }else{
            $this->debugLevel = 0;
        }
        $this->logPath = WAFPHP::$runtimePath.'Logs'.DIRECTORY_SEPARATOR;
        if(!is_dir($this->logPath) && !mkdir($this->logPath,0755)){
            die('Failed to create logs folders in '.$this->logPath);
        }
    }

    private function __clone(){
        die('Log can\'t be cloned');
    }

    public static function getInstance($debugLevel=''){
        if(!self::$instance instanceof self){
            self::$instance = new self($debugLevel);
        }
        return self::$instance;
    }

    public function debug($method,$msg){
        $this->storeLog('DEBUG',$method,$msg);
    }

    public function info($method,$msg){
        $this->storeLog('INFO',$method,$msg);
    }

    public function warn($method,$msg){
        $this->storeLog('WARN',$method,$msg);
    }

    public function error($method,$msg){
        $this->storeLog('ERROR',$method,$msg);
    }

    private function storeLog($level,$method,$msg){
        $constLevel = self::DEBUG_LEVEL;

        // 等级低于当前配置的debug等级的信息将不保存
        if($constLevel[$level] >= $this->debugLevel){
            $this->logMsg[] = sprintf(self::LOG_TEMPLATE,date('Y-m-d H:i:s'),$level,$method,$msg.PHP_EOL);
        }
        return true;
    }

    public function writeLog(){
        $logName = date('Y_m_d').'.log';
        file_put_contents($this->logPath.$logName,$this->logMsg,FILE_APPEND|LOCK_EX);
        return true;
    }
}