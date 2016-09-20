<?php
/**
 * User: Zoa Chou
 * Date: 15/7/30
 */

namespace WAFPHP\Lib\Waf;
use WAFPHP\WAFPHP;
use WAFPHP\Common\Common;
use WAFPHP\Lib\Library\Challenge;


class Robot implements WafInterface{
    const IP_FAILURE_PREFIX = 'robot_ip_failure_list:';// 同IP挑战失败次数统计列表标识，IP进入黑白名单后列表将重置
    const IP_VISIT_TIMES_FLAG = 'robot_ip_visit_times:';// 同IP有效时间内访问次数统计列表标识，IP进入黑白名单后列表将重置
    const SESSION_WHITE_FLAG = 'robot_white';// 模块自有白名单标识
    const SESSION_POST_BACKUP_FLAG = 'robot_post_backup';// 模块备份post数据标识
    protected $client = null;
    protected $config = null;
    protected $result = null;
    protected $error = null;
    private $wafPHP = null;

    /*
     * 默认配置
     */
    public static function getDefaultConfig(){
        $config = array(
            'IP_START_CHALLENGE_TIMES' => 0,// 同IP段开启挑战访问次数，在同IP段访问统计有效时间内访问次数超过设定值后开启挑战，为0时首次访问直接开启，该IP客户端段进入黑白名单后统计重置
            'IP_START_CHALLENGE_LIFETIME' => 3,// 同IP段访问统计有效时间
            'CHALLENGE_MODEL' => 'code-cn',// 可选挑战模式：js（返回一段js挑战）、code（返回验证码挑战）、code-cn（返回中文验证码挑战）
            'WHITE_LIST_TIMEOUT' => 120,// 白名单失效时间，受全局session有效时间影响，session失效后白名单效用随之失效
            'IP_FAILURE_LIFETIME' => 120,// 同IP挑战失败次数统计保留时间，超过时间后失败次数统计将重置，每次挑战失败重新计时
            'IP_FAILURE_LIMIT' => 1000,// 同IP挑战失败上限次数，超过次数后将进入全局黑名单，若该IP有客户端成功则重置失败次数
            'CHALLENGE_AJAX' => true,// 是否关闭ajax类型请求的挑战
            'AJAX_FLAG' => '',// 用于标识当前请求为ajax请求，客户端GET、POST请求携带此变量时将被作为ajax请求处理（JQUERY类库的请求可自动识别），为空时不启用私有ajax标识
            'VERIFY_CODE_LENGTH' => 4,// 验证码类型挑战的验证码长度
        );

        return $config;
    }

    /*
     * 获取错误信息
     */
    public function getError(){
        return $this->error;
    }

    /*
     * 运行脚本检测
     */
    public function run($config,$client){
        $startTime = Common::click();// 脚本开始执行时间
        $startRAM = Common::RAMclick();// 脚本结束执行时间
        $this->config = $config;
        $this->client = $client;
        $this->wafPHP = WAFPHP::getInstance();

        // 检测是否存在有效的白名单标识
        if($this->checkWhiteFlag()){
            Common::Log()->info(__METHOD__,sprintf('Session[%s] was in white list',$this->client['session']));
            return true;
        }

        // 若关闭ajax类型请求的挑战则当请求为ajax类型时直接返回
        if(Common::getConfig('challenge_ajax',$this->config) && Common::isAjax(Common::getConfig('ajax_flag',$this->config))){
            // 避免验证码模式的Ajax请求被绕过
            if(!isset($this->client['post'][Challenge\VerifyCodeChallenge::CODE_CHALLENGE_FLAG])){
                Common::Log()->info(__METHOD__,sprintf('The client was ajax request'));
                return true;
            }
        }

        // 当前IP达到指定开启挑战访问次数前直接返回
        $visitTimes = intval(Common::M()->get($this->client['ip'],self::IP_VISIT_TIMES_FLAG));
        $startChallengeTimes = Common::getConfig('ip_start_challenge_times',$this->config);
        if($visitTimes < $startChallengeTimes){
            $visitTimes++;
            Common::M()->set(
                $this->client['ip'],
                $visitTimes,
                Common::getConfig('ip_start_challenge_lifetime',$this->config),
                self::IP_VISIT_TIMES_FLAG
            );
            Common::Log()->info(__METHOD__,sprintf('The client ip[%s] was overlook',$this->client['ip']));
            return true;
        }

        // 根据配置启用相应挑战模块
        $challengeType = Common::getConfig('challenge_model',$this->config);
        switch($challengeType){
            case 'js' :
                $this->jsChallenge();
                break;
            case 'code' :
                $this->CodeChallenge();
                break;
            case 'code-cn' :
                $this->CodeChallenge(true);
                break;
            case 'proof-of-work':
                $this->ProofOfWorkChallenge();
                break;
            default :
                die('Undefined challenge model');
        }
        $startRAM = Common::click();// 脚本开始执行内存
        $endRAM = Common::RAMclick();// 脚本结束执行内存
        Common::Log()->debug(__METHOD__,sprintf('Running robot detection with type %s use time %fs,RAM %s',$challengeType,$endRAM-$startTime,$endRAM-$startRAM));
        return true;
    }

