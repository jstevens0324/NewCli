<?php

namespace Application\Command;

use PDO,
    Application\Logger\File as FileLogger,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

class PetwiseNewsletters extends AbstractPoller
{
    protected $pidfile = 'petwise-newsletters.pid';
    protected $interval = 15;

    /**
     * @var MssMessage\Service\Newsletter
     */
    protected $service;

    protected function configure()
    {
        $this->setName('petwise:newsletters')
             ->setDescription('Queues pending newsletters for PetWise')
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

        // Logger for each type of newsletter
        $logger1 = new FileLogger('logs/petwise/newsletters', 'company');
        $logger2 = new FileLogger('logs/petwise/newsletters', 'clinic');
        $logger3 = new FileLogger('logs/petwise/newsletters', 'pims-query');

        // Service for sending newsletters
        $messenger     = $this->getHelper('messenger')->getMessenger();
        $mergeword     = $this->getHelper('mergeword')->getMergewordService();
        $this->service = new \MssMessage\Service\Newsletter($messenger, $mergeword);

        //while(true) {
            $this->send($this->getClinicSql(), $logger2, $debug);
            $this->send($this->getCompanySql(), $logger1, $debug);

            // Time delay for the loop (saves the precious CPU)
            //$this->waitInterval();
        //}

                unlink('data/' . $this->pidfile);
    }

    protected function send($sql, $logger, $debug)
    {
        $conn = $this->getHelper('connection')->getConnection('default');

        // Prepare PDO statement and fetch responses from db
        $result = $conn->executeQuery($sql)
                       ->fetchAll(PDO::FETCH_ASSOC);

        // Only process if a newsletter is found
        if (!empty($result)) {
            // List recipients are additional (todo: merge with original query?)
            $stmt       = $conn->executeQuery($this->getListSql(), array($result[0]['newsletterListId']));
            $listResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $msg = sprintf(
                '[%d] (%s) for [%d] (%s)',
                $result[0]['newsletterId'],
                $result[0]['subject'],
                $result[0]['companyId'],
                $result[0]['companyName']
            );

            if ($debug) {
                echo $msg . PHP_EOL;
            } else {
                $logger->writeLn($msg);
            }

            if (!$debug) {
                $this->service->queueBatch($result, $listResult);

                // Update the newsletter and mark as sent
                $conn->update(
                    'newsletter',
                    array('sent' => 1),
                    array('id' => $result[0]['newsletterId'])
                );
            }
        }
    }

    protected function getListSql()
    {
        return <<<SQL
            SELECT mr.address AS recipientAddress,
                   mr.id      AS recipientId

            FROM   message_recipient AS mr
                   LEFT JOIN newsletter_list_recipient AS nlr
                     ON nlr.recipientId = mr.id

            WHERE  nlr.listId = ?
SQL;
    }

