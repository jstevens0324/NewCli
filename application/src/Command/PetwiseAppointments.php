<?php

namespace Application\Command;

use PDO,
    Application\Logger\File as FileLogger,
    MssMessage\Mapper\Appointment\DoctrineDbal as AppointmentMapper,
    MssMessage\Service\Appointment as AppointmentService,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

class PetwiseAppointments extends AbstractPoller
{
    protected $pidfile = 'petwise-appointments.pid';

    protected function configure()
    {
        $this->setName('petwise:appointments')
             ->setDescription('Queues pending appointments for PetWise')
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

        $logger    = new FileLogger('logs/appointments');
        $conn      = $this->getHelper('connection')->getConnection('default');
        $messenger = $this->getHelper('messenger')->getMessenger();
        $mergeword = $this->getHelper('mergeword')->getMergewordService();
        $service   = new AppointmentService($messenger, $mergeword, new AppointmentMapper($conn));

        $messageTypeId = \MssMessage\Message::MESSAGE_TYPE_APPOINTMENT;
        $contactTypeId = \MssMessage\Message::CONTACT_TYPE_EMAIL;
        $sql = <<<SQL
            /* pw appt */
            SELECT a.rid                                AS appointmentRid,
                   a.notes                              AS appointmentNotes,
                   a.activeStartDate                    AS appointmentStartDate,
                   DATE_FORMAT(a.activeStartTime,'%h:%i:%s %p') AS appointmentStartTime,

                   CONCAT(c.firstName, ' ', c.lastName) AS clientFullName,
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

            FROM   appointment AS a
                   LEFT JOIN message_appointment msa
                     ON a.dsid = msa.dsid AND a.rid = msa.appointmentRid
                   LEFT JOIN patient p
                     ON a.dsid = p.dsid AND a.patientRid = p.rid
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
                     ON c.dsid = cl.dsid AND a.clinicRid = cl.rid
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

            WHERE  cl.autoAppointmentReminders = 1

                   AND c.validEmail = 1
                   AND c.inactive = 0
                   AND c.optAppointments = 0

                   AND p.deceased = 0
                   AND p.moved = 0
                   AND p.inactive = 0
                   AND p.suspendReminders = 0

                   AND a.enabled = 1
                     AND a.confirmed IS NULL
                   AND (
                     (
                       (
                         a.activeStartDate = CURRENT_DATE()
                         AND a.activeStartTime > CURRENT_TIME()
                       ) OR (
                         a.activeSTartDate > CURRENT_DATE()
                       )
                     ) AND (
                       a.activeStartDate <= CURRENT_DATE() + INTERVAL cl.appointmentRemindersDaysInAdvance DAY
                     )
                   )

            GROUP  BY a.dsid,
                      a.rid

            HAVING COUNT(msa.appointmentRid) = 0

            LIMIT  0, 30
SQL;

        //while(true) {
            $result = $conn->executeQuery($sql)
                           ->fetchAll(PDO::FETCH_ASSOC);

            // Log the results
            foreach($result as $row) 
	     {
                $msg = sprintf(
                    "\t%s\t%s\t%s\t%s\t%s\t%s\t%s",
                    $row['dsid'],
                    $row['companyId'],
     		      $row['clinicRid'],
                    $row['appointmentRid'],
                    $row['clientFullName'],
                    $row['companyName'],
                    $row['appointmentStartDate']
                );

                //if ($debug) {
                //    echo $msg . PHP_EOL;
                //} else {
                    $logger->writeLn($msg);
                //}
            }

            // Queue them in message and message_reminder tables
            //if (!$debug) {
                $service->queuePetwiseBatch($result);
            //}

            // Interval delay
            //$this->waitInterval();
        //}

        unlink('data/' . $this->pidfile);
    }
}