    /*
     * JS类型挑战
     */
    private function jsChallenge(){
        $Js = new Challenge\JsChallenge($this->client);
        // 如果存在JS挑战验证码且验证通过则加入白名单
        $checkCode = $Js->checkCode();

        // 统计挑战结果
        if($this->checkCodeCount($checkCode)){
            return true;
        }

        // 生成验证码
        $verifyCode = $Js->makeVerifyCode();

        Common::Log()->info(__METHOD__,sprintf('Session[%s] start js challenge with code:%s',$this->client['session'],$verifyCode));
        $Js->startChallenge($verifyCode);
        exit;

    }

    /*
     * 工作量证明挑战
     */
    public function ProofOfWorkChallenge(){
        $ProofOfWork = new Challenge\ProofOfWorkChallenge($this->client);
        // 如果存在JS挑战验证码且验证通过则加入白名单
        $checkCode = $ProofOfWork->checkCode();

        // 统计挑战结果
        if($this->checkCodeCount($checkCode)){
            return true;
        }

        // 生成验证码
        $verifyCode = $ProofOfWork->makeVerifyCode();

        Common::Log()->info(__METHOD__,sprintf('Session[%s] start proof of work challenge with code:%s',$this->client['session'],$verifyCode));
        $ProofOfWork->startChallenge($verifyCode);
        exit;
    }

    /*
     * 验证码类型挑战
     */
    private function CodeChallenge($isChinese=false){
        $verifyCode = '';
        if($isChinese){
            $Code = new Challenge\ChineseVerifyCodeChallenge($this->client);
        }else{
            $Code = new Challenge\VerifyCodeChallenge($this->client);
        }


        // 如果是获取图片则直接输出图片
        if(isset($this->client['get'][$Code::IMG_SHOW_FLAG])){
            // 生成验证码
            $verifyCode = $Code->makeVerifyCode(Common::getConfig('verify_code_length',$this->config));
            $Code->ShowVerifyImage($verifyCode);
            Common::Log()->info(__METHOD__,sprintf('Session[%s] start code challenge with code:%s',$this->client['session'],$verifyCode));
            exit;
        }

        // 如果存在JS挑战验证码且验证通过则加入白名单
        $checkCode = $Code->checkCode();

        // 统计挑战结果
        if($this->checkCodeCount($checkCode)){
            // 挑战成功
            if(isset($this->client['post'][Challenge\VerifyCodeChallenge::CODE_CHALLENGE_FLAG])){
                // ajax请求返回ajax信息
                $Code->ajaxReturnSuccess();
            }else{
                return true;
            }
        }elseif($checkCode === false){
            // 挑战失败
            $Code->ajaxReturnError();
        }

        Common::Log()->info(__METHOD__,sprintf('Session[%s] start code challenge',$this->client['session']));
        $Code->startChallenge();
        exit;
    }

    /*
     * 挑战结果统计
     */
    private function checkCodeCount($checkCode){
        if($checkCode){
            // 设置白名单标识
            $this->setWhiteFlag();
            // 重置统计信息
            $this->unsetCount();
            Common::Log()->info(__METHOD__,sprintf('Session[%s] robot challenge succeed',$this->client['session']));
            return true;
        }else{
            if($checkCode === false){
                Common::Log()->info(__METHOD__,sprintf('Session[%s] robot challenge failure',$this->client['session']));
            }
            $ipFailure = intval(Common::M()->get($this->client['ip'],self::IP_FAILURE_PREFIX));
            $ipFailure++;
            if($ipFailure > Common::getConfig('ip_failure_limit',$this->config)){
                // 如果验证失败则在指定挑战失败上限次数后将IP加入全局黑名单
                $this->wafPHP->addBlackList();
                // 重置统计信息
                $this->unsetCount();
            }else{
                // 累加该IP挑战失败次数
                Common::M()->set(
                    $this->client['ip'],
                    $ipFailure,
                    Common::getConfig('ip_failure_lifetime',$this->config),
                    self::IP_FAILURE_PREFIX
                );
            }
            return false;
        }
    }

    /*
     * 检查白名单时间是否有效
     */
    private function checkWhiteFlag(){
        $time = Common::session(self::SESSION_WHITE_FLAG);
        // 白名单未设置则直接返回
        if(!$time){
            return false;
        }

        //获取白名单有效时间
        $lifetime = Common::getConfig('white_list_timeout',$this->config);
        // 超过设定的白名单有效时候失效
        if(time() - $time > $lifetime){
            // 超时后删除白名单标识
            $this->delWhiteFlag();
            return false;
        }else{
            return true;
        }
    }

    /*
     * 设置白名单标识
     */
    private function setWhiteFlag(){
        return Common::session(self::SESSION_WHITE_FLAG,time());
    }

    /*
     * 删除白名单标识
     */
    private function delWhiteFlag(){
        return Common::session(self::SESSION_WHITE_FLAG,null);
    }

    /*
     * 重置IP段访问统计
     */
    private function unsetCount(){
        // 重置该IP累计访问次数
        Common::M()->del($this->client['ip'],self::IP_VISIT_TIMES_FLAG);
        // 重置该IP挑战失败次数
        Common::M()->del($this->client['ip'],self::IP_FAILURE_PREFIX);
    }
}