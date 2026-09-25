<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function sprintf;

#[Signature('billing:api-key
    {--merchant= : Team ID acting as the merchant}
    {--name= : Display name for the key}')]
#[Description('Create a merchant API key and print the plaintext key once')]
final class CreateApiKeyCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $merchantId = (int) $this->option('merchant');

        if ($merchantId < 1) {
            $this->error('The --merchant option (team ID) is required.');

            return self::FAILURE;
        }

        $merchant = Team::query()->find($merchantId);

        if ($merchant === null) {
            $this->error(sprintf('Team %d does not exist.', $merchantId));

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?? 'Default');
        $plaintext = 'mall_' . bin2hex(random_bytes(32));

        ApiKey::query()->create([
            'merchant_id' => $merchant->id,
            'name'        => $name,
            'key_hash'    => hash('sha256', $plaintext),
        ]);

        $this->info('API key created for merchant (team) ' . $merchant->name . '.');
        $this->line($plaintext);
        $this->warn('Store this key now — it will not be shown again.');

        return self::SUCCESS;
    }
}
