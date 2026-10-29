<?php

namespace App\Http\Requests\Sms;

use Illuminate\Foundation\Http\FormRequest;

class SmsCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string'],
            'report_uuid' => ['required', 'string'],
            'message_status' => ['required', 'integer'],
            'event_type' => ['required', 'string'],
            'part_no' => ['required', 'integer'],
            'part_total' => ['required', 'integer'],
            'receiver' => ['required', 'string'],
            'sender' => ['required', 'string'],
            'created_at' => ['required', 'string'],
            'result' => ['required', 'string'],
        ];
    }
}
