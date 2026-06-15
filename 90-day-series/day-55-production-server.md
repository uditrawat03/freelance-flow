# Day 55 - Production Server Setup

> **Series:** FreelanceFlow - Laravel Zero to Hero | **Phase 4:** Production
> **Read time:** 18 min | **Level:** Intermediate-Advanced

---

Everything we built now needs a real home. Today we provision a production Ubuntu server, install the services FreelanceFlow actually uses, configure Nginx with TLS, run Laravel Octane behind a reverse proxy, and supervise Horizon and Reverb with systemd.

The goal is not only "it works on one VPS." The goal is a scale-ready first production shape: Redis-backed cache, sessions, queues, isolated Horizon lanes, a dedicated Reverb Redis database for WebSocket scaling, S3-ready storage, and clear knobs for increasing worker capacity without rewriting the app.

---

## What We Are Doing Today

This is mostly an operations day. We will make a few repo-level production fixes first, then configure the server.

Repo changes:

| File | Purpose |
|---|---|
| `.env.example` | Documents production-scale Redis, Horizon, Octane, filesystem, and Reverb variables |
| `config/reverb.php` | Keeps Reverb scaling traffic on `REDIS_REVERB_DB` instead of sharing the default app Redis DB |
| `tests/Feature/ProductionServerConfigurationTest.php` | Protects the production scaling configuration from drifting |

Server changes:

| Server file | Purpose |
|---|---|
| `/etc/php/8.3/cli/conf.d/10-opcache.ini` | OPcache for Octane's long-running PHP process |
| `/etc/redis/redis.conf` | Redis auth, memory cap, eviction policy, and local binding |
| `/etc/nginx/sites-available/freelanceflow` | TLS, static assets, reverse proxy to Octane and Reverb |
| `/etc/systemd/system/octane.service` | Octane/FrankenPHP process supervision |
| `/etc/systemd/system/horizon.service` | Queue worker supervision through Horizon |
| `/etc/systemd/system/reverb.service` | WebSocket server supervision |

Prerequisites:

- Ubuntu 24.04 VPS with at least 2 vCPU and 4 GB RAM
- A domain pointed to the server's public IP
- SSH access as root or a sudo user
- A MySQL backup and rollback plan before touching an existing production server

---

## Step 1 - Initial Server Setup

```bash
apt-get update && apt-get upgrade -y

adduser deploy
usermod -aG sudo deploy
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy

su - deploy
```

From this point forward, run application commands as `deploy`, not `root`.

---

## Step 2 - Install PHP 8.3, Composer, Node, and Nginx

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update

sudo apt-get install -y \
    nginx \
    unzip \
    git \
    curl \
    php8.3-cli \
    php8.3-fpm \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-bcmath \
    php8.3-curl \
    php8.3-zip \
    php8.3-mysql \
    php8.3-redis \
    php8.3-gd \
    php8.3-intl \
    php8.3-sqlite3 \
    php8.3-opcache \
    php8.3-tokenizer \
    php8.3-fileinfo

curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

php -v
composer --version
node --version
npm --version
```

FreelanceFlow runs through Octane/FrankenPHP, so PHP-FPM is not the main request runtime. It is still useful as an emergency fallback and for compatibility with server tooling.

---

## Step 3 - Configure OPcache for Octane

Octane is a long-running PHP CLI process, so configure OPcache for CLI. This is different from a traditional PHP-FPM-only app.

```bash
sudo nano /etc/php/8.3/cli/conf.d/10-opcache.ini
```

```ini
[opcache]
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.jit=off
```

`opcache.validate_timestamps=0` means PHP will not check whether source files changed. That is good for production throughput, but every deploy must restart or reload Octane.

---

## Step 4 - Install MySQL

```bash
sudo apt-get install -y mysql-server
sudo mysql_secure_installation

