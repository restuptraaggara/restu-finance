<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PayDebtRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : null;

        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'wallet_id' => [
                'nullable',
                Rule::exists('wallets', 'id')->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }),
            ],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
