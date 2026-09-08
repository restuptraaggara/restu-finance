<?php

namespace App\Http\Requests;

use App\Models\RecurringTransaction;
use Carbon\Carbon;

class UpdateRecurringRequest extends BaseApiRequest
{
    /**
     * Aturan validasi untuk update recurring transaction.
     */
    public function rules(): array
    {
        $recurringId = $this->route('id') ?: $this->route('recurring_transaction');
        $recurring = $recurringId ? RecurringTransaction::find($recurringId) : null;

        return [
            'wallet' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:income,expense'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['sometimes', 'in:daily,weekly,monthly,yearly'],
            'start_date' => ['nullable', 'date'],
            'next_date' => ['sometimes', 'date'],
            'end_date' => [
                'nullable',
                'date',
                function ($attr, $value, $fail) use ($recurring) {
                    $startDate = $this->input('start_date') ?? ($recurring ? $recurring->start_date : null);
                    if ($value && $startDate && Carbon::parse($value)->lt(Carbon::parse($startDate))) {
                        $fail('end_date tidak boleh lebih awal dari start_date.');
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validasi tambahan untuk memastikan wallet & category milik user jika disediakan.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->getAuthUser();
            if (!$user) {
                return;
            }

            if ($this->has('wallet')) {
                $walletExists = $user->wallets()->where('name', $this->input('wallet'))->exists();
                if (!$walletExists) {
                    $validator->errors()->add('wallet', 'Wallet tidak ditemukan atau bukan milik Anda.');
                }
            }

            if ($this->has('category')) {
                $categoryExists = $user->categories()->where('name', $this->input('category'))->exists();
                if (!$categoryExists) {
                    $validator->errors()->add('category', 'Category tidak ditemukan atau bukan milik Anda.');
                }
            }
        });
    }
}
