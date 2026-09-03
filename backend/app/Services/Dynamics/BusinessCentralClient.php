<?php
namespace App\Services\Dynamics;

use App\Models\BcConnection;
use App\Services\Dynamics\Exceptions\BcAuthException;
use App\Services\Dynamics\Exceptions\BcRequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin OAuth2 + OData client for Dynamics 365 Business Central.
 *
 * Built on Illuminate\Http (Guzzle) — no new Composer dependency.
 *
 * IMPORTANT: no request in this class has ever completed successfully against a
 * live BC tenant. Endpoint shapes follow Microsoft's documented API, but region
 * and environment differences are exactly what could not be verified here.
 */
class BusinessCentralClient
{
    public function __construct(private readonly BcConnection $connection) {}

    /* ---------------------------------------------------------------- auth */

    /**
     * Client-credentials token, cached below its real lifetime so it cannot
     * expire mid-request.
     *
     * @throws BcAuthException
     */
    public function accessToken(bool $forceRefresh = false): string
    {
        $key = "bc:token:conn{$this->connection->id}";
        if ($forceRefresh) Cache::forget($key);

        $cached = Cache::get($key);
        if ($cached) return $cached;

        $creds = $this->connection->credentials();
        if (!$this->connection->isConfigured()) {
            throw new BcAuthException('Connection is missing tenant id, client id or client secret.');
        }

        $url = str_replace('{tenant}', $creds['tenant_id'], config('dynamics.token_url'));

        $response = $this->http(withToken: false)->asForm()->post($url, [
            'grant_type'    => 'client_credentials',
            'client_id'     => $creds['client_id'],
            'client_secret' => $creds['client_secret'],
            'scope'         => config('dynamics.scope'),
        ]);

        if ($response->failed()) {
            // Entra ID returns a structured error; surface it rather than a raw dump.
            $body  = $response->json() ?? [];
            $code  = $body['error'] ?? 'unknown_error';
            $desc  = $body['error_description'] ?? $response->body();
            throw new BcAuthException(trim($code.': '.strtok((string) $desc, "\r\n")), $response->status());
        }

        $token     = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        if (!$token) throw new BcAuthException('Token endpoint returned no access_token.');

        $ttl = max(60, $expiresIn - config('dynamics.token_cache_skew'));
        Cache::put($key, $token, $ttl);

        return $token;
    }

    /* ------------------------------------------------------------- requests */

    public function baseUrl(): string
    {
        $creds = $this->connection->credentials();
        $template = $this->connection->base_url ?: config('dynamics.base_url');
        return rtrim(str_replace(
            ['{tenant}', '{environment}'],
            [(string) $creds['tenant_id'], (string) $creds['environment']],
            $template
        ), '/');
    }

    /** Entity-set URL, scoped to the bound BC company when one is selected. */
    public function entityUrl(string $entity, ?string $id = null): string
    {
        $url = $this->baseUrl();
        if ($this->connection->bc_company_id) {
            $url .= "/companies({$this->connection->bc_company_id})";
        }
        $url .= '/'.ltrim($entity, '/');
        if ($id !== null) $url .= "({$id})";
        return $url;
    }

    /** @throws BcRequestException|BcAuthException */
    public function get(string $url, array $query = []): array
    {
        $request = $this->http();

        // An EMPTY query must not be forwarded. Laravel's get($url, $query) sets Guzzle's
        // `query` option whenever a second argument is present, and that option REPLACES the
        // URL's own query string — which silently stripped the $skiptoken out of every
        // @odata.nextLink and made paging refetch the first page forever.
        $response = $query === [] ? $request->get($url) : $request->get($url, $query);

        if ($response->failed()) $this->fail($response->status(), $response->json() ?? [], $response->body());
        return $response->json() ?? [];
    }

    /**
     * Follow @odata.nextLink until the entity set is exhausted.
     * Bounded so a pathological result set cannot loop forever.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllPages(string $url, array $query = [], int $maxPages = 100): array
    {
        $items = [];
        $page = 0;
        $next = $url;
        $params = $query + ['$top' => config('dynamics.page_size')];

        while ($next && $page < $maxPages) {
            // First request carries our query; every nextLink after it must be fetched with
            // NO query array. Passing even an empty array sets Guzzle's `query` option, which
            // REPLACES the URL's own query string — that silently stripped the $skiptoken out
            // of every nextLink, so paging refetched page 1 until $maxPages and imported only
            // the first page while making 100 requests to do it.
            $body = $page === 0 ? $this->get($next, $params) : $this->get($next);

            $items = array_merge($items, $body['value'] ?? []);

            $following = $body['@odata.nextLink'] ?? null;
            // A server echoing the same link would otherwise spin to the page cap.
            $next = ($following && $following !== $next) ? $following : null;
            $page++;
        }
        return $items;
    }

    /** @throws BcRequestException|BcAuthException */
    public function post(string $url, array $payload): array
    {
        $response = $this->http()->post($url, $payload);
        if ($response->failed()) $this->fail($response->status(), $response->json() ?? [], $response->body());
        return $response->json() ?? [];
    }

    /**
     * BC uses optimistic concurrency: PATCH requires If-Match with the row's ETag.
     * A 412 means someone else changed the record — that is a conflict to record,
     * not a failure to blindly retry.
     *
     * @throws BcRequestException|BcAuthException
     */
    public function patch(string $url, array $payload, string $etag): array
    {
        $response = $this->http()->withHeaders(['If-Match' => $etag])->patch($url, $payload);
        if ($response->status() === 412) {
            throw new BcRequestException('ETag mismatch — the record changed in Business Central.', 412, true);
        }
        if ($response->failed()) $this->fail($response->status(), $response->json() ?? [], $response->body());
        return $response->json() ?? [];
    }

    /** Cheapest call that proves auth + reachability + company visibility. */
    public function listCompanies(): array
    {
        $creds = $this->connection->credentials();
        $template = $this->connection->base_url ?: config('dynamics.base_url');
        $root = rtrim(str_replace(
            ['{tenant}', '{environment}'],
            [(string) $creds['tenant_id'], (string) $creds['environment']],
            $template
        ), '/');
        return $this->get($root.'/companies')['value'] ?? [];
    }

    /* -------------------------------------------------------------- private */

    private function http(bool $withToken = true): PendingRequest
    {
        $cfg = config('dynamics.http');
        $request = Http::timeout($cfg['timeout'])
            ->connectTimeout($cfg['connect_timeout'])
            // Bounded retry: an unreachable tenant must not pin a queue worker.
            ->retry($cfg['retries'], $cfg['retry_delay_ms'], throw: false)
            ->acceptJson();

        return $withToken ? $request->withToken($this->accessToken()) : $request;
    }

    /** @throws BcRequestException */
    private function fail(int $status, array $body, string $raw): void
    {
        $message = $body['error']['message'] ?? ($body['error_description'] ?? $raw);
        throw new BcRequestException(
            trim(strtok((string) $message, "\r\n")) ?: 'Business Central request failed.',
            $status
        );
    }
}
