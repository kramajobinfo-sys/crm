# Krama Enterprise CRM Platform

Modular, multi-company, multi-currency CRM built on Laravel 12 + Vue 3.
Modeled after Zoho CRM. For internal use at Krama.

## Tech stack
- Backend: Laravel 12, PHP 8.3, JWT, REST
- Frontend: Vue 3, Vite, Pinia, Tailwind CSS
- Database: MySQL 8, cache/queue: Redis 7
- Nginx, Docker, Ubuntu 22.04 LTS

## Repository layout
- backend/   Laravel 12 API
- frontend/  Vue 3 SPA
- docker/    nginx/php/mysql configs
- docs/      Architecture, ERD, install, deployment

## Modules
1. Dashboard ✅   2. Leads ✅   3. Accounts ✅   4. Contacts ✅   5. Deals ✅
6. Activities ✅  7. Email ✅    8. Sales ✅       9. Purchase ✅
10. Inventory ✅ 11. Helpdesk ✅ 12. Marketing ✅  13. HR ✅
14. Reports ✅   15. Workflow ✅ 16. AI Assistant ✅
(Settings UI ✅ — admin surface for company/users/roles/branches/departments)
17. Social/Chat Inbox ✅ (WhatsApp · Messenger · Instagram · TikTok · Telegram · SMS · Web chat — multi-account, media)
18. Dynamics 365 Business Central 🔧 (pull sync for companies, customers, items and
    invoice reconciliation — **still never run against a live tenant**; verified against a
    local OData stub, see `docs/DYNAMICS_BC_SYNC_SCOPE.md`)

Plus, from the Zoho gap-closure work: Price Books ✅ · Knowledge Base ✅ · Documents ✅ ·
Customer Portal ✅ · E-signature ✅ · **Customer Credits ✅** (overpayments and reduced
invoices become credits applied to future invoices, instead of the excess silently vanishing)

## Quick start
```
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
docker compose up -d
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan jwt:secret
docker compose exec backend php artisan migrate --seed
```
App: http://localhost:8081 · API: http://localhost:8000/api/v1

## Verification

The backend test suite uses an isolated in-memory SQLite database; it never reads or writes the
development MySQL database.

```bash
cd backend
composer test

cd ../frontend
npm run build
```

## Seeded credentials (change immediately)
- admin@krama.local / password123 (Super Admin)
- ceo@krama.local / password123 (CEO)
- sales.mgr@krama.local / password123 (Sales Manager)
- sales@krama.local / password123 (Sales Staff)

See docs/INSTALL.md for full setup, docs/ARCHITECTURE.md for design, and
docs/PRODUCT_ROADMAP.md for the real-domain launch gate and agreed Priority 0–4 roadmap.
For the immutable on-premise production package, database restore and first-deployment procedure,
see docs/PRODUCTION_PACKAGE.md.
