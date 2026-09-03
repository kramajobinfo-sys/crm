# Krama CRM — Production Package and Restore Guide

This guide deploys the packaged Krama CRM application and its database to an on-premise Linux
server using Docker Compose. The production stack does not expose MySQL or Redis, does not run
Mailpit, and serves a compiled frontend instead of the Vite development server.

## Package contents

- Application source and locked PHP/Node dependency manifests
- Immutable production Dockerfiles
- `docker-compose.production.yml`
- `.env.production.example` without secrets
- `deployment/database/krama_crm.sql`, exported from the source system at package time
- `deployment/storage`, containing the current uploaded documents and media
- SHA-256 checksums and package manifest

The database export contains application data and password hashes. Treat the archive as
confidential, transfer it over a protected channel, restrict filesystem access, and remove
unnecessary copies after deployment. Development email/social/Business Central secrets, active
API keys, reset tokens and encrypted 2FA secrets are deliberately cleared in the packaged copy.
Reconnect those services and re-enrol 2FA after deployment using the new production `APP_KEY`.

## Host prerequisites

- 64-bit Linux host with Docker Engine and Docker Compose v2
- A reverse proxy or firewall that can route the public domain to `127.0.0.1:8080`
- DNS record for the CRM domain pointing to the on-premise public IP
- TLS certificate for the CRM domain
- Outbound access during the first image build to download Docker images and locked dependencies

## First deployment

1. Extract the archive into a dedicated directory such as `/opt/krama-crm`.
2. Copy `.env.production.example` to `.env`.
3. Replace every `REPLACE_WITH_...` value. Set `APP_URL`, `APP_DOMAIN`, `FRONTEND_URL`, mail
   settings and timezone for the real domain. Do not reuse database and Redis passwords.
4. Restrict the environment file: `chmod 600 .env`.
   Ensure `deployment/storage` is writable by the application container; the packaged entrypoint
   sets its contents to the container's `www-data` user during startup.
5. Verify the archive before starting:

   ```bash
   sha256sum -c SHA256SUMS.txt
   docker compose --env-file .env -f docker-compose.production.yml config --quiet
   ```

6. Build and start the private services:

   ```bash
   docker compose --env-file .env -f docker-compose.production.yml build
   docker compose --env-file .env -f docker-compose.production.yml up -d mysql redis
   ```

   On the first start of a new `mysql_data` volume, MySQL imports
   `deployment/database/krama_crm.sql` automatically. It does not re-import it on later starts.

7. Start the application and apply any migrations added after the export:

   ```bash
   docker compose --env-file .env -f docker-compose.production.yml up -d backend queue scheduler web
   docker compose --env-file .env -f docker-compose.production.yml exec backend php artisan migrate --force
   docker compose --env-file .env -f docker-compose.production.yml exec backend php artisan optimize
   ```

8. Route the public HTTPS domain to `http://127.0.0.1:8080`. Preserve the original `Host`,
   `X-Forwarded-Proto`, `X-Forwarded-For` and `X-Real-IP` headers.
9. Verify `https://your-domain/up` and `https://your-domain/ready`, then run the smoke tests below.

## Required first-login actions

The database is a copy of the current environment. Immediately:

1. Change all seeded/demo and administrator passwords.
2. Remove or disable accounts that must not access production.
3. Create new API keys and reconnect email/social/Business Central credentials as required; the
   development credentials are disabled and removed from the packaged database.
4. Confirm tenant domains, company branding, mail sender and portal access settings.
5. Create a fresh encrypted off-server backup.

Do not expose the public domain before the initial administrator password has been changed.

## Smoke tests

- Staff login, refresh after 20 minutes, logout and re-login
- Leads → Account + Contact + optional Deal conversion
- Create/edit/move a Deal and confirm the page stops loading normally
- Product/custom line mix → Quotation → Sales Order → Invoice
- Portal login and tenant-authorized invoice/ticket access
- Email test and one queue-backed notification
- `/up` returns 200 and `/ready` returns 200
- A user from one tenant cannot access a known record ID from another tenant

## Reverse proxy example

Use the TLS-enabled proxy already operated by the hosting environment. The important proxy target
is `http://127.0.0.1:8080`; do not expose the MySQL or Redis containers. For nginx:

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

## Updates

Never overwrite `.env` or the Docker volumes during an application update.

```bash
docker compose --env-file .env -f docker-compose.production.yml build
docker compose --env-file .env -f docker-compose.production.yml run --rm backend php artisan migrate --force
docker compose --env-file .env -f docker-compose.production.yml up -d
docker compose --env-file .env -f docker-compose.production.yml exec backend php artisan optimize
```

## Rollback triggers

Rollback immediately when login/token refresh fails, `/ready` remains unhealthy, migrations fail,
cross-tenant access is observed, queue failures grow continuously, or a core workflow returns
repeated 500 errors.

Before every update, export the database and preserve the previous application archive/image tags.
Database migrations are forward-only unless a tested migration-specific rollback is documented.

## Database restore outside first boot

Importing a dump into a populated database overwrites or conflicts with live information. Take a
backup and stop application writers before a deliberate restore. For a new empty installation,
prefer the automatic first-boot import described above.
