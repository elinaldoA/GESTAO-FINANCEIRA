<?php

use App\Models\CategoryRule;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, WithPagination;

    #[Validate('required|string|max:255')]
    public string $description = '';

    #[Validate('required|numeric|min:0.01')]
    public string $amount = '';

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('required|in:receita,despesa,transferencia')]
    public string $type = 'despesa';

    public string $payment_method = 'pix';

    public ?int $account_id = null;

    public ?int $credit_card_id = null;

    public ?int $destination_account_id = null;

    public ?int $category_id = null;

    public bool $is_paid = true;

    public string $notes = '';

    public bool $is_recurring = false;

    public string $recurrence_interval = 'mensal';

    public int $recurrence_count = 12;

    public int $installments = 1;

    public $attachment = null;

    public ?string $existingAttachmentPath = null;

    public ?string $existingAttachmentName = null;

    public ?int $editingId = null;

    // filters
    public string $filterType = '';

    public string $filterMonth = '';

    public ?int $filterCategoryId = null;

    public string $filterPaymentMethod = '';

    public string $filterReconciled = '';

    #[Url(as: 'busca', except: '')]
    public string $filterSearch = '';

    // bulk actions
    public array $selected = [];

    public bool $selectAllPage = false;

    public string $bulkCategoryId = '';

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function updated($property): void
    {
        if (str_starts_with($property, 'filter')) {
            $this->selected = [];
            $this->selectAllPage = false;
        }
    }

    public function updatedSelectAllPage(bool $value): void
    {
        $pageIds = $this->filteredQuery()->orderByDesc('date')->orderByDesc('id')
            ->forPage($this->getPage(), 15)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->selected = $value
            ? array_values(array_unique(array_merge($this->selected, $pageIds)))
            : array_values(array_diff($this->selected, $pageIds));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAllPage = false;
    }

    private function selectedTransactions()
    {
        return auth()->user()->transactions()->whereIn('id', $this->selected)->get();
    }

    public function bulkMarkPaid(): void
    {
        auth()->user()->transactions()->whereIn('id', $this->selected)->update(['is_paid' => true]);
        $this->dispatch('notify', type: 'success', message: 'Transações marcadas como pagas.');
        $this->clearSelection();
    }

    public function bulkMarkUnpaid(): void
    {
        auth()->user()->transactions()->whereIn('id', $this->selected)->update(['is_paid' => false]);
        $this->dispatch('notify', type: 'success', message: 'Transações marcadas como pendentes.');
        $this->clearSelection();
    }

    public function bulkReconcile(): void
    {
        auth()->user()->transactions()->whereIn('id', $this->selected)->whereNull('reconciled_at')->update(['reconciled_at' => now()]);
        $this->dispatch('notify', type: 'success', message: 'Transações marcadas como conciliadas.');
        $this->clearSelection();
    }

    public function toggleReconciled(Transaction $transaction): void
    {
        $this->authorize('update', $transaction);
        $transaction->update(['reconciled_at' => $transaction->reconciled_at ? null : now()]);
    }

    public function bulkAssignCategory(): void
    {
        if ($this->bulkCategoryId === '') {
            return;
        }

        $categoryOwned = auth()->user()->categories()->whereKey($this->bulkCategoryId)->exists();

        if (! $categoryOwned) {
            abort(403);
        }

        auth()->user()->transactions()->whereIn('id', $this->selected)->update(['category_id' => $this->bulkCategoryId]);
        $this->dispatch('notify', type: 'success', message: 'Categoria aplicada às transações selecionadas.');
        $this->bulkCategoryId = '';
        $this->clearSelection();
    }

    public function bulkDelete(): void
    {
        foreach ($this->selectedTransactions() as $transaction) {
            if ($transaction->attachment_path) {
                Storage::disk('public')->delete($transaction->attachment_path);
            }
            $transaction->delete();
        }

        $this->dispatch('notify', type: 'success', message: 'Transações selecionadas excluídas com sucesso.');
        $this->clearSelection();
    }

    public function updatedType(): void
    {
        $this->payment_method = 'pix';
        $this->credit_card_id = null;
        $this->installments = 1;
    }

    public function updatedPaymentMethod(): void
    {
        if ($this->payment_method !== 'credito') {
            $this->installments = 1;
        }
    }

    protected function rulesForType(): array
    {
        $rules = [
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'type' => 'required|in:receita,despesa,transferencia',
        ];

        $ownAccount = Rule::exists('accounts', 'id')->where('user_id', auth()->id());
        $ownCategory = Rule::exists('categories', 'id')->where('user_id', auth()->id());
        $ownCreditCard = Rule::exists('credit_cards', 'id')->where('user_id', auth()->id());

        if ($this->type === 'transferencia') {
            $rules['account_id'] = ['required', $ownAccount];
            $rules['destination_account_id'] = ['required', 'different:account_id', $ownAccount];
        } else {
            $rules['category_id'] = ['nullable', $ownCategory];
            $rules['payment_method'] = $this->type === 'despesa'
                ? 'required|in:pix,debito,credito,dinheiro,boleto,outro'
                : 'required|in:pix,debito,dinheiro,boleto,outro';

            if ($this->type === 'despesa' && $this->payment_method === 'credito') {
                $rules['credit_card_id'] = ['required', $ownCreditCard];
            } else {
                $rules['account_id'] = ['required', $ownAccount];
            }
        }

        return $rules;
    }

    public function save(): void
    {
        $rules = $this->rulesForType();
        $rules['attachment'] = 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png';

        if ($this->editingId === null && $this->type !== 'transferencia' && $this->is_recurring) {
            $rules['recurrence_interval'] = 'required|in:mensal,semanal,anual';
            $rules['recurrence_count'] = 'required|integer|min:1|max:60';
        }

        $isNew = $this->editingId === null;
        $isCredit = $this->type === 'despesa' && $this->payment_method === 'credito';

        if ($isNew && $isCredit) {
            $rules['installments'] = 'required|integer|min:1|max:24';
        }

        $this->validate($rules);

        $resolvedCategoryId = $this->category_id;
        if ($this->type !== 'transferencia' && ! $resolvedCategoryId) {
            $resolvedCategoryId = CategoryRule::matchCategoryFor(auth()->id(), $this->description);
        }

        $data = [
            'description' => $this->description,
            'amount' => $this->amount,
            'date' => $this->date,
            'type' => $this->type,
            'payment_method' => $this->type === 'transferencia' ? null : $this->payment_method,
            'is_paid' => $this->is_paid,
            'notes' => $this->notes,
            'category_id' => $this->type === 'transferencia' ? null : $resolvedCategoryId,
            'account_id' => $this->type === 'transferencia' ? $this->account_id : ($isCredit ? null : $this->account_id),
            'credit_card_id' => $isCredit ? $this->credit_card_id : null,
            'destination_account_id' => $this->type === 'transferencia' ? $this->destination_account_id : null,
        ];

        if ($isNew) {
            $data['is_recurring'] = $this->type !== 'transferencia' && $this->is_recurring;
            $data['recurrence_interval'] = $data['is_recurring'] ? $this->recurrence_interval : null;
        }

        if ($this->attachment) {
            if (! $isNew && $this->existingAttachmentPath) {
                Storage::disk('public')->delete($this->existingAttachmentPath);
            }

            $data['attachment_path'] = $this->attachment->store('attachments/'.auth()->id(), 'public');
            $data['attachment_name'] = $this->attachment->getClientOriginalName();
        }

        if ($isNew && $isCredit && $this->installments > 1) {
            $transaction = $this->createInstallmentPurchase($data);
        } else {
            $transaction = auth()->user()->transactions()->updateOrCreate(['id' => $this->editingId], $data);

            if ($isNew && ($data['is_recurring'] ?? false)) {
                $this->generateRecurrences($transaction);
            }
        }

        $this->checkBudgetAlert($transaction);

        $this->resetForm();

        $this->dispatch('notify', type: 'success', message: $isNew ? 'Transação adicionada com sucesso.' : 'Transação atualizada com sucesso.');
    }

    private function createInstallmentPurchase(array $baseData): Transaction
    {
        $total = (float) $baseData['amount'];
        $n = $this->installments;
        $each = round($total / $n, 2);
        $roundingAdjustment = round($total - ($each * $n), 2);
        $firstDate = Carbon::parse($baseData['date']);
        $baseDescription = $baseData['description'];

        $first = null;

        for ($i = 1; $i <= $n; $i++) {
            $installmentData = array_merge($baseData, [
                'description' => "{$baseDescription} ({$i}/{$n})",
                'amount' => $each + ($i === $n ? $roundingAdjustment : 0),
                'date' => $firstDate->copy()->addMonths($i - 1),
                'installment_number' => $i,
                'installment_total' => $n,
                'parent_transaction_id' => $i > 1 ? $first?->id : null,
            ]);

            if ($i > 1) {
                unset($installmentData['attachment_path'], $installmentData['attachment_name']);
            }

            $transaction = auth()->user()->transactions()->create($installmentData);

            if ($i === 1) {
                $first = $transaction;
            }
        }

        return $first;
    }

    private function generateRecurrences(Transaction $parent): void
    {
        for ($i = 1; $i <= $this->recurrence_count; $i++) {
            $nextDate = match ($this->recurrence_interval) {
                'semanal' => $parent->date->copy()->addWeeks($i),
                'anual' => $parent->date->copy()->addYears($i),
                default => $parent->date->copy()->addMonths($i),
            };

            auth()->user()->transactions()->create([
                'account_id' => $parent->account_id,
                'credit_card_id' => $parent->credit_card_id,
                'category_id' => $parent->category_id,
                'type' => $parent->type,
                'payment_method' => $parent->payment_method,
                'description' => $parent->description,
                'amount' => $parent->amount,
                'date' => $nextDate,
                'is_paid' => false,
                'is_recurring' => true,
                'recurrence_interval' => $parent->recurrence_interval,
                'parent_transaction_id' => $parent->id,
                'notes' => $parent->notes,
            ]);
        }
    }

    private function checkBudgetAlert(Transaction $transaction): void
    {
        if ($transaction->type !== 'despesa' || ! $transaction->is_paid || ! $transaction->category_id) {
            return;
        }

        $budget = auth()->user()->budgets()
            ->where('category_id', $transaction->category_id)
            ->where('month', $transaction->date->month)
            ->where('year', $transaction->date->year)
            ->first();

        if (! $budget || (float) $budget->amount <= 0) {
            return;
        }

        $percent = ($budget->spent / (float) $budget->amount) * 100;
        $categoryName = $transaction->category->name ?? 'categoria';

        if ($percent >= 100) {
            $this->dispatch('notify', type: 'error', message: "Orçamento de \"{$categoryName}\" estourado! Já foi gasto ".number_format($percent, 0).'% do limite.');
        } elseif ($percent >= 80) {
            $this->dispatch('notify', type: 'warning', message: "Atenção: orçamento de \"{$categoryName}\" já atingiu ".number_format($percent, 0).'% do limite.');
        }
    }

    public function resetForm(): void
    {
        $this->reset([
            'description', 'amount', 'type', 'account_id', 'credit_card_id',
            'destination_account_id', 'category_id', 'notes', 'editingId',
            'is_recurring', 'attachment', 'existingAttachmentPath', 'existingAttachmentName',
        ]);
        $this->date = now()->format('Y-m-d');
        $this->type = 'despesa';
        $this->payment_method = 'pix';
        $this->is_paid = true;
        $this->recurrence_interval = 'mensal';
        $this->recurrence_count = 12;
        $this->installments = 1;
    }

    public function edit(Transaction $transaction): void
    {
        $this->authorize('update', $transaction);

        $this->editingId = $transaction->id;
        $this->description = $transaction->description;
        $this->amount = (string) $transaction->amount;
        $this->date = $transaction->date->format('Y-m-d');
        $this->type = $transaction->type;
        $this->payment_method = $transaction->payment_method ?? 'pix';
        $this->account_id = $transaction->account_id;
        $this->credit_card_id = $transaction->credit_card_id;
        $this->destination_account_id = $transaction->destination_account_id;
        $this->category_id = $transaction->category_id;
        $this->is_paid = $transaction->is_paid;
        $this->notes = $transaction->notes ?? '';
        $this->existingAttachmentPath = $transaction->attachment_path;
        $this->existingAttachmentName = $transaction->attachment_name;
        $this->attachment = null;
    }

    public function removeAttachment(): void
    {
        if ($this->editingId) {
            $transaction = auth()->user()->transactions()->find($this->editingId);

            if ($transaction && $transaction->attachment_path) {
                Storage::disk('public')->delete($transaction->attachment_path);
                $transaction->update(['attachment_path' => null, 'attachment_name' => null]);
            }
        }

        $this->existingAttachmentPath = null;
        $this->existingAttachmentName = null;
        $this->dispatch('notify', type: 'success', message: 'Anexo removido.');
    }

    public function delete(Transaction $transaction): void
    {
        $this->authorize('delete', $transaction);

        if ($transaction->attachment_path) {
            Storage::disk('public')->delete($transaction->attachment_path);
        }

        $transaction->delete();

        $this->dispatch('notify', type: 'success', message: 'Transação excluída com sucesso.');
    }

    public function deleteSeries(Transaction $transaction): void
    {
        $this->authorize('delete', $transaction);

        $parentId = $transaction->parent_transaction_id ?? $transaction->id;

        $toDelete = auth()->user()->transactions()
            ->where(fn ($q) => $q->where('id', $parentId)->orWhere('parent_transaction_id', $parentId))
            ->where('date', '>=', $transaction->date)
            ->get();

        foreach ($toDelete as $t) {
            if ($t->attachment_path) {
                Storage::disk('public')->delete($t->attachment_path);
            }
            $t->delete();
        }

        $this->dispatch('notify', type: 'success', message: 'Série de transações excluída com sucesso.');
    }

    private function filteredQuery()
    {
        $query = auth()->user()->transactions()->with(['category', 'account', 'creditCard', 'destinationAccount']);

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        if ($this->filterMonth && ! $this->filterSearch) {
            [$year, $month] = explode('-', $this->filterMonth);
            $query->whereYear('date', $year)->whereMonth('date', $month);
        }

        if ($this->filterCategoryId) {
            $query->where('category_id', $this->filterCategoryId);
        }

        if ($this->filterPaymentMethod) {
            $query->where('payment_method', $this->filterPaymentMethod);
        }

        if ($this->filterReconciled === 'sim') {
            $query->whereNotNull('reconciled_at');
        } elseif ($this->filterReconciled === 'nao') {
            $query->whereNull('reconciled_at');
        }

        if ($this->filterSearch) {
            $query->where(function ($q) {
                $q->where('description', 'like', '%'.$this->filterSearch.'%')
                    ->orWhere('notes', 'like', '%'.$this->filterSearch.'%');
            });
        }

        return $query;
    }

    public function exportCsv()
    {
        $transactions = $this->filteredQuery()->orderByDesc('date')->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Descrição', 'Tipo', 'Forma de pagamento', 'Parcela', 'Categoria', 'Conta', 'Cartão', 'Valor', 'Pago', 'Observações'], ';');

            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->date->format('d/m/Y'),
                    $t->description,
                    ucfirst($t->type),
                    $t->payment_method_label,
                    $t->is_installment ? "{$t->installment_number}/{$t->installment_total}" : '',
                    $t->category?->name,
                    $t->account?->name,
                    $t->creditCard?->name,
                    number_format($t->amount, 2, ',', '.'),
                    $t->is_paid ? 'Sim' : 'Não',
                    $t->notes,
                ], ';');
            }

            fclose($handle);
        }, 'transacoes_'.now()->format('Y-m-d_His').'.csv');
    }

    public function with(): array
    {
        $query = $this->filteredQuery();

        return [
            'transactions' => $query->orderByDesc('date')->orderByDesc('id')->paginate(15),
            'accounts' => auth()->user()->accounts()->where('is_active', true)->get(),
            'creditCards' => auth()->user()->creditCards()->where('is_active', true)->get(),
            'categories' => auth()->user()->categories()->where('type', $this->type === 'receita' ? 'receita' : 'despesa')->orderBy('name')->get(),
            'allCategories' => auth()->user()->categories()->orderBy('name')->get(),
            'paymentMethods' => Transaction::PAYMENT_METHODS,
        ];
    }
}; ?>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Transações') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">{{ $editingId ? 'Editar transação' : 'Nova transação' }}</h3>
                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <x-input-label for="type" value="Tipo" />
                            <select id="type" wire:model.live="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="despesa">Despesa</option>
                                <option value="receita">Receita</option>
                                <option value="transferencia">Transferência</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="description" value="Descrição" />
                            <x-text-input id="description" type="text" class="mt-1 block w-full" wire:model="description" />
                            <x-input-error :messages="$errors->get('description')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="amount" value="Valor" />
                            <x-text-input id="amount" type="number" step="0.01" class="mt-1 block w-full" wire:model="amount" />
                            <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <x-input-label for="date" value="Data" />
                            <x-text-input id="date" type="date" class="mt-1 block w-full" wire:model="date" />
                            <x-input-error :messages="$errors->get('date')" class="mt-1" />
                        </div>

                        @if($type === 'transferencia')
                            <div>
                                <x-input-label for="account_id" value="Conta de origem" />
                                <select id="account_id" wire:model="account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecione</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('account_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="destination_account_id" value="Conta de destino" />
                                <select id="destination_account_id" wire:model="destination_account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Selecione</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('destination_account_id')" class="mt-1" />
                            </div>
                        @else
                            <div>
                                <x-input-label for="category_id" value="Categoria" />
                                <select id="category_id" wire:model="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Sem categoria</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="payment_method" value="Forma de pagamento" />
                                <select id="payment_method" wire:model.live="payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="pix">Pix</option>
                                    <option value="debito">Débito</option>
                                    @if($type === 'despesa')
                                        <option value="credito">Crédito</option>
                                    @endif
                                    <option value="dinheiro">Dinheiro</option>
                                    <option value="boleto">Boleto</option>
                                    <option value="outro">Outro</option>
                                </select>
                                <x-input-error :messages="$errors->get('payment_method')" class="mt-1" />
                            </div>
                            @if($type === 'despesa' && $payment_method === 'credito')
                                <div>
                                    <x-input-label for="credit_card_id" value="Cartão de crédito" />
                                    <select id="credit_card_id" wire:model="credit_card_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="">Selecione</option>
                                        @foreach ($creditCards as $card)
                                            <option value="{{ $card->id }}">{{ $card->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('credit_card_id')" class="mt-1" />
                                </div>
                            @else
                                <div>
                                    <x-input-label for="account_id" value="Conta" />
                                    <select id="account_id" wire:model="account_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="">Selecione</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('account_id')" class="mt-1" />
                                </div>
                            @endif
                        @endif
                    </div>

                    <div class="flex items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="is_paid" class="rounded border-gray-300">
                            Pago / efetivado
                        </label>
                        <div class="flex-1">
                            <x-text-input type="text" class="block w-full" placeholder="Observações (opcional)" wire:model="notes" />
                        </div>
                    </div>

                    @if(! $editingId && $type === 'despesa' && $payment_method === 'credito')
                        <div class="border border-gray-200 rounded-md p-4">
                            <x-input-label for="installments" value="🧾 Parcelar em quantas vezes?" />
                            <select id="installments" wire:model.live="installments" class="mt-1 block w-full sm:w-48 rounded-md border-gray-300 shadow-sm">
                                @foreach (range(1, 24) as $n)
                                    <option value="{{ $n }}">{{ $n === 1 ? 'À vista (1x)' : "{$n}x" }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('installments')" class="mt-1" />
                            @if($installments > 1)
                                <p class="text-xs text-gray-500 mt-2">
                                    Serão criadas {{ $installments }} parcelas de aproximadamente
                                    R$ {{ $amount ? number_format(((float) $amount) / $installments, 2, ',', '.') : '0,00' }} cada, uma por mês.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if(! $editingId && $type !== 'transferencia' && $installments <= 1)
                        <div class="border border-gray-200 rounded-md p-4">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
                                <input type="checkbox" wire:model.live="is_recurring" class="rounded border-gray-300">
                                🔁 Repetir esta transação
                            </label>
                            @if($is_recurring)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                                    <div>
                                        <x-input-label for="recurrence_interval" value="Frequência" />
                                        <select id="recurrence_interval" wire:model="recurrence_interval" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                            <option value="semanal">Semanal</option>
                                            <option value="mensal">Mensal</option>
                                            <option value="anual">Anual</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label for="recurrence_count" value="Quantas repetições futuras" />
                                        <x-text-input id="recurrence_count" type="number" min="1" max="60" class="mt-1 block w-full" wire:model="recurrence_count" />
                                        <x-input-error :messages="$errors->get('recurrence_count')" class="mt-1" />
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Serão criadas {{ $recurrence_count }} transações futuras (pendentes), além desta.</p>
                            @endif
                        </div>
                    @endif

                    <div>
                        <x-input-label for="attachment" value="Comprovante (opcional)" />
                        @if($existingAttachmentName)
                            <div class="mt-1 flex items-center gap-2 text-sm text-gray-700">
                                <a href="{{ Storage::url($existingAttachmentPath) }}" target="_blank" class="text-indigo-600 hover:underline">📎 {{ $existingAttachmentName }}</a>
                                <button type="button" wire:click="removeAttachment" class="text-red-600 hover:underline text-xs">remover</button>
                            </div>
                        @else
                            <input id="attachment" type="file" wire:model="attachment" class="mt-1 block w-full text-sm text-gray-600" accept=".pdf,.jpg,.jpeg,.png">
                            <div wire:loading wire:target="attachment" class="text-xs text-gray-500 mt-1">Enviando...</div>
                        @endif
                        <x-input-error :messages="$errors->get('attachment')" class="mt-1" />
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button type="submit">{{ $editingId ? 'Salvar alterações' : 'Adicionar transação' }}</x-primary-button>
                        @if($editingId)
                            <x-secondary-button type="button" wire:click="resetForm">Cancelar</x-secondary-button>
                        @endif
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap gap-4 items-end">
                <div>
                    <x-input-label value="Buscar" />
                    <x-text-input type="text" class="mt-1" wire:model.live.debounce.400ms="filterSearch" placeholder="Descrição ou observação..." />
                </div>
                <div>
                    <x-input-label value="Mês" />
                    <input type="month" wire:model.live="filterMonth" class="mt-1 rounded-md border-gray-300 shadow-sm" @if($filterSearch) disabled title="Desative a busca para filtrar por mês" @endif>
                    @if($filterSearch)
                        <p class="text-xs text-gray-400 mt-1">Buscando em todo o histórico</p>
                    @endif
                </div>
                <div>
                    <x-input-label value="Tipo" />
                    <select wire:model.live="filterType" class="mt-1 rounded-md border-gray-300 shadow-sm">
                        <option value="">Todos</option>
                        <option value="receita">Receita</option>
                        <option value="despesa">Despesa</option>
                        <option value="transferencia">Transferência</option>
                    </select>
                </div>
                <div>
                    <x-input-label value="Categoria" />
                    <select wire:model.live="filterCategoryId" class="mt-1 rounded-md border-gray-300 shadow-sm">
                        <option value="">Todas</option>
                        @foreach ($allCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Forma de pagamento" />
                    <select wire:model.live="filterPaymentMethod" class="mt-1 rounded-md border-gray-300 shadow-sm">
                        <option value="">Todas</option>
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Conciliação" />
                    <select wire:model.live="filterReconciled" class="mt-1 rounded-md border-gray-300 shadow-sm">
                        <option value="">Todas</option>
                        <option value="sim">Conciliadas</option>
                        <option value="nao">Não conciliadas</option>
                    </select>
                </div>
                <div class="ms-auto flex gap-2">
                    <a href="{{ route('transactions.import') }}" wire:navigate>
                        <x-secondary-button type="button">⬆ Importar CSV</x-secondary-button>
                    </a>
                    <x-secondary-button type="button" wire:click="exportCsv">⬇ Exportar CSV</x-secondary-button>
                </div>
            </div>

            @if (count($selected))
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 flex flex-wrap items-center gap-3">
                    <span class="text-sm font-medium text-indigo-800">{{ count($selected) }} selecionada(s)</span>
                    <button type="button" wire:click="bulkMarkPaid" class="text-sm px-3 py-1.5 rounded-md bg-white border border-gray-300 hover:bg-gray-50">Marcar como paga</button>
                    <button type="button" wire:click="bulkMarkUnpaid" class="text-sm px-3 py-1.5 rounded-md bg-white border border-gray-300 hover:bg-gray-50">Marcar como pendente</button>
                    <button type="button" wire:click="bulkReconcile" class="text-sm px-3 py-1.5 rounded-md bg-white border border-gray-300 hover:bg-gray-50">Marcar como conciliada</button>
                    <div class="flex items-center gap-1">
                        <select wire:model="bulkCategoryId" class="text-sm rounded-md border-gray-300 shadow-sm">
                            <option value="">Categoria...</option>
                            @foreach ($allCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="bulkAssignCategory" class="text-sm px-3 py-1.5 rounded-md bg-white border border-gray-300 hover:bg-gray-50">Aplicar</button>
                    </div>
                    <button
                        type="button"
                        x-on:click="Swal.fire({icon:'warning',title:'Excluir {{ count($selected) }} transação(ões)?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.bulkDelete())"
                        class="text-sm px-3 py-1.5 rounded-md bg-white border border-red-300 text-red-600 hover:bg-red-50"
                    >Excluir selecionadas</button>
                    <button type="button" wire:click="clearSelection" class="text-sm text-gray-500 hover:underline ms-auto">Limpar seleção</button>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 w-8"><input type="checkbox" wire:model.live="selectAllPage" class="rounded border-gray-300"></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria / Conta</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pagamento</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($transactions as $t)
                            <tr>
                                <td class="px-4 py-4"><input type="checkbox" wire:model.live="selected" value="{{ $t->id }}" class="rounded border-gray-300"></td>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    {{ $t->description }}
                                    @if($t->is_recurring)
                                        <span class="ml-1" title="Transação recorrente">🔁</span>
                                    @endif
                                    @if($t->is_installment)
                                        <span class="ml-1" title="Parcela {{ $t->installment_number }} de {{ $t->installment_total }}">🧾</span>
                                    @endif
                                    @if($t->attachment_path)
                                        <a href="{{ Storage::url($t->attachment_path) }}" target="_blank" class="ml-1" title="Ver comprovante">📎</a>
                                    @endif
                                    @unless($t->is_paid)
                                        <span class="ml-1 text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">pendente</span>
                                    @endunless
                                    <button
                                        type="button"
                                        wire:click="toggleReconciled({{ $t->id }})"
                                        title="{{ $t->is_reconciled ? 'Conciliada — clique para desfazer' : 'Não conciliada — clique para marcar' }}"
                                        class="ml-1 text-xs px-1.5 py-0.5 rounded {{ $t->is_reconciled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-400' }}"
                                    >{{ $t->is_reconciled ? '✓ conciliada' : 'não conciliada' }}</button>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $t->date->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $t->category?->name }}
                                    @if($t->account) · {{ $t->account->name }} @endif
                                    @if($t->creditCard) · {{ $t->creditCard->name }} @endif
                                    @if($t->destinationAccount) → {{ $t->destinationAccount->name }} @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($t->payment_method)
                                        @php
                                            $pmColors = [
                                                'pix' => 'bg-teal-100 text-teal-700',
                                                'debito' => 'bg-blue-100 text-blue-700',
                                                'credito' => 'bg-purple-100 text-purple-700',
                                                'dinheiro' => 'bg-green-100 text-green-700',
                                                'boleto' => 'bg-orange-100 text-orange-700',
                                                'outro' => 'bg-gray-100 text-gray-600',
                                            ];
                                        @endphp
                                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $pmColors[$t->payment_method] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ $t->payment_method_label }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold {{ $t->type === 'receita' ? 'text-green-600' : ($t->type === 'despesa' ? 'text-red-600' : 'text-blue-600') }}">
                                    {{ $t->type === 'despesa' ? '-' : ($t->type === 'receita' ? '+' : '') }} R$ {{ number_format($t->amount, 2, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right space-x-2">
                                    <button wire:click="edit({{ $t->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir transação?',showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.delete({{ $t->id }}))" class="text-red-600 hover:underline">Excluir</button>
                                    @if($t->is_recurring || $t->is_installment)
                                        <button type="button" x-on:click="Swal.fire({icon:'warning',title:'Excluir esta e as futuras?',text:'Todas as ocorrências futuras desta série também serão excluídas.',showCancelButton:true,confirmButtonText:'Excluir série',cancelButtonText:'Cancelar',confirmButtonColor:'#dc2626'}).then((r) => r.isConfirmed && $wire.deleteSeries({{ $t->id }}))" class="text-red-600 hover:underline">Excluir série</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-6 text-center text-sm text-gray-500">Nenhuma transação encontrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $transactions->links() }}</div>
            </div>
        </div>
    </div>
