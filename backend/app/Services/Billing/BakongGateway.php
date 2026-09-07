<?php
namespace App\Services\Billing;

use App\Models\BillingSetting;
use App\Support\KhqrGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * KHQR via the Bakong network (National Bank of Cambodia).
 *
 * Generating the QR needs only the merchant's public Bakong identity, so it works as soon as those
 * fields are filled in. Verifying a payment ("has this md5 been paid?") calls the Bakong Open API and
 * requires an API token + base URL; without them the payment stays in manual-confirm mode.
 */
class BakongGateway
{
    private BillingSetting $settings;

    public function __construct(?BillingSetting $settings = null)
    {
        $this->settings = $settings ?: BillingSetting::singleton();
    }

    private function cfg(): array
    {
        return ($this->settings->config['khqr'] ?? []);
    }

    /** True once verification credentials exist (QR generation itself needs none). */
    public function canVerify(): bool
    {
        $c = $this->cfg();
        return !empty($c['api_token']) && !empty($c['api_base']);
    }

    /**
     * Build a KHQR for an amount.
     * @return array{qr:string,md5:string,deeplink:?string}
     */
    public function createCharge(float $amount, string $currency, string $billNumber): array
    {
        $c = $this->cfg();
        $out = KhqrGenerator::build([
            'bakong_id' => $c['bakong_id'] ?? '',
            'merchant_name' => $c['merchant_name'] ?? 'NPCRM',
            'merchant_city' => $c['merchant_city'] ?? 'Phnom Penh',
            'merchant_id' => $c['merchant_id'] ?? '',
            'acquiring_bank' => $c['acquiring_bank'] ?? '',
            'amount' => $amount,
            'currency' => $currency,
            'bill_number' => $billNumber,
            'store_label' => 'Subscription',
        ]);
        return ['qr' => $out['qr'], 'md5' => $out['md5'], 'deeplink' => null];
    }

    /** Ask Bakong whether the KHQR with this md5 has been paid. Returns true only on a confirmed match. */
    public function isPaid(string $md5): bool
    {
        if (!$this->canVerify() || $md5 === '') return false;
        $c = $this->cfg();
        try {
            $res = Http::withToken($c['api_token'])
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim($c['api_base'], '/').'/v1/check_transaction_by_md5', ['md5' => $md5]);
            if (!$res->successful()) return false;
            $body = $res->json();
            // Bakong returns responseCode 0 + data.hash present when the transaction is settled.
            return (int) ($body['responseCode'] ?? -1) === 0 && !empty($body['data']);
        } catch (\Throwable $e) {
            Log::warning('Bakong verify failed', ['md5' => $md5, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
