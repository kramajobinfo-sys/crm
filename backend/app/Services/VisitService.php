<?php
namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\WebPageView;
use App\Models\WebVisitor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Website visitor tracking. See docs/VISITS_SCOPE.md.
 *
 * The ingest half of this class is reached from the app's ONLY public write endpoint, so it
 * treats every argument as hostile: the site key, the visitor uid, the URLs, the email. It
 * runs with no authenticated user, so company_id is always taken from the resolved site key
 * and never from auth().
 */
class VisitService
{
    /**
     * Truncation caps — the ingest truncates rather than rejects; a clipped view beats a lost
     * one. Each MUST match its column width exactly: clipping to more than the column holds
     * turns a long URL into a DB error, which on a public endpoint surfaces as a 500 that the
     * "always 204" contract forbids. (That is precisely what a 2 KB URL did before
     * MAX_LANDING existed and first_landing_url was clipped to MAX_URL.)
     */
    private const MAX_URL      = 1024;   // web_page_views.url
    private const MAX_LANDING  = 512;    // web_visitors.first_landing_url — NOT the same width
    private const MAX_PATH     = 512;
    private const MAX_TITLE    = 255;
    private const MAX_REFERRER = 512;
    private const MAX_UA       = 512;

    /** A single lead can only absorb this many distinct visitors per window (anti-burial). */
    private const IDENTIFY_VISITORS_PER_LEAD = 25;
    private const IDENTIFY_WINDOW_HOURS      = 24;

    /* ------------------------------------------------------------------ ingest */

    /**
     * Resolve a public site key to its company. Returns null for unknown, blank or inactive —
     * the caller answers 204 either way, so this is never an enumeration oracle.
     */
    public function companyForKey(?string $key): ?Company
    {
        $key = trim((string) $key);
        if ($key === '' || strlen($key) > 40) return null;

        return Company::withoutGlobalScopes()
            ->where('visits_site_key', $key)->where('is_active', true)->first();
    }

    /**
     * Record one page view. Returns false when the payload is unusable — the caller still
     * answers 204.
     */
    public function recordPageView(Company $company, array $payload, ?string $ip, ?string $userAgent): bool
    {
        $uid = $this->validUid($payload['uid'] ?? null);
        $url = $this->validUrl($payload['url'] ?? null);
        if (!$uid || !$url) return false;

        return DB::transaction(function () use ($company, $payload, $ip, $userAgent, $uid, $url) {
            $visitor = $this->visitorFor($company, $uid, $url, $payload, $ip, $userAgent);

            WebPageView::create([
                'company_id'  => $company->id,
                'visitor_id'  => $visitor->id,
                'url'         => $url,
                'path'        => $this->clip($this->pathOf($url), self::MAX_PATH),
                'title'       => $this->clip($payload['title'] ?? null, self::MAX_TITLE),
                'referrer'    => $this->clip($this->validUrl($payload['referrer'] ?? null), self::MAX_REFERRER),
                // Client clocks are not trusted for ordering — the server stamps it.
                'occurred_at' => now(),
            ]);

            $visitor->forceFill(['last_seen_at' => now()])->save();
            $visitor->increment('page_view_count');

            return true;
        });
    }