sudo mysql -u root -p << 'SQL'
CREATE DATABASE freelance_flow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'freelanceflow'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON freelance_flow.* TO 'freelanceflow'@'localhost';
FLUSH PRIVILEGES;
SQL
```

This single-server setup is the first production shape. When traffic grows, move MySQL to a managed database service before you add more web servers.

---

## Step 5 - Install Redis

```bash
sudo apt-get install -y redis-server
sudo nano /etc/redis/redis.conf
```

Use these production settings as the baseline:

```conf
bind 127.0.0.1 ::1
protected-mode yes
requirepass YOUR_REDIS_PASSWORD_HERE

save 60 1000
maxmemory 1gb
maxmemory-policy allkeys-lru
```

FreelanceFlow uses separate logical Redis databases:

| DB | Purpose |
|---|---|
| `0` | app data, sessions, default queue |
| `1` | cache store |
| `2` | Reverb scaling pub/sub coordination |

```bash
sudo systemctl enable redis-server
sudo systemctl restart redis-server
redis-cli -a YOUR_REDIS_PASSWORD_HERE ping
```

Expected result:

```text
PONG
```

---

## Step 6 - Deploy the Application

```bash
sudo mkdir -p /var/www/freelanceflow
sudo chown -R deploy:deploy /var/www/freelanceflow

cd /var/www
git clone https://github.com/yourusername/freelanceflow.git freelanceflow
cd freelanceflow

composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build

cp .env.example .env
php artisan key:generate
```

Set production values in `.env`:

```dotenv
APP_NAME="FreelanceFlow"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://freelanceflow.yourdomain.com
APP_TIMEZONE=UTC

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freelance_flow
DB_USERNAME=freelanceflow
DB_PASSWORD=STRONG_PASSWORD_HERE

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=YOUR_REDIS_PASSWORD_HERE
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_REVERB_DB=2
REDIS_QUEUE=default

HORIZON_DEFAULT_MIN_PROCESSES=2
HORIZON_DEFAULT_MAX_PROCESSES=10
HORIZON_EMAIL_MIN_PROCESSES=1
HORIZON_EMAIL_MAX_PROCESSES=5
HORIZON_NOTIFICATION_MIN_PROCESSES=1
HORIZON_NOTIFICATION_MAX_PROCESSES=5
HORIZON_LOW_PROCESSES=2
HORIZON_MEMORY_LIMIT=256

OCTANE_SERVER=frankenphp
OCTANE_HTTPS=true
OCTANE_HOST=127.0.0.1
OCTANE_PORT=8000
OCTANE_WORKERS=4
OCTANE_MAX_REQUESTS=500
OCTANE_GARBAGE=100
OCTANE_MAX_EXECUTION_TIME=30

BROADCAST_CONNECTION=reverb
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_APP_ID=freelanceflow
REVERB_APP_KEY=GENERATE_A_SECURE_KEY
REVERB_APP_SECRET=GENERATE_A_SECURE_SECRET
REVERB_HOST=freelanceflow.yourdomain.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=https://freelanceflow.yourdomain.com
REVERB_SCALING_ENABLED=true
REVERB_APP_RATE_LIMITING_ENABLED=true
REVERB_APP_MAX_CONNECTIONS=1000

FILESYSTEM_DISK=s3
UPLOAD_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=YOUR_POSTMARK_API_KEY
MAIL_PASSWORD=YOUR_POSTMARK_API_KEY
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@freelanceflow.yourdomain.com
MAIL_FROM_NAME="FreelanceFlow"

STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

SENTRY_LARAVEL_DSN=https://...@sentry.io/...
LOG_CHANNEL=stack
LOG_LEVEL=warning

TELESCOPE_ENABLED=false
LIGHTHOUSE_SCHEMA_CACHE_ENABLE=true
LIGHTHOUSE_QUERY_CACHE_ENABLE=true
LIGHTHOUSE_VALIDATION_CACHE_ENABLE=true
LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION=true
```

Finish the first deploy:

```bash
php artisan migrate --force
php artisan db:seed --class=RoleAndPermissionSeeder --force
php artisan storage:link
php artisan optimize
```

Create the first admin user:

```bash
php artisan tinker
```

```php
$user = App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@yourdomain.com',
    'password' => bcrypt('STRONG_PASSWORD'),
]);

