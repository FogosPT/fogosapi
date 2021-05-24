<?php


namespace App\Tools;

use Spatie\Browsershot\Browsershot;

class ScreenShotTool
{
    public static function takeScreenShot($url, $name, $width = false, $height = false)
    {
        if(!$width){
            $width = env('SCREENSHOT_WIDTH');
        }
        if(!$height){
            $height = env('SCREENSHOT_HEIGHT');
        }

        exec("node /var/www/html/screenshot-script.js --url {$url} --width {$width} --height {$height} --name {$name}");
    }

    public static function removeScreenShotFile($name)
    {
        unlink("/var/www/html/public/screenshots/{$name}.png");

    }
}
