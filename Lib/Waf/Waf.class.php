<?php
/**
 * User: Zoa Chou
 * Date: 15/8/24
 */

namespace WAFPHP\Lib\Waf;
use WAFPHP\Common\Common;


class Waf{
    private $scripts = null;// 当前需执行检测脚本实例及配置
    protected $error = null;// 错误信息

    /*
     * 命令链模式执行检测脚本
     */
    public function run($client){
        unset($script);
        foreach($this->scripts as $key => $script){
            $runner = new $script['script']();
            $result = $runner->run($script['config'],$client);
            Common::Log()->debug(__METHOD__,'Running script detection '.$script['script'].' with result '.$result);
            if(!$result){
                $this->error = $runner->getError();
                Common::Log()->info(__METHOD__,'Running script detection '.$script['script'].' with error '.$this->error);
                return false;
            }
        }

        return true;
    }

    /*
     * 装载检测脚本及配置
     */
    public function addScript($script,$config){
        // 合并系统配置与默认配置
        if($config && is_array($config)){
            $config = array_merge(call_user_func(array($script,'getDefaultConfig')),$config);
        }else{
            $config = call_user_func(array($script,'getDefaultConfig'));
        }
        $tmpScript['script'] = $script;
        $tmpScript['config'] = $config;
        $this->scripts[] = $tmpScript;
    }

    public function getError(){
        return $this->error;
    }
}