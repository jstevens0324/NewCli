<?php

namespace Application\Helper;

use Application\Logger\File as FileLogger,
    Symfony\Component\Console\Helper\Helper as SymfonyHelper;

class Logger extends SymfonyHelper
{
    /**
     * @var Application\Logger\File
     */
    private $logger;
    
    /**
     * Helper for accessing the logger.
     * 
     * @param Application\Logger\FileLogger $logger
     */
    public function __construct(FileLogger $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Gets the logger
     * 
     * @return Application\Logger\FileLogger
     */
    public function getLogger()
    {
        return $this->logger;
    }
    
    /**
     * Abstract implementation.
     * 
     * @return string
     */
    public function getName()
    {
        return 'logger';
    }
}