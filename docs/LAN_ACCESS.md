# Accessing Krama CRM from another device on your LAN

The stack is now configured so any device on your network can reach it by the
Docker host's IP — no per-IP editing needed.

## 1. Find your Docker host's LAN IP
On the machine running Docker Desktop:
- Windows:  `ipconfig`  -> IPv4 Address (e.g. 192.168.1.50)
- Mac/Linux: `ifconfig | grep "inet "` or `ip addr`

## 2. Start the stack (from the project folder)
```
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
docker compose up -d --build
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan jwt:secret
docker compose exec backend php artisan migrate --seed
```

## 3. Open from the other device
```
http://<HOST_IP>:8080        e.g. http://192.168.1.50:8080
```
Log in: admin@krama.local / password123

## How it works (why this fixes localhost)
- The frontend now calls the API with a RELATIVE path (/api/v1), so the browser
  talks only to the same host:port it loaded the app from. Vite proxies /api to
  the backend container. One origin => no CORS pain, no hardcoded IP.
- Vite listens on 0.0.0.0 with allowedHosts:true, so it serves any host header.
- Backend CORS also whitelists localhost + private LAN ranges by regex as a
  safety net (192.168.x.x, 10.x.x.x, 172.16-31.x.x on any port).

## Firewall note (Windows)
If the other device still can't connect, Windows Defender Firewall may be
blocking inbound. Allow inbound TCP on ports 8080 and 8000 for Private networks,
or run (PowerShell as admin):
```
New-NetFirewallRule -DisplayName "Krama CRM 8080" -Direction Inbound -LocalPort 8080 -Protocol TCP -Action Allow
New-NetFirewallRule -DisplayName "Krama CRM 8000" -Direction Inbound -LocalPort 8000 -Protocol TCP -Action Allow
```

## Optional: pin to one origin
If you prefer to lock the app to a single IP instead of the LAN regex, set in
backend/.env:  FRONTEND_URL=http://192.168.1.50:8080  and rebuild.

## Testing tenant subdomains locally
Every tenant gets a unique `subdomain` slug (e.g. `acme` for "Acme Test Co"), resolved from the
`Host` header for login-page branding only (`GET /tenant-info` — see ARCHITECTURE.md "Per-tenant
subdomains"; it's read-only, nothing depends on it for auth). `APP_DOMAIN` in `backend/.env`
(default `localhost`) is the base domain a subdomain is resolved against.

To see it in a browser rather than curl, add a hosts-file entry mapping the subdomain to
`127.0.0.1` — Windows: `C:\Windows\System32\drivers\etc\hosts` (as admin), Mac/Linux:
`/etc/hosts` — then visit `http://<subdomain>.localhost:8081/login`. No entry needed to test via
curl: `curl -H 'Host: acme.localhost' http://localhost:8000/api/v1/tenant-info`.
