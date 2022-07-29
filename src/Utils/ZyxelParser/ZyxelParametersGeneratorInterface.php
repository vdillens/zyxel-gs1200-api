<?php

namespace App\Utils\ZyxelParser;

interface ZyxelParametersGeneratorInterface
{
    public function generateParameters(string $device, array $cmdParse): array;
    public function generateUrlAndMethod(): array;
    public function generateHeaders(): array;
}
