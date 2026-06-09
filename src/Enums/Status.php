<?php

namespace Jiannius\Mailog\Enums;

use Jiannius\Mailog\Traits\Enum;

enum Status: string
{
    use Enum;

    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
