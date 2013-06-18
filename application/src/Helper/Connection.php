<?php

namespace Application\Helper;

use Application\Service\Db as DbService,
    Symfony\Component\Console\Helper\Helper as SymfonyHelper;

class Connection extends SymfonyHelper
{
    /**
     * @var Application\Service\Db
     */
    private $dbService;
    
    /**
     * Helper for accessing DBAL connections.
     * 
     * @param Application\Service\Db $dbService
     */
    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
    }
    
    /**
     * Gets a connection from the db service.
     * 
     * @param  string $name
     * @return Doctrine\DBAL\Connection
     */
    public function getConnection($name)
    {
        return $this->dbService->getConnection($name);
    }
    
    /**
     * Abstract implementation.
     * 
     * @return string
     */
    public function getName()
    {
        return 'connection';
    }
}