<?php
/**
 * User: Zoa Chou
 * Date: 15/8/26
 */

namespace WAFPHP\Lib\Library\Challenge;
use WAFPHP\Common\Common;


class VerifyCodeChallenge extends BaseChallenge{
    const CODE_CHALLENGE_FLAG = 'WAFPHP_CODE_CHALLENGE';
    const IMG_SHOW_FLAG = 'WAFPHP_SHOW_VERIFY_IMG';
    const IMG_FONT_SIZE = 25;// 验证码字体大小(px)
    protected $ttfPath = null;// 字体文件路径
    private $fontColor = null;// 验证码字体颜色

    /*
     * 兼容PHP5.3
     */
    protected function init(){
        $this->ttfPath = WAF_ROOT.'Lib'.DIRECTORY_SEPARATOR.'Ttf'.DIRECTORY_SEPARATOR.'VerifyCode.ttf';
    }

    /*
     * 生成验证码输入页面
     */
    public function startChallenge($verifyCode=''){
        $selfUrl = 'http://'.$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI'];
        $showImgFlag = self::IMG_SHOW_FLAG;
        $codeChallengeFlag = self::CODE_CHALLENGE_FLAG;
        $verifyErrorTimes = self::ROBOT_CHALLENGE_ERROR_TIMES;
        $html = <<<EOF
<!DOCTYPE html>
<html>
<head lang="en">
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <meta name="robots" content="none" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="renderer" content="webkit">
    <meta name="author" content="佐柱">
    <title>机器人挑战模式</title>
    <link rel="stylesheet" href="http://www.mudoom.com/WAFPHP/css/bootstrap.min.css">
    <link href="http://www.mudoom.com/WAFPHP/css/style.css" rel="stylesheet">


    <block name="style">

    </block>
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="http://cdn.bootcss.com/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="http://cdn.bootcss.com/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body class="login-body">
    <div class="container-fluid">
        <form class="form-signin well" method="post" action="{$selfUrl}">
        <h2 class="form-signin-heading text-center">机器人挑战模式</h2>
            <label for="inputVerify" class="sr-only">验证码</label>
            <input type="text" name="{$codeChallengeFlag}" type="verify" id="inputVerify" class="form-control" placeholder="验证码" required>
            <div class="form-group">
                <img class="verifyimg reloadverify img-responsive img-thumbnail" title="点击切换" src="{$selfUrl}?&{$showImgFlag}=true">
            </div>
            <button class="btn btn-lg btn-primary btn-block" type="submit">提交</button>
        </form>

    </div>

<div class="modal fade" id="imgPreview" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="alert">
            <img class="img-responsive">
        </div>
    </div>
</div>
<div class="modal fade" id="message" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="alert">
            全局信息提示框
        </div>
    </div>
</div>

<script src="http://www.mudoom.com/WAFPHP/js/jquery.min.js"></script>
<script src="http://www.mudoom.com/WAFPHP/js/bootstrap.min.js"></script>
<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
<script>
        var verifyCodeErrorTimes = 0;
        jQuery(document).ready(function($) {
            if (navigator.userAgent.match(/IEMobile\/10\.0/)) {
                var msViewportStyle = document.createElement('style')
                msViewportStyle.appendChild(
                        document.createTextNode(
                                '@-ms-viewport{width:auto!important}'
                        )
                )
                document.querySelector('head').appendChild(msViewportStyle)
            }

            /*
             * 公用提示框
             * @param string message 提示信息
             * @param string type 信息类型
             * （success：成功，warn：警告，error：错误，默认：信息）
             * @param int time 提示框显示时间，0为不自动关闭提示框
             */
            $.pop = function(message,type,time) {
                switch(type){
                    case 'success' :
                        $('#message').find('.alert').removeClass('alert-success alert-warning alert-danger alert-info').addClass('alert-success');
                        break;
                    case 'warn' :
                        $('#message').find('.alert').removeClass('alert-success alert-warning alert-danger alert-info').addClass('alert-warning');
                        break;
                    case 'error' :
                        $('#message').find('.alert').removeClass('alert-success alert-warning alert-danger alert-info').addClass('alert-danger');
                        break;
                    default :
                        $('#message').find('.alert').removeClass('alert-success alert-warning alert-danger alert-info').addClass('alert-info');
                }
                $('#message').find('.alert').html(message);
                $('#message').modal('show');

                // 默认三秒钟后自动隐藏,为0时关闭自动隐藏
                if(time !== 0){
                    time = !time ? 3000 : time;
                    var messageSetTimeOut = window.setTimeout(function(){
                        $('#message').modal('hide');
                    }, time);

                    $('#message').on('hide.bs.modal', function () {
                        clearTimeout(messageSetTimeOut);
                    })
                }

            }

            $(".reloadverify").click(function(){
                verifyimg = $(".verifyimg").attr("src");
                if( verifyimg.indexOf('?')>0){
                    $(".verifyimg").attr("src", verifyimg+'&random='+Math.random());
                }else{
                    $(".verifyimg").attr("src", verifyimg.replace(/\?.*$/,'')+'?'+Math.random());
                }
            });

            //表单提交
            $(document)
                    .ajaxStart(function(){
                        $("button:submit").addClass("log-in").attr("disabled", true);
                    })
                    .ajaxStop(function(){
                        $("button:submit").removeClass("log-in").attr("disabled", false);
                    });

            $("form").submit(function(){
                var self = $(this);
                $.post(self.attr("action"), self.serialize(), success, "json");
                return false;

                function success(data){
                    if(data.status){
                        $.pop(data.info);
                        window.location.reload(true);
                    } else {
                        $.pop(data.info);
                        // 失败超过设定次数刷新验证码
                        verifyCodeErrorTimes++;
                        if(verifyCodeErrorTimes >= {$verifyErrorTimes}){
                            $(".reloadverify").click();
                        }
                    }
                }
            });

        });
    </script>
</body>
</html>
EOF;
        echo $html;
    }

