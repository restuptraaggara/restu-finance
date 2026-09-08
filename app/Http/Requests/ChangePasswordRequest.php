<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Hash;

class ChangePasswordRequest extends BaseApiRequest
{
    /**
     * Aturan validasi pergantian kata sandi.
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * Pesan kustom validasi pergantian kata sandi.
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal harus terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
        ];
    }

    /**
     * Validasi tambahan untuk mencocokkan kata sandi saat ini.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->getAuthUser();
            if ($user && $this->filled('current_password')) {
                if (!Hash::check($this->current_password, $user->password)) {
                    $validator->errors()->add('current_password', 'Kata sandi saat ini yang Anda masukkan tidak sesuai.');
                }
            }
        });
    }
}
