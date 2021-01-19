<?php

class Examples
{
    public function getCode()
    {
        //实例化验证类
        $cap = new \Util\Captcha();

        //设置header 输出二进制
        header('Content-Type', 'application/octet-stream');
        header('Content-Transfer-Encoding', 'binary');

        //生成验证图片
        $bin = $cap->create();

        //记录验证码
        getRedis()->set('captcha_code', $cap->getCode());

        echo $bin;
    }

    public function verifyCode()
    {
        $code = $_POST['code'];
        $angle = getRedis()->get('captcha_code');
        //验证码比对，需要允许存在一些误差
        if(($code < $angle + 2) && ($code > $angle - 2)){
            echo '验证成功';
        }else{
            echo '验证失败';
        }
    }
}
