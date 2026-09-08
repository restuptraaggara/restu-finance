<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : 0;

        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $userId)],
            'category' => ['nullable', 'string', 'max:100'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'limit' => ['nullable', 'numeric', 'gt:0'],
            'limit_amount' => ['nullable', 'numeric', 'gt:0'],
            'period' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Kategori yang dipilih tidak valid atau tidak ditemukan.',
            'amount.numeric' => 'Nominal anggaran harus berupa angka.',
            'amount.gt' => 'Nominal anggaran harus lebih dari 0.',
            'limit.numeric' => 'Nominal anggaran harus berupa angka.',
            'limit.gt' => 'Nominal anggaran harus lebih dari 0.',
            'limit_amount.numeric' => 'Nominal anggaran harus berupa angka.',
            'limit_amount.gt' => 'Nominal anggaran harus lebih dari 0.',
        ];
    }
}
