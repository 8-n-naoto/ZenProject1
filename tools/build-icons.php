<?php

/**
 * PWA用のアイコンPNGを生成する（外部ツール不要）。
 *
 *   php tools/build-icons.php
 */
$sizes = [192, 512];
$dir = __DIR__.'/../public/icons';

if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

foreach ($sizes as $size) {
    $image = imagecreatetruecolor($size, $size);

    // 背景（sky-600）
    $bg = imagecolorallocate($image, 2, 132, 199);
    imagefilledrectangle($image, 0, 0, $size, $size, $bg);

    // 中央の白い「共」を模した記号（フォントに依存しないよう図形で描く）
    $white = imagecolorallocate($image, 255, 255, 255);
    $unit = (int) round($size / 16);
    $bar = max(2, (int) round($size / 22));

    // 上の横棒
    imagefilledrectangle($image, $unit * 4, $unit * 4, $size - $unit * 4, $unit * 4 + $bar, $white);
    // 中の横棒
    imagefilledrectangle($image, $unit * 3, $unit * 7, $size - $unit * 3, $unit * 7 + $bar, $white);
    // 左右の縦棒
    imagefilledrectangle($image, $unit * 5, $unit * 4, $unit * 5 + $bar, $unit * 10, $white);
    imagefilledrectangle($image, $size - $unit * 5 - $bar, $unit * 4, $size - $unit * 5, $unit * 10, $white);
    // 下の2本の足
    imagefilledrectangle($image, $unit * 4, $size - $unit * 4 - $bar, $unit * 6, $size - $unit * 4, $white);
    imagefilledrectangle($image, $size - $unit * 6, $size - $unit * 4 - $bar, $size - $unit * 4, $size - $unit * 4, $white);

    imagepng($image, $dir.'/icon-'.$size.'.png');
    imagedestroy($image);

    echo 'generated '.$dir.'/icon-'.$size.'.png'.PHP_EOL;
}
