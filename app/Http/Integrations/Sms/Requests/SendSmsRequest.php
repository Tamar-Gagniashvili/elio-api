<?php

namespace App\Http\Integrations\Sms\Requests;

use App\Data\Sms\SendSmsRequestData;
use App\Data\Sms\SmsResponseData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class SendSmsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(protected readonly SendSmsRequestData $data) {}

    public function resolveEndpoint(): string
    {
        return '/sms/one';
    }

    public function createDtoFromResponse(Response $response): SmsResponseData
    {
        return SmsResponseData::from((array) $response->json());
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return $this->data->toArray();
    }
}
