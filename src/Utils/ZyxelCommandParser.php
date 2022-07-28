<?php

namespace App\Utils;

class ZyxelCommandParser
{
    public static function parse(string $cmdToParse): array
    {
        $urlInfos = self::detectUrlAndMethod($cmdToParse);
        $result = [
            "url" => $urlInfos['url'],
            "method" => $urlInfos['method'],
            "params" => self::generateParameters($cmdToParse)
        ];
        return $result;
    }
    private static function detectUrlAndMethod(string $cmdToParse)
    {
        $url = [];
        $elements = explode(" ", $cmdToParse);
        switch ($elements[0]) {
            case "port_settings":
                $url = ["url" => "/port_state_set.cgi", "method" => "POST"];
                break;
            default:
                throw new \Exception("not supported");
        }
        return $url;
    }
    private static function generateParameters(string $cmdToParse): array
    {
        $parmeters = [];
        $elements = explode(" ", $cmdToParse);

        return $parmeters;
    }
}
