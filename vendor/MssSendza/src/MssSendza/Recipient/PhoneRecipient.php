<?php

namespace MssSendza\Recipient;

use MssSendza\Cast;

class PhoneRecipient extends AbstractRecipient
{
    public function getType()
    {
        return Cast::TYPE_PHONE;
    }
}