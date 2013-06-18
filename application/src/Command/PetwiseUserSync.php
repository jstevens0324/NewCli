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

class PetwiseUserSync extends AbstractPoller
{
    protected $pidfile         = 'petwise-user-sync.pid';
    protected $layout          = null;
    protected $communityLayout = null;

    protected function configure()
    {
        $this->setName('petwise:user-sync')
             ->setDescription('Syncs user clients to user accounts and the user_client table')
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
                    30
                ),
             ));
    }

    public function execute(InputInterface $in, OutputInterface $out)
    {
        $debug          = (bool) $in->getArgument('debug');
        $this->interval = $in->getArgument('interval');

        $this->layout          = file_get_contents(__DIR__ . '/../../../vendor/MssMessage/layout/user-sync/email.html');
        $this->communityLayout = file_get_contents(__DIR__ . '/../../../vendor/MssMessage/layout/user-sync/email-community.html');

        $logger    = new FileLogger('logs/petwise/user-sync');
        $conn      = $this->getHelper('connection')->getConnection('default');
        $mergeword = $this->getHelper('mergeword')->getMergewordService();
        $messenger = $this->getHelper('messenger')->getMessenger();

        // Poller loop
        //while(true) {
            // All updates done in a transaction for rollbacks/speed
            $conn->beginTransaction();

            // This section is for NEW user accounts.
            // Any emails found that match an email in the user table will
            // be added to the user_client table.
            $stmt = $conn->executeQuery($this->getCreateUserSql());

            // An in-memory cache array to store email => userId's
            $userEmailIdCache = array();

            $messages = array();
            while(($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                $userId = (isset($userEmailIdCache['clientEmail']) ? $userEmailIdCache['clientEmail'] : null);

                if (null === $userId) {
                    // Does a user exist already?
                    $sql  = 'SELECT id FROM user WHERE email = ? AND companyId = ?';
                    $stmt2 = $conn->executeQuery(
                        $sql,
                        array(
                            $row['clientEmail'],
                            $row['companyId'],
                        )
                    );
                    $user = $stmt2->fetch(PDO::FETCH_ASSOC);

                    // Create a new user if one wasn't found.
                    if ($user) {
                        $userId = $user['id'];
                    } else {
                        $data     = $this->createUser($row);
                        $userId   = $data['id'];
                        $password = $data['password'];

                        $msg = sprintf(
                            'creating account for %s at (%d) %s',
                            $row['clientFullName'],
                            $row['companyId'],
                            $row['companyName']
                        );

                        if ($debug) {
                            echo $msg . PHP_EOL;
                        } else {
                            $logger->writeLn($msg);
                        }

                        $layout  = $row['websiteIsCommunity'] ? $this->communityLayout : $this->layout;
                        $row     = array_merge(array('password' => $password), $row);
                        $subject = $row['subject'] ? $row['subject'] : sprintf('PetProfile created at %s', $row['clinicName']);
                        $body    = $row['body'] ? $row['body'] : $layout;

                        $set     = $mergeword->findByCompanyId($row['companyId']);
                        $subject = $mergeword->mergeFromArray($subject, $set, $row);
                        $body    = $mergeword->mergeFromArray($body, $set, $row);

                        $recipient = new Recipient;
                        $recipient->setClientRid($row['clientRid'])
                                  ->setDsid($row['dsid']);

                        $message = new Message;
                        $message->setCompanyId($row['companyId'])
                                ->setSubject($subject)
                                ->setSender($row['websiteEmail'])
                                ->setSenderName($row['websiteName'])
                                ->setMessageTypeId(Message::MESSAGE_TYPE_ACCOUNT_CREATED)
                                ->setContactTypeId(Message::CONTACT_TYPE_EMAIL)
                                ->setRecipient($recipient)
                                ->setBody($body);

                        $messages[] = $message;
                    }

                    // Link to user_client table
                    $row['userId'] = $userId;

                    $msg = sprintf(
                        'linking (%d) user to client (%d:%d) at (%d) %s',
                        $userId,
                        $row['dsid'],
                        $row['clientRid'],
                        $row['companyId'],
                        $row['companyName']
                    );

                    if ($debug) {
                        echo $msg . PHP_EOL;
                    } else {
                        $this->createUserClientLink($row);
                        $logger->writeLn($msg);
                    }

                    $userEmailIdCache[$row['clientEmail']] = $userId;
                }
            }

            // Queue up any create user messages
            if (!empty($messages)) {
                $messenger->queueBatch($messages);
            }

            // This section is for SYNCING all clients to users.
            // Only pertains to emails in client that don't match the respective user
            // entry.
            $stmt = $conn->executeQuery($this->getUpdatedEmailSql());
            $userIdEmailCache = array();

            while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                // Update the user to the new client email
                $conn->update(
                    'user',
                    array('email' => $row['clientEmail']),
                    array('id' => $row['userId'])
                );

                // Add rows to the push table so that the VetLogic client can update them appropriately.
                $sql = <<<SQL
                    SELECT c.rid,
                           c.dsid

                    FROM   client AS c
                           LEFT JOIN user_client AS uc
                             ON uc.dsid = c.dsid AND uc.clientRid = c.rid

                    WHERE  c.email != ?
                           AND uc.userId = ?
SQL;
                $stmt = $conn->executeQuery(
                    $sql,
                    array($row['clientEmail'], $row['userId']),
                    array(PDO::PARAM_STR, PDO::PARAM_INT)
                );
                $pushes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Insert the pushes
                foreach($pushes as $push) {
                    $conn->insert(
                        'client_push',
                        array(
                            'dsid'      => $push['dsid'],
                            'clientRid' => $push['rid'],
                            'email'     => $row['clientEmail']
                        )
                    );
                }

                // Update all references of the client in user_client to the new email using one query.
                $sql = <<<SQL
                    UPDATE client AS c
                           LEFT JOIN user_client AS uc
                             ON uc.dsid = c.dsid AND uc.clientRid = c.rid
                    SET    c.email = ?

                    WHERE  uc.userId = ?
SQL;
                $conn->executeUpdate(
                    $sql,
                    array($row['clientEmail'], $row['userId']),
                    array(PDO::PARAM_STR, PDO::PARAM_INT)
                );
            }

            // Commit the transaction
            if ($debug) {
                $conn->rollback();
            } else {
                $conn->commit();
            }

            // Time delay for the loop (saves the precious CPU)
            //$this->waitInterval();
        //}

        unlink('data/' . $this->pidfile);
    }

    /**
     * Creates a new user in the database and returns the user id from the new user.
     *
     * @param array $data
     * @return int
     */
    protected function createUser(array $data)
    {
        $conn = $this->getHelper('connection')->getConnection('default');

        $password = $this->createPassword();
        $udata = array(
            'groupId'   => 3, // Guest permissions, by default
            'companyId' => $data['companyId'],
            'email'     => $data['clientEmail'],
            'firstName' => $data['clientFirstName'],
            'lastName'  => $data['clientLastName'],
            'password'  => md5($password)
        );
        $conn->insert('user', $udata);

        return array('id' => $conn->lastInsertId(), 'password' => $password);
    }

    /**
     * Creates a user client link.
     *
     * @param array $data
     */
    protected function createUserClientLink(array $data)
    {
        $conn  = $this->getHelper('connection')->getConnection('default');
        $udata = array(
            'userId'    => $data['userId'],
            'dsid'      => $data['dsid'],
            'clientRid' => $data['clientRid']
        );
        $conn->insert('user_client', $udata);
    }

    protected function getUpdatedEmailSql()
    {
        return <<<SQL
            SELECT c.email AS clientEmail,

                   u.id    AS userId,
                   u.email AS userEmail

            FROM   client AS c
                   RIGHT JOIN user_client AS uc
                     ON uc.dsid = c.dsid AND uc.clientRid = c.rid
                   RIGHT JOIN user AS u
                     ON uc.userId = u.id
                   RIGHT JOIN data_source AS ds
                     ON c.dsid = ds.id

            WHERE  c.validEmail = 1
                   AND c.inactive = 0
                   AND c.suspendReminders = 0
                   AND ds.syncUserClientEnabled = 1
                   AND c.email != u.email

            GROUP  BY c.dsid, c.email
SQL;
    }

    protected function getCreateUserSql()
    {
        $messageTypeId = Message::MESSAGE_TYPE_ACCOUNT_CREATED;
        $contactTypeId = Message::CONTACT_TYPE_EMAIL;
        return <<<SQL
            SELECT DISTINCT
                   CONCAT(c.firstName, ' ', c.lastName) AS recipientName,
                   c.email                              AS recipientContact,

                   CONCAT(c.firstName, ' ', c.lastName) AS clientFullName,
                   c.rid                                AS clientRid,
                   c.dsid                               AS dsid,
                   c.firstName                          AS clientFirstName,
                   c.lastName                           AS clientLastName,
                   c.email                              AS clientEmail,
                   ca.addressLineOne                    AS clientAddressLineOne,
                   ca.addressLineTwo                    AS clientAddressLineTwo,
                   ca.city                              AS clientCity,
                   ca.state                             AS clientState,
                   ca.country                           AS clientCountry,
                   ca.zip                               AS clientZip,

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

                   ws.name                              AS websiteName,
                   ws.slogan                            AS websiteSlogan,
                   ws.email                             AS websiteEmail,
                   CONCAT('http://', wsd.uri)           AS websiteUrl,

                   IF(wsf.featureId = 3, 1, 0)          AS websiteIsCommunity,

                   ml.name                              AS subject,
                   ml.message                           AS body

            FROM   client AS c
                   LEFT JOIN address AS ca
                     ON ca.id = c.addressId
                   LEFT JOIN user_client AS uc
                     ON uc.clientRid = c.rid AND uc.dsid = c.dsid
                   LEFT JOIN user AS u
                     ON uc.userId = u.id
                   LEFT JOIN data_source AS ds
                     ON c.dsid = ds.id
                   LEFT JOIN company AS co
                     ON ds.companyId = co.id
                   LEFT JOIN address AS coa
                     ON coa.id = co.addressId
                   LEFT JOIN clinic AS cl
                     ON cl.dsid = ds.id AND c.defaultclinicRid = cl.rid
                   LEFT JOIN address AS cla
                     ON cla.id = cl.addressId
                   LEFT JOIN data_source_website AS dsw
                     ON ds.id = dsw.dsid
                   LEFT JOIN website AS ws
                     ON dsw.websiteId = ws.id
                   LEFT JOIN website_domain AS wsd
                     ON wsd.websiteId = ws.id
                   LEFT JOIN patient AS p
                     ON c.dsid = p.dsid AND p.clientRid = c.rid
                   LEFT JOIN clinic_message_layout AS cml
                     ON cl.dsid = cml.dsid AND cl.rid = cml.clinicRid AND {$messageTypeId}=cml.messageTypeId
                   LEFT JOIN message_layout AS ml
                     ON cml.messageLayoutId = ml.id
                   LEFT JOIN website_feature AS wsf
                     ON wsf.websiteId = ws.id

            WHERE  u.id IS NULL
                   AND c.validEmail = 1
                   AND c.inactive = 0
                   AND c.suspendReminders = 0
                   AND ds.createUsersEnabled = 1
                   AND p.deceased = 0
                   AND p.inactive = 0
                   AND p.suspendReminders = 0
                   AND p.moved = 0
                   AND ws.id IS NOT NULL
                   AND p.rid IS NOT NULL
                   AND wsd.main = 1

            LIMIT  0, 30
SQL;
    }

    protected function createPassword()
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';

        $pword = '';
        for ($i = 0; $i < 6; $i++) {
            $pword .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $pword;
    }
}