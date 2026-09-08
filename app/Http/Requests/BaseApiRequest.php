<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseApiRequest extends FormRequest
{
    /**
     * Tentukan apakah user terotorisasi untuk request ini (wajib terautentikasi).
     */
    public function authorize(): bool
    {
        return $this->getAuthUser() !== null;
    }

    /**
     * Tangani kegagalan otorisasi jika user belum terautentikasi (HTTP 401).
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            'data' => null,
        ], 401));
    }

    /**
     * Mengambil user yang terautentikasi (mendukung sesi & header testing X-User-Id).
     */
    public function getAuthUser(): ?User
    {
        $user = $this->user() ?: auth()->user();
        if ($user) {
            return $user;
        }

        if (app()->environment('testing')) {
            $userId = $this->header('X-User-Id');
            if ($userId) {
                return User::find($userId);
            }
        }

        return null;
    }

    /**
     * Standarisasi response error validasi (HTTP 422).
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
