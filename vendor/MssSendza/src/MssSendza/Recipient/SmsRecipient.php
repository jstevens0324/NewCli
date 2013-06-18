<?php

namespace MssSendza\Recipient;

use MssSendza\Cast;

class SmsRecipient extends AbstractRecipient
{
    public function getType()
    {
        return Cast::TYPE_SMS;
    }
}