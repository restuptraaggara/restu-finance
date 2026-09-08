<?php

namespace App\Http\Requests;

class SendChatMessageRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->getAuthUser() !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Pesan tidak boleh kosong.',
            'message.max' => 'Pesan maksimal 2000 karakter.',
        ];
    }
}