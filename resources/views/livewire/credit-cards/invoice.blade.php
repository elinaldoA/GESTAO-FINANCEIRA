<?php

use App\Models\CreditCard;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public CreditCard $creditCard;
    public int $year;
    public int $month;
    public ?int $payFromAccountId = null;

    public function mount(CreditCard $creditCard): void
    {
        $this->authorize('view', $creditCard);

        $this->creditCard = $creditCard;
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonthNoOverflow();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonthNoOverflow();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function payInvoice(): void
    {
        $this->validate(['payFromAccountId' => 'required|exists:accounts,id']);

        $openTransactions = $this->creditCard->invoiceTransactionsQuery($this->year, $this->month)
            ->where('invoice_paid', false)->get();

        if ($openTransactions->isEmpty()) {
            $this->dispatch('notify', type: 'warning', message: 'Não há valores em aberto nesta fatura.');

            return;
        }

        $total = (float) $openTransactions->sum('amount');
        [, $closing] = $this->creditCard->invoicePeriod($this->year, $this->month);

        auth()->user()->transactions()->create([
            'account_id' => $this->payFromAccountId,
            'type' => 'despesa',
            'payment_method' => 'debito',
            'description' => "Pagamento fatura {$this->creditCard->name} - ".ucfirst($closing->translatedFormat('M/Y')),
            'amount' => $total,
            'date' => now()->format('Y-m-d'),
            'is_paid' => true,
        ]);

        foreach ($openTransactions as $transaction) {
            $transaction->update(['invoice_paid' => true]);
        }

        $this->payFromAccountId = null;

        $this->dispatch('notify', type: 'success', message: 'Fatura paga com sucesso.');
    }

    public function with(): array
    {
        $transactions = $this->creditCard->invoiceTransactionsQuery($this->year, $this->month)->get();
        $closing = $this->creditCard->invoiceClosingDate($this->year, $this->month);
        $due = $this->creditCard->invoiceDueDate($this->year, $this->month);
        $openAmount = (float) $transactions->where('invoice_paid', false)->sum('amount');
        $paidAmount = (float) $transactions->where('invoice_paid', true)->sum('amount');

        return [
            'transactions' => $transactions,
            'closingDate' => $closing,
            'dueDate' => $due,
            'isClosed' => $closing->lessThan(now()),
            'total' => (float) $transactions->sum('amount'),
            'openAmount' => $openAmount,
            'paidAmount' => $paidAmount,
            'isFullyPaid' => $transactions->isNotEmpty() && $openAmount <= 0,
            'accounts' => auth()->user()->accounts()->where('is_active', true)->get(),
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Fatura &middot; {{ $creditCard->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-4 flex items-center justify-between">
                <button type="button" wire:click="previousMonth" class="px-3 py-1.5 rounded-md text-sm text-gray-600 hover:bg-gray-100">← Anterior</button>
                <span class="font-semibold text-gray-800">{{ ucfirst($closingDate->translatedFormat('F \d\e Y')) }}</span>
                <button type="button" wire:click="nextMonth" class="px-3 py-1.5 rounded-md text-sm text-gray-600 hover:bg-gray-100">Próxima →</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Total da fatura</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900">R$ {{ number_format($total, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Fecha em / Vence em</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ $closingDate->format('d/m/Y') }} / {{ $dueDate->format('d/m/Y') }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <p class="text-sm text-gray-500">Situação</p>
                    <p class="mt-1">
                        @if($transactions->isEmpty())
                            <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">Sem lançamentos</span>
                        @elseif($isFullyPaid)
                            <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700">Paga</span>
                        @elseif($isClosed)
                            <span class="px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700">Fechada &middot; em aberto</span>
                        @else
                            <span class="px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-700">Aberta (ainda acumulando)</span>
                        @endif
                    </p>
                </div>
            </div>

            @if($isClosed && ! $isFullyPaid && $transactions->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800 mb-1">Pagar fatura</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Valor em aberto: <span class="font-semibold text-red-600">R$ {{ number_format($openAmount, 2, ',', '.') }}</span>
                        @if($paidAmount > 0)
                            <span class="text-gray-400">(R$ {{ number_format($paidAmount, 2, ',', '.') }} já pago)</span>
                        @endif
                    </p>
                    <form wire:submit="payInvoice" class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[220px]">
                            <x-input-label for="payFromAccountId" value="Pagar com a conta" />
                            <select id="payFromAccountId" wire:model="payFromAccountId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Selecione</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('payFromAccountId')" class="mt-1" />
                        </div>
                        <x-primary-button type="submit">Marcar fatura como paga</x-primary-button>
                    </form>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($transactions as $t)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $t->date->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    {{ $t->description }}
                                    @if($t->is_installment)
                                        <span class="ml-1" title="Parcela {{ $t->installment_number }} de {{ $t->installment_total }}">🧾</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $t->category?->name }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">R$ {{ number_format($t->amount, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-sm">
                                    @if($t->invoice_paid)
                                        <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Paga</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700">Em aberto</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-6 text-center text-sm text-gray-500">Nenhum lançamento nesta fatura.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <a href="{{ route('credit-cards.index') }}" wire:navigate class="inline-block text-sm text-indigo-600 hover:underline">← Voltar para cartões</a>
        </div>
    </div>
