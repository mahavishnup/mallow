<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Billing\ChangeSubscriptionPlanAction;
use App\Actions\Billing\CreateSubscriptionAction;
use App\Actions\Billing\GenerateInvoiceAction;
use App\Enums\TeamRole;
use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Demo dataset for the subscription billing platform.
 *
 * Creates one merchant with tiered plans, customers with mixed subscription
 * stories (stable, mid-cycle upgrade, mid-cycle downgrade, overage-heavy,
 * churn-risk), backdated usage, one mid-cycle plan change, and one
 * engine-generated invoice — proving the full pipeline on seed.
 *
 * Run: php artisan db:seed --class=BillingDemoSeeder --no-interaction
 */
final class BillingDemoSeeder extends Seeder
{
    /**
     * Seed the application's demo data.
     */
    public function run(): void
    {
        $today = today();
        $monthStart = $today->copy()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();

        $merchant = $this->seedMerchant();
        $plans = $this->seedPlans($merchant);

        $create = app(CreateSubscriptionAction::class);
        $change = app(ChangeSubscriptionPlanAction::class);
        $invoice = app(GenerateInvoiceAction::class);

        // ── Stable customers on Starter ────────────────────────────────
        foreach (range(1, 4) as $i) {
            $customer = Customer::factory()->create([
                'merchant_id' => $merchant->id,
                'name'        => "Stable Customer {$i}",
            ]);

            $create->handle($merchant->id, [
                'customer_id' => $customer->id,
                'plan_id'     => $plans['starter']->id,
                'starts_at'   => $today->copy()->subDays(45)->toDateString(),
            ]);

            $this->seedUsage($customer->id, $today->copy()->subDays(45), $today, 2_500);
        }

        // ── Mid-cycle upgrade: Starter → Growth, 10 days ago ───────────
        // Started 30 days ago → today-10 falls inside the open segment.
        $upgrader = Customer::factory()->create([
            'merchant_id' => $merchant->id,
            'name'        => 'Upgrading Customer',
        ]);
        $upgraderSubscription = $create->handle($merchant->id, [
            'customer_id' => $upgrader->id,
            'plan_id'     => $plans['starter']->id,
            'starts_at'   => $today->copy()->subDays(30)->toDateString(),
        ]);
        $change->handle($upgraderSubscription, [
            'plan_id'        => $plans['growth']->id,
            'effective_date' => $today->copy()->subDays(10)->toDateString(),
        ]);
        $this->seedUsage($upgrader->id, $today->copy()->subDays(30), $today, 3_000);

        // Invoice for the upgrader's completed first cycle.
        $invoice->handle($upgraderSubscription, [
            'period_start' => $today->copy()->subDays(30)->toDateString(),
            'period_end'   => $today->toDateString(),
        ]);

        // ── Mid-cycle downgrade: Scale → Starter, 12 days ago ──────────
        $downgrader = Customer::factory()->create([
            'merchant_id' => $merchant->id,
            'name'        => 'Downgrading Customer',
        ]);
        $downgraderSubscription = $create->handle($merchant->id, [
            'customer_id' => $downgrader->id,
            'plan_id'     => $plans['scale']->id,
            'starts_at'   => $today->copy()->subDays(40)->toDateString(),
        ]);
        $change->handle($downgraderSubscription, [
            'plan_id'        => $plans['starter']->id,
            'effective_date' => $today->copy()->subDays(12)->toDateString(),
        ]);
        $this->seedUsage($downgrader->id, $today->copy()->subDays(40), $today, 1_800);

        // ── High-usage (overage) on Growth ─────────────────────────────
        $heavy = Customer::factory()->create([
            'merchant_id' => $merchant->id,
            'name'        => 'Heavy Usage Customer',
        ]);
        $create->handle($merchant->id, [
            'customer_id' => $heavy->id,
            'plan_id'     => $plans['growth']->id,
            'starts_at'   => $today->copy()->subDays(20)->toDateString(),
        ]);
        $this->seedUsage($heavy->id, $today->copy()->subDays(20), $today, 35_000);

        // ── Churn risk: heavy last month, near-zero this month ─────────
        $churnRisk = Customer::factory()->create([
            'merchant_id' => $merchant->id,
            'name'        => 'Churn Risk Customer',
        ]);
        $create->handle($merchant->id, [
            'customer_id' => $churnRisk->id,
            'plan_id'     => $plans['starter']->id,
            'starts_at'   => $today->copy()->subDays(50)->toDateString(),
        ]);
        $this->seedUsage($churnRisk->id, $lastMonthStart, $lastMonthStart->copy()->addDays(20), 120_000);
        DailyUsage::factory()->forDay($churnRisk->id, $today->copy()->subDays(2)->toDateString(), 5_000, 1)->create();

        $this->command?->info('Demo dataset complete: 8 customers, 2 plan changes, 1 invoice.');
    }

    /**
     * Merchant team, owner login, and demo API key.
     */
    private function seedMerchant(): Team
    {
        $owner = User::factory()->create([
            'name'  => 'Demo Merchant Owner',
            'email' => 'demo@example.com',
        ]);

        $merchant = Team::factory()->create([
            'name'        => 'Acme Metering Co',
            'slug'        => 'acme-metering',
            'is_personal' => false,
        ]);

        $merchant->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $owner->switchTeam($merchant);

        $plaintext = 'mall_' . bin2hex(random_bytes(32));

        ApiKey::factory()->create([
            'merchant_id' => $merchant->id,
            'name'        => 'Demo key',
            'key_hash'    => hash('sha256', $plaintext),
        ]);

        $this->command?->info('Demo login: demo@example.com / password');
        $this->command?->info('Demo team: ' . $merchant->name);
        $this->command?->warn('Demo API key (store now — shown once): ' . $plaintext);

        return $merchant;
    }

    /**
     * Tiered plan catalogue.
     *
     * @return array<string, Plan>
     */
    private function seedPlans(Team $merchant): array
    {
        return [
            'starter' => Plan::factory()->create([
                'merchant_id'    => $merchant->id,
                'name'           => 'Starter',
                'base_price'     => '29.00',
                'included_units' => 100_000,
                'overage_rate'   => '0.0025',
            ]),
            'growth' => Plan::factory()->create([
                'merchant_id'    => $merchant->id,
                'name'           => 'Growth',
                'base_price'     => '99.00',
                'included_units' => 500_000,
                'overage_rate'   => '0.0020',
            ]),
            'scale' => Plan::factory()->create([
                'merchant_id'    => $merchant->id,
                'name'           => 'Scale',
                'base_price'     => '299.00',
                'included_units' => 2_000_000,
                'overage_rate'   => '0.0015',
            ]),
        ];
    }

    /**
     * Seed daily_usage rows directly (derived table — fast path; usage_events
     * omitted by design: daily_usage is rebuildable from events at any time).
     *
     * merchant_id resolves automatically from the customer via the factory.
     */
    private function seedUsage(int $customerId, CarbonInterface $start, CarbonInterface $end, int $dailyQuantity): void
    {
        $cursor = $start;

        // Reassign (never mutate): $start is CarbonImmutable under this app's
        // global Date::use configuration, so addDay() returns a new instance.
        while ($cursor->lessThan($end)) {
            DailyUsage::factory()->forDay($customerId, $cursor->toDateString(), $dailyQuantity, 1)->create();
            $cursor = $cursor->addDay();
        }
    }
}
