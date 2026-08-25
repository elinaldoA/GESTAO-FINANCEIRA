<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\FinancialAlertsDigest;
use App\Support\FinancialAlerts;
use Illuminate\Console\Command;

class SendFinancialAlerts extends Command
{
    protected $signature = 'finance:send-alerts';

    protected $description = 'Envia por e-mail um resumo dos alertas financeiros pendentes de cada usuário';

    public function handle(): int
    {
        $sent = 0;

        User::query()->chunk(50, function ($users) use (&$sent) {
            foreach ($users as $user) {
                $alerts = FinancialAlerts::forUser($user);

                if ($alerts === []) {
                    continue;
                }

                $user->notify(new FinancialAlertsDigest($alerts));
                $sent++;
            }
        });

        $this->info("Alertas enviados para {$sent} usuário(s).");

        return self::SUCCESS;
    }
}
