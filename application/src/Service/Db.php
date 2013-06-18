<?php

namespace Application\Service;

use InvalidArgumentException,
    Doctrine\DBAL\Connection,
    Doctrine\DBAL\DriverManager;

class Db
{
    /**
     * @var array
     */
    protected $config = array();
    
    /**
     * @var array
     */
    protected $connections = array();
    
    /**
     * Service to manage Db connections using Doctrine 2 DBAL. Input config
     * is expected to be an array keyed by connection name with connection 
     * parameters.
     * 
     * @param $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Gets a DB connection and lazy-load instantiates if necessary.
     * 
     * @return Doctrine\DBAL\Connection
     */
    public function getConnection($name)
    {
        if (!isset($this->config[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Connection with name "%s" is not defined',
                $name
            ));
        }
        
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = DriverManager::getConnection($this->config[$name]);
        }
        return $this->connections[$name];
    }
}