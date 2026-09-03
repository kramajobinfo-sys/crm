# Krama CRM — Installation Manual

## Prerequisites
- Docker & Docker Compose v2
- Git
- 4 GB RAM minimum (8 GB recommended)
- Ports free: 8000 (API), 8080 (frontend), 3307 (MySQL), 6380 (Redis)

## Step 1 — Clone & configure
```bash
git clone <repo-url> krama-crm
cd krama-crm
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

## Step 2 — Start containers
```bash
docker compose up -d --build
```
This starts: nginx, backend (php-fpm), queue worker, scheduler, frontend, MySQL, Redis, and
Mailpit (a local SMTP catcher for the Email module — see Step 5).

## Step 3 — Install backend dependencies & generate keys
```bash
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan jwt:secret
```

## Step 4 — Migrate & seed
```bash
docker compose exec backend php artisan migrate --seed
docker compose exec backend php artisan storage:link
```

## Step 5 — Access
- Frontend: http://localhost:8080
- API root: http://localhost:8000/api/v1
- Health check: http://localhost:8000/up
- Mailpit (SMTP test inbox for the Email module — no real provider is configured by default):
  http://localhost:8025. Point an email account's SMTP settings at host `mailpit`, port `1025`,
  no auth/encryption, to send-and-inspect locally instead of a real mailbox.

## Default logins (all password `password123`)
| Email                  | Role           |
|------------------------|----------------|
| admin@krama.local       | Super Admin    |
| ceo@krama.local         | CEO            |
| sales.mgr@krama.local   | Sales Manager  |
| sales@krama.local       | Sales Staff    |
| purchase@krama.local    | Purchase Mgr   |
| warehouse@krama.local   | Warehouse      |
| accounts@krama.local    | Accountant     |
| support@krama.local     | Customer Svc   |
| hr@krama.local          | HR             |

**Change every password after first login.**

## Common commands
```bash
# View logs
docker compose logs -f backend

# Rebuild frontend deps
docker compose exec frontend npm install

# Fresh DB (destroys data)
docker compose exec backend php artisan migrate:fresh --seed

# Clear caches
docker compose exec backend php artisan optimize:clear

# Run queue manually
docker compose exec backend php artisan queue:work
```

## Troubleshooting
- **"JWT secret not set"** → run `php artisan jwt:secret`.
- **CORS errors** → confirm `FRONTEND_URL` in backend/.env matches the browser URL.
- **MySQL connection refused on first boot** → wait ~30s for the healthcheck; the backend depends_on waits for it.
- **Frontend 502** → the node container runs `npm install` on first start; give it a minute, then reload.
