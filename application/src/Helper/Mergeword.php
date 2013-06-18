<?php

namespace Application\Helper;

use Application\Service\Db as DbService,
    MssMessage\Service\Mergeword as MergewordService,
    Symfony\Component\Console\Helper\Helper as SymfonyHelper;

class Mergeword extends SymfonyHelper
{
    /**
     * @var MssMessage\Service\Mergeword
     */
    private $mergeword;
    
    /**
     * Helper for accessing the Mss mergeword service.
     * 
     * @param MssMessage\Service\Mergeword $mergeword
     */
    public function __construct(MergewordService $mergeword)
    {
        $this->mergeword = $mergeword;
    }
    
    public function getMergewordService()
    {
        return $this->mergeword;
    }

    /**
     * Abstract implementation.
     * 
     * @return string
     */
    public function getName()
    {
        return 'mergeword';
    }
}