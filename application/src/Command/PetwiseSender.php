<?php

namespace Application\Command;

use DateTime,
    InvalidArgumentException,
    PDO,
    Application\Logger\File as FileLogger,
    MssMessage\Message,
    MssMessage\Recipient,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

class PetwiseSender extends AbstractPoller
{
    const BATCH_SIZE = 100;

    protected $pidfile = 'petwise-sender.pid';

    protected function configure()
    {
        $this->setName('petwise:sender')
             ->setDescription('Sends messages in the queue based on a polling interval for PetWise')
             ->setDefinition(array(
                new InputArgument(
                    'debug',
                    InputArgument::OPTIONAL,
                    'If enabled, messages are not marked as sent and are written to StdOut',
                    true
                ),
                new InputArgument(
                    'interval',
                    InputArgument::OPTIONAL,
                    'The polling interval, in seconds, to send messages',
                    5
                ),
             ));
    }

    public function execute(InputInterface $in, OutputInterface $out)
    {
        $debug          = (bool) $in->getArgument('debug');
        $this->interval = $in->getArgument('interval');

        $logger    = new FileLogger('logs/one-off');
        $conn      = $this->getHelper('connection')->getConnection('default');
        $messenger = $this->getHelper('messenger')->getMessenger();

        $batch = self::BATCH_SIZE;
        $type  = Message::CONTACT_TYPE_EMAIL;
        $sql   = <<<SQL
            /* pw send */
            SELECT m.id                       AS id,
                   m.subject                  AS subject,

                   IF (m.contactTypeId = 1 AND NOT (m.sender LIKE '%@%'), 
                       'mailer@petwise.me', m.sender) AS sender,
                   m.senderName               AS senderName,
                   m.body                     AS body,
                   m.queuedAt                 AS queuedAt,
                   m.sentAt                   AS sentAt,
                   m.result                   AS result,
                   m.priority                 AS priority,
                   m.transportId              AS transportId,
                   m.messageTypeId            AS messageTypeId,
                   m.contactTypeId            AS contactTypeId,
                   m.companyId                AS companyId,
                   m.dsid                     AS dsid,
                   m.clientRid                AS clientRid,
                   m.recipientId              AS recipientId,

                   c.firstName                AS firstName,
                   c.lastName                 AS lastName,
                   c.homePhone                AS homePhone,
                   c.mobilePhone              AS mobilePhone,
                   c.workPhone                AS workPhone,

                   IFNULL(r.address, c.email) AS email

            FROM   message AS m
                   LEFT JOIN client AS c
                     ON m.dsid = c.dsid AND m.clientRid = c.rid
                   LEFT JOIN message_recipient AS r
                     ON m.recipientId = r.id 
                     

            WHERE  m.sentAt IS NULL
                   AND m.messageTypeId = {$type}
                   AND m.is_processed = 0
		     AND m.contactTypeId = 1
		     AND m.is_processed = 0

            /* ORDER  BY m.priority DESC */

            LIMIT  0, {$batch}
SQL;

        // Poller loop
        //while(true) {
            // Prepare PDO statement and fetch messages
            $stmt = $conn->executeQuery($sql);

            // Log all messages in /logs
            $messages = array();
            $message_ids = array();

            while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) 
	     {
                $message_ids[] = $row['id'];
                $messages[] = $messenger->createFromArray($row);

		  if ($row['transportId'] == 1)
		  {
			$logger->writeLn(sprintf(
                    	'[%d] [%s] is sending a Petwise message to [%s %s] at [%s]',
                    	$row['id'],
						$row['senderName'],
                    	$row['firstName'],
                    	$row['lastName'],
						$row['email']));
		  }
		  else
		  {
                	$logger->writeLn(sprintf(
                    	'[%d] [%s] is sending an eMinders message to [%s]',
                    	$row['id'],
						$row['senderName'],
                    	$row['email']));
		  }
            }

            $stmt->closeCursor();

            foreach ($message_ids as $msg_id)
	     {
                $conn->executeUpdate('update `message` SET is_processed = 1 where `id` = ?',array($msg_id));
            }

            // Only attempt to queue batch if messages were found
            //if (!empty($messages)) 
	     //{
                // Setup transport adapter
                //if ($debug) 
		  //{
                //    $adapter = new \MssMessage\Transport\Stdout;
                //    $adapter->setDebug(true);
                //} 
		  //else 
		  //{
                    $tr = \Swift_SmtpTransport::newInstance('smtp.elasticemail.com', 2525)
                                                        ->setUsername('petwise.contact@avimark.net')
                                                        ->setPassword('89e7f1ed-611c-4e63-8ad6-2da058ee6ff9');
                                                        
                    $adapter = new \MssMessage\Transport\SwiftMailer($tr);
                //}
                $messenger->setTransportAdapter($adapter);
                $messenger->sendBatch($messages);
            //}

            // Time delay for the loop (saves the precious CPU)
            $this->waitInterval();
        //}
    }
}