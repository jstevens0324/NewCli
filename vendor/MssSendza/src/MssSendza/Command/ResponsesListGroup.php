<?php

namespace MssSendza\Command;

use Zend\Http\Request;

class ResponsesListGroup extends AbstractCommand
{
    public function getEndpoint()
    {
        return 'Cast/Accounts/%accountId%/Groups/%groupId%/Responses';
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
