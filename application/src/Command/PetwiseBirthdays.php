<?php

namespace Application\Command;

use PDO,
    Application\Logger\File as FileLogger,
    MssMessage\Service\Birthday as BirthdayService,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

class PetwiseBirthdays extends AbstractPoller
{
    protected $pidfile = 'petwise-birthdays.pid';

    protected function configure()
    {
        $this->setName('petwise:birthdays')
             ->setDescription('Queues pending birthdays for PetWise')
             ->setDefinition(array(
                new InputArgument(
                    'debug',
                    InputArgument::OPTIONAL,
                    'If debug mode is enabled nothing is written to the DB and messages are sent to StdOut',
                    true
                ),
             ));
    }

    public function execute(InputInterface $in, OutputInterface $out)
    {
        $debug     = (bool) $in->getArgument('debug');

        $logger    = new FileLogger('logs/petwise/birthdays');
        $conn      = $this->getHelper('connection')->getConnection('default');
        $messenger = $this->getHelper('messenger')->getMessenger();
        $mergeword = $this->getHelper('mergeword')->getMergewordService();
        $service   = new BirthdayService($messenger, $mergeword, new BirthdateMapper($conn));

        $messageTypeId = \MssMessage\Message::MESSAGE_TYPE_BIRTHDAY;
        $contactTypeId = \MssMessage\Message::CONTACT_TYPE_EMAIL;
        $sql = <<<SQL
            SELECT CONCAT(c.firstName, ' ', c.lastName) AS clientFullName,
                   CONCAT(c.firstName, ' ', c.lastName) AS recipientName,
                   c.email                              AS recipientContact,
                   c.rid                                AS clientRid,
                   c.dsid                               AS dsid,
                   c.firstName                          AS clientFirstName,
                   c.lastName                           AS clientLastName,
                   ca.addressLineOne                    AS clientAddressLineOne,
                   ca.addressLineTwo                    AS clientAddressLineTwo,
                   ca.city                              AS clientCity,
                   ca.state                             AS clientState,
                   ca.country                           AS clientCountry,
                   ca.zip                               AS clientZip,

                   p.rid                                AS patientRid,
                   p.dsid                               AS patientDsid,
                   p.name                               AS patientName,
                   p.birthDate                          AS patientBirthDate,
                   p.weight                             AS patientWeight,
                   p.gender                             AS patientGender,
                   p.fixed                              AS patientFixed,
                   pc.description                       AS patientColor,
                   pb.description                       AS patientBreed,
                   ps.description                       AS patientSpecies,

                   cl.rid                               AS clinicRid,
                   cl.name                              AS clinicName,
                   cl.phoneNumber                       AS clinicPhone,
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
                   co.phoneNumber                       AS companyPhone,
                   co.phoneNumber                       AS companyPhoneNumber,
                   coa.addressLineOne                   AS companyAddressLineOne,
                   coa.addressLineTwo                   AS companyAddressLineTwo,
                   coa.city                             AS companyCity,
                   coa.state                            AS companyState,
                   coa.country                          AS companyCountry,
                   coa.zip                              AS companyZip,

                   ml.name                              AS subject,
                   ml.message                           AS body

            FROM   patient AS p
                   LEFT JOIN message_birthdate mb  
                     ON p.dsid = mb.dsid AND p.rid = mb.patient_rid
                   LEFT JOIN breed pb
                     ON pb.dsid = p.dsid AND pb.rid = p.breedRid
                   LEFT JOIN color pc
                     ON pc.dsid = p.dsid AND pc.rid = p.colorRid
                   LEFT JOIN species ps
                     ON ps.dsid = p.dsid AND ps.rid = p.speciesRid
					 
                   LEFT JOIN client AS c
                     ON p.dsid = c.dsid AND p.clientRid = c.rid
                   LEFT JOIN address AS ca
                     ON ca.id = c.addressId
                   LEFT JOIN clinic AS cl
                     ON c.dsid = cl.dsid AND c.defaultClinicRid = cl.rid
                   LEFT JOIN address AS cla
                     ON cla.id = cl.addressId
                   LEFT JOIN data_source AS ds
                     ON cl.dsid = ds.id
                   LEFT JOIN clinic_message_layout AS cml
                     ON cl.dsid = cml.dsid AND cl.rid = cml.clinicRid AND {$messageTypeId}=cml.messageTypeId
                   LEFT JOIN message_layout AS ml
                     ON cml.messageLayoutId = ml.id
                   LEFT JOIN company AS co
                     ON ds.companyId = co.id
                   LEFT JOIN address AS coa
                     ON coa.id = co.addressId

            WHERE  cl.birthdaysEnabled = 1

                   AND DATE_FORMAT(CURRENT_DATE(), '%m-%d') = DATE_FORMAT(p.birthDate - INTERVAL cl.birthdayDaysInAdvance DAY, '%m-%d')

                   AND c.validEmail = 1
                   AND c.inactive = 0
                   AND c.optBirthdays = 0

                   AND p.deceased = 0
                   AND p.moved = 0
                   AND p.inactive = 0
                   AND p.suspendReminders = 0
                   AND (mb.birthday_year IS NULL OR mb.birthday_year != {$year})
            GROUP  BY p.dsid,p.rid
			
            //GROUP  BY p.dsid,
            //          p.rid

            LIMIT  0, 30
SQL;

        $result = $conn->executeQuery($sql)
                       ->fetchAll(PDO::FETCH_ASSOC);

        // Log the results
        foreach($result as $row) {
            $msg = sprintf(
                'rid:[%d] dsid:[%d] (%s) on %s at [%d] (%s)',
                $row['patientRid'],
		  $row['patientDsid'],
                $row['patientName'],
                $row['patientBirthDate'],
                $row['companyId'],
                $row['companyName']
            );

            /*if ($debug) {
                echo $msg . PHP_EOL;
            } else {*/
                $logger->writeLn($msg);
            //}
        }

        // Queue them in message and message_reminder tables
        //if (!$debug) {
            $service->queueBatch($result);
        //}

        unlink('data/' . $this->pidfile);
    }
}