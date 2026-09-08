<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateDebtRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : null;

        return [
            'type' => ['sometimes', 'required', 'string', 'in:debt,credit'],
            'person_name' => ['sometimes', 'required', 'string', 'max:150'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:1'],
            'wallet_id' => [
                'nullable',
                Rule::exists('wallets', 'id')->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }),
            ],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
