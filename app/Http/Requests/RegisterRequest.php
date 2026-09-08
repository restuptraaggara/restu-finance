<?php

namespace App\Http\Requests;

class RegisterRequest extends BaseApiRequest
{
    /**
     * Endpoint publik - siapapun dapat melakukan registrasi.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi pendaftaran user baru.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^(?!\s*$).+/',
            ],
            'email' => [
                'required',
                'email',
                'max:150',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'registration_key' => [
                'required',
                'string',
            ],
        ];
    }

    /**
     * Pesan kustom validasi registrasi.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.regex' => 'Nama tidak boleh berupa spasi saja.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email tersebut sudah terdaftar.',
            'password.required' => 'Kata sandi (password) wajib diisi.',
            'password.min' => 'Kata sandi minimal harus terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'registration_key.required' => 'Kode akses pendaftaran wajib diisi.',
        ];
    }
}
