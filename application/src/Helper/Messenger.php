<?php

namespace Application\Helper;

use Application\Service\Db as DbService,
    MssMessage\Service\Messenger as MessengerService,
    Symfony\Component\Console\Helper\Helper as SymfonyHelper;

class Messenger extends SymfonyHelper
{
    /**
     * @var MssMessage\Service\Messenger
     */
    private $messenger;
    
    /**
     * Helper for accessing the Mss messenger service.
     * 
     * @param MssMessage\Service\Messenger $messenger
     */
    public function __construct(MessengerService $messenger)
    {
        $this->messenger = $messenger;
    }
    
    public function getMessenger()
    {
        return $this->messenger;
    }

    /**
     * Abstract implementation.
     * 
     * @return string
     */
    public function getName()
    {
        return 'messenger';
    }
}