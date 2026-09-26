# Architecture Overview

## Purpose

This project is a multi-tenant subscription billing and usage metering platform built on Laravel and React. It models merchants, customers, plans, subscriptions, usage events, daily aggregates, and invoices.

The system is designed to satisfy the core interview brief:

- idempotent usage capture
- queued daily aggregation
- segmented billing with proration and overage
- mid-cycle plan change handling
- cache-backed plan pricing
- tenant-scoped dashboard insights

## High-level system view

```text
Browser / Inertia UI
        |
        v
Laravel application
  - Controllers
  - Form Requests
  - Actions
  - Services
  - Jobs
  - Models
        |
        +--> PostgreSQL (OLTP + billing state)
        |
        +--> Redis (cache + queue, optional fallback)
        |
        +--> Vite / React frontend assets
```

## Request flow

### Usage ingestion

```text
POST /api/usage
  -> rate limit check
  -> tenant/customer validation
  -> idempotency_key check
  -> insert usage_events row
  -> dispatch AggregateDailyUsageJob after commit
  -> return 201 or 200 duplicate response
```

### Daily aggregation

```text
AggregateDailyUsageJob
  -> fetch unaggregated usage_events in chunks
  -> group by customer + usage_date
  -> upsert daily_usage
  -> mark events as aggregated
```

### Billing

```text
GenerateInvoiceAction / BillingService
  -> resolve subscription segments
  -> determine active plan for each date window
  -> compute prorated base price
  -> compute included units and overage
  -> generate invoice items and totals
```

## Domain responsibilities

### Controllers

Keep controllers thin and response-oriented. They validate HTTP input and delegate to domain actions.

### Actions

Actions handle a single business outcome such as:

- recording usage
- creating a subscription
- changing a plan mid-cycle
- generating invoice
- aggregating daily amounts

### Services

Services own domain logic that is longer-lived and reusable, including:

- billing calculations
- dashboard metrics
- plan pricing cache

### Jobs

Queued jobs protect the write path from expensive logic. They process usage aggregation and invoice generation asynchronously.

## Database architecture

The project keeps raw usage events immutable and aggregates them into a denormalized daily table for reporting and billing speed.

Key patterns:

- `usage_events` is the write-heavy source of truth.
- `daily_usage` is derived and optimized for reads.
- `subscription_segments` is the source of truth for plan-period history.
- `invoices` and `invoice_items` store the final computed billing result.

## Security and tenancy

- Merchant isolation is enforced via customer ownership and tenant-scoped queries.
- API access is guarded by merchant API keys.
- Dashboard and routes are scoped to the team/merchant context.

## Verification status

The project is currently in a verified green state:

- 159 Pest tests passing
- production frontend build succeeding
- PHPStan reporting 0 errors
- formatter checks passing

See [README.md](README.md) and [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) for implementation details and operational guidance.
