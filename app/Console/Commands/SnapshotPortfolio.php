<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SnapshotPortfolio extends Command
{
    protected $signature = 'portfolio:snapshot';

    protected $description = 'Registra um snapshot diário do patrimônio investido de cada usuário para permitir o gráfico de evolução';

    public function handle(): int
    {
        $today = today();
        $count = 0;

        User::query()->chunk(50, function ($users) use ($today, &$count) {
            foreach ($users as $user) {
                $investments = $user->investments()->where('is_active', true)->get();

                if ($investments->isEmpty()) {
                    continue;
                }

                $user->portfolioSnapshots()->updateOrCreate(
                    ['date' => $today],
                    [
                        'total_invested' => $investments->sum('invested_amount'),
                        'total_current' => $investments->sum('current_amount'),
                    ]
                );

                $count++;
            }
        });

        $this->info("Snapshot de patrimônio registrado para {$count} usuário(s).");

        return self::SUCCESS;
    }
}
