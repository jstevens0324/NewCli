<?php

namespace MssSendza\Command;

use Zend\Http\Request;

class AccountsCreate extends AbstractCommand
{
    protected $dataKey      = 'Account';
    protected $requiredData = array(
        'Name',
        'Email',
        'Phone'
    );

    public function getEndpoint()
    {
        return 'Account/Accounts';
    }

    public function getMethod()
    {
        return Request::METHOD_POST;
    }
}
