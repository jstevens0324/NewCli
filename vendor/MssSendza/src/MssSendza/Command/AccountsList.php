<?php

namespace MssSendza\Command;

use Zend\Http\Request;

class AccountsList extends AbstractCommand
{
    public function getEndpoint()
    {
        return 'Account/Accounts';
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
