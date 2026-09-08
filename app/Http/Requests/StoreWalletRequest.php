<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreWalletRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : 0;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^(?!\s*$).+/',
                Rule::unique('wallets', 'name')->where('user_id', $userId),
            ],
            'type' => ['nullable', 'string', 'max:50'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama dompet wajib diisi.',
            'name.string' => 'Nama dompet harus berupa teks.',
            'name.regex' => 'Nama dompet tidak boleh kosong.',
            'name.unique' => 'Dompet dengan nama tersebut sudah ada.',
            'initial_balance.numeric' => 'Saldo awal harus berupa angka.',
            'initial_balance.min' => 'Saldo awal tidak boleh bernilai negatif.',
            'opening_balance.numeric' => 'Saldo awal harus berupa angka.',
            'opening_balance.min' => 'Saldo awal tidak boleh bernilai negatif.',
        ];
    }
}
