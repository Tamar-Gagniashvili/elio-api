<?php

namespace App\Http\Integrations\Sms\Requests;

use App\Data\Sms\VerifyOtpRequestData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class VerifyOtpRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(protected readonly VerifyOtpRequestData $data) {}

    public function resolveEndpoint(): string
    {
        return '/otp/verify';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return $this->data->toArray();
    }
}
