<?php

namespace App\Helpers;

class TotpHelper
{
    /**
     * Verify a 6-digit TOTP code against a Base32 secret key.
     *
     * @param  string  $secret  The 16-character Base32 secret.
     * @param  string  $code  The 6-digit code to verify.
     * @param  int  $discrepancy  Allowed time window discrepancy (+/- 30s steps).
     */
    public static function verify(string $secret, string $code, int $discrepancy = 1): bool
    {
        $currentTimeSlice = floor(time() / 30);
        $secretUpper = strtoupper($secret);

        $lookupTable = [
            'A' => 0,  'B' => 1,  'C' => 2,  'D' => 3,  'E' => 4,  'F' => 5,  'G' => 6,  'H' => 7,
            'I' => 8,  'J' => 9,  'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
            'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31,
        ];

        $secretUpper = str_replace('=', '', $secretUpper);
        $secretLength = strlen($secretUpper);
        $binaryString = '';

        for ($i = 0; $i < $secretLength; $i = $i + 8) {
            $x = '';
            for ($j = 0; $j < 8; $j++) {
                $char = $secretUpper[$i + $j] ?? 'A';
                $x .= str_pad(decbin($lookupTable[$char] ?? 0), 5, '0', STR_PAD_LEFT);
            }
            foreach (str_split($x, 8) as $eightBit) {
                $binaryString .= chr(bindec($eightBit));
            }
        }

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $timeSlice = $currentTimeSlice + $i;
            $time = pack('N*', 0).pack('N*', $timeSlice);
            $hash = hash_hmac('sha1', $time, $binaryString, true);
            $offset = ord($hash[19]) & 0xF;
            $value = (
                ((ord($hash[$offset]) & 0x7F) << 24) |
                ((ord($hash[$offset + 1]) & 0xFF) << 16) |
                ((ord($hash[$offset + 2]) & 0xFF) << 8) |
                (ord($hash[$offset + 3]) & 0xFF)
            );
            $calculatedCode = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);

            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }
}
