<?php
class QRcode {
    public static function png($text, $outfile = false, $level = 'L', $size = 3, $margin = 4) {
        $length = strlen($text);
        $scale = max(1, (int)$size);
        $marginPixels = $margin * $scale;
        $imgSize = ($length * $scale) + $marginPixels * 2;
        $im = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefill($im, 0, 0, $white);
        for ($i = 0; $i < $length; $i++) {
            $bit = ord($text[$i]);
            for ($b = 0; $b < 8; $b++) {
                $color = ($bit & (1 << $b)) ? $black : $white;
                $x = $marginPixels + ($i * $scale);
                $y = $marginPixels + ($b * $scale);
                imagefilledrectangle($im, $x, $y, $x + $scale - 1, $y + $scale - 1, $color);
            }
        }
        if ($outfile) {
            imagepng($im, $outfile);
        } else {
            header('Content-Type: image/png');
            imagepng($im);
        }
        imagedestroy($im);
    }
}
?>

