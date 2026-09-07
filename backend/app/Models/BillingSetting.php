<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton platform billing config (row id=1). `config` holds encrypted gateway credentials;
 * never expose it raw over the API — use publicConfig()/methodConfigured() instead.
 */
class BillingSetting extends Model
{
    protected $fillable = ['enabled', 'cod_instructions', 'config'];

    protected function casts(): array
    {
        return ['enabled' => 'array', 'config' => 'encrypted:array'];
    }

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['enabled' => ['cod' => true, 'khqr' => false, 'aba_khqr' => false]],
        );
    }

    /** Whether a payment method is switched on by the admin. */
    public function methodEnabled(string $method): bool
    {
        return (bool) ($this->enabled[$method] ?? false);
    }

    /** Whether a QR method has the credentials it needs to run live (vs. manual-confirm fallback). */
    public function methodConfigured(string $method): bool
    {
        $c = $this->config ?? [];
        return match ($method) {
            'khqr' => !empty($c['khqr']['bakong_id']) && !empty($c['khqr']['merchant_name']),
            'aba_khqr' => !empty($c['aba']['merchant_id']) && !empty($c['aba']['api_key']),
            'cod' => true,
            default => false,
        };
    }

    /** Non-secret view of the config, safe to return to a platform admin (secrets masked). */
    public function publicConfig(): array
    {
        $c = $this->config ?? [];
        return [
            'khqr' => [
                'merchant_name' => $c['khqr']['merchant_name'] ?? '',
                'merchant_city' => $c['khqr']['merchant_city'] ?? 'Phnom Penh',
                'bakong_id' => $c['khqr']['bakong_id'] ?? '',
                'api_base' => $c['khqr']['api_base'] ?? '',
                'has_api_token' => !empty($c['khqr']['api_token']),
            ],
            'aba' => [
                'merchant_id' => $c['aba']['merchant_id'] ?? '',
                'base_url' => $c['aba']['base_url'] ?? '',
                'sandbox' => (bool) ($c['aba']['sandbox'] ?? true),
                'has_api_key' => !empty($c['aba']['api_key']),
            ],
        ];
    }
}
