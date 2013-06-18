<?php

namespace MssSendza\Command;

use InvalidArgumentException;

abstract class AbstractCommand
{
    protected $dataKey      = null;
    protected $requiredData = array();

    abstract public function getMethod();
    abstract public function getEndpoint();

    public function getDataKey()
    {
        return $this->dataKey;
    }

    public function getRequiredData()
    {
        return $this->requiredData;
    }
}