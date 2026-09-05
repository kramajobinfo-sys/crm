<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

/** Opaque, tamper-proof (encrypted) token identifying a marketing recipient for opt-out. */
class UnsubscribeToken
{
    public static function encode(int $companyId, string $email, ?int $campaignId = null): string
    {
        $payload = json_encode(['c' => $companyId, 'e' => $email, 'k' => $campaignId]);
        return rtrim(strtr(base64_encode(Crypt::encryptString($payload)), '+/', '-_'), '=');
    }

    /** @return array{c:int,e:string,k:int|null}|null */
    public static function decode(string $token): ?array
    {
        try {
            $b64 = strtr($token, '-_', '+/');
            $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
            $data = json_decode(Crypt::decryptString(base64_decode($b64)), true);
            return (is_array($data) && isset($data['c'], $data['e'])) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
