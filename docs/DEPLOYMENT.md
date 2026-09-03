# Krama CRM — Production Deployment (Ubuntu 22.04 LTS)

> Local development frontend: `frontend/src` is bind-mounted and Vite applies ordinary UI edits
> through live updates. Do not restart the `frontend` service after each source change because
> doing so disconnects open browser module sessions. Restart only after dependency, Vite, or
> Compose configuration changes, then wait until the frontend health check reports healthy.

## 1. Server prep
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y docker.io docker-compose-plugin git
sudo systemctl enable --now docker
sudo usermod -aG docker $USER   # re-login after this
```

## 2. Clone & configure for production
```bash
git clone <repo-url> /opt/krama-crm
cd /opt/krama-crm
cp backend/.env.example backend/.env
```
Edit `backend/.env`:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crm.yourdomain.com
DB_PASSWORD=<strong-password>
DB_ROOT_PASSWORD=<strong-password>
REDIS_PASSWORD=<strong-password>
FRONTEND_URL=https://crm.yourdomain.com
MAIL_MAILER=smtp   # configure real SMTP
```

## 3. Build frontend as static bundle
For production, serve the built SPA via nginx instead of the vite dev server:
```bash
cd frontend
cp .env.example .env   # set VITE_API_BASE_URL=https://crm.yourdomain.com/api/v1
docker run --rm -v $(pwd):/app -w /app node:20-alpine sh -c "npm install && npm run build"
```
Point nginx at `frontend/dist` (see nginx config below).

## 4. Production nginx (TLS via Certbot)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d crm.yourdomain.com
```
Serve SPA + proxy /api to the backend container.

## 5. Boot the stack
```bash
docker compose up -d --build
docker compose exec backend composer install --optimize-autoloader --no-dev
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan jwt:secret
docker compose exec backend php artisan migrate --seed --force
docker compose exec backend php artisan storage:link
docker compose exec backend php artisan config:cache
docker compose exec backend php artisan route:cache
docker compose exec backend php artisan event:cache
```

## 6. Hardening checklist

### Windows Docker development performance

The local PHP image disables OPcache timestamp validation because the Laravel application is
bind-mounted from a Windows filesystem. This prevents multi-second filesystem scans on every API
request. After changing backend PHP code locally, reload the PHP processes with:

```powershell
docker compose restart backend queue scheduler
```

- [ ] Change all seeded passwords
- [ ] APP_DEBUG=false
- [ ] Strong DB / Redis passwords
- [ ] Firewall: expose only 80/443, block 3307/6380
- [ ] Enable 2FA for all admin accounts
- [ ] Set up automated MySQL backups (`mysqldump` cron)
- [ ] Configure log rotation (already daily; ship to central logging if available)
- [ ] TLS certificate auto-renewal (`certbot renew` cron)
- [ ] Set `SESSION_SECURE_COOKIE=true`

## 7. Backups
```bash
# Daily DB dump
docker compose exec mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" krama_crm > backup-$(date +%F).sql
```

## 8. Zero-downtime updates
```bash
git pull
docker compose exec backend composer install --no-dev --optimize-autoloader
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan optimize
docker compose restart backend queue scheduler
```

## 9. Monitoring
- Liveness endpoint: `GET /up` (returns 200 when the Laravel process can serve requests)
- Readiness endpoint: `GET /ready` (returns 200 only when both the database and cache are available)
- Queue: `php artisan queue:monitor redis:default,emails,reports`
- Failed jobs: `php artisan queue:failed`
