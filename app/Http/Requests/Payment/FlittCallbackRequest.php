<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class FlittCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'order_status' => ['required', 'string'],
            'response_status' => ['required', 'string'],
            'signature' => ['required', 'string'],
            'rectoken' => ['nullable', 'string'],
            'masked_card' => ['nullable', 'string'],
        ];
    }

    /**
     * The body as Flitt sent it: the signature covers the original values, before the framework
     * trims strings and nulls empty ones.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $decoded = $this->isJson() ? json_decode($this->getContent(), true) : null;

        return is_array($decoded) ? $decoded : $this->post();
    }
}
