# Knowledge Base / Solutions (Zoho gap #8) — scope

**Status:** scoping, decisions settled. Normal module build (see the checklist in
[`BUILD_PROGRESS.md`](BUILD_PROGRESS.md)). Zoho calls this *Solutions*; **the code name is `kb`
everywhere** (permission `kb.*`, route prefix `/kb`, locale namespace `kb.*`, nav label
"Knowledge Base"). The [`ZOHO_GAP_ANALYSIS.md`](ZOHO_GAP_ANALYSIS.md) row notes this alias.

**Decisions locked (product owner, 2026-08-31):**
- **Plain-text bodies**, rendered with CSS `white-space: pre-wrap`. **No `v-html`, no HTML sink.**
  A KB article renders inside the customer portal's own origin, where the portal login token lives
  in `localStorage`; plain text removes the token-theft XSS surface entirely. (Rich HTML was the
  alternative — it would have required server-side sanitization on write as a hard line item.)
- **Staff + customer portal.** Two independent flags: `status` (draft|published) and `visibility`
  (internal|public). The portal shows only articles that are **both** `published` **and** `public`.
- **Professional+** plan tier (`feature:kb`), matching `tickets`, the module it pairs with.

## Security surface — the double gate (get it right at the query, not the Resource)
- Portal queries filter `withoutGlobalScope('company')->where('company_id', $contact->company_id)
  ->where('status','published')->where('visibility','public')` — and **no `customer_id` filter**,
  because articles are company-wide knowledge, not per-customer records. This is a **deliberate
  departure** from the other three portal controllers (which all filter `customer_id`); documented
  in the portal controller's docblock so it isn't "fixed" into a bug.
- `show` carries the **same three predicates** as the list. The IDOR to test here is a portal
  contact fetching an **internal** or **draft** article by id → must be **404**, not 200.
- Portal uses `App\Http\Resources\Portal\*` — never the staff Resource (which exposes `visibility`,
  author, internal state).

## Data model (delta; next migration after 000034)
- **kb_categories** — company_id, name, code, is_active. `unique(company_id, code)`.
- **kb_articles** — company_id, category_id (nullable, nullOnDelete), title, slug, body (text,
  plain), excerpt (nullable), status (draft|published, default draft), visibility
  (internal|public, default internal), view_count (unsigned, default 0), author_id (users,
  nullOnDelete), published_at (nullable), timestamps, softDeletes.
  `unique(company_id, slug)`; index (company_id, status, visibility), (company_id, category_id).
- **slug uniqueness is per-company** — FormRequest uses
  `Rule::unique('kb_articles','slug')->where('company_id',$companyId)` (+ `->ignore($id)` on
  update). Portal addresses articles **by id** (consistent with the other portal controllers);
  slug is stored for staff display / future public URLs.
- **view_count** increments via atomic `increment('view_count')` on the portal `show` — never
  read-modify-write (avoids the check-then-act race family). Note: a write on a GET.

## Endpoints
- Staff (`feature:kb`): `GET/POST /kb/articles`, `GET /kb/articles/meta`,
  `GET/PUT/DELETE /kb/articles/{id}`, `POST /kb/categories` — gated `kb.view/create/update/delete`.
- Portal (`portal.auth`): `GET /portal/kb/articles`, `GET /portal/kb/articles/{id}`
  (published+public only; id increments view_count).

## RBAC (three TenantProvisioner places)
- `MODULES`: `kb => [view, create, update, delete]`.
- `roleDefinitions`: Customer Service full CRUD (they author solutions); `kb.view` to Sales
  Manager + Sales Staff (reference solutions with customers). Owner/CEO full via existing rules.
- `planDefinitions`: add `kb` to `professionalModules`.

## Explicitly cut (not gaps to close now)
- **Helpdesk linkage** (attach a solution to a ticket / suggested articles on a case). The gap
  analysis says KB "pairs with Helpdesk" — this MVP delivers a standalone KB + portal; ticket
  linkage is a named follow-up, not built here.
- **Fully public, unauthenticated help center** (anonymous browsing, SEO, caching) — a different
  project. KB is exposed to **authenticated** portal contacts only.
