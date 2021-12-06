<?php

namespace Util;

class Captcha
{
    private $dir_path = __DIR__ . '/images/';
    private $orig_info = [];
    private $width = 640;
    private $height = 300;
    private $angle = 0;

    public function __construct(array $option = [], ?string $dir_path = null)
    {
        if(isset($option['width']))$this->width = $option['width'];
        if(isset($option['height']))$this->height = $option['height'];
        if(!empty($dir_path))$this->dir_path = $dir_path;
    }

    public function create(): string
    {
        //挑选一张原始图
        $files = scandir($this->dir_path);
        shuffle($files);
        foreach($files as $file){
            if(!strpos($file,'.'))continue;
            $arr = getimagesize($this->dir_path . $file);
            if(in_array($arr[2], [1,2,3]) && $arr[0] >= $this->width && $arr[1] >= $this->height){
                $this->orig_info = [
                    'width' => $arr[0],
                    'height' => $arr[1],
                    'type' => $arr[2],
                    'path' => $this->dir_path . $file
                ];
                break;
            }
        }
        if(empty($this->orig_info)){
            trigger_error('没有符合要求的图片库', E_USER_WARNING);
            return '';
        }

        //获取图片数据
        $im = null;
        switch ($this->orig_info['type']){
            case 1:
                $im = @imagecreatefromgif($this->orig_info['path']);
                break;
            case 2:
                $im = @imagecreatefromjpeg($this->orig_info['path']);
                break;
            case 3:
                $im = @imagecreatefrompng($this->orig_info['path']);
                break;
        }
        if(empty($im)){
            trigger_error('图片载入失败，请检查GD库版本', E_USER_WARNING);
            return '';
        }

        //随机裁切出一块
        $master_im = imagecrop($im, [
            'x' => rand(0, $this->orig_info['width'] - $this->width),
            'y' => rand(0, $this->orig_info['height'] - $this->height),
            'width' => $this->width,
            'height' => $this->height
        ]);

        //从裁切图片中再随机裁切一块方形出来
        //随机圆形半径
        $radius = rand(80,120);
        //随机圆形坐标
        $position = [
            rand(10 + $radius, $this->width - $radius - 10),
            rand(10 + $radius, $this->height - $radius - 10)
        ];
        //创建一张透明图片
        $sub_im = imagecreatetruecolor($radius * 2, $radius * 2);
        $mask_im = imagecreatetruecolor($radius * 2, $radius * 2);
        imagesavealpha($mask_im, true);
        $bg = imagecolorallocatealpha($mask_im, 255, 255, 255, 127);
        imagefill($mask_im, 0, 0, $bg);
        $black = imagecolorallocate($mask_im, 0, 0, 0);

        //在透明图片上绘制圆形图像
        for ($x = 0; $x < $radius * 2; $x++) {
            for ($y = 0; $y < $radius * 2; $y++) {
                $rgbColor = imagecolorat($master_im, $x + $position[0] - $radius, $y + $position[1] - $radius);
                if(pow($x - $radius,2) + pow($y - $radius, 2) < pow($radius, 2)){
                    imagesetpixel($sub_im, $x, $y, $rgbColor);
                    imagesetpixel($mask_im, $x, $y, $black);
                }
            }
        }
        //将黑色遮罩覆盖在原图上
        imagecopy($master_im, $mask_im, $position[0] - $radius, $position[1] - $radius, 0, 0, $radius * 2, $radius * 2);

        //将圆形图旋转任意角度
        $this->angle = rand(0, 36000) / 100;
        $sub_im = imagerotate($sub_im, $this->angle, 0);
        $x = imagesx($sub_im) / 2 - $radius;
        $sub_im = imagecrop($sub_im, [
            'x' => $x,
            'y' => $x,
            'width' => $radius * 2,
            'height' => $radius * 2
        ]);

        ob_start();
        //写入时间戳
        echo pack('I', time());
        //写入X坐标
        echo pack('v', $position[0]);
        //写入Y坐标
        echo pack('v', $position[1]);
        //写入主图
        imagejpeg($master_im);
        $size1 = ob_get_length() - 8;
        //写入分图
        imagejpeg($sub_im);
        $size2 = ob_get_length() - $size1 - 8;
        //写入主图大小
        echo pack('I', $size1);
        //写入分图大小
        echo pack('I', $size2);
        //输出缓冲区
        return ob_get_clean();
    }

    /**
     * 获取角度验证码
     * @return float
     */
    public function getCode() : float
    {
        return $this->angle;
    }

    /**
     * 验证code是否正确
     * @param int $code
     * @return bool
     */
    public function verify(int $code) : bool
    {
        return ($code < $this->angle + 2) && ($code > $this->angle - 2);
    }
}
