<?php
namespace App\Support;

/**
 * Builds a KHQR (Cambodia National QR / Bakong) payload following the EMVCo QR + NBC KHQR spec.
 *
 * The output string is what a phone's banking app scans; its md5 is what Bakong's Open API uses to
 * reconcile a payment (`check_transaction_by_md5`). Everything here is deterministic and needs only
 * the merchant's public identity (Bakong ID, name, city) — no API credentials — so a valid, scannable
 * KHQR can be produced the moment those fields are entered in Billing Settings.
 *
 * @see https://bakong.nbc.gov.kh  (KHQR / EMVCo tag layout)
 */
class KhqrGenerator
{
    private const CURRENCY_NUM = ['USD' => '840', 'KHR' => '116'];

    /**
     * @param  array{bakong_id:string,merchant_name:string,merchant_city?:string,amount:float|int|string,currency:string,bill_number?:string,store_label?:string,merchant_id?:string,acquiring_bank?:string,mcc?:string}  $o
     * @return array{qr:string,md5:string}
     */
    public static function build(array $o): array
    {
        $currency = strtoupper($o['currency'] ?? 'USD');
        $currencyNum = self::CURRENCY_NUM[$currency] ?? '840';
        $amount = number_format((float) ($o['amount'] ?? 0), 2, '.', '');
        $merchantId = trim((string) ($o['merchant_id'] ?? ''));
        $isMerchant = $merchantId !== '';

        // Merchant Account Information — tag 30 (merchant) or 29 (individual).
        $accountTag = $isMerchant ? '30' : '29';
        $account = self::field('00', trim((string) $o['bakong_id']));
        if ($isMerchant) {
            $account .= self::field('01', $merchantId);
            if (!empty($o['acquiring_bank'])) $account .= self::field('02', (string) $o['acquiring_bank']);
        }

        $payload = self::field('00', '01')                                  // Payload Format Indicator
            . self::field('01', '12')                                       // Point of Initiation — dynamic (amount present)
            . self::field($accountTag, $account)                            // Merchant Account Information
            . self::field('52', (string) ($o['mcc'] ?? '5999'))            // Merchant Category Code
            . self::field('53', $currencyNum)                               // Transaction Currency
            . self::field('54', $amount)                                    // Transaction Amount
            . self::field('58', 'KH')                                       // Country Code
            . self::field('59', self::clip($o['merchant_name'] ?? 'MERCHANT', 25))
            . self::field('60', self::clip($o['merchant_city'] ?? 'Phnom Penh', 15));

        // Additional Data Field (tag 62): bill number (01) and store label (03).
        $additional = '';
        if (!empty($o['bill_number'])) $additional .= self::field('01', self::clip($o['bill_number'], 25));
        if (!empty($o['store_label'])) $additional .= self::field('03', self::clip($o['store_label'], 25));
        if ($additional !== '') $payload .= self::field('62', $additional);

        // CRC (tag 63) is computed over the whole payload including the "6304" prefix.
        $payload .= '63' . '04';
        $payload .= self::crc16($payload);

        return ['qr' => $payload, 'md5' => md5($payload)];
    }

    /** EMV field: 2-char id + 2-char zero-padded length + value. */
    private static function field(string $id, string $value): string
    {
        return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    private static function clip(string $v, int $max): string
    {
        return substr(trim($v), 0, $max);
    }

    /** CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF) — uppercase 4-hex, per KHQR. */
    private static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
