<?php

namespace App\Utils\ZyxelParser;

class ZyxelParametersGeneratorFactory
{
    public static function get(string $type): ZyxelParametersGeneratorInterface
    {
        switch ($type) {
            case ZyxelPortsSettingsParametersGenerator::COMMAND_NAME:
                return new ZyxelPortsSettingsParametersGenerator();
                break;
            default:
                throw new ZyxelParametersGeneratorFactoryEception("no parameters generator found");
        }
    }
}