$user->assignRole('admin');
```

---

## Step 7 - Optional Search Service

Do not install Meilisearch on this server unless the application has Laravel Scout and the Meilisearch PHP client installed. This codebase currently does not require Scout directly, so search is not part of the mandatory Day 55 deployment.

When Scout is added later, use a private listener:

```toml
env = "production"
master_key = "YOUR_MEILISEARCH_MASTER_KEY_HERE"
db_path = "/var/lib/meilisearch/data"
http_addr = "127.0.0.1:7700"
log_level = "WARN"
```

Then set:

```dotenv
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=YOUR_MEILISEARCH_MASTER_KEY_HERE
```

---

## Step 8 - TLS with Let's Encrypt

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot certonly --nginx -d freelanceflow.yourdomain.com
sudo certbot renew --dry-run
```

Certbot installs a renewal timer automatically on Ubuntu. Confirm it:

```bash
systemctl list-timers | grep certbot
```

---

## Step 9 - Nginx Reverse Proxy

```bash
sudo nano /etc/nginx/sites-available/freelanceflow
```

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}

server {
    listen 80;
    server_name freelanceflow.yourdomain.com;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name freelanceflow.yourdomain.com;

    root /var/www/freelanceflow/public;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/freelanceflow.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/freelanceflow.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    client_max_body_size 20m;

    location ~* \.(?:js|css|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
        access_log off;
    }

    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;
    }

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_read_timeout 60s;
        proxy_connect_timeout 10s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable it:

```bash
sudo ln -s /etc/nginx/sites-available/freelanceflow /etc/nginx/sites-enabled/freelanceflow
sudo nginx -t
sudo systemctl reload nginx
```

---

## Step 10 - systemd Services

### Octane

```bash
sudo nano /etc/systemd/system/octane.service
```

```ini
[Unit]
Description=FreelanceFlow Laravel Octane
After=network.target mysql.service redis-server.service

[Service]
User=deploy
Group=deploy
WorkingDirectory=/var/www/freelanceflow
ExecStart=/usr/bin/php /var/www/freelanceflow/artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000 --workers=4 --max-requests=500
ExecReload=/usr/bin/php /var/www/freelanceflow/artisan octane:reload
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=60
StandardOutput=append:/var/www/freelanceflow/storage/logs/octane.log
StandardError=append:/var/www/freelanceflow/storage/logs/octane.log

[Install]
WantedBy=multi-user.target
```

Start with `workers = CPU cores`. Increase workers only after watching memory per worker under real traffic.

### Horizon

```bash
sudo nano /etc/systemd/system/horizon.service
```

```ini
[Unit]
Description=FreelanceFlow Laravel Horizon
After=network.target mysql.service redis-server.service

[Service]
User=deploy
Group=deploy
WorkingDirectory=/var/www/freelanceflow
ExecStart=/usr/bin/php /var/www/freelanceflow/artisan horizon
ExecStop=/usr/bin/php /var/www/freelanceflow/artisan horizon:terminate
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=3600
StandardOutput=append:/var/www/freelanceflow/storage/logs/horizon.log
StandardError=append:/var/www/freelanceflow/storage/logs/horizon.log

[Install]
WantedBy=multi-user.target
```

Horizon already defines four scalable queue lanes:

| Supervisor | Queue | Purpose |
|---|---|---|
| `supervisor-default` | `default` | regular application jobs |
| `supervisor-emails` | `emails` | user-facing mail |
| `supervisor-notifications` | `notifications` | notifications and webhook-style side effects |
| `supervisor-low` | `low` | slow maintenance, PDFs, cache warming |

### Reverb

```bash
sudo nano /etc/systemd/system/reverb.service
```

```ini
[Unit]
Description=FreelanceFlow Laravel Reverb
After=network.target redis-server.service

[Service]
User=deploy
Group=deploy
WorkingDirectory=/var/www/freelanceflow
ExecStart=/usr/bin/php /var/www/freelanceflow/artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=60
StandardOutput=append:/var/www/freelanceflow/storage/logs/reverb.log
StandardError=append:/var/www/freelanceflow/storage/logs/reverb.log

[Install]
WantedBy=multi-user.target
```

