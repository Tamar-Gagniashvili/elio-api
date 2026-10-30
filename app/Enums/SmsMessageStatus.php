<?php

namespace App\Enums;

enum SmsMessageStatus: string
{
    case Delivered = 'delivered';
    case Submitted = 'submitted';
    case NotSent = 'not_sent';
}
