<?php

namespace App\Http\Requests;

class ImportTransactionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File CSV transaksi wajib diunggah.',
            'file.file' => 'Unggahan harus berupa berkas yang valid.',
            'file.max' => 'Ukuran file CSV maksimal 5MB.',
        ];
    }
}