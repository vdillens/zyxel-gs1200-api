<?php

namespace App\Utils;

/**
 * Utility for crypt a password for Zyxel router
 */
class ZyxelCrypt
{
    /**
     * Get a single random caracter from a list 
     *
     * @return string
     */
    private static function randomStr(): string
    {
        $randomString = [
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
            'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
            'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
        ];

        return $randomString[rand(0, count($randomString) - 1)];
    }

    /**
     * Encrypt a password in order to login in the Zyxel's interface
     *
     * @param string $password The password that we need to crypt
     * @return string The encrypted password according Zyxel algorithm
     */
    public static function encryptPassword(string $password): string
    {
        $passwordElements = str_split($password);
        $passwordFinal = "";
        for ($i = 0; $i < count($passwordElements); $i++) {
            $caracter = $passwordElements[$i];

            $code = ord($caracter);
            $tempStr = chr($code - count($passwordElements));
            $passwordFinal .= self::randomStr() . $tempStr;

            if ($i == count($passwordElements) - 1) {
                $passwordFinal .= self::randomStr();
            }
        }

        return $passwordFinal;
    }
}
