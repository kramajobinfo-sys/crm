<?php
namespace App\Services\Billing;

use App\Models\BillingSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ABA KHQR via ABA Bank's PayWay merchant API.
 *
 * PayWay authenticates each call with an HMAC-SHA512 hash of the concatenated request params keyed by
 * the merchant API key. We request a `abapay_khqr` purchase, which returns a KHQR string + an ABA app
 * deeplink, and later poll `check-transaction`. Live use needs merchant_id + api_key + base_url; until
 * those are set the method degrades to manual-confirm.
 *
 * NOTE: the exact hash field ORDER is PayWay-API-version specific. The order below follows the common
 * v1 purchase spec; confirm it against your PayWay merchant docs when credentials are added.
 */
class AbaPayWayGateway
{
    private BillingSetting $settings;

    public function __construct(?BillingSetting $settings = null)
    {
        $this->settings = $settings ?: BillingSetting::singleton();
    }

    private function cfg(): array
    {
        return ($this->settings->config['aba'] ?? []);
    }

    public function isConfigured(): bool
    {
        $c = $this->cfg();
        return !empty($c['merchant_id']) && !empty($c['api_key']) && !empty($c['base_url']);
    }

    /**
     * Create an ABA KHQR purchase transaction.
     * @return array{ref:string,qr:?string,deeplink:?string,raw:array}
     */
    public function createCharge(string $tranId, float $amount, string $currency, string $itemLabel): array
    {
        $c = $this->cfg();
        $reqTime = now()->format('YmdHis');
        $amountStr = number_format($amount, 2, '.', '');
        $items = base64_encode(json_encode([['name' => $itemLabel, 'quantity' => 1, 'price' => $amountStr]]));
        $type = 'purchase';
        $paymentOption = 'abapay_khqr';
        $returnUrl = base64_encode(url('/api/v1/billing/aba/callback'));

        // Hash order (PayWay v1 purchase): req_time, merchant_id, tran_id, amount, items, type,
        // payment_option, return_url, currency. Sign with the merchant API key.
        $toHash = $reqTime . $c['merchant_id'] . $tranId . $amountStr . $items . $type . $paymentOption . $returnUrl . $currency;
        $hash = base64_encode(hash_hmac('sha512', $toHash, $c['api_key'], true));

        try {
            $res = Http::asMultipart()->timeout(20)->post(rtrim($c['base_url'], '/').'/api/payment-gateway/v1/payments/purchase', [
                ['name' => 'req_time', 'contents' => $reqTime],
                ['name' => 'merchant_id', 'contents' => $c['merchant_id']],
                ['name' => 'tran_id', 'contents' => $tranId],
                ['name' => 'amount', 'contents' => $amountStr],
                ['name' => 'items', 'contents' => $items],
                ['name' => 'type', 'contents' => $type],
                ['name' => 'payment_option', 'contents' => $paymentOption],
                ['name' => 'return_url', 'contents' => $returnUrl],
                ['name' => 'currency', 'contents' => $currency],
                ['name' => 'hash', 'contents' => $hash],
            ]);
            $body = $res->json() ?? [];
            return [
                'ref' => $tranId,
                'qr' => $body['qrString'] ?? ($body['qr_string'] ?? null),
                'deeplink' => $body['abapay_deeplink'] ?? ($body['deeplink'] ?? null),
                'raw' => is_array($body) ? $body : [],
            ];
        } catch (\Throwable $e) {
            Log::warning('ABA PayWay purchase failed', ['tran_id' => $tranId, 'error' => $e->getMessage()]);
            return ['ref' => $tranId, 'qr' => null, 'deeplink' => null, 'raw' => []];
        }
    }

    /** Poll PayWay for a transaction's status. Returns true when settled/approved. */
    public function isPaid(string $tranId): bool
    {
        if (!$this->isConfigured() || $tranId === '') return false;
        $c = $this->cfg();
        $reqTime = now()->format('YmdHis');
        $toHash = $reqTime . $c['merchant_id'] . $tranId;
        $hash = base64_encode(hash_hmac('sha512', $toHash, $c['api_key'], true));
        try {
            $res = Http::asMultipart()->timeout(15)->post(rtrim($c['base_url'], '/').'/api/payment-gateway/v1/payments/check-transaction', [
                ['name' => 'req_time', 'contents' => $reqTime],
                ['name' => 'merchant_id', 'contents' => $c['merchant_id']],
                ['name' => 'tran_id', 'contents' => $tranId],
                ['name' => 'hash', 'contents' => $hash],
            ]);
            if (!$res->successful()) return false;
            $body = $res->json();
            // PayWay: status.code "00" (or data.payment_status APPROVED) means paid.
            $statusCode = $body['status']['code'] ?? ($body['data']['status'] ?? null);
            $payStatus = strtoupper((string) ($body['data']['payment_status'] ?? ''));
            return $statusCode === '00' || $statusCode === 0 || $payStatus === 'APPROVED';
        } catch (\Throwable $e) {
            Log::warning('ABA PayWay check failed', ['tran_id' => $tranId, 'error' => $e->getMessage()]);
            return false;
        }
    }
}
