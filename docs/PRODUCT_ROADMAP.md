# Krama CRM — Go-Live and Product Roadmap

**Recorded:** 2026-09-03  
**Purpose:** preserve the agreed Priority 0–4 work so Krama can launch on real domains first
and continue product development afterward without depending on chat history.

Status legend: `TODO` · `IN PROGRESS` · `DONE` · `PARKED`

> A real-domain launch is not permission to postpone the entire Priority 0 group. Complete the
> minimum launch gate below before storing real customer data. The remaining roadmap can follow
> after the first controlled release.

## Release 1 — Minimum real-domain launch gate

| Work item | Status | Completion evidence |
|---|---|---|
| Production Docker/Compose configuration | DONE | Immutable PHP and compiled Vue/nginx images built and passed an isolated first-boot database restore and smoke test on 2026-09-03. |
| Domain, DNS and TLS | TODO | Staff and portal domains resolve correctly, HTTPS is forced, and certificate renewal is tested. |
| Production environment and secrets | TODO | `APP_ENV=production`, debug disabled, unique application/JWT/database/Redis secrets, correct trusted hosts/CORS/cookie settings, and no secrets committed. |
| Network hardening | TODO | Only required HTTP/HTTPS ports are public; MySQL and Redis are private to the Docker network. |
| Authentication/session smoke test | TODO | Login, token refresh, logout, expired-session recovery and permission refresh work without a stuck page. |
| Private customer files | TODO | Lead, Deal, Chat and Email attachments require authenticated, tenant-authorized download. |
| Initial backup and restore proof | TODO | An encrypted database/file backup is stored off-server and restored successfully into a clean test environment. |
| Health and failure visibility | TODO | `/up` and `/ready` are monitored; application errors, failed queue jobs and disk/database capacity produce an alert. |
| Safe migration and rollback procedure | TODO | Deployment uses `migrate --force` (never `migrate:fresh`), preserves uploaded files, and has a documented rollback rehearsal. |
| End-to-end release test | TODO | Staff CRM workflow, customer portal, email, quotation-to-invoice and one tenant-isolation check pass on the production domain. |

## Priority 0 — Production stabilization after launch

**Goal:** make releases and daily operation predictable.

| Planned capability | Status |
|---|---|
| Automated CI/CD with versioned releases and minimal-downtime deployment | TODO |
| Centralized application error and performance monitoring | TODO |
| Queue, scheduler, email and integration-health dashboards | TODO |
| Automated encrypted off-server backups with retention policy | TODO |
| Scheduled restore drills and disaster-recovery runbook | TODO |
| Security review: rate limits, headers, audit retention, dependency scanning and secret rotation | TODO |
| Load/performance tests for login, lists, Deal editing, dashboards and reports | TODO |

## Priority 1 — Customer 360 and real omnichannel

**Goal:** give users one reliable history of every customer interaction.

| Planned capability | Status |
|---|---|
| Unified customer timeline across Leads, Contacts, Accounts, Deals, email, social conversations, tickets, sales documents, portal and projects | TODO |
| Stronger identity resolution, duplicate review and merge suggestions | TODO |
| Production provider adapters for approved social/messaging channels | TODO |
| Verified inbound webhooks with signature validation and idempotency | TODO |
| Outbound delivery worker with retries, dead-letter handling and delivery/read/failure statuses | TODO |
| Routing, ownership, SLA and escalation rules for conversations | TODO |
| Communication consent, subscription preferences and opt-out history | TODO |
| Telephony/call integration evaluation and first supported provider | TODO |

## Priority 2 — Admin customization platform

**Goal:** configure Krama for different customers and industries without custom code for every
change.

| Planned capability | Status |
|---|---|
| Tenant-defined custom fields with type, validation and permissions | TODO |
| Configurable record layouts, sections and conditional visibility | TODO |
| Formula, calculated and roll-up fields | TODO |
| Configurable validation and required-field rules | TODO |
| Visual workflow/journey builder with triggers, branches, delays and approvals | TODO |
| Custom modules/objects and relationships | TODO |
| Configuration export, version history and rollback | TODO |
| Tenant test/sandbox strategy for configuration changes | TODO |

## Priority 3 — Sales productivity, marketing and analytics

**Goal:** help teams create and convert demand, then measure results.

| Planned capability | Status |
|---|---|
| Sales sequences/cadences, templates and follow-up automation | TODO |
| Meeting scheduler and calendar synchronization | TODO |
| Email engagement tracking with privacy controls | TODO |
| Lead routing, scoring, territory, quota and playbook improvements | TODO |
| Marketing forms, landing pages, segmentation and nurture journeys | TODO |
| Campaign attribution, funnel and cohort analytics | TODO |
| Expanded cross-module report datasets and scheduled delivery | TODO |
| Forecast explanations, targets and management analytics | TODO |
| Governed tenant-aware AI: summarization, next action, scoring, anomaly detection and audit/cost controls | TODO |

## Priority 4 — Global, mobile, ecosystem and enterprise readiness

**Goal:** support larger and geographically distributed customers.

| Planned capability | Status |
|---|---|
| Mobile-first PWA/native strategy, push notifications and selected offline workflows | TODO |
| SAML/OIDC single sign-on and SCIM provisioning | TODO |
| Field-level security, record-sharing rules and stronger delegated administration | TODO |
| Multi-currency improvements with dated exchange rates and currency-safe reporting | TODO |
| Locale, timezone, regional tax and document-format improvements | TODO |
| Consent, retention, export/deletion and data-residency controls | TODO |
| Public API documentation, OAuth applications, webhooks and SDK starter kits | TODO |
| Versioned connector/plugin framework and integration marketplace foundations | TODO |
| Dynamics 365 Business Central live-tenant validation, incremental sync, conflict handling and monitored retries | TODO |
| Governed Business Central push sync after pull/reconciliation is production-proven | TODO |

## Product positioning guardrail

Krama should not clone every feature in Salesforce, Zoho CRM, Bitrix24 and HubSpot. The intended
position is:

> A self-hosted, configurable business operations platform connecting CRM, projects,
> inventory, customer service, social communication and Microsoft Dynamics 365 Business
> Central.

Prioritize a roadmap item when it improves reliability, customer workflow continuity,
configuration reuse, Dynamics integration, data control or regional deployment. Treat unrelated
website/CMS, generic office-suite and broad collaboration features as optional unless validated
customer demand makes them necessary.

## Roadmap operating rules

1. `BUILD_PROGRESS.md` remains the implementation tracker; this document records product order
   and scope.
2. Move only one major capability group into `IN PROGRESS` at a time unless work is explicitly
   coordinated.
3. Do not mark an item `DONE` from code inspection alone. Record API, browser, security and
   tenant-isolation verification where applicable.
4. New customer commitments must identify the roadmap item, acceptance criteria, owner and
   target release before development begins.
5. Review priorities after launch using support incidents, performance data and customer
   win/loss feedback rather than competitor feature counts alone.
