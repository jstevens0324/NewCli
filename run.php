<?php
use Application\Application,
    Application\Helper,
    Application\Service\Db as DbService,
    MssMessage\Mapper\Messenger\DoctrineDbal as MessengerMapper,
    MssMessage\Mapper\Mergeword\DoctrineDbal as MergewordMapper,
    MssMessage\Service\Mergeword as MergewordService,
    MssMessage\Service\Messenger as MessengerService;

// CLI process, disable time limit
set_time_limit(0);

chdir(__DIR__);
require 'autoload_register.php';

// Swiftmailer required include
require 'vendor/SwiftMailer/lib/swift_required.php';

$config = include 'config.php';
$db     = new DbService($config['connection']);
$app    = new Application($db);

$helpers = array(
    new Helper\Connection($db),
    new Helper\Mergeword(new MergewordService(new MergewordMapper($db->getConnection('default')))),
    new Helper\Messenger(new MessengerService(new MessengerMapper($db->getConnection('default'))))
);

$app->setName($config['name']);
$app->setVersion($config['version']);
$app->addCommands($config['commands']);
$app->setHelperSet(new \Symfony\Component\Console\Helper\HelperSet($helpers));
$app->run();