<?php

namespace App\Http\Requests;

use Carbon\Carbon;

class StoreRecurringRequest extends BaseApiRequest
{
    /**
     * Aturan validasi untuk pembuatan recurring transaction baru.
     */
    public function rules(): array
    {
        return [
            'wallet' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:daily,weekly,monthly,yearly'],
            'start_date' => ['nullable', 'date'],
            'next_date' => ['required', 'date'],
            'end_date' => [
                'nullable',
                'date',
                function ($attr, $value, $fail) {
                    $startDate = $this->input('start_date') ?? $this->input('next_date');
                    if ($value && $startDate && Carbon::parse($value)->lt(Carbon::parse($startDate))) {
                        $fail('end_date tidak boleh lebih awal dari start_date.');
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validasi tambahan untuk memastikan wallet & category milik user.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->getAuthUser();
            if (!$user) {
                return;
            }

            if ($this->filled('wallet')) {
                $walletExists = $user->wallets()->where('name', $this->input('wallet'))->exists();
                if (!$walletExists) {
                    $validator->errors()->add('wallet', 'Wallet tidak ditemukan atau bukan milik Anda.');
                }
            }

            if ($this->filled('category')) {
                $categoryExists = $user->categories()->where('name', $this->input('category'))->exists();
                if (!$categoryExists) {
                    $validator->errors()->add('category', 'Category tidak ditemukan atau bukan milik Anda.');
                }
            }
        });
    }
}
