<?php

namespace MssSendza\Recipient;

use MssSendza\Cast;

class EmailRecipient extends AbstractRecipient
{
    public function getType()
    {
        return Cast::TYPE_EMAIL;
    }
}