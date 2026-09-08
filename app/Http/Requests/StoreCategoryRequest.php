<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreCategoryRequest extends BaseApiRequest
{
    /**
     * Aturan validasi untuk penambahan kategori baru.
     */
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^(?!\s*$).+/',
                Rule::unique('categories', 'name')->where('user_id', $userId),
            ],
            'type' => ['required', 'string', 'in:income,expense'],
        ];
    }

    /**
     * Pesan kustom untuk validasi kategori.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.string' => 'Nama kategori harus berupa teks.',
            'name.regex' => 'Nama kategori tidak boleh kosong.',
            'name.unique' => 'Kategori dengan nama tersebut sudah ada.',
            'type.required' => 'Tipe kategori (type) wajib diisi.',
            'type.in' => 'Tipe kategori hanya boleh bernilai "income" atau "expense".',
        ];
    }
}
