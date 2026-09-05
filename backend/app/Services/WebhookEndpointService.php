<?php
namespace App\Services;

use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Crypt;

class WebhookEndpointService
{
    /** @return array{endpoint: array, plain_secret: string} — secret shown once. */
    public function create(array $data): array
    {
        $plain = 'whsec_' . bin2hex(random_bytes(24));
        $endpoint = WebhookEndpoint::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'slug' => $this->uniqueSlug(),
            'secret' => Crypt::encryptString($plain),
            'secret_prefix' => substr($plain, 0, 12),
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);
        return ['endpoint' => $this->present($endpoint), 'plain_secret' => $plain];
    }

    public function present(WebhookEndpoint $e): array
    {
        return [
            'id' => $e->id,
            'name' => $e->name,
            'type' => $e->type,
            'slug' => $e->slug,
            'url' => route('webhooks.receive', ['slug' => $e->slug]),
            'secret_prefix' => $e->secret_prefix,
            'is_active' => $e->is_active,
            'last_received_at' => optional($e->last_received_at)->toIso8601String(),
            'created_at' => optional($e->created_at)->toIso8601String(),
        ];
    }

    private function uniqueSlug(): string
    {
        do { $slug = bin2hex(random_bytes(10)); }
        while (WebhookEndpoint::withoutGlobalScopes()->where('slug', $slug)->exists());
        return $slug;
    }
}
