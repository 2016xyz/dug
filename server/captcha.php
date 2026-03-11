<?php
// captcha.php
session_start();

$width = 120;
$height = 40;
$image = imagecreatetruecolor($width, $height);

// 颜色
$bg = imagecolorallocate($image, 240, 240, 240);
$text_color = imagecolorallocate($image, 50, 50, 50);
$line_color = imagecolorallocate($image, 200, 200, 200);

imagefill($image, 0, 0, $bg);

// 生成验证码
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$captcha = '';
for ($i = 0; $i < 4; $i++) {
    $captcha .= $chars[rand(0, strlen($chars) - 1)];
}
$_SESSION['captcha'] = $captcha;

// 干扰线
for ($i = 0; $i < 5; $i++) {
    imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $line_color);
}

// 写入文字
for ($i = 0; $i < 4; $i++) {
    imagestring($image, 5, 20 + ($i * 25), 10, $captcha[$i], $text_color);
}

header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
?>
