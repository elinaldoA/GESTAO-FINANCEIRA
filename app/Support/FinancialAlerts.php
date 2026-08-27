<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Carbon;

class FinancialAlerts
{
    public static function forUser(User $user): array
    {
        $alerts = [];
        $today = Carbon::today();

        foreach ($user->creditCards()->where('is_active', true)->get() as $card) {
            $due = $card->invoiceDueDate((int) now()->year, (int) now()->month);
            $openAmount = (float) $card->invoiceTransactionsQuery((int) now()->year, (int) now()->month)
                ->where('invoice_paid', false)->sum('amount');

            if ($openAmount <= 0) {
                continue;
            }

            $daysToDue = (int) $today->diffInDays($due, false);

            if ($daysToDue <= 5) {
                $alerts[] = [
                    'severity' => $daysToDue < 0 ? 'error' : 'warning',
                    'message' => $daysToDue < 0
                        ? "Fatura do cartão \"{$card->name}\" venceu em {$due->format('d/m/Y')} e está em aberto (R$ ".number_format($openAmount, 2, ',', '.').').'
                        : "Fatura do cartão \"{$card->name}\" vence em {$due->format('d/m/Y')} (R$ ".number_format($openAmount, 2, ',', '.').').',
                    'url' => route('credit-cards.invoice', $card),
                ];
            }
        }

        $overdueCount = $user->transactions()->where('is_paid', false)->where('date', '<', $today)->count();
        if ($overdueCount > 0) {
            $alerts[] = [
                'severity' => 'error',
                'message' => "Você tem {$overdueCount} ".($overdueCount === 1 ? 'transação pendente vencida' : 'transações pendentes vencidas').'.',
                'url' => route('transactions.index'),
            ];
        }

        foreach ($user->goals()->where('is_active', true)->whereNotNull('target_date')->get() as $goal) {
            if ($goal->is_achieved) {
                continue;
            }

            $daysLeft = (int) $today->diffInDays($goal->target_date, false);

            if ($daysLeft >= 0 && $daysLeft <= 30) {
                $alerts[] = [
                    'severity' => 'warning',
                    'message' => "Meta \"{$goal->name}\" tem prazo em {$daysLeft} dias e ainda falta R$ ".number_format($goal->remaining_amount, 2, ',', '.').'.',
                    'url' => route('goals.index'),
                ];
            }
        }

        foreach ($user->investments()->where('is_active', true)->whereNotNull('day_change_percent')->get() as $investment) {
            $change = (float) $investment->day_change_percent;

            if (abs($change) >= 5) {
                $direction = $change >= 0 ? 'subiu' : 'caiu';
                $formattedChange = number_format(abs($change), 2, ',', '.');

                $alerts[] = [
                    'severity' => $change < 0 ? 'warning' : 'info',
                    'message' => "\"{$investment->name}\" ({$investment->ticker}) {$direction} {$formattedChange}% hoje.",
                    'url' => route('investments.index'),
                ];
            }
        }

        $daysInMonth = $today->daysInMonth;
        $daysLeft = $daysInMonth - $today->day;

        if ($today->day > 3 && $daysLeft >= 2) {
            $spentMap = Budget::spentMapFor($user->id, (int) $today->month, (int) $today->year);

            foreach ($user->budgets()->with('category')->where('month', $today->month)->where('year', $today->year)->get() as $budget) {
                if ((float) $budget->amount <= 0) {
                    continue;
                }

                $spent = $spentMap[$budget->category_id] ?? 0.0;
                $percent = ($spent / (float) $budget->amount) * 100;

                if ($percent >= 100) {
                    continue;
                }

                $projection = $spent / $today->day * $daysInMonth;

                if ($projection >= (float) $budget->amount) {
                    $categoryName = $budget->category->name ?? 'categoria';

                    $alerts[] = [
                        'severity' => 'warning',
                        'message' => "No ritmo atual, o orçamento de \"{$categoryName}\" deve estourar antes do fim do mês (projeção: R$ ".number_format($projection, 2, ',', '.').' de R$ '.number_format((float) $budget->amount, 2, ',', '.').').',
                        'url' => route('budgets.index'),
                    ];
                }
            }
        }

        return $alerts;
    }
}
