<?php

namespace MssSendza\Command;

use Zend\Http\Request;

class UsersCreate extends AbstractCommand
{
    protected $dataKey      = 'LoginUser';
    protected $requiredData = array(
        'Username',
        'Email',
        'Password'
    );

    public function getEndpoint()
    {
        return 'Account/Accounts/%accountId%/LoginUsers';
    }

    public function getMethod()
    {
        return Request::METHOD_POST;
    }
}
