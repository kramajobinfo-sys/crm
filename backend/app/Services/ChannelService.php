<?php
namespace App\Services;

use App\Models\ChatChannel;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Manages the connection settings (API credentials) for each social/chat channel account.
 * Credentials live in the already-encrypted `chat_channels.config` column; secret fields are
 * NEVER returned to the client — only whether they are set.
 */
class ChannelService
{
    /**
     * Per-type credential schema. `secret` fields are write-only (masked on read); `account_field`
     * names the config key mirrored into `external_account_id`. `webhook` is the callback path a
     * user would register with the provider (informational — no live receiver is wired here).
     */
    public function schemas(): array
    {
        return [
            'whatsapp' => [
                'label' => 'WhatsApp Business', 'account_field' => 'phone_number_id',
                'webhook' => '/api/v1/webhooks/whatsapp',
                'fields' => [
                    ['key' => 'phone_number_id', 'label' => 'Phone number ID', 'required' => true],
                    ['key' => 'business_account_id', 'label' => 'WhatsApp Business Account ID', 'required' => false],
                    ['key' => 'access_token', 'label' => 'Permanent access token', 'required' => true, 'secret' => true],
                    ['key' => 'verify_token', 'label' => 'Webhook verify token', 'required' => false, 'secret' => true],
                ],
            ],
            'messenger' => [
                'label' => 'Facebook Messenger', 'account_field' => 'page_id',
                'webhook' => '/api/v1/webhooks/messenger',
                'fields' => [
                    ['key' => 'page_id', 'label' => 'Facebook Page ID', 'required' => true],
                    ['key' => 'page_access_token', 'label' => 'Page access token', 'required' => true, 'secret' => true],
                    ['key' => 'app_secret', 'label' => 'App secret', 'required' => false, 'secret' => true],
                    ['key' => 'verify_token', 'label' => 'Webhook verify token', 'required' => false, 'secret' => true],
                ],
            ],
            'instagram' => [
                'label' => 'Instagram Direct', 'account_field' => 'ig_account_id',
                'webhook' => '/api/v1/webhooks/instagram',
                'fields' => [
                    ['key' => 'ig_account_id', 'label' => 'Instagram account ID', 'required' => true],
                    ['key' => 'page_access_token', 'label' => 'Linked Page access token', 'required' => true, 'secret' => true],
                    ['key' => 'verify_token', 'label' => 'Webhook verify token', 'required' => false, 'secret' => true],
                ],
            ],
            'telegram' => [
                'label' => 'Telegram Bot', 'account_field' => 'bot_username',
                'webhook' => '/api/v1/webhooks/telegram',
                'fields' => [
                    ['key' => 'bot_username', 'label' => 'Bot username', 'required' => true],
                    ['key' => 'bot_token', 'label' => 'Bot token', 'required' => true, 'secret' => true],
                ],
            ],
            'tiktok' => [
                'label' => 'TikTok', 'account_field' => 'app_id',
                'webhook' => '/api/v1/webhooks/tiktok',
                'fields' => [
                    ['key' => 'app_id', 'label' => 'App ID', 'required' => true],
                    ['key' => 'app_secret', 'label' => 'App secret', 'required' => true, 'secret' => true],
                    ['key' => 'access_token', 'label' => 'Access token', 'required' => false, 'secret' => true],
                ],
            ],
            'sms' => [
                'label' => 'SMS', 'account_field' => 'sender_id',
                'webhook' => '/api/v1/webhooks/sms',
                'fields' => [
                    ['key' => 'provider', 'label' => 'Provider (twilio, unifonic, …)', 'required' => true],
                    ['key' => 'sender_id', 'label' => 'Sender ID / number', 'required' => true],
                    ['key' => 'api_key', 'label' => 'API key', 'required' => true, 'secret' => true],
                    ['key' => 'api_secret', 'label' => 'API secret', 'required' => false, 'secret' => true],
                ],
            ],
            'webchat' => [
                'label' => 'Website chat widget', 'account_field' => 'widget_id',
                'webhook' => null,
                'fields' => [
                    ['key' => 'widget_id', 'label' => 'Widget ID', 'required' => false],
                    ['key' => 'allowed_origin', 'label' => 'Allowed website origin', 'required' => false],
                ],
            ],
        ];
    }