    protected function getCompanySql()
    {
        return <<<SQL
            /* pw news getCo */
            SELECT n.id                                 AS newsletterId,
                   n.subject,
                   n.body,
                   n.listId                             AS newsletterListId,

                   co.id                                AS companyId,
                   co.name                              AS companyName,
                   co.email                             AS companyEmail,
                   co.phoneNumber                       AS companyPhoneNumber,
                   coa.addressLineOne                   AS companyAddressLineOne,
                   coa.addressLineTwo                   AS companyAddressLineTwo,
                   coa.city                             AS companyCity,
                   coa.state                            AS companyState,
                   coa.country                          AS companyCountry,
                   coa.zip                              AS companyZip,

                   cl.rid                               AS clinicRid,
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

                   CONCAT(c.firstName, ' ', c.lastName) AS clientFullName,
                   CONCAT(c.firstName, ' ', c.lastName) AS recipientName,
                   c.email                              AS recipientContact,
                   c.rid                                AS clientRid,
                   c.dsid                               AS dsid,
                   c.email                              AS clientEmail,
                   c.firstName                          AS clientFirstName,
                   c.lastName                           AS clientLastName,
                   ca.addressLineOne                    AS clientAddressLineOne,
                   ca.addressLineTwo                    AS clientAddressLineTwo,
                   ca.city                              AS clientCity,
                   ca.state                             AS clientState,
                   ca.country                           AS clientCountry,
                   ca.zip                               AS clientZip

            FROM   newsletter AS n
                   LEFT JOIN company AS co
                     ON n.companyId = co.id
                   LEFT JOIN data_source AS ds
                     ON ds.companyId = co.id
                   LEFT JOIN client AS c
                     ON ds.id = c.dsid
                   LEFT JOIN clinic AS cl
                     ON c.defaultClinicRid = cl.rid AND cl.dsid = cl.dsid
                   LEFT JOIN address AS ca
                     ON c.addressId = ca.id
                   LEFT JOIN address AS cla
                     ON cl.addressId = cla.id
                   LEFT JOIN address AS coa
                     ON co.addressId = coa.id

            WHERE    n.id = (
                     SELECT n2.id

                     FROM   newsletter AS n2

                     WHERE  n2.sent = 0
                            AND n2.sendDate = CURRENT_DATE()
                            AND n2.pimsQueryScheduleId IS NULL
                            AND n2.published = 1
                            AND n2.dsid IS NULL
                            AND n2.clinicRid IS NULL
                            AND n2.pimsQueryScheduleId IS NULL

                     LIMIT  0,1
                   )
                   AND cl.newslettersEnabled = 1
                   AND c.validEmail = 1
                   AND c.inactive = 0
                   AND c.optNewsletters = 0

            GROUP  BY c.dsid,
                      c.rid
SQL;
    }

    protected function getClinicSql()
    {
        return <<<SQL
            /* pw news getClinic */
            SELECT n.id                                 AS newsletterId,
                   n.subject,
                   n.body,
                   n.listId                             AS newsletterListId,

                   co.id                                AS companyId,
                   co.name                              AS companyName,
                   co.email                             AS companyEmail,
                   co.phoneNumber                       AS companyPhoneNumber,
                   coa.addressLineOne                   AS companyAddressLineOne,
                   coa.addressLineTwo                   AS companyAddressLineTwo,
                   coa.city                             AS companyCity,
                   coa.state                            AS companyState,
                   coa.country                          AS companyCountry,
                   coa.zip                              AS companyZip,

                   cl.rid                               AS clinicRid,
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

                   CONCAT(c.firstName, ' ', c.lastName) AS clientFullName,
                   c.rid                                AS clientRid,
                   c.dsid                               AS dsid,
                   c.email                              AS clientEmail,
                   c.firstName                          AS clientFirstName,
                   c.lastName                           AS clientLastName,
                   ca.addressLineOne                    AS clientAddressLineOne,
                   ca.addressLineTwo                    AS clientAddressLineTwo,
                   ca.city                              AS clientCity,
                   ca.state                             AS clientState,
                   ca.country                           AS clientCountry,
                   ca.zip                               AS clientZip

            FROM   newsletter AS n
                   LEFT JOIN company AS co
                     ON n.companyId = co.id
                   LEFT JOIN data_source AS ds
                     ON n.dsid = ds.id
                   LEFT JOIN client AS c
                     ON ds.id = c.dsid
                   LEFT JOIN clinic AS cl
                     ON c.defaultClinicRid = cl.rid AND cl.dsid = cl.dsid
                   LEFT JOIN address AS ca
                     ON c.addressId = ca.id
                   LEFT JOIN address AS cla
                     ON cl.addressId = cla.id
                   LEFT JOIN address AS coa
                     ON co.addressId = coa.id

            WHERE  n.id = (
                     SELECT n2.id

                     FROM   newsletter AS n2

                     WHERE  n2.sent = 0
                            AND n2.sendDate = CURRENT_DATE()
                            AND n2.pimsQueryScheduleId IS NULL
                            AND n2.published = 1
                            AND n2.dsid IS NOT NULL
                            AND n2.clinicRid IS NOT NULL
                            AND n2.pimsQueryScheduleId IS NULL

                     LIMIT  0,1
                   )
                   AND cl.newslettersEnabled = 1
                   AND c.validEmail = 1
                   AND c.inactive = 0

            GROUP  BY c.dsid,
                      c.rid
SQL;
    }

}