<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreGoalRequest extends BaseApiRequest
{
    /**
     * Aturan validasi untuk pembuatan target tabungan baru.
     */
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : null;

        return [
            'name' => [
                'required_without:title',
                'nullable',
                'string',
                'max:100',
                'regex:/^(?!\s*$).+/',
                Rule::unique('goals', 'name')->where('user_id', $userId),
            ],
            'title' => [
                'required_without:name',
                'nullable',
                'string',
                'max:100',
                'regex:/^(?!\s*$).+/',
                Rule::unique('goals', 'name')->where('user_id', $userId),
            ],
            'target_amount' => ['required_without:target', 'nullable', 'numeric', 'gt:0'],
            'target' => ['required_without:target_amount', 'nullable', 'numeric', 'gt:0'],
            'saved_amount' => ['nullable', 'numeric', 'min:0'],
            'saved' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['required', 'date'],
        ];
    }

    /**
     * Pesan kustom untuk validasi target tabungan.
     */
    public function messages(): array
    {
        return [
            'name.required_without' => 'Nama target tabungan (name) wajib diisi.',
            'name.regex' => 'Nama target tabungan tidak boleh kosong.',
            'name.unique' => 'Target tabungan dengan nama tersebut sudah ada.',
            'title.required_without' => 'Nama target tabungan (title) wajib diisi.',
            'title.regex' => 'Nama target tabungan tidak boleh kosong.',
            'title.unique' => 'Target tabungan dengan nama tersebut sudah ada.',
            'target_amount.required_without' => 'Nominal target tabungan wajib diisi.',
            'target_amount.numeric' => 'Nominal target tabungan harus berupa angka.',
            'target_amount.gt' => 'Nominal target tabungan harus lebih dari 0.',
            'target.required_without' => 'Nominal target tabungan wajib diisi.',
            'target.numeric' => 'Nominal target tabungan harus berupa angka.',
            'target.gt' => 'Nominal target tabungan harus lebih dari 0.',
            'saved_amount.numeric' => 'Nominal tabungan awal harus berupa angka.',
            'saved_amount.min' => 'Nominal tabungan awal tidak boleh bernilai negatif.',
            'saved.numeric' => 'Nominal tabungan awal harus berupa angka.',
            'saved.min' => 'Nominal tabungan awal tidak boleh bernilai negatif.',
            'deadline.required' => 'Tenggat waktu (deadline) wajib diisi.',
            'deadline.date' => 'Format tenggat waktu tidak valid.',
        ];
    }
}
