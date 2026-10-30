<?php

namespace App\Http\Controllers;

use App\Enums\SmsMessageStatus;
use App\Http\Requests\Sms\SmsCallbackRequest;
use App\Models\SmsMessage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SmsCallbackController extends Controller
{
    public function submit(SmsCallbackRequest $request): Response
    {
        Log::info('SMS submit callback received', $request->all());

        $smsMessage = SmsMessage::where('uuid', $request->input('uuid'))->first();

        if (! $smsMessage) {
            return response()->noContent(404);
        }

        /** @var int|string $submitStatus */
        $submitStatus = $request->input('message_status');
        if ((int) $submitStatus === 0) {
            $smsMessage->increment('parts_submitted');
            $smsMessage->update(['sms_message_status' => SmsMessageStatus::Submitted]);
        } else {
            $smsMessage->update(['sms_message_status' => SmsMessageStatus::NotSent]);
        }

        return response()->noContent();
    }

    public function delivery(SmsCallbackRequest $request): Response
    {
        Log::info('SMS delivery callback received', $request->all());

        $smsMessage = SmsMessage::where('uuid', $request->input('uuid'))->first();

        if (! $smsMessage) {
            return response()->noContent(404);
        }

        /** @var int|string $deliveryStatus */
        $deliveryStatus = $request->input('message_status');
        if ((int) $deliveryStatus === 2) {
            $smsMessage->increment('parts_delivered');
            $smsMessage->refresh();

            $status = $smsMessage->parts_delivered >= $smsMessage->total_parts
                ? SmsMessageStatus::Delivered
                : $smsMessage->sms_message_status;

            $smsMessage->update(['sms_message_status' => $status]);
        } else {
            $smsMessage->update(['sms_message_status' => SmsMessageStatus::NotSent]);
        }

        return response()->noContent();
    }
}
