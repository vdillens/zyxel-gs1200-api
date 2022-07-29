<?php

namespace App\Utils\ZyxelParser;

class ZyxelCommandParser
{
    public static function parse(string $device, array $cmdToParse): array
    {
        $urlInfos = self::detectUrlAndMethod($cmdToParse);
        $result = [
            "url" => $urlInfos['url'],
            "headers" => self::generateHeaders($cmdToParse),
            "method" => $urlInfos['method'],
            "params" => self::generateParameters($device, $cmdToParse)

        ];
        return $result;
    }
    private static function detectUrlAndMethod(array $cmdToParse)
    {
        $zyxelParametersGenerator = ZyxelParametersGeneratorFactory::get($cmdToParse[0]);
        return $zyxelParametersGenerator->generateUrlAndMethod();
    }
    private static function generateHeaders(array $cmdToParse)
    {
        $zyxelParametersGenerator = ZyxelParametersGeneratorFactory::get($cmdToParse[0]);
        return $zyxelParametersGenerator->generateHeaders();
    }
    private static function generateParameters(string $device, array $cmdToParse): array
    {
        $zyxelParametersGenerator = ZyxelParametersGeneratorFactory::get($cmdToParse[0]);
        return $zyxelParametersGenerator->generateParameters($device, $cmdToParse);
    }
}
