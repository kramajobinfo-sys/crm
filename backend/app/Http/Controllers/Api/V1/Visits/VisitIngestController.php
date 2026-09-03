<?php
namespace App\Http\Controllers\Api\V1\Visits;

use App\Http\Controllers\Controller;
use App\Services\VisitService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The app's ONLY public write endpoint. See docs/VISITS_SCOPE.md.
 *
 * Everything here answers **204 with no body**, always — success, unknown site key, malformed
 * payload, rejected URL. Any other answer would turn this into a tenant/lead enumeration
 * oracle, and a beacon has nothing to do with a response anyway.
 *
 * The tracker sends `text/plain` via navigator.sendBeacon, which is a CORS *simple request*:
 * no preflight, so config/cors.php (localhost/LAN origins only) is never consulted and real
 * customer domains work without loosening it.
 */
class VisitIngestController extends Controller
{
    public function __construct(private readonly VisitService $visits) {}

    /** Max beacon body. Anything larger is dropped unread. */
    private const MAX_BODY_BYTES = 8192;

    public function collect(Request $request): SymfonyResponse
    {
        // Defence in depth for the "always 204" contract. Validation already rejects hostile
        // input, but anything unforeseen — a column narrower than its cap, a deadlock — would
        // otherwise escape as a 500 from a PUBLIC endpoint, which both breaks the contract and
        // tells an attacker their input reached the database. Logged, never surfaced.
        try {
            $this->ingest($request);
        } catch (\Throwable $e) {
            Log::warning('Visit ingest failed', ['error' => $e->getMessage()]);
        }

        return $this->noContent();
    }

    private function ingest(Request $request): void
    {
        $payload = $this->payload($request);
        $company = $this->visits->companyForKey($payload['key'] ?? null);

        if ($company) {
            $event = (string) ($payload['event'] ?? 'pageview');

            if ($event === 'identify') {
                // Its own, much tighter limiter: identify fires once per form submit, a page
                // view once per page. Sharing one budget would either throttle real browsing
                // or leave identify wide open as an email-existence probe. Keyed by site key
                // + hashed IP so one abusive source cannot spend another tenant's budget.
                $limiterKey = 'visits:id:'.$company->id.':'.sha1((string) $request->ip());
                $perMinute = (int) config('visits.rate_limit.identify_per_minute');

                if (!RateLimiter::tooManyAttempts($limiterKey, $perMinute)) {
                    RateLimiter::hit($limiterKey, 60);
                    $this->visits->identify($company, $payload, $request->ip(), $request->userAgent());
                }
            } else {
                $this->visits->recordPageView($company, $payload, $request->ip(), $request->userAgent());
            }
        }
        // Nothing is returned: collect() answers 204 for every outcome — success, unknown key,
        // throttled identify, malformed payload. A different answer would tell an attacker
        // which of those happened.
    }

    /**
     * sendBeacon posts text/plain, so Laravel does not parse it as JSON. Read the raw body
     * and decode defensively — a malformed body is simply an empty payload, never an error.
     */
    private function payload(Request $request): array
    {
        $raw = $request->getContent();
        if ($raw === '' || strlen($raw) > self::MAX_BODY_BYTES) {
            // A form-encoded fallback (older browsers without sendBeacon) still works.
            return $request->all() ?: [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ($request->all() ?: []);
    }

    /** 204 with no body and no cache, for every outcome. */
    private function noContent(): SymfonyResponse
    {
        return response('', Response::HTTP_NO_CONTENT)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * The tracker itself. Served by the API so a bug is fixed centrally instead of living in
     * every customer's HTML. Contains no tenant data — the key comes from the embedding
     * page's data-key — so one cached copy serves every tenant.
     */
    public function script(Request $request): SymfonyResponse
    {
        $endpoint = url('/api/v1/visits/collect');

        $js = <<<JS
(function () {
  'use strict';
  var s = document.currentScript;
  if (!s) return;
  var key = s.getAttribute('data-key');
  if (!key) return;

  var ENDPOINT = '{$endpoint}';
  var STORE = 'krama_vid';

  // The visitor id is generated and kept client-side, which is what lets the beacon be a
  // simple request: the tracker never needs to READ a response, so no CORS, no preflight.
  function uid() {
    try {
      var v = localStorage.getItem(STORE);
      if (v) return v;
      v = (crypto && crypto.randomUUID) ? crypto.randomUUID()
        : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
          });
      localStorage.setItem(STORE, v);
      return v;
    } catch (e) { return null; }   // private mode / storage blocked: stay silent
  }

  function send(body) {
    var id = uid();
    if (!id) return;
    body.key = key;
    body.uid = id;
    var json = JSON.stringify(body);
    try {
      // text/plain keeps this a CORS simple request (no preflight).
      if (navigator.sendBeacon) {
        navigator.sendBeacon(ENDPOINT, new Blob([json], { type: 'text/plain;charset=UTF-8' }));
        return;
      }
      var x = new XMLHttpRequest();
      x.open('POST', ENDPOINT, true);
      x.setRequestHeader('Content-Type', 'text/plain;charset=UTF-8');
      x.send(json);
    } catch (e) { /* never break the host page */ }
  }

  function pageview() {
    send({ event: 'pageview', url: location.href, referrer: document.referrer || '', title: document.title || '' });
  }

  window.krama = window.krama || {};
  // Call krama.identify('a@b.com') from a form submit handler to attach this browser's
  // history to an EXISTING lead or customer. It never creates one.
  window.krama.identify = function (email) {
    if (email) send({ event: 'identify', email: String(email) });
  };
  window.krama.pageview = pageview;

  pageview();

  // SPA route changes: history.pushState does not fire a navigation event.
  var push = history.pushState;
  if (push) {
    history.pushState = function () { push.apply(this, arguments); setTimeout(pageview, 0); };
    window.addEventListener('popstate', function () { setTimeout(pageview, 0); });
  }
})();
JS;

        return response($js, 200)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
