<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreTransactionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : 0;

        return [
            'type' => ['required', 'string', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'wallet_id' => ['nullable', 'integer', Rule::exists('wallets', 'id')->where('user_id', $userId)],
            'wallet' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $userId)],
            'category' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'method' => ['nullable', 'string', 'max:50'],
            'time' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:1000'],
            'goal' => ['nullable', 'string', 'max:100'],
            'split_needs' => ['nullable', 'array'],
            'split_needs.*.category' => ['nullable', 'string', 'max:100'],
            'split_needs.*.amount' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Tipe transaksi (type) wajib diisi.',
            'type.in' => 'Tipe transaksi hanya boleh bernilai "income" atau "expense".',
            'amount.required' => 'Nominal transaksi (amount) wajib diisi.',
            'amount.numeric' => 'Nominal transaksi harus berupa angka.',
            'amount.gt' => 'Nominal transaksi harus lebih dari 0.',
            'wallet_id.exists' => 'Dompet yang dipilih tidak valid atau tidak ditemukan.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid atau tidak ditemukan.',
            'transaction_date.date' => 'Format tanggal transaksi tidak valid.',
            'date.date' => 'Format tanggal transaksi tidak valid.',
        ];
    }
}
