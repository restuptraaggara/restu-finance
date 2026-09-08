<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends BaseApiRequest
{
    /**
     * Aturan validasi untuk pembaruan kategori.
     */
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : null;
        $categoryId = $this->route('id') ?: $this->route('category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^(?!\s*$).+/',
                Rule::unique('categories', 'name')->where('user_id', $userId)->ignore($categoryId),
            ],
            'type' => ['sometimes', 'required', 'string', 'in:income,expense'],
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
            'type.in' => 'Tipe kategori hanya boleh bernilai "income" atau "expense".',
        ];
    }
}
