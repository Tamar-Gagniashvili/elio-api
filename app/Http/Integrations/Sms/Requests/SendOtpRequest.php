<?php

namespace App\Http\Integrations\Sms\Requests;

use App\Data\Sms\OtpResponseData;
use App\Data\Sms\SendOtpRequestData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class SendOtpRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(protected readonly SendOtpRequestData $data) {}

    public function resolveEndpoint(): string
    {
        return '/otp/send';
    }

    public function createDtoFromResponse(Response $response): OtpResponseData
    {
        return OtpResponseData::from((array) $response->json());
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return $this->data->toArray();
    }
}
