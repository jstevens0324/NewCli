<?php

namespace MssSendza\Command;

use Zend\Http\Request;

class UsersListEmail extends AbstractCommand
{
    public function getEndpoint()
    {
        return 'Account/Emails/%email%/LoginUsers';
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
