<?php

namespace Application\Command;

use PDO,
    Application\Logger\File as FileLogger,
    MssMessage\Mapper\Winusers\DoctrineDbal as WinusersMapper,
    MssMessage\Service\Winusers as WinusersService,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

class EmindersWinuserBatch extends AbstractPoller
{
    protected $pidfile = 'eminders-winuser-batch.pid';

    protected function configure()
    {
        $this->setName('eminders:winuser-batch')
             ->setDescription('Sends WinUser batches stored in message_winuser_batch')
             ->setDefinition(array(
                new InputArgument(
                    'debug',
                    InputArgument::OPTIONAL,
                    'If debug mode is enabled nothing is written to the DB and messages are sent to StdOut',
                    true
                ),
                new InputArgument(
                    'interval',
                    InputArgument::OPTIONAL,
                    'The polling interval, in seconds, to process a batch',
                    30
                ),
             ));
    }

    public function execute(InputInterface $in, OutputInterface $out)
    {
        $debug          = (bool) $in->getArgument('debug');
        $this->interval = $in->getArgument('interval');


        $logger    = new FileLogger('logs/batch');
        $conn      = $this->getHelper('connection')->getConnection('default');
        $messenger = $this->getHelper('messenger')->getMessenger();
        $mergeword = $this->getHelper('mergeword')->getMergewordService();
        $service   = new WinusersService($messenger, $mergeword, new WinusersMapper($conn));

        $sql = <<<SQL
            SELECT cl.sendzaId,

                   b.id,
                   b.dsid,
                   b.subject,
                   b.patients,
                   b.contactTypeId,
                   ml.message                           AS body,

                   cl.name                              AS clinicName,
                   cl.phoneNumber                       AS clinicPhoneNumber,
                   cl.faxNumber                         AS clinicFaxNumber,
                   cl.email                             AS clinicEmail,
                   cla.addressLineOne                   AS clinicAddressLineOne,
                   cla.addressLineTwo                   AS clinicAddressLineTwo,
                   cla.city                             AS clinicCity,
                   cla.state                            AS clinicState,
                   cla.country                          AS clinicCountry,
                   cla.zip                              AS clinicZip,

                   co.id                                AS companyId,
                   co.name                              AS companyName,
                   co.email                             AS companyEmail,
                   co.phoneNumber                       AS companyPhoneNumber,
                   coa.addressLineOne                   AS companyAddressLineOne,
                   coa.addressLineTwo                   AS companyAddressLineTwo,
                   coa.city                             AS companyCity,
                   coa.state                            AS companyState,
                   coa.country                          AS companyCountry,
                   coa.zip                              AS companyZip

            FROM   message_winuser_batch AS b
                   LEFT JOIN clinic AS cl
                     ON b.dsid = cl.dsid AND b.clinicRid = cl.rid
                   LEFT JOIN address AS cla
                     ON cla.id = cl.addressId
                   LEFT JOIN data_source AS ds
                     ON cl.dsid = ds.id
                   LEFT JOIN message_layout AS ml
                     ON b.messageLayoutId = ml.id
                   LEFT JOIN company AS co
                     ON ds.companyId = co.id
                   LEFT JOIN address AS coa
                     ON coa.id = co.addressId

            LIMIT  0,1
SQL;

        //while(true) {
            // Prepare PDO statement and fetch responses from db
            $row = $conn->executeQuery($sql)->fetch(PDO::FETCH_ASSOC);

            if (!empty($row)) {
                $id = $row['id'];
                unset($row['id']);
                
		// Queue the batch using the service.
                // A batch, in this case, consists of a single row of data because patients is a
                // serialized array and could contain a large amount of patients.
                $service->queueBatch($row);

                // Remove the batch, it's processed
                $conn->delete('message_winuser_batch', array('id' => $id));
            }

            // Time delay for the loop (saves the precious CPU)
            //$this->waitInterval();
        //}

        unlink('data/' . $this->pidfile);
    }
}