    /*
     * 显示验证码图片
     */
    public function ShowVerifyImage($verifyCode){
        // 验证码长度
        $verifyCodeLength = mb_strlen($verifyCode);
        // 图片宽(px)
        $imageWidth = $verifyCodeLength*self::IMG_FONT_SIZE*1.5 + $verifyCodeLength*self::IMG_FONT_SIZE/2;
        // 图片高(px)
        $imageHeight = self::IMG_FONT_SIZE*2.5;
        // 建立图像
        $Image = imagecreate($imageWidth, $imageHeight);
        // 设置背景
        imagecolorallocate($Image, 243, 251, 254);

        // 验证码字体随机颜色
        $this->fontColor = imagecolorallocate($Image, mt_rand(1,150), mt_rand(1,150), mt_rand(1,150));
        // 验证码使用字体
        $fontPath = $this->ttfPath;

        // 绘制噪点
        $codeNoise = self::ROBOT_CHALLENGE_DEFAULT_CODE_TEXT;
        for($i = 0; $i < 10; $i++){
            //杂点颜色
            $noiseColor = imagecolorallocate($Image, mt_rand(150,225), mt_rand(150,225), mt_rand(150,225));
            for($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring(
                    $Image, 5, mt_rand(-10, $imageWidth),
                    mt_rand(-10, $imageHeight),
                    $codeNoise[mt_rand(0, strlen($codeNoise)-1)],
                    $noiseColor
                );
            }
        }
        // 绘制干扰线
        $this->_writeCurve($Image,$imageWidth,$imageHeight);

        // 绘制验证码
        $codeNX = 0;
        unset($code);
        for($i = 0; $i < $verifyCodeLength; $i++) {
            $code = mb_substr($verifyCode,$i,1);
            // 转换编码，防止出现乱码
            $code=mb_convert_encoding($code,"html-entities", "utf-8");
            $codeNX  += mt_rand(self::IMG_FONT_SIZE*1.2, self::IMG_FONT_SIZE*1.6); // 验证码第N个字符的左边距
            imagettftext($Image, self::IMG_FONT_SIZE, mt_rand(-40, 40), $codeNX, self::IMG_FONT_SIZE*1.6, $this->fontColor, $fontPath, $code);
        }

        header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header("content-type: image/png");

        // 输出图像
        imagepng($Image);
        imagedestroy($Image);
    }

