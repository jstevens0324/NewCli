<?php

namespace MssSendza\Command;

use Zend\Http\Request;

class AccountsGet extends AbstractCommand
{
    public function getEndpoint()
    {
        return 'Account/Accounts/%accountId%';
    }

    public function getMethod()
    {
        return Request::METHOD_GET;
    }

    public function getData()
    {
        return array();
    }
}
