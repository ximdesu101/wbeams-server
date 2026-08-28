<?php

namespace App\Console\Commands;

use App\Enums\OperatorStatus;
use App\Models\Operator\Operator;
use Illuminate\Console\Command;

class ExpireOperatorTokens extends Command
{
    protected $signature = 'operators:expire-tokens';

    protected $description = 'Mark operator activation tokens as expired if they have passed the expiry date';

    public function handle(): int
    {
        $expiredOperators = Operator::where('status', OperatorStatus::Inactive)
            ->where('activation_token_expires_at', '<', now())
            ->get(['id', 'operator_id', 'email']);

        if ($expiredOperators->isEmpty()) {
            $this->info('No expired operator tokens found.');

            return Command::SUCCESS;
        }

        Operator::whereIn('id', $expiredOperators->pluck('id'))
            ->update([
                'status' => OperatorStatus::Expired,
                'expired_at' => now(),
            ]);

        foreach ($expiredOperators as $operator) {
            $this->info("Expired operator: {$operator->operator_id} - {$operator->email}");
        }

        $this->info("Expired {$expiredOperators->count()} operator activation tokens.");

        return Command::SUCCESS;
    }
}