    /*
     * 检查验证码是否正确
     */
    public function checkCode(){
        // 如果验证码挑战post不存在则表示未设定js挑战验证码
        if(!isset($this->client['post'][self::CODE_CHALLENGE_FLAG])){
            return null;
        }
        // 从post中读取验证码
        $code = $this->client['post'][self::CODE_CHALLENGE_FLAG];

        // 验证验证码是否正确
        if($this->checkVerify($code)){
            // 验证成功则清除post中的挑战标签
            unset($_POST[self::CODE_CHALLENGE_FLAG]);
            return true;
        }else{
            return false;
        }
    }

    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线(你可以改成更帅的曲线函数)
     *
     *      高中的数学公式咋都忘了涅，写出来
     *		正弦型函数解析式：y=Asin(ωx+φ)+b
     *      各常数值对函数图像的影响：
     *        A：决定峰值（即纵向拉伸压缩的倍数）
     *        b：表示波形在Y轴的位置关系或纵向移动距离（上加下减）
     *        φ：决定波形与X轴位置关系或横向移动距离（左加右减）
     *        ω：决定周期（最小正周期T=2π/∣ω∣）
     * @author: ThinkPHP
     */
    private function _writeCurve(&$Image,$width,$height) {
        $px = $py = 0;

        // 曲线前部分
        $A = mt_rand(1, $height/2);                  // 振幅
        $b = mt_rand(-$height/4, $height/4);   // Y轴方向偏移量
        $f = mt_rand(-$height/4, $height/4);   // X轴方向偏移量
        $T = mt_rand($height, $width*2);  // 周期
        $w = (2* M_PI)/$T;

        $px1 = 0;  // 曲线横坐标起始位置
        $px2 = mt_rand($width/2, $width * 0.8);  // 曲线横坐标结束位置

        for ($px=$px1; $px<=$px2; $px = $px + 1) {
            if ($w!=0) {
                $py = $A * sin($w*$px + $f)+ $b + $height/2;  // y = Asin(ωx+φ) + b
                $i = (int) (self::IMG_FONT_SIZE/5);
                while ($i > 0) {
                    imagesetpixel($Image, $px + $i , $py + $i, $this->fontColor);  // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
                    $i--;
                }
            }
        }

        // 曲线后部分
        $A = mt_rand(1, $height/10);                  // 振幅
        $f = mt_rand(-$height/4, $height/4);   // X轴方向偏移量
        $T = mt_rand($height, $width*2);  // 周期
        $w = (2* M_PI)/$T;
        $b = $py - $A * sin($w*$px + $f) - $height/2;
        $px1 = $px2;
        $px2 = $width;

        for ($px=$px1; $px<=$px2; $px=$px+ 1) {
            if ($w!=0) {
                $py = $A * sin($w*$px + $f)+ $b + $height/2;  // y = Asin(ωx+φ) + b
                $i = (int) (self::IMG_FONT_SIZE/5);
                while ($i > 0) {
                    imagesetpixel($Image, $px + $i, $py + $i, $this->fontColor);
                    $i--;
                }
            }
        }
    }

    /*
     * 返回验证成功ajax信息
     */
    public function ajaxReturnSuccess(){
        $data = array(
            'status'=>1,
            'info'=>'通过验证，跳转中请稍后',
        );
        Common::ajaxReturn($data);
    }

    /*
     * 返回验证失败ajax信息
     */
    public function ajaxReturnError(){
        $data = array(
            'status'=>0,
            'info'=>'验证码错误',
        );
        Common::ajaxReturn($data);
    }

}