    /**
     * Attach a visitor to an existing Lead or Customer by email.
     *
     * NEVER creates either: this is a public endpoint and creation would make it a lead-spam
     * faucet. Never re-points an already-identified visitor either, so a later contradicting
     * claim cannot steal a visitor from the record it first matched.
     */
    public function identify(Company $company, array $payload, ?string $ip, ?string $userAgent): bool
    {
        $uid   = $this->validUid($payload['uid'] ?? null);
        $email = filter_var(trim((string) ($payload['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$uid || !$email || strlen($email) > 191) return false;

        $visitor = WebVisitor::withoutGlobalScopes()
            ->where('company_id', $company->id)->where('visitor_uid', $uid)->first();

        // Nothing to identify, or already identified — first identification wins.
        if (!$visitor || $visitor->isIdentified()) return false;

        $lead = Lead::withoutGlobalScope('company')
            ->where('company_id', $company->id)->where('email', $email)->first();

        $customer = $lead ? null : Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)->where('email', $email)->first();

        if (!$lead && !$customer) return false;   // unknown address: stays anonymous

        // Anti-burial: a scripted loop must not be able to attach hundreds of fake visitors to
        // one real prospect and drown their genuine history.
        if ($this->identifyCapReached($company, $lead?->id, $customer?->id)) return false;

        $visitor->forceFill([
            'lead_id'       => $lead?->id,
            'customer_id'   => $customer?->id,
            'identified_at' => now(),
            'user_agent'    => $visitor->user_agent ?: $this->clip($userAgent, self::MAX_UA),
            'ip_hash'       => $visitor->ip_hash ?: $this->hashIp($ip),
        ])->save();

        return true;
    }

    private function identifyCapReached(Company $company, ?int $leadId, ?int $customerId): bool
    {
        $since = now()->subHours(self::IDENTIFY_WINDOW_HOURS);

        return WebVisitor::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('identified_at', '>=', $since)
            ->when($leadId, fn ($q) => $q->where('lead_id', $leadId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->count() >= self::IDENTIFY_VISITORS_PER_LEAD;
    }

    private function visitorFor(Company $company, string $uid, string $url, array $payload, ?string $ip, ?string $ua): WebVisitor
    {
        $visitor = WebVisitor::withoutGlobalScopes()
            ->where('company_id', $company->id)->where('visitor_uid', $uid)->first();

        if ($visitor) return $visitor;

        return WebVisitor::create([
            'company_id'        => $company->id,
            'visitor_uid'       => $uid,
            'first_landing_url' => $this->clip($url, self::MAX_LANDING),
            'first_referrer'    => $this->clip($this->validUrl($payload['referrer'] ?? null), self::MAX_REFERRER),
            'user_agent'        => $this->clip($ua, self::MAX_UA),
            'ip_hash'           => $this->hashIp($ip),
            'page_view_count'   => 0,
            'first_seen_at'     => now(),
            'last_seen_at'      => now(),
        ]);
    }

    /* ------------------------------------------------------------- validation */

    /** Client uids must be UUIDs — otherwise the unique index is over arbitrary input. */
    private function validUid($value): ?string
    {
        $uid = strtolower(trim((string) $value));
        return Str::isUuid($uid) ? $uid : null;
    }

    /** http(s) only, and length-capped. Anything else (javascript:, data:, file:) is dropped. */
    private function validUrl($value): ?string
    {
        $url = trim((string) $value);
        if ($url === '' || strlen($url) > self::MAX_URL) {
            $url = mb_substr($url, 0, self::MAX_URL);
        }
        if ($url === '') return null;

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) return null;
        if (!parse_url($url, PHP_URL_HOST)) return null;

        return $url;
    }

    private function pathOf(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);
        return $query ? $path.'?'.$query : $path;
    }

    /** Salted with APP_KEY so the CRM never stores a raw address. */
    private function hashIp(?string $ip): ?string
    {
        return $ip ? hash('sha256', config('app.key').'|'.$ip) : null;
    }

    private function clip($value, int $max): ?string
    {
        $s = trim((string) ($value ?? ''));
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    /* ----------------------------------------------------------------- reading */

    public function paginateVisitors(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return WebVisitor::query()
            ->with(['lead:id,lead_no,name,email', 'customer:id,customer_no,name,email'])
            ->when(($f['status'] ?? null) === 'identified', fn ($q) => $q->identified())
            ->when(($f['status'] ?? null) === 'anonymous',
                fn ($q) => $q->whereNull('lead_id')->whereNull('customer_id'))
            ->when(!empty($f['lead_id']), fn ($q) => $q->where('lead_id', $f['lead_id']))
            ->when(!empty($f['q']), fn ($q) => $q->where(fn ($w) =>
                $w->where('first_landing_url', 'like', '%'.$f['q'].'%')
                  ->orWhere('visitor_uid', 'like', '%'.$f['q'].'%')))
            ->orderByDesc('last_seen_at')
            ->paginate($perPage);
    }

    public function findVisitor(int $id): WebVisitor
    {
        return WebVisitor::with([
            'lead:id,lead_no,name,email', 'customer:id,customer_no,name,email',
            // Bounded: a visitor can accumulate thousands of views.
            'pageViews' => fn ($q) => $q->orderByDesc('occurred_at')->limit(200),
        ])->findOrFail($id);
    }

    /** Page views for one lead, across every visitor that identified as them. */
    public function pageViewsForLead(int $leadId, int $limit = 100): \Illuminate\Support\Collection
    {
        return WebPageView::query()
            ->whereIn('visitor_id', WebVisitor::query()->where('lead_id', $leadId)->select('id'))
            ->orderByDesc('occurred_at')->limit($limit)
            ->get(['id', 'url', 'path', 'title', 'referrer', 'occurred_at']);
    }

    public function stats(): array
    {
        return [
            'visitors_total'   => WebVisitor::query()->count(),
            'identified'       => WebVisitor::query()->identified()->count(),
            'page_views_total' => WebPageView::query()->count(),
            'active_7d'        => WebVisitor::query()->where('last_seen_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /* -------------------------------------------------------------- site key */

    /** Generate or rotate the tenant's public key. Rotating silently stops old snippets. */
    public function rotateSiteKey(Company $company): string
    {
        $key = 'krv_'.bin2hex(random_bytes(16));
        $company->forceFill(['visits_site_key' => $key])->save();
        return $key;
    }

    /* ---------------------------------------------------------------- pruning */

    /**
     * Delete page views older than $days, then the anonymous visitors left with none.
     *
     * Runs on the scheduler (unauthenticated): no auth() anywhere, company_id never inferred,
     * and withoutGlobalScope('company') — never the plural withoutGlobalScopes(), which also
     * strips SoftDeletingScope. Chunked so 180 days of beacons cannot exhaust memory.
     *
     * @return array{page_views:int,visitors:int}
     */
    public function prune(int $days, int $chunk = 1000): array
    {
        $cutoff = Carbon::now()->subDays($days);
        $views = 0;

        do {
            $deleted = WebPageView::withoutGlobalScope('company')
                ->where('occurred_at', '<', $cutoff)->limit($chunk)->delete();
            $views += $deleted;
        } while ($deleted === $chunk);

        // Identified visitors are kept even with no views left: the link to a lead is the
        // valuable part and outlives the raw page-view history.
        $visitors = 0;
        do {
            $ids = WebVisitor::withoutGlobalScope('company')
                ->whereNull('lead_id')->whereNull('customer_id')
                ->where('last_seen_at', '<', $cutoff)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('web_page_views')
                    ->whereColumn('web_page_views.visitor_id', 'web_visitors.id'))
                ->limit($chunk)->pluck('id');

            if ($ids->isEmpty()) break;
            $visitors += WebVisitor::withoutGlobalScope('company')->whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunk);

        return ['page_views' => $views, 'visitors' => $visitors];
    }
}
