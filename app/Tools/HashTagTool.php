<?php

namespace App\Tools;

class HashTagTool
{
    public static function getHashTag($concelho)
    {
        return '#IR'.preg_replace('/\s+|\-/', '', $concelho);
    }

    public static function getHashTagEmergencias($concelho)
    {
        return '#'.preg_replace('/\s+|\-/', '', $concelho);
    }
}
