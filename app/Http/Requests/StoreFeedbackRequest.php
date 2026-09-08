<?php

namespace App\Http\Requests;

class StoreFeedbackRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:bug,feature,general'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