    public function types(): array
    {
        return collect($this->schemas())->map(fn ($s, $type) => [
            'type' => $type, 'label' => $s['label'], 'webhook' => $s['webhook'],
            'fields' => array_map(fn ($f) => [
                'key' => $f['key'], 'label' => $f['label'],
                'required' => (bool) ($f['required'] ?? false), 'secret' => (bool) ($f['secret'] ?? false),
            ], $s['fields']),
        ])->values()->all();
    }

    public function list(): Collection
    {
        return ChatChannel::orderBy('type')->orderBy('name')->withCount('conversations')->get()
            ->map(fn (ChatChannel $c) => $this->present($c));
    }

    public function create(array $data): ChatChannel
    {
        $schema = $this->schemaFor($data['type']);
        $config = $this->buildConfig($schema, $data['config'] ?? [], []);
        $channel = ChatChannel::create([
            'type' => $data['type'],
            'name' => $data['name'],
            'external_account_id' => $this->accountId($schema, $config),
            'config' => $config,
            'is_active' => $data['is_active'] ?? true,
        ]);
        return $channel;
    }

    public function update(ChatChannel $channel, array $data): ChatChannel
    {
        $schema = $this->schemaFor($channel->type);
        // Start from the existing (decrypted) config so blank secrets are preserved.
        $config = $this->buildConfig($schema, $data['config'] ?? [], $channel->config ?? []);
        $channel->update([
            'name' => $data['name'] ?? $channel->name,
            'config' => $config,
            'external_account_id' => $this->accountId($schema, $config),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $channel->is_active,
        ]);
        return $channel;
    }

    /**
     * Structural connection test: confirm every required field is set. No live provider is
     * contacted — a real handshake would go here once outbound delivery is wired.
     */
    public function test(ChatChannel $channel): array
    {
        $schema = $this->schemaFor($channel->type);
        $config = $channel->config ?? [];
        $missing = [];
        foreach ($schema['fields'] as $f) {
            if (($f['required'] ?? false) && empty($config[$f['key']])) $missing[] = $f['label'];
        }
        return [
            'ok' => $missing === [],
            'missing' => $missing,
            'message' => $missing === []
                ? 'All required credentials are set. Live provider verification is not wired up in this build.'
                : 'Missing required credentials: '.implode(', ', $missing).'.',
        ];
    }

    /** Public read shape — secrets shown only as set/unset, never their value. */
    public function present(ChatChannel $channel): array
    {
        $schema = $this->schemaFor($channel->type, false);
        $config = $channel->config ?? [];
        $credentials = [];
        foreach (($schema['fields'] ?? []) as $f) {
            $secret = (bool) ($f['secret'] ?? false);
            $credentials[$f['key']] = [
                'set' => filled($config[$f['key']] ?? null),
                'value' => $secret ? null : ($config[$f['key']] ?? null),
                'secret' => $secret,
            ];
        }
        $required = array_filter($schema['fields'] ?? [], fn ($f) => $f['required'] ?? false);
        $configured = !array_filter($required, fn ($f) => empty($config[$f['key']]));

        return [
            'id' => $channel->id, 'type' => $channel->type, 'name' => $channel->name,
            'external_account_id' => $channel->external_account_id,
            'is_active' => (bool) $channel->is_active,
            'configured' => $configured,
            'conversations_count' => (int) ($channel->conversations_count ?? 0),
            'credentials' => $credentials,
            'webhook' => $schema['webhook'] ?? null,
        ];
    }

    private function buildConfig(array $schema, array $input, array $existing): array
    {
        $config = $existing;
        foreach ($schema['fields'] as $f) {
            $key = $f['key'];
            if (!array_key_exists($key, $input)) continue;
            $value = is_string($input[$key]) ? trim($input[$key]) : $input[$key];
            // A blank secret means "keep the stored one"; blank non-secret clears the field.
            if (($f['secret'] ?? false) && ($value === '' || $value === null)) continue;
            $config[$key] = $value;
        }
        return $config;
    }

    private function accountId(array $schema, array $config): ?string
    {
        $field = $schema['account_field'] ?? null;
        return $field ? ($config[$field] ?? null) : null;
    }

    private function schemaFor(string $type, bool $strict = true): array
    {
        $schemas = $this->schemas();
        if (!isset($schemas[$type])) {
            if ($strict) throw new RuntimeException('Unknown channel type: '.$type);
            return ['fields' => [], 'webhook' => null];
        }
        return $schemas[$type];
    }
}