### Scheduler

```bash
sudo crontab -u deploy -e
```

```cron
* * * * * cd /var/www/freelanceflow && php artisan schedule:run >> /dev/null 2>&1
```

Enable services:

```bash
sudo systemctl daemon-reload
sudo systemctl enable octane horizon reverb
sudo systemctl start octane horizon reverb
sudo systemctl status octane horizon reverb
```

---

## Step 11 - Production Permissions

```bash
sudo chown -R deploy:www-data /var/www/freelanceflow/storage /var/www/freelanceflow/bootstrap/cache
sudo chmod -R 775 /var/www/freelanceflow/storage /var/www/freelanceflow/bootstrap/cache
```

If `FILESYSTEM_DISK=s3` and `UPLOAD_DISK=s3`, local storage is still used for logs, compiled views, framework cache, and temporary files.

---

## Step 12 - Verify the Deployment

Run these checks from the server:

```bash
php artisan about
php artisan route:list --path=up
curl -I https://freelanceflow.yourdomain.com/up
curl -I https://freelanceflow.yourdomain.com/login
php artisan horizon:status
redis-cli -a YOUR_REDIS_PASSWORD_HERE -n 0 ping
redis-cli -a YOUR_REDIS_PASSWORD_HERE -n 1 ping
redis-cli -a YOUR_REDIS_PASSWORD_HERE -n 2 ping
```

Expected results:

- `/up` returns HTTP 200
- `/login` returns HTTP 200 or 302
- Horizon reports that it is running
- Redis DBs `0`, `1`, and `2` respond with `PONG`

Check logs:

```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/octane.log
tail -f storage/logs/horizon.log
tail -f storage/logs/reverb.log
```

Run the production config regression tests locally before shipping this day:

```powershell
php artisan test tests\Feature\ProductionServerConfigurationTest.php
php artisan test
```

---

## Production Architecture

```text
Internet
  -> Nginx :443
       -> static assets from /public
       -> Octane/FrankenPHP :8000 for HTTP
       -> Reverb :8080 for WebSockets

Application Server
  -> Octane workers handle web requests
  -> Horizon supervisors process default, emails, notifications, and low queues
  -> Scheduler runs Laravel scheduled commands every minute
  -> Reverb handles WebSocket connections

Data Services
  -> MySQL stores application data
  -> Redis DB 0 stores sessions and queues
  -> Redis DB 1 stores cache data
  -> Redis DB 2 coordinates Reverb scaling
  -> S3-compatible object storage stores uploads
```

The next scaling moves are straightforward:

| Pressure | Scaling move |
|---|---|
| CPU-bound web requests | Add Octane workers, then add another web server behind a load balancer |
| Queue backlog | Increase the matching Horizon supervisor process counts |
| WebSocket growth | Run more Reverb nodes with `REVERB_SCALING_ENABLED=true` |
| Database load | Move MySQL to managed DB, then add read replicas where query patterns justify it |
| Upload/storage growth | Keep uploads on S3-compatible storage, not local disk |

---

## What We Learned Today

- Octane uses the PHP CLI runtime, so OPcache must be enabled for CLI in production.
- Redis should be private, password-protected, memory-capped, and split by logical workload.
- Horizon scales best when queue lanes are explicit and each lane has its own supervisor.
- Reverb can scale horizontally when instances share Redis pub/sub state through a dedicated Redis DB.
- Nginx should terminate TLS, serve immutable static assets, and proxy only dynamic traffic to Octane.
- Production docs must match installed dependencies. Meilisearch is optional here until Scout is added to the app.
- The production `.env` is part of the architecture. Missing env knobs become scaling bottlenecks later.

---

## Day 56 - Production Deployment Runbook

Tomorrow we turn this server into a repeatable deployment target: maintenance windows, release commands, backup checks, cache warming, service reload order, and rollback steps.
