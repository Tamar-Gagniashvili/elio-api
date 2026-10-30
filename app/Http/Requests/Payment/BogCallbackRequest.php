<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BogCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', Rule::in(['order_payment'])],
            'zoned_request_time' => ['required', 'string'],
            'body' => ['required', 'array'],
        ];
    }
}
