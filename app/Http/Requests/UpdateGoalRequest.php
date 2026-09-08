<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateGoalRequest extends BaseApiRequest
{
    /**
     * Aturan validasi untuk pembaruan target tabungan.
     */
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : null;
        $goalId = $this->route('id') ?: $this->route('goal');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^(?!\s*$).+/',
                Rule::unique('goals', 'name')->where('user_id', $userId)->ignore($goalId),
            ],
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^(?!\s*$).+/',
                Rule::unique('goals', 'name')->where('user_id', $userId)->ignore($goalId),
            ],
            'target_amount' => ['nullable', 'numeric', 'gt:0'],
            'target' => ['nullable', 'numeric', 'gt:0'],
            'saved_amount' => ['nullable', 'numeric', 'min:0'],
            'saved' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['sometimes', 'required', 'date'],
        ];
    }

    /**
     * Pesan kustom untuk validasi target tabungan.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama target tabungan wajib diisi.',
            'name.unique' => 'Target tabungan dengan nama tersebut sudah ada.',
            'title.required' => 'Nama target tabungan wajib diisi.',
            'title.unique' => 'Target tabungan dengan nama tersebut sudah ada.',
            'deadline.date' => 'Format tenggat waktu tidak valid.',
        ];
    }
}
