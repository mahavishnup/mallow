# Database Architecture

## Overview

The database is organized around a tenant-first billing model. All merchant-level state is isolated by the `merchant_id` / team relationship, while customer-level usage is attached to subscription plans and usage events.

## Core tables

### merchants / teams

The app uses the existing multi-tenant foundation and maps merchant context to the team model.

### plans

Responsible for the pricing configuration used by subscriptions.

Fields include:

- `id`
- `merchant_id`
- `name`
- `base_price`
- `included_units`
- `overage_rate`
- `billing_cycle_days`
- `is_active`

### customers

Each customer belongs to one merchant and can have one or more active subscriptions over time.

### subscriptions

Tracks the current billing lifecycle for a customer.

Important fields:

- `customer_id`
- `plan_id`
- `status`
- `starts_at`
- `ends_at`

### subscription_segments

This is the critical historical plan-change table.

It stores each contiguous period in which a subscription was attached to a specific plan, for example:

- `2026-09-01` to `2026-09-15` old plan
- `2026-09-16` to `2026-09-30` new plan

This is the source of truth for which pricing applies to which date range.

### usage_events

Immutable raw usage data is stored here.

Key fields:

- `merchant_id`
- `customer_id`
- `usage_date`
- `quantity`
- `type`
- `idempotency_key`
- `aggregated_at`

This table is intentionally write-heavy and append-oriented.

### daily_usage

Derived aggregate table used for billing and dashboard reads.

Key fields:

- `merchant_id`
- `customer_id`
- `usage_date`
- `total_quantity`
- `event_count`

This table is updated via `upsert` and is the read-optimized layer feeding billing and analytics.

### invoices

Aggregate billing record for a subscription cycle.

### invoice_items

Per-segment billing breakdown generated from the plan history and daily usage totals.

## Index strategy

Important indexes are designed to support the hot paths:

- `usage_events(idempotency_key)` unique
- `usage_events(customer_id, usage_date)`
- `usage_events(merchant_id, usage_date)`
- `usage_events(aggregated_at)` to pick only unprocessed rows
- `daily_usage(customer_id, usage_date)` unique
- `daily_usage(merchant_id, usage_date)`
- `subscriptions(customer_id)`
- `subscription_segments(subscription_id, starts_at)`
- `invoices(subscription_id, period_start)`

## Why this design

This design keeps the system fast and understandable even as usage volume grows:

- raw events remain normalized and auditable
- aggregate tables avoid repeated full scans
- billing logic reads from the right abstraction layer
- plan changes are represented as explicit period segments instead of mutating history

## Scaling outlook

For 5M+ rows, the planned next steps are:

- date-based partitioning on `usage_events(usage_date)`
- retention or archival of older raw event data
- read replicas for reporting workloads
- bulk ingestion and queue scaling for peak burst traffic

See [README.md](README.md) for a full scaling and operational discussion.
