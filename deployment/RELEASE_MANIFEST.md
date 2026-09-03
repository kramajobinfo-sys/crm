# Krama CRM Production Release Manifest

**Release:** 2026-09-03 on-premise production candidate  
**Runtime:** Docker Compose v2 on 64-bit Linux  
**Application:** Laravel 12 / PHP 8.3, Vue 3 static build, MySQL 8, Redis 7, nginx

## Included

- Complete current application source, including uncommitted workspace functionality
- Locked Composer and npm dependencies
- Production Dockerfiles and Compose stack
- Current database export: 138 tables and 50 recorded migrations
- Current application storage: uploaded public/private documents and media
- Production environment template without usable secrets
- Deployment, restore, smoke-test and rollback documentation

## Credential treatment

The packaged database preserves business/demo data and password hashes but deliberately disables
or clears development email, social-channel and Dynamics 365 Business Central credentials, API
keys, password-reset tokens and encrypted two-factor secrets. Generate a new production `APP_KEY`
and `JWT_SECRET`, reconnect integrations, create new API keys, and re-enrol 2FA after deployment.

The existing documented demo/administrator password may still be present in the database. Keep
the application bound to localhost until it is changed, then expose it through the TLS reverse
proxy.

## Verification performed

- Backend: 44 tests passed, 204 assertions
- Frontend: clean production Docker build passed (1,656 modules transformed)
- Production backend image build passed with locked non-development dependencies
- Database first-boot restore passed: 138 tables, all 50 migrations present
- Isolated production smoke stack passed compiled SPA, database readiness and Redis readiness
- Authentication smoke test passed login, authenticated `/auth/me`, token refresh and logout

## Known release gates

- Replace every placeholder in `.env.production.example` before use.
- Configure the real domains, DNS, TLS reverse proxy and SMTP provider.
- Change all seeded/demo and administrator passwords before public exposure.
- Complete the production-domain tenant-isolation and customer-portal smoke tests.
- Private authenticated delivery for every legacy Lead/Deal/Chat/Email attachment remains on the
  Priority 0 roadmap; do not store sensitive attachments until that work is completed.

See `docs/PRODUCTION_PACKAGE.md` for deployment instructions and `docs/PRODUCT_ROADMAP.md` for the
remaining launch gate and post-launch roadmap.
