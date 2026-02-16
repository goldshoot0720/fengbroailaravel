<?php
// 動態生成 PWA 圖示
$size = isset($_GET['size']) ? intval($_GET['size']) : 192;
if (!in_array($size, [192, 512])) $size = 192;

header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000');

$img = imagecreatetruecolor($size, $size);
imagesavealpha($img, true);

// 背景圓角矩形
$bg = imagecolorallocate($img, 52, 152, 219); // #3498db
$dark = imagecolorallocate($img, 44, 62, 80);  // #2c3e50
$white = imagecolorallocate($img, 255, 255, 255);

// 填滿背景
imagefilledrectangle($img, 0, 0, $size, $size, $bg);

// 繪製文字 "鋒"
$fontSize = $size * 0.45;
$fontFile = null;

// 嘗試找系統中文字型
$fontPaths = [
    'C:/Windows/Fonts/msjh.ttc',      // 微軟正黑體
    'C:/Windows/Fonts/msyh.ttc',      // 微軟雅黑
    'C:/Windows/Fonts/simsun.ttc',    // 宋體
    'C:/Windows/Fonts/simhei.ttf',    // 黑體
    '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
    '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
];

foreach ($fontPaths as $path) {
    if (file_exists($path)) {
        $fontFile = $path;
        break;
    }
}

if ($fontFile) {
    $bbox = imagettfbbox($fontSize, 0, $fontFile, '鋒');
    $textWidth = $bbox[2] - $bbox[0];
    $textHeight = $bbox[1] - $bbox[7];
    $x = ($size - $textWidth) / 2 - $bbox[0];
    $y = ($size - $textHeight) / 2 - $bbox[7];
    imagettftext($img, $fontSize, 0, (int)$x, (int)$y, $white, $fontFile, '鋒');
} else {
    // 沒有中文字型，用英文
    $fontSize2 = $size * 0.35;
    $text = 'F';
    $bbox = imagettfbbox($fontSize2, 0, 5, $text);
    imagestring($img, 5, (int)($size * 0.38), (int)($size * 0.35), $text, $white);
}

imagepng($img);
imagedestroy($img);
