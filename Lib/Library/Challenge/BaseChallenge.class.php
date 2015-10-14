<?php
/**
 * User: Zoa Chou
 * Date: 15/8/25
 */

namespace WAFPHP\Lib\Library\Challenge;
use WAFPHP\Common\Common;


// 机器人挑战模式
class BaseChallenge{
    const ROBOT_CHALLENGE_FLAG = 'robot_challenge_verify';
    const ROBOT_CHALLENGE_VERIFY_TIMES = 'robot_challenge_verify_times';
    const ROBOT_CHALLENGE_ERROR_TIMES = 3;// 验证码允许验证次数，超过后验证码失效
    const ROBOT_CHALLENGE_VERIFY_LENGTH = 4;// 验证码长度
    const ROBOT_CHALLENGE_DEFAULT_CODE_TEXT = '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY';// 默认验证码待选字符
    protected $client = null;// 当前客户端请求信息
    protected $error = null;// 错误信息

    public final function __construct($client){
        $this->client = $client;
        $this->init();
    }

    public function BaseChallenge($client){
        $this->__construct($client);
    }

    protected function init(){}

    /*
     * 生成验证码
     */
    protected function makeCode($codeLength=self::ROBOT_CHALLENGE_VERIFY_LENGTH){
        $str = self::ROBOT_CHALLENGE_DEFAULT_CODE_TEXT;
        $code = '';
        for($i = 0; $i < $codeLength; $i++){
            $code .= substr($str,mt_rand(1,strlen($str))-1,1);
        }
        return $code;
    }

    /*
     * 检测验证码是否有效
     */
    protected function checkVerify($code){
        // 验证码不区分大小写
        $code = strtolower($code);
        $checkCode = Common::session(self::ROBOT_CHALLENGE_FLAG);

        // 验证码不存在，直接返回
        if(!$checkCode){
            $this->error = 'Verify not exists';
            return false;
        }

        if($checkCode !== $code){
            // 验证失败
            $checkTimes = intval(Common::session(self::ROBOT_CHALLENGE_VERIFY_TIMES));

            // 验证码超过指定次数后失效
            if($checkTimes > self::ROBOT_CHALLENGE_ERROR_TIMES){
                Common::session(self::ROBOT_CHALLENGE_FLAG,null);
            }else{
                $checkTimes++;
                Common::session(self::ROBOT_CHALLENGE_VERIFY_TIMES,$checkTimes);
            }

            $this->error = 'Check verify failure';
            return false;
        }else{
            // 验证成功，重置验证次数统计，删除验证码
            Common::session(self::ROBOT_CHALLENGE_VERIFY_TIMES,null);
            Common::session(self::ROBOT_CHALLENGE_FLAG,null);
            return true;
        }
    }

    /*
     * 保存验证码至session
     */
    private function setVerify($code){
        // 验证码不区分大小写
        $code = strtolower($code);
        // 重置验证码验证失败次数
        Common::session(self::ROBOT_CHALLENGE_VERIFY_TIMES,0);
        if(Common::session(self::ROBOT_CHALLENGE_FLAG,$code)){
            return true;
        }else{
            $this->error = 'Set verify into session failure';
            return false;
        }
    }

    /*
     * 生成并保存验证码
     */
    public function makeVerifyCode($length=self::ROBOT_CHALLENGE_VERIFY_LENGTH){
        // 生成验证码
        $verifyCode = $this->makeCode($length);
        // 保存验证码
        $this->setVerify($verifyCode);
        return $verifyCode;
    }

    /*
     * 获取错误信息
     */
    public function getError(){
        return $this->error;
    }

    public function startChallenge($verifyCode=''){
        die('Undefined challenge.');
    }
}