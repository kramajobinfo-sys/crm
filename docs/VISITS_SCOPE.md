# Visits — website visitor tracking (Zoho gap #12)

**Status:** decisions settled, **being built** (module `visits`).

Last item on the Zoho menu-parity queue. It was flagged "heaviest and most externally
dependent — build last", but the dependency is different in kind from the two items that were
skipped: SalesInbox needs a live IMAP server and payments need a real Stripe key, whereas here
**both halves are ours** — the tracker script and the ingest endpoint. It is embedded in an
external site, not dependent on an external service, so it is fully verifiable here.

## What it is
A small JS snippet a tenant pastes into their marketing site. It records page views against an
anonymous visitor, and — once that visitor identifies themselves (typically by submitting a
form) — attaches the history to an existing Lead or Customer, so a salesperson can see what a
prospect read before they got in touch.

## The dominant constraint: this is the app's only public write endpoint

Everything else that writes is behind `jwt.auth` or the portal guard. The ingest **cannot** be:
a beacon fires from a random browser on someone else's domain. So every byte it receives is
attacker-controlled and it is designed accordingly.

### Decisions that follow from that

**1. Beacon is a "simple request"; the browser never reads the response.**
`navigator.sendBeacon` with a `text/plain` body (JSON inside). No preflight, so
`config/cors.php` — which only allows localhost/LAN origins and would otherwise block every
real customer domain — is never consulted. The endpoint always answers **204 with no body**.

**2. The visitor id is generated client-side**, `crypto.randomUUID()` in `localStorage`. The
tracker therefore never needs to *read* a response, which is what makes (1) possible. It also
means a visitor id is not a secret and is never treated as one.

**3. An unknown, inactive or malformed site key returns 204 like everything else.** No 401, no
404, no error body — otherwise the endpoint is a tenant-enumeration oracle. Nothing is written.
A separate *authenticated* endpoint exists for a tenant to test its own key.

**4. `identify` links; it never creates.** A submitted email is matched against existing Leads
then Customers *within the key's company only*. If nothing matches, the visitor stays anonymous.
Allowing creation would turn a public endpoint into a lead-spam faucet.

**Accepted, documented abuse:** anyone who reads the snippet can call `identify` with a known
email and attach their own browsing to that Lead's history — i.e. inject noise into one record.
Every client-side analytics product shares this; the mitigation (server-side confirmed identity)
would require the tenant's site to authenticate, which defeats a paste-in snippet. Impact is
bounded to noise on a record, never data disclosure: the endpoint returns nothing.

Three cheap bounds keep that from being unlimited, because `identify` is otherwise an
email-existence oracle (submit an address, observe whether a link appears):
- **A far tighter rate limit than page views.** The two have completely different legitimate
  frequencies — one fires per form submit, the other per page — so they get separate limiters.
- **An already-identified visitor is never re-pointed** to a different lead. First identification
  wins; a later contradicting one is ignored.
- **A cap on how many distinct visitors may attach to one lead** in a window, so a scripted loop
  cannot bury a real prospect's history under fake visitors.

**4b. `visitor_uid` is hostile input, not merely "not secret".** It is validated as a UUID
(fixed shape and length) and anything else is dropped with 204 — otherwise
`unique(company_id, visitor_uid)` becomes an index over arbitrary attacker-chosen strings, and a
10KB uid is a trivial write amplifier. Note the residual, which cannot be fixed client-side:
**someone who obtains another visitor's uid can attach page views to that visitor's record**
(e.g. off a shared machine). Same bounded-noise class as the `identify` case above.

**5. Nothing from the browser is trusted as-is.** URLs must parse as `http(s)`; every string is
length-capped and truncated on write, not rejected (a truncated page view is better than a lost
one); the payload itself is size-capped. Stored values are only ever rendered as text — no
`v-html` anywhere in the Visits UI.

**6. IP is stored hashed, never raw.** A salted SHA-256 (`APP_KEY` as the salt) is enough to
group a visitor's requests and spot abuse, without the CRM accumulating personal data a tenant
did not ask for.

**7. Rate limited per site key + IP hash.** Bounded so one noisy site cannot fill a tenant's
table or the disk.

## Data model
- **`companies.visits_site_key`** — one public key per tenant (unique, regenerable). A column
  rather than a table: one key per tenant is the whole MVP, and this matches the existing
  `companies.subdomain` precedent.
- **`web_visitors`** — `company_id`, `visitor_uid` (client UUID, unique per company),
  `lead_id` / `customer_id` (nullable — set by `identify`), first/last seen, first referrer and
  landing URL, `page_view_count`, `ip_hash`, `user_agent`.
- **`web_page_views`** — `company_id`, `visitor_id`, `url`, `path`, `title`, `referrer`,
  `occurred_at`.

Sessions are deliberately **not** modelled: "what did this prospect read" needs visitors and
page views, and a session table would be a third concept to keep consistent for no MVP value.

## How the tracker is delivered
**Served by the API**, not pasted inline: the tenant embeds one
`<script src=".../api/v1/visits/t.js" data-key="SITE_KEY" async>`. A bug in the tracker is then
fixed centrally instead of living forever in every customer's HTML. A classic `<script src>` is
not a CORS request, so this works cross-origin with no config change. The response is cacheable
and contains no tenant data — the key comes from the embedding page's `data-key`, so one cached
script serves every tenant.

## Retention
Page views grow without bound, which the cross-module audit repeatedly found to be this
codebase's most common latent problem. A `visits:prune` command deletes page views (and the
anonymous visitors left with none) older than `VISITS_RETENTION_DAYS` (default 180), scheduled
daily alongside the existing `audit:purge`.

It runs on the scheduler, i.e. **unauthenticated**, so it obeys the three traps this codebase has
already been bitten by: no `auth()` anywhere, `company_id` never inferred, and
`withoutGlobalScope('company')` — *never* the plural `withoutGlobalScopes()`, which also strips
`SoftDeletingScope`. Deletion is chunked; an unbounded `delete()` over 180 days of beacons is
exactly the memory profile the audit kept flagging.

## Plan tier and permissions
`feature:visits`, **Professional+** — a marketing-intelligence feature, not core CRM.
Permissions: `visits.view` (see visitors and page views) and `visits.manage` (reveal/regenerate
the site key). Wired in all three `TenantProvisioner` places.

## Not built (named so the gap stays visible)
- **Sessions / time-on-page / scroll depth** — needs an unload beacon and a session concept.
- **Company-level (reverse-IP) identification** — needs a third-party IP-intelligence service.
- **Real-time "who is on the site now"** — needs websockets; the inbox already has that
  machinery but this MVP is read-after-the-fact.
- **Visit-triggered workflows** (`visit.identified` as a workflow event) — additive once the
  data exists; deliberately out of the first pass so the ingest stays as simple as possible.
- **A cookie/consent banner.** The tenant is responsible for consent on their own site; the
  snippet does not manage it. Stated so nobody assumes Krama handles it.
