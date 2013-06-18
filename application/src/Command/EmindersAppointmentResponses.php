<?php

namespace Application\Command;

use PDO,
    Application\Logger\File as FileLogger,
    MssMessage\Mapper\Appointment\DoctrineDbal as AppointmentMapper,
    MssMessage\Service\Appointment as AppointmentService,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

class EmindersAppointmentResponses extends AbstractPoller
{
    protected $pidfile = 'eminders-appointment-responses.pid';

    protected function configure()
    {
        $this->setName('eminders:appointment-responses')
             ->setDescription('Reads appointment responses from eMinders and upates the database')
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

        $logger    = new FileLogger('logs/eminders/appointment-responses');
        $conn      = $this->getHelper('connection')->getConnection('default');
        $sendza    = new \MssSendza\Service\Sendza;

        $sql = <<<SQL
            SELECT a.dsid,
                   a.rid    AS appointmentRid,
                   m.sender AS sendzaId,
                   m.result

            FROM   message_appointment AS ma
                   LEFT JOIN message AS m
                     ON ma.messageId = m.id
                   LEFT JOIN appointment AS a
                     ON ma.dsid = a.dsid AND ma.appointmentRid = a.rid
                   LEFT JOIN patient AS p
                     ON p.dsid = a.dsid AND p.rid = a.patientRid
                   LEFT JOIN client AS c
                     ON c.dsid = a.dsid AND c.rid = p.clientRid
                   LEFT JOIN clinic AS cl
                     ON c.dsid = a.dsid AND c.defaultClinicRid = cl.rid

            WHERE  a.enabled = 1
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
                   AND ma.responded = 0

            GROUP  BY ma.dsid,
                   ma.appointmentRid,
                   ma.messageId

            LIMIT  0,30
SQL;

        while(true) {
            // All updates done in a transaction for rollbacks/speed
            $conn->beginTransaction();

            // Prepare PDO statement and fetch responses from db
            $stmt = $conn->executeQuery($sql);

            while(($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                // If no response is recorded then they haven't clicked the link in the email yet
                // so skip it for now.
                if (!preg_match('/"Group":\{"Id":"([\w\d]+)"/', $row['result'], $matches)) {
                    continue;
                }

                // Set the tokens for the Sendza API request
                $sendza->setTokens(array(
                    'accountId' => $row['sendzaId'],
                    'groupId'   => $matches[1]
                ));
                $result = $sendza->execute('responses.list.group');

                // Snag the value from the result
                if (preg_match('/"Value":"([\w\d]+)"/', $result, $matches)) {
                    $logger->writeLn(sprintf(
                        'checking response for appointment [%d:%d]',
                        $row['dsid'],
                        $row['appointmentRid']
                    ));

                    $logger->writeLn(sprintf(
                        '    read response as "%s"',
                        $matches[1]
                    ));

                    // Convert the text-based Sendza value into our database values
                    switch($matches[1]) {
                        case 'decline':
                        case 'cancel':
                            $value = 0;
                            break;
                        case 'confirm':
                            $value = 1;
                            break;
                        case 'reschedule':
                            $value = 2;
                            break;
                    }

                    // Update the appropriate tables
                    $conn->update(
                        'message_appointment',
                        array('responded' => 1),
                        array('dsid' => $row['dsid'], 'appointmentRid' => $row['appointmentRid'])
                    );

                    $conn->update(
                        'appointment',
                        array('confirmed' => $value),
                        array('dsid' => $row['dsid'], 'rid' => $row['appointmentRid'])
                    );
                }
            }

            // Commit the transaction
            $conn->commit();

            // Time delay for the loop (saves the precious CPU)
            $this->waitInterval();
        }
    }
}