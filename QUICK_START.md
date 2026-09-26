# Quick Start

## Prerequisites

- PHP 8.4
- Composer
- Node.js + npm
- PostgreSQL
- Redis is optional, but recommended for cache and queue workloads

## 1. Install dependencies

```bash
composer install
npm install
```

## 2. Create environment file

```bash
cp .env.example .env
php artisan key:generate
```

Configure database and queue/cache settings in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mallow
DB_USERNAME=postgres
DB_PASSWORD=secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

If Redis is not available, switch to `database` or `file` for local development.

## 3. Run migrations and seed demo data

```bash
php artisan migrate --seed
```

The default seeder creates the merchant demo state, plans, customers, subscriptions, usage data, and a generated invoice.

## 4. Start the queue worker

```bash
php artisan queue:work --tries=3 --timeout=90
```

This is required for asynchronous aggregation and invoice generation.

## 5. Start the app

### Laravel / Inertia app

With Herd, use the project URL such as:

```text
https://mallow.test
```

For local Vite/frontend development:

```bash
npm run dev
```

For production asset build:

```bash
npm run build
```

## 6. Demo credentials

The seeder prints the demo login and API key details. Typical values are:

- email: `demo@example.com`
- password: `password`

Use the seeded API key with requests to `POST /api/usage`.

## 7. Smoke test usage API

```bash
curl -X POST http://localhost:8000/api/usage \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: <your-api-key>" \
  -d '{
    "customer_id": 1,
    "usage_date": "2026-09-26",
    "quantity": 250,
    "type": "api_calls",
    "idempotency_key": "11111111-2222-3333-4444-555555555555"
  }'
```

The same idempotency key retried again should return a duplicate response without double counting.

## 8. Verification commands

```bash
php artisan test --compact
php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php vendor/bin/pint --dirty --format agent
npm run build
```

## 9. Troubleshooting

- If queue jobs are not processing, confirm the queue worker is running and the queue driver is valid.
- If the dashboard shows stale pricing, confirm cache invalidation ran after a plan change.
- If the seeder fails, run a clean database reset:

```bash
php artisan migrate:fresh --seed
```

See [README.md](README.md) for the full system overview and operational details.
