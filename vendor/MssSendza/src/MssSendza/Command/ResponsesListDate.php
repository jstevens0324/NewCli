<?php

namespace MssSendza\Command;

use Zend\Http\Request;

class ResponsesListDate extends AbstractCommand
{
    public function getEndpoint()
    {
        return 'Cast/Accounts/%accountId%/Dates/%date%/Responses';
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
