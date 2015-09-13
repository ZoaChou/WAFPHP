<?php
/**
 * User: Zoa Chou
 * Date: 15/8/26
 */

namespace WAFPHP\Lib\Library\Challenge;


class JsChallenge extends BaseChallenge{
    const JS_CHALLENGE_FLAG = 'WAFPHP_JS_CHALLENGE';

    /*
     * js挑战生成更复杂的md5验证码
     */
    public function makeCode($dummy = 0){
        $code = parent::makeCode();
        return md5($code);
    }

    /*
     * 生成挑战js
     */
    public function startChallenge($verifyCode=''){
        $flag = self::JS_CHALLENGE_FLAG;
        // js挑战原文
        $jsReal = <<<EOF
<html>
<body onload="challenge();">
<script>
var key = '{$flag}';
var value = '{$verifyCode}';
function setCookie(key,value){
    var exp  = new Date();
    exp.setTime(exp.getTime() + 3*60*1000);
    document.cookie = key + '=' + value + ';expires=' + exp.toGMTString();;
}
function challenge(){
    setCookie(key,value);
    window.location.reload(true);
}
</script>
</body>
</html>
EOF;
        // js挑战加密混淆，注意字符转义后输出是否为预期
        $js = <<<EOF
<html>
<body onload="challenge();">
<script>
eval(function(p,a,c,k,e,r){e=function(c){return c.toString(a)};if(!''.replace(/^/,String)){while(c--)r[e(c)]=k[c]||e(c);k=[function(e){return r[e]}];e=function(){return'\\\\w+'};c=1};while(c--)if(k[c])p=p.replace(new RegExp('\\\\b'+e(c)+'\\\\b','g'),k[c]);return p}('1 5="6",2="9";3 4(b,c){1 a=7 8;a.n(a.d()+e);f.g=b+"="+c+";h="+a.i()}3 j(){4(5,2);k.l.m(!0)};',24,24,'|var|value|function|setCookie|key|{$flag}|new|Date|{$verifyCode}||||getTime|18E4|document|cookie|expires|toGMTString|challenge|window|location|reload|setTime'.split('|'),0,{}))
</script>
</body>
</html>
EOF;

        echo $js;
    }

    public function checkCode(){
        // 如果js挑战cookie不存在则表示未设定js挑战验证码
        if(!isset($this->client['cookie'][self::JS_CHALLENGE_FLAG])){
            return null;
        }
        // 从cookie中读取验证码
        $code = $this->client['cookie'][self::JS_CHALLENGE_FLAG];

        // 验证验证码是否正确
        if($this->checkVerify($code)){
            // 验证成功则清除js挑战cookie
            setcookie(self::JS_CHALLENGE_FLAG,'',time()-3600);
            return true;
        }else{
            return false;
        }

    }
}