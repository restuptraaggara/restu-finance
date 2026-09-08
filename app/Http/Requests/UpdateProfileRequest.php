<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends BaseApiRequest
{
    /**
     * Aturan validasi untuk pembaruan profil pengguna.
     */
    public function rules(): array
    {
        $user = $this->getAuthUser();
        $userId = $user ? $user->id : null;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^(?!\s*$).+/',
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'theme' => [
                'sometimes',
                'required',
                'string',
                'in:dark,light,system,special',
            ],
            'currency' => [
                'sometimes',
                'required',
                'string',
                'in:IDR,USD,EUR,SGD,MYR,JPY,GBP,AUD',
            ],
            'language' => [
                'sometimes',
                'required',
                'string',
                'in:id,en',
            ],
            'current_password' => [
                'required_with:password',
                'nullable',
                'string',
            ],
            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
            ],
        ];
    }

    /**
     * Pesan kustom untuk validasi profil.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengguna wajib diisi.',
            'name.regex' => 'Nama pengguna tidak boleh kosong.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email tersebut sudah digunakan oleh pengguna lain.',
            'theme.in' => 'Pilihan tema hanya boleh "dark", "light", "system", atau "special".',
            'currency.in' => 'Mata uang yang dipilih tidak didukung.',
            'language.in' => 'Pilihan bahasa hanya boleh "id" (Indonesia) atau "en" (English).',
            'current_password.required_with' => 'Password saat ini wajib diisi untuk mengganti password.',
            'password.min' => 'Password baru minimal harus terdiri dari 8 karakter.',
        ];
    }

    /**
     * Validasi tambahan untuk memeriksa kecocokan password saat ini.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->getAuthUser();
            if ($user && $this->filled('password')) {
                if (!Hash::check($this->current_password, $user->password)) {
                    $validator->errors()->add('current_password', 'Password saat ini yang Anda masukkan tidak sesuai.');
                }
            }
        });
    }
}
