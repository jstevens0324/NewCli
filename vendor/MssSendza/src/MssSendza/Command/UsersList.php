<?php

namespace MssSendza\Command;

use Zend\Http\Request;

class UsersList extends AbstractCommand
{
    public function getEndpoint()
    {
        return 'Account/Accounts/%accountId%/LoginUsers';
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
