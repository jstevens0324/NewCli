<?php

namespace MssSendza\Command;

use Zend\Http\Request;

class ResponsesList extends AbstractCommand
{
    public function getEndpoint()
    {
        return 'Cast/Accounts/%accountId%/Responses';
